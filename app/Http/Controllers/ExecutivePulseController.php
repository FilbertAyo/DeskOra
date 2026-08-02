<?php

namespace App\Http\Controllers;

use App\Services\ExecutivePulseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExecutivePulseController extends Controller
{
    public function __construct(
        private readonly ExecutivePulseService $pulse,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && ($user->status ?? '') === 'active', 403);
        abort_unless($this->canViewPulse($user), 403);

        $snapshot = $this->pulse->snapshot($user);

        return view('executive-pulse.index', compact('snapshot'));
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
        return $user->can('view reports')
            || $user->can('view all documents');
    }
}
