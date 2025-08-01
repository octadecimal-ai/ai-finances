<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Użyj głównej bazy danych
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', database_path('database.sqlite'));
        
        // Zachowaj dane w bazie
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        // Cofnij zmiany w bazie po każdym teście
        DB::rollBack();
        
        parent::tearDown();
    }
}
