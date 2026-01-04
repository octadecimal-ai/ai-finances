# Wilson accountant document
_Exported on 4.01.2026 at 22:52:26 CET from Cursor (2.2.44)_

---

**User**

@wilson-accountant.md (1-119) 

---

**Cursor**


Pobieranie aktualnego czasu i przegląd struktury projektu, aby rozpocząć audyt jako Wilson.



Przeglądanie kluczowych modeli i serwisów, aby zrozumieć strukturę danych i logikę księgową.



Sprawdzanie szczegółów danych z 2024 r. i analiza zgodności z przepisami podatkowymi.



Sprawdzanie szczegółów dotyczących dat księgowania i momentu ujęcia przychodów/kosztów:



Przygotowywanie raportu audytu. Sprawdzanie struktury katalogów logów:



Przygotowywanie raportu audytu podatkowego za 2024 r.:


```log
Model: Auto (Agent Router)
Czas: 20260104_200800

================================================================================
AUDYT PODATKOWY JDG "OCTADECIMAL" - ROK 2024
Raport przygotowany przez: Wilson (Główny Księgowy)
Data audytu: 2026-01-04
================================================================================

🟢 PODSUMOWANIE OGÓLNE

Rok podatkowy 2024 wymaga uwagi. Podstawowe dane są obecne, ale wykryto kilka 
istotnych problemów, które mogą prowadzić do nieprawidłowego rozliczenia podatkowego.

Status ogólny: ⚠️ WYMAGA KOREKTY

Główne obszary problemowe:
1. Brak dat księgowania (taxregister_date) dla wszystkich wydatków
2. Brak określenia skutku księgowego (accounting_effect) dla wydatków
3. Potencjalny problem z momentem ujęcia przychodu (data sprzedaży vs data wystawienia)
4. Brak walidacji zgodności VAT z przepisami

Ryzyko kontroli skarbowej: ŚREDNIE

--------------------------------------------------------------------------------

📊 ANALIZA DANYCH 2024

FAKTURY SPRZEDAŻOWE (WFirmaInvoice):
- Liczba faktur: 12
- Przychód netto: 217 750,18 PLN
- VAT należny: 50 082,53 PLN
- Przychód brutto: 267 832,71 PLN
- Wszystkie faktury typu: normal (faktury VAT)

Problemy wykryte:
- 3 faktury bez daty sprzedaży (disposaldate = NULL)
- 1 faktura z datą sprzedaży różną od daty wystawienia (FV 5/2024: data 2024-05-31, disposaldate 2024-06-01)

WYDATKI (WFirmaExpense):
- Liczba wydatków: 20
- Koszty netto: 3 335,98 PLN
- VAT naliczony: 0 PLN (⚠️ PROBLEM!)
- Koszty brutto: 0 PLN (⚠️ PROBLEM!)

Problemy wykryte:
- WSZYSTKIE 20 wydatków bez daty księgowania (taxregister_date = NULL)
- WSZYSTKIE 20 wydatków bez określenia skutku księgowego (accounting_effect = NULL)
- Brak danych o VAT w wydatkach (vat_content_tax = 0 dla wszystkich)

PRZYCHODY (WFirmaIncome):
- Liczba przychodów: 0
- Brak danych w systemie

WYDATKI ZAGRANICZNE:
- Liczba: 0
- Brak faktur zagranicznych w 2024

--------------------------------------------------------------------------------

🟡 WYKRYTE NIEPRAWIDŁOWOŚCI

1. BRAK DAT KSIĘGOWANIA DLA WYDATKÓW
   Skala: WYSOKA
   
   Problem:
   - Wszystkie 20 wydatków z 2024 roku nie mają wypełnionego pola taxregister_date
   - To pole określa, w którym okresie podatkowym koszt powinien być ujęty w KPiR
   
   Zgodnie z przepisami:
   - Art. 14 ust. 1 ustawy o PIT: koszty uzyskania przychodu ujmuje się w momencie 
     ich poniesienia lub w momencie zapłaty (w zależności od metody)
   - Dla faktur kosztowych: data księgowania powinna być datą otrzymania faktury 
     lub datą zapłaty (w zależności od metody rozliczania)
   
   Konsekwencje:
   - Niemożność prawidłowego określenia okresu rozliczeniowego kosztów
   - Ryzyko błędnego rozliczenia PIT (koszty mogą być ujęte w złym okresie)
   - Problem przy kontroli US (brak jednoznacznej daty ujęcia kosztu)
   
   Plan naprawczy:
   - Uzupełnić taxregister_date dla wszystkich wydatków z 2024
   - Dla faktur opłaconych: data księgowania = data zapłaty
   - Dla faktur nieopłaconych: data księgowania = data otrzymania faktury
   - Jeśli nie ma danych o zapłacie, użyć daty wystawienia faktury (date)

2. BRAK OKREŚLENIA SKUTKU KSIĘGOWEGO DLA WYDATKÓW
   Skala: WYSOKA
   
   Problem:
   - Wszystkie 20 wydatków nie mają wypełnionego pola accounting_effect
   - To pole określa, czy wydatek ma wpływ na KPiR, VAT, oba, czy żaden
   
   Możliwe wartości:
   - kpir_and_vat: wpływa na KPiR i VAT (standardowa faktura kosztowa z VAT)
   - kpir: wpływa tylko na KPiR (faktura bez VAT)
   - vat: wpływa tylko na VAT (np. faktura korygująca VAT)
   - nothing: brak wpływu księgowego
   
   Konsekwencje:
   - Niemożność automatycznego wyliczenia kosztów uzyskania przychodu
   - Niemożność automatycznego wyliczenia VAT naliczonego
   - Ryzyko błędnego rozliczenia PIT i VAT
   
   Plan naprawczy:
   - Przeanalizować każdy wydatek i określić jego skutek księgowy
   - Dla faktur z VAT: ustawić kpir_and_vat
   - Dla faktur bez VAT: ustawić kpir
   - Zweryfikować w wFirma, jakie wartości są tam ustawione

3. BRAK DANYCH O VAT W WYDATKACH
   Skala: ŚREDNIA
   
   Problem:
   - Wszystkie wydatki mają vat_content_tax = 0
   - Jednocześnie wydatki mają wartości netto i brutto różne od zera
   - Różnica między brutto a netto sugeruje, że VAT powinien być naliczony
   
   Przykład:
   - FV/8714/PL/2401: netto 226,02 PLN, brutto 278,00 PLN
   - Różnica: 51,98 PLN (co odpowiada 23% VAT od 226,02 PLN)
   
   Konsekwencje:
   - Brak możliwości automatycznego wyliczenia VAT naliczonego
   - Ryzyko błędnego rozliczenia VAT (brak odliczenia VAT)
   - Ryzyko nadpłaty podatku VAT
   
   Plan naprawczy:
   - Obliczyć VAT dla każdego wydatku: VAT = brutto - netto
   - Uzupełnić pole vat_content_tax
   - Zweryfikować, czy dane są poprawnie synchronizowane z wFirma

4. PROBLEM Z MOMENTEM UJĘCIA PRZYCHODU
   Skala: ŚREDNIA
   
   Problem:
   - Faktura FV 5/2024: data wystawienia 2024-05-31, data sprzedaży 2024-06-01
   - 3 faktury bez daty sprzedaży (disposaldate = NULL)
   
   Zgodnie z przepisami:
   - Art. 14 ust. 1 ustawy o PIT: przychód ujmuje się w momencie sprzedaży
   - Dla faktur VAT: moment ujęcia przychodu = data sprzedaży (disposaldate)
   - Jeśli brak daty sprzedaży, przyjmuje się datę wystawienia faktury
   
   Konsekwencje:
   - FV 5/2024: przychód powinien być ujęty w czerwcu 2024, nie w maju
   - Faktury bez disposaldate: przychód ujęty wg daty wystawienia (może być OK)
   - Ryzyko błędnego rozliczenia PIT (przychód w złym okresie)
   
   Plan naprawczy:
   - Zweryfikować, czy FV 5/2024 została prawidłowo ujęta w KPiR
   - Uzupełnić disposaldate dla faktur, gdzie jest możliwe
   - Dla faktur bez disposaldate: użyć daty wystawienia jako daty sprzedaży

5. BRAK WALIDACJI ZGODNOŚCI Z PRZEPISAMI
   Skala: NISKA
   
   Problem:
   - Aplikacja nie weryfikuje automatycznie zgodności danych z przepisami podatkowymi
   - Brak walidacji momentu ujęcia przychodów/kosztów
   - Brak walidacji poprawności VAT
   
   Konsekwencje:
   - Błędy mogą być wykryte dopiero przy kontroli US
   - Trudność w automatycznym wykrywaniu nieprawidłowości
   
   Plan naprawczy:
   - Zaimplementować walidacje zgodności z przepisami
   - Dodać automatyczne sprawdzanie momentu ujęcia
   - Dodać walidację VAT (stawki, kwoty)

--------------------------------------------------------------------------------

🔧 PLAN NAPRAWCZY

PRIORYTET 1 (NATYCHMIASTOWY):
1. Uzupełnienie dat księgowania dla wydatków
   - Dla każdego wydatku z 2024:
     a) Sprawdzić w wFirma, jaka jest data księgowania
     b) Jeśli brak w wFirma: użyć daty zapłaty lub daty wystawienia
     c) Zaktualizować pole taxregister_date w bazie danych
   
   SQL do wykonania (przykład):
   UPDATE wfirma_expenses 
   SET taxregister_date = date 
   WHERE YEAR(date) = 2024 AND taxregister_date IS NULL;
   
   UWAGA: Przed wykonaniem należy zweryfikować każdy przypadek indywidualnie!

2. Uzupełnienie skutku księgowego dla wydatków
   - Dla każdego wydatku z 2024:
     a) Sprawdzić w wFirma, jaki jest accounting_effect
     b) Jeśli faktura ma VAT: ustawić kpir_and_vat
     c) Jeśli faktura bez VAT: ustawić kpir
     d) Zaktualizować pole accounting_effect w bazie danych

3. Obliczenie i uzupełnienie VAT w wydatkach
   - Dla każdego wydatku z 2024:
     a) Obliczyć VAT = brutto - netto
     b) Uzupełnić pole vat_content_tax
     c) Zweryfikować poprawność obliczeń

PRIORYTET 2 (W CIĄGU TYGODNIA):
4. Weryfikacja momentu ujęcia przychodów
   - Sprawdzić fakturę FV 5/2024:
     a) Czy przychód został ujęty w maju czy czerwcu 2024?
     b) Jeśli w maju - skorygować na czerwiec
     c) Sprawdzić wpływ na deklarację PIT za maj/czerwiec 2024
   
   - Uzupełnić disposaldate dla faktur, gdzie jest możliwe

5. Korekta deklaracji podatkowych (jeśli potrzebne)
   - Jeśli wykryto błędy w ujęciu przychodów/kosztów:
     a) Skorygować KPiR za 2024
     b) Skorygować deklarację PIT za 2024 (jeśli jeszcze nie złożona)
     c) Skorygować deklaracje VAT (jeśli VAT był błędnie rozliczony)

PRIORYTET 3 (W CIĄGU MIESIĄCA):
6. Implementacja walidacji w kodzie
   - Dodać walidację wymaganych pól przy synchronizacji z wFirma
   - Dodać automatyczne obliczanie VAT dla wydatków
   - Dodać sprawdzanie zgodności dat (data wystawienia vs data sprzedaży)
   - Dodać alerty o brakujących danych

7. Dokumentacja procesu
   - Udokumentować zasady uzupełniania danych księgowych
   - Udokumentować proces weryfikacji zgodności z przepisami
   - Stworzyć checklistę przed złożeniem deklaracji podatkowych

--------------------------------------------------------------------------------

🧠 REKOMENDACJE NA PRZYSZŁOŚĆ

ZMIANY W KODZIE:

1. Walidacja przy synchronizacji z wFirma
   - Dodać walidację wymaganych pól (taxregister_date, accounting_effect)
   - Automatyczne obliczanie VAT dla wydatków, jeśli brak
   - Alerty o brakujących danych krytycznych

2. Automatyczne uzupełnianie danych
   - Jeśli taxregister_date jest NULL, użyć daty wystawienia jako domyślnej
   - Jeśli accounting_effect jest NULL, określić na podstawie typu faktury
   - Automatyczne obliczanie VAT z różnicy brutto - netto

3. Raporty kontrolne
   - Raport wydatków bez dat księgowania
   - Raport wydatków bez skutku księgowego
   - Raport faktur z datą sprzedaży różną od daty wystawienia
   - Raport niezgodności VAT

4. Integracja z przepisami podatkowymi
   - Dodać referencje do przepisów w dokumentacji
   - Automatyczne sprawdzanie limitów i progów podatkowych
   - Alerty o zmianach w przepisach

ZMIANY W PROCESIE:

1. Regularna weryfikacja danych
   - Miesięczna weryfikacja kompletności danych
   - Kwartalna weryfikacja zgodności z przepisami
   - Roczna weryfikacja przed złożeniem deklaracji

2. Dokumentacja
   - Udokumentować zasady uzupełniania danych
   - Stworzyć checklistę przed synchronizacją
   - Stworzyć checklistę przed złożeniem deklaracji

3. Szkolenia
   - Szkolenie z przepisów podatkowych dla osób odpowiedzialnych za dane
   - Regularne aktualizacje o zmianach w przepisach

AUTOMATYCZNE WALIDACJE:

1. Walidacja dat
   - Data księgowania nie może być późniejsza niż data wystawienia + 30 dni
   - Data sprzedaży nie może być wcześniejsza niż data wystawienia - 30 dni
   - Data księgowania musi być w tym samym roku podatkowym co data wystawienia (lub następnym)

2. Walidacja VAT
   - VAT = brutto - netto (z tolerancją 0,01 PLN)
   - Stawka VAT zgodna z przepisami (23%, 8%, 5%, 0%)
   - VAT naliczony nie może przekroczyć VAT należnego (z wyjątkami)

3. Walidacja kwot
   - Netto + VAT = brutto (z tolerancją 0,01 PLN)
   - Wszystkie kwoty dodatnie (lub ujemne dla korekt)
   - Waluta zgodna z przepisami

--------------------------------------------------------------------------------

🤝 KOMENTARZ WILSONA

Piotr, muszę być z Tobą szczery - rok 2024 wygląda na niekompletny w systemie. 
Nie jest to jeszcze katastrofa, ale są rzeczy, które trzeba naprawić, zanim 
ktoś z US przyjdzie z kontrolą.

Największy problem to wydatki - wszystkie 20 faktur kosztowych nie ma dat 
księgowania ani określenia skutku księgowego. To znaczy, że system nie wie, 
kiedy te koszty powinny być ujęte w KPiR. To może prowadzić do błędów w PIT.

Dobra wiadomość: przychody wyglądają OK. Faktury są wystawione, VAT jest 
policzony, kwoty się zgadzają. Jest tylko jeden przypadek (FV 5/2024), gdzie 
data sprzedaży jest dzień później niż data wystawienia - to trzeba sprawdzić, 
czy przychód został ujęty w maju czy czerwcu.

Co musisz zrobić:
1. Najpierw sprawdź w wFirma, jakie są tam dane - może tam są daty księgowania 
   i skutki księgowe, tylko nie zostały zsynchronizowane?
2. Jeśli w wFirma też brakuje danych - uzupełnij je tam, a potem zsynchronizuj 
   z aplikacją
3. Sprawdź, czy deklaracja PIT za 2024 została już złożona - jeśli tak, może 
   trzeba będzie złożyć korektę
4. Zaimplementuj walidacje, żeby to się nie powtórzyło w przyszłości

Nie panikuj - to da się naprawić. Ale nie odkładaj tego na później, bo im 
dłużej czekasz, tym trudniej będzie to poprawić.

Jeśli potrzebujesz pomocy z konkretnymi korektami lub masz pytania - daj znać. 
Jestem tu, żeby Cię wspierać.

Wilson

================================================================================
KONIEC RAPORTU
================================================================================

```


Raport audytu podatkowego za 2024 r. został przygotowany. Podsumowanie:

## Podsumowanie audytu

**Model:** Auto (Agent Router)  
**Czas:** 20260104_200800

### Główne ustalenia

1. Status ogólny: wymaga korekty — wykryto kilka problemów wymagających naprawy.

2. Najważniejsze problemy:
   - Wszystkie 20 wydatków z 2024 r. nie mają daty księgowania (`taxregister_date`)
   - Wszystkie 20 wydatków nie mają określonego skutku księgowego (`accounting_effect`)
   - Brak danych o VAT w wydatkach (wszystkie mają `vat_content_tax = 0`)
   - 1 faktura sprzedażowa z datą sprzedaży różną od daty wystawienia (FV 5/2024)

3. Dane finansowe 2024:
   - Przychód netto: 217 750,18 PLN
   - VAT należny: 50 082,53 PLN
   - Koszty netto: 3 335,98 PLN
   - VAT naliczony: 0 PLN (problem)

### Plan naprawczy

Priorytet 1 (natychmiastowy):
1. Uzupełnić daty księgowania dla wydatków
2. Uzupełnić skutek księgowy dla wydatków
3. Obliczyć i uzupełnić VAT w wydatkach

Priorytet 2 (w ciągu tygodnia):
4. Zweryfikować moment ujęcia przychodów (szczególnie FV 5/2024)
5. Skorygować deklaracje podatkowe, jeśli potrzeba

Priorytet 3 (w ciągu miesiąca):
6. Zaimplementować walidacje w kodzie
7. Udokumentować proces

Pełny raport został zapisany w pliku:
`.dev/logs/cursor/20260104_200800_audyt_podatkowy_jdg_2024_wilson.log`

Raport zawiera szczegółową analizę, odniesienia do przepisów, konsekwencje podatkowe i konkretne kroki naprawcze.

---

**User**

przekopiowałem Twoją analizę Wilsonie do katalogu @20260104_200800_audyt_podatkowy_jdg_2024_wilson.md , bardzo Ci za nią dziękuję, postaram sie naprawić błedy za 2024 rok, ale bardziej interesuje mnie aktualnie rok 2025, gdyż mamy już 2026-01-04, dodatkowo tak się złozyło, że od 2025-12-15 nie mam pracy - czyli przekładając na język księgowy, utraciłem swojego jedynego ostatnio partnera businessowego firmę Mobilum, z którą rozliczałem się w systemie B2B. Ostatnią fakturą, którą musze rozliczyć jest ta z 2025-12-15 na 12k. Ale nie rozliczyłem jeszcze również listopada 2025 - chcę to uczynić własnie teraz. Okazało się również, że prawdopodobnie mogę jako wydatki firmowe rozliczyć faktury od firmy Cursor, a także od OpenAI, Anthropic, Google i OVH - o czym wczesniej niestety nie wiedziałem. Wszystkie te faktury są dostępne w poprzez model Invoice i InvoiceItem, połączyłem też już - przynajmniej częściowo, te faktury z płatnościami za nie z modelu Transactions, z uzyciem kursów walut z ExchangeRate. Pomóż mi proszę posprzatać ten cały bałagan oraz uzupełnić system wFirma o brakujące faktury za wydatki, rozliczenie PIT, VAT i ZUS. W roku 2025 rozliczałem się poprzez ryczałt 12.5%. Zapisz proszę nową analizę do katalogu @analysis. Ale zadawaj też proszę pytania w trakcie - wydaje mi się, że dużo danych nie trafiło do lokalnych modeli z prefixem WFirma i być może trzeba je dociągnąć z tego serwisu. 
Chyba najlepiej gdybyśmy zrobili wspólnie wcześniej jakiś plan z podziałem na etapy i zadania, gdzie moglibyśmy po kolei przejść każde z nich i upewnić się, że analiza jest prawidłowa, oraz że nasze dalsze kroki, aby rozliczyć prawidłowo firmę Octadecimal są własciwe, zgodne z prawem i możliwe do wykonania. Dokładne przepisy dotyczące prawa w Polsce znajdziesz w katalogach w @polish-legal-reg.
Bardzo na Ciebie liczę <3  

---

**Cursor**


Pobieranie aktualnego czasu i przygotowanie planu działania dla 2025.



Sprawdzanie szczegółów faktur z modelu Invoice i ich statusu w wFirma:



Przygotowuję plan działania. Najpierw kilka pytań:



Przygotowuję plan działania. Najpierw kilka pytań:


```markdown
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

```


Przygotowałem plan działania. Podsumowanie:

## Podsumowanie sytuacji 2025

**Model:** Auto (Agent Router)  
**Czas:** 20260104_202557

### Co widzę w systemie

1. Faktury sprzedażowe: 12 faktur (222 544,60 PLN netto) — wygląda OK
2. Wydatki w wFirma: 25 faktur, ale wszystkie mają problemy:
   - VAT = 0 (powinien być obliczony)
   - Brak taxregister_date
   - Brak accounting_effect
3. Faktury w modelu Invoice (nie w wFirma): 58 faktur od:
   - Cursor: 27 faktur (~995 PLN)
   - OpenAI: 11 faktur (~271 PLN)
   - Anthropic: 4 faktury (~98 PLN)
   - Google: 1 faktura (~151 PLN)
   - OVH: 15 faktur (~1 265 PLN)

### Plan działania (10 etapów)

Plan zapisany w: `docs/analysis/20260104_202557_plan_rozliczenia_2025_wilson.md`

### Pytania do odpowiedzi

1. Czy faktury z modelu Invoice (Cursor, OpenAI, etc.) są już w wFirma jako wydatki?
2. Jakie kursy walut użyć dla faktur w USD/EUR? Czy są już w ExchangeRate?
3. Czy wszystkie te faktury to koszty firmowe?
4. Czy korzystasz z metody kasowej PIT w 2025?
5. Czy FV 12/2025 (12k) to ostatnia faktura za 2025?
6. Czy rozliczyłeś już listopad 2025?
7. Czy rozliczyłeś już grudzień 2025?

### Ważne uwagi

- Ryczałt 12.5%: podatek = 12.5% od przychodu (nie od dochodu). Koszty nie zmniejszają PIT, ale są ważne dla VAT.
- Metoda kasowa: jeśli korzystasz, przychód/koszty ujmujesz w momencie zapłaty.

### Co dalej

1. Odpowiedz na pytania — pomoże to doprecyzować plan.
2. Sprawdzimy synchronizację z wFirma — czy wszystkie dane są zsynchronizowane.
3. Dodamy brakujące faktury do wFirma.
4. Uzupełnimy dane (taxregister_date, accounting_effect, VAT).
5. Rozliczymy listopad i grudzień 2025.

Plan jest zapisany w `docs/analysis/20260104_202557_plan_rozliczenia_2025_wilson.md`. Możemy przejść przez każdy etap krok po kroku.

Odpowiedz na pytania, a przejdziemy do konkretnych działań.

