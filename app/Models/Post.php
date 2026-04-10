<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = ['image_url', 'caption', 'likes', 'dislikes', 'ip_address'];

    public function getImageUrlAttribute($value)
    {
        if (empty($value)) {
            return [];
        }

        // If it's already an array (e.g. from casting or manual decode)
        if (is_array($value)) {
            return $value;
        }

        // Try to decode JSON
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // If it's a string (old format), wrap it in an array
        return [$value];
    }

    public function setImageUrlAttribute($value)
    {
        $this->attributes['image_url'] = is_array($value) ? json_encode($value) : $value;
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function interactions()
    {
        return $this->hasMany(Interaction::class);
    }
}
