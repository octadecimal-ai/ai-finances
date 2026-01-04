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

