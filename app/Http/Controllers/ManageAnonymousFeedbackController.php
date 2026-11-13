<?php

namespace App\Http\Controllers;

use App\Models\AnonymousFeedback;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class ManageAnonymousFeedbackController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', AnonymousFeedback::class);

        $query = AnonymousFeedback::query()->latest();

        $status = $request->query('status');
        if (is_string($status) && $status !== '' && in_array($status, AnonymousFeedback::statuses(), true)) {
            $query->status($status);
        }

        $items = $query->paginate(20)->withQueryString();

        $statusCounts = [
            AnonymousFeedback::STATUS_NEW => AnonymousFeedback::query()->status(AnonymousFeedback::STATUS_NEW)->count(),
            AnonymousFeedback::STATUS_IN_PROGRESS => AnonymousFeedback::query()->status(AnonymousFeedback::STATUS_IN_PROGRESS)->count(),
            AnonymousFeedback::STATUS_RESOLVED => AnonymousFeedback::query()->status(AnonymousFeedback::STATUS_RESOLVED)->count(),
        ];

        $feedbackUrl = url('/feedback/'.config('anonymous_feedback.public_token'));

        $writer = new SvgWriter;
        $qrSvg = $writer->write(new QrCode(
            data: $feedbackUrl,
            size: 180,
            margin: 8,
        ))->getString();

        return Inertia::render('AnonymousFeedback/Index', [
            'items' => $items->through(fn (AnonymousFeedback $item) => [
                'id' => $item->id,
                'category' => $item->category,
                'body' => $item->body,
                'status' => $item->status,
                'updateUrl' => route('anonymous-feedback.update', $item),
            ]),
            'statuses' => AnonymousFeedback::statuses(),
            'statusCounts' => $statusCounts,
            'feedbackUrl' => $feedbackUrl,
            'qrSvg' => $qrSvg,
            'activeStatus' => is_string($status) ? $status : null,
            'indexUrl' => route('anonymous-feedback.index'),
            'qrDownloadUrl' => route('anonymous-feedback.qr-download'),
        ]);
    }

    public function downloadQr(): Response
    {
        $this->authorize('viewAny', AnonymousFeedback::class);

        $feedbackUrl = url('/feedback/'.config('anonymous_feedback.public_token'));

        $writer = new PngWriter;
        $result = $writer->write(new QrCode(
            data: $feedbackUrl,
            size: 512,
            margin: 16,
        ));

        return response($result->getString(), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="anonymous-feedback-qr.png"',
        ]);
    }

    public function update(Request $request, AnonymousFeedback $anonymous_feedback): RedirectResponse
    {
        $this->authorize('update', $anonymous_feedback);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(AnonymousFeedback::statuses())],
        ]);

        $anonymous_feedback->update(['status' => $validated['status']]);

        Cache::forget('anonymous-feedback-new-count');

        return redirect()
            ->back()
            ->with('success', 'Status updated.');
    }
}
