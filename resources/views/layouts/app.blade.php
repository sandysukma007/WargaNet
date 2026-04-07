<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? 'SuaraDinding - Sosmed Anonim' }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">

        <style>
            body { font-family: 'Outfit', sans-serif; }
        </style>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-slate-50 text-slate-900 antialiased selection:bg-indigo-100 selection:text-indigo-700">
        <header class="sticky top-0 z-50 w-full border-b border-slate-200 bg-white/80 backdrop-blur-md">
            <div class="mx-auto flex h-16 max-w-5xl items-center justify-between px-4">
                <div class="flex items-center gap-2">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 font-bold text-white shadow-lg shadow-indigo-200">
                        SD
                    </div>
                    <span class="text-xl font-bold tracking-tight text-slate-800">SuaraDinding</span>
                </div>
                <div class="text-sm font-medium text-slate-500">
                    Bebas Bersuara, Tanpa Nama.
                </div>
            </div>
        </header>

        <main class="mx-auto min-h-screen max-w-5xl p-4 sm:p-6 lg:p-8">
            {{ $slot }}
        </main>

        <footer class="mt-20 border-t border-slate-200 py-10">
            <div class="mx-auto max-w-5xl px-4 text-center">
                <p class="text-sm text-slate-400">&copy; 2026 SuaraDinding. Dibuat dengan cinta & Laravel 12.</p>
            </div>
        </footer>
    </body>
</html>
