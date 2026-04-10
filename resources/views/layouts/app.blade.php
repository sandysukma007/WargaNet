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
                <div class="flex items-center gap-2">
                    <!-- Bell Notifications -->
                    <div x-data="{
                        notifications: [],
                        unreadCount: 0,
                        showDropdown: false,
                        init() {
                            Echo.channel('posts')
                                .listen('post.interacted', (e) => {
                                    this.notifications.unshift({
                                        postId: e.postId,
                                        type: e.type,
                                        data: e.data,
                                        time: new Date().toLocaleTimeString()
                                    });
                                    this.unreadCount++;
                                    // Play sound
                                    new Audio('/sounds/notification.mp3').play().catch(() => {});
                                })
                                .listen('post.created', (e) => {
                                    this.notifications.unshift({
                                        postId: e.id,
                                        type: 'new-post',
                                        data: { caption: e.caption },
                                        time: new Date().toLocaleTimeString()
                                    });
                                    this.unreadCount++;
                                });
                        },
                        markAsRead() {
                            this.unreadCount = 0;
                            this.notifications = this.notifications.slice(0, 10);
                        }
                    }" @click.away="showDropdown = false" class="relative">
                        <button @click="showDropdown = !showDropdown" class="relative p-1 rounded-full hover:bg-gray-200 transition-all duration-200 hover:scale-110">
                            <span class="text-xl">🔔</span>
                            <span x-show="unreadCount > 0" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center text-xs font-bold" x-text="unreadCount"></span>
                        </button>
<div x-show="showDropdown" x-transition class="notification-dropdown">
                            <div class="px-4 py-2 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                                <h3 class="font-semibold text-gray-900 dark:text-white">Notifikasi</h3>
                                <button @click="markAsRead(); showDropdown = false" class="text-sm text-blue-600 hover:text-blue-500 font-medium">Tandai semua dibaca</button>
                            </div>
                            <template x-if="notifications.length === 0">
                                <div class="px-4 py-4 text-center text-gray-500 dark:text-gray-400 text-sm">
                                    Belum ada notifikasi
                                </div>
                            </template>
                            <template x-for="notif in notifications.slice(0,10)" :key="notif.time">
<div class="notification-item">
                                    <div class="flex items-start gap-3">
                                        <span class="flex-shrink-0 mt-0.5 text-lg">
                                            <template x-if="notif.type === 'like'">
                                                ❤️
                                            </template>
                                            <template x-if="notif.type === 'dislike'">
                                                👎
                                            </template>
                                            <template x-if="notif.type === 'comment'">
                                                💬
                                            </template>
                                            <template x-if="notif.type === 'new-post'">
                                                🆕
                                            </template>
                                        </span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="notif.type === 'comment' ? `${notif.data.nickname} berkomentar: ${notif.data.content}` : notif.type.includes('like') ? 'Post kamu mendapat like' : notif.type === 'new-post' ? 'Post baru dibuat' : 'Post mendapat dislike'"></p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5" x-text="notif.time"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
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
