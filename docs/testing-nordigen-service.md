# Testowanie NordigenService

## Przegląd

NordigenService umożliwia integrację z Nordigen Open Banking API. Aby przetestować serwis, musisz najpierw skonfigurować konto w Nordigen i uzyskać odpowiednie klucze API.

## Wymagania wstępne

### 1. Konto Nordigen
- Zarejestruj się na [Nordigen](https://nordigen.com/)
- Utwórz aplikację w panelu dewelopera
- Uzyskaj `secret_id` i `secret_key`

### 2. Konfiguracja środowiska
Dodaj następujące zmienne do pliku `.env`:

```env
# Nordigen Configuration
NORDIGEN_SECRET_ID=your_secret_id_here
NORDIGEN_SECRET_KEY=your_secret_key_here
NORDIGEN_BASE_URL=https://ob.nordigen.com/api/v2
NORDIGEN_TIMEOUT=30
NORDIGEN_RETRY_ATTEMPTS=3

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
  -d '{"provider": "nordigen"}'
```

**Oczekiwana odpowiedź:**
```json
{
  "success": true,
  "data": {
    "success": true,
    "provider": "nordigen"
  },
  "message": "Połączenie z dostawcą działa poprawnie."
}
```

### 2. Pobieranie instytucji bankowych

```bash
curl -X GET "http://localhost:8000/api/banking/institutions?country=PL" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Oczekiwana odpowiedź:**
```json
{
  "success": true,
  "data": [
    {
      "id": "PKO_BANK_POLSKI_SA",
      "name": "PKO Bank Polski SA",
      "bic": "BPKOPLPW",
      "transaction_total_days": "90"
    },
    {
      "id": "MBANK_SA",
      "name": "mBank SA",
      "bic": "BREXPLPWMBK",
      "transaction_total_days": "90"
    }
  ]
}
```

### 3. Tworzenie requisition

```bash
curl -X POST "http://localhost:8000/api/banking/requisitions" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "institution_id": "PKO_BANK_POLSKI_SA",
    "redirect_url": "http://localhost:8000/banking/callback"
  }'
```

**Oczekiwana odpowiedź:**
```json
{
  "success": true,
  "data": {
    "requisition_id": "12345678-1234-1234-1234-123456789012"
  }
}
```

### 4. Pobieranie kont z requisition

```bash
curl -X GET "http://localhost:8000/api/banking/requisitions/12345678-1234-1234-1234-123456789012" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Oczekiwana odpowiedź:**
```json
{
  "success": true,
  "data": [
    {
      "id": "account_id_here",
      "iban": "PL12345678901234567890123456",
      "currency": "PLN",
      "status": "enabled"
    }
  ]
}
```

## Testowanie przez przeglądarkę

### 1. Konfiguracja aplikacji

1. Otwórz aplikację w przeglądarce: `http://localhost:8000`
2. Zaloguj się do systemu
3. Przejdź do sekcji "Banking" lub "Konta bankowe"

### 2. Dodawanie konta Nordigen

1. Kliknij "Dodaj konto bankowe"
2. Wybierz "Nordigen" jako dostawcę
3. Wybierz bank z listy dostępnych instytucji
4. Zostaniesz przekierowany do strony banku w celu autoryzacji
5. Po autoryzacji wrócisz do aplikacji z kontami

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
$nordigen = app(\App\Services\Banking\NordigenService::class);
$authenticated = $nordigen->authenticate();
echo $authenticated ? "Połączenie OK" : "Błąd połączenia";

// Pobieranie instytucji
$institutions = $nordigen->getInstitutions('PL');
print_r($institutions);

// Tworzenie requisition
$requisitionId = $nordigen->createRequisition('PKO_BANK_POLSKI_SA', 'http://localhost:8000/callback');
echo "Requisition ID: " . $requisitionId;
```

### 2. Przez Artisan Command

Utwórz command do testowania:

```bash
php artisan make:command TestNordigenService
```

```php
// app/Console/Commands/TestNordigenService.php
public function handle()
{
    $nordigen = app(\App\Services\Banking\NordigenService::class);
    
    $this->info('Testing Nordigen connection...');
    $authenticated = $nordigen->authenticate();
    
    if ($authenticated) {
        $this->info('✓ Connection successful');
        
        $institutions = $nordigen->getInstitutions('PL');
        $this->info('✓ Found ' . count($institutions) . ' institutions');
    } else {
        $this->error('✗ Connection failed');
    }
}
```

## Rozwiązywanie problemów

### 1. Błąd autoryzacji

**Problem:** `Nordigen authentication failed`

**Rozwiązanie:**
- Sprawdź poprawność `NORDIGEN_SECRET_ID` i `NORDIGEN_SECRET_KEY`
- Upewnij się, że konto jest aktywne w Nordigen
- Sprawdź limity API

### 2. Błąd timeout

**Problem:** `Request timeout`

**Rozwiązanie:**
- Zwiększ `NORDIGEN_TIMEOUT` w konfiguracji
- Sprawdź połączenie internetowe
- Spróbuj ponownie za kilka minut

### 3. Błąd webhook

**Problem:** `Invalid webhook signature`

**Rozwiązanie:**
- Sprawdź `BANKING_WEBHOOK_SECRET`
- Upewnij się, że webhook URL jest poprawny
- Sprawdź logi aplikacji

## Monitoring i logi

### 1. Sprawdzanie logów

```bash
tail -f storage/logs/laravel.log | grep Nordigen
```

### 2. Cache tokenów

```bash
php artisan tinker
```

```php
// Sprawdź cache tokenów
echo Cache::get('nordigen_access_token') ? "Token cached" : "No token cached";

// Wyczyść cache
Cache::forget('nordigen_access_token');
```

### 3. Statystyki synchronizacji

```bash
curl -X GET "http://localhost:8000/api/banking/sync-statistics" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Następne kroki

Po pomyślnym przetestowaniu NordigenService:

1. Przetestuj RevolutService
2. Przetestuj WFirmaService
3. Przetestuj BankDataSyncService
4. Skonfiguruj webhooki
5. Uruchom automatyczną synchronizację

## Przydatne linki

- [Nordigen API Documentation](https://nordigen.com/en/account_information_documenation/integration/quickstart_guide/)
- [Nordigen Developer Portal](https://ob.nordigen.com/)
- [Open Banking Directory](https://www.openbanking.org.uk/) 