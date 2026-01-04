# Dokumentacja struktury danych modeli wFirma

## Spis treści

1. [Wprowadzenie](#wprowadzenie)
2. [Przegląd modeli](#przegląd-modeli)
3. [Szczegółowa dokumentacja modeli](#szczegółowa-dokumentacja-modeli)
   - [WFirmaInvoice - Faktury sprzedażowe](#wfirmainvoice---faktury-sprzedażowe)
   - [WFirmaInvoiceContent - Zawartość faktur](#wfirmainvoicecontent---zawartość-faktur)
   - [WFirmaExpense - Faktury wydatkowe](#wfirmaexpense---faktury-wydatkowe)
   - [WFirmaExpensePart - Części wydatków](#wfirmaexpensepart---części-wydatków)
   - [WFirmaIncome - Przychody](#wfirmaincome---przychody)
   - [WFirmaPayment - Płatności](#wfirmapayment---płatności)
   - [WFirmaTerm - Terminarz](#wfirmaterm---terminarz)
   - [WFirmaInterest - Rozliczenia ZUS](#wfirmainterest---rozliczenia-zus)
4. [Relacje między modelami](#relacje-między-modelami)
5. [Scope'y i metody pomocnicze](#scopey-i-metody-pomocnicze)
6. [Przykłady użycia](#przykłady-użycia)

---

## Wprowadzenie

Dokumentacja opisuje strukturę danych modeli Laravel odzwierciedlających dane z systemu wFirma API. Wszystkie modele mają przedrostek `WFirma` i są zgodne z dokumentacją API dostępną pod adresem: https://doc.wfirma.pl/#moduly

### Wspólne cechy wszystkich modeli

- **Przedrostek**: Wszystkie modele zaczynają się od `WFirma`
- **Pole `wfirma_id`**: ID rekordu z wFirma API (unikalny identyfikator w systemie wFirma)
- **Pole `user_id`**: Relacja do użytkownika w systemie lokalnym
- **Pole `metadata`**: JSON dla dodatkowych danych z API
- **Pole `synced_at`**: Data i czas ostatniej synchronizacji z wFirma
- **Relacja `user()`**: Wszystkie modele mają relację do modelu `User`

---

## Przegląd modeli

| Model | Tabela | Moduł wFirma | Opis |
|-------|--------|--------------|------|
| `WFirmaInvoice` | `wfirma_invoices` | `invoices` | Faktury sprzedażowe (przychody) |
| `WFirmaInvoiceContent` | `wfirma_invoice_contents` | `invoices > invoicecontents` | Pozycje faktur sprzedażowych |
| `WFirmaExpense` | `wfirma_expenses` | `expenses` | Faktury wydatkowe |
| `WFirmaExpensePart` | `wfirma_expense_parts` | `expenses > expense_parts` | Pozycje faktur wydatkowych |
| `WFirmaIncome` | `wfirma_incomes` | `incomes` | Przychody |
| `WFirmaPayment` | `wfirma_payments` | `payments` | Płatności |
| `WFirmaTerm` | `wfirma_terms` | `terms` | Terminarz |
| `WFirmaInterest` | `wfirma_interests` | `interests` | Rozliczenia ZUS |

---

## Szczegółowa dokumentacja modeli

### WFirmaInvoice - Faktury sprzedażowe

**Klasa**: `App\Models\WFirmaInvoice`  
**Tabela**: `wfirma_invoices`  
**Moduł wFirma**: `invoices`  
**Dokumentacja API**: https://doc.wfirma.pl/#moduly

#### Opis

Model reprezentujący faktury sprzedażowe (przychody) z systemu wFirma. Obsługuje różne typy dokumentów: faktury VAT, pro forma, oferty, paragony, inne przychody.

#### Rodzaje dokumentów

**Dokumenty płatnika VAT:**
- `normal` - Faktura VAT
- `margin` - Faktura VAT marża
- `proforma` - Pro forma
- `offer` - Oferta
- `receipt_normal` - Dowód sprzedaży / Paragon niefiskalny
- `receipt_fiscal_normal` - Paragon fiskalny
- `income_normal` - Inny przychód - sprzedaż

**Dokumenty nievatowca:**
- `bill` - Faktura (bez VAT)
- `proforma_bill` - Pro forma (bez VAT)
- `offer_bill` - Oferta (bez VAT)
- `receipt_bill` - Dowód sprzedaży / Paragon niefiskalny (bez VAT)
- `receipt_fiscal_bill` - Paragon fiskalny (bez VAT)
- `income_bill` - Inny przychód - sprzedaż (bez VAT)

#### Pola

##### Identyfikatory

| Pole | Typ | Opis | Wartości |
|------|-----|------|----------|
| `id` | `bigint` | Klucz główny (auto) | - |
| `wfirma_id` | `string` | ID z wFirma API | - |
| `user_id` | `bigint` | ID użytkownika | Foreign key do `users.id` |
| `contractor_id` | `string` | ID kontrahenta z wFirma | - |
| `series_id` | `string` | ID serii numeracji | - |

##### Typ i numeracja

| Pole | Typ | Opis | Wartości |
|------|-----|------|----------|
| `type` | `string` | Typ dokumentu | `normal`, `margin`, `proforma`, `offer`, `receipt_normal`, `receipt_fiscal_normal`, `income_normal`, `bill`, `proforma_bill`, `offer_bill`, `receipt_bill`, `receipt_fiscal_bill`, `income_bill` |
| `number` | `string` | Numer wstawiany w miejsce znacznika [numer] | - |
| `day` | `string` | Dzień wstawiany w miejsce znacznika [dzień] | - |
| `month` | `string` | Miesiąc wstawiany w miejsce znacznika [miesiąc] | - |
| `year` | `string` | Rok wstawiany w miejsce znacznika [rok] | - |
| `fullnumber` | `string` | Numer wygenerowany na podstawie wzorca serii numeracji | Tylko odczyt |
| `semitemplatenumber` | `string` | Częściowo wygenerowany numer | Tylko odczyt |

##### Daty

| Pole | Typ | Opis | Format |
|------|-----|------|--------|
| `date` | `date` | Data wystawienia faktury | `YYYY-MM-DD` |
| `disposaldate` | `date` | Data sprzedaży | `YYYY-MM-DD` |
| `disposaldate_empty` | `boolean` | Czy data sprzedaży jest pusta (sprzedaż wysyłkowa) | `0`, `1` |
| `disposaldate_format` | `string` | Format daty sprzedaży na wydruku | `month`, `day` |
| `paymentdate` | `date` | Termin płatności | `YYYY-MM-DD` |
| `currency_date` | `date` | Data opublikowania kursu | Tylko odczyt |

##### Płatności

| Pole | Typ | Opis | Wartości |
|------|-----|------|----------|
| `paymentmethod` | `string` | Metoda płatności | `cash`, `transfer`, `compensation`, `cod`, `payment_card` |
| `paymentstate` | `string` | Stan płatności | `paid`, `unpaid`, `undefined` |
| `alreadypaid_initial` | `decimal(15,2)` | Kwota zapłacono określona przy dodawaniu faktury | - |
| `alreadypaid` | `decimal(15,2)` | Kwota zapłacono uwzględniająca wszystkie płatności | Tylko odczyt |

##### Waluta i kursy

| Pole | Typ | Opis | Tylko odczyt |
|------|-----|------|-------------|
| `currency` | `string` | Waluta | - |
| `currency_exchange` | `decimal(15,4)` | Kurs księgowy faktury | Tak |
| `currency_label` | `string` | Numer tabeli NBP kursu księgowego | Tak |
| `price_currency_exchange` | `decimal(15,4)` | Kurs stosowany przy przeliczaniu cen w panelu wfirmy | Tak |
| `good_price_group_currency_exchange` | `decimal(15,4)` | Kurs grupy cenowej | Tak |

##### Kwoty

| Pole | Typ | Opis | Tylko odczyt |
|------|-----|------|-------------|
| `netto` | `decimal(15,2)` | Wartość netto ogółem | Tak |
| `tax` | `decimal(15,2)` | Wartość podatku ogółem | Tak |
| `total` | `decimal(15,2)` | Kwota razem dokumentu sprzedaży bez uwzględnienia korekt | Tak |
| `total_composed` | `decimal(15,2)` | Kwota razem faktury z uwzględnieniem korekt | Tak |

##### Schematy księgowe

| Pole | Typ | Opis | Wartości |
|------|-----|------|----------|
| `schema` | `string` | Schemat księgowy | `normal`, `vat_invoice_date`, `vat_buyer_construction_service`, `assessor`, `split_payment` |
| `schema_bill` | `boolean` | Opcja faktura do paragonu | `0`, `1` |
| `schema_cancelled` | `boolean` | Opcja faktura anulowana | `0`, `1` |
| `schema_receipt_book` | `boolean` | Czy paragon ma być księgowany | `0`, `1` |
| `register_description` | `string` | Domyślny opis księgowania do ewidencji | - |

##### Korekty

| Pole | Typ | Opis | Tylko odczyt |
|------|-----|------|-------------|
| `correction_type` | `string` | Pole wykorzystywane wewnętrznie przy fakturach korygujących | Tak |
| `corrections` | `integer` | Liczba korekt | Tak |

##### Szablony i wydruki

| Pole | Typ | Opis |
|------|-----|------|
| `template` | `string` | Identyfikator szablonu wydruku dokumentu sprzedaży |
| `auto_send` | `boolean` | Automatyczna wysyłka faktury na adres e-mail kontrahenta |
| `header` | `string` | Dodatkowe informacje w nagłówku faktury | Tylko odczyt |
| `footer` | `string` | Dodatkowe informacje w stopce faktury | Tylko odczyt |
| `user_name` | `string` | Imię i nazwisko osoby upoważnionej do wystawienia faktury | Tylko odczyt |

##### Dodatkowe informacje

| Pole | Typ | Opis |
|------|-----|------|
| `description` | `text` | Uwagi |
| `id_external` | `string` | Pole do zapisywania własnych wartości |
| `tags` | `string` | Znaczniki powiązane z fakturą w formacie `(ID ZNACZNIKA X),(ID ZNACZNIKA Y)...` |
| `price_type` | `string` | Rodzaj ceny - `netto` lub `brutto` |

##### Informacje o paragonach fiskalnych

| Pole | Typ | Opis | Tylko odczyt |
|------|-----|------|-------------|
| `receipt_fiscal_printed` | `boolean` | Czy paragon został wydrukowany | Tak |

##### Informacje o innych przychodach

| Pole | Typ | Opis |
|------|-----|------|
| `income_lumpcode` | `string` | Stawka ryczałtu w przypadku prowadzenia Ewidencji przychodów |
| `income_correction` | `string` | Stawka ryczałtu w przypadku prowadzenia Ewidencji przychodów |

##### Informacje systemowe

| Pole | Typ | Opis | Tylko odczyt |
|------|-----|------|-------------|
| `warehouse_type` | `string` | Czy był włączony moduł magazynowy (`extended`) czy katalog produktów (`simple`) | Tak |
| `notes` | `integer` | Liczba notatek powiązanych z dokumentem sprzedaży | Tak |
| `documents` | `integer` | Liczba dokumentów powiązanych z dokumentem sprzedaży | Tak |
| `period` | `string` | Okres w którym dokument jest widoczny na liście | Tak |
| `signed` | `boolean` | Oznaczenie faktur podpisanych elektronicznie | Tak |
| `hash` | `string` | Hash zabezpieczający odsyłacz do faktury w panelu klienta | Tak |
| `metadata` | `json` | Dodatkowe dane w formacie JSON | - |
| `synced_at` | `datetime` | Data synchronizacji z wFirma | - |

#### Relacje

```php
// Relacja do użytkownika
public function user(): BelongsTo

// Relacja do pozycji faktury
public function invoiceContents(): HasMany
```

#### Scope'y

```php
// Faktury zapłacone
WFirmaInvoice::paid()->get();

// Faktury niezapłacone
WFirmaInvoice::unpaid()->get();

// Faktury określonego typu
WFirmaInvoice::byType('normal')->get();

// Faktury z zakresu dat
WFirmaInvoice::byDateRange('2025-01-01', '2025-12-31')->get();
```

---

### WFirmaInvoiceContent - Zawartość faktur

**Klasa**: `App\Models\WFirmaInvoiceContent`  
**Tabela**: `wfirma_invoice_contents`  
**Moduł wFirma**: `invoices > invoicecontents`  
**Dokumentacja API**: https://doc.wfirma.pl/#moduly

#### Opis

Model reprezentujący pozycje (zawartość) faktur sprzedażowych. Każda faktura może mieć wiele pozycji.

#### Pola

##### Identyfikatory

| Pole | Typ | Opis |
|------|-----|------|
| `id` | `bigint` | Klucz główny (auto) |
| `wfirma_id` | `string` | ID z wFirma API |
| `invoice_id` | `bigint` | ID faktury | Foreign key do `wfirma_invoices.id` |

##### Informacje o pozycji

| Pole | Typ | Opis |
|------|-----|------|
| `name` | `string` | Nazwa pozycji |
| `classification` | `string` | Kod PKWiU |
| `unit` | `string` | Jednostka słownie, np. "szt." |
| `unit_id` | `string` | ID jednostki |
| `count` | `decimal(15,4)` | Ilość |
| `unit_count` | `decimal(15,4)` | Ilość jednostkowa |

##### Ceny i rabaty

| Pole | Typ | Opis |
|------|-----|------|
| `price` | `decimal(15,2)` | Kwota produktu - w zależności od `price_type` będzie to cena netto lub brutto |
| `price_modified` | `boolean` | Czy cena została zmodyfikowana |
| `discount` | `decimal(15,2)` | Rabat |
| `discount_percent` | `decimal(15,2)` | Rabat w procentach |

##### VAT

| Pole | Typ | Opis |
|------|-----|------|
| `vat` | `decimal(15,2)` | Stawka VAT |
| `vat_code_id` | `string` | ID stawki VAT |

##### Kwoty

| Pole | Typ | Opis |
|------|-----|------|
| `netto` | `decimal(15,2)` | Wartość netto |
| `brutto` | `decimal(15,2)` | Wartość brutto |
| `lumpcode` | `string` | Kod ryczałtu |

##### Powiązania

| Pole | Typ | Opis |
|------|-----|------|
| `good_id` | `string` | ID produktu z wFirma |
| `tangiblefixedasset_id` | `string` | ID środka trwałego |
| `equipment_id` | `string` | ID wyposażenia |
| `vehicle_id` | `string` | ID pojazdu |

##### Dodatkowe

| Pole | Typ | Opis |
|------|-----|------|
| `metadata` | `json` | Dodatkowe dane w formacie JSON |

#### Relacje

```php
// Relacja do faktury
public function invoice(): BelongsTo
```

---

### WFirmaExpense - Faktury wydatkowe

**Klasa**: `App\Models\WFirmaExpense`  
**Tabela**: `wfirma_expenses`  
**Moduł wFirma**: `expenses`  
**Dokumentacja API**: https://doc.wfirma.pl/#moduly

#### Opis

Model reprezentujący faktury wydatkowe (koszty) z systemu wFirma.

#### Typy dokumentów

- `invoice` - Faktura
- `bill` - Rachunek
- `vat_exempt` - Zwolnione z VAT

#### Pola

##### Identyfikatory

| Pole | Typ | Opis |
|------|-----|------|
| `id` | `bigint` | Klucz główny (auto) |
| `wfirma_id` | `string` | ID z wFirma API |
| `user_id` | `bigint` | ID użytkownika | Foreign key do `users.id` |
| `contractor_id` | `string` | ID kontrahenta z wFirma |

##### Typ i numeracja

| Pole | Typ | Opis | Wartości |
|------|-----|------|----------|
| `type` | `string` | Typ dokumentu | `invoice`, `bill`, `vat_exempt` |
| `fullnumber` | `string` | Pełny numer dokumentu | - |
| `number` | `string` | Numer dokumentu | - |

##### Daty

| Pole | Typ | Opis | Format |
|------|-----|------|--------|
| `date` | `date` | Data wystawienia wydatku | `YYYY-MM-DD` |
| `taxregister_date` | `date` | Data księgowania do KPIR | `YYYY-MM-DD` |
| `payment_date` | `date` | Termin płatności | `YYYY-MM-DD` |

##### Płatności

| Pole | Typ | Opis | Wartości |
|------|-----|------|----------|
| `payment_method` | `string` | Metoda płatności | `cash`, `transfer`, `compensation`, `cod`, `payment_card` |
| `paid` | `boolean` | Czy zapłacono całość | `0`, `1` |
| `alreadypaid_initial` | `decimal(15,2)` | Kwota do podania, jeśli "paid" wynosi 1 | - |

##### Waluta i księgowość

| Pole | Typ | Opis | Wartości |
|------|-----|------|----------|
| `currency` | `string` | Waluta np. PLN | - |
| `accounting_effect` | `string` | Skutek księgowy | `kpir_and_vat`, `kpir`, `vat`, `nothing` |
| `warehouse_type` | `string` | Typ magazynu | `simple`, `extended` |
| `tax_evaluation_method` | `string` | Sposób przeliczania ceny | `netto`, `brutto` |

##### Opcje VAT

| Pole | Typ | Opis |
|------|-----|------|
| `schema_vat_cashbox` | `boolean` | Metoda kasowa |
| `wnt` | `boolean` | WNT |
| `service_import` | `boolean` | Import usług |
| `service_import2` | `boolean` | Import usług art.28b |
| `cargo_import` | `boolean` | Import towarów art. 33a |
| `split_payment` | `boolean` | Podzielona płatność |

##### Status

| Pole | Typ | Opis |
|------|-----|------|
| `draft` | `boolean` | Draft wydatku |

##### Kwoty

| Pole | Typ | Opis |
|------|-----|------|
| `netto` | `decimal(15,2)` | Wartość netto |
| `brutto` | `decimal(15,2)` | Wartość brutto |
| `vat_content_netto` | `decimal(15,2)` | Suma netto z VAT |
| `vat_content_tax` | `decimal(15,2)` | Suma podatku VAT |
| `vat_content_brutto` | `decimal(15,2)` | Suma brutto z VAT |
| `total` | `decimal(15,2)` | Suma całkowita |
| `remaining` | `decimal(15,2)` | Pozostało do zapłaty |

##### Dodatkowe

| Pole | Typ | Opis |
|------|-----|------|
| `description` | `text` | Opis |
| `metadata` | `json` | Dodatkowe dane w formacie JSON |
| `synced_at` | `datetime` | Data synchronizacji z wFirma |

#### Relacje

```php
// Relacja do użytkownika
public function user(): BelongsTo

// Relacja do części wydatku
public function expenseParts(): HasMany
```

#### Scope'y

```php
// Wydatki zapłacone
WFirmaExpense::paid()->get();

// Wydatki niezapłacone
WFirmaExpense::unpaid()->get();

// Drafty wydatków
WFirmaExpense::draft()->get();
```

---

### WFirmaExpensePart - Części wydatków

**Klasa**: `App\Models\WFirmaExpensePart`  
**Tabela**: `wfirma_expense_parts`  
**Moduł wFirma**: `expenses > expense_parts`  
**Dokumentacja API**: https://doc.wfirma.pl/#moduly

#### Opis

Model reprezentujący pozycje (części) faktur wydatkowych. Każdy wydatek może mieć wiele pozycji.

#### Pola

##### Identyfikatory

| Pole | Typ | Opis |
|------|-----|------|
| `id` | `bigint` | Klucz główny (auto) |
| `wfirma_id` | `string` | ID z wFirma API |
| `expense_id` | `bigint` | ID wydatku | Foreign key do `wfirma_expenses.id` |

##### Typ i schemat

| Pole | Typ | Opis | Wartości |
|------|-----|------|----------|
| `expense_part_type` | `string` | Typ części wydatku | `rates`, `positions` |
| `schema` | `string` | Typ dokumentu | `cost`, `purchase_trade_goods`, `vehicle_fuel`, `vehicle_expense` |
| `good_action` | `string` | Akcja produktu | `new` - wysyłamy, gdy chcemy utworzyć nowy produkt |

##### Informacje o pozycji

| Pole | Typ | Opis |
|------|-----|------|
| `name` | `string` | Nazwa pozycji |
| `classification` | `string` | Kod PKWiU |
| `unit` | `string` | Jednostka słownie, np. "szt." |
| `unit_id` | `string` | ID jednostki |
| `count` | `decimal(15,4)` | Ilość - niewysłanie tego parametru wstawi produkt o ilości 1 |

##### Ceny i VAT

| Pole | Typ | Opis |
|------|-----|------|
| `price` | `decimal(15,2)` | Kwota produktu - w zależności od `tax_evaluation_method` będzie to cena netto lub brutto |
| `vat_code_id` | `string` | ID stawki VAT zawarte w gałęzi ID |

##### Kwoty

| Pole | Typ | Opis |
|------|-----|------|
| `netto` | `decimal(15,2)` | Wartość netto |
| `brutto` | `decimal(15,2)` | Wartość brutto |
| `vat` | `decimal(15,2)` | Stawka VAT |

##### Rabaty

| Pole | Typ | Opis |
|------|-----|------|
| `discount` | `decimal(15,2)` | Rabat |
| `discount_percent` | `decimal(15,2)` | Rabat w procentach |

##### Powiązania

| Pole | Typ | Opis |
|------|-----|------|
| `good_id` | `string` | ID produktu z wFirma |

##### Dodatkowe

| Pole | Typ | Opis |
|------|-----|------|
| `metadata` | `json` | Dodatkowe dane w formacie JSON |

#### Relacje

```php
// Relacja do wydatku
public function expense(): BelongsTo
```

---

### WFirmaIncome - Przychody

**Klasa**: `App\Models\WFirmaIncome`  
**Tabela**: `wfirma_incomes`  
**Moduł wFirma**: `incomes`  
**Dokumentacja API**: https://doc.wfirma.pl/#moduly

#### Opis

Model reprezentujący przychody z systemu wFirma.

#### Pola

##### Identyfikatory

| Pole | Typ | Opis |
|------|-----|------|
| `id` | `bigint` | Klucz główny (auto) |
| `wfirma_id` | `string` | ID z wFirma API |
| `user_id` | `bigint` | ID użytkownika | Foreign key do `users.id` |
| `contractor_id` | `string` | ID kontrahenta z wFirma |

##### Typ i numeracja

| Pole | Typ | Opis |
|------|-----|------|
| `type` | `string` | Typ przychodu |
| `fullnumber` | `string` | Pełny numer dokumentu |
| `number` | `string` | Numer dokumentu |

##### Daty

| Pole | Typ | Opis | Format |
|------|-----|------|--------|
| `date` | `date` | Data wystawienia | `YYYY-MM-DD` |
| `taxregister_date` | `date` | Data księgowania do KPIR | `YYYY-MM-DD` |
| `payment_date` | `date` | Termin płatności | `YYYY-MM-DD` |

##### Płatności

| Pole | Typ | Opis | Wartości |
|------|-----|------|----------|
| `payment_method` | `string` | Metoda płatności | `cash`, `transfer`, `compensation`, `cod`, `payment_card` |
| `paid` | `boolean` | Czy zapłacono całość | `0`, `1` |
| `alreadypaid_initial` | `decimal(15,2)` | Kwota do podania, jeśli "paid" wynosi 1 | - |

##### Waluta i księgowość

| Pole | Typ | Opis | Wartości |
|------|-----|------|----------|
| `currency` | `string` | Waluta np. PLN | - |
| `accounting_effect` | `string` | Skutek księgowy | `kpir_and_vat`, `kpir`, `vat`, `nothing` |
| `warehouse_type` | `string` | Typ magazynu | `simple`, `extended` |
| `tax_evaluation_method` | `string` | Sposób przeliczania ceny | `netto`, `brutto` |

##### Opcje VAT

| Pole | Typ | Opis |
|------|-----|------|
| `schema_vat_cashbox` | `boolean` | Metoda kasowa |
| `wnt` | `boolean` | WNT |
| `service_import` | `boolean` | Import usług |
| `service_import2` | `boolean` | Import usług art.28b |
| `cargo_import` | `boolean` | Import towarów art. 33a |
| `split_payment` | `boolean` | Podzielona płatność |

##### Status

| Pole | Typ | Opis |
|------|-----|------|
| `draft` | `boolean` | Draft przychodu |

##### Kwoty

| Pole | Typ | Opis |
|------|-----|------|
| `netto` | `decimal(15,2)` | Wartość netto |
| `brutto` | `decimal(15,2)` | Wartość brutto |
| `vat_content_netto` | `decimal(15,2)` | Suma netto z VAT |
| `vat_content_tax` | `decimal(15,2)` | Suma podatku VAT |
| `vat_content_brutto` | `decimal(15,2)` | Suma brutto z VAT |
| `total` | `decimal(15,2)` | Suma całkowita |
| `remaining` | `decimal(15,2)` | Pozostało do zapłaty |

##### Dodatkowe

| Pole | Typ | Opis |
|------|-----|------|
| `description` | `text` | Opis |
| `metadata` | `json` | Dodatkowe dane w formacie JSON |
| `synced_at` | `datetime` | Data synchronizacji z wFirma |

#### Relacje

```php
// Relacja do użytkownika
public function user(): BelongsTo
```

#### Scope'y

```php
// Przychody zapłacone
WFirmaIncome::paid()->get();

// Przychody niezapłacone
WFirmaIncome::unpaid()->get();

// Drafty przychodów
WFirmaIncome::draft()->get();
```

---

### WFirmaPayment - Płatności

**Klasa**: `App\Models\WFirmaPayment`  
**Tabela**: `wfirma_payments`  
**Moduł wFirma**: `payments`  
**Dokumentacja API**: https://doc.wfirma.pl/#moduly

#### Opis

Model reprezentujący płatności z systemu wFirma. Płatności mogą być powiązane z fakturami, wydatkami, przychodami, kontrahentami.

#### Pola

##### Identyfikatory

| Pole | Typ | Opis |
|------|-----|------|
| `id` | `bigint` | Klucz główny (auto) |
| `wfirma_id` | `string` | ID z wFirma API |
| `user_id` | `bigint` | ID użytkownika | Foreign key do `users.id` |
| `invoice_id` | `string` | ID faktury powiązanej (jeśli dotyczy) |
| `expense_id` | `string` | ID wydatku powiązanego (jeśli dotyczy) |
| `income_id` | `string` | ID przychodu powiązanego (jeśli dotyczy) |
| `contractor_id` | `string` | ID kontrahenta z wFirma |
| `bank_account_id` | `string` | ID konta bankowego z wFirma |
| `payment_cashbox_id` | `string` | ID kasy z wFirma |

##### Informacje o płatności

| Pole | Typ | Opis | Format |
|------|-----|------|--------|
| `date` | `date` | Data płatności | `YYYY-MM-DD` |
| `amount` | `decimal(15,2)` | Kwota płatności | - |
| `currency` | `string` | Waluta | - |
| `payment_method` | `string` | Metoda płatności | `cash`, `transfer`, `compensation`, `cod`, `payment_card` |
| `status` | `string` | Status płatności | - |
| `description` | `text` | Opis płatności | - |

##### Dodatkowe

| Pole | Typ | Opis |
|------|-----|------|
| `metadata` | `json` | Dodatkowe dane w formacie JSON |
| `synced_at` | `datetime` | Data synchronizacji z wFirma |

#### Relacje

```php
// Relacja do użytkownika
public function user(): BelongsTo
```

#### Scope'y

```php
// Płatności z zakresu dat
WFirmaPayment::byDateRange('2025-01-01', '2025-12-31')->get();
```

---

### WFirmaTerm - Terminarz

**Klasa**: `App\Models\WFirmaTerm`  
**Tabela**: `wfirma_terms`  
**Moduł wFirma**: `terms`  
**Dokumentacja API**: https://doc.wfirma.pl/#moduly

#### Opis

Model reprezentujący terminy (terminarz) z systemu wFirma. Terminy mogą być powiązane z fakturami, wydatkami, przychodami, kontrahentami.

#### Pola

##### Identyfikatory

| Pole | Typ | Opis |
|------|-----|------|
| `id` | `bigint` | Klucz główny (auto) |
| `wfirma_id` | `string` | ID z wFirma API |
| `user_id` | `bigint` | ID użytkownika | Foreign key do `users.id` |
| `term_group_id` | `string` | ID grupy terminów z wFirma |
| `contractor_id` | `string` | ID kontrahenta powiązanego (jeśli dotyczy) |
| `invoice_id` | `string` | ID faktury powiązanej (jeśli dotyczy) |
| `expense_id` | `string` | ID wydatku powiązanego (jeśli dotyczy) |
| `income_id` | `string` | ID przychodu powiązanego (jeśli dotyczy) |

##### Informacje o terminie

| Pole | Typ | Opis | Format |
|------|-----|------|--------|
| `date` | `date` | Data terminu | `YYYY-MM-DD` |
| `time` | `string` | Godzina terminu (opcjonalna) | `HH:MM` |
| `title` | `string` | Tytuł terminu | - |
| `description` | `text` | Opis terminu | - |
| `status` | `string` | Status terminu | - |

##### Przypomnienia

| Pole | Typ | Opis |
|------|-----|------|
| `reminder` | `boolean` | Czy przypomnienie włączone |
| `reminder_minutes` | `integer` | Liczba minut przed terminem na przypomnienie |

##### Dodatkowe

| Pole | Typ | Opis |
|------|-----|------|
| `metadata` | `json` | Dodatkowe dane w formacie JSON |
| `synced_at` | `datetime` | Data synchronizacji z wFirma |

#### Relacje

```php
// Relacja do użytkownika
public function user(): BelongsTo
```

#### Scope'y

```php
// Nadchodzące terminy
WFirmaTerm::upcoming()->get();

// Minione terminy
WFirmaTerm::past()->get();

// Terminy z zakresu dat
WFirmaTerm::byDateRange('2025-01-01', '2025-12-31')->get();
```

---

### WFirmaInterest - Rozliczenia ZUS

**Klasa**: `App\Models\WFirmaInterest`  
**Tabela**: `wfirma_interests`  
**Moduł wFirma**: `interests`  
**Dokumentacja API**: https://doc.wfirma.pl/#moduly

#### Opis

Model reprezentujący rozliczenia ZUS z systemu wFirma.

**Uwaga**: wFirma API nie posiada dedykowanego modułu dla ZUS. Rozliczenia ZUS mogą być dostępne w module `interests` lub wymagać dodatkowych uprawnień lub integracji z e-ZUS/PUE.

#### Pola

##### Identyfikatory

| Pole | Typ | Opis |
|------|-----|------|
| `id` | `bigint` | Klucz główny (auto) |
| `wfirma_id` | `string` | ID z wFirma API |
| `user_id` | `bigint` | ID użytkownika | Foreign key do `users.id` |
| `employee_id` | `string` | ID pracownika (jeśli dotyczy) |

##### Informacje o rozliczeniu

| Pole | Typ | Opis |
|------|-----|------|
| `type` | `string` | Typ rozliczenia ZUS |
| `period` | `string` | Okres rozliczeniowy (np. `2025-12`) |
| `zus_type` | `string` | Typ ZUS | `sp`, `zp`, `fp`, `fgsp`, `fgzp`, `fgfp` (składki pracownicze/pracodawcy) |
| `declaration_number` | `string` | Numer deklaracji |

##### Daty

| Pole | Typ | Opis | Format |
|------|-----|------|--------|
| `date` | `date` | Data rozliczenia | `YYYY-MM-DD` |
| `due_date` | `date` | Termin płatności | `YYYY-MM-DD` |
| `payment_date` | `date` | Data płatności | `YYYY-MM-DD` |

##### Kwoty i status

| Pole | Typ | Opis |
|------|-----|------|
| `amount` | `decimal(15,2)` | Kwota rozliczenia |
| `currency` | `string` | Waluta |
| `status` | `string` | Status rozliczenia |
| `paid` | `boolean` | Czy zapłacono |

##### Dodatkowe

| Pole | Typ | Opis |
|------|-----|------|
| `description` | `text` | Opis rozliczenia |
| `metadata` | `json` | Dodatkowe dane w formacie JSON |
| `synced_at` | `datetime` | Data synchronizacji z wFirma |

#### Relacje

```php
// Relacja do użytkownika
public function user(): BelongsTo
```

#### Scope'y

```php
// Rozliczenia zapłacone
WFirmaInterest::paid()->get();

// Rozliczenia niezapłacone
WFirmaInterest::unpaid()->get();

// Rozliczenia dla określonego okresu
WFirmaInterest::byPeriod('2025-12')->get();

// Rozliczenia określonego typu ZUS
WFirmaInterest::byZusType('sp')->get();
```

---

## Relacje między modelami

### Diagram relacji

```
User
  ├── WFirmaInvoice (1:N)
  │     └── WFirmaInvoiceContent (1:N)
  ├── WFirmaExpense (1:N)
  │     └── WFirmaExpensePart (1:N)
  ├── WFirmaIncome (1:N)
  ├── WFirmaPayment (1:N)
  ├── WFirmaTerm (1:N)
  └── WFirmaInterest (1:N)
```

### Szczegółowe relacje

#### WFirmaInvoice
- **Należy do**: `User` (belongsTo)
- **Ma wiele**: `WFirmaInvoiceContent` (hasMany)
- **Może być powiązana z**: `WFirmaPayment`, `WFirmaTerm` (przez `invoice_id`)

#### WFirmaInvoiceContent
- **Należy do**: `WFirmaInvoice` (belongsTo)

#### WFirmaExpense
- **Należy do**: `User` (belongsTo)
- **Ma wiele**: `WFirmaExpensePart` (hasMany)
- **Może być powiązana z**: `WFirmaPayment`, `WFirmaTerm` (przez `expense_id`)

#### WFirmaExpensePart
- **Należy do**: `WFirmaExpense` (belongsTo)

#### WFirmaIncome
- **Należy do**: `User` (belongsTo)
- **Może być powiązana z**: `WFirmaPayment`, `WFirmaTerm` (przez `income_id`)

#### WFirmaPayment
- **Należy do**: `User` (belongsTo)
- **Może być powiązana z**: `WFirmaInvoice`, `WFirmaExpense`, `WFirmaIncome` (opcjonalne relacje przez `invoice_id`, `expense_id`, `income_id`)

#### WFirmaTerm
- **Należy do**: `User` (belongsTo)
- **Może być powiązana z**: `WFirmaInvoice`, `WFirmaExpense`, `WFirmaIncome` (opcjonalne relacje przez `invoice_id`, `expense_id`, `income_id`)

#### WFirmaInterest
- **Należy do**: `User` (belongsTo)

---

## Scope'y i metody pomocnicze

### WFirmaInvoice

```php
// Faktury zapłacone
WFirmaInvoice::paid()->get();

// Faktury niezapłacone
WFirmaInvoice::unpaid()->get();

// Faktury określonego typu
WFirmaInvoice::byType('normal')->get();
WFirmaInvoice::byType('proforma')->get();

// Faktury z zakresu dat
WFirmaInvoice::byDateRange('2025-01-01', '2025-12-31')->get();
```

### WFirmaExpense

```php
// Wydatki zapłacone
WFirmaExpense::paid()->get();

// Wydatki niezapłacone
WFirmaExpense::unpaid()->get();

// Drafty wydatków
WFirmaExpense::draft()->get();
```

### WFirmaIncome

```php
// Przychody zapłacone
WFirmaIncome::paid()->get();

// Przychody niezapłacone
WFirmaIncome::unpaid()->get();

// Drafty przychodów
WFirmaIncome::draft()->get();
```

### WFirmaPayment

```php
// Płatności z zakresu dat
WFirmaPayment::byDateRange('2025-01-01', '2025-12-31')->get();
```

### WFirmaTerm

```php
// Nadchodzące terminy
WFirmaTerm::upcoming()->get();

// Minione terminy
WFirmaTerm::past()->get();

// Terminy z zakresu dat
WFirmaTerm::byDateRange('2025-01-01', '2025-12-31')->get();
```

### WFirmaInterest

```php
// Rozliczenia zapłacone
WFirmaInterest::paid()->get();

// Rozliczenia niezapłacone
WFirmaInterest::unpaid()->get();

// Rozliczenia dla określonego okresu
WFirmaInterest::byPeriod('2025-12')->get();

// Rozliczenia określonego typu ZUS
WFirmaInterest::byZusType('sp')->get();  // Składka pracownicza
WFirmaInterest::byZusType('zp')->get();  // Składka pracodawcy
```

---

## Przykłady użycia

### Pobieranie faktur sprzedażowych

```php
use App\Models\WFirmaInvoice;

// Wszystkie faktury zapłacone
$paidInvoices = WFirmaInvoice::paid()->get();

// Faktury VAT z grudnia 2025
$invoices = WFirmaInvoice::byType('normal')
    ->byDateRange('2025-12-01', '2025-12-31')
    ->get();

// Faktura z pozycjami
$invoice = WFirmaInvoice::with('invoiceContents')
    ->find(1);

foreach ($invoice->invoiceContents as $content) {
    echo $content->name . ': ' . $content->brutto . ' ' . $invoice->currency;
}
```

### Pobieranie wydatków

```php
use App\Models\WFirmaExpense;

// Wszystkie niezapłacone wydatki
$unpaidExpenses = WFirmaExpense::unpaid()->get();

// Wydatek z pozycjami
$expense = WFirmaExpense::with('expenseParts')
    ->find(1);

foreach ($expense->expenseParts as $part) {
    echo $part->name . ': ' . $part->netto;
}
```

### Pobieranie przychodów

```php
use App\Models\WFirmaIncome;

// Wszystkie przychody zapłacone
$paidIncomes = WFirmaIncome::paid()->get();

// Przychody z określonego okresu
$incomes = WFirmaIncome::whereBetween('date', ['2025-01-01', '2025-12-31'])
    ->get();
```

### Pobieranie płatności

```php
use App\Models\WFirmaPayment;

// Płatności z grudnia 2025
$payments = WFirmaPayment::byDateRange('2025-12-01', '2025-12-31')
    ->get();

// Płatności powiązane z fakturą
$invoicePayments = WFirmaPayment::whereNotNull('invoice_id')
    ->get();
```

### Pobieranie terminów

```php
use App\Models\WFirmaTerm;

// Nadchodzące terminy
$upcoming = WFirmaTerm::upcoming()->get();

// Minione terminy
$past = WFirmaTerm::past()->get();

// Terminy z przypomnieniami
$withReminders = WFirmaTerm::where('reminder', true)
    ->get();
```

### Pobieranie rozliczeń ZUS

```php
use App\Models\WFirmaInterest;

// Rozliczenia ZUS za grudzień 2025
$zus = WFirmaInterest::byPeriod('2025-12')->get();

// Składki pracownicze
$employeeContributions = WFirmaInterest::byZusType('sp')->get();

// Składki pracodawcy
$employerContributions = WFirmaInterest::byZusType('zp')->get();
```

### Tworzenie nowych rekordów

```php
use App\Models\WFirmaInvoice;
use App\Models\WFirmaInvoiceContent;

// Tworzenie faktury
$invoice = WFirmaInvoice::create([
    'wfirma_id' => '12345',
    'user_id' => auth()->id(),
    'type' => 'normal',
    'date' => '2025-12-15',
    'paymentdate' => '2026-01-15',
    'paymentmethod' => 'transfer',
    'currency' => 'PLN',
    'contractor_id' => '67890',
]);

// Dodawanie pozycji do faktury
$content = WFirmaInvoiceContent::create([
    'wfirma_id' => '54321',
    'invoice_id' => $invoice->id,
    'name' => 'Usługa programistyczna',
    'unit' => 'godz.',
    'count' => 10.00,
    'price' => 150.00,
    'vat' => 23.00,
    'netto' => 1500.00,
    'brutto' => 1845.00,
]);
```

### Aktualizacja rekordów

```php
// Aktualizacja faktury
$invoice = WFirmaInvoice::where('wfirma_id', '12345')->first();
$invoice->update([
    'paymentstate' => 'paid',
    'alreadypaid_initial' => $invoice->total,
]);
```

### Usuwanie rekordów

```php
// Usuwanie faktury (cascade usunie również pozycje)
$invoice = WFirmaInvoice::find(1);
$invoice->delete();
```

---

## Uwagi techniczne

### Synchronizacja danych

Wszystkie modele mają pole `synced_at`, które powinno być aktualizowane przy każdej synchronizacji z wFirma API. Pozwala to na śledzenie, kiedy dane były ostatnio zsynchronizowane.

### Pole `wfirma_id`

Pole `wfirma_id` przechowuje identyfikator rekordu z systemu wFirma. Powinno być unikalne dla każdego użytkownika i może być używane do identyfikacji rekordów podczas synchronizacji.

### Pole `metadata`

Pole `metadata` jest typu JSON i może przechowywać dodatkowe dane z API wFirma, które nie zostały zmapowane na konkretne pola modelu. Pozwala to na elastyczne przechowywanie danych bez konieczności modyfikacji struktury tabeli.

### Relacje opcjonalne

Niektóre modele (`WFirmaPayment`, `WFirmaTerm`) mają opcjonalne relacje do innych modeli (faktury, wydatki, przychody) przez pola `invoice_id`, `expense_id`, `income_id`. Te pola mogą być `null`, jeśli płatność lub termin nie są powiązane z konkretnym dokumentem.

### Typy danych

- **Daty**: Wszystkie pola datowe są typu `date` i przechowują daty w formacie `YYYY-MM-DD`
- **Kwoty**: Wszystkie pola kwotowe są typu `decimal(15,2)` z dokładnością do 2 miejsc po przecinku
- **Boolean**: Pola booleanowe przechowują wartości `0` (false) lub `1` (true)
- **JSON**: Pole `metadata` jest typu JSON i może przechowywać dowolne dane strukturalne

---

## Wersja dokumentacji

**Data utworzenia**: 2026-01-04  
**Ostatnia aktualizacja**: 2026-01-04  
**Wersja**: 1.0

---

## Linki

- [Dokumentacja wFirma API](https://doc.wfirma.pl/#moduly)
- [Dokumentacja konfiguracji wFirma](./wfirma-setup.md)

