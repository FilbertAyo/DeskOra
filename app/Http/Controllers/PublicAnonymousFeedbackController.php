<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicAnonymousFeedbackRequest;
use App\Models\AnonymousFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PublicAnonymousFeedbackController extends Controller
{
    public function create(): View
    {
        return view('anonymous-feedback.public-form', [
            'categories' => [
                AnonymousFeedback::CATEGORY_COMPLAINT => 'Complaint',
                AnonymousFeedback::CATEGORY_SUGGESTION => 'Suggestion',
                AnonymousFeedback::CATEGORY_ASSISTANCE => 'Request for assistance',
                AnonymousFeedback::CATEGORY_OTHER => 'Other',
            ],
        ]);
    }

    public function store(StorePublicAnonymousFeedbackRequest $request): RedirectResponse
    {
        AnonymousFeedback::query()->create([
            'category' => $request->validated('category'),
            'body' => $request->validated('body'),
            'status' => AnonymousFeedback::STATUS_NEW,
        ]);

        return redirect()
            ->route('anonymous-feedback.form', ['token' => config('anonymous_feedback.public_token')])
            ->with('status', 'Thank you. Your message has been submitted anonymously.')
            ->with('success', 'Thank you. Your message has been submitted anonymously.');
    }
}
