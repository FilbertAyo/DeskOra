<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;

class DocumentWorkflowController extends DocumentController
{
    public function reviewerShow(Document $document)
    {
        return parent::reviewerShow($document);
    }

    public function submit(Request $request, Document $document)
    {
        return parent::submit($request, $document);
    }

    public function reject(Request $request, Document $document)
    {
        return parent::reject($request, $document);
    }
}
