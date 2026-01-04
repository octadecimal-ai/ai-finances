#!/bin/bash

# Skrypt do sprawdzania logów Laravel
# Autor: Piotr Adamczyk
# Usage: ./check_logs.sh [opcja]

# Ustal ścieżkę względem głównego katalogu projektu
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOGS_DIR="$PROJECT_ROOT/storage/logs"
MAIN_LOG="$LOGS_DIR/laravel.log"

# Kolory do czytelności
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Funkcja pomocy
show_help() {
    echo -e "${CYAN}🔍 Skrypt do sprawdzania logów Laravel${NC}"
    echo ""
    echo -e "${YELLOW}Użycie:${NC}"
    echo "  ./check_logs.sh [opcje...] [tail]"
    echo ""
    echo -e "${YELLOW}Opcje podstawowe:${NC}"
    echo -e "  ${GREEN}tail${NC}       - Ostatnie 50 linii z głównego loga"
    echo -e "  ${GREEN}follow${NC}     - Śledzenie logów na żywo (tail -f)"
    echo -e "  ${GREEN}errors${NC}     - Tylko błędy (ERROR)"
    echo -e "  ${GREEN}today${NC}      - Logi z dzisiaj"
    echo -e "  ${GREEN}size${NC}       - Rozmiary plików logów"
    echo -e "  ${GREEN}list${NC}       - Lista wszystkich plików logów"
    echo -e "  ${GREEN}clear${NC}      - Wyczyść logi (z potwierdzeniem)"
    echo -e "  ${GREEN}search${NC}     - Szukaj frazy w logach"
    echo -e "  ${GREEN}campaigns${NC}  - Logi kampanii"
    echo -e "  ${GREEN}help${NC}       - Ta pomoc"
    echo ""
    echo -e "${YELLOW}Kombinowanie opcji:${NC}"
    echo -e "  ${PURPLE}Można łączyć filtry:${NC} errors, today, search"
    echo -e "  ${PURPLE}Modyfikator 'tail':${NC} dodaje śledzenie na żywo"
    echo ""
    echo -e "${YELLOW}Przykłady:${NC}"
    echo "  ./check_logs.sh tail                    # Ostatnie logi"
    echo "  ./check_logs.sh errors                  # Błędy z przeszłości"
    echo "  ./check_logs.sh errors tail             # Błędy na żywo"
    echo "  ./check_logs.sh today errors            # Dzisiejsze błędy"
    echo "  ./check_logs.sh today errors tail       # Dzisiejsze błędy na żywo"
    echo "  ./check_logs.sh search \"balance\" today tail # Szukaj 'balance' w dzisiejszych logach na żywo"
}

# Sprawdź czy katalog logów istnieje
check_logs_dir() {
    if [ ! -d "$LOGS_DIR" ]; then
        echo -e "${RED}❌ Katalog logów nie istnieje: $LOGS_DIR${NC}"
        exit 1
    fi
}

# Lista plików logów
list_logs() {
    echo -e "${CYAN}📋 Pliki logów w $LOGS_DIR:${NC}"
    echo ""
    ls -la "$LOGS_DIR/" | grep -E "\.(log)$" | while read -r line; do
        filename=$(echo "$line" | awk '{print $NF}')
        size=$(echo "$line" | awk '{print $5}')
        date=$(echo "$line" | awk '{print $6 " " $7 " " $8}')
        echo -e "  ${GREEN}$filename${NC} (${YELLOW}$size bytes${NC}) - $date"
    done
}

# Rozmiary plików
show_sizes() {
    echo -e "${CYAN}📊 Rozmiary plików logów:${NC}"
    echo ""
    du -h "$LOGS_DIR"/*.log 2>/dev/null | sort -hr | while read -r size file; do
        filename=$(basename "$file")
        echo -e "  ${YELLOW}$size${NC} - ${GREEN}$filename${NC}"
    done
}

# Ostatnie logi
show_tail() {
    local lines=${1:-50}
    echo -e "${CYAN}📝 Ostatnie $lines linii z $MAIN_LOG:${NC}"
    echo ""
    if [ -f "$MAIN_LOG" ]; then
        tail -n "$lines" "$MAIN_LOG" | sed 's/ERROR/\x1b[31mERROR\x1b[0m/g' | sed 's/WARNING/\x1b[33mWARNING\x1b[0m/g' | sed 's/INFO/\x1b[36mINFO\x1b[0m/g'
    else
        echo -e "${RED}❌ Plik $MAIN_LOG nie istnieje${NC}"
    fi
}

# Śledzenie na żywo
follow_logs() {
    echo -e "${CYAN}👁️  Śledzenie logów na żywo (Ctrl+C aby zatrzymać):${NC}"
    echo ""
    if [ -f "$MAIN_LOG" ]; then
        tail -f "$MAIN_LOG" | sed 's/ERROR/\x1b[31mERROR\x1b[0m/g' | sed 's/WARNING/\x1b[33mWARNING\x1b[0m/g' | sed 's/INFO/\x1b[36mINFO\x1b[0m/g'
    else
        echo -e "${RED}❌ Plik $MAIN_LOG nie istnieje${NC}"
    fi
}

# Tylko błędy
show_errors() {
    local follow_mode="$1"

    if [ "$follow_mode" = "tail" ]; then
        echo -e "${CYAN}🚨 Błędy na żywo (Ctrl+C aby zatrzymać):${NC}"
        echo ""
        if [ -f "$MAIN_LOG" ]; then
            tail -f "$MAIN_LOG" | grep --line-buffered "ERROR" | sed 's/ERROR/\x1b[31mERROR\x1b[0m/g'
        else
            echo -e "${RED}❌ Plik $MAIN_LOG nie istnieje${NC}"
        fi
    else
        echo -e "${CYAN}🚨 Błędy z logów:${NC}"
        echo ""
        if [ -f "$MAIN_LOG" ]; then
            grep -A 5 -B 5 "ERROR" "$MAIN_LOG" | tail -100 | sed 's/ERROR/\x1b[31mERROR\x1b[0m/g'
        else
            echo -e "${RED}❌ Plik $MAIN_LOG nie istnieje${NC}"
        fi
    fi
}

# Logi z dzisiaj
show_today() {
    local follow_mode="$1"
    local today=$(date +%Y-%m-%d)

    if [ "$follow_mode" = "tail" ]; then
        echo -e "${CYAN}📅 Logi z dzisiaj ($today) na żywo (Ctrl+C aby zatrzymać):${NC}"
        echo ""
        if [ -f "$MAIN_LOG" ]; then
            tail -f "$MAIN_LOG" | grep --line-buffered "$today" | sed 's/ERROR/\x1b[31mERROR\x1b[0m/g' | sed 's/WARNING/\x1b[33mWARNING\x1b[0m/g' | sed 's/INFO/\x1b[36mINFO\x1b[0m/g'
        else
            echo -e "${RED}❌ Plik $MAIN_LOG nie istnieje${NC}"
        fi
    else
        echo -e "${CYAN}📅 Logi z dzisiaj ($today):${NC}"
        echo ""
        if [ -f "$MAIN_LOG" ]; then
            grep "$today" "$MAIN_LOG" | tail -50 | sed 's/ERROR/\x1b[31mERROR\x1b[0m/g' | sed 's/WARNING/\x1b[33mWARNING\x1b[0m/g' | sed 's/INFO/\x1b[36mINFO\x1b[0m/g'
        else
            echo -e "${RED}❌ Plik $MAIN_LOG nie istnieje${NC}"
        fi
    fi
}

# Szukanie frazy
search_logs() {
    local query="$1"
    local follow_mode="$2"

    if [ -z "$query" ]; then
        echo -e "${RED}❌ Podaj frazę do wyszukania${NC}"
        echo "Przykład: ./check_logs.sh search \"CommissionChargeService\""
        exit 1
    fi

    if [ "$follow_mode" = "tail" ]; then
        echo -e "${CYAN}🔍 Szukanie: \"$query\" na żywo (Ctrl+C aby zatrzymać):${NC}"
        echo ""
        if [ -f "$MAIN_LOG" ]; then
            tail -f "$MAIN_LOG" | grep --line-buffered -i "$query" | sed "s/$query/\x1b[43m$query\x1b[0m/gi" | sed 's/ERROR/\x1b[31mERROR\x1b[0m/g' | sed 's/WARNING/\x1b[33mWARNING\x1b[0m/g'
        else
            echo -e "${RED}❌ Plik $MAIN_LOG nie istnieje${NC}"
        fi
    else
        echo -e "${CYAN}🔍 Szukanie: \"$query\" w logach:${NC}"
        echo ""
        if [ -f "$MAIN_LOG" ]; then
            grep -i -A 3 -B 3 "$query" "$MAIN_LOG" | tail -50 | sed "s/$query/\x1b[43m$query\x1b[0m/gi" | sed 's/ERROR/\x1b[31mERROR\x1b[0m/g' | sed 's/WARNING/\x1b[33mWARNING\x1b[0m/g'
        else
            echo -e "${RED}❌ Plik $MAIN_LOG nie istnieje${NC}"
        fi
    fi
}

# Logi kampanii
show_campaigns() {
    echo -e "${CYAN}🎯 Logi kampanii:${NC}"
    echo ""

    local campaign_logs=$(ls "$LOGS_DIR"/campaign*.log "$LOGS_DIR"/campaigns*.log 2>/dev/null | head -5)

    if [ -n "$campaign_logs" ]; then
        for log_file in $campaign_logs; do
            filename=$(basename "$log_file")
            echo -e "${GREEN}📄 $filename:${NC}"
            tail -20 "$log_file" | sed 's/ERROR/\x1b[31mERROR\x1b[0m/g' | sed 's/WARNING/\x1b[33mWARNING\x1b[0m/g' | sed 's/INFO/\x1b[36mINFO\x1b[0m/g'
            echo ""
        done
    else
        echo -e "${YELLOW}⚠️  Nie znaleziono logów kampanii${NC}"
    fi
}

# Czyszczenie logów
clear_logs() {
    echo -e "${YELLOW}⚠️  Czy na pewno chcesz wyczyścić wszystkie logi? (y/N)${NC}"
    read -r response

    if [[ "$response" =~ ^[Yy]$ ]]; then
        echo -e "${CYAN}🧹 Czyszczenie logów...${NC}"
        find "$LOGS_DIR" -name "*.log" -type f -exec truncate -s 0 {} \;
        echo -e "${GREEN}✅ Logi zostały wyczyszczone${NC}"
    else
        echo -e "${BLUE}❌ Anulowano czyszczenie logów${NC}"
    fi
}

# Nowa funkcja do kombinowania filtrów
apply_combined_filters() {
    local follow_mode="$1"
    local show_errors_filter="$2"
    local show_today_filter="$3"
    local search_query="$4"

    local today=$(date +%Y-%m-%d)

    if [ "$follow_mode" = "tail" ]; then
        echo -e "${CYAN}🔄 Kombinowane filtry na żywo (Ctrl+C aby zatrzymać):${NC}"
        [ "$show_errors_filter" = "true" ] && echo -e "  ${RED}📍 Filtr: tylko błędy${NC}"
        [ "$show_today_filter" = "true" ] && echo -e "  ${BLUE}📅 Filtr: dzisiejsze logi ($today)${NC}"
        [ -n "$search_query" ] && echo -e "  ${YELLOW}🔍 Szukam: \"$search_query\"${NC}"
        echo ""

        if [ -f "$MAIN_LOG" ]; then
            local filter_chain="tail -f \"$MAIN_LOG\""

            # Dodaj filtr dzisiejszych logów
            if [ "$show_today_filter" = "true" ]; then
                filter_chain="$filter_chain | grep --line-buffered \"$today\""
            fi

            # Dodaj filtr błędów
            if [ "$show_errors_filter" = "true" ]; then
                filter_chain="$filter_chain | grep --line-buffered \"ERROR\""
            fi

            # Dodaj filtr wyszukiwania
            if [ -n "$search_query" ]; then
                filter_chain="$filter_chain | grep --line-buffered -i \"$search_query\""
            fi

            # Dodaj kolorowanie
            filter_chain="$filter_chain | sed 's/ERROR/\\x1b[31mERROR\\x1b[0m/g' | sed 's/WARNING/\\x1b[33mWARNING\\x1b[0m/g' | sed 's/INFO/\\x1b[36mINFO\\x1b[0m/g'"

            # Dodaj podświetlanie wyszukiwanej frazy
            if [ -n "$search_query" ]; then
                filter_chain="$filter_chain | sed \"s/$search_query/\\x1b[43m$search_query\\x1b[0m/gi\""
            fi

            eval "$filter_chain"
        else
            echo -e "${RED}❌ Plik $MAIN_LOG nie istnieje${NC}"
        fi
    else
        echo -e "${CYAN}🔄 Kombinowane filtry:${NC}"
        [ "$show_errors_filter" = "true" ] && echo -e "  ${RED}📍 Filtr: tylko błędy${NC}"
        [ "$show_today_filter" = "true" ] && echo -e "  ${BLUE}📅 Filtr: dzisiejsze logi ($today)${NC}"
        [ -n "$search_query" ] && echo -e "  ${YELLOW}🔍 Szukam: \"$search_query\"${NC}"
        echo ""

        if [ -f "$MAIN_LOG" ]; then
            local filter_chain="cat \"$MAIN_LOG\""

            # Dodaj filtr dzisiejszych logów
            if [ "$show_today_filter" = "true" ]; then
                filter_chain="$filter_chain | grep \"$today\""
            fi

            # Dodaj filtr błędów
            if [ "$show_errors_filter" = "true" ]; then
                filter_chain="$filter_chain | grep \"ERROR\""
            fi

            # Dodaj filtr wyszukiwania
            if [ -n "$search_query" ]; then
                filter_chain="$filter_chain | grep -i \"$search_query\""
            fi

            # Dodaj tail dla ostatnich wyników
            filter_chain="$filter_chain | tail -50"

            # Dodaj kolorowanie
            filter_chain="$filter_chain | sed 's/ERROR/\\x1b[31mERROR\\x1b[0m/g' | sed 's/WARNING/\\x1b[33mWARNING\\x1b[0m/g' | sed 's/INFO/\\x1b[36mINFO\\x1b[0m/g'"

            # Dodaj podświetlanie wyszukiwanej frazy
            if [ -n "$search_query" ]; then
                filter_chain="$filter_chain | sed \"s/$search_query/\\x1b[43m$search_query\\x1b[0m/gi\""
            fi

            eval "$filter_chain"
        else
            echo -e "${RED}❌ Plik $MAIN_LOG nie istnieje${NC}"
        fi
    fi
}

# Główna logika
main() {
    check_logs_dir

    # Parsowanie argumentów
    local follow_mode=""
    local show_errors_filter=""
    local show_today_filter=""
    local search_query=""
    local show_basic_tail=""
    local show_single_action=""

    # Przejdź przez wszystkie argumenty
    for arg in "$@"; do
        case "$arg" in
            "tail")
                follow_mode="tail"
                ;;
            "errors"|"error"|"e")
                show_errors_filter="true"
                ;;
            "today"|"td")
                show_today_filter="true"
                ;;
            "search"|"find"|"grep")
                # Szukaj następnego argumentu jako query
                for ((i=1; i<=$#; i++)); do
                    if [ "${!i}" = "$arg" ] && [ $((i+1)) -le $# ]; then
                        next_arg_index=$((i+1))
                        search_query="${!next_arg_index}"
                        break
                    fi
                done
                ;;
            "follow"|"f"|"live")
                follow_logs
                return
                ;;
            "size"|"sizes"|"s")
                show_sizes
                return
                ;;
            "list"|"ls"|"l")
                list_logs
                return
                ;;
            "clear"|"clean"|"c")
                clear_logs
                return
                ;;
            "campaigns"|"campaign"|"camp")
                show_campaigns
                return
                ;;
            "help"|"h"|"-h"|"--help")
                show_help
                return
                ;;
        esac
    done

    # Jeśli nie ma żadnych filtrów, pokaż podstawowe tail
    if [ -z "$show_errors_filter" ] && [ -z "$show_today_filter" ] && [ -z "$search_query" ]; then
        if [ "$follow_mode" = "tail" ]; then
            follow_logs
        else
            show_tail "50"
        fi
        return
    fi

    # Zastosuj kombinowane filtry
    apply_combined_filters "$follow_mode" "$show_errors_filter" "$show_today_filter" "$search_query"
}

# Uruchom skrypt
main "$@"
