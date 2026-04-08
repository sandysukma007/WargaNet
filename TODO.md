# Fix Undefined $posts Error in Feed Component

## Steps:
- [x] 1. Edit resources/views/livewire/feed.blade.php: Replace both `$posts->hasMorePages()` → `$this->posts->hasMorePages()`
- [x] 2. Clear caches: `php artisan view:clear` (livewire:discover not available in Volt)
- [x] 3. Test locally and deploy to Vercel
- [x] 4. Mark complete and remove TODO.md
