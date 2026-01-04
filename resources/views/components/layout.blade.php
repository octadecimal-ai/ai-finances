<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'AI Finances')</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    
    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] min-h-screen">
    <div class="flex">
        <!-- Lewe menu -->
        <aside id="sidebar" class="w-64 bg-white dark:bg-[#161615] border-r border-[#e3e3e0] dark:border-[#3E3E3A] min-h-screen fixed left-0 top-0 z-10 overflow-y-auto transition-all duration-300">
            <div class="p-4 min-h-screen flex flex-col">
                <div class="flex items-center justify-between mb-8">
                    <a href="{{ route('welcome') }}" class="block sidebar-title">
                        <h1 class="text-xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">AI Finances</h1>
                    </a>
                    <button id="sidebar-toggle" class="p-2 hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a] rounded-sm transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
                
                <nav class="space-y-2">
                    <a href="{{ route('transactions.index') }}" 
                       class="flex items-center px-4 py-2 text-sm rounded-sm transition-colors {{ request()->routeIs('transactions.*') ? 'bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A]' : 'text-[#1b1b18] dark:text-[#EDEDEC] hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a]' }}">
                        <span class="mr-3 sidebar-icon">💳</span>
                        <span class="sidebar-text">Historia płatności</span>
                    </a>
                    <a href="{{ route('invoices.index') }}" 
                       class="flex items-center px-4 py-2 text-sm rounded-sm transition-colors {{ request()->routeIs('invoices.*') ? 'bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A]' : 'text-[#1b1b18] dark:text-[#EDEDEC] hover:bg-[#FDFDFC] dark:hover:bg-[#0a0a0a]' }}">
                        <span class="mr-3 sidebar-icon">📄</span>
                        <span class="sidebar-text">Faktury</span>
                    </a>
                </nav>
            
                <div class="mt-auto pt-4 border-t border-[#e3e3e0] dark:border-[#3E3E3A]">
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 text-sm rounded-sm border border-[#19140035] dark:border-[#3E3E3A] hover:border-[#1915014a] dark:hover:border-[#62605b] transition-colors text-[#1b1b18] dark:text-[#EDEDEC]">
                                <span class="sidebar-text">Wyloguj</span>
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="block w-full px-4 py-2 text-sm text-center rounded-sm border border-transparent hover:border-[#19140035] dark:hover:border-[#3E3E3A] transition-colors text-[#1b1b18] dark:text-[#EDEDEC]">
                            <span class="sidebar-text">Zaloguj</span>
                        </a>
                    @endauth
                </div>
            </div>
        </aside>

        <!-- Główna zawartość -->
        <div id="main-content" class="flex-1 ml-64 transition-all duration-300">
            <main class="w-full px-4 sm:px-6 lg:px-8 py-8">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleButton = document.getElementById('sidebar-toggle');
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        
        function toggleSidebar() {
            const collapsed = sidebar.classList.toggle('w-16');
            sidebar.classList.toggle('w-64');
            
            if (collapsed) {
                mainContent.classList.remove('ml-64');
                mainContent.classList.add('ml-16');
                document.querySelectorAll('.sidebar-text').forEach(el => el.style.display = 'none');
                document.querySelectorAll('.sidebar-title').forEach(el => el.style.display = 'none');
                localStorage.setItem('sidebarCollapsed', 'true');
            } else {
                mainContent.classList.remove('ml-16');
                mainContent.classList.add('ml-64');
                document.querySelectorAll('.sidebar-text').forEach(el => el.style.display = '');
                document.querySelectorAll('.sidebar-title').forEach(el => el.style.display = '');
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        }
        
        if (toggleButton) {
            toggleButton.addEventListener('click', toggleSidebar);
        }
        
        // Przywróć stan z localStorage
        if (isCollapsed) {
            sidebar.classList.remove('w-64');
            sidebar.classList.add('w-16');
            mainContent.classList.remove('ml-64');
            mainContent.classList.add('ml-16');
            document.querySelectorAll('.sidebar-text').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.sidebar-title').forEach(el => el.style.display = 'none');
        }
    });
    </script>

    @stack('scripts')
</body>
</html>

