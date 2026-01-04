Model: Auto (Agent Router)
Czas: 20260104_202557

================================================================================
PLAN ROZLICZENIA JDG "OCTADECIMAL" - ROK 2025
Przygotowany przez: Wilson (Główny Księgowy)
Data: 2026-01-04
================================================================================

🟢 SYTUACJA OBECNA

Rok podatkowy 2025: RYCZAŁT 12.5%

DANE W SYSTEMIE:
- Faktury sprzedażowe (WFirmaInvoice): 12 faktur
  * Przychód netto: 222 544,60 PLN
  * VAT należny: 51 185,25 PLN
  * Przychód brutto: 273 729,85 PLN
  
- Wydatki (WFirmaExpense): 25 faktur
  * Koszty netto: 10 451,92 PLN
  * VAT naliczony: 0 PLN ⚠️ PROBLEM!
  * Koszty brutto: 0 PLN ⚠️ PROBLEM!
  * Wszystkie bez taxregister_date ⚠️
  * Wszystkie bez accounting_effect ⚠️

- Faktury w modelu Invoice (NIE w wFirma): 58 faktur
  * Cursor: 27 faktur (~995 PLN)
  * OpenAI: 11 faktur (~271 PLN)
  * Anthropic: 4 faktury (~98 PLN)
  * Google: 1 faktura (~151 PLN)
  * OVH: 15 faktur (~1 265 PLN)
  * RAZEM: ~1 780 PLN (w różnych walutach)

- Ostatnia faktura sprzedażowa: FV 12/2025 z 2025-12-17 (10 120 PLN netto)
- Ostatnia faktura kosztowa w wFirma: z października 2025
- Brak faktur kosztowych za listopad i grudzień 2025 w wFirma

--------------------------------------------------------------------------------

❓ PYTANIA DO WERYFIKACJI

1. CZY FAKTURY Z MODELU Invoice SĄ JUŻ W wFirma?
   - Muszę sprawdzić, czy faktury od Cursor, OpenAI, Anthropic, Google, OVH
     są już wprowadzone w wFirma jako wydatki
   - Jeśli NIE - trzeba je dodać do wFirma

2. JAKIE SĄ KURSY WALUT DLA FAKTUR ZAGRANICZNYCH?
   - Faktury od Cursor, OpenAI, Anthropic są w USD
   - Faktury od OVH są w EUR
   - Czy kursy są już w systemie ExchangeRate?
   - Jaki kurs użyć: kurs z daty faktury czy kurs z daty zapłaty?

3. CZY FAKTURY OD CURSOR, OPENAI, ANTHROPIC, GOOGLE, OVH TO WYDATKI FIRMOWE?
   - Czy wszystkie te faktury można rozliczyć jako koszty uzyskania przychodu?
   - Czy są jakieś faktury, które NIE powinny być kosztami?

4. JAKI JEST MOMENT UJĘCIA KOSZTÓW W 2025?
   - Czy rozliczasz się metodą kasową (od 2025 możliwa)?
   - Czy koszty ujmujesz w momencie otrzymania faktury czy zapłaty?

5. CZY FAKTURA FV 12/2025 (12k) TO OSTATNIA FAKTURA ZA 2025?
   - Data: 2025-12-17
   - Czy są jeszcze jakieś faktury sprzedażowe za grudzień 2025?

6. CZY WSZYSTKIE FAKTURY KOSZTOWE Z 2025 SĄ JUŻ W wFirma?
   - W WFirmaExpense widzę tylko 25 faktur
   - Czy są jeszcze jakieś faktury kosztowe, które nie są w systemie?

7. JAKI JEST STATUS PŁATNOŚCI FAKTUR?
   - Czy wszystkie faktury kosztowe są już opłacone?
   - Kiedy zostały opłacone (ważne dla metody kasowej)?

8. CZY ROZLICZYŁEŚ JUŻ LISTOPAD 2025?
   - Faktura sprzedażowa FV 11/2025: 23 000 PLN netto (30.11.2025)
   - Czy koszty za listopad są już ujęte?

9. CZY ROZLICZYŁEŚ JUŻ GRUDZIEŃ 2025?
   - Faktura sprzedażowa FV 12/2025: 10 120 PLN netto (17.12.2025)
   - Czy koszty za grudzień są już ujęte?

10. JAKI JEST STATUS SKŁADEK ZUS ZA 2025?
    - Czy wszystkie składki zostały opłacone?
    - Czy korzystałeś z wakacji składkowych?

--------------------------------------------------------------------------------

📋 PLAN DZIAŁANIA - ETAPY

ETAP 1: ANALIZA I WERYFIKACJA DANYCH
─────────────────────────────────────
Cel: Zrozumieć pełny obraz sytuacji

Zadania:
1. ✅ Sprawdzenie faktur sprzedażowych 2025 (WFirmaInvoice)
2. ⏳ Sprawdzenie faktur kosztowych 2025 (WFirmaExpense)
3. ⏳ Sprawdzenie faktur w modelu Invoice (potencjalne wydatki firmowe)
4. ⏳ Porównanie: które faktury z Invoice są już w wFirma?
5. ⏳ Sprawdzenie kursów walut dla faktur zagranicznych
6. ⏳ Weryfikacja statusu płatności faktur
7. ⏳ Sprawdzenie składki ZUS za 2025

Pytania do odpowiedzi:
- Czy faktury z Invoice są już w wFirma?
- Jakie kursy walut użyć?
- Które faktury to koszty firmowe?

ETAP 2: SYNCHRONIZACJA Z wFirma
────────────────────────────────
Cel: Upewnić się, że wszystkie dane są zsynchronizowane

Zadania:
1. ⏳ Sprawdzenie, czy wszystkie faktury z wFirma są w lokalnej bazie
2. ⏳ Synchronizacja brakujących faktur z wFirma do lokalnej bazy
3. ⏳ Weryfikacja, czy wszystkie dane są kompletne

Pytania do odpowiedzi:
- Czy synchronizacja z wFirma jest kompletna?
- Czy wszystkie faktury mają wszystkie wymagane pola?

ETAP 3: DODANIE BRAKUJĄCYCH FAKTUR KOSZTOWYCH DO wFirma
────────────────────────────────────────────────────────
Cel: Dodać faktury od Cursor, OpenAI, Anthropic, Google, OVH do wFirma

Zadania:
1. ⏳ Identyfikacja faktur, które NIE są w wFirma
2. ⏳ Przygotowanie danych do dodania do wFirma:
   - Konwersja walut na PLN (kurs z daty faktury lub zapłaty)
   - Określenie daty księgowania (taxregister_date)
   - Określenie skutku księgowego (accounting_effect)
   - Obliczenie VAT (jeśli dotyczy)
3. ⏳ Dodanie faktur do wFirma (ręcznie lub przez API)
4. ⏳ Synchronizacja dodanych faktur do lokalnej bazy

Pytania do odpowiedzi:
- Które faktury trzeba dodać?
- Jakie kursy walut użyć?
- Jakie daty księgowania ustawić?

ETAP 4: UZUPEŁNIENIE BRAKUJĄCYCH DANYCH W WFirmaExpense
───────────────────────────────────────────────────────
Cel: Uzupełnić taxregister_date, accounting_effect, VAT dla wszystkich wydatków

Zadania:
1. ⏳ Uzupełnienie taxregister_date dla wszystkich wydatków 2025
2. ⏳ Uzupełnienie accounting_effect dla wszystkich wydatków 2025
3. ⏳ Obliczenie i uzupełnienie VAT dla wszystkich wydatków 2025
4. ⏳ Weryfikacja poprawności danych

Pytania do odpowiedzi:
- Jakie daty księgowania ustawić?
- Jakie skutki księgowe ustawić?
- Jak obliczyć VAT?

ETAP 5: ROZLICZENIE LISTOPADA 2025
───────────────────────────────────
Cel: Upewnić się, że listopad 2025 jest prawidłowo rozliczony

Zadania:
1. ⏳ Weryfikacja faktury sprzedażowej FV 11/2025 (23 000 PLN netto)
2. ⏳ Weryfikacja kosztów za listopad 2025
3. ⏳ Sprawdzenie, czy wszystkie koszty są ujęte w KPiR
4. ⏳ Weryfikacja VAT za listopad 2025
5. ⏳ Kalkulacja PIT za listopad 2025 (ryczałt 12.5%)

Pytania do odpowiedzi:
- Czy wszystkie koszty za listopad są ujęte?
- Czy VAT jest prawidłowo rozliczony?
- Czy PIT jest prawidłowo obliczony?

ETAP 6: ROZLICZENIE GRUDNIA 2025
─────────────────────────────────
Cel: Upewnić się, że grudzień 2025 jest prawidłowo rozliczony

Zadania:
1. ⏳ Weryfikacja faktury sprzedażowej FV 12/2025 (10 120 PLN netto)
2. ⏳ Weryfikacja kosztów za grudzień 2025
3. ⏳ Sprawdzenie, czy wszystkie koszty są ujęte w KPiR
4. ⏳ Weryfikacja VAT za grudzień 2025
5. ⏳ Kalkulacja PIT za grudzień 2025 (ryczałt 12.5%)

Pytania do odpowiedzi:
- Czy wszystkie koszty za grudzień są ujęte?
- Czy VAT jest prawidłowo rozliczony?
- Czy PIT jest prawidłowo obliczony?

ETAP 7: KALKULACJA PIT ZA CAŁY 2025
───────────────────────────────────
Cel: Obliczyć podatek PIT za cały rok 2025 (ryczałt 12.5%)

Zadania:
1. ⏳ Suma przychodów za 2025 (z faktur sprzedażowych)
2. ⏳ Suma kosztów za 2025 (z faktur kosztowych)
3. ⏳ Obliczenie podstawy opodatkowania (przychód - koszty)
4. ⏳ Obliczenie podatku PIT (12.5% od podstawy)
5. ⏳ Weryfikacja zgodności z przepisami

Pytania do odpowiedzi:
- Jaka jest suma przychodów?
- Jaka jest suma kosztów?
- Jaki jest podatek PIT?

ETAP 8: KALKULACJA VAT ZA CAŁY 2025
────────────────────────────────────
Cel: Obliczyć VAT za cały rok 2025

Zadania:
1. ⏳ Suma VAT należnego za 2025 (z faktur sprzedażowych)
2. ⏳ Suma VAT naliczonego za 2025 (z faktur kosztowych)
3. ⏳ Obliczenie VAT do zapłaty (należny - naliczony)
4. ⏳ Weryfikacja zgodności z przepisami

Pytania do odpowiedzi:
- Jaki jest VAT należny?
- Jaki jest VAT naliczony?
- Jaki jest VAT do zapłaty?

ETAP 9: WERYFIKACJA ZUS ZA 2025
────────────────────────────────
Cel: Sprawdzić, czy wszystkie składki ZUS za 2025 zostały opłacone

Zadania:
1. ⏳ Sprawdzenie składki ZUS w modelu WFirmaInterest
2. ⏳ Weryfikacja, czy wszystkie składki zostały opłacone
3. ⏳ Sprawdzenie, czy korzystałeś z wakacji składkowych

Pytania do odpowiedzi:
- Czy wszystkie składki zostały opłacone?
- Czy są jakieś zaległości?

ETAP 10: PRZYGOTOWANIE RAPORTU KOŃCOWEGO
────────────────────────────────────────
Cel: Przygotować raport z analizą i rekomendacjami

Zadania:
1. ⏳ Podsumowanie wszystkich danych za 2025
2. ⏳ Identyfikacja problemów i nieprawidłowości
3. ⏳ Plan naprawczy dla wykrytych problemów
4. ⏳ Rekomendacje na przyszłość

Pytania do odpowiedzi:
- Jakie są główne problemy?
- Co trzeba naprawić?
- Jak zapobiec problemom w przyszłości?

--------------------------------------------------------------------------------

⚠️ UWAGI I OSTRZEŻENIA

1. RYCZAŁT 12.5% - WAŻNE:
   - Przy ryczałcie NIE odliczasz kosztów uzyskania przychodu
   - Podatek = 12.5% od przychodu (nie od dochodu!)
   - Koszty są ważne tylko dla VAT (odliczenie VAT naliczonego)

2. METODA KASOWA PIT (NOWOŚĆ 2025):
   - Jeśli korzystasz z metody kasowej, przychód ujmujesz w momencie zapłaty
   - Koszty ujmujesz w momencie zapłaty
   - Czy korzystasz z metody kasowej?

3. VAT:
   - VAT należny = VAT od faktur sprzedażowych
   - VAT naliczony = VAT od faktur kosztowych
   - VAT do zapłaty = VAT należny - VAT naliczony

4. KURSY WALUT:
   - Dla faktur zagranicznych trzeba użyć kursu z daty faktury lub zapłaty
   - Kursy powinny być z tabeli NBP
   - Czy kursy są już w systemie ExchangeRate?

5. DATA KSIĘGOWANIA:
   - taxregister_date określa, w którym okresie koszt jest ujęty w KPiR
   - Dla metody kasowej: data księgowania = data zapłaty
   - Dla metody memoriałowej: data księgowania = data otrzymania faktury

--------------------------------------------------------------------------------

🤝 KOMENTARZ WILSONA

Piotr, widzę, że mamy sporo pracy do zrobienia. Najważniejsze to:

1. **Najpierw odpowiedz na pytania** - to pomoże mi zrozumieć pełny obraz sytuacji
2. **Sprawdźmy, co jest w wFirma** - może niektóre faktury są już tam, tylko nie są zsynchronizowane?
3. **Dodajmy brakujące faktury** - faktury od Cursor, OpenAI, Anthropic, Google, OVH trzeba dodać do wFirma
4. **Uzupełnijmy dane** - taxregister_date, accounting_effect, VAT dla wszystkich wydatków
5. **Rozliczmy listopad i grudzień** - to są ostatnie miesiące, które trzeba rozliczyć

Pamiętaj: przy ryczałcie 12.5% koszty NIE zmniejszają podstawy opodatkowania PIT, 
ale są ważne dla VAT (odliczenie VAT naliczonego).

Zacznijmy od odpowiedzi na pytania, a potem przejdziemy przez każdy etap po kolei.

Wilson

================================================================================
KONIEC PLANU
================================================================================

