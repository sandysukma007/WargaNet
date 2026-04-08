# TODO: Implementasi Notifikasi Real-time dengan Laravel Reverb

## Status CreatePost Fix:
- [x] Step 1: Edit create-post.blade.php (confirmed working, 11MB → compressed OK di Supabase)
- [x] Step 2: Test lokal ✅ (`php artisan serve` running)
- [ ] Step 3: Deploy Vercel
- [ ] Step 4: Clear caches

## Plan Notifikasi Real-time (Approved):
1. [ ] Install Laravel Reverb (`php artisan install:broadcasting`)
2. [ ] Buat Events: PostInteracted.php, PostCreated.php
3. [ ] Update routes/channels.php (public 'posts' channel)
4. [ ] Edit feed.blade.php Volt: trigger broadcasts di like/dislike/addComment
5. [ ] Edit app.blade.php: Tambah bell icon + dropdown Alpine/Livewire
6. [ ] Update resources/js/app.js: Reverb/Echo client
7. [ ] Test: `php artisan reverb:start --debug`
8. [ ] Deploy & Update TODO

**Next Step:** Install Reverb → konfirmasi output sebelum lanjut edit files.
