<?php

namespace Tests;

use App\Database\DocumentsUnionView;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DocumentsUnionView::canCreate() && ! DocumentsUnionView::exists()) {
            DocumentsUnionView::recreate(DB::connection()->getDriverName() === 'mysql');
        }
    }
}
