#!/bin/bash

# Skrypt do zarządzania serwerem Laravel
# Autor: Piotr Adamczyk
# Usage: ./laravel_server.sh [start|stop|restart|status] [port]

# Ustal ścieżkę względem głównego katalogu projektu
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# PROJECT_ROOT to katalog, w którym znajduje się skrypt (główny katalog projektu Laravel)
PROJECT_ROOT="$SCRIPT_DIR"

# Domyślny port
DEFAULT_PORT=8000

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
    echo -e "${CYAN}🚀 Skrypt zarządzania serwerem Laravel${NC}"
    echo ""
    echo -e "${YELLOW}Użycie:${NC}"
    echo "  ./laravel_server.sh [akcja] [port]"
    echo ""
    echo -e "${YELLOW}Akcje:${NC}"
    echo -e "  ${GREEN}start${NC}    - Uruchom serwer Laravel (domyślnie port $DEFAULT_PORT)"
    echo -e "  ${GREEN}stop${NC}     - Zatrzymaj wszystkie procesy serwera Laravel"
    echo -e "  ${GREEN}restart${NC}  - Zatrzymaj i uruchom ponownie serwer"
    echo -e "  ${GREEN}status${NC}   - Sprawdź status serwera"
    echo -e "  ${GREEN}help${NC}     - Ta pomoc"
    echo ""
    echo -e "${YELLOW}Parametry:${NC}"
    echo -e "  ${PURPLE}port${NC}     - Port na którym uruchomić serwer (opcjonalny)"
    echo ""
    echo -e "${YELLOW}Przykłady:${NC}"
    echo "  ./laravel_server.sh start           # Uruchom na porcie $DEFAULT_PORT"
    echo "  ./laravel_server.sh start 8001      # Uruchom na porcie 8001"
    echo "  ./laravel_server.sh stop            # Zatrzymaj serwer"
    echo "  ./laravel_server.sh restart 8002    # Restart na porcie 8002"
    echo "  ./laravel_server.sh status          # Sprawdź status"
}

# Sprawdź czy jesteśmy w katalogu projektu Laravel
check_laravel_project() {
    if [ ! -f "$PROJECT_ROOT/artisan" ]; then
        echo -e "${RED}❌ Nie znaleziono pliku artisan w katalogu: $PROJECT_ROOT${NC}"
        echo -e "${YELLOW}💡 Upewnij się, że uruchamiasz skrypt z katalogu projektu Laravel${NC}"
        exit 1
    fi
}

# Znajdź wszystkie procesy Laravel
find_laravel_processes() {
    # Znajdź procesy artisan serve
    ARTISAN_PIDS=$(ps aux | grep "php artisan serve" | grep -v grep | awk '{print $2}')

    # Znajdź procesy PHP server z portami 8000-8010
    PHP_SERVER_PIDS=$(ps aux | grep -E "php.*-S.*:(800[0-9]|801[0])" | grep -v grep | awk '{print $2}')

    # Połącz wszystkie PIDy
    ALL_PIDS="$ARTISAN_PIDS $PHP_SERVER_PIDS"
    echo "$ALL_PIDS" | tr ' ' '\n' | sort -u | tr '\n' ' '
}

# Sprawdź status serwera
check_status() {
    echo -e "${CYAN}📊 Status serwera Laravel:${NC}"
    echo ""

    local found_processes=false

    # Sprawdź procesy artisan serve
    ARTISAN_PIDS=$(ps aux | grep "php artisan serve" | grep -v grep)
    if [ -n "$ARTISAN_PIDS" ]; then
        echo -e "${GREEN}🟢 Procesy artisan serve:${NC}"
        echo "$ARTISAN_PIDS" | while read -r line; do
            local pid=$(echo "$line" | awk '{print $2}')
            local port=$(echo "$line" | grep -oE ':[0-9]+' | sed 's/://')
            echo -e "  ${YELLOW}PID $pid${NC} - Port: ${PURPLE}${port:-8000}${NC}"
        done
        found_processes=true
    fi

    # Sprawdź procesy PHP server
    PHP_SERVER_PIDS=$(ps aux | grep -E "php.*-S.*:(800[0-9]|801[0])" | grep -v grep)
    if [ -n "$PHP_SERVER_PIDS" ]; then
        echo -e "${GREEN}🟢 Procesy PHP development server:${NC}"
        echo "$PHP_SERVER_PIDS" | while read -r line; do
            local pid=$(echo "$line" | awk '{print $2}')
            local port=$(echo "$line" | grep -oE ':[0-9]+' | sed 's/://')
            echo -e "  ${YELLOW}PID $pid${NC} - Port: ${PURPLE}$port${NC}"
        done
        found_processes=true
    fi

    if [ "$found_processes" = false ]; then
        echo -e "${RED}🔴 Brak uruchomionych procesów serwera Laravel${NC}"
        return 1
    fi

    echo ""

    # Sprawdź porty
    echo -e "${CYAN}🌐 Sprawdzanie portów:${NC}"
    for port in 8000 8001 8002 8003; do
        if lsof -i :$port >/dev/null 2>&1; then
            local process=$(lsof -i :$port | tail -1 | awk '{print $1 " (PID " $2 ")"}')
            echo -e "  Port ${PURPLE}$port${NC}: ${GREEN}zajęty${NC} - $process"
        else
            echo -e "  Port ${PURPLE}$port${NC}: ${BLUE}wolny${NC}"
        fi
    done

    return 0
}

# Zatrzymaj serwer
stop_server() {
    echo -e "${CYAN}🛑 Zatrzymywanie serwera Laravel...${NC}"
    echo ""

    local pids=$(find_laravel_processes)

    if [ -z "$pids" ]; then
        echo -e "${YELLOW}⚠️  Nie znaleziono uruchomionych procesów serwera Laravel${NC}"
        return 0
    fi

    echo -e "${YELLOW}📋 Znalezione procesy do zatrzymania:${NC}"
    ps aux | grep -E "(php artisan serve|php.*-S.*:(800[0-9]|801[0]))" | grep -v grep | while read -r line; do
        local pid=$(echo "$line" | awk '{print $2}')
        local cmd=$(echo "$line" | awk '{for(i=11;i<=NF;i++) printf "%s ", $i; print ""}')
        echo -e "  ${RED}PID $pid${NC}: $cmd"
    done

    echo ""
    echo -e "${YELLOW}⚠️  Czy chcesz zatrzymać te procesy? (y/N)${NC}"
    read -r response

    if [[ "$response" =~ ^[Yy]$ ]]; then
        echo -e "${CYAN}🔄 Zatrzymywanie procesów...${NC}"

        local stopped_count=0
        for pid in $pids; do
            if [ -n "$pid" ]; then
                echo -e "Zatrzymywanie procesu PID: ${YELLOW}$pid${NC}"
                if kill -TERM "$pid" 2>/dev/null; then
                    echo -e "  ${GREEN}✅ Proces $pid zatrzymany${NC}"
                    ((stopped_count++))
                else
                    echo -e "  ${RED}❌ Nie można zatrzymać procesu $pid${NC}"
                fi
            fi
        done

        # Poczekaj chwilę
        sleep 2

        # Sprawdź czy procesy zostały zatrzymane
        local remaining_pids=$(find_laravel_processes)
        if [ -n "$remaining_pids" ]; then
            echo -e "${YELLOW}⚠️  Niektóre procesy wciąż działają. Wymuszam zatrzymanie...${NC}"
            for pid in $remaining_pids; do
                if [ -n "$pid" ]; then
                    echo -e "Force killing PID: ${RED}$pid${NC}"
                    kill -9 "$pid" 2>/dev/null
                fi
            done
            sleep 1
        fi

        echo -e "${GREEN}🎉 Zatrzymano $stopped_count procesów${NC}"

        # Sprawdź końcowy status
        echo ""
        check_status >/dev/null 2>&1 || echo -e "${GREEN}✅ Wszystkie procesy serwera Laravel zostały zatrzymane${NC}"

    else
        echo -e "${BLUE}❌ Anulowano zatrzymywanie serwera${NC}"
    fi
}

# Uruchom serwer
start_server() {
    local port=${1:-$DEFAULT_PORT}

    echo -e "${CYAN}🚀 Uruchamianie serwera Laravel na porcie $port...${NC}"
    echo ""

    # Sprawdź czy port jest wolny
    if lsof -i :$port >/dev/null 2>&1; then
        local process=$(lsof -i :$port | tail -1)
        echo -e "${RED}❌ Port $port jest już zajęty:${NC}"
        echo "$process"
        echo ""
        echo -e "${YELLOW}💡 Użyj opcji 'stop' lub wybierz inny port${NC}"
        return 1
    fi

    # Przejdź do katalogu projektu
    cd "$PROJECT_ROOT" || exit 1

    echo -e "${BLUE}📁 Katalog projektu: $PROJECT_ROOT${NC}"
    echo -e "${BLUE}🌐 URL serwera: http://localhost:$port${NC}"
    echo ""

    # Uruchom serwer w tle
    echo -e "${CYAN}▶️  Uruchamianie php artisan serve --port=$port...${NC}"

    # Uruchom w tle i przekieruj output
    nohup php artisan serve --port="$port" > /dev/null 2>&1 &
    local server_pid=$!

    # Poczekaj chwilę żeby serwer się uruchomił
    sleep 3

    # Sprawdź czy serwer się uruchomił
    if ps -p $server_pid > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Serwer Laravel uruchomiony pomyślnie!${NC}"
        echo -e "  ${YELLOW}PID:${NC} $server_pid"
        echo -e "  ${YELLOW}Port:${NC} $port"
        echo -e "  ${YELLOW}URL:${NC} ${BLUE}http://localhost:$port${NC}"
        echo ""
        echo -e "${PURPLE}💡 Aby zatrzymać serwer, użyj: ./laravel_server.sh stop${NC}"
    else
        echo -e "${RED}❌ Nie udało się uruchomić serwera${NC}"
        echo -e "${YELLOW}💡 Sprawdź logi błędów lub uruchom ręcznie: php artisan serve --port=$port${NC}"
        return 1
    fi
}

# Restart serwera
restart_server() {
    local port=${1:-$DEFAULT_PORT}

    echo -e "${CYAN}🔄 Restart serwera Laravel...${NC}"
    echo ""

    # Zatrzymaj bez pytania
    echo -e "${YELLOW}🛑 Zatrzymywanie istniejących procesów...${NC}"
    local pids=$(find_laravel_processes)

    if [ -n "$pids" ]; then
        for pid in $pids; do
            if [ -n "$pid" ]; then
                echo -e "Zatrzymywanie PID: ${YELLOW}$pid${NC}"
                kill -TERM "$pid" 2>/dev/null
            fi
        done

        sleep 2

        # Force kill jeśli potrzeba
        local remaining_pids=$(find_laravel_processes)
        if [ -n "$remaining_pids" ]; then
            echo -e "${YELLOW}Wymuszam zatrzymanie...${NC}"
            for pid in $remaining_pids; do
                if [ -n "$pid" ]; then
                    kill -9 "$pid" 2>/dev/null
                fi
            done
            sleep 1
        fi
    fi

    echo -e "${GREEN}✅ Procesy zatrzymane${NC}"
    echo ""

    # Uruchom ponownie
    start_server "$port"
}

# Główna logika
main() {
    local action="${1:-help}"
    local port="${2:-$DEFAULT_PORT}"

    # Sprawdź czy to jest projekt Laravel
    check_laravel_project

    case "$action" in
        "start")
            start_server "$port"
            ;;
        "stop")
            stop_server
            ;;
        "restart")
            restart_server "$port"
            ;;
        "status")
            check_status
            ;;
        "help"|"h"|"-h"|"--help")
            show_help
            ;;
        *)
            echo -e "${RED}❌ Nieznana akcja: $action${NC}"
            echo ""
            show_help
            exit 1
            ;;
    esac
}

# Uruchom skrypt
main "$@"
