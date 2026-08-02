<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesDocumentCrud;

class DocumentCrudController extends DocumentController
{
    use HandlesDocumentCrud;
}
