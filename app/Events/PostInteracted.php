<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PostInteracted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $postId;
    public $type; // 'like', 'dislike', 'comment'
    public $data; // detail (e.g. comment text, IP hash)

    public function __construct($postId, $type, $data = [])
    {
        $this->postId = $postId;
        $this->type = $type;
        $this->data = $data;
Log::info('Broadcasting PostInteracted', ['postId' => $postId, 'type' => $type]);
    }

    public function broadcastOn()
    {
        return new Channel('posts');
    }

    public function broadcastAs()
    {
        return 'post.interacted';
    }

    public function broadcastWith()
    {
        return [
            'postId' => $this->postId,
            'type' => $this->type,
'likes' => \App\Models\Post::find($this->postId)->likes ?? 0,
'dislikes' => \App\Models\Post::find($this->postId)->dislikes ?? 0,
            'data' => $this->data,
        ];
    }
}

