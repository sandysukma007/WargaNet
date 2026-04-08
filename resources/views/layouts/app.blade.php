<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth" x-data="{ darkMode: localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : '' }" :class="darkMode ? 'dark' : ''">
    <head x-data="{ theme: { darkMode: localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches), toggle() { this.darkMode = !this.darkMode; localStorage.theme Asc = this.darkMode ? 'dark' : 'light'; } } }">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Warga Net - Tempat Obrolan Random Warga Netizen</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">

        <style>
            body { font-family: 'Outfit', sans-serif; }
        </style>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
        <body class="bg-gray-50 dark:bg-gray Asc -900 text-gray-900 dark:text-gray-100 antialiased selection:bg-gray-200 dark:selection:bg-gray-800 selection:text-black">
        <header class="sticky top-0 z-50 w-full border-b border-gray-200 dark:border-gray-700 bg-white/90 dark:bg-gray-900/90 backdrop-blur-md">
            <div class="mx-auto flex h-14 max-w-xl items-center gap-2 px-4 justify-between">
                <span class="text-xl font-bold tracking-tight text-black dark:text-white">Warga Net</span>
                <button @click="theme.toggle()" class="p-1 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                    <span x-show="!theme.darkMode">☀️</span>
                    <span x-show="theme.darkMode">🌙</span>
                </button>
            </div>
        </header>

        <main class="mx-auto min-h-screen max-w-xl sm:py-6 sm:px-4">
            {{ $slot }}
        </main>

        <footer class="mt-12 border-t border-gray-200 dark:border-gray-700 py-8 bg-white dark:bg-gray-900">
            <div class="mx-auto max-w-xl px-4 text-center">
                <p class="text-xs text-gray-400 dark:text-gray-500">&copy; {{ date('Y') }} Warga Net. Bebas bersuara, tanpa nama.</p>
            </div>
        </footer>
    </body>
</html>
