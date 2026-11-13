<?php

namespace App\Http\Controllers;

use App\Services\ExecutivePulseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExecutivePulseController extends Controller
{
    public function __construct(
        private readonly ExecutivePulseService $pulse,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->status ?? '') === 'active', 403);
        abort_unless($this->canViewPulse($user), 403);

        $snapshot = $this->pulse->snapshot($user);

        $snapshot['generated_at'] = $snapshot['generated_at']->format('d M Y, H:i');

        return Inertia::render('ExecutivePulse/Index', [
            'snapshot' => $snapshot,
            'downloadUrl' => route('executive-pulse.download'),
            'approvalsUrl' => route('petty.list'),
        ]);
    }

    public function download(Request $request): Response|StreamedResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->status ?? '') === 'active', 403);
        abort_unless($this->canViewPulse($user), 403);

        $snapshot = $this->pulse->snapshot($user);
        $pdf = Pdf::loadView('executive-pulse.pdf', compact('snapshot'))
            ->setPaper('a4', 'portrait');

        $stamp = now()->format('Y-m-d');

        return $pdf->download("executive-pulse-{$stamp}.pdf");
    }

    private function canViewPulse($user): bool
    {
        return $user->can('view reports');
    }
}
