<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = ['image_url', 'caption', 'likes', 'dislikes', 'ip_address'];

    protected $casts = [
        'image_url' => 'array',
    ];

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function interactions()
    {
        return $this->hasMany(Interaction::class);
    }
}
