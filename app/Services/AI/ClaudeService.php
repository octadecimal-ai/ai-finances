<?php

namespace App\Services\AI;

use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeService
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private float $temperature;

    public function __construct()
    {
        $this->apiKey = config('claude.api_key');
        $this->model = config('claude.model', 'claude-3-sonnet-20240229');
        $this->maxTokens = config('claude.max_tokens', 4000);
        $this->temperature = config('claude.temperature', 0.7);
    }

    /**
     * Analyze a transaction using Claude AI
     * 
     * @param Transaction $transaction
     * @return array<string, mixed>
     */
    public function analyzeTransaction(Transaction $transaction): array
    {
        if (!config('claude.features.transaction_analysis')) {
            return ['enabled' => false];
        }

        try {
            $prompt = $this->buildTransactionAnalysisPrompt($transaction);
            
            $response = $this->makeApiCall($prompt);
            
            if ($response) {
                $analysis = $this->parseAnalysisResponse($response);
                $analysis['transaction_id'] = $transaction->id;
                return $analysis;
            }
            
            return ['error' => 'Failed to analyze transaction'];
            
        } catch (\Exception $e) {
            Log::error('Claude transaction analysis failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
            
            return ['error' => 'Analysis failed: ' . $e->getMessage()];
        }
    }

    /**
     * Suggest category for a transaction
     * 
     * @param Transaction $transaction
     * @param \Illuminate\Database\Eloquent\Collection<int, Category> $categories
     * @return array<string, mixed>
     */
    public function suggestCategory(Transaction $transaction, $categories): array
    {
        if (!config('claude.features.category_suggestion')) {
            return ['enabled' => false];
        }

        try {
            $prompt = $this->buildCategorySuggestionPrompt($transaction, $categories);
            
            $response = $this->makeApiCall($prompt);
            
            if ($response) {
                return $this->parseCategorySuggestion($response);
            }
            
            return ['error' => 'Failed to suggest category'];
            
        } catch (\Exception $e) {
            Log::error('Claude category suggestion failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
            
            return ['error' => 'Suggestion failed: ' . $e->getMessage()];
        }
    }

    /**
     * Generate budget recommendations
     * 
     * @param array<string, mixed> $spendingData
     * @return array<string, mixed>
     */
    public function generateBudgetRecommendations(array $spendingData): array
    {
        if (!config('claude.features.budget_recommendations')) {
            return ['enabled' => false];
        }

        try {
            $prompt = $this->buildBudgetAnalysisPrompt($spendingData);
            
            $response = $this->makeApiCall($prompt);
            
            if ($response) {
                return $this->parseBudgetRecommendations($response);
            }
            
            return ['error' => 'Failed to generate budget recommendations'];
            
        } catch (\Exception $e) {
            Log::error('Claude budget recommendations failed', [
                'error' => $e->getMessage()
            ]);
            
            return ['error' => 'Recommendations failed: ' . $e->getMessage()];
        }
    }

    /**
     * Generate financial insights
     * 
     * @param array<string, mixed> $financialData
     * @return array<string, mixed>
     */
    public function generateFinancialInsights(array $financialData): array
    {
        if (!config('claude.features.financial_insights')) {
            return ['enabled' => false];
        }

        try {
            $prompt = $this->buildFinancialInsightsPrompt($financialData);
            
            $response = $this->makeApiCall($prompt);
            
            if ($response) {
                return $this->parseFinancialInsights($response);
            }
            
            return ['error' => 'Failed to generate financial insights'];
            
        } catch (\Exception $e) {
            Log::error('Claude financial insights failed', [
                'error' => $e->getMessage()
            ]);
            
            return ['error' => 'Insights failed: ' . $e->getMessage()];
        }
    }

    /**
     * Build prompt for transaction analysis
     */
    private function buildTransactionAnalysisPrompt(Transaction $transaction): string
    {
        $category = $transaction->category?->name ?? 'Nieprzypisana';
        
        return "Przeanalizuj następującą transakcję finansową i podaj szczegółowe informacje:

Transakcja:
- Kwota: {$transaction->amount} {$transaction->currency}
- Typ: " . ($transaction->type === 'credit' ? 'Przychód' : 'Wydatek') . "
- Opis: {$transaction->description}
- Data: {$transaction->transaction_date}
- Kategoria: {$category}
- Sprzedawca: {$transaction->merchant_name}

Przeanalizuj:
1. Czy transakcja wygląda na normalną czy podejrzaną?
2. Jakie mogą być możliwe kategorie dla tej transakcji?
3. Czy są jakieś wzorce w tej transakcji?
4. Rekomendacje dotyczące kategoryzacji

Odpowiedz w formacie JSON z polami: analysis, suspicious, suggested_categories, patterns, recommendations";
    }

    /**
     * Build prompt for category suggestion
     */
    private function buildCategorySuggestionPrompt(Transaction $transaction, $categories): string
    {
        $categoryList = $categories->map(fn($cat) => "{$cat->id}: {$cat->name}")->join(', ');
        
        return "Sugeruj najlepszą kategorię dla tej transakcji:

Transakcja:
- Kwota: {$transaction->amount} {$transaction->currency}
- Opis: {$transaction->description}
- Sprzedawca: {$transaction->merchant_name}

Dostępne kategorie: {$categoryList}

Wybierz najlepszą kategorię i podaj uzasadnienie w formacie JSON:
{
  \"category_id\": ID_kategorii,
  \"confidence\": 0.95,
  \"reasoning\": \"Uzasadnienie wyboru\"
}";
    }

    /**
     * Build prompt for budget analysis
     * 
     * @param array<string, mixed> $spendingData
     */
    private function buildBudgetAnalysisPrompt(array $spendingData): string
    {
        $data = json_encode($spendingData, JSON_PRETTY_PRINT);
        
        return "Przeanalizuj dane wydatków i wygeneruj rekomendacje budżetowe:

Dane wydatków:
{$data}

Wygeneruj rekomendacje w formacie JSON z polami:
- budget_recommendations: lista rekomendacji
- spending_patterns: wzorce wydatków
- savings_opportunities: możliwości oszczędności
- risk_alerts: alerty ryzyka";
    }

    /**
     * Build prompt for financial insights
     * 
     * @param array<string, mixed> $financialData
     */
    private function buildFinancialInsightsPrompt(array $financialData): string
    {
        $data = json_encode($financialData, JSON_PRETTY_PRINT);
        
        return "Przeanalizuj dane finansowe i wygeneruj szczegółowe wnioski:

Dane finansowe:
{$data}

Wygeneruj wnioski w formacie JSON z polami:
- key_insights: główne wnioski
- trends: trendy
- recommendations: rekomendacje
- risk_factors: czynniki ryzyka";
    }

    /**
     * Make API call to Claude
     */
    private function makeApiCall(string $prompt): ?string
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'content-type' => 'application/json',
                'anthropic-version' => '2023-06-01'
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

            if ($response->successful()) {
                return $response->json('content.0.text');
            }

            Log::error('Claude API call failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Claude API call exception', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Parse analysis response
     * 
     * @return array<string, mixed>
     */
    private function parseAnalysisResponse(string $response): array
    {
        try {
            return json_decode($response, true) ?: ['raw_response' => $response];
        } catch (\Exception $e) {
            return ['raw_response' => $response, 'parse_error' => $e->getMessage()];
        }
    }

    /**
     * Parse category suggestion
     * 
     * @return array<string, mixed>
     */
    private function parseCategorySuggestion(string $response): array
    {
        try {
            return json_decode($response, true) ?: ['raw_response' => $response];
        } catch (\Exception $e) {
            return ['raw_response' => $response, 'parse_error' => $e->getMessage()];
        }
    }

    /**
     * Parse budget recommendations
     * 
     * @return array<string, mixed>
     */
    private function parseBudgetRecommendations(string $response): array
    {
        try {
            return json_decode($response, true) ?: ['raw_response' => $response];
        } catch (\Exception $e) {
            return ['raw_response' => $response, 'parse_error' => $e->getMessage()];
        }
    }

    /**
     * Parse financial insights
     * 
     * @return array<string, mixed>
     */
    private function parseFinancialInsights(string $response): array
    {
        try {
            return json_decode($response, true) ?: ['raw_response' => $response];
        } catch (\Exception $e) {
            return ['raw_response' => $response, 'parse_error' => $e->getMessage()];
        }
    }
} 