<?php

namespace App\Http\Controllers;

use App\Models\AnonymousFeedback;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ManageAnonymousFeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AnonymousFeedback::class);

        $query = AnonymousFeedback::query()->latest();

        $status = $request->query('status');
        if (is_string($status) && $status !== '' && in_array($status, AnonymousFeedback::statuses(), true)) {
            $query->status($status);
        }

        $items = $query->paginate(20)->withQueryString();

        $feedbackUrl = url('/feedback/'.config('anonymous_feedback.public_token'));

        $writer = new SvgWriter;
        $qrSvg = $writer->write(new QrCode(
            data: $feedbackUrl,
            size: 180,
            margin: 8,
        ))->getString();

        return view('settings.anonymous-feedback.index', [
            'items' => $items,
            'statuses' => AnonymousFeedback::statuses(),
            'feedbackUrl' => $feedbackUrl,
            'qrSvg' => $qrSvg,
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

        return redirect()
            ->back()
            ->with('success', 'Status updated.');
    }
}
