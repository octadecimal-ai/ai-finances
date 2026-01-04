#!/bin/bash

# Skrypt do importu plików CSV z transakcjami
# Autor: Piotr Adamczyk
# Usage: ./import.sh [--account=revoult|velo-priv|velo-company]

# Ustal ścieżkę względem głównego katalogu projektu
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR"

# Kolory do czytelności
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Plik konfiguracyjny do śledzenia zaimportowanych plików
IMPORT_TRACKER_FILE="$PROJECT_ROOT/storage/app/import_tracker.json"

# Funkcja pomocy
show_help() {
    echo -e "${CYAN}📥 Skrypt importu plików CSV${NC}"
    echo ""
    echo -e "${YELLOW}Użycie:${NC}"
    echo "  ./import.sh [opcje]"
    echo ""
    echo -e "${YELLOW}Opcje:${NC}"
    echo -e "  ${GREEN}--account=revoult${NC}      - Import z katalogu Revolut (REVOULT_DIR)"
    echo -e "  ${GREEN}--account=velo-priv${NC}    - Import z katalogu Velo Private (VELO_PRIV_DIR) - TODO"
    echo -e "  ${GREEN}--account=velo-company${NC} - Import z katalogu Velo Company (VELO_COMPANY_DIR) - TODO"
    echo -e "  ${GREEN}--invoices=cursor${NC}     - Import faktur PDF z katalogu Cursor (CURSOR_INVOICES_DIR)"
    echo -e "  ${GREEN}--invoices=anthropic${NC}  - Import faktur PDF z katalogu Anthropic (ANTHROPIC_INVOICES_DIR)"
    echo -e "  ${GREEN}--invoices=google${NC}     - Import faktur PDF z katalogu Google (GOOGLE_INVOICES_DIR)"
    echo -e "  ${GREEN}--invoices=openai${NC}     - Import faktur PDF z katalogu OpenAI (OPENAI_INVOICES_DIR)"
    echo -e "  ${GREEN}--invoices=ovh${NC}       - Import faktur CSV z katalogu OVH (OVH_INVOICES_DIR)"
    echo -e "  ${GREEN}--invoices=all${NC}       - Import faktur ze wszystkich katalogów faktur"
    echo -e "  ${GREEN}--exchange-rates${NC}     - Import kursów walut (domyślnie bieżący rok)"
    echo -e "  ${GREEN}--exchange-rates=2025${NC} - Import kursów walut dla konkretnego roku"
    echo -e "  ${GREEN}--exchange-rates=all${NC} - Import kursów walut ze wszystkich lat"
    echo -e "  ${GREEN}--match-transactions${NC} - Dopasuj faktury do transakcji"
    echo -e "  ${GREEN}--wfirma=2025-12${NC}   - Synchronizacja danych z wFirma dla miesiąca (format: YYYY-MM)"
    echo -e "  ${GREEN}--wfirma=2025${NC}      - Synchronizacja danych z wFirma dla całego roku (format: YYYY)"
    echo ""
    echo -e "${YELLOW}Przykłady:${NC}"
    echo "  ./import.sh --account=revoult"
    echo "  ./import.sh --invoices=cursor"
    echo "  ./import.sh --invoices=anthropic"
    echo "  ./import.sh --invoices=google"
    echo "  ./import.sh --invoices=openai"
    echo "  ./import.sh --invoices=ovh"
    echo "  ./import.sh --invoices=all"
    echo "  ./import.sh --exchange-rates"
    echo "  ./import.sh --exchange-rates=2025"
    echo "  ./import.sh --exchange-rates=all"
    echo "  ./import.sh --match-transactions"
    echo "  ./import.sh --wfirma=2025-12"
    echo "  ./import.sh --wfirma=2025"
    echo ""
    echo -e "${YELLOW}Mechanizm śledzenia:${NC}"
    echo "  Skrypt zapamiętuje zaimportowane pliki w pliku:"
    echo "  ${PURPLE}$IMPORT_TRACKER_FILE${NC}"
    echo ""
    echo "  Pliki są śledzone na podstawie:"
    echo "  - Nazwy pliku"
    echo "  - Rozmiaru pliku"
    echo "  - Daty modyfikacji"
    echo ""
    echo "  Jeśli plik został już zaimportowany i się nie zmienił,"
    echo "  zostanie pominięty. Jeśli się zmienił, zostanie zaimportowany"
    echo "  ponownie (bez duplikatów)."
}

# Funkcja do ładowania zmiennych z .env
load_env() {
    if [ ! -f "$PROJECT_ROOT/.env" ]; then
        echo -e "${RED}❌ Nie znaleziono pliku .env${NC}"
        exit 1
    fi

    # Ładuj zmienne z .env
    export $(grep -v '^#' "$PROJECT_ROOT/.env" | grep -E '^(REVOULT_DIR|VELO_PRIV_DIR|VELO_COMPANY_DIR|CURSOR_INVOICES_DIR|ANTHROPIC_INVOICES_DIR|GOOGLE_INVOICES_DIR|OPENAI_INVOICES_DIR|OVH_INVOICES_DIR|EXCHANGE_RATES_DIR)=' | xargs)
}

# Funkcja do inicjalizacji pliku tracker
init_tracker() {
    if [ ! -f "$IMPORT_TRACKER_FILE" ]; then
        echo "{}" > "$IMPORT_TRACKER_FILE"
    fi
}

# Funkcja do sprawdzania czy plik został już zaimportowany
is_file_imported() {
    local file_path="$1"
    local file_name=$(basename "$file_path")
    local file_size=$(stat -f%z "$file_path" 2>/dev/null || stat -c%s "$file_path" 2>/dev/null)
    local file_mtime=$(stat -f%m "$file_path" 2>/dev/null || stat -c%Y "$file_path" 2>/dev/null)

    if [ ! -f "$IMPORT_TRACKER_FILE" ]; then
        return 1
    fi

    # Sprawdź w pliku JSON
    if command -v jq &> /dev/null; then
        local tracked=$(jq -r --arg name "$file_name" --arg size "$file_size" --arg mtime "$file_mtime" \
            '.[$name] // empty | select(.size == $size and .mtime == $mtime)' \
            "$IMPORT_TRACKER_FILE" 2>/dev/null)

        if [ -n "$tracked" ]; then
            return 0  # Plik został już zaimportowany
        fi
    else
        # Fallback - użyj PHP do sprawdzenia
        local tracked=$(php -r "
            \$data = json_decode(file_get_contents('$IMPORT_TRACKER_FILE'), true) ?: [];
            if (isset(\$data['$file_name']) && 
                \$data['$file_name']['size'] == '$file_size' && 
                \$data['$file_name']['mtime'] == '$file_mtime') {
                echo '1';
            }
        " 2>/dev/null)

        if [ "$tracked" = "1" ]; then
            return 0  # Plik został już zaimportowany
        fi
    fi

    return 1  # Plik nie został zaimportowany lub się zmienił
}

# Funkcja do zapisania informacji o zaimportowanym pliku
mark_file_imported() {
    local file_path="$1"
    local file_name=$(basename "$file_path")
    local file_size=$(stat -f%z "$file_path" 2>/dev/null || stat -c%s "$file_path" 2>/dev/null)
    local file_mtime=$(stat -f%m "$file_path" 2>/dev/null || stat -c%Y "$file_path" 2>/dev/null)
    local imported_date=$(date -u +"%Y-%m-%d %H:%M:%S")

    # Aktualizuj plik JSON
    if command -v jq &> /dev/null; then
        local temp_file=$(mktemp)
        jq --arg name "$file_name" \
           --arg size "$file_size" \
           --arg mtime "$file_mtime" \
           --arg path "$file_path" \
           --arg date "$imported_date" \
           '.[$name] = {
               "size": $size,
               "mtime": $mtime,
               "path": $path,
               "imported_at": $date
           }' "$IMPORT_TRACKER_FILE" > "$temp_file" 2>/dev/null

        if [ $? -eq 0 ]; then
            mv "$temp_file" "$IMPORT_TRACKER_FILE"
        else
            # Fallback - użyj PHP do aktualizacji JSON
            php -r "
                \$data = json_decode(file_get_contents('$IMPORT_TRACKER_FILE'), true) ?: [];
                \$data['$file_name'] = [
                    'size' => '$file_size',
                    'mtime' => '$file_mtime',
                    'path' => '$file_path',
                    'imported_at' => '$imported_date'
                ];
                file_put_contents('$IMPORT_TRACKER_FILE', json_encode(\$data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            "
        fi
    else
        # Fallback - użyj PHP do aktualizacji JSON
        php -r "
            \$data = json_decode(file_get_contents('$IMPORT_TRACKER_FILE'), true) ?: [];
            \$data['$file_name'] = [
                'size' => '$file_size',
                'mtime' => '$file_mtime',
                'path' => '$file_path',
                'imported_at' => '$imported_date'
            ];
            file_put_contents('$IMPORT_TRACKER_FILE', json_encode(\$data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        "
    fi
}

# Funkcja do importu plików z katalogu
import_from_directory() {
    local account_type="$1"
    local directory="$2"
    local format="$3"

    if [ -z "$directory" ] || [ ! -d "$directory" ]; then
        echo -e "${RED}❌ Katalog nie istnieje lub nie jest ustawiony: ${directory}${NC}"
        return 1
    fi

    echo -e "${CYAN}📂 Skanowanie katalogu: ${directory}${NC}"
    echo ""

    local imported_count=0
    local skipped_count=0
    local error_count=0

    # Znajdź wszystkie pliki CSV
    while IFS= read -r -d '' file; do
        local file_name=$(basename "$file")
        
        echo -e "${BLUE}📄 Plik: ${file_name}${NC}"
        
        # Sprawdź czy plik został już zaimportowany
        if is_file_imported "$file"; then
            echo -e "  ${YELLOW}⏭️  Pominięto (już zaimportowany)${NC}"
            ((skipped_count++))
            continue
        fi

        # Importuj plik
        echo -e "  ${CYAN}📥 Importowanie...${NC}"
        
        cd "$PROJECT_ROOT" || exit 1
        
        php artisan import:csv-file "$file" --format="$format" --user-id=1 2>&1 | while IFS= read -r line; do
            if [[ "$line" == *"✅"* ]]; then
                echo -e "  ${GREEN}$line${NC}"
            elif [[ "$line" == *"❌"* ]] || [[ "$line" == *"Błąd"* ]]; then
                echo -e "  ${RED}$line${NC}"
            elif [[ "$line" == *"⚠️"* ]]; then
                echo -e "  ${YELLOW}$line${NC}"
            else
                echo -e "  $line"
            fi
        done

        local import_result=$?

        if [ $import_result -eq 0 ]; then
            # Oznacz plik jako zaimportowany
            mark_file_imported "$file"
            echo -e "  ${GREEN}✅ Zapisano w trackerze${NC}"
            ((imported_count++))
        else
            echo -e "  ${RED}❌ Błąd importu${NC}"
            ((error_count++))
        fi

        echo ""

    done < <(find "$directory" -maxdepth 1 -type f -name "*.csv" -print0 | sort -z)

    # Podsumowanie
    echo -e "${CYAN}📊 Podsumowanie:${NC}"
    echo -e "  ${GREEN}✅ Zaimportowano: ${imported_count}${NC}"
    echo -e "  ${YELLOW}⏭️  Pominięto: ${skipped_count}${NC}"
    echo -e "  ${RED}❌ Błędy: ${error_count}${NC}"
    echo ""

    return 0
}

# Funkcja do importu faktur z katalogu
import_invoices_from_directory() {
    local source_type="$1"
    local directory="$2"

    if [ -z "$directory" ] || [ ! -d "$directory" ]; then
        echo -e "${RED}❌ Katalog nie istnieje lub nie jest ustawiony: ${directory}${NC}"
        return 1
    fi

    echo -e "${CYAN}📂 Skanowanie katalogu faktur: ${directory}${NC}"
    echo ""

    local imported_count=0
    local skipped_count=0
    local error_count=0

    # Znajdź wszystkie pliki PDF lub CSV (dla OVH)
    local file_pattern="*.pdf"
    if [ "$source_type" = "ovh" ]; then
        file_pattern="*.csv"
    fi
    
    while IFS= read -r -d '' file; do
        local file_name=$(basename "$file")
        
        echo -e "${BLUE}📄 Plik: ${file_name}${NC}"
        
        # Sprawdź czy plik został już zaimportowany
        if is_file_imported "$file"; then
            echo -e "  ${YELLOW}⏭️  Pominięto (już zaimportowany)${NC}"
            ((skipped_count++))
            continue
        fi

        # Importuj fakturę
        echo -e "  ${CYAN}📥 Importowanie...${NC}"
        
        cd "$PROJECT_ROOT" || exit 1
        
        php artisan import:invoices "$file" --user-id=1 --source-type="$source_type" 2>&1 | while IFS= read -r line; do
            if [[ "$line" == *"✅"* ]]; then
                echo -e "  ${GREEN}$line${NC}"
            elif [[ "$line" == *"❌"* ]] || [[ "$line" == *"Błąd"* ]]; then
                echo -e "  ${RED}$line${NC}"
            elif [[ "$line" == *"⚠️"* ]]; then
                echo -e "  ${YELLOW}$line${NC}"
            else
                echo -e "  $line"
            fi
        done

        local import_result=$?

        if [ $import_result -eq 0 ]; then
            # Oznacz plik jako zaimportowany
            mark_file_imported "$file"
            echo -e "  ${GREEN}✅ Zapisano w trackerze${NC}"
            ((imported_count++))
        else
            echo -e "  ${RED}❌ Błąd importu${NC}"
            ((error_count++))
        fi

        echo ""

    done < <(find "$directory" -maxdepth 1 -type f \( -name "*.pdf" -o -name "*.csv" \) -print0 | sort -z)

    # Podsumowanie
    echo -e "${CYAN}📊 Podsumowanie:${NC}"
    echo -e "  ${GREEN}✅ Zaimportowano: ${imported_count}${NC}"
    echo -e "  ${YELLOW}⏭️  Pominięto: ${skipped_count}${NC}"
    echo -e "  ${RED}❌ Błędy: ${error_count}${NC}"
    echo ""

    return 0
}

# Główna logika
main() {
    # Sprawdź czy jesteśmy w katalogu projektu Laravel
    if [ ! -f "$PROJECT_ROOT/artisan" ]; then
        echo -e "${RED}❌ Nie znaleziono pliku artisan w katalogu: $PROJECT_ROOT${NC}"
        echo -e "${YELLOW}💡 Upewnij się, że uruchamiasz skrypt z katalogu projektu Laravel${NC}"
        exit 1
    fi

    # Jeśli brak parametrów, wyświetl help
    if [ $# -eq 0 ]; then
        show_help
        exit 0
    fi

    # Parsuj argumenty
    local account_type=""
    local invoices_type=""
    local exchange_rates_year=""
    local match_transactions=false
    local wfirma_period=""
    for arg in "$@"; do
        case $arg in
            --account=*)
                account_type="${arg#*=}"
                ;;
            --invoices=*)
                invoices_type="${arg#*=}"
                ;;
            --exchange-rates)
                exchange_rates_year="CURRENT_YEAR"  # Oznaczenie dla bieżącego roku
                ;;
            --exchange-rates=*)
                exchange_rates_year="${arg#*=}"
                ;;
            --match-transactions)
                match_transactions=true
                ;;
            --wfirma=*)
                wfirma_period="${arg#*=}"
                ;;
            --help|-h)
                show_help
                exit 0
                ;;
            *)
                echo -e "${RED}❌ Nieznany parametr: $arg${NC}"
                echo ""
                show_help
                exit 1
                ;;
        esac
    done

    # Sprawdź czy podano typ konta, faktur, kursów walut, wFirma lub dopasowanie transakcji
    if [ -z "$account_type" ] && [ -z "$invoices_type" ] && [ -z "$exchange_rates_year" ] && [ "$match_transactions" = false ] && [ -z "$wfirma_period" ]; then
        echo -e "${RED}❌ Musisz podać typ: --account=revoult, --invoices=cursor|anthropic|google|openai|all, --exchange-rates, --wfirma=YYYY-MM|YYYY lub --match-transactions${NC}"
        echo ""
        show_help
        exit 1
    fi

    # Załaduj zmienne z .env
    load_env

    # Inicjalizuj tracker
    init_tracker

    # Sprawdź czy jq jest dostępne (opcjonalne, PHP jest używany jako fallback)
    if ! command -v jq &> /dev/null; then
        echo -e "${BLUE}ℹ️  jq nie jest zainstalowane. Użyję PHP do obsługi tracker.${NC}"
        echo ""
    fi

    # Importuj w zależności od typu
    if [ -n "$wfirma_period" ]; then
        # Synchronizacja danych z wFirma
        echo -e "${CYAN}🔄 Synchronizacja danych z wFirma${NC}"
        echo ""
        
        cd "$PROJECT_ROOT" || exit 1
        
        php artisan wfirma:sync "$wfirma_period" --user-id=1 2>&1 | while IFS= read -r line; do
            if [[ "$line" == *"✅"* ]] || [[ "$line" == *"Zsynchronizowano"* ]] || [[ "$line" == *"zakończona"* ]]; then
                echo -e "${GREEN}$line${NC}"
            elif [[ "$line" == *"❌"* ]] || [[ "$line" == *"Błąd"* ]] || [[ "$line" == *"Error"* ]]; then
                echo -e "${RED}$line${NC}"
            elif [[ "$line" == *"⚠️"* ]] || [[ "$line" == *"Warning"* ]]; then
                echo -e "${YELLOW}$line${NC}"
            else
                echo -e "$line"
            fi
        done
        
        echo ""
        echo -e "${GREEN}✅ Synchronizacja zakończona${NC}"
        echo ""
    elif [ "$match_transactions" = true ]; then
        # Dopasuj faktury do transakcji
        echo -e "${CYAN}🔗 Dopasowywanie faktur do transakcji${NC}"
        echo ""
        
        cd "$PROJECT_ROOT" || exit 1
        
        php artisan invoices:match-transactions 2>&1 | while IFS= read -r line; do
            if [[ "$line" == *"✅"* ]] || [[ "$line" == *"Dopasowano"* ]]; then
                echo -e "${GREEN}$line${NC}"
            elif [[ "$line" == *"❌"* ]] || [[ "$line" == *"Błąd"* ]] || [[ "$line" == *"Error"* ]]; then
                echo -e "${RED}$line${NC}"
            elif [[ "$line" == *"⚠️"* ]] || [[ "$line" == *"Warning"* ]]; then
                echo -e "${YELLOW}$line${NC}"
            else
                echo -e "$line"
            fi
        done
        
        echo ""
        echo -e "${GREEN}✅ Dopasowywanie zakończone${NC}"
        echo ""
    elif [ -n "$exchange_rates_year" ]; then
        # Import kursów walut
        if [ -z "$EXCHANGE_RATES_DIR" ]; then
            echo -e "${RED}❌ EXCHANGE_RATES_DIR nie jest ustawione w .env${NC}"
            exit 1
        fi
        
        cd "$PROJECT_ROOT" || exit 1
        
        # Jeśli exchange_rates_year to CURRENT_YEAR (--exchange-rates bez wartości), użyj bieżącego roku
        if [ "$exchange_rates_year" = "CURRENT_YEAR" ]; then
            exchange_rates_year=$(date +%Y)
        fi
        
        echo -e "${CYAN}💱 Import kursów walut${NC}"
        echo ""
        
        php artisan import:exchange-rates "$exchange_rates_year" 2>&1 | while IFS= read -r line; do
            if [[ "$line" == *"✅"* ]]; then
                echo -e "${GREEN}$line${NC}"
            elif [[ "$line" == *"❌"* ]] || [[ "$line" == *"Błąd"* ]]; then
                echo -e "${RED}$line${NC}"
            elif [[ "$line" == *"⚠️"* ]]; then
                echo -e "${YELLOW}$line${NC}"
            else
                echo -e "$line"
            fi
        done
        
    elif [ -n "$invoices_type" ]; then
        # Import faktur
        case "$invoices_type" in
            cursor)
                if [ -z "$CURSOR_INVOICES_DIR" ]; then
                    echo -e "${RED}❌ CURSOR_INVOICES_DIR nie jest ustawione w .env${NC}"
                    exit 1
                fi
                import_invoices_from_directory "cursor" "$CURSOR_INVOICES_DIR"
                ;;
            anthropic)
                if [ -z "$ANTHROPIC_INVOICES_DIR" ]; then
                    echo -e "${RED}❌ ANTHROPIC_INVOICES_DIR nie jest ustawione w .env${NC}"
                    exit 1
                fi
                import_invoices_from_directory "anthropic" "$ANTHROPIC_INVOICES_DIR"
                ;;
            google)
                if [ -z "$GOOGLE_INVOICES_DIR" ]; then
                    echo -e "${RED}❌ GOOGLE_INVOICES_DIR nie jest ustawione w .env${NC}"
                    exit 1
                fi
                import_invoices_from_directory "google" "$GOOGLE_INVOICES_DIR"
                ;;
            openai)
                if [ -z "$OPENAI_INVOICES_DIR" ]; then
                    echo -e "${RED}❌ OPENAI_INVOICES_DIR nie jest ustawione w .env${NC}"
                    exit 1
                fi
                import_invoices_from_directory "openai" "$OPENAI_INVOICES_DIR"
                ;;
            ovh)
                if [ -z "$OVH_INVOICES_DIR" ]; then
                    echo -e "${RED}❌ OVH_INVOICES_DIR nie jest ustawione w .env${NC}"
                    exit 1
                fi
                import_invoices_from_directory "ovh" "$OVH_INVOICES_DIR"
                ;;
            all)
                echo -e "${CYAN}📦 Import faktur ze wszystkich katalogów${NC}"
                echo ""
                
                local total_imported=0
                local total_skipped=0
                local total_errors=0
                
                # Import z Cursor
                if [ -n "$CURSOR_INVOICES_DIR" ] && [ -d "$CURSOR_INVOICES_DIR" ]; then
                    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
                    echo -e "${BLUE}📁 Katalog: Cursor${NC}"
                    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
                    import_invoices_from_directory "cursor" "$CURSOR_INVOICES_DIR"
                    # Funkcja zwraca kod, ale nie możemy łatwo przechwycić liczników
                    # Więc po prostu kontynuujemy
                else
                    echo -e "${YELLOW}⚠️  Pominięto Cursor (katalog nie istnieje lub nie jest ustawiony)${NC}"
                    echo ""
                fi
                
                # Import z Anthropic
                if [ -n "$ANTHROPIC_INVOICES_DIR" ] && [ -d "$ANTHROPIC_INVOICES_DIR" ]; then
                    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
                    echo -e "${BLUE}📁 Katalog: Anthropic${NC}"
                    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
                    import_invoices_from_directory "anthropic" "$ANTHROPIC_INVOICES_DIR"
                else
                    echo -e "${YELLOW}⚠️  Pominięto Anthropic (katalog nie istnieje lub nie jest ustawiony)${NC}"
                    echo ""
                fi
                
                # Import z Google
                if [ -n "$GOOGLE_INVOICES_DIR" ] && [ -d "$GOOGLE_INVOICES_DIR" ]; then
                    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
                    echo -e "${BLUE}📁 Katalog: Google${NC}"
                    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
                    import_invoices_from_directory "google" "$GOOGLE_INVOICES_DIR"
                else
                    echo -e "${YELLOW}⚠️  Pominięto Google (katalog nie istnieje lub nie jest ustawiony)${NC}"
                    echo ""
                fi
                
                # Import z OpenAI
                if [ -n "$OPENAI_INVOICES_DIR" ] && [ -d "$OPENAI_INVOICES_DIR" ]; then
                    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
                    echo -e "${BLUE}📁 Katalog: OpenAI${NC}"
                    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
                    import_invoices_from_directory "openai" "$OPENAI_INVOICES_DIR"
                else
                    echo -e "${YELLOW}⚠️  Pominięto OpenAI (katalog nie istnieje lub nie jest ustawiony)${NC}"
                    echo ""
                fi
                
                # Import z OVH
                if [ -n "$OVH_INVOICES_DIR" ] && [ -d "$OVH_INVOICES_DIR" ]; then
                    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
                    echo -e "${BLUE}📁 Katalog: OVH${NC}"
                    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
                    import_invoices_from_directory "ovh" "$OVH_INVOICES_DIR"
                else
                    echo -e "${YELLOW}⚠️  Pominięto OVH (katalog nie istnieje lub nie jest ustawiony)${NC}"
                    echo ""
                fi
                
                echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
                echo -e "${CYAN}📊 Podsumowanie wszystkich importów${NC}"
                echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
                echo -e "${GREEN}✅ Import faktur ze wszystkich katalogów zakończony${NC}"
                echo ""
                ;;
            *)
                echo -e "${RED}❌ Nieznany typ faktur: $invoices_type${NC}"
                echo -e "${YELLOW}Dostępne typy: cursor, anthropic, google, openai, ovh, all${NC}"
                exit 1
                ;;
        esac
    elif [ -n "$account_type" ]; then
        # Import transakcji
        case "$account_type" in
            revoult)
                if [ -z "$REVOULT_DIR" ]; then
                    echo -e "${RED}❌ REVOULT_DIR nie jest ustawione w .env${NC}"
                    exit 1
                fi
                import_from_directory "revoult" "$REVOULT_DIR" "revolut"
                ;;
            velo-priv)
                echo -e "${YELLOW}⚠️  Import z Velo Private nie jest jeszcze zaimplementowany${NC}"
                exit 1
                ;;
            velo-company)
                echo -e "${YELLOW}⚠️  Import z Velo Company nie jest jeszcze zaimplementowany${NC}"
                exit 1
                ;;
            *)
                echo -e "${RED}❌ Nieznany typ konta: $account_type${NC}"
                echo -e "${YELLOW}Dostępne typy: revoult, velo-priv, velo-company${NC}"
                exit 1
                ;;
        esac
    fi
}

# Uruchom skrypt
main "$@"

