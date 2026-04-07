<?php
use App\Models\Post;

$posts = Post::all();
$count = 0;

foreach ($posts as $post) {
    if (str_contains($post->image_url, '/storage/v1/s3/')) {
        $post->image_url = str_replace('/storage/v1/s3/', '/storage/v1/object/public/MyTest/', $post->image_url);
        $post->save();
        $count++;
    }
}

echo "Berhasil memperbaiki $count link foto lama!\n";
