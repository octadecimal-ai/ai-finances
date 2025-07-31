<?php

namespace App\Console\Commands;

use App\Services\Banking\RevolutService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TestRevolutService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:revolut {--clear-cache : Clear cached tokens}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test RevolutService connection and basic functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing RevolutService...');
        $this->newLine();

        // Clear cache if requested
        if ($this->option('clear-cache')) {
            Cache::forget('revolut_access_token');
            Cache::forget('revolut_refresh_token');
            $this->info('🗑️  Cleared cached tokens');
            $this->newLine();
        }

        $revolut = app(RevolutService::class);

        // Test 1: Configuration check
        $this->info('1️⃣  Checking configuration...');
        
        $config = [
            'Base URL' => config('banking.revolut.base_url'),
            'Client ID' => config('banking.revolut.client_id') ? 'Set' : 'Not set',
            'Client Secret' => config('banking.revolut.client_secret') ? 'Set' : 'Not set',
            'Redirect URI' => config('banking.revolut.redirect_uri'),
            'Timeout' => config('banking.revolut.timeout'),
            'Retry Attempts' => config('banking.revolut.retry_attempts'),
        ];
        
        $this->table(
            ['Setting', 'Value'],
            collect($config)->map(fn($value, $key) => [$key, $value])->toArray()
        );

        // Test 2: Cache status
        $this->newLine();
        $this->info('2️⃣  Checking cache status...');
        
        $accessToken = Cache::get('revolut_access_token');
        $refreshToken = Cache::get('revolut_refresh_token');
        
        if ($accessToken) {
            $this->info('✅ Access token cached');
        } else {
            $this->warn('⚠️  No access token cached');
        }
        
        if ($refreshToken) {
            $this->info('✅ Refresh token cached');
        } else {
            $this->warn('⚠️  No refresh token cached');
        }

        // Test 3: Connection test
        $this->newLine();
        $this->info('3️⃣  Testing connection...');
        
        $connected = $revolut->testConnection();
        
        if ($connected) {
            $this->info('✅ Connection successful');
        } else {
            $this->error('❌ Connection failed');
            $this->error('Please check your REVOLUT_CLIENT_ID and REVOLUT_CLIENT_SECRET in .env file');
            $this->error('Or authenticate first using OAuth flow');
            return 1;
        }

        // Test 4: Get authorization URL
        $this->newLine();
        $this->info('4️⃣  Testing authorization URL generation...');
        
        $authUrl = $revolut->getAuthorizationUrl('test123');
        
        if ($authUrl) {
            $this->info('✅ Authorization URL generated');
            $this->line('URL: ' . $authUrl);
        } else {
            $this->error('❌ Failed to generate authorization URL');
        }

        // Test 5: Get accounts (if authenticated)
        if ($connected) {
            $this->newLine();
            $this->info('5️⃣  Testing accounts retrieval...');
            
            $accounts = $revolut->getAccounts();
            
            if (!empty($accounts)) {
                $this->info('✅ Found ' . count($accounts) . ' accounts');
                
                // Show first few accounts
                $accountData = array_slice($accounts, 0, 3);
                $this->table(
                    ['ID', 'Name', 'Currency', 'Status'],
                    collect($accountData)->map(function($account) {
                        return [
                            $account['id'] ?? 'N/A',
                            $account['name'] ?? 'N/A',
                            $account['currency'] ?? 'N/A',
                            $account['status'] ?? 'N/A',
                        ];
                    })->toArray()
                );
            } else {
                $this->warn('⚠️  No accounts found');
                $this->info('This might be normal if no accounts are connected');
            }
        }

        // Test 6: Refresh token test
        $this->newLine();
        $this->info('6️⃣  Testing refresh token functionality...');
        
        if ($refreshToken) {
            $refreshed = $revolut->refreshToken();
            
            if ($refreshed) {
                $this->info('✅ Token refreshed successfully');
            } else {
                $this->warn('⚠️  Token refresh failed');
            }
        } else {
            $this->warn('⚠️  No refresh token available for testing');
        }

        $this->newLine();
        $this->info('🎉 RevolutService test completed!');
        
        if ($connected) {
            $this->info('✅ Service is ready to use');
            $this->info('💡 To test full OAuth flow, use the authorization URL above');
        } else {
            $this->error('❌ Service needs configuration or authentication');
            $this->info('💡 Please configure REVOLUT_CLIENT_ID and REVOLUT_CLIENT_SECRET');
            return 1;
        }

        return 0;
    }
}
