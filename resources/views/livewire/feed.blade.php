<?php

use App\Models\Post;
use App\Models\Interaction;
use App\Models\Comment;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Cache;

new class extends Component
{
    #[On('post-created')]
    #[Computed]
    public function posts()
    {
        // Don't show posts older than 1 month
        return Post::with(['comments'])
            ->where('created_at', '>=', now()->subMonth())
            ->latest()
            ->get();
    }

    #[Computed]
    public function userInteractions()
    {
        return Interaction::where('ip_address', request()->ip())
            ->pluck('type', 'post_id')
            ->toArray();
    }

    public function like($postId)
    {
        $ip = request()->ip();
        $existing = Interaction::where('post_id', $postId)->where('ip_address', $ip)->first();

        if ($existing && $existing->type === 'like') {
            // Unlike it
            $existing->delete();
            Post::find($postId)->decrement('likes');
            return;
        }

        if ($existing && $existing->type === 'dislike') {
            $existing->update(['type' => 'like']);
            Post::find($postId)->decrement('dislikes');
            Post::find($postId)->increment('likes');
        } else {
            Interaction::create(['post_id' => $postId, 'ip_address' => $ip, 'type' => 'like']);
            Post::find($postId)->increment('likes');
        }
    }

    public function dislike($postId)
    {
        $ip = request()->ip();
        $existing = Interaction::where('post_id', $postId)->where('ip_address', $ip)->first();

        if ($existing && $existing->type === 'dislike') {
            // Undislike it
            $existing->delete();
            Post::find($postId)->decrement('dislikes');
            return;
        }

        if ($existing && $existing->type === 'like') {
            $existing->update(['type' => 'dislike']);
            Post::find($postId)->decrement('likes');
            Post::find($postId)->increment('dislikes');
        } else {
            Interaction::create(['post_id' => $postId, 'ip_address' => $ip, 'type' => 'dislike']);
            Post::find($postId)->increment('dislikes');
        }
    }

    private function hasProfanity($text) {
        $badWords = ['anjing', 'bangsat', 'kontol', 'memek', 'ngentot', 'babi', 'tolol', 'goblok', 'peli', 'jembut', 'pukimak', 'lonte', 'pelacur'];
        $text = strtolower($text);
        foreach ($badWords as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }
        return false;
    }

    public function addComment($postId, $content)
    {
        if (trim($content) === '') return;

        $ip = request()->ip();

        if (Cache::has('banned_' . $ip)) {
            session()->flash('error', 'Anda diblokir sementara karena menggunakan kata kasar.');
            return;
        }

        if ($this->hasProfanity($content)) {
            Cache::put('banned_' . $ip, true, now()->addHour());
            session()->flash('error', 'Kata kasar terdeteksi! Anda diblokir dari memposting selama 1 jam.');
            return;
        }

        Comment::create([
            'post_id' => $postId,
            'content' => $content,
            'nickname' => 'Warga-' . substr(md5($ip), 0, 4)
        ]);
    }
};
?>

<div class="space-y-6 pb-20">
    <!-- Header/Create Post Section -->
    <livewire:create-post />

    <!-- Feed Section -->
    <div class="space-y-6">
        @foreach ($this->posts as $post)
            @php
                $userAction = $this->userInteractions[$post->id] ?? null;
            @endphp
            <article class="bg-white border-y sm:border sm:rounded-xl border-gray-200 overflow-hidden">
                <!-- Image Wrapper -->
                <div class="relative w-full bg-gray-100 flex justify-center items-center overflow-hidden max-h-[280px] sm:max-h-[420px] md:max-h-[560px]">
                    <img src="{{ $post->image_url }}" alt="Post image"
                         class="w-full h-auto max-h-[280px] sm:max-h-[420px] md:max-h-[560px] object-contain"
                         loading="lazy">
                </div>

                <!-- Post Content -->
                <div class="p-4 sm:p-5">
                    <!-- Interactions -->
                    <div class="flex items-center gap-4 mb-3">
                        <button wire:click="like({{ $post->id }})" class="flex items-center gap-1.5 transition-all {{ $userAction === 'like' ? 'scale-110' : 'hover:scale-105' }}">
                           <span class="text-xl leading-none">{{ $userAction === 'like' ? '❤️' : '🤍' }}</span>
                           <span class="text-sm font-semibold {{ $userAction === 'like' ? 'text-red-500' : 'text-gray-700' }}">{{ $post->likes }}</span>
                        </button>
                        
                        <button wire:click="dislike({{ $post->id }})" class="flex items-center gap-1.5 transition-all {{ $userAction === 'dislike' ? 'scale-110' : 'hover:scale-105' }}">
                           <span class="text-xl leading-none">{{ $userAction === 'dislike' ? '👎' : '👎🏻' }}</span>
                           <span class="text-sm font-semibold {{ $userAction === 'dislike' ? 'text-gray-900' : 'text-gray-400' }}">{{ $post->dislikes }}</span>
                        </button>
                    </div>

                    @if($post->caption)
                    <p class="text-gray-900 text-sm leading-relaxed mb-3"><span class="font-bold mr-1">{{ $post->comments->firstWhere('nickname', '!=', '') ? 'Warga-' . substr(md5($post->id), 0, 4) : 'anonim' }}</span> {{ $post->caption }}</p>
                    @endif

                    <!-- Comments List -->
                    <div class="space-y-1">
                        @if($post->comments->count() > 0)
                            <div class="text-gray-500 text-[13px] mb-2 mt-2">Lihat semua {{ $post->comments->count() }} komentar</div>
                        @endif
                        @foreach ($post->comments as $comment)
                            <p class="text-sm text-gray-900 leading-tight">
                                <span class="font-bold mr-1">{{ $comment->nickname }}</span>{{ $comment->content }}
                            </p>
                        @endforeach

                        <div class="text-[11px] text-gray-400 uppercase tracking-wide mt-2 mb-1">
                            {{ $post->created_at->format('d M Y') }}
                        </div>

                        <!-- Comment Input -->
                        @if (session()->has('error'))
                            <div class="text-xs font-semibold text-red-500 mt-2 mb-2">{{ session('error') }}</div>
                        @endif
                        <div class="relative mt-2 pt-3 border-t border-gray-100">
                            <input type="text"
                                   placeholder="Tambahkan komentar..."
                                   x-on:keydown.enter="$wire.addComment({{ $post->id }}, $event.target.value); $event.target.value = ''"
                                   class="w-full bg-transparent text-sm focus:ring-0 border-none outline-none p-0 text-gray-900 placeholder-gray-400">
                        </div>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</div>