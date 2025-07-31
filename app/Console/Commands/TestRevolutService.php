<?php

namespace App\Console\Commands;

use App\Services\Banking\RevolutService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TestRevolutService extends Command
{
    protected $signature = 'test:revolut {--clear-cache : Clear cache before testing}';
    protected $description = 'Test Revolut API service';

    public function __construct(
        private RevolutService $revolutService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('clear-cache')) {
            Cache::forget('revolut_access_token');
            Cache::forget('revolut_refresh_token');
            $this->info('Cache cleared.');
        }

        $this->info('Testing Revolut API Service...');

        try {
            // Test configuration
            $this->info('1. Testing configuration...');
            $config = [
                'Client ID' => config('banking.revolut.client_id') ? 'Set' : 'Not set',
                'Client Secret' => config('banking.revolut.client_secret') ? 'Set' : 'Not set',
                'Redirect URI' => config('banking.revolut.redirect_uri'),
                'Base URL' => config('banking.revolut.base_url'),
            ];
            
            $this->table(['Setting', 'Value'], collect($config)->map(fn($value, $key) => [$key, $value])->toArray());

            // Test connection
            $this->info('2. Testing connection...');
            $connected = $this->revolutService->testConnection();
            if ($connected) {
                $this->info('✓ Connection successful');
            } else {
                $this->error('✗ Connection failed');
                return 1;
            }

            // Test authorization URL
            $this->info('3. Testing authorization URL generation...');
            $authUrl = $this->revolutService->getAuthorizationUrl();
            if ($authUrl) {
                $this->info('✓ Authorization URL generated');
                $this->line('  URL: ' . $authUrl);
            } else {
                $this->error('✗ Failed to generate authorization URL');
                return 1;
            }

            // Test token exchange (if code provided)
            if ($this->argument('code')) {
                $this->info('4. Testing token exchange...');
                $token = $this->revolutService->exchangeCodeForToken($this->argument('code'));
                if ($token) {
                    $this->info('✓ Token exchange successful');
                } else {
                    $this->error('✗ Token exchange failed');
                }
            } else {
                $this->warn('⚠ Skipping token exchange - no authorization code provided');
            }

            // Test getting accounts
            $this->info('5. Testing accounts retrieval...');
            $accounts = $this->revolutService->getAccounts();
            if (!empty($accounts)) {
                $this->info('✓ Found ' . count($accounts) . ' accounts');
                $this->table(['ID', 'Name', 'Currency', 'Balance'], array_map(function ($acc) {
                    return [$acc['id'], $acc['name'], $acc['currency'], $acc['balance'] ?? 'N/A'];
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
