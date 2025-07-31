# Konfiguracja Revolut Open Banking

## Krok 1: Konto Revolut

### 1.1 Utwórz konto Revolut
1. Przejdź na [Revolut](https://revolut.com/)
2. Kliknij "Get Started" lub "Sign Up"
3. Wypełnij formularz rejestracyjny:
   - Imię i nazwisko
   - Email
   - Numer telefonu
   - Hasło
4. Potwierdź email i numer telefonu

### 1.2 Aktywuj konto
1. Pobierz aplikację Revolut na telefon
2. Zaloguj się używając danych z rejestracji
3. Zweryfikuj tożsamość (selfie + dokument)
4. Aktywuj kartę (jeśli wybrana)

### 1.3 Uzupełnij konto
1. Dodaj środki do konta (przelew, karta)
2. Sprawdź, czy konto jest w pełni aktywne
3. Upewnij się, że masz dostęp do API

## Krok 2: Revolut Developer Portal

### 2.1 Przejdź do Developer Portal
1. Otwórz [Revolut Developer Portal](https://developer.revolut.com/)
2. Kliknij "Sign In"
3. Zaloguj się używając swojego konta Revolut

### 2.2 Utwórz aplikację
1. Kliknij "Create App" lub "New Application"
2. Wypełnij formularz:
   - **Nazwa aplikacji**: `Finances Analyzer`
   - **Opis**: `Personal finance analyzer with Revolut integration`
   - **Redirect URI**: `http://localhost:8000/banking/revolut/callback` (dla development)
   - **Webhook URL**: `http://localhost:8000/api/webhooks/revolut` (opcjonalnie)

### 2.3 Uzyskaj klucze API
Po utworzeniu aplikacji otrzymasz:
- **Client ID** (np. `your_client_id_here`)
- **Client Secret** (np. `your_client_secret_here`)

### 2.4 Skonfiguruj uprawnienia
1. Przejdź do ustawień aplikacji
2. Włącz uprawnienia:
   - `read` - odczyt danych konta
   - `write` - zapis danych (opcjonalnie)
3. Zapisz zmiany

## Krok 3: Konfiguracja środowiska

### 3.1 Dodaj zmienne do .env
```env
# Revolut Configuration
REVOLUT_CLIENT_ID=your_client_id_here
REVOLUT_CLIENT_SECRET=your_client_secret_here
REVOLut_BASE_URL=https://api.revolut.com
REVOLUT_REDIRECT_URI=http://localhost:8000/banking/revolut/callback
REVOLUT_TIMEOUT=30
REVOLUT_RETRY_ATTEMPTS=3

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
php artisan make:command TestRevolutService
```

Następnie uruchom:
```bash
php artisan test:revolut
```

### 4.2 Sprawdź logi
```bash
tail -f storage/logs/laravel.log | grep Revolut
```

## Krok 5: OAuth 2.0 Flow

### 5.1 Proces autoryzacji
1. Użytkownik klika "Dodaj konto Revolut"
2. Aplikacja generuje URL autoryzacji
3. Użytkownik zostaje przekierowany do Revolut
4. Loguje się do swojego konta Revolut
5. Autoryzuje dostęp do danych
6. Revolut przekierowuje z kodem autoryzacyjnym
7. Aplikacja wymienia kod na token dostępu

### 5.2 Test OAuth flow
```bash
# 1. Pobierz URL autoryzacji
curl -X GET "http://localhost:8000/api/banking/revolut/auth-url?state=test123" \
  -H "Authorization: Bearer YOUR_TOKEN"

# 2. Otwórz URL w przeglądarce i przejdź przez autoryzację

# 3. Wymień kod na token
curl -X POST "http://localhost:8000/api/banking/revolut/exchange-code" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"code": "authorization_code_from_revolut"}'
```

## Krok 6: Konfiguracja webhooków (opcjonalnie)

### 6.1 Ustaw webhook URL
W Revolut Developer Portal:
1. Przejdź do ustawień aplikacji
2. Dodaj webhook URL: `https://yourdomain.com/api/webhooks/revolut`
3. Ustaw secret dla webhooków

### 6.2 Test webhooków
```bash
# Test webhook signature verification
curl -X POST "http://localhost:8000/api/webhooks/revolut" \
  -H "Content-Type: application/json" \
  -H "X-Revolut-Signature: your_signature_here" \
  -d '{"event": "test", "data": {}}'
```

## Krok 7: Integracja z kontami

### 7.1 Dostępne typy kont
Revolut obsługuje:
- **Konta osobiste** - standardowe konta Revolut
- **Konta biznesowe** - dla firm
- **Konta młodzieżowe** - dla osób 13-17 lat
- **Konta premium** - z dodatkowymi funkcjami

### 7.2 Waluty
Revolut obsługuje wiele walut:
- EUR (Euro)
- USD (Dolar amerykański)
- GBP (Funt brytyjski)
- PLN (Złoty polski)
- I wiele innych

### 7.3 Funkcje konta
- Transakcje w czasie rzeczywistym
- Historia transakcji
- Saldo konta
- Kursy walut
- Karty wirtualne i fizyczne

## Krok 8: Monitoring i debugowanie

### 8.1 Sprawdzanie statusu
```bash
# Sprawdź cache tokenów
php artisan tinker
>>> Cache::get('revolut_access_token') ? 'Access token cached' : 'No access token'
>>> Cache::get('revolut_refresh_token') ? 'Refresh token cached' : 'No refresh token'
```

### 8.2 Debugowanie problemów
```bash
# Wyczyść cache i przetestuj ponownie
php artisan test:revolut --clear-cache
```

### 8.3 Logi aplikacji
```bash
# Sprawdź logi Revolut
grep -i revolut storage/logs/laravel.log
```

## Krok 9: Produkcja

### 9.1 Zmień URL-e na produkcję
```env
# Zmień w .env
REVOLUT_REDIRECT_URI=https://yourdomain.com/banking/revolut/callback
BANKING_WEBHOOK_URL=https://yourdomain.com/api/webhooks/revolut
```

### 9.2 Skonfiguruj HTTPS
Upewnij się, że aplikacja działa na HTTPS, ponieważ Revolut wymaga bezpiecznego połączenia.

### 9.3 Monitoring
- Skonfiguruj alerty dla błędów API
- Monitoruj limity API
- Sprawdzaj logi regularnie

## Rozwiązywanie problemów

### Problem: "Client ID not found"
**Rozwiązanie:**
1. Sprawdź poprawność REVOLUT_CLIENT_ID
2. Upewnij się, że aplikacja jest aktywowana
3. Sprawdź, czy konto Revolut jest aktywne

### Problem: "Invalid redirect URI"
**Rozwiązanie:**
1. Sprawdź, czy redirect URI jest identyczny w aplikacji i konfiguracji
2. Upewnij się, że URL jest publicznie dostępny
3. Sprawdź, czy nie ma dodatkowych parametrów

### Problem: "Authorization code expired"
**Rozwiązanie:**
1. Kod autoryzacyjny wygasa po 10 minutach
2. Wygeneruj nowy URL autoryzacji
3. Przejdź przez OAuth flow ponownie

### Problem: "Access token expired"
**Rozwiązanie:**
1. Refresh token jest automatycznie używany
2. Sprawdź, czy refresh token jest w cache
3. Jeśli nie, przejdź przez OAuth flow ponownie

### Problem: "Webhook signature invalid"
**Rozwiązanie:**
1. Sprawdź BANKING_WEBHOOK_SECRET
2. Upewnij się, że webhook URL jest poprawny
3. Sprawdź logi aplikacji

## Przydatne linki

- [Revolut API Documentation](https://developer.revolut.com/docs/api)
- [Revolut Developer Portal](https://developer.revolut.com/)
- [Revolut Open Banking](https://www.revolut.com/en-US/open-banking/)
- [OAuth 2.0 Documentation](https://oauth.net/2/)
- [Revolut Support](https://help.revolut.com/)

## Następne kroki

Po pomyślnej konfiguracji Revolut:

1. Przetestuj integrację z konkretnym kontem
2. Skonfiguruj WFirma API
3. Uruchom automatyczną synchronizację
4. Skonfiguruj powiadomienia Slack
5. Przetestuj webhooki 