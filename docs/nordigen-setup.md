# Konfiguracja Nordigen Open Banking

## Krok 1: Rejestracja w Nordigen

### 1.1 Utwórz konto
1. Przejdź na [Nordigen](https://nordigen.com/)
2. Kliknij "Get Started" lub "Sign Up"
3. Wypełnij formularz rejestracyjny
4. Potwierdź email

### 1.2 Aktywuj konto
1. Sprawdź email z linkiem aktywacyjnym
2. Kliknij link aktywacyjny
3. Zaloguj się do panelu Nordigen

## Krok 2: Utwórz aplikację

### 2.1 Przejdź do Developer Portal
1. Zaloguj się na [Nordigen Developer Portal](https://ob.nordigen.com/)
2. Przejdź do sekcji "Applications" lub "Dashboard"

### 2.2 Utwórz nową aplikację
1. Kliknij "Create Application" lub "New App"
2. Wypełnij formularz:
   - **Nazwa aplikacji**: `Finances Analyzer`
   - **Opis**: `Personal finance analyzer with Open Banking integration`
   - **Redirect URL**: `http://localhost:8000/banking/callback` (dla development)
   - **Webhook URL**: `http://localhost:8000/api/webhooks/nordigen` (opcjonalnie)

### 2.3 Uzyskaj klucze API
Po utworzeniu aplikacji otrzymasz:
- **Secret ID** (np. `your_secret_id_here`)
- **Secret Key** (np. `your_secret_key_here`)

## Krok 3: Konfiguracja środowiska

### 3.1 Dodaj zmienne do .env
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

### 3.2 Wyczyść cache konfiguracji
```bash
php artisan config:clear
php artisan cache:clear
```

## Krok 4: Testowanie konfiguracji

### 4.1 Uruchom test command
```bash
php artisan test:nordigen
```

### 4.2 Sprawdź logi
```bash
tail -f storage/logs/laravel.log | grep Nordigen
```

## Krok 5: Konfiguracja webhooków (opcjonalnie)

### 5.1 Ustaw webhook URL
W panelu Nordigen:
1. Przejdź do ustawień aplikacji
2. Dodaj webhook URL: `https://yourdomain.com/api/webhooks/nordigen`
3. Ustaw secret dla webhooków

### 5.2 Test webhooków
```bash
# Test webhook signature verification
curl -X POST "http://localhost:8000/api/webhooks/nordigen" \
  -H "Content-Type: application/json" \
  -H "X-Signature: your_signature_here" \
  -d '{"event": "test", "data": {}}'
```

## Krok 6: Integracja z bankami

### 6.1 Dostępne banki w Polsce
Nordigen obsługuje następujące banki:
- PKO Bank Polski SA
- mBank SA
- ING Bank Śląski SA
- Bank Pekao SA
- Santander Bank Polska SA
- Bank Zachodni WBK SA
- Alior Bank SA
- Bank Millennium SA
- Getin Noble Bank SA
- T-Mobile Usługi Bankowe SA

### 6.2 Proces autoryzacji
1. Użytkownik wybiera bank
2. Zostaje przekierowany do strony banku
3. Loguje się do banku
4. Autoryzuje dostęp do danych
5. Wraca do aplikacji z kontami

## Krok 7: Monitoring i debugowanie

### 7.1 Sprawdzanie statusu
```bash
# Sprawdź cache tokenów
php artisan tinker
>>> Cache::get('nordigen_access_token') ? 'Token cached' : 'No token'
```

### 7.2 Debugowanie problemów
```bash
# Wyczyść cache i przetestuj ponownie
php artisan test:nordigen --clear-cache
```

### 7.3 Logi aplikacji
```bash
# Sprawdź logi Nordigen
grep -i nordigen storage/logs/laravel.log
```

## Krok 8: Produkcja

### 8.1 Zmień URL-e na produkcję
```env
# Zmień w .env
NORDIGEN_REDIRECT_URI=https://yourdomain.com/banking/callback
BANKING_WEBHOOK_URL=https://yourdomain.com/api/webhooks/nordigen
```

### 8.2 Skonfiguruj HTTPS
Upewnij się, że aplikacja działa na HTTPS, ponieważ banki wymagają bezpiecznego połączenia.

### 8.3 Monitoring
- Skonfiguruj alerty dla błędów API
- Monitoruj limity API
- Sprawdzaj logi regularnie

## Rozwiązywanie problemów

### Problem: "Authentication failed"
**Rozwiązanie:**
1. Sprawdź poprawność Secret ID i Secret Key
2. Upewnij się, że konto jest aktywne
3. Sprawdź limity API

### Problem: "No institutions found"
**Rozwiązanie:**
1. Sprawdź, czy konto ma dostęp do instytucji
2. Spróbuj z innym krajem (np. 'DE' zamiast 'PL')
3. Sprawdź status API Nordigen

### Problem: "Requisition creation failed"
**Rozwiązanie:**
1. Sprawdź poprawność redirect URL
2. Upewnij się, że URL jest publicznie dostępny
3. Sprawdź, czy instytucja jest aktywna

### Problem: "Webhook signature invalid"
**Rozwiązanie:**
1. Sprawdź BANKING_WEBHOOK_SECRET
2. Upewnij się, że webhook URL jest poprawny
3. Sprawdź logi aplikacji

## Przydatne linki

- [Nordigen API Documentation](https://nordigen.com/en/account_information_documenation/integration/quickstart_guide/)
- [Nordigen Developer Portal](https://ob.nordigen.com/)
- [Open Banking Directory](https://www.openbanking.org.uk/)
- [PSD2 Compliance](https://ec.europa.eu/info/law/payment-services-psd2_en)

## Następne kroki

Po pomyślnej konfiguracji Nordigen:

1. Przetestuj integrację z konkretnym bankiem
2. Skonfiguruj Revolut API
3. Skonfiguruj wFirma API
4. Uruchom automatyczną synchronizację
5. Skonfiguruj powiadomienia Slack 