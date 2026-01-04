# Konfiguracja wFirma API

## Wprowadzenie

wFirma to polski system do zarządzania fakturami, kontrahentami i dokumentami księgowymi. API wFirma umożliwia integrację zewnętrznych systemów z platformą, pozwalając na automatyzację procesów księgowych.

**Ważne:** Zaleca się korzystanie z nowego API, którego dokumentacja znajduje się pod adresem [https://doc.wfirma.pl](https://doc.wfirma.pl). Stare API zostało wyłączone 1 stycznia 2019 roku.

## Krok 1: Uzyskanie kluczy API

### 1.1 Access Key i Secret Key

1. Zaloguj się na swoje konto w wFirma
2. Przejdź do zakładki **Ustawienia** > **Bezpieczeństwo** > **Aplikacje** > **Klucze API**
3. Kliknij przycisk **Dodaj**, aby utworzyć nową parę kluczy API
4. Wprowadź nazwę aplikacji (np. `Finances Analyzer`)
5. Potwierdź operację swoim hasłem do wFirma
6. Po zatwierdzeniu wyświetlą się dwa klucze:
   - **Access Key** (np. `your_access_key_here`)
   - **Secret Key** (np. `your_secret_key_here`)

**Uwaga:** Po zamknięciu okna nie będzie możliwości ponownego odczytania **Secret Key**, więc upewnij się, że został on zapisany w bezpiecznym miejscu.

### 1.2 App Key

Klucz **App Key** jest wymagany do autoryzacji zewnętrznych aplikacji:

1. Wypełnij formularz zgłoszeniowy dostępny na stronie: [https://wfirma.pl/kontakt](https://wfirma.pl/kontakt)
2. W formularzu zaznacz, że potrzebujesz klucza App Key do integracji API
3. Po weryfikacji, klucz **App Key** zostanie przesłany na Twój adres e-mail

### 1.3 ID Firmy

ID Firmy jest wymagane do połączenia z API:

1. Zaloguj się do systemu wFirma
2. Przejdź do zakładki **Ustawienia** > **Moja firma**
3. Znajdź **ID Firmy** (np. `374892`)
4. Skopiuj ID Firmy - będzie potrzebne w konfiguracji

## Krok 2: Konfiguracja środowiska

### 2.1 Dodaj zmienne do .env

```env
# wFirma API Configuration
WFIRMA_ACCESS_KEY=your_access_key_here
WFIRMA_SECRET_KEY=your_secret_key_here
WFIRMA_APP_KEY=your_app_key_here
WFIRMA_COMPANY_ID=your_company_id_here
WFIRMA_BASE_URL=https://api2.wfirma.pl
WFIRMA_TIMEOUT=30
WFIRMA_RETRY_ATTEMPTS=3
```

### 2.2 Konfiguracja w config/wfirma.php

Utwórz plik konfiguracyjny:

```php
<?php

return [
    'access_key' => env('WFIRMA_ACCESS_KEY'),
    'secret_key' => env('WFIRMA_SECRET_KEY'),
    'app_key' => env('WFIRMA_APP_KEY'),
    'company_id' => env('WFIRMA_COMPANY_ID'),
    'base_url' => env('WFIRMA_BASE_URL', 'https://api2.wfirma.pl'),
    'timeout' => env('WFIRMA_TIMEOUT', 30),
    'retry_attempts' => env('WFIRMA_RETRY_ATTEMPTS', 3),
];
```

### 2.3 Wyczyść cache konfiguracji

```bash
php artisan config:clear
php artisan cache:clear
```

## Krok 3: Autoryzacja API

### 3.1 Metoda autoryzacji

wFirma API używa autoryzacji OAuth 1.0 z podpisem HMAC-SHA1. Wymagane są następujące parametry:

- **Access Key** - identyfikator aplikacji
- **Secret Key** - klucz prywatny do podpisywania żądań
- **App Key** - klucz aplikacji (wymagany dla zewnętrznych integracji)
- **Company ID** - identyfikator firmy w systemie wFirma

### 3.2 Format żądań

Wszystkie żądania do API wFirma muszą zawierać:

1. **Nagłówki autoryzacji** - OAuth 1.0 signature
2. **Company ID** - jako parametr URL `?company_id=xxx`
3. **Content-Type** - `application/json` lub `application/xml`

### 3.3 Przykład żądania

```bash
curl -X GET "https://api2.wfirma.pl/invoices?company_id=374892" \
  -H "Authorization: OAuth oauth_consumer_key=\"ACCESS_KEY\", oauth_signature_method=\"HMAC-SHA1\", oauth_signature=\"SIGNATURE\"" \
  -H "Content-Type: application/json"
```

## Krok 4: Dostępne funkcjonalności API

### 4.1 Faktury

API wFirma umożliwia zarządzanie fakturami:

- **Wystawianie faktur VAT** - automatyczne tworzenie faktur
- **Wystawianie faktur proforma** - faktury proforma
- **Pobieranie faktur** - odczyt istniejących faktur
- **Edycja faktur** - modyfikacja faktur
- **Usuwanie faktur** - usuwanie faktur (z ograniczeniami)
- **Pobieranie PDF** - generowanie i pobieranie faktur w formacie PDF
- **Wysyłanie faktur** - automatyczne wysyłanie faktur na e-mail kontrahenta

**Endpointy:**
- `GET /invoices` - lista faktur
- `GET /invoices/{id}` - szczegóły faktury
- `POST /invoices` - utworzenie faktury
- `PUT /invoices/{id}` - aktualizacja faktury
- `DELETE /invoices/{id}` - usunięcie faktury
- `GET /invoices/{id}/download` - pobranie PDF

### 4.2 Kontrahenci

Zarządzanie kontrahentami:

- **Dodawanie kontrahentów** - automatyczne tworzenie kontrahentów
- **Pobieranie kontrahentów** - lista i szczegóły kontrahentów
- **Edycja kontrahentów** - modyfikacja danych kontrahentów
- **Usuwanie kontrahentów** - usuwanie kontrahentów

**Endpointy:**
- `GET /contractors` - lista kontrahentów
- `GET /contractors/{id}` - szczegóły kontrahenta
- `POST /contractors` - utworzenie kontrahenta
- `PUT /contractors/{id}` - aktualizacja kontrahenta
- `DELETE /contractors/{id}` - usunięcie kontrahenta

### 4.3 Produkty i usługi

Zarządzanie produktami i usługami:

- **Dodawanie produktów** - tworzenie produktów/usług
- **Pobieranie produktów** - lista i szczegóły produktów
- **Edycja produktów** - modyfikacja produktów
- **Usuwanie produktów** - usuwanie produktów

**Endpointy:**
- `GET /goods` - lista produktów
- `GET /goods/{id}` - szczegóły produktu
- `POST /goods` - utworzenie produktu
- `PUT /goods/{id}` - aktualizacja produktu
- `DELETE /goods/{id}` - usunięcie produktu

### 4.4 Paragony

Zarządzanie paragonami:

- **Wystawianie paragonów fiskalnych** - paragony fiskalne
- **Wystawianie paragonów niefiskalnych** - paragony niefiskalne
- **Pobieranie paragonów** - odczyt paragonów

**Endpointy:**
- `GET /receipts` - lista paragonów
- `GET /receipts/{id}` - szczegóły paragonu
- `POST /receipts` - utworzenie paragonu

### 4.5 Dokumenty sprzedaży

Zarządzanie innymi dokumentami:

- **Faktury korygujące** - korekty faktur
- **Rachunki** - rachunki
- **WZ (Wydania Zewnętrzne)** - dokumenty wydań zewnętrznych
- **PZ (Przyjęcia Zewnętrzne)** - dokumenty przyjęć zewnętrznych

## Krok 5: Przykłady użycia

### 5.1 Utworzenie faktury

```php
$data = [
    'invoice' => [
        'contractor' => [
            'name' => 'Jan Kowalski',
            'nip' => '1234567890',
            'street' => 'ul. Przykładowa 1',
            'zip' => '00-000',
            'city' => 'Warszawa',
        ],
        'invoiceitems' => [
            [
                'name' => 'Usługa programistyczna',
                'count' => 1,
                'price' => 1000.00,
                'vat' => 23,
            ],
        ],
        'paymentmethod' => 'przelew',
        'paymentdate' => date('Y-m-d', strtotime('+14 days')),
    ],
];

$response = $wfirmaService->createInvoice($data);
```

### 5.2 Pobranie listy faktur

```php
$filters = [
    'date_from' => '2024-01-01',
    'date_to' => '2024-12-31',
    'status' => 'issued',
];

$invoices = $wfirmaService->getInvoices($filters);
```

### 5.3 Pobranie faktury w formacie PDF

```php
$invoiceId = 12345;
$pdfContent = $wfirmaService->downloadInvoicePdf($invoiceId);

// Zapis do pliku
file_put_contents("invoice_{$invoiceId}.pdf", $pdfContent);
```

### 5.4 Wysyłanie faktury na e-mail

```php
$invoiceId = 12345;
$email = 'kontrahent@example.com';

$result = $wfirmaService->sendInvoiceByEmail($invoiceId, $email);
```

### 5.5 Utworzenie kontrahenta

```php
$contractor = [
    'contractor' => [
        'name' => 'Firma Sp. z o.o.',
        'nip' => '1234567890',
        'street' => 'ul. Przykładowa 1',
        'zip' => '00-000',
        'city' => 'Warszawa',
        'email' => 'firma@example.com',
        'phone' => '+48 123 456 789',
    ],
];

$response = $wfirmaService->createContractor($contractor);
```

## Krok 6: Testowanie konfiguracji

### 6.1 Test połączenia

```bash
php artisan tinker
>>> $wfirma = app(\App\Services\WfirmaService::class);
>>> $wfirma->testConnection();
```

### 6.2 Test pobierania faktur

```bash
php artisan tinker
>>> $wfirma = app(\App\Services\WfirmaService::class);
>>> $invoices = $wfirma->getInvoices(['limit' => 5]);
>>> dd($invoices);
```

### 6.3 Sprawdź logi

```bash
tail -f storage/logs/laravel.log | grep wFirma
```

## Krok 7: Obsługa błędów

### 7.1 Typowe błędy

**Błąd: "Invalid credentials"**
- Sprawdź poprawność Access Key, Secret Key i App Key
- Upewnij się, że klucze nie zawierają dodatkowych spacji
- Sprawdź, czy konto wFirma jest aktywne

**Błąd: "Company ID not found"**
- Sprawdź poprawność Company ID
- Upewnij się, że Company ID jest dodane jako parametr URL

**Błąd: "Signature invalid"**
- Sprawdź poprawność Secret Key
- Upewnij się, że używasz poprawnej metody podpisywania (HMAC-SHA1)
- Sprawdź, czy parametry OAuth są poprawnie sformatowane

**Błąd: "Rate limit exceeded"**
- API wFirma ma limity żądań
- Zaimplementuj retry logic z exponential backoff
- Rozważ cachowanie odpowiedzi

### 7.2 Logowanie błędów

```php
try {
    $response = $wfirmaService->createInvoice($data);
} catch (\Exception $e) {
    \Log::error('wFirma API Error', [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'data' => $data,
    ]);
    
    throw $e;
}
```

## Krok 8: Produkcja

### 8.1 Bezpieczeństwo

- **Nigdy nie commituj kluczy API** do repozytorium
- Używaj zmiennych środowiskowych dla wszystkich kluczy
- Rotuj klucze API regularnie
- Używaj HTTPS dla wszystkich żądań

### 8.2 Monitoring

- Skonfiguruj alerty dla błędów API
- Monitoruj limity API
- Sprawdzaj logi regularnie
- Śledź czas odpowiedzi API

### 8.3 Optymalizacja

- Implementuj cachowanie odpowiedzi tam, gdzie to możliwe
- Używaj paginacji dla dużych list
- Unikaj niepotrzebnych żądań
- Grupuj operacje, gdy to możliwe

## Krok 9: Integracja z systemem

### 9.1 Automatyczne wystawianie faktur

Możesz zautomatyzować proces wystawiania faktur na podstawie transakcji:

```php
// Przykład: automatyczne wystawianie faktury dla transakcji
$transaction = Transaction::find($id);

if ($transaction->shouldCreateInvoice()) {
    $invoiceData = [
        'invoice' => [
            'contractor' => $transaction->getContractorData(),
            'invoiceitems' => $transaction->getInvoiceItems(),
            // ... inne dane
        ],
    ];
    
    $invoice = $wfirmaService->createInvoice($invoiceData);
    $transaction->linkInvoice($invoice);
}
```

### 9.2 Synchronizacja kontrahentów

Automatyczna synchronizacja kontrahentów z wFirma:

```php
// Pobierz kontrahentów z wFirma
$wfirmaContractors = $wfirmaService->getContractors();

foreach ($wfirmaContractors as $contractor) {
    Contractor::updateOrCreate(
        ['wfirma_id' => $contractor['id']],
        [
            'name' => $contractor['name'],
            'nip' => $contractor['nip'],
            // ... inne pola
        ]
    );
}
```

## Rozwiązywanie problemów

### Problem: "OAuth signature invalid"

**Rozwiązanie:**
1. Sprawdź poprawność Secret Key
2. Upewnij się, że używasz poprawnej metody podpisywania
3. Sprawdź, czy wszystkie parametry OAuth są poprawnie sformatowane
4. Zweryfikuj, czy używasz poprawnego base URL

### Problem: "Invoice creation failed"

**Rozwiązanie:**
1. Sprawdź, czy wszystkie wymagane pola są wypełnione
2. Zweryfikuj format danych (daty, kwoty)
3. Sprawdź, czy kontrahent istnieje w systemie
4. Sprawdź logi wFirma dla szczegółów błędu

### Problem: "PDF download failed"

**Rozwiązanie:**
1. Sprawdź, czy faktura została poprawnie utworzona
2. Upewnij się, że faktura ma status umożliwiający pobranie PDF
3. Sprawdź uprawnienia do pobierania dokumentów
4. Zweryfikuj, czy faktura nie jest w trakcie przetwarzania

### Problem: "Rate limit exceeded"

**Rozwiązanie:**
1. Zaimplementuj retry logic z exponential backoff
2. Rozważ cachowanie odpowiedzi
3. Ogranicz częstotliwość żądań
4. Skontaktuj się z wFirma w sprawie zwiększenia limitu

## Przydatne linki

- [wFirma API Documentation](https://doc.wfirma.pl)
- [wFirma Pomoc - API](https://pomoc.wfirma.pl/-api-interfejs-dla-programistow)
- [wFirma Kontakt](https://wfirma.pl/kontakt)
- [OAuth 1.0 Specification](https://oauth.net/1/)

## Następne kroki

Po pomyślnej konfiguracji wFirma API:

1. Przetestuj integrację z konkretnymi funkcjonalnościami
2. Zaimplementuj automatyczne wystawianie faktur
3. Skonfiguruj synchronizację kontrahentów
4. Uruchom automatyczną synchronizację dokumentów
5. Skonfiguruj powiadomienia o błędach

