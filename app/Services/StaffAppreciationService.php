<?php

namespace App\Services;

use App\Exceptions\StaffAppreciationException;
use App\Models\Nomination;
use App\Models\NominationParticipation;
use App\Models\NominationPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StaffAppreciationService
{
    /**
     * Day of month the nomination window opens.
     */
    public const OPEN_DAY = 26;

    /**
     * Resolve the nomination period for "now" when the calendar window is active,
     * lazily creating the row on first access. Returns null outside the window.
     *
     * The window runs from the 26th at 00:00 to the last day of the month at 23:59,
     * evaluated in the application timezone.
     */
    public function currentOpenPeriod(?Carbon $now = null): ?NominationPeriod
    {
        $now ??= $this->now();

        // Honor any period explicitly open right now. This supports windows that
        // were opened early/manually (e.g. via openNow) in addition to the
        // automatic monthly window.
        $active = NominationPeriod::query()
            ->where('status', NominationPeriod::STATUS_OPEN)
            ->where('opens_at', '<=', $now)
            ->where('closes_at', '>=', $now)
            ->orderByDesc('opens_at')
            ->first();

        if ($active) {
            return $active;
        }

        // Otherwise auto-open during the standard monthly window (26th -> month end).
        [$opensAt, $closesAt] = $this->windowFor($now);

        if ($now->lt($opensAt) || $now->gt($closesAt)) {
            return null;
        }

        return NominationPeriod::firstOrCreate(
            ['year' => (int) $now->year, 'month' => (int) $now->month],
            [
                'opens_at' => $opensAt,
                'closes_at' => $closesAt,
                'status' => NominationPeriod::STATUS_OPEN,
            ]
        );
    }

    /**
     * Open (or re-open) a nomination window immediately. Useful to start a cycle
     * on demand (e.g. a catch-up window) when the automatic 26th window has not
     * yet arrived or was missed.
     *
     * @param  Carbon|null  $closesAt  When the window should close (default: end of the current month).
     * @param  int|null  $year  Period year key (default: current). Use a distinct key (e.g. the missed
     *                          month) so a catch-up window does not collide with the automatic month.
     * @param  int|null  $month  Period month key (default: current).
     */
    public function openNow(?Carbon $now = null, ?Carbon $closesAt = null, ?int $year = null, ?int $month = null): NominationPeriod
    {
        $now ??= $this->now();
        $year ??= (int) $now->year;
        $month ??= (int) $now->month;
        $closesAt ??= $now->copy()->endOfMonth();

        $period = NominationPeriod::firstOrNew(['year' => $year, 'month' => $month]);

        if (! $period->exists || ! $period->opens_at || $period->opens_at->gt($now)) {
            $period->opens_at = $now;
        }

        $period->closes_at = $closesAt;
        $period->status = NominationPeriod::STATUS_OPEN;
        $period->save();

        return $period;
    }

    /**
     * Whether the given user should be prompted to nominate right now.
     */
    public function shouldPromptUser(User $user, ?Carbon $now = null): bool
    {
        if (($user->status ?? '') !== 'active') {
            return false;
        }

        $period = $this->currentOpenPeriod($now);

        if (! $period) {
            return false;
        }

        return ! $this->hasResponded($period, $user);
    }

    public function hasResponded(NominationPeriod $period, User $user): bool
    {
        return NominationParticipation::query()
            ->where('nomination_period_id', $period->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Record an anonymous nomination for the acting user.
     *
     * Two rows are written in a single transaction: a participation row (who
     * responded) and a nomination row (the anonymous nominee + reason). They share
     * no linking column, so the nominator can never be derived from a nomination.
     *
     * @throws StaffAppreciationException
     */
    public function recordNomination(User $actor, User $nominee, ?string $reason = null, ?NominationPeriod $period = null): NominationPeriod
    {
        $period ??= $this->currentOpenPeriod();

        if (! $period) {
            throw StaffAppreciationException::windowClosed();
        }

        $reason = $this->normalizeReason($reason);

        return DB::transaction(function () use ($period, $actor, $nominee, $reason) {
            $alreadyResponded = NominationParticipation::query()
                ->where('nomination_period_id', $period->id)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyResponded) {
                throw StaffAppreciationException::alreadyResponded();
            }

            NominationParticipation::create([
                'nomination_period_id' => $period->id,
                'user_id' => $actor->id,
                'status' => NominationParticipation::STATUS_NOMINATED,
            ]);

            Nomination::create([
                'nomination_period_id' => $period->id,
                'nominee_id' => $nominee->id,
                'reason' => $reason,
            ]);

            return $period;
        });
    }

    /**
     * Mark that the user has opted out for the current month. Idempotent: if the
     * user has already responded (nominated or skipped) this is a no-op.
     *
     * @throws StaffAppreciationException
     */
    public function skip(User $actor, ?NominationPeriod $period = null): NominationPeriod
    {
        $period ??= $this->currentOpenPeriod();

        if (! $period) {
            throw StaffAppreciationException::windowClosed();
        }

        NominationParticipation::firstOrCreate(
            [
                'nomination_period_id' => $period->id,
                'user_id' => $actor->id,
            ],
            [
                'status' => NominationParticipation::STATUS_SKIPPED,
            ]
        );

        return $period;
    }

    /**
     * Ensure the current period exists (when the window is open) and close any
     * periods whose window has ended. Used by the scheduled command.
     *
     * @return array{closed:int, ensured:?NominationPeriod}
     */
    public function ensureAndCloseFor(?Carbon $now = null): array
    {
        $now ??= $this->now();

        $closed = 0;
        NominationPeriod::query()
            ->where('status', NominationPeriod::STATUS_OPEN)
            ->where('closes_at', '<', $now)
            ->get()
            ->each(function (NominationPeriod $period) use (&$closed): void {
                $period->update(['status' => NominationPeriod::STATUS_CLOSED]);
                $closed++;
            });

        $ensured = $this->currentOpenPeriod($now);

        return ['closed' => $closed, 'ensured' => $ensured];
    }

    /**
     * Participation statistics for a period.
     *
     * @return array{eligible:int, responded:int, nominated:int, skipped:int, rate:float}
     */
    public function participationStats(NominationPeriod $period): array
    {
        $eligible = User::query()->where('status', 'active')->count();

        $nominated = NominationParticipation::query()
            ->where('nomination_period_id', $period->id)
            ->where('status', NominationParticipation::STATUS_NOMINATED)
            ->count();

        $skipped = NominationParticipation::query()
            ->where('nomination_period_id', $period->id)
            ->where('status', NominationParticipation::STATUS_SKIPPED)
            ->count();

        $responded = $nominated + $skipped;

        return [
            'eligible' => $eligible,
            'responded' => $responded,
            'nominated' => $nominated,
            'skipped' => $skipped,
            'rate' => $eligible > 0 ? round(($responded / $eligible) * 100, 1) : 0.0,
        ];
    }

    /**
     * Nominees for a period ranked by number of nominations received.
     *
     * @return Collection<int, array{user:?User, votes:int}>
     */
    public function nomineeTallies(NominationPeriod $period): Collection
    {
        $rows = Nomination::query()
            ->selectRaw('nominee_id, COUNT(*) as votes')
            ->where('nomination_period_id', $period->id)
            ->groupBy('nominee_id')
            ->orderByDesc('votes')
            ->get();

        $users = User::query()
            ->whereIn('id', $rows->pluck('nominee_id')->all())
            ->get()
            ->keyBy('id');

        return $rows->map(fn ($row) => [
            'user' => $users->get($row->nominee_id),
            'votes' => (int) $row->votes,
        ]);
    }

    /**
     * Anonymous nomination reasons for a period (nominee + reason, never nominator).
     *
     * @return Collection<int, Nomination>
     */
    public function reasons(NominationPeriod $period): Collection
    {
        return Nomination::query()
            ->with('nominee')
            ->where('nomination_period_id', $period->id)
            ->whereNotNull('reason')
            ->where('reason', '!=', '')
            ->latest()
            ->get();
    }

    /**
     * All periods newest-first, for history browsing.
     *
     * @return Collection<int, NominationPeriod>
     */
    public function periodsForHistory(): Collection
    {
        return NominationPeriod::query()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
    }

    /**
     * The calendar month immediately before the given period, if a row exists.
     */
    public function previousPeriodOf(NominationPeriod $period): ?NominationPeriod
    {
        $anchor = Carbon::create($period->year, $period->month, 1)->subMonth();

        return NominationPeriod::query()
            ->where('year', $anchor->year)
            ->where('month', $anchor->month)
            ->first();
    }

    /**
     * @return array{0: Carbon, 1: Carbon} [opensAt, closesAt]
     */
    private function windowFor(Carbon $now): array
    {
        $opensAt = $now->copy()->startOfMonth()->setDay(self::OPEN_DAY)->startOfDay();
        $closesAt = $now->copy()->endOfMonth();

        return [$opensAt, $closesAt];
    }

    private function normalizeReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $reason = trim($reason);

        return $reason === '' ? null : $reason;
    }

    private function now(): Carbon
    {
        return Carbon::now(config('app.timezone'));
    }
}
