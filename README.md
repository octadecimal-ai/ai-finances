# Finances Analyzer

Zaawansowany analizator finansów osobistych oparty na Laravel z integracją AI i bankowością otwartą.

## 🚀 Funkcjonalności

### 📊 Analiza Finansowa
- Automatyczne pobieranie danych bankowych (Nordigen, Revolut)
- Import plików CSV z różnych banków
- Kategoryzacja transakcji z pomocą AI (Claude)
- Analiza wzorców wydatków
- Generowanie raportów Excel/PDF

### 🤖 AI Asystent
- Analiza transakcji przez Claude AI
- Automatyczne sugestie kategorii
- Rekomendacje budżetowe
- Insights finansowe

### 🔗 Integracje
- **Nordigen AIS API** - Open Banking
- **Revolut Open Banking API**
- **Google Drive API** - arkusze Excel
- **Claude API** - analiza AI
- **Slack API** - powiadomienia
- **Własne REST API**

### 📱 Powiadomienia
- Alerty o przekroczeniu budżetu
- Powiadomienia o dużych transakcjach
- Raporty o synchronizacji
- Powiadomienia o błędach

## 🛠️ Technologie

### Backend
- **Laravel 11** - framework PHP
- **PHP 8.2+** - język programowania
- **MySQL/PostgreSQL** - baza danych
- **Redis** - cache i kolejki
- **Laravel Sanctum** - autentykacja API

### Frontend
- **Blade** - szablony
- **Alpine.js** - interaktywność
- **Tailwind CSS** - stylowanie
- **Chart.js** - wykresy

### Integracje
- **Nordigen SDK** - Open Banking
- **Google APIs Client** - Drive API
- **Anthropic Claude API** - AI
- **Slack Web API** - powiadomienia
- **PhpSpreadsheet** - Excel

## 📦 Instalacja

### Wymagania
- PHP 8.2+
- Composer
- MySQL/PostgreSQL
- Redis (opcjonalnie)
- Node.js (dla frontend)

### Krok 1: Klonowanie
```bash
git clone https://github.com/your-username/finances-analyzer.git
cd finances-analyzer
```

### Krok 2: Zależności
```bash
composer install
npm install
```

### Krok 3: Konfiguracja
```bash
cp .env.example .env
php artisan key:generate
```

### Krok 4: Baza danych
```bash
php artisan migrate
php artisan db:seed
```

### Krok 5: Konfiguracja API
Edytuj plik `.env` i dodaj klucze API:

```env
# Nordigen
NORDIGEN_SECRET_ID=your_secret_id
NORDIGEN_SECRET_KEY=your_secret_key

# Revolut
REVOLUT_CLIENT_ID=your_client_id
REVOLUT_CLIENT_SECRET=your_client_secret

# Claude AI
CLAUDE_API_KEY=your_claude_api_key

# Google Drive
GOOGLE_DRIVE_CLIENT_ID=your_client_id
GOOGLE_DRIVE_CLIENT_SECRET=your_client_secret

# Slack
SLACK_WEBHOOK_URL=your_webhook_url
```

### Krok 6: Uruchomienie
```bash
php artisan serve
npm run dev
```

## 🔧 Konfiguracja

### Banki
Projekt obsługuje następujące banki:
- **mBank** - import CSV
- **ING** - import CSV
- **PKO BP** - import CSV
- **Revolut** - API + CSV
- **Nordigen** - Open Banking API

### Kategorie
Domyślne kategorie:
- Jedzenie (Restauracje, Sklepy spożywcze)
- Transport (Paliwo, Transport publiczny, Taksówki)
- Zakupy (Ubrania, Elektronika)
- Rachunki (Prąd, Gaz, Internet, Telefon)
- Zdrowie (Leki, Lekarz)
- Edukacja
- Rozrywka (Kino, Sport, Streaming)
- Inne

## 📚 API Dokumentacja

### Endpoints

#### Transakcje
```
GET    /api/transactions              # Lista transakcji
POST   /api/transactions              # Nowa transakcja
GET    /api/transactions/{id}         # Szczegóły transakcji
PUT    /api/transactions/{id}         # Aktualizacja transakcji
DELETE /api/transactions/{id}         # Usunięcie transakcji
GET    /api/transactions/statistics   # Statystyki
```

#### Bankowość
```
GET    /api/banking/accounts          # Lista kont
POST   /api/banking/accounts          # Nowe konto
POST   /api/banking/accounts/{id}/sync # Synchronizacja
GET    /api/banking/institutions      # Lista banków
```

#### AI
```
POST   /api/ai/analyze-transaction/{id}    # Analiza transakcji
POST   /api/ai/suggest-category/{id}       # Sugestia kategorii
POST   /api/ai/budget-recommendations      # Rekomendacje budżetowe
```

#### Import
```
POST   /api/import/csv               # Import CSV
GET    /api/import/csv/formats       # Obsługiwane formaty
```

### Przykłady użycia

#### Pobieranie transakcji
```bash
curl -X GET "http://localhost:8000/api/transactions" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Import CSV
```bash
curl -X POST "http://localhost:8000/api/import/csv" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@transactions.csv" \
  -F "format=mbank"
```

#### Analiza AI
```bash
curl -X POST "http://localhost:8000/api/ai/analyze-transaction/1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## 🔄 Synchronizacja

### Automatyczna
```bash
# Cron job dla automatycznej synchronizacji
* * * * * php /path/to/artisan schedule:run
```

### Ręczna
```bash
# Synchronizacja wszystkich kont
php artisan banking:sync

# Synchronizacja konkretnego konta
php artisan banking:sync --account=1
```

## 📊 Raporty

### Typy raportów
- **Miesięczne podsumowanie** - automatycznie generowane
- **Analiza budżetu** - cotygodniowe
- **Wzorce wydatków** - miesięczne
- **Podsumowanie podatkowe** - roczne

### Eksport
- **Excel** - z wykresami i analizą
- **PDF** - raporty gotowe do druku
- **CSV** - dane surowe
- **Google Drive** - automatyczny backup

## 🔔 Powiadomienia

### Slack
- Przekroczenie budżetu
- Duże transakcje (>1000 PLN)
- Zakończenie synchronizacji
- Wygenerowanie raportów
- Alerty o błędach

### Email
- Cotygodniowe podsumowania
- Miesięczne raporty
- Alerty o problemach

## 🧪 Testy

```bash
# Testy jednostkowe
php artisan test --testsuite=Unit

# Testy funkcjonalne
php artisan test --testsuite=Feature

# Testy integracji
php artisan test --testsuite=Integration
```

## 🚀 Deployment

### Produkcja
```bash
# Optymalizacja
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Queue workers
php artisan queue:work --daemon

# Horizon (monitoring)
php artisan horizon
```

### Docker
```bash
docker-compose up -d
```

## 📈 Monitoring

### Laravel Telescope
```bash
# Włączenie w development
php artisan telescope:install
```

### Logi
- `storage/logs/laravel.log` - główne logi
- `storage/logs/banking.log` - operacje bankowe
- `storage/logs/ai.log` - operacje AI

## 🤝 Contributing

1. Fork projektu
2. Utwórz branch (`git checkout -b feature/amazing-feature`)
3. Commit zmian (`git commit -m 'Add amazing feature'`)
4. Push do branch (`git push origin feature/amazing-feature`)
5. Otwórz Pull Request

## 📄 Licencja

Ten projekt jest licencjonowany pod MIT License - zobacz plik [LICENSE](LICENSE) dla szczegółów.

## 🆘 Wsparcie

- **Dokumentacja**: [docs/](docs/)
- **Issues**: [GitHub Issues](https://github.com/your-username/finances-analyzer/issues)
- **Discussions**: [GitHub Discussions](https://github.com/your-username/finances-analyzer/discussions)

## 🔐 Bezpieczeństwo

- Wszystkie dane wrażliwe są szyfrowane
- OAuth 2.0 dla API bankowych
- Rate limiting na wszystkich endpointach
- Audit logging dla operacji finansowych
- GDPR compliance

## 📋 Roadmap

### v1.1
- [ ] Integracja z Revolut API
- [ ] Więcej formatów CSV
- [ ] Eksport do Google Sheets

### v1.2
- [ ] Mobilna aplikacja
- [ ] Zaawansowane wykresy
- [ ] Integracja z więcej bankami

### v1.3
- [ ] Machine Learning dla kategoryzacji
- [ ] Predykcje wydatków
- [ ] Integracja z systemami księgowymi

---

**Autor**: [Twoje Imię]  
**Wersja**: 1.0.0  
**Ostatnia aktualizacja**: 2024-01-01
