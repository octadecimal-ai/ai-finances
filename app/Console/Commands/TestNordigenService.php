<?php

namespace App\Console\Commands;

use App\Services\Banking\NordigenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TestNordigenService extends Command
{
    protected $signature = 'test:nordigen {--clear-cache : Clear cache before testing}';
    protected $description = 'Test Nordigen API service';

    public function __construct(
        private NordigenService $nordigenService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('clear-cache')) {
            Cache::forget('nordigen_access_token');
            $this->info('Cache cleared.');
        }

        $this->info('Testing Nordigen API Service...');

        try {
            // Test authentication
            $this->info('1. Testing authentication...');
            $token = $this->nordigenService->authenticate();
            if ($token) {
                $this->info('✓ Authentication successful');
            } else {
                $this->error('✗ Authentication failed');
                return 1;
            }

            // Test getting institutions
            $this->info('2. Testing institutions retrieval...');
            $institutions = $this->nordigenService->getInstitutions();
            if (!empty($institutions)) {
                $this->info('✓ Found ' . count($institutions) . ' institutions');
                $this->table(['ID', 'Name', 'Country'], array_map(function ($inst) {
                    return [$inst['id'], $inst['name'], $inst['country'] ?? 'N/A'];
                }, array_slice($institutions, 0, 5)));
            } else {
                $this->warn('⚠ No institutions found');
            }

            // Test creating requisition
            $this->info('3. Testing requisition creation...');
            $institutionId = $institutions[0]['id'] ?? null;
            if ($institutionId) {
                $requisition = $this->nordigenService->createRequisition($institutionId, 'http://localhost/callback');
                if ($requisition) {
                    $this->info('✓ Requisition created: ' . $requisition['id']);
                    $this->info('  Link: ' . $requisition['link']);
                } else {
                    $this->error('✗ Failed to create requisition');
                }
            } else {
                $this->warn('⚠ Skipping requisition test - no institution available');
            }

            // Test getting accounts
            $this->info('4. Testing accounts retrieval...');
            $accounts = $this->nordigenService->getAccounts();
            if (!empty($accounts)) {
                $this->info('✓ Found ' . count($accounts) . ' accounts');
                $this->table(['ID', 'Name', 'Currency', 'Status'], array_map(function ($acc) {
                    return [$acc['id'], $acc['name'], $acc['currency'], $acc['status']];
                }, array_slice($accounts, 0, 5)));
            } else {
                $this->warn('⚠ No accounts found');
            }

            $this->info('✓ All tests completed successfully!');
            return 0;

        } catch (\Exception $e) {
            $this->error('✗ Test failed: ' . $e->getMessage());
            return 1;
        }
    }
}
