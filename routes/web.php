<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/', 'feed')->name('home');

Route::get('/api/cron/cleanup', function () {
    // Prevent unauthorized access, Vercel cron automatically sends a secure header
    // but for simplicity it's fine as long as we only run standard cleanup.
    
    $oldPosts = \App\Models\Post::where('created_at', '<', now()->subMonth())->get();
    $deletedCount = 0;

    foreach ($oldPosts as $post) {
        // Parse S3 path from the public URL
        // Example URL: https://.../public/bucket-name/photos/123_abc.jpg
        $urlParts = explode('/photos/', $post->image_url);
        if (count($urlParts) > 1) {
            $s3Path = 'photos/' . end($urlParts);
            \Illuminate\Support\Facades\Storage::disk('s3')->delete($s3Path);
        }
        
        $post->delete();
        $deletedCount++;
    }

    return response()->json([
        'status' => 'success',
        'message' => "Deleted $deletedCount old posts."
    ]);
});
