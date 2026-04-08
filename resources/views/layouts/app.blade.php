<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth"

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
        <body class="bg-gray-50 text-gray-900 antialiased selection:bg-gray-200 selection:text-black transition-colors duration-300">
        <header class="sticky top-0 z-50 w-full border-b border-gray-200 bg-white/90 backdrop-blur-md">
            <div class="mx-auto flex h-14 max-w-xl items-center gap-2 px-4 justify-between">
                <span class="text-xl font-bold tracking-tight text-black">Warga Net</span>
                <!-- Dark mode toggle HIDDEN per user request -->
                <!-- <button x-data="{ dark: false, toggle() { document.documentElement.classList.toggle('dark') } }" @click="toggle()" class="p-1 rounded-full hover:bg-gray-200 transition-all duration-200 hover:rotate-12 hover:scale-110">
                    <span x-show="!dark" class="transition-opacity">☀️</span>
                    <span x-show="dark" class="transition-opacity">🌙</span>
                </button> -->
            </div>
        </header>

        <main class="mx-auto min-h-screen max-w-xl sm:py-6 sm:px-4">
            {{ $slot }}
        </main>

        <footer class="mt-12 border-t border-gray-200 py-8 bg-white">
            <div class="mx-auto max-w-xl px-4 text-center">
                <p class="text-xs text-gray-400">&copy; {{ date('Y') }} Warga Net. Bebas bersuara, tanpa nama.</p>
            </div>
        </footer>
    </body>
</html>
