<?php

namespace App\Http\Controllers;

use App\Models\ReminderDispatchLog;
use App\Services\NotificationService;
use App\Services\ReminderConfigService;
use App\Services\WeeklyGracePeriodNotificationDispatcher;
use App\Services\WeeklySubmissionReminderDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(NotificationService $notificationService, ReminderConfigService $reminderConfigService)
    {
        $smsInfo = $notificationService->getSmsAccountInfo();
        $reminderConfig = $reminderConfigService->getWeeklyPlanReportConfig();
        $graceConfig = $reminderConfigService->getWeeklyGracePeriodConfig();

        $recentReminderLogs = ReminderDispatchLog::query()
            ->with(['user:id,name,email'])
            ->whereIn('reminder_config_id', [$reminderConfig->id, $graceConfig->id])
            ->latest()
            ->limit(25)
            ->get()
            ->map(function (ReminderDispatchLog $log) use ($graceConfig) {
                $log->setAttribute(
                    'dispatch_type_label',
                    $log->reminder_config_id === $graceConfig->id
                        ? 'Grace period'
                        : 'Scheduled reminder'
                );

                return $log;
            });

        $lastReminderSentAt = ReminderDispatchLog::query()
            ->where('reminder_config_id', $reminderConfig->id)
            ->latest('created_at')
            ->value('created_at');

        $lastGraceSentAt = ReminderDispatchLog::query()
            ->where('reminder_config_id', $graceConfig->id)
            ->latest('created_at')
            ->value('created_at');

        return view('notifications.index', compact(
            'smsInfo',
            'reminderConfig',
            'graceConfig',
            'recentReminderLogs',
            'lastReminderSentAt',
            'lastGraceSentAt',
        ));
    }

    public function updateGracePeriod(Request $request, ReminderConfigService $reminderConfigService)
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
        ]);

        $config = $reminderConfigService->updateWeeklyGracePeriodConfig([
            'enabled' => (bool) ($validated['enabled'] ?? false),
        ]);

        return redirect()
            ->route('notification.index')
            ->with('success', "Grace period email alerts updated for {$config->name}.");
    }

    public function updateWeeklyReminder(Request $request, ReminderConfigService $reminderConfigService)
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'time' => ['required', 'date_format:H:i'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:email,in_app'],
        ]);

        $config = $reminderConfigService->updateWeeklyPlanReportConfig([
            'enabled' => (bool) ($validated['enabled'] ?? false),
            'day_of_week' => (int) $validated['day_of_week'],
            'time' => $validated['time'],
            'timezone' => config('app.timezone', 'UTC'),
            'channels' => array_values(array_unique($validated['channels'])),
            'criteria_rules' => ['mode' => 'separate_per_activity'],
        ]);

        return redirect()
            ->route('notification.index')
            ->with('success', "Reminder settings updated for {$config->name}.");
    }

    public function markRead(Request $request, string $notificationId): RedirectResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $notificationId)
            ->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return back();
    }

    public function inbox(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $unreadCount = $request->user()->unreadNotifications()->count();

        return view('notifications.inbox', compact('notifications', 'unreadCount'));
    }

    public function open(Request $request, string $notificationId): RedirectResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $notificationId)
            ->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $data = is_array($notification->data) ? $notification->data : [];
        $actionUrl = $data['action_url'] ?? route('notifications.inbox');

        return redirect()->to($actionUrl);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function runWeeklyReminderNow(WeeklySubmissionReminderDispatcher $dispatcher): RedirectResponse
    {
        $result = $dispatcher->dispatchNow();
        $sent = (int) ($result['sent'] ?? 0);
        $targets = (int) ($result['targets'] ?? 0);

        return redirect()
            ->route('notification.index')
            ->with('success', "Manual reminder run completed. Sent to {$sent} recipient activity rows out of {$targets} targets.");
    }

    public function runGracePeriodNow(WeeklyGracePeriodNotificationDispatcher $dispatcher): RedirectResponse
    {
        $result = $dispatcher->dispatchNow();

        if ($result['skipped_disabled'] ?? false) {
            return redirect()
                ->route('notification.index')
                ->with('error', 'Grace period alerts are disabled. Enable them and save before running.');
        }

        $emailsSent = (int) ($result['emails_sent'] ?? $result['sent'] ?? 0);
        $periodsWithGrace = (int) ($result['periods_with_grace'] ?? 0);
        $skippedNoEmail = (int) ($result['skipped_no_email'] ?? 0);

        if ($periodsWithGrace === 0) {
            return redirect()
                ->route('notification.index')
                ->with('success', 'No open work periods are in an active grace window right now. No emails were sent.');
        }

        if ($emailsSent === 0) {
            $message = "Grace periods are active for {$periodsWithGrace} week(s), but no new emails were sent.";
            if ($skippedNoEmail > 0) {
                $message .= " {$skippedNoEmail} staff member(s) have no email on file.";
            } else {
                $message .= ' Eligible staff may already have been emailed for this deadline, or none owe a submission.';
            }

            return redirect()
                ->route('notification.index')
                ->with('success', $message);
        }

        $message = "Grace period emails sent to {$emailsSent} staff member(s) across {$periodsWithGrace} week(s) in grace.";
        if ($skippedNoEmail > 0) {
            $message .= " {$skippedNoEmail} staff skipped (no email address).";
        }

        return redirect()
            ->route('notification.index')
            ->with('success', $message);
    }
}
