<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackService
{
    private string $webhookUrl;
    private string $botToken;
    private array $channels;
    private array $notifications;
    private array $thresholds;

    public function __construct()
    {
        $this->webhookUrl = config('slack.webhook_url');
        $this->botToken = config('slack.bot_token');
        $this->channels = config('slack.channels', []);
        $this->notifications = config('slack.notifications', []);
        $this->thresholds = config('slack.thresholds', []);
    }

    /**
     * Send notification to Slack
     * 
     * @param array<string, mixed> $data
     * @return bool
     */
    public function sendNotification(array $data): bool
    {
        $channel = $data['channel'] ?? 'general';
        $message = $this->buildMessage($data);
        
        return $this->sendToSlack($message, $channel);
    }

    /**
     * Notify about budget exceeded
     */
    public function notifyBudgetExceeded(string $category, float $amount, float $limit): bool
    {
        if (!$this->notifications['budget_alerts'] ?? false) {
            return false;
        }

        $data = [
            'type' => 'budget_alert',
            'title' => '🚨 Przekroczono limit budżetu',
            'category' => $category,
            'amount' => $amount,
            'limit' => $limit,
            'channel' => $this->channels['alerts'] ?? 'general',
        ];

        return $this->sendNotification($data);
    }

    /**
     * Notify about large transaction
     */
    public function notifyLargeTransaction(float $amount, string $description): bool
    {
        $threshold = $this->thresholds['large_transaction'] ?? 1000;
        
        if ($amount < $threshold) {
            return false;
        }

        $data = [
            'type' => 'large_transaction',
            'title' => '💰 Duża transakcja',
            'amount' => $amount,
            'description' => $description,
            'channel' => $this->channels['transactions'] ?? 'general',
        ];

        return $this->sendNotification($data);
    }

    /**
     * Notify about sync completion
     */
    public function notifySyncCompletion(int $accountCount, int $transactionCount): bool
    {
        if (!$this->notifications['sync_alerts'] ?? false) {
            return false;
        }

        $data = [
            'type' => 'sync_completion',
            'title' => '✅ Synchronizacja zakończona',
            'accounts' => $accountCount,
            'transactions' => $transactionCount,
            'channel' => $this->channels['sync'] ?? 'general',
        ];

        return $this->sendNotification($data);
    }

    /**
     * Notify about report generation
     */
    public function notifyReportGenerated(string $reportType, string $reportUrl = null): bool
    {
        if (!$this->notifications['report_alerts'] ?? false) {
            return false;
        }

        $data = [
            'type' => 'report_generated',
            'title' => '📊 Raport wygenerowany',
            'report_type' => $reportType,
            'report_url' => $reportUrl,
            'channel' => $this->channels['reports'] ?? 'general',
        ];

        return $this->sendNotification($data);
    }

    /**
     * Notify about error
     */
    public function notifyError(string $error, string $context = ''): bool
    {
        if (!$this->notifications['error_alerts'] ?? false) {
            return false;
        }

        $data = [
            'type' => 'error',
            'title' => '❌ Błąd aplikacji',
            'error' => $error,
            'context' => $context,
            'channel' => $this->channels['errors'] ?? 'general',
        ];

        return $this->sendNotification($data);
    }

    /**
     * Build Slack message
     * 
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildMessage(array $data): array
    {
        $type = $data['type'] ?? 'info';
        $title = $data['title'] ?? 'Powiadomienie';
        
        $message = [
            'text' => $title,
            'attachments' => [
                [
                    'color' => $this->getColorForType($type),
                    'fields' => $this->buildFields($data),
                    'footer' => 'Finances App',
                    'ts' => time(),
                ]
            ]
        ];

        return $message;
    }

    /**
     * Build fields for Slack message
     * 
     * @param array<string, mixed> $data
     * @return array<int, array<string, string>>
     */
    private function buildFields(array $data): array
    {
        $fields = [];

        switch ($data['type'] ?? 'info') {
            case 'budget_alert':
                $fields = [
                    [
                        'title' => 'Kategoria',
                        'value' => $data['category'] ?? 'Nieznana',
                        'short' => true
                    ],
                    [
                        'title' => 'Wydano',
                        'value' => number_format($data['amount'], 2) . ' PLN',
                        'short' => true
                    ],
                    [
                        'title' => 'Limit',
                        'value' => number_format($data['limit'], 2) . ' PLN',
                        'short' => true
                    ],
                    [
                        'title' => 'Przekroczenie',
                        'value' => number_format($data['amount'] - $data['limit'], 2) . ' PLN',
                        'short' => true
                    ]
                ];
                break;

            case 'large_transaction':
                $fields = [
                    [
                        'title' => 'Kwota',
                        'value' => number_format($data['amount'], 2) . ' PLN',
                        'short' => true
                    ],
                    [
                        'title' => 'Opis',
                        'value' => $data['description'] ?? 'Brak opisu',
                        'short' => true
                    ]
                ];
                break;

            case 'sync_completion':
                $fields = [
                    [
                        'title' => 'Konta',
                        'value' => $data['accounts'] ?? 0,
                        'short' => true
                    ],
                    [
                        'title' => 'Transakcje',
                        'value' => $data['transactions'] ?? 0,
                        'short' => true
                    ]
                ];
                break;

            case 'report_generated':
                $fields = [
                    [
                        'title' => 'Typ raportu',
                        'value' => $data['report_type'] ?? 'Nieznany',
                        'short' => true
                    ]
                ];
                
                if ($data['report_url'] ?? null) {
                    $fields[] = [
                        'title' => 'Link',
                        'value' => '<' . $data['report_url'] . '|Pobierz raport>',
                        'short' => true
                    ];
                }
                break;

            case 'error':
                $fields = [
                    [
                        'title' => 'Błąd',
                        'value' => $data['error'] ?? 'Nieznany błąd',
                        'short' => false
                    ]
                ];
                
                if ($data['context'] ?? null) {
                    $fields[] = [
                        'title' => 'Kontekst',
                        'value' => $data['context'],
                        'short' => false
                    ];
                }
                break;

            default:
                $fields = [
                    [
                        'title' => 'Wiadomość',
                        'value' => $data['message'] ?? 'Brak wiadomości',
                        'short' => false
                    ]
                ];
        }

        return $fields;
    }

    /**
     * Get color for message type
     */
    private function getColorForType(string $type): string
    {
        return match ($type) {
            'budget_alert' => '#ff0000',
            'large_transaction' => '#ffa500',
            'sync_completion' => '#00ff00',
            'report_generated' => '#0000ff',
            'error' => '#ff0000',
            default => '#cccccc',
        };
    }

    /**
     * Send message to Slack
     * 
     * @param array<string, mixed> $message
     * @return bool
     */
    private function sendToSlack(array $message, string $channel = 'general'): bool
    {
        try {
            if ($this->webhookUrl) {
                // Use webhook
                $response = Http::post($this->webhookUrl, $message);
            } elseif ($this->botToken) {
                // Use bot token
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->botToken,
                    'Content-Type' => 'application/json'
                ])->post('https://slack.com/api/chat.postMessage', [
                    'channel' => $channel,
                    'text' => $message['text'],
                    'attachments' => $message['attachments'] ?? []
                ]);
            } else {
                Log::warning('Slack notification failed: No webhook URL or bot token configured');
                return false;
            }

            if ($response->successful()) {
                Log::info('Slack notification sent successfully', [
                    'channel' => $channel,
                    'type' => $message['attachments'][0]['fields'][0]['title'] ?? 'unknown'
                ]);
                return true;
            } else {
                Log::error('Slack notification failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error('Slack notification exception', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
} 