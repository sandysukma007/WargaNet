<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Interaction extends Model
{
    protected $fillable = ['post_id', 'ip_address', 'type'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
