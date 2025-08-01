<?php

namespace App\Services;

use App\Models\Finance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FinancesService
{
    /**
     * Oblicza ostatni dzień miesiąca na podstawie daty
     */
    public function calculateLastDayOfMonth(string $date): string
    {
        $carbon = Carbon::parse($date);
        return $carbon->endOfMonth()->format('Y-m-d');
    }

    /**
     * Oblicza liczbę dni roboczych między dwoma datami
     */
    public function calculateWorkingDays(string $startDate, string $endDate): int
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        $workingDays = 0;
        $current = $start->copy();
        
        while ($current->lte($end)) {
            if ($current->isWeekday()) {
                $workingDays++;
            }
            $current->addDay();
        }
        
        return $workingDays;
    }

    /**
     * Oblicza sumę opłat dla danego miesiąca
     */
    public function calculateSumaOplat(Finance $finance): float
    {
        $sum = 0;
        
        // Dodaj wszystkie kategorie wydatków
        $categories = [
            'mieszkanie', 'kredyt_samochodowy', 'oc', 'play', 'internet', 'woda', 'inne',
            'us_santander', 'vg_mc', 'samochod', 'paliwo', 'remont', 'sprzatanie', 'hobby',
            'podroze', 'wiola_zwrot_kasy', 'allegro', 'allegro_pay', 'macbook_santander',
            'note_3_8_alior_mbank', 'bph_silnik', 'alior_maroko', 'alior_basia',
            'komp_dron_smartnej', 'smartney_7k', 'ca_rower_mbank', 'ca_wynajem_mbank',
            'lux_med', 'multisport', 'innogy', 'mec_boguslawa', 'terapia', 'angielski',
            'xiaomi', 'ca_eg', 'millenium_eg', 'egzekucja', 'alior_piec_blacharz_remont',
            'alior_konsolidacja', 'wiola_plus', 'basia_plus_velo', 'basia_iphone'
        ];
        
        foreach ($categories as $category) {
            if ($finance->$category) {
                $sum += $finance->$category;
            }
        }
        
        return $sum;
    }

    /**
     * Oblicza ile zostaje (pensja - suma opłat)
     */
    public function calculateZostaje(Finance $finance): float
    {
        $pensja = $finance->pensja ?? 0;
        $sumaOplat = $this->calculateSumaOplat($finance);
        
        return $pensja - $sumaOplat;
    }

    /**
     * Oblicza sumę kredytów i pożyczek
     */
    public function calculateKredytyIPozyczki(Finance $finance): float
    {
        $sum = 0;
        
        $kredyty = [
            'us_santander', 'macbook_santander', 'smartney_7k', 'alior_maroko',
            'alior_basia', 'alior_konsolidacja', 'alior_piec_blacharz_remont'
        ];
        
        foreach ($kredyty as $kredyt) {
            if ($finance->$kredyt) {
                $sum += $finance->$kredyt;
            }
        }
        
        return $sum;
    }

    /**
     * Oblicza sumę kategorii mieszkanie
     */
    public function calculateMieszkanieKategoria(Finance $finance): float
    {
        $sum = 0;
        
        $mieszkanie = [
            'mieszkanie', 'woda', 'innogy', 'sprzatanie'
        ];
        
        foreach ($mieszkanie as $kategoria) {
            if ($finance->$kategoria) {
                $sum += $finance->$kategoria;
            }
        }
        
        return $sum;
    }

    /**
     * Oblicza P1 (suma wybranych kategorii)
     */
    public function calculateP1(Finance $finance): float
    {
        $sum = 0;
        
        // Kategorie z formuły =sum(M2+X2+AH2+AI2+AN2+AO2+AP2+AJ2+AK2+AL2+AM2+AQ2+AQ2+BB2+BC2)
        // Mapowanie kolumn: M=pozyczki, X=inne_plus, AH=us_santander, AI=vg_mc, AN=macbook_santander, 
        // AO=note_3_8_alior_mbank, AP=bph_silnik, AJ=potr_is24, AK=zosia, AL=alior_maroko, 
        // AM=alior_basia, AQ=smartney_7k, BB=alior_konsolidacja, BC=alior_piec_blacharz_remont
        
        $p1Categories = [
            'pozyczki', 'inne_plus', 'us_santander', 'vg_mc', 'macbook_santander',
            'note_3_8_alior_mbank', 'bph_silnik', 'potr_is24', 'zosia', 'alior_maroko',
            'alior_basia', 'smartney_7k', 'alior_konsolidacja', 'alior_piec_blacharz_remont'
        ];
        
        foreach ($p1Categories as $category) {
            if ($finance->$category) {
                $sum += $finance->$category;
            }
        }
        
        return $sum;
    }

    /**
     * Aktualizuje obliczone pola w rekordzie wydatków
     */
    public function updateCalculatedFields(Finance $finance): void
    {
        $finance->suma_oplat = $this->calculateSumaOplat($finance);
        $finance->zostaje = $this->calculateZostaje($finance);
        $finance->kredyty_i_pozyczki = $this->calculateKredytyIPozyczki($finance);
        $finance->mieszkanie_kategoria = $this->calculateMieszkanieKategoria($finance);
        $finance->p1 = $this->calculateP1($finance);
        
        $finance->save();
    }

    /**
     * Importuje dane z arkusza Excel do bazy danych
     */
    public function importFromExcel(array $data, string $sourceFile, string $sourceId): array
    {
        $imported = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($data as $index => $row) {
            $rowNumber = $index + 2; // +2 bo usunęliśmy nagłówki i index zaczyna się od 0

            try {
                // Sprawdź czy to nie jest wiersz "suma"
                if (isset($row[0]) && strtolower(trim($row[0])) === 'suma') {
                    $skipped++;
                    continue;
                }

                // Sprawdź czy mamy wymagane dane
                if (empty($row[0]) || !is_numeric($row[0])) {
                    $skipped++;
                    continue;
                }

                // Konwertuj datę Excel na datę PHP
                $excelDate = (int)$row[0];
                $date = $this->excelToDate($excelDate);
                
                if (!$date) {
                    $skipped++;
                    continue;
                }

                $wydatkiData = [
                    'miesiac' => $date,
                    'ostatni_dzien' => $this->calculateLastDayOfMonth($date),
                    'dni_robocze' => $this->calculateWorkingDays($date, $this->calculateLastDayOfMonth($date)),
                    'source_file' => $sourceFile,
                    'source_id' => "{$sourceId}_row_{$rowNumber}",
                ];

                // Mapuj dane z arkusza na kolumny w bazie
                $columnMapping = $this->getColumnMapping();
                
                foreach ($columnMapping as $excelColumn => $dbColumn) {
                    if (isset($row[$excelColumn]) && is_numeric($row[$excelColumn])) {
                        $wydatkiData[$dbColumn] = (float)$row[$excelColumn];
                    }
                }

                // Sprawdź czy rekord już istnieje
                $existing = Finance::where('miesiac', $date)->first();
                if ($existing) {
                    $existing->update($wydatkiData);
                    $finance = $existing;
                } else {
                    $finance = Finance::create($wydatkiData);
                }

                // Oblicz i zaktualizuj pola obliczone
                $this->updateCalculatedFields($finance);

                $imported++;

            } catch (\Exception $e) {
                $errors++;
                Log::error('Import wydatków błąd', [
                    'row' => $rowNumber,
                    'data' => $row,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
            'skipped' => $skipped
        ];
    }

    /**
     * Mapowanie kolumn z arkusza Excel na kolumny w bazie danych
     */
    private function getColumnMapping(): array
    {
        return [
            10 => 'pensja', // Kolumna K (10)
            12 => 'inne_plus', // Kolumna L (12)
            13 => 'pozyczki', // Kolumna M (13)
            14 => 'zwrot_pozyczki', // Kolumna N (14)
            15 => 'potr_is24', // Kolumna O (15)
            16 => 'zosia', // Kolumna P (16)
            17 => 'mieszkanie', // Kolumna Q (17)
            18 => 'kredyt_samochodowy', // Kolumna R (18)
            19 => 'oc', // Kolumna S (19)
            20 => 'play', // Kolumna T (20)
            21 => 'internet', // Kolumna U (21)
            22 => 'woda', // Kolumna V (22)
            23 => 'inne', // Kolumna W (23)
            24 => 'us_santander', // Kolumna X (24)
            25 => 'vg_mc', // Kolumna Y (25)
            26 => 'samochod', // Kolumna Z (26)
            27 => 'paliwo', // Kolumna AA (27)
            28 => 'remont', // Kolumna AB (28)
            29 => 'sprzatanie', // Kolumna AC (29)
            30 => 'hobby', // Kolumna AD (30)
            31 => 'podroze', // Kolumna AE (31)
            32 => 'wiola_zwrot_kasy', // Kolumna AF (32)
            33 => 'allegro', // Kolumna AG (33)
            34 => 'allegro_pay', // Kolumna AH (34)
            35 => 'macbook_santander', // Kolumna AI (35)
            36 => 'note_3_8_alior_mbank', // Kolumna AJ (36)
            37 => 'bph_silnik', // Kolumna AK (37)
            38 => 'alior_maroko', // Kolumna AL (38)
            39 => 'alior_basia', // Kolumna AM (39)
            40 => 'komp_dron_smartnej', // Kolumna AN (40)
            41 => 'smartney_7k', // Kolumna AO (41)
            42 => 'ca_rower_mbank', // Kolumna AP (42)
            43 => 'ca_wynajem_mbank', // Kolumna AQ (43)
            44 => 'lux_med', // Kolumna AR (44)
            45 => 'multisport', // Kolumna AS (45)
            46 => 'innogy', // Kolumna AT (46)
            47 => 'mec_boguslawa', // Kolumna AU (47)
            48 => 'terapia', // Kolumna AV (48)
            49 => 'angielski', // Kolumna AW (49)
            50 => 'xiaomi', // Kolumna AX (50)
            51 => 'ca_eg', // Kolumna AY (51)
            52 => 'millenium_eg', // Kolumna AZ (52)
            53 => 'egzekucja', // Kolumna BA (53)
            54 => 'alior_piec_blacharz_remont', // Kolumna BB (54)
            55 => 'alior_konsolidacja', // Kolumna BC (55)
            57 => 'wiola_plus', // Kolumna BD (57)
            58 => 'basia_plus_velo', // Kolumna BE (58)
            59 => 'basia_iphone', // Kolumna BF (59)
        ];
    }

    /**
     * Konwertuje datę Excel na datę PHP
     */
    private function excelToDate(int $excelDate): ?string
    {
        // Excel daty zaczynają się od 1 stycznia 1900
        // PHP daty zaczynają się od 1 stycznia 1970
        // Różnica to 25569 dni
        
        $unixTimestamp = ($excelDate - 25569) * 86400;
        
        if ($unixTimestamp < 0) {
            return null;
        }
        
        return date('Y-m-d', $unixTimestamp);
    }
} 