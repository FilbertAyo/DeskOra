<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;

class DocumentCrudController extends DocumentController
{
    public function index(Request $request)
    {
        return parent::index($request);
    }

    public function create(Request $request)
    {
        return parent::create($request);
    }

    public function store(Request $request)
    {
        return parent::store($request);
    }

    public function show(Document $document)
    {
        return parent::show($document);
    }

    public function downloadPdf(Document $document)
    {
        return parent::downloadPdf($document);
    }

    public function edit(Document $document)
    {
        return parent::edit($document);
    }

    public function update(Request $request, Document $document)
    {
        return parent::update($request, $document);
    }

    public function destroy(Document $document)
    {
        return parent::destroy($document);
    }
}
