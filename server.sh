#!/bin/bash

# Skrypt do zarządzania serwerem Laravel i wszystkimi zależnościami
# Autor: Piotr Adamczyk
# Usage: ./server.sh [start|stop|restart|status] [port]

# Ustal ścieżkę względem głównego katalogu projektu
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR"

# Funkcja do ładowania zmiennych z .env
load_env() {
    if [ -f "$PROJECT_ROOT/.env" ]; then
        # Ładuj DEFAULT_PORT z .env (bez komentarzy i pustych linii)
        port_line=$(grep -v '^#' "$PROJECT_ROOT/.env" | grep -E '^DEFAULT_PORT=' | head -1)
        if [ -n "$port_line" ]; then
            # Wyciągnij wartość po znaku =
            port_value=$(echo "$port_line" | cut -d'=' -f2 | tr -d ' ' | tr -d '"' | tr -d "'")
            if [ -n "$port_value" ]; then
                DEFAULT_PORT="$port_value"
            fi
        fi
    fi
}

# Domyślny port (z .env lub 8000 jako fallback)
load_env
DEFAULT_PORT=${DEFAULT_PORT:-8000}

# Plik do przechowywania PIDów procesów
PID_FILE="$PROJECT_ROOT/.server_pids"

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
    # Upewnij się, że DEFAULT_PORT jest załadowany
    if [ -z "$DEFAULT_PORT" ]; then
        load_env
        DEFAULT_PORT=${DEFAULT_PORT:-8000}
    fi
    
    echo -e "${CYAN}🚀 Skrypt zarządzania serwerem Laravel i zależnościami${NC}"
    echo ""
    echo -e "${YELLOW}Użycie:${NC}"
    echo "  ./server.sh [akcja] [port]"
    echo ""
    echo -e "${YELLOW}Akcje:${NC}"
    echo -e "  ${GREEN}start${NC}    - Uruchom serwer Laravel i wszystkie zależności (domyślnie port $DEFAULT_PORT)"
    echo -e "  ${GREEN}stop${NC}     - Zatrzymaj wszystkie procesy serwera i zależności"
    echo -e "  ${GREEN}restart${NC}  - Zatrzymaj i uruchom ponownie serwer i zależności"
    echo -e "  ${GREEN}status${NC}   - Sprawdź status serwera i zależności"
    echo -e "  ${GREEN}help${NC}     - Ta pomoc"
    echo ""
    echo -e "${YELLOW}Parametry:${NC}"
    echo -e "  ${PURPLE}port${NC}     - Port na którym uruchomić serwer Laravel (opcjonalny, domyślnie z .env: $DEFAULT_PORT)"
    echo ""
    echo -e "${YELLOW}Uruchamiane zależności:${NC}"
    echo -e "  ${BLUE}•${NC} Laravel server (php artisan serve)"
    echo -e "  ${BLUE}•${NC} Queue worker (php artisan queue:work)"
    echo -e "  ${BLUE}•${NC} Vite dev server (npm run dev)"
    echo ""
    echo -e "${YELLOW}Przykłady:${NC}"
    echo "  ./server.sh start           # Uruchom na porcie $DEFAULT_PORT (z .env)"
    echo "  ./server.sh start 8001     # Uruchom na porcie 8001"
    echo "  ./server.sh stop            # Zatrzymaj wszystkie procesy"
    echo "  ./server.sh restart 8002    # Restart na porcie 8002"
    echo "  ./server.sh status          # Sprawdź status"
}

# Sprawdź czy jesteśmy w katalogu projektu Laravel
check_laravel_project() {
    if [ ! -f "$PROJECT_ROOT/artisan" ]; then
        echo -e "${RED}❌ Nie znaleziono pliku artisan w katalogu: $PROJECT_ROOT${NC}"
        echo -e "${YELLOW}💡 Upewnij się, że uruchamiasz skrypt z katalogu projektu Laravel${NC}"
        exit 1
    fi
}

# Zapisz PID do pliku
save_pid() {
    local service=$1
    local pid=$2
    if [ -n "$pid" ]; then
        echo "$service:$pid" >> "$PID_FILE"
    fi
}

# Wczytaj PIDy z pliku
load_pids() {
    if [ -f "$PID_FILE" ]; then
        cat "$PID_FILE"
    fi
}

# Usuń PID z pliku
remove_pid() {
    local service=$1
    if [ -f "$PID_FILE" ]; then
        grep -v "^$service:" "$PID_FILE" > "${PID_FILE}.tmp" && mv "${PID_FILE}.tmp" "$PID_FILE"
    fi
}

# Wyczyść plik PIDów
clear_pids() {
    rm -f "$PID_FILE"
}

# Sprawdź czy proces działa
is_process_running() {
    local pid=$1
    if [ -n "$pid" ] && ps -p "$pid" > /dev/null 2>&1; then
        return 0
    fi
    return 1
}

# Zatrzymaj proces
kill_process() {
    local pid=$1
    local service=$2
    
    if [ -z "$pid" ]; then
        return 1
    fi
    
    if is_process_running "$pid"; then
        echo -e "  ${YELLOW}Zatrzymywanie $service (PID: $pid)...${NC}"
        kill -TERM "$pid" 2>/dev/null
        
        # Poczekaj chwilę
        sleep 2
        
        # Jeśli wciąż działa, wymuś zatrzymanie
        if is_process_running "$pid"; then
            echo -e "  ${RED}Wymuszam zatrzymanie $service (PID: $pid)...${NC}"
            kill -9 "$pid" 2>/dev/null
            sleep 1
        fi
        
        if ! is_process_running "$pid"; then
            echo -e "  ${GREEN}✅ $service zatrzymany${NC}"
            return 0
        else
            echo -e "  ${RED}❌ Nie można zatrzymać $service${NC}"
            return 1
        fi
    else
        echo -e "  ${BLUE}ℹ️  $service już nie działa (PID: $pid)${NC}"
        return 0
    fi
}

# Sprawdź status serwera
check_status() {
    echo -e "${CYAN}📊 Status serwera i zależności:${NC}"
    echo ""

    local found_any=false

    # Sprawdź procesy z pliku PID
    if [ -f "$PID_FILE" ]; then
        while IFS=: read -r service pid; do
            if [ -n "$pid" ] && is_process_running "$pid"; then
                found_any=true
                local cmd=$(ps -p "$pid" -o command= 2>/dev/null | head -1)
                echo -e "${GREEN}🟢 $service${NC} (PID: ${YELLOW}$pid${NC})"
                echo -e "   ${BLUE}Command:${NC} $cmd"
            else
                echo -e "${RED}🔴 $service${NC} (PID: ${YELLOW}$pid${NC}) - ${RED}nie działa${NC}"
                remove_pid "$service"
            fi
        done < "$PID_FILE"
    fi

    # Sprawdź dodatkowe procesy Laravel (jeśli są uruchomione poza skryptem)
    ARTISAN_PIDS=$(ps aux | grep "php artisan serve" | grep -v grep | awk '{print $2}')
    if [ -n "$ARTISAN_PIDS" ]; then
        for pid in $ARTISAN_PIDS; do
            if ! grep -q ":$pid$" "$PID_FILE" 2>/dev/null; then
                found_any=true
                local port=$(ps aux | grep "php artisan serve.*$pid" | grep -oE '\-\-port[= ][0-9]+' | grep -oE '[0-9]+' | head -1)
                if [ -z "$port" ]; then
                    port="8000"
                fi
                echo -e "${YELLOW}⚠️  Laravel server (PID: $pid, Port: $port) - uruchomiony poza skryptem${NC}"
            fi
        done
    fi

    if [ "$found_any" = false ]; then
        if [ ! -f "$PID_FILE" ] || [ ! -s "$PID_FILE" ]; then
            echo -e "${RED}🔴 Brak uruchomionych procesów${NC}"
            return 1
        fi
    fi

    echo ""

    # Sprawdź porty
    echo -e "${CYAN}🌐 Sprawdzanie portów:${NC}"
    for port in 8000 8001 8002 8003 5173; do
        if lsof -i :$port >/dev/null 2>&1; then
            local process=$(lsof -i :$port | tail -1 | awk '{print $1 " (PID " $2 ")"}')
            echo -e "  Port ${PURPLE}$port${NC}: ${GREEN}zajęty${NC} - $process"
        else
            echo -e "  Port ${PURPLE}$port${NC}: ${BLUE}wolny${NC}"
        fi
    done

    return 0
}

# Zatrzymaj wszystkie procesy
stop_all() {
    echo -e "${CYAN}🛑 Zatrzymywanie wszystkich procesów...${NC}"
    echo ""

    local stopped_count=0

    # Zatrzymaj procesy z pliku PID
    if [ -f "$PID_FILE" ]; then
        while IFS=: read -r service pid; do
            if [ -n "$pid" ]; then
                if kill_process "$pid" "$service"; then
                    ((stopped_count++))
                fi
                remove_pid "$service"
            fi
        done < "$PID_FILE"
    fi

    # Zatrzymaj dodatkowe procesy Laravel (jeśli są)
    ARTISAN_PIDS=$(ps aux | grep "php artisan serve" | grep -v grep | awk '{print $2}')
    if [ -n "$ARTISAN_PIDS" ]; then
        for pid in $ARTISAN_PIDS; do
            if kill_process "$pid" "Laravel server (dodatkowy)"; then
                ((stopped_count++))
            fi
        done
    fi

    # Zatrzymaj procesy queue
    QUEUE_PIDS=$(ps aux | grep -E "php artisan queue:(listen|work)" | grep -v grep | awk '{print $2}')
    if [ -n "$QUEUE_PIDS" ]; then
        for pid in $QUEUE_PIDS; do
            if kill_process "$pid" "Queue worker (dodatkowy)"; then
                ((stopped_count++))
            fi
        done
    fi

    # Zatrzymaj procesy Vite
    VITE_PIDS=$(ps aux | grep -E "vite|node.*5173" | grep -v grep | awk '{print $2}')
    if [ -n "$VITE_PIDS" ]; then
        for pid in $VITE_PIDS; do
            if kill_process "$pid" "Vite dev server (dodatkowy)"; then
                ((stopped_count++))
            fi
        done
    fi

    # Wyczyść plik PIDów
    clear_pids

    echo ""
    if [ $stopped_count -gt 0 ]; then
        echo -e "${GREEN}🎉 Zatrzymano $stopped_count procesów${NC}"
    else
        echo -e "${BLUE}ℹ️  Nie znaleziono procesów do zatrzymania${NC}"
    fi

    # Sprawdź końcowy status
    echo ""
    check_status >/dev/null 2>&1 || echo -e "${GREEN}✅ Wszystkie procesy zostały zatrzymane${NC}"
}

# Zatrzymaj serwer (z potwierdzeniem)
stop_server() {
    echo -e "${CYAN}🛑 Zatrzymywanie serwera i zależności...${NC}"
    echo ""

    # Sprawdź czy są uruchomione procesy
    local has_processes=false
    if [ -f "$PID_FILE" ] && [ -s "$PID_FILE" ]; then
        has_processes=true
    fi

    ARTISAN_PIDS=$(ps aux | grep "php artisan serve" | grep -v grep | awk '{print $2}')
    if [ -n "$ARTISAN_PIDS" ]; then
        has_processes=true
    fi

    if [ "$has_processes" = false ]; then
        echo -e "${YELLOW}⚠️  Nie znaleziono uruchomionych procesów${NC}"
        return 0
    fi

    echo -e "${YELLOW}📋 Znalezione procesy do zatrzymania:${NC}"
    check_status
    echo ""
    echo -e "${YELLOW}⚠️  Czy chcesz zatrzymać wszystkie procesy? (y/N)${NC}"
    read -r response

    if [[ "$response" =~ ^[Yy]$ ]]; then
        stop_all
    else
        echo -e "${BLUE}❌ Anulowano zatrzymywanie${NC}"
    fi
}

# Uruchom serwer i wszystkie zależności
start_server() {
    local port=${1:-$DEFAULT_PORT}

    echo -e "${CYAN}🚀 Uruchamianie serwera Laravel i zależności na porcie $port...${NC}"
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

    # Sprawdź czy port Vite jest wolny
    if lsof -i :5173 >/dev/null 2>&1; then
        echo -e "${YELLOW}⚠️  Port 5173 (Vite) jest już zajęty${NC}"
        echo -e "${YELLOW}💡 Możliwe, że Vite już działa. Kontynuuję...${NC}"
    fi

    # Przejdź do katalogu projektu
    cd "$PROJECT_ROOT" || exit 1

    echo -e "${BLUE}📁 Katalog projektu: $PROJECT_ROOT${NC}"
    echo -e "${BLUE}🌐 URL serwera: http://localhost:$port${NC}"
    echo ""

    # Wyczyść stary plik PIDów
    clear_pids

    # 1. Uruchom Laravel server
    echo -e "${CYAN}▶️  Uruchamianie Laravel server (port $port)...${NC}"
    nohup php artisan serve --port="$port" > /dev/null 2>&1 &
    local laravel_pid=$!
    sleep 2
    
    if is_process_running "$laravel_pid"; then
        save_pid "laravel" "$laravel_pid"
        echo -e "${GREEN}✅ Laravel server uruchomiony (PID: $laravel_pid)${NC}"
    else
        echo -e "${RED}❌ Nie udało się uruchomić Laravel server${NC}"
        return 1
    fi

    # 2. Uruchom Queue worker
    echo -e "${CYAN}▶️  Uruchamianie Queue worker...${NC}"
    # Używamy queue:work dla lepszej wydajności
    # --tries=3: maksymalna liczba prób, --timeout=60: timeout dla zadania
    nohup php artisan queue:work --tries=3 --timeout=60 > /dev/null 2>&1 &
    local queue_pid=$!
    sleep 2
    
    if is_process_running "$queue_pid"; then
        save_pid "queue" "$queue_pid"
        echo -e "${GREEN}✅ Queue worker uruchomiony (PID: $queue_pid)${NC}"
    else
        echo -e "${YELLOW}⚠️  Nie udało się uruchomić Queue worker${NC}"
        echo -e "${YELLOW}💡 Sprawdź czy baza danych jest skonfigurowana i czy tabela jobs istnieje${NC}"
    fi

    # 3. Uruchom Vite dev server
    echo -e "${CYAN}▶️  Uruchamianie Vite dev server...${NC}"
    if [ -f "$PROJECT_ROOT/package.json" ]; then
        # Sprawdź czy node_modules istnieje
        if [ ! -d "$PROJECT_ROOT/node_modules" ]; then
            echo -e "${YELLOW}⚠️  node_modules nie istnieje. Instalowanie zależności...${NC}"
            npm install
        fi
        
        nohup npm run dev > /dev/null 2>&1 &
        local vite_pid=$!
        sleep 2
        
        # Vite może uruchomić wiele procesów, znajdź główny
        local vite_main_pid=$(ps aux | grep -E "vite.*dev" | grep -v grep | head -1 | awk '{print $2}')
        if [ -n "$vite_main_pid" ] && is_process_running "$vite_main_pid"; then
            save_pid "vite" "$vite_main_pid"
            echo -e "${GREEN}✅ Vite dev server uruchomiony (PID: $vite_main_pid)${NC}"
        else
            echo -e "${YELLOW}⚠️  Nie udało się uruchomić Vite dev server${NC}"
        fi
    else
        echo -e "${YELLOW}⚠️  package.json nie istnieje. Pomijam Vite${NC}"
    fi

    echo ""
    echo -e "${GREEN}🎉 Wszystkie serwisy uruchomione!${NC}"
    echo ""
    echo -e "${CYAN}📊 Podsumowanie:${NC}"
    echo -e "  ${YELLOW}Laravel:${NC} http://localhost:$port"
    echo -e "  ${YELLOW}Vite:${NC} http://localhost:5173"
    echo ""
    echo -e "${PURPLE}💡 Aby zatrzymać wszystkie serwisy, użyj: ./server.sh stop${NC}"
    
    # Pokaż status
    echo ""
    check_status
}

# Restart serwera
restart_server() {
    local port=${1:-$DEFAULT_PORT}

    echo -e "${CYAN}🔄 Restart serwera i zależności...${NC}"
    echo ""

    # Zatrzymaj bez pytania
    echo -e "${YELLOW}🛑 Zatrzymywanie istniejących procesów...${NC}"
    stop_all >/dev/null 2>&1

    sleep 2

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

