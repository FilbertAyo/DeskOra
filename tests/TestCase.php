<?php

namespace Tests;

use App\Database\DocumentsUnionView;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('weekly_plans') && ! DocumentsUnionView::exists()) {
            DocumentsUnionView::recreate(DB::connection()->getDriverName() === 'mysql');
        }
    }
}
