<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Vinkla\Hashids\Facades\Hashids;

class MinutesController extends Controller
{
    /**
     * Display a listing of minutes documents.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Only minutes_preparer and admin can access
        if (!$user->hasAnyRole(['minutes_preparer', 'admin'])) {
            abort(403, 'Unauthorized access. Only minutes preparers can access this page.');
        }

        $query = Document::with('user')
            ->weeklyMinutes();

        // Filter by state if provided
        if ($request->has('state') && $request->state) {
            $query->ofState($request->state);
        }

        $documents = $query->latest()->paginate(15)->withQueryString();

        return view('minutes.index', compact('documents'));
    }

    /**
     * Show the form for creating a new minutes document.
     */
    public function create()
    {
        $user = Auth::user();

        // Only minutes_preparer and admin can create minutes
        if (!$user->hasAnyRole(['minutes_preparer', 'admin'])) {
            abort(403, 'Unauthorized access. Only minutes preparers can create minutes.');
        }

        // Authorize create action for weekly_minutes type
        $this->authorize('createType', [Document::class, Document::TYPE_WEEKLY_MINUTES]);

        return view('minutes.create');
    }

    /**
     * Store a newly created minutes document.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Only minutes_preparer and admin can create minutes
        if (!$user->hasAnyRole(['minutes_preparer', 'admin'])) {
            abort(403, 'Unauthorized access. Only minutes preparers can create minutes.');
        }

        // Authorize create action for weekly_minutes type
        $this->authorize('createType', [Document::class, Document::TYPE_WEEKLY_MINUTES]);

        $request->validate([
            'content' => ['required', 'string', 'min:10'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        // Determine state based on action
        $action = $request->input('action', 'draft');
        $state = ($action === 'submit') ? Document::STATE_SUBMITTED : Document::STATE_DRAFT;

        $document = Document::create([
            'user_id' => $user->id,
            'type' => Document::TYPE_WEEKLY_MINUTES,
            'content' => $request->content,
            'title' => $request->title ?? 'Weekly Minutes - ' . now()->format('M d, Y'),
            'state' => $state,
        ]);

        $message = ($action === 'submit') 
            ? 'Weekly Minutes submitted successfully.' 
            : 'Weekly Minutes saved as draft.';

        return redirect()->route('minutes.show', Hashids::encode($document->id))
            ->with('success', $message);
    }

    /**
     * Display the specified minutes document.
     */
    public function show(string $id)
    {
        $decodedId = Hashids::decode($id);
        $document = Document::with(['user', 'comments.user'])
            ->findOrFail($decodedId[0] ?? null);

        // Ensure it's a minutes document
        if ($document->type !== Document::TYPE_WEEKLY_MINUTES) {
            abort(404, 'Document not found.');
        }

        // Authorize view action
        $this->authorize('view', $document);

        return view('minutes.show', compact('document'));
    }

    /**
     * Show the form for editing the specified minutes document.
     */
    public function edit(string $id)
    {
        $decodedId = Hashids::decode($id);
        $document = Document::findOrFail($decodedId[0] ?? null);

        // Ensure it's a minutes document
        if ($document->type !== Document::TYPE_WEEKLY_MINUTES) {
            abort(404, 'Document not found.');
        }

        // Only minutes_preparer and admin can edit minutes
        if ($document->user_id !== Auth::id() && !Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorized access. Only the creator or admin can edit minutes.');
        }

        // Authorize update action
        $this->authorize('update', $document);

        return view('minutes.edit', compact('document'));
    }

    /**
     * Update the specified minutes document.
     */
    public function update(Request $request, string $id)
    {
        $decodedId = Hashids::decode($id);
        $document = Document::findOrFail($decodedId[0] ?? null);

        // Ensure it's a minutes document
        if ($document->type !== Document::TYPE_WEEKLY_MINUTES) {
            abort(404, 'Document not found.');
        }

        // Only minutes_preparer and admin can update minutes
        if ($document->user_id !== Auth::id() && !Auth::user()->hasRole('admin')) {
            abort(403, 'Unauthorized access. Only the creator or admin can update minutes.');
        }

        // Authorize update action
        $this->authorize('update', $document);

        $request->validate([
            'content' => ['required', 'string', 'min:10'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        // Determine state based on action
        $action = $request->input('action', 'draft');
        
        // If submitting, authorize submit action
        if ($action === 'submit') {
            $this->authorize('submit', $document);
        }

        $updateData = [
            'content' => $request->content,
        ];
        
        if ($request->has('title')) {
            $updateData['title'] = $request->title;
        }
        
        // Update state if submitting
        if ($action === 'submit') {
            $updateData['state'] = Document::STATE_SUBMITTED;
        }

        $document->update($updateData);

        $message = ($action === 'submit') 
            ? 'Weekly Minutes submitted successfully.' 
            : 'Weekly Minutes updated successfully.';

        return redirect()->route('minutes.show', $id)
            ->with('success', $message);
    }

    /**
     * Remove the specified minutes document.
     */
    public function destroy(string $id)
    {
        $decodedId = Hashids::decode($id);
        $document = Document::findOrFail($decodedId[0] ?? null);

        // Ensure it's a minutes document
        if ($document->type !== Document::TYPE_WEEKLY_MINUTES) {
            abort(404, 'Document not found.');
        }

        // Authorize delete action
        $this->authorize('delete', $document);

        $document->delete();

        return redirect()->route('minutes.index')
            ->with('success', 'Weekly Minutes deleted successfully.');
    }
}
