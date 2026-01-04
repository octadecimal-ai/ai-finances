#!/bin/bash

# Skrypt do importowania plików CSV z kosztami do bazy danych MySQL
# Model: Claude 3.5 Sonnet
# Czas: 2025-07-31 14:36:53

# Konfiguracja bazy danych
DATABASE_URL="mysql://root:Passat377310!@127.0.0.1:3306/costs?serverVersion=8.0&charset=utf8mb4"

# Wyciągnięcie parametrów połączenia z DATABASE_URL
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_USER="root"
DB_PASS="Passat377310!"
DB_NAME="costs"

# Katalog z plikami CSV
CSV_DIR="../costs"

# Google Sheets URL
GOOGLE_SHEETS_URL="https://docs.google.com/spreadsheets/d/19P92DYMvNzCZkzDlNGrkyfgjOzLZ3MsiXDz6DY0lRpU/edit?usp=sharing"
GOOGLE_SHEETS_ID="19P92DYMvNzCZkzDlNGrkyfgjOzLZ3MsiXDz6DY0lRpU"

# Kolory dla output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== Skrypt importowania kosztów Cursor ===${NC}"

# Funkcja do sprawdzenia czy MySQL jest dostępny
check_mysql() {
    if ! mysql --silent -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "SELECT 1;" >/dev/null 2>&1; then
        echo -e "${RED}❌ Błąd: Nie można połączyć się z bazą danych MySQL${NC}"
        echo "Sprawdź czy MySQL jest uruchomiony i czy dane połączenia są poprawne."
        exit 1
    fi
    echo -e "${GREEN}✅ Połączenie z MySQL OK${NC}"
}

# Funkcja do tworzenia bazy danych i tabel
create_database_and_tables() {
    echo -e "${YELLOW}📊 Tworzenie bazy danych i tabel...${NC}"
    
    mysql --silent -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" <<EOF 2>/dev/null
    -- Tworzenie bazy danych jeśli nie istnieje
    CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    USE \`$DB_NAME\`;
    
    -- Tabela processed_files do śledzenia przetworzonych plików
    CREATE TABLE IF NOT EXISTS \`processed_files\` (
        \`id\` int(11) NOT NULL AUTO_INCREMENT,
        \`filename\` varchar(255) NOT NULL,
        \`processed_at\` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        \`records_count\` int(11) DEFAULT 0,
        PRIMARY KEY (\`id\`),
        UNIQUE KEY \`filename\` (\`filename\`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    
    -- Tabela costs do przechowywania danych z CSV
    CREATE TABLE IF NOT EXISTS \`costs\` (
        \`id\` int(11) NOT NULL AUTO_INCREMENT,
        \`date\` datetime NOT NULL,
        \`user\` varchar(255) DEFAULT NULL,
        \`kind\` varchar(255) DEFAULT NULL,
        \`max_mode\` varchar(50) DEFAULT NULL,
        \`model\` varchar(255) DEFAULT NULL,
        \`tokens\` varchar(255) DEFAULT NULL,
        \`cost\` decimal(10,4) DEFAULT NULL,
        \`source_file\` varchar(255) DEFAULT NULL,
        \`created_at\` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (\`id\`),
        KEY \`idx_date\` (\`date\`),
        KEY \`idx_source_file\` (\`source_file\`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    
    -- Tabela finanses do przechowywania danych z Google Sheets
    CREATE TABLE IF NOT EXISTS \`finanses\` (
        \`id\` int(11) NOT NULL AUTO_INCREMENT,
        \`miesiac\` date DEFAULT NULL,
        \`ostatni_dzien\` date DEFAULT NULL,
        \`dni_robocze\` int(11) DEFAULT NULL,
        \`urlop_swieta\` decimal(10,2) DEFAULT NULL,
        \`nadgodziny\` decimal(10,2) DEFAULT NULL,
        \`stawka_netto\` decimal(10,2) DEFAULT NULL,
        \`pensja_brutto\` decimal(10,2) DEFAULT NULL,
        \`pit\` decimal(10,2) DEFAULT NULL,
        \`vat\` decimal(10,2) DEFAULT NULL,
        \`zus\` decimal(10,2) DEFAULT NULL,
        \`pensja\` decimal(10,2) DEFAULT NULL,
        \`inne\` decimal(10,2) DEFAULT NULL,
        \`pozyczki\` decimal(10,2) DEFAULT NULL,
        \`zwrot_pozyczki\` decimal(10,2) DEFAULT NULL,
        \`potr_is24\` decimal(10,2) DEFAULT NULL,
        \`zosia\` decimal(10,2) DEFAULT NULL,
        \`mieszkanie\` decimal(10,2) DEFAULT NULL,
        \`kredyt_samochodowy\` decimal(10,2) DEFAULT NULL,
        \`oc\` decimal(10,2) DEFAULT NULL,
        \`play\` decimal(10,2) DEFAULT NULL,
        \`internet\` decimal(10,2) DEFAULT NULL,
        \`woda\` decimal(10,2) DEFAULT NULL,
        \`inne_wydatki\` decimal(10,2) DEFAULT NULL,
        \`us_santander\` decimal(10,2) DEFAULT NULL,
        \`vg_mc\` decimal(10,2) DEFAULT NULL,
        \`samochod\` decimal(10,2) DEFAULT NULL,
        \`paliwo\` decimal(10,2) DEFAULT NULL,
        \`remont\` decimal(10,2) DEFAULT NULL,
        \`sprzatanie\` decimal(10,2) DEFAULT NULL,
        \`hobby\` decimal(10,2) DEFAULT NULL,
        \`podroze\` decimal(10,2) DEFAULT NULL,
        \`wiola_zwrot_kasy\` decimal(10,2) DEFAULT NULL,
        \`allegro\` decimal(10,2) DEFAULT NULL,
        \`allegro_pay\` decimal(10,2) DEFAULT NULL,
        \`macbook_santander\` decimal(10,2) DEFAULT NULL,
        \`note_3_8_alior_mbank\` decimal(10,2) DEFAULT NULL,
        \`bph_silnik\` decimal(10,2) DEFAULT NULL,
        \`alior_maroko\` decimal(10,2) DEFAULT NULL,
        \`alior_basia\` decimal(10,2) DEFAULT NULL,
        \`komp_dron_smartnej\` decimal(10,2) DEFAULT NULL,
        \`smartney_7k\` decimal(10,2) DEFAULT NULL,
        \`ca_rower_mbank\` decimal(10,2) DEFAULT NULL,
        \`ca_wynajem\` decimal(10,2) DEFAULT NULL,
        \`lux_med\` decimal(10,2) DEFAULT NULL,
        \`multisport\` decimal(10,2) DEFAULT NULL,
        \`innogy\` decimal(10,2) DEFAULT NULL,
        \`mec_boguslawa\` decimal(10,2) DEFAULT NULL,
        \`terapia\` decimal(10,2) DEFAULT NULL,
        \`angielski\` decimal(10,2) DEFAULT NULL,
        \`xiaomi\` decimal(10,2) DEFAULT NULL,
        \`ca_eg\` decimal(10,2) DEFAULT NULL,
        \`millenium_eg\` decimal(10,2) DEFAULT NULL,
        \`egzekucja\` decimal(10,2) DEFAULT NULL,
        \`alior_piec_blacharz_remont_velo\` decimal(10,2) DEFAULT NULL,
        \`alior_konsolidacja_ca_mama\` decimal(10,2) DEFAULT NULL,
        \`wiola_plus\` decimal(10,2) DEFAULT NULL,
        \`basia_plus_velo\` decimal(10,2) DEFAULT NULL,
        \`basia_iphone\` decimal(10,2) DEFAULT NULL,
        \`suma_oplat\` decimal(10,2) DEFAULT NULL,
        \`zostaje\` decimal(10,2) DEFAULT NULL,
        \`opis_innych\` text DEFAULT NULL,
        \`diy\` decimal(10,2) DEFAULT NULL,
        \`wiek\` int(11) DEFAULT NULL,
        \`kredyty_pozyczki\` decimal(10,2) DEFAULT NULL,
        \`mieszkanie_kategoria\` decimal(10,2) DEFAULT NULL,
        \`zosia_kategoria\` decimal(10,2) DEFAULT NULL,
        \`archived\` boolean DEFAULT FALSE,
        \`archived_date\` timestamp NULL DEFAULT NULL,
        \`created_at\` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        \`updated_at\` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (\`id\`),
        KEY \`idx_miesiac\` (\`miesiac\`),
        KEY \`idx_archived\` (\`archived\`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOF

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Baza danych i tabele utworzone/istnieją${NC}"
    else
        echo -e "${RED}❌ Błąd podczas tworzenia bazy danych i tabel${NC}"
        exit 1
    fi
}

# Funkcja do sprawdzenia czy plik został już przetworzony
is_file_processed() {
    local filename="$1"
    local count=$(mysql --silent -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -s -N -e "USE \`$DB_NAME\`; SELECT COUNT(*) FROM processed_files WHERE filename = '$filename';" 2>/dev/null)
    echo "$count"
}

# Funkcja do importowania pliku CSV
import_csv_file() {
    local csv_file="$1"
    local filename=$(basename "$csv_file")
    
    echo -e "${YELLOW}📁 Przetwarzanie pliku: $filename${NC}"
    
    # Sprawdzenie czy plik istnieje
    if [ ! -f "$csv_file" ]; then
        echo -e "${RED}❌ Plik $csv_file nie istnieje${NC}"
        return 1
    fi
    
    # Sprawdzenie czy plik został już przetworzony
    local processed=$(is_file_processed "$filename")
    if [ "$processed" -gt 0 ]; then
        echo -e "${YELLOW}⏭️  Plik $filename już został przetworzony, pomijam${NC}"
        return 0
    fi
    
    # Tymczasowy plik SQL
    local temp_sql="/tmp/import_${filename%.csv}.sql"
    
    # Konwersja CSV do SQL z sortowaniem według daty
    echo "USE \`$DB_NAME\`;" > "$temp_sql"
    echo "START TRANSACTION;" >> "$temp_sql"
    
    # Pomijamy nagłówek i sortujemy według daty (kolumna 1)
    tail -n +2 "$csv_file" | sort -t',' -k1,1 | while IFS=',' read -r date user kind max_mode model tokens cost; do
        # Usuwanie cudzysłowów z wartości
        date=$(echo "$date" | sed 's/^"//;s/"$//')
        user=$(echo "$user" | sed 's/^"//;s/"$//')
        kind=$(echo "$kind" | sed 's/^"//;s/"$//')
        max_mode=$(echo "$max_mode" | sed 's/^"//;s/"$//')
        model=$(echo "$model" | sed 's/^"//;s/"$//')
        tokens=$(echo "$tokens" | sed 's/^"//;s/"$//')
        cost=$(echo "$cost" | sed 's/^"//;s/"$//')
        
        # Konwersja daty z ISO 8601 do formatu MySQL
        # Usuwamy 'T' i 'Z' z formatu ISO i konwertujemy
        mysql_date=$(echo "$date" | sed 's/T/ /;s/Z$//' | sed 's/\.[0-9]*$//' 2>/dev/null || echo "$date")
        
        # Escape znaków specjalnych
        user=$(echo "$user" | sed "s/'/\\\'/g")
        kind=$(echo "$kind" | sed "s/'/\\\'/g")
        max_mode=$(echo "$max_mode" | sed "s/'/\\\'/g")
        model=$(echo "$model" | sed "s/'/\\\'/g")
        tokens=$(echo "$tokens" | sed "s/'/\\\'/g")
        
        # Przetwarzanie kolumny cost - "Included" zamieniamy na NULL, resztę na liczbę
        if [ "$cost" = "Included" ] || [ "$cost" = "" ]; then
            cost_value="NULL"
        else
            # Usuwamy znak $ i konwertujemy na liczbę
            cost_value=$(echo "$cost" | sed 's/\$//g' | sed 's/,//g')
            # Sprawdzamy czy to liczba
            if [[ "$cost_value" =~ ^[0-9]+\.?[0-9]*$ ]]; then
                cost_value="'$cost_value'"
            else
                cost_value="NULL"
            fi
        fi
        
        echo "INSERT INTO \`costs\` (\`date\`, \`user\`, \`kind\`, \`max_mode\`, \`model\`, \`tokens\`, \`cost\`, \`source_file\`) VALUES ('$mysql_date', '$user', '$kind', '$max_mode', '$model', '$tokens', $cost_value, '$filename');" >> "$temp_sql"
    done
    
    # Dodanie rekordu do processed_files
    local records_count=$(tail -n +2 "$csv_file" | wc -l)
    echo "INSERT INTO \`processed_files\` (\`filename\`, \`records_count\`) VALUES ('$filename', $records_count);" >> "$temp_sql"
    echo "COMMIT;" >> "$temp_sql"
    
    # Wykonanie importu
    if mysql --silent -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" < "$temp_sql" 2>/dev/null; then
        echo -e "${GREEN}✅ Zaimportowano $records_count rekordów z pliku $filename${NC}"
        rm -f "$temp_sql"
    else
        echo -e "${RED}❌ Błąd podczas importowania pliku $filename${NC}"
        rm -f "$temp_sql"
        return 1
    fi
}

# Funkcja do pobierania danych z Google Sheets
import_google_sheets() {
    echo -e "${YELLOW}📊 Pobieranie danych z Google Sheets...${NC}"
    
    # Sprawdzenie czy curl jest dostępny
    if ! command -v curl &> /dev/null; then
        echo -e "${RED}❌ Błąd: curl nie jest zainstalowany${NC}"
        return 1
    fi
    
    # Pobieranie danych z Google Sheets (format CSV)
    local csv_url="https://docs.google.com/spreadsheets/d/$GOOGLE_SHEETS_ID/export?format=csv&gid=0"
    local temp_csv="/tmp/finanses_$(date +%s).csv"
    
    if ! curl --silent --fail "$csv_url" > "$temp_csv"; then
        echo -e "${RED}❌ Błąd: Nie można pobrać danych z Google Sheets${NC}"
        return 1
    fi
    
    echo -e "${GREEN}✅ Pobrano dane z Google Sheets${NC}"
    
    # Oznaczenie starych rekordów jako archived
    mysql --silent -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "USE \`$DB_NAME\`; UPDATE finanses SET archived = TRUE, archived_date = NOW() WHERE archived = FALSE;" 2>/dev/null
    
    # Przetwarzanie CSV
    local line_number=0
    local temp_sql="/tmp/finanses_import_$(date +%s).sql"
    
    echo "USE \`$DB_NAME\`;" > "$temp_sql"
    echo "START TRANSACTION;" >> "$temp_sql"
    
    # Proste parsowanie CSV z użyciem cut i sed
    local line_number=0
    while IFS= read -r line; do
        ((line_number++))
        
        # Pomijamy nagłówek
        if [ $line_number -eq 1 ]; then
            continue
        fi
        
        # Pomijamy wiersz z sumą
        if [[ "$line" == *"suma"* ]] || [[ "$line" == *"Suma"* ]]; then
            continue
        fi
        
        # Sprawdzanie czy to nie jest pusty wiersz
        if [ -z "$line" ]; then
            continue
        fi
        
        # Konwersja wartości na liczby
        convert_value() {
            local value="$1"
            # Usuwanie znaków $ i spacji
            value=$(echo "$value" | sed 's/\$//g' | sed 's/,//g' | sed 's/ //g')
            # Sprawdzanie czy to liczba
            if [[ "$value" =~ ^[0-9]+\.?[0-9]*$ ]]; then
                echo "$value"
            else
                echo "NULL"
            fi
        }
        
        # Wyciągnięcie pierwszych 10 kolumn jako przykład
        local miesiac=$(echo "$line" | cut -d',' -f1)
        local ostatni_dzien=$(convert_value "$(echo "$line" | cut -d',' -f2)")
        local dni_robocze=$(convert_value "$(echo "$line" | cut -d',' -f3)")
        local urlop_swieta=$(convert_value "$(echo "$line" | cut -d',' -f4)")
        local nadgodziny=$(convert_value "$(echo "$line" | cut -d',' -f5)")
        local stawka_netto=$(convert_value "$(echo "$line" | cut -d',' -f6)")
        local pensja_brutto=$(convert_value "$(echo "$line" | cut -d',' -f7)")
        local pit=$(convert_value "$(echo "$line" | cut -d',' -f8)")
        local vat=$(convert_value "$(echo "$line" | cut -d',' -f9)")
        local zus=$(convert_value "$(echo "$line" | cut -d',' -f10)")
        local pensja=$(convert_value "$(echo "$line" | cut -d',' -f11)")
        

        
        # Konwersja daty
        local mysql_date=""
        if [[ "$miesiac" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
            mysql_date="$miesiac"
        else
            mysql_date="NULL"
        fi
        
        # Escape znaków specjalnych
        miesiac=$(echo "$miesiac" | sed "s/'/\\\'/g")
        
        echo "INSERT INTO \`finanses\` (\`miesiac\`, \`ostatni_dzien\`, \`dni_robocze\`, \`urlop_swieta\`, \`nadgodziny\`, \`stawka_netto\`, \`pensja_brutto\`, \`pit\`, \`vat\`, \`zus\`, \`pensja\`) VALUES ('$mysql_date', $ostatni_dzien, $dni_robocze, $urlop_swieta, $nadgodziny, $stawka_netto, $pensja_brutto, $pit, $vat, $zus, $pensja);" >> "$temp_sql"
    done < "$temp_csv"
        

    
    echo "COMMIT;" >> "$temp_sql"
    
    # Wykonanie importu
    if mysql --silent -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" < "$temp_sql" 2>/dev/null; then
        local imported_count=$(grep -c "INSERT INTO" "$temp_sql" 2>/dev/null || echo "0")
        echo -e "${GREEN}✅ Zaimportowano $imported_count rekordów z Google Sheets${NC}"
        rm -f "$temp_csv" "$temp_sql"
    else
        echo -e "${RED}❌ Błąd podczas importowania danych z Google Sheets${NC}"
        rm -f "$temp_csv" "$temp_sql"
        return 1
    fi
}

# Funkcja do wyświetlenia statystyk
show_statistics() {
    echo -e "${YELLOW}📊 Statystyki bazy danych:${NC}"
    
    local total_costs=$(mysql --silent -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -s -N -e "USE \`$DB_NAME\`; SELECT COUNT(*) FROM costs;" 2>/dev/null)
    local total_finanses=$(mysql --silent -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -s -N -e "USE \`$DB_NAME\`; SELECT COUNT(*) FROM finanses WHERE archived = FALSE;" 2>/dev/null)
    local processed_files=$(mysql --silent -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -s -N -e "USE \`$DB_NAME\`; SELECT COUNT(*) FROM processed_files;" 2>/dev/null)
    
    echo -e "${GREEN}📈 Łącznie rekordów costs: $total_costs${NC}"
    echo -e "${GREEN}📈 Łącznie rekordów finanses: $total_finanses${NC}"
    echo -e "${GREEN}📁 Przetworzonych plików: $processed_files${NC}"
    
    echo -e "${YELLOW}📋 Lista przetworzonych plików:${NC}"
    mysql --silent -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "USE \`$DB_NAME\`; SELECT filename, records_count, processed_at FROM processed_files ORDER BY processed_at;" 2>/dev/null || echo "Brak danych"
}

# Główna logika skryptu
main() {
    echo -e "${GREEN}🚀 Rozpoczynam import plików CSV z kosztami Cursor${NC}"
    
    # Sprawdzenie połączenia z MySQL
    check_mysql
    
    # Tworzenie bazy danych i tabel
    create_database_and_tables
    
    # Sprawdzenie czy katalog z plikami CSV istnieje
    if [ ! -d "$CSV_DIR" ]; then
        echo -e "${RED}❌ Katalog $CSV_DIR nie istnieje${NC}"
        exit 1
    fi
    
    # Znalezienie wszystkich plików CSV
    local csv_files=($(find "$CSV_DIR" -name "*.csv" -type f | sort))
    
    if [ ${#csv_files[@]} -eq 0 ]; then
        echo -e "${YELLOW}⚠️  Nie znaleziono plików CSV w katalogu $CSV_DIR${NC}"
        exit 0
    fi
    
    echo -e "${GREEN}📁 Znaleziono ${#csv_files[@]} plików CSV do przetworzenia${NC}"
    
    # Import każdego pliku CSV
    local processed_count=0
    local skipped_count=0
    
    for csv_file in "${csv_files[@]}"; do
        if import_csv_file "$csv_file"; then
            ((processed_count++))
        else
            ((skipped_count++))
        fi
    done
    
    echo -e "${GREEN}✅ Import zakończony${NC}"
    echo -e "${GREEN}📊 Przetworzono: $processed_count plików${NC}"
    if [ $skipped_count -gt 0 ]; then
        echo -e "${YELLOW}⏭️  Pominięto: $skipped_count plików${NC}"
    fi
    
    # Import danych z Google Sheets
    echo -e "${YELLOW}📊 Rozpoczynam import danych z Google Sheets...${NC}"
    import_google_sheets
    
    # Wyświetlenie statystyk
    show_statistics
    
    echo -e "${GREEN}🎉 Skrypt zakończony pomyślnie!${NC}"
}

# Uruchomienie głównej funkcji
main "$@" 