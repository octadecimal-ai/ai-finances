<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Drive Status - Finances</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-900 mb-8">Status Google Drive</h1>
            
            @if(isset($error))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <strong class="font-bold">Błąd:</strong>
                    <span class="block sm:inline">{{ $error }}</span>
                </div>
            @endif

            @if($connected ?? false)
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <strong class="font-bold">✓ Połączony z Google Drive</strong>
                </div>

                @if(isset($user))
                    <div class="bg-white shadow rounded-lg p-6 mb-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Informacje o użytkowniku</h2>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="font-medium">Nazwa:</span>
                                <span>{{ $user['name'] ?? 'Nieznany' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Email:</span>
                                <span>{{ $user['email'] ?? 'Nieznany' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">ID:</span>
                                <span>{{ $user['id'] ?? 'Nieznany' }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                @if(isset($storage))
                    <div class="bg-white shadow rounded-lg p-6 mb-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Przestrzeń dyskowa</h2>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="font-medium">Użyte:</span>
                                <span>{{ \App\Helpers\NumberHelper::formatBytes($storage['used'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Dostępne:</span>
                                <span>{{ \App\Helpers\NumberHelper::formatBytes($storage['available'] ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Łącznie:</span>
                                <span>{{ \App\Helpers\NumberHelper::formatBytes($storage['total'] ?? 0) }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-6">
                    <p class="font-medium">Google Drive jest gotowy do użycia!</p>
                    <p class="text-sm mt-1">Możesz teraz przesyłać pliki, tworzyć foldery i zarządzać danymi.</p>
                </div>

            @else
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-6">
                    <strong class="font-bold">⚠️ Nie połączony z Google Drive</strong>
                    <p class="mt-1">Aby używać Google Drive, musisz się autoryzować.</p>
                </div>

                <div class="bg-white shadow rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Autoryzacja Google Drive</h2>
                    <p class="text-gray-600 mb-4">
                        Aby połączyć się z Google Drive, kliknij przycisk poniżej. Zostaniesz przekierowany do Google, 
                        gdzie będziesz mógł autoryzować aplikację.
                    </p>
                    <a href="{{ route('google.redirect') }}" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Połącz z Google Drive
                    </a>
                </div>
            @endif

            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Test połączenia</h2>
                <p class="text-gray-600 mb-4">
                    Sprawdź aktualny status połączenia z Google Drive API.
                </p>
                <button onclick="testConnection()" 
                        class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Testuj połączenie
                </button>
                <div id="test-result" class="mt-4"></div>
            </div>

            <div class="mt-8 text-center">
                <a href="/" class="text-blue-600 hover:text-blue-800 underline">
                    ← Powrót do strony głównej
                </a>
            </div>
        </div>
    </div>

    <script>
        function testConnection() {
            const resultDiv = document.getElementById('test-result');
            resultDiv.innerHTML = '<div class="text-blue-600">Testowanie połączenia...</div>';
            
            fetch('/auth/google/test')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = `
                            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                                <strong class="font-bold">✓ Połączenie działa!</strong>
                                <p class="mt-1">${data.message}</p>
                                <p class="text-sm mt-2">Użytkownik: ${data.user?.name || 'Nieznany'}</p>
                            </div>
                        `;
                    } else {
                        resultDiv.innerHTML = `
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                                <strong class="font-bold">❌ Błąd połączenia</strong>
                                <p class="mt-1">${data.error}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = `
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            <strong class="font-bold">❌ Błąd testu</strong>
                            <p class="mt-1">${error.message}</p>
                        </div>
                    `;
                });
        }
    </script>
</body>
</html> 