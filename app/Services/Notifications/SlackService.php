<?php

namespace App\Services\Notifications;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

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
        $this->channels = config('slack.channels');
        $this->notifications = config('slack.notifications');
        $this->thresholds = config('slack.thresholds');
    }

    public function sendNotification(string $type, array $data = []): bool
    {
        if (!$this->notifications[$type] ?? false) {
            return false;
        }

        try {
            $message = $this->buildMessage($type, $data);
            $channel = $this->getChannelForType($type);
            
            return $this->sendToSlack($channel, $message);
        } catch (Exception $e) {
            Log::error('Slack notification failed', [
                'type' => $type,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function notifyBudgetExceeded(User $user, string $category, float $amount, float $budget): bool
    {
        if (!$this->notifications['budget_exceeded']) {
            return false;
        }

        $data = [
            'user' => $user->name,
            'category' => $category,
            'amount' => number_format($amount, 2),
            'budget' => number_format($budget, 2),
            'percentage' => round(($amount / $budget) * 100, 1),
        ];

        return $this->sendNotification('budget_exceeded', $data);
    }

    public function notifyLargeTransaction(Transaction $transaction): bool
    {
        if (!$this->notifications['large_transactions']) {
            return false;
        }

        $threshold = $this->thresholds['large_transaction_amount'];
        
        if (abs($transaction->amount) < $threshold) {
            return false;
        }

        $data = [
            'user' => $transaction->user->name,
            'amount' => $transaction->formatted_amount,
            'description' => $transaction->description,
            'date' => $transaction->transaction_date->format('Y-m-d'),
            'category' => $transaction->category?->name ?? 'Nieprzypisana',
        ];

        return $this->sendNotification('large_transaction', $data);
    }

    public function notifySyncCompleted(User $user, int $importedCount, string $provider): bool
    {
        if (!$this->notifications['sync_completed']) {
            return false;
        }

        $data = [
            'user' => $user->name,
            'count' => $importedCount,
            'provider' => $provider,
            'date' => now()->format('Y-m-d H:i'),
        ];

        return $this->sendNotification('sync_completed', $data);
    }

    public function notifyReportGenerated(User $user, string $reportType, string $reportUrl = null): bool
    {
        if (!$this->notifications['report_generated']) {
            return false;
        }

        $data = [
            'user' => $user->name,
            'report_type' => $reportType,
            'report_url' => $reportUrl,
            'date' => now()->format('Y-m-d H:i'),
        ];

        return $this->sendNotification('report_generated', $data);
    }

    public function notifyError(string $error, array $context = []): bool
    {
        if (!$this->notifications['error_alerts']) {
            return false;
        }

        $data = [
            'error' => $error,
            'context' => $context,
            'date' => now()->format('Y-m-d H:i:s'),
        ];

        return $this->sendNotification('error_alert', $data);
    }

    private function buildMessage(string $type, array $data): array
    {
        $templates = config('slack.message_templates');
        $template = $templates[$type] ?? [];

        $text = $template['text'] ?? '';
        $color = $template['color'] ?? '#36a64f';

        // Replace placeholders with actual data
        foreach ($data as $key => $value) {
            $text = str_replace(":{$key}", $value, $text);
        }

        return [
            'attachments' => [
                [
                    'color' => $color,
                    'text' => $text,
                    'fields' => $this->buildFields($type, $data),
                    'footer' => 'Finances Analyzer',
                    'ts' => time(),
                ],
            ],
        ];
    }

    private function buildFields(string $type, array $data): array
    {
        $fields = [];

        switch ($type) {
            case 'budget_exceeded':
                $fields = [
                    [
                        'title' => 'Kategoria',
                        'value' => $data['category'] ?? 'N/A',
                        'short' => true,
                    ],
                    [
                        'title' => 'Wydatki',
                        'value' => ($data['amount'] ?? 0) . ' PLN',
                        'short' => true,
                    ],
                    [
                        'title' => 'Budżet',
                        'value' => ($data['budget'] ?? 0) . ' PLN',
                        'short' => true,
                    ],
                    [
                        'title' => 'Procent',
                        'value' => ($data['percentage'] ?? 0) . '%',
                        'short' => true,
                    ],
                ];
                break;

            case 'large_transaction':
                $fields = [
                    [
                        'title' => 'Kwota',
                        'value' => $data['amount'] ?? 'N/A',
                        'short' => true,
                    ],
                    [
                        'title' => 'Opis',
                        'value' => $data['description'] ?? 'N/A',
                        'short' => true,
                    ],
                    [
                        'title' => 'Data',
                        'value' => $data['date'] ?? 'N/A',
                        'short' => true,
                    ],
                    [
                        'title' => 'Kategoria',
                        'value' => $data['category'] ?? 'N/A',
                        'short' => true,
                    ],
                ];
                break;

            case 'sync_completed':
                $fields = [
                    [
                        'title' => 'Użytkownik',
                        'value' => $data['user'] ?? 'N/A',
                        'short' => true,
                    ],
                    [
                        'title' => 'Zaimportowane transakcje',
                        'value' => $data['count'] ?? 0,
                        'short' => true,
                    ],
                    [
                        'title' => 'Dostawca',
                        'value' => $data['provider'] ?? 'N/A',
                        'short' => true,
                    ],
                    [
                        'title' => 'Data',
                        'value' => $data['date'] ?? 'N/A',
                        'short' => true,
                    ],
                ];
                break;

            case 'report_generated':
                $fields = [
                    [
                        'title' => 'Użytkownik',
                        'value' => $data['user'] ?? 'N/A',
                        'short' => true,
                    ],
                    [
                        'title' => 'Typ raportu',
                        'value' => $data['report_type'] ?? 'N/A',
                        'short' => true,
                    ],
                    [
                        'title' => 'Data',
                        'value' => $data['date'] ?? 'N/A',
                        'short' => true,
                    ],
                ];
                break;

            case 'error_alert':
                $fields = [
                    [
                        'title' => 'Błąd',
                        'value' => $data['error'] ?? 'N/A',
                        'short' => false,
                    ],
                    [
                        'title' => 'Data',
                        'value' => $data['date'] ?? 'N/A',
                        'short' => true,
                    ],
                ];
                break;
        }

        return $fields;
    }

    private function getChannelForType(string $type): string
    {
        switch ($type) {
            case 'budget_exceeded':
            case 'large_transaction':
                return $this->channels['alerts'];
            case 'sync_completed':
            case 'report_generated':
                return $this->channels['notifications'];
            case 'error_alert':
                return $this->channels['alerts'];
            default:
                return $this->channels['notifications'];
        }
    }

    private function sendToSlack(string $channel, array $message): bool
    {
        try {
            if ($this->webhookUrl) {
                // Using webhook
                $response = Http::timeout(10)->post($this->webhookUrl, $message);
            } elseif ($this->botToken) {
                // Using bot token
                $message['channel'] = $channel;
                $response = Http::withToken($this->botToken)
                    ->timeout(10)
                    ->post('https://slack.com/api/chat.postMessage', $message);
            } else {
                Log::error('Slack configuration missing');
                return false;
            }

            if ($response->successful()) {
                Log::info('Slack notification sent', [
                    'channel' => $channel,
                    'type' => $message['attachments'][0]['text'] ?? 'unknown',
                ]);
                return true;
            }

            Log::error('Slack notification failed', [
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Slack notification error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
} 