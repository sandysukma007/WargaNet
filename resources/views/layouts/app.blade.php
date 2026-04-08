<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth"
x-data="{
        darkMode: localStorage.getItem('theme') === 'dark' ||
                 (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches),
        toggle() {
            this.darkMode = !this.darkMode;
            localStorage.setItem('theme', this.darkMode ? 'dark' : 'light');
            if (this.darkMode) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
    }"
    x-init="toggle()"
    :class="darkMode ? 'dark' : ''">
    <head>

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
        <body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 antialiased selection:bg-gray-200 dark:selection:bg-gray-800 selection:text-black transition-colors duration-300">
        <header class="sticky top-0 z-50 w-full border-b border-gray-200 dark:border-gray-700 bg-white/90 dark:bg-gray-900/90 backdrop-blur-md">
            <div class="mx-auto flex h-14 max-w-xl items-center gap-2 px-4 justify-between">
                <span class="text-xl font-bold tracking-tight text-black dark:text-white">Warga Net</span>
                <button @click="toggle()" class="p-1 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-all duration-200 hover:rotate-12 hover:scale-110">
                    <span x-show="!darkMode" class="transition-opacity">☀️</span>
                    <span x-show="darkMode" class="transition-opacity">🌙</span>
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
