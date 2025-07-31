# Testowanie RevolutService

## Przegląd

RevolutService umożliwia integrację z Revolut Open Banking API. Aby przetestować serwis, musisz najpierw skonfigurować aplikację w Revolut Developer Portal i uzyskać odpowiednie klucze API.

## Wymagania wstępne

### 1. Konto Revolut
- Zarejestruj się na [Revolut](https://revolut.com/)
- Utwórz konto osobiste lub biznesowe
- Aktywuj konto i zweryfikuj tożsamość

### 2. Revolut Developer Portal
- Przejdź na [Revolut Developer Portal](https://developer.revolut.com/)
- Zaloguj się używając swojego konta Revolut
- Utwórz nową aplikację

### 3. Konfiguracja środowiska
Dodaj następujące zmienne do pliku `.env`:

```env
# Revolut Configuration
REVOLUT_CLIENT_ID=your_client_id_here
REVOLUT_CLIENT_SECRET=your_client_secret_here
REVOLUT_BASE_URL=https://api.revolut.com
REVOLUT_REDIRECT_URI=http://localhost:8000/banking/revolut/callback
REVOLUT_TIMEOUT=30
REVOLUT_RETRY_ATTEMPTS=3

# Webhook Configuration (opcjonalnie)
BANKING_WEBHOOKS_ENABLED=true
BANKING_WEBHOOK_SECRET=your_webhook_secret_here
```

## Testowanie przez API

### 1. Test połączenia

```bash
curl -X POST "http://localhost:8000/api/banking/test-connection" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"provider": "revolut"}'
```

**Oczekiwana odpowiedź:**
```json
{
  "success": true,
  "data": {
    "success": true,
    "provider": "revolut"
  },
  "message": "Połączenie z dostawcą działa poprawnie."
}
```

### 2. Pobieranie URL autoryzacji

```bash
curl -X GET "http://localhost:8000/api/banking/revolut/auth-url?state=test123" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Oczekiwana odpowiedź:**
```json
{
  "success": true,
  "data": {
    "auth_url": "https://api.revolut.com/oauth/authorize?client_id=...&redirect_uri=...&response_type=code&scope=read&state=test123"
  }
}
```

### 3. Wymiana kodu na token

```bash
curl -X POST "http://localhost:8000/api/banking/revolut/exchange-code" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"code": "authorization_code_from_revolut"}'
```

**Oczekiwana odpowiedź:**
```json
{
  "success": true,
  "message": "Autoryzacja Revolut zakończona pomyślnie."
}
```

### 4. Pobieranie kont Revolut

```bash
curl -X GET "http://localhost:8000/api/banking/revolut/accounts" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Oczekiwana odpowiedź:**
```json
{
  "success": true,
  "data": [
    {
      "id": "account_id_here",
      "name": "Main Account",
      "currency": "EUR",
      "balance": 1000.50,
      "status": "active"
    }
  ]
}
```

## Testowanie przez przeglądarkę

### 1. Konfiguracja aplikacji

1. Otwórz aplikację w przeglądarce: `http://localhost:8000`
2. Zaloguj się do systemu
3. Przejdź do sekcji "Banking" lub "Konta bankowe"

### 2. Dodawanie konta Revolut

1. Kliknij "Dodaj konto bankowe"
2. Wybierz "Revolut" jako dostawcę
3. Zostaniesz przekierowany do Revolut w celu autoryzacji
4. Zaloguj się do swojego konta Revolut
5. Autoryzuj dostęp do danych
6. Wróć do aplikacji z kontami

### 3. Synchronizacja danych

1. Po dodaniu konta, kliknij "Synchronizuj"
2. Sprawdź, czy transakcje zostały zaimportowane
3. Sprawdź saldo konta

## Testowanie bezpośrednie serwisu

### 1. Przez Tinker

```bash
php artisan tinker
```

```php
// Test połączenia
$revolut = app(\App\Services\Banking\RevolutService::class);
$connected = $revolut->testConnection();
echo $connected ? "Połączenie OK" : "Błąd połączenia";

// Pobieranie URL autoryzacji
$authUrl = $revolut->getAuthorizationUrl('test123');
echo "Auth URL: " . $authUrl;

// Pobieranie kont
$accounts = $revolut->getAccounts();
print_r($accounts);
```

### 2. Przez Artisan Command

Utwórz command do testowania:

```bash
php artisan make:command TestRevolutService
```

```php
// app/Console/Commands/TestRevolutService.php
public function handle()
{
    $revolut = app(\App\Services\Banking\RevolutService::class);
    
    $this->info('Testing Revolut connection...');
    $connected = $revolut->testConnection();
    
    if ($connected) {
        $this->info('✓ Connection successful');
        
        $accounts = $revolut->getAccounts();
        $this->info('✓ Found ' . count($accounts) . ' accounts');
    } else {
        $this->error('✗ Connection failed');
    }
}
```

## Rozwiązywanie problemów

### 1. Błąd autoryzacji

**Problem:** `Revolut token exchange failed`

**Rozwiązanie:**
- Sprawdź poprawność `REVOLUT_CLIENT_ID` i `REVOLUT_CLIENT_SECRET`
- Upewnij się, że aplikacja jest aktywowana w Revolut Developer Portal
- Sprawdź, czy redirect URI jest poprawny
- Sprawdź limity API

### 2. Błąd OAuth flow

**Problem:** `Invalid authorization code`

**Rozwiązanie:**
- Upewnij się, że kod autoryzacyjny jest świeży (nie starszy niż 10 minut)
- Sprawdź, czy redirect URI jest identyczny w żądaniu i konfiguracji
- Sprawdź, czy aplikacja ma odpowiednie uprawnienia

### 3. Błąd timeout

**Problem:** `Request timeout`

**Rozwiązanie:**
- Zwiększ `REVOLUT_TIMEOUT` w konfiguracji
- Sprawdź połączenie internetowe
- Spróbuj ponownie za kilka minut

### 4. Błąd webhook

**Problem:** `Invalid webhook signature`

**Rozwiązanie:**
- Sprawdź `BANKING_WEBHOOK_SECRET`
- Upewnij się, że webhook URL jest poprawny
- Sprawdź logi aplikacji

## Monitoring i logi

### 1. Sprawdzanie logów

```bash
tail -f storage/logs/laravel.log | grep Revolut
```

### 2. Cache tokenów

```bash
php artisan tinker
```

```php
// Sprawdź cache tokenów
echo Cache::get('revolut_access_token') ? "Access token cached" : "No access token cached";
echo Cache::get('revolut_refresh_token') ? "Refresh token cached" : "No refresh token cached";

// Wyczyść cache
Cache::forget('revolut_access_token');
Cache::forget('revolut_refresh_token');
```

### 3. Statystyki synchronizacji

```bash
curl -X GET "http://localhost:8000/api/banking/sync-statistics" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Następne kroki

Po pomyślnym przetestowaniu RevolutService:

1. Przetestuj WFirmaService
2. Przetestuj BankDataSyncService
3. Skonfiguruj webhooki
4. Uruchom automatyczną synchronizację
5. Skonfiguruj powiadomienia Slack

## Przydatne linki

- [Revolut API Documentation](https://developer.revolut.com/docs/api)
- [Revolut Developer Portal](https://developer.revolut.com/)
- [Revolut Open Banking](https://www.revolut.com/en-US/open-banking/)
- [OAuth 2.0 Documentation](https://oauth.net/2/) 