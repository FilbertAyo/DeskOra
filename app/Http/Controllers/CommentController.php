<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Vinkla\Hashids\Facades\Hashids;

class CommentController extends Controller
{
    /**
     * Store a newly created comment.
     */
    public function store(Request $request, string $documentId)
    {
        $decodedId = Hashids::decode($documentId);
        $document = Document::findOrFail($decodedId[0] ?? null);

        // Authorize comment creation (only reviewers can comment)
        $this->authorize('create', Comment::class);

        $request->validate([
            'comment' => ['required', 'string', 'min:3', 'max:5000'],
        ]);

        $comment = Comment::create([
            'document_id' => $document->id,
            'user_id' => Auth::id(),
            'comment' => $request->comment,
        ]);

        return redirect()->route('documents.show', $documentId)
            ->with('success', 'Comment added successfully.');
    }

    /**
     * Update the specified comment.
     */
    public function update(Request $request, string $id)
    {
        $decodedId = Hashids::decode($id);
        $comment = Comment::findOrFail($decodedId[0] ?? null);

        // Authorize update action
        $this->authorize('update', $comment);

        $request->validate([
            'comment' => ['required', 'string', 'min:3', 'max:5000'],
        ]);

        $comment->update([
            'comment' => $request->comment,
        ]);

        $document = $comment->document;
        $documentId = Hashids::encode($document->id);

        return redirect()->route('documents.show', $documentId)
            ->with('success', 'Comment updated successfully.');
    }

    /**
     * Remove the specified comment.
     */
    public function destroy(string $id)
    {
        $decodedId = Hashids::decode($id);
        $comment = Comment::findOrFail($decodedId[0] ?? null);

        // Authorize delete action
        $this->authorize('delete', $comment);

        $document = $comment->document;
        $documentId = Hashids::encode($document->id);
        $comment->delete();

        return redirect()->route('documents.show', $documentId)
            ->with('success', 'Comment deleted successfully.');
    }
}
