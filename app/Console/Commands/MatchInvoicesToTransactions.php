<?php

namespace App\Console\Commands;

use App\Jobs\MatchInvoiceToTransaction;
use App\Models\Invoice;
use Illuminate\Console\Command;

class MatchInvoicesToTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:match-transactions 
                            {--user-id= : ID użytkownika (opcjonalne)}
                            {--invoice-id= : ID konkretnej faktury (opcjonalne)}
                            {--force : Wymuś ponowne dopasowanie nawet jeśli już dopasowane}
                            {--queue : Uruchom w kolejce (domyślnie synchronicznie)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dopasowuje faktury do transakcji używając algorytmu dopasowania';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Rozpoczynam dopasowywanie faktur do transakcji...');
        
        $query = Invoice::query();
        
        // Filtruj po użytkowniku jeśli podano
        if ($userId = $this->option('user-id')) {
            $query->where('user_id', $userId);
        }
        
        // Filtruj po konkretnej fakturze jeśli podano
        if ($invoiceId = $this->option('invoice-id')) {
            $query->where('id', $invoiceId);
        }
        
        // Jeśli nie ma --force, pomiń już dopasowane faktury
        if (!$this->option('force')) {
            $query->whereNull('transaction_id');
        }
        
        $invoices = $query->get();
        
        if ($invoices->isEmpty()) {
            $this->warn('Nie znaleziono faktur do dopasowania.');
            return self::FAILURE;
        }
        
        $this->info("Znaleziono {$invoices->count()} faktur do dopasowania.");
        
        $bar = $this->output->createProgressBar($invoices->count());
        $bar->start();
        
        $useQueue = $this->option('queue');
        
        foreach ($invoices as $invoice) {
            if ($useQueue) {
                MatchInvoiceToTransaction::dispatch($invoice);
            } else {
                // Uruchom synchronicznie
                $job = new MatchInvoiceToTransaction($invoice);
                $job->handle();
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        if ($useQueue) {
            $this->info("✅ Dodano {$invoices->count()} zadań do kolejki.");
            $this->comment('Uruchom: php artisan queue:work aby przetworzyć zadania.');
        } else {
            $this->info("✅ Zakończono dopasowywanie {$invoices->count()} faktur.");
        }
        
        return self::SUCCESS;
    }
}
