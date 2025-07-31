<?php

namespace App\Console\Commands;

use App\Services\Banking\NordigenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TestNordigenService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:nordigen {--clear-cache : Clear cached tokens}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test NordigenService connection and basic functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing NordigenService...');
        $this->newLine();

        // Clear cache if requested
        if ($this->option('clear-cache')) {
            Cache::forget('nordigen_access_token');
            $this->info('🗑️  Cleared cached tokens');
            $this->newLine();
        }

        $nordigen = app(NordigenService::class);

        // Test 1: Authentication
        $this->info('1️⃣  Testing authentication...');
        $authenticated = $nordigen->authenticate();
        
        if ($authenticated) {
            $this->info('✅ Authentication successful');
        } else {
            $this->error('❌ Authentication failed');
            $this->error('Please check your NORDIGEN_SECRET_ID and NORDIGEN_SECRET_KEY in .env file');
            return 1;
        }

        // Test 2: Get institutions
        $this->newLine();
        $this->info('2️⃣  Testing institutions retrieval...');
        $institutions = $nordigen->getInstitutions('PL');
        
        if (!empty($institutions)) {
            $this->info('✅ Found ' . count($institutions) . ' institutions');
            
            // Show first few institutions
            $this->table(
                ['ID', 'Name', 'BIC'],
                array_slice($institutions, 0, 5)
            );
        } else {
            $this->warn('⚠️  No institutions found');
        }

        // Test 3: Create requisition (if institution available)
        if (!empty($institutions)) {
            $this->newLine();
            $this->info('3️⃣  Testing requisition creation...');
            
            $firstInstitution = $institutions[0];
            $requisitionId = $nordigen->createRequisition(
                $firstInstitution['id'],
                'http://localhost:8000/banking/callback'
            );
            
            if ($requisitionId) {
                $this->info('✅ Requisition created: ' . $requisitionId);
                
                // Test 4: Get accounts from requisition
                $this->newLine();
                $this->info('4️⃣  Testing accounts retrieval...');
                $accounts = $nordigen->getAccounts($requisitionId);
                
                if (!empty($accounts)) {
                    $this->info('✅ Found ' . count($accounts) . ' accounts');
                } else {
                    $this->warn('⚠️  No accounts found in requisition');
                }
            } else {
                $this->error('❌ Failed to create requisition');
            }
        }

        // Test 5: Cache status
        $this->newLine();
        $this->info('5️⃣  Checking cache status...');
        $cachedToken = Cache::get('nordigen_access_token');
        
        if ($cachedToken) {
            $this->info('✅ Token cached successfully');
        } else {
            $this->warn('⚠️  No token in cache');
        }

        // Test 6: Configuration check
        $this->newLine();
        $this->info('6️⃣  Checking configuration...');
        
        $config = [
            'Base URL' => config('banking.nordigen.base_url'),
            'Timeout' => config('banking.nordigen.timeout'),
            'Retry Attempts' => config('banking.nordigen.retry_attempts'),
            'Secret ID' => config('banking.nordigen.secret_id') ? 'Set' : 'Not set',
            'Secret Key' => config('banking.nordigen.secret_key') ? 'Set' : 'Not set',
        ];
        
        $this->table(
            ['Setting', 'Value'],
            collect($config)->map(fn($value, $key) => [$key, $value])->toArray()
        );

        $this->newLine();
        $this->info('🎉 NordigenService test completed!');
        
        if ($authenticated) {
            $this->info('✅ Service is ready to use');
        } else {
            $this->error('❌ Service needs configuration');
            return 1;
        }

        return 0;
    }
}
