# Konfiguracja OAuth dla Google Drive

## Wymagania

Aby używać OAuth zamiast Service Account, potrzebujesz:

1. **Google Cloud Console** - projekt z włączonymi API
2. **OAuth 2.0 Client ID** - skonfigurowany w Google Cloud Console
3. **Redirect URI** - adres URL do obsługi callback

## Kroki konfiguracji

### 1. Google Cloud Console

1. Przejdź do [Google Cloud Console](https://console.cloud.google.com/)
2. Wybierz projekt `octadecimal`
3. Przejdź do **APIs & Services** > **Credentials**
4. Kliknij **Create Credentials** > **OAuth 2.0 Client ID**
5. Wybierz typ aplikacji: **Web application**
6. Dodaj **Authorized redirect URIs**:
   - `http://localhost:8000/auth/google/callback` (dla development)
   - `https://yourdomain.com/auth/google/callback` (dla production)

### 2. Konfiguracja w .env

Po utworzeniu OAuth Client ID, zaktualizuj plik `.env`:

```env
# Google OAuth Configuration
GOOGLE_DRIVE_CLIENT_ID=your_oauth_client_id_here
GOOGLE_DRIVE_CLIENT_SECRET=your_oauth_client_secret_here
GOOGLE_DRIVE_REDIRECT_URI=http://localhost:8000/auth/google/callback

# Zmień typ credentials na OAuth
GOOGLE_CREDENTIALS_TYPE=oauth
```

### 3. Konfiguracja Service Account dla OAuth Delegation

Jeśli chcesz używać Service Account z OAuth delegation:

1. W Google Cloud Console przejdź do **IAM & Admin** > **Service Accounts**
2. Znajdź Service Account `octadecimal-finances@octadecimal.iam.gserviceaccount.com`
3. Kliknij **Edit** > **Keys** > **Add Key** > **Create new key**
4. Wybierz **JSON** format
5. Pobierz plik i zaktualizuj credentials w `.env`

### 4. Testowanie

Po skonfigurowaniu OAuth, uruchom test:

```bash
php artisan test tests/Feature/GoogleDriveServiceTest.php
```

## Różnice między Service Account a OAuth

| Funkcjonalność | Service Account | OAuth |
|----------------|-----------------|-------|
| Upload plików | ❌ (wymaga Shared Drive) | ✅ |
| Tworzenie folderów | ✅ | ✅ |
| Pobieranie metadanych | ✅ | ✅ |
| Wyszukiwanie | ✅ | ✅ |
| Autoryzacja | Automatyczna | Wymaga user consent |

## Troubleshooting

### Błąd: "Service Accounts do not have storage quota"

**Rozwiązanie:** Użyj OAuth lub Shared Drive

### Błąd: "missing the required redirect URI"

**Rozwiązanie:** Sprawdź czy `GOOGLE_DRIVE_REDIRECT_URI` jest ustawiony w `.env`

### Błąd: "Google Drive authorization required"

**Rozwiązanie:** Przejdź do podanego URL i autoryzuj aplikację

## Przykład użycia

```php
// W kontrolerze
public function googleAuth()
{
    $googleDriveService = new GoogleDriveService();
    $authUrl = $googleDriveService->getAuthorizationUrl();
    
    return redirect($authUrl);
}

public function googleCallback(Request $request)
{
    $googleDriveService = new GoogleDriveService();
    $success = $googleDriveService->exchangeCodeForToken($request->get('code'));
    
    if ($success) {
        return redirect()->route('dashboard')->with('success', 'Google Drive connected!');
    }
    
    return redirect()->route('dashboard')->with('error', 'Google Drive connection failed');
}
``` 