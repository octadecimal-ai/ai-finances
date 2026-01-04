<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\ExchangeRate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MatchInvoiceToTransaction implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Invoice $invoice
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Zwolnij poprzednią transakcję jeśli była przypisana
            $previousTransactionId = $this->invoice->transaction_id;
            
            $bestMatch = $this->findBestMatch($this->invoice);
            
            if ($bestMatch) {
                // Sprawdź czy transakcja nie jest już przypisana do innej faktury
                $existingInvoice = Invoice::where('transaction_id', $bestMatch['transaction']->id)
                    ->where('id', '!=', $this->invoice->id)
                    ->first();
                
                if ($existingInvoice) {
                    Log::warning("Transakcja {$bestMatch['transaction']->id} jest już przypisana do faktury {$existingInvoice->id}. Pomijam dopasowanie dla faktury {$this->invoice->id}.");
                    
                    // Jeśli nie znaleziono dopasowania, wyczyść poprzednie
                    $this->invoice->update([
                        'transaction_id' => null,
                        'match_score' => null,
                        'matched_at' => null,
                    ]);
                    return;
                }
                
                // Przypisz transakcję do faktury
                $this->invoice->update([
                    'transaction_id' => $bestMatch['transaction']->id,
                    'match_score' => $bestMatch['score'],
                    'matched_at' => now(),
                ]);
                
                Log::info("Dopasowano fakturę {$this->invoice->id} do transakcji {$bestMatch['transaction']->id} z wynikiem {$bestMatch['score']}");
            } else {
                // Jeśli nie znaleziono dopasowania, wyczyść poprzednie
                $this->invoice->update([
                    'transaction_id' => null,
                    'match_score' => null,
                    'matched_at' => null,
                ]);
                
                Log::info("Nie znaleziono dopasowania dla faktury {$this->invoice->id}");
            }
        } catch (\Exception $e) {
            Log::error("Błąd podczas dopasowywania faktury {$this->invoice->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Znajduje najlepsze dopasowanie transakcji dla faktury
     * 
     * @return array{transaction: Transaction, score: float}|null
     */
    private function findBestMatch(Invoice $invoice): ?array
    {
        // Oblicz oczekiwany koszt w PLN dla kilku dat
        $expectedAmountsPLN = $this->calculateExpectedAmountsPLN($invoice);
        
        if (empty($expectedAmountsPLN)) {
            return null;
        }
        
        // Określ okno czasowe wyszukiwania
        $dateWindow = $this->getDateWindow($invoice);
        
        // Znajdź transakcje w oknie czasowym
        // Wyklucz transakcje, które są już przypisane do innych faktur
        $alreadyAssignedTransactionIds = Invoice::where('user_id', $invoice->user_id)
            ->whereNotNull('transaction_id')
            ->where('id', '!=', $invoice->id) // Wyklucz aktualną fakturę (jeśli jest ponownie dopasowywana)
            ->pluck('transaction_id')
            ->toArray();
        
        $transactions = Transaction::where('user_id', $invoice->user_id)
            ->where('type', 'debit') // Tylko wydatki
            ->whereBetween('transaction_date', [$dateWindow['start'], $dateWindow['end']])
            ->whereNotIn('id', $alreadyAssignedTransactionIds) // Wyklucz już przypisane transakcje
            ->get();
        
        if ($transactions->isEmpty()) {
            return null;
        }
        
        // Oblicz score dla każdej transakcji
        $scores = [];
        foreach ($transactions as $transaction) {
            $score = $this->calculateMatchScore($invoice, $transaction, $expectedAmountsPLN, $dateWindow);
            if ($score > 0) {
                $scores[] = [
                    'transaction' => $transaction,
                    'score' => $score,
                ];
            }
        }
        
        if (empty($scores)) {
            return null;
        }
        
        // Sortuj po score (malejąco) i zwróć najlepsze dopasowanie
        usort($scores, fn($a, $b) => $b['score'] <=> $a['score']);
        
        $bestMatch = $scores[0];
        
        // Zwróć tylko jeśli score jest wystarczająco wysoki (np. > 30)
        if ($bestMatch['score'] >= 30) {
            return $bestMatch;
        }
        
        return null;
    }

    /**
     * Oblicza oczekiwany koszt w PLN dla kilku dat (issue_date, due_date, paid_at)
     */
    private function calculateExpectedAmountsPLN(Invoice $invoice): array
    {
        $amounts = [];
        $dates = [];
        
        // Data wystawienia faktury
        if ($invoice->issue_date) {
            $dates[] = $invoice->issue_date;
        }
        
        // Data płatności (jeśli jest)
        if ($invoice->paid_at) {
            $dates[] = $invoice->paid_at;
        }
        
        // Termin płatności
        if ($invoice->due_date) {
            $dates[] = $invoice->due_date;
        }
        
        // Jeśli brak dat, użyj daty faktury
        if (empty($dates) && $invoice->invoice_date) {
            $dates[] = $invoice->invoice_date;
        }
        
        // Dla każdej daty oblicz kwotę w PLN
        foreach ($dates as $date) {
            $amountPLN = $this->convertToPLN($invoice->total_amount, $invoice->currency, $date);
            if ($amountPLN !== null) {
                $amounts[$date->format('Y-m-d')] = $amountPLN;
            }
        }
        
        return $amounts;
    }

    /**
     * Konwertuje kwotę na PLN
     */
    private function convertToPLN(?float $amount, ?string $currency, \DateTime $date): ?float
    {
        if ($amount === null || $amount == 0) {
            return null;
        }
        
        if (strtoupper($currency ?? '') === 'PLN') {
            return $amount;
        }
        
        if (empty($currency)) {
            return null;
        }
        
        // Pobierz kurs wymiany dla daty
        $exchangeRate = ExchangeRate::getRateForDate(
            strtoupper($currency),
            $date->format('Y-m-d')
        );
        
        if (!$exchangeRate) {
            $exchangeRate = ExchangeRate::getLatestRate(
                strtoupper($currency),
                $date->format('Y-m-d')
            );
        }
        
        if (!$exchangeRate || !$exchangeRate->rate) {
            return null;
        }
        
        return round($amount * $exchangeRate->rate, 2);
    }

    /**
     * Określa okno czasowe wyszukiwania transakcji
     */
    private function getDateWindow(Invoice $invoice): array
    {
        $startDate = $invoice->issue_date ?? $invoice->invoice_date ?? now();
        $endDate = $invoice->due_date ?? $invoice->paid_at ?? $startDate;
        
        // Rozszerz okno o 30 dni przed i 30 dni po
        // Używamy Carbon dla łatwiejszej manipulacji datami
        $startDate = \Carbon\Carbon::parse($startDate)->subDays(30);
        $endDate = \Carbon\Carbon::parse($endDate)->addDays(30);
        
        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    /**
     * Oblicza score dopasowania między fakturą a transakcją
     * 
     * @param array<string, float> $expectedAmountsPLN
     * @param array{start: \DateTime, end: \DateTime} $dateWindow
     */
    private function calculateMatchScore(
        Invoice $invoice,
        Transaction $transaction,
        array $expectedAmountsPLN,
        array $dateWindow
    ): float {
        $score = 0;
        $maxScore = 100;
        
        // 1. Dopasowanie kwoty (max 50 punktów)
        $amountScore = $this->calculateAmountScore($transaction, $expectedAmountsPLN);
        $score += $amountScore * 0.5; // 50% wagi
        
        // 2. Dopasowanie daty (max 30 punktów)
        $dateScore = $this->calculateDateScore($invoice, $transaction, $dateWindow);
        $score += $dateScore * 0.3; // 30% wagi
        
        // 3. Dopasowanie opisu/nazwy (max 20 punktów)
        $descriptionScore = $this->calculateDescriptionScore($invoice, $transaction);
        $score += $descriptionScore * 0.2; // 20% wagi
        
        return round($score, 2);
    }

    /**
     * Oblicza score dopasowania kwoty
     */
    private function calculateAmountScore(Transaction $transaction, array $expectedAmountsPLN): float
    {
        // Konwertuj kwotę transakcji na PLN
        $transactionAmountPLN = $this->convertToPLN(
            abs($transaction->amount),
            $transaction->currency,
            $transaction->transaction_date ?? now()
        );
        
        if ($transactionAmountPLN === null || empty($expectedAmountsPLN)) {
            return 0;
        }
        
        // Znajdź najbliższą oczekiwaną kwotę
        $bestMatch = 0;
        foreach ($expectedAmountsPLN as $expectedAmount) {
            if ($expectedAmount == 0) {
                continue;
            }
            
            // Oblicz różnicę procentową
            $diff = abs($transactionAmountPLN - $expectedAmount);
            $percentDiff = ($diff / $expectedAmount) * 100;
            
            // Score: 100 - percentDiff, ale nie mniej niż 0
            $match = max(0, 100 - $percentDiff);
            $bestMatch = max($bestMatch, $match);
        }
        
        return $bestMatch;
    }

    /**
     * Oblicza score dopasowania daty
     */
    private function calculateDateScore(Invoice $invoice, Transaction $transaction, array $dateWindow): float
    {
        $transactionDate = $transaction->transaction_date ?? $transaction->booking_date;
        
        if (!$transactionDate) {
            return 0;
        }
        
        // Sprawdź odległość od różnych dat faktury
        $dates = [];
        if ($invoice->issue_date) {
            $dates[] = $invoice->issue_date;
        }
        if ($invoice->paid_at) {
            $dates[] = $invoice->paid_at;
        }
        if ($invoice->due_date) {
            $dates[] = $invoice->due_date;
        }
        if ($invoice->invoice_date) {
            $dates[] = $invoice->invoice_date;
        }
        
        if (empty($dates)) {
            return 0;
        }
        
        $bestScore = 0;
        $transactionCarbon = \Carbon\Carbon::parse($transactionDate);
        
        foreach ($dates as $date) {
            $dateCarbon = \Carbon\Carbon::parse($date);
            $daysDiff = abs($transactionCarbon->diffInDays($dateCarbon));
            
            // Score: 100 dla 0 dni, liniowo spada do 0 dla 30+ dni
            $score = max(0, 100 - ($daysDiff * 3.33)); // 30 dni = 0 punktów
            $bestScore = max($bestScore, $score);
        }
        
        return $bestScore;
    }

    /**
     * Oblicza score dopasowania opisu/nazwy
     */
    private function calculateDescriptionScore(Invoice $invoice, Transaction $transaction): float
    {
        $score = 0;
        
        // Porównaj nazwę sprzedawcy z opisem/merchant_name transakcji
        $sellerName = strtolower($invoice->seller_name ?? '');
        $transactionDescription = strtolower($transaction->description ?? '');
        $merchantName = strtolower($transaction->merchant_name ?? '');
        
        if (empty($sellerName)) {
            return 0;
        }
        
        // Sprawdź czy nazwa sprzedawcy występuje w opisie transakcji
        if (!empty($transactionDescription) && str_contains($transactionDescription, $sellerName)) {
            $score += 50;
        }
        
        // Sprawdź czy nazwa sprzedawcy występuje w merchant_name
        if (!empty($merchantName) && str_contains($merchantName, $sellerName)) {
            $score += 50;
        }
        
        // Sprawdź częściowe dopasowanie (słowa kluczowe)
        $sellerWords = explode(' ', $sellerName);
        $matchedWords = 0;
        foreach ($sellerWords as $word) {
            if (strlen($word) > 3) { // Ignoruj krótkie słowa
                if (str_contains($transactionDescription, $word) || str_contains($merchantName, $word)) {
                    $matchedWords++;
                }
            }
        }
        
        if (count($sellerWords) > 0) {
            $wordMatchScore = ($matchedWords / count($sellerWords)) * 50;
            $score += $wordMatchScore;
        }
        
        return min(100, $score);
    }
}
