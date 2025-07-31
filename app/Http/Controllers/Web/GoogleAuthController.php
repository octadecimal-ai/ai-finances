<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Google\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleAuthController extends Controller
{
    private ?GoogleDriveService $googleDriveService = null;

    public function __construct()
    {
        // Nie inicjalizujemy GoogleDriveService w konstruktorze
        // Zrobimy to w metodach, które tego potrzebują
    }

    private function getGoogleDriveService(): GoogleDriveService
    {
        if ($this->googleDriveService === null) {
            $this->googleDriveService = new GoogleDriveService();
        }
        return $this->googleDriveService;
    }

    /**
     * Przekieruj użytkownika do Google OAuth
     */
    public function redirect()
    {
        try {
            // Utwórz klienta Google bez inicjalizacji
            $client = new \Google\Client();
            $client->setApplicationName(config('google.drive.application_name', 'Finances App'));
            $client->setScopes(config('google.drive.scopes', [
                'https://www.googleapis.com/auth/drive',
                'https://www.googleapis.com/auth/drive.file',
            ]));
            
            // Ustaw OAuth credentials
            $client->setClientId(config('google.drive.client_id'));
            $client->setClientSecret(config('google.drive.client_secret'));
            $client->setRedirectUri(config('google.drive.redirect_uri'));
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');
            
            $authUrl = $client->createAuthUrl();
            return redirect($authUrl);
            
        } catch (\Exception $e) {
            Log::error('Google OAuth redirect failed', [
                'error' => $e->getMessage()
            ]);
            
            return redirect('/')
                ->with('error', 'Nie można zainicjować autoryzacji Google Drive: ' . $e->getMessage());
        }
    }

    /**
     * Obsłuż callback z Google OAuth
     */
    public function callback(Request $request)
    {
        try {
            $code = $request->get('code');
            
            if (!$code) {
                return redirect('/')
                    ->with('error', 'Brak kodu autoryzacji z Google');
            }

            // Utwórz klienta Google bezpośrednio
            $client = new \Google\Client();
            $client->setApplicationName(config('google.drive.application_name', 'Finances App'));
            $client->setScopes(config('google.drive.scopes', [
                'https://www.googleapis.com/auth/drive',
                'https://www.googleapis.com/auth/drive.file',
            ]));
            
            // Ustaw OAuth credentials
            $client->setClientId(config('google.drive.client_id'));
            $client->setClientSecret(config('google.drive.client_secret'));
            $client->setRedirectUri(config('google.drive.redirect_uri'));
            $client->setAccessType('offline');
            $client->setPrompt('select_account consent');
            
            // Wymień kod na token
            $token = $client->fetchAccessTokenWithAuthCode($code);
            
            if (isset($token['error'])) {
                Log::error('Google OAuth token exchange failed', [
                    'error' => $token['error'],
                    'code' => $code
                ]);
                
                return redirect('/')
                    ->with('error', 'Błąd wymiany kodu na token: ' . $token['error']);
            }
            
            // Zapisz token
            $tokenPath = storage_path('app/google_token.json');
            file_put_contents($tokenPath, json_encode($token));
            
            // Test połączenia
            $client->setAccessToken($token);
            $driveService = new \Google\Service\Drive($client);
            
            try {
                $about = $driveService->about->get(['fields' => 'user']);
                $userName = $about->getUser()->getDisplayName();
                
                return redirect('/')
                    ->with('success', 'Google Drive połączony pomyślnie! Użytkownik: ' . $userName);
                    
            } catch (\Exception $e) {
                Log::error('Google Drive test failed', [
                    'error' => $e->getMessage()
                ]);
                
                return redirect('/')
                    ->with('success', 'Google Drive połączony pomyślnie! (test połączenia nie powiódł się)');
            }
            
        } catch (\Exception $e) {
            Log::error('Google OAuth callback failed', [
                'error' => $e->getMessage(),
                'code' => $request->get('code')
            ]);
            
            return redirect('/')
                ->with('error', 'Błąd autoryzacji Google Drive: ' . $e->getMessage());
        }
    }

    /**
     * Test połączenia z Google Drive
     */
    public function test()
    {
        try {
            $connection = $this->getGoogleDriveService()->testConnection();
            $userInfo = $this->getGoogleDriveService()->getUserInfo();
            $storageUsage = $this->getGoogleDriveService()->getStorageUsage();
            
            return response()->json([
                'success' => true,
                'connection' => $connection,
                'user' => $userInfo,
                'storage' => $storageUsage,
                'message' => 'Google Drive działa poprawnie'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Google Drive test failed', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pokaż status połączenia z Google Drive
     */
    public function status()
    {
        try {
            $connection = $this->getGoogleDriveService()->testConnection();
            $userInfo = $this->getGoogleDriveService()->getUserInfo();
            $storageUsage = $this->getGoogleDriveService()->getStorageUsage();
            
            return view('google.status', [
                'connected' => $connection,
                'user' => $userInfo,
                'storage' => $storageUsage
            ]);
            
        } catch (\Exception $e) {
            return view('google.status', [
                'connected' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
} 