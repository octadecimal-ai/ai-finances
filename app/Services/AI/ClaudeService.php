<?php

namespace App\Services\AI;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class ClaudeService
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private int $maxTokens;
    private float $temperature;

    public function __construct()
    {
        $this->apiKey = config('claude.api_key');
        $this->baseUrl = config('claude.base_url');
        $this->model = config('claude.model');
        $this->maxTokens = config('claude.max_tokens');
        $this->temperature = config('claude.temperature');
    }

    public function analyzeTransaction(Transaction $transaction): array
    {
        if (!config('claude.features.transaction_analysis')) {
            return [];
        }

        $cacheKey = "claude_analysis_{$transaction->id}";
        
        return Cache::remember($cacheKey, 3600, function () use ($transaction) {
            try {
                $prompt = $this->buildTransactionAnalysisPrompt($transaction);
                
                $response = $this->makeRequest($prompt, 'transaction_analysis');
                
                if ($response) {
                    $transaction->update(['ai_analyzed' => true]);
                    return $this->parseAnalysisResponse($response);
                }
                
                return [];
            } catch (Exception $e) {
                Log::error('Claude transaction analysis failed', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
                return [];
            }
        });
    }

    public function suggestCategory(Transaction $transaction): ?string
    {
        if (!config('claude.features.category_suggestion')) {
            return null;
        }

        try {
            $prompt = $this->buildCategorySuggestionPrompt($transaction);
            
            $response = $this->makeRequest($prompt, 'category_suggestion');
            
            if ($response) {
                return $this->parseCategorySuggestion($response);
            }
            
            return null;
        } catch (Exception $e) {
            Log::error('Claude category suggestion failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function generateBudgetRecommendations(int $userId, array $spendingData): array
    {
        if (!config('claude.features.budget_recommendations')) {
            return [];
        }

        try {
            $prompt = $this->buildBudgetAnalysisPrompt($spendingData);
            
            $response = $this->makeRequest($prompt, 'budget_analysis');
            
            if ($response) {
                return $this->parseBudgetRecommendations($response);
            }
            
            return [];
        } catch (Exception $e) {
            Log::error('Claude budget recommendations failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function generateFinancialInsights(int $userId, array $financialData): array
    {
        if (!config('claude.features.financial_insights')) {
            return [];
        }

        try {
            $prompt = $this->buildFinancialInsightsPrompt($financialData);
            
            $response = $this->makeRequest($prompt, 'budget_analysis');
            
            if ($response) {
                return $this->parseFinancialInsights($response);
            }
            
            return [];
        } catch (Exception $e) {
            Log::error('Claude financial insights failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function makeRequest(string $prompt, string $promptType): ?string
    {
        $config = config("claude.prompts.{$promptType}");
        
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(30)->post($this->baseUrl . '/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $config['max_tokens'] ?? $this->maxTokens,
                'temperature' => $this->temperature,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $config['system'],
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

            if ($response->successful()) {
                return $response->json('content.0.text');
            }

            Log::error('Claude API request failed', [
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Claude API request error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function buildTransactionAnalysisPrompt(Transaction $transaction): string
    {
        return "Przeanalizuj następującą transakcję finansową:

Opis: {$transaction->description}
Kwota: {$transaction->formatted_amount}
Data: {$transaction->transaction_date}
Typ: " . ($transaction->isIncome() ? 'Przychód' : 'Wydatek') . "
Kategoria: " . ($transaction->category?->name ?? 'Nieprzypisana') . "

Proszę o:
1. Analizę wzorca wydatków
2. Sugestie kategorii
3. Rekomendacje finansowe
4. Czy to jest nietypowa transakcja?

Odpowiedz w języku polskim w formacie JSON.";
    }

    private function buildCategorySuggestionPrompt(Transaction $transaction): string
    {
        return "Na podstawie opisu transakcji, zasugeruj najlepszą kategorię:

Opis: {$transaction->description}
Kwota: {$transaction->formatted_amount}
Data: {$transaction->transaction_date}

Dostępne kategorie: Jedzenie, Transport, Rozrywka, Zakupy, Rachunki, Zdrowie, Edukacja, Inne

Odpowiedz tylko nazwą kategorii w języku polskim.";
    }

    private function buildBudgetAnalysisPrompt(array $spendingData): string
    {
        $monthlySpending = json_encode($spendingData, JSON_UNESCAPED_UNICODE);
        
        return "Przeanalizuj dane wydatków użytkownika i wygeneruj rekomendacje budżetowe:

Dane wydatków: {$monthlySpending}

Proszę o:
1. Analizę wzorców wydatków
2. Rekomendacje oszczędności
3. Sugestie optymalizacji budżetu
4. Alerty o potencjalnych problemach finansowych

Odpowiedz w języku polskim w formacie JSON.";
    }

    private function buildFinancialInsightsPrompt(array $financialData): string
    {
        $data = json_encode($financialData, JSON_UNESCAPED_UNICODE);
        
        return "Przeanalizuj dane finansowe użytkownika i wygeneruj insights:

Dane finansowe: {$data}

Proszę o:
1. Główne insights finansowe
2. Trendy wydatków
3. Rekomendacje inwestycyjne
4. Prognozy finansowe

Odpowiedz w języku polskim w formacie JSON.";
    }

    private function parseAnalysisResponse(string $response): array
    {
        try {
            return json_decode($response, true) ?? [];
        } catch (Exception $e) {
            Log::error('Failed to parse Claude analysis response', [
                'response' => $response,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function parseCategorySuggestion(string $response): ?string
    {
        $response = trim($response);
        $validCategories = ['Jedzenie', 'Transport', 'Rozrywka', 'Zakupy', 'Rachunki', 'Zdrowie', 'Edukacja', 'Inne'];
        
        foreach ($validCategories as $category) {
            if (stripos($response, $category) !== false) {
                return $category;
            }
        }
        
        return null;
    }

    private function parseBudgetRecommendations(string $response): array
    {
        try {
            return json_decode($response, true) ?? [];
        } catch (Exception $e) {
            Log::error('Failed to parse Claude budget recommendations', [
                'response' => $response,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function parseFinancialInsights(string $response): array
    {
        try {
            return json_decode($response, true) ?? [];
        } catch (Exception $e) {
            Log::error('Failed to parse Claude financial insights', [
                'response' => $response,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
} 