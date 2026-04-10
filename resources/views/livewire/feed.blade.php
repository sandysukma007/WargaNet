<?php

use App\Models\Post;
use App\Models\Interaction;
use App\Models\Comment;
use App\Models\Ban;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Event;
use Livewire\Attributes\On as LivewireOn;

new class extends Component
{
    use WithPagination;

    public $cursorName = 'posts-cursor';

    #[On('post-created')]
    #[Computed]
    public function posts()
    {
        return Post::with(['comments'])
            ->where('created_at', '>=', now()->subMonth())
            ->latest()
            ->cursorPaginate(12, ['*'], $this->cursorName);
    }

    public function nextPage()
    {
        $this->resetPage($this->cursorName);
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

    private function hasProfanity($text): bool
    {
        $badWords = ['anjing', 'bangsat', 'kontol', 'memek', 'ngentot', 'babi', 'tolol', 'goblok', 'peli', 'jembut', 'pukimak', 'lonte', 'pelacur'];
        $text = strtolower($text);
        foreach ($badWords as $word) {
            if (str_contains($text, $word)) return true;
        }
        return false;
    }

    public function addComment($postId, $content)
    {
        if (trim($content) === '') return;

        $ip = request()->ip();

        if (Ban::isBanned($ip)) {
            $this->dispatch('comment_error', message: 'Anda masih diblokir karena sebelumnya menggunakan kata kasar.', postId: $postId);
            return;
        }

        if ($this->hasProfanity($content)) {
            Ban::banIp($ip);
            $this->dispatch('comment_error', message: 'Kata kasar terdeteksi! Anda diblokir 1 jam.', postId: $postId);
            return;
        }

        $comment = Comment::create([
            'post_id'  => $postId,
            'content'  => $content,
            'nickname' => 'Warga-' . substr(md5($ip), 0, 4),
        ]);
    }

    /**
     * Render caption with hashtags styled as blue links.
     */
    public function renderCaption(string $caption): string
    {
        return preg_replace(
            '/#([a-zA-Z0-9_\-]+)/u',
            '<span class="text-blue-500 font-medium">#$1</span>',
            e($caption)
        );
    }
};
?>
<div class="space-y-8 pb-20 px-1 sm:px-0">
    <div class="mb-8">
        <livewire:create-post />
    </div>
    <div class="space-y-6">
        @foreach ($this->posts as $post)
            @php
                $userAction = $this->userInteractions[$post->id] ?? null;
                $nickname  = 'Warga-' . substr(md5($post->ip_address ?? $post->id), 0, 4);
            @endphp
            <article class="post-card max-w-sm mx-auto rounded-xl shadow-md bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm hover:shadow-2xl transition-all duration-300">
                {{-- Card Header --}}
                <div class="px-3 py-2 flex items-center justify-between shadow-sm bg-white dark:bg-gray-900">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-gradient-to-tr from-blue-500 to-indigo-500 flex items-center justify-center text-white text-[10px] font-bold shadow-sm">
                            {{ strtoupper(substr($nickname, 6, 1)) }}
                        </div>
                        <div class="flex flex-col -space-y-0.5">
                            <span class="font-bold text-xs text-gray-900 dark:text-gray-100">{{ $nickname }}</span>
                            <span class="text-[9px] text-gray-400 dark:text-gray-500 font-medium">
                                {{ $post->created_at->diffForHumans(null, true) }} ago
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Image Slider (Super Compact) -->
                @php
                    $images = $post->image_url;
                @endphp
                @if (count($images) > 0)
                <div x-data="{
                    activeSlide: 0,
                    slides: {{ count($images) }},
                    next() { this.activeSlide = (this.activeSlide + 1) % this.slides },
                    prev() { this.activeSlide = (this.activeSlide - 1 + this.slides) % this.slides }
                }" class="relative w-full bg-gray-50 dark:bg-gray-800/50 overflow-hidden group">

                    <div class="flex transition-transform duration-500 ease-in-out" :style="'transform: translateX(-' + (activeSlide * 100) + '%)'">
                        @foreach ($images as $url)
                            <div class="w-full flex-shrink-0 flex justify-center items-center bg-gray-50 dark:bg-gray-800/50 min-h-[150px] sm:min-h-[200px] max-h-[250px]">
                                <img src="{{ $url }}" alt=""
                                     class="w-full h-auto max-h-[250px] object-contain"
                                     loading="lazy">
                            </div>
                        @endforeach
                    </div>

                    @if (count($images) > 1)
                        <div class="absolute inset-0 flex items-center justify-between px-2 pointer-events-none">
                            <button type="button" @click="prev" class="pointer-events-auto bg-white/80 dark:bg-black/30 text-gray-800 dark:text-white p-1.5 rounded-full shadow-sm transition-all hover:scale-110 opacity-0 group-hover:opacity-100">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" /></svg>
                            </button>
                            <button type="button" @click="next" class="pointer-events-auto bg-white/80 dark:bg-black/30 text-gray-800 dark:text-white p-1.5 rounded-full shadow-sm transition-all hover:scale-110 opacity-0 group-hover:opacity-100">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
                            </button>
                        </div>

                        <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1 z-10 bg-black/10 dark:bg-white/10 px-1.5 py-1 rounded-full backdrop-blur-sm">
                            <template x-for="i in slides" :key="i-1">
                                <div class="h-1 w-1 rounded-full transition-all duration-300" :class="activeSlide === (i-1) ? 'bg-blue-500 w-3' : 'bg-gray-400 dark:bg-gray-500'"></div>
                            </template>
                        </div>
                    @endif
                </div>
                @endif

                <!-- Post Body -->
                <div class="p-2.5">
                    @if($post->caption)
                        <div class="text-[13px] leading-relaxed text-gray-800 dark:text-gray-200 mb-3 font-medium">
                            {!! $this->renderCaption($post->caption) !!}
                        </div>
                    @endif

                    <!-- Interactions -->
                    <div class="flex items-center gap-2 mb-3">
                        <button wire:click="like({{ $post->id }})" class="action-btn {{ $userAction === 'like' ? 'action-btn-active' : '' }}">
                           <span class="text-base">{{ $userAction === 'like' ? '❤️' : '🤍' }}</span>
                           <span class="text-[11px] font-bold">{{ $post->likes }}</span>
                        </button>

                        <button wire:click="dislike({{ $post->id }})" class="action-btn {{ $userAction === 'dislike' ? 'action-btn-active' : '' }}">
                           <span class="text-base">{{ $userAction === 'dislike' ? '👎' : '👎🏻' }}</span>
                           <span class="text-[11px] font-bold">{{ $post->dislikes }}</span>
                        </button>
                    </div>

                    <!-- Comments -->
                    <div class="space-y-3 pt-4">
                        <div class="space-y-1.5">
                            @foreach ($post->comments->take(2) as $comment)
                                <div class="text-[11px] leading-relaxed flex items-start gap-1.5">
                                    <span class="font-bold text-gray-900 dark:text-gray-100">{{ $comment->nickname }}</span>
                                    <span class="text-gray-600 dark:text-gray-400">{{ $comment->content }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Compact Reply --}}
                        <div class="relative flex items-center gap-2" x-data="{ commentText: '' }">
                            <input type="text"
                                   x-model="commentText"
                                   placeholder="Balas..."
                                   class="comment-input"
                                   x-on:keydown.enter="if(commentText.trim()) { $wire.addComment({{ $post->id }}, commentText); commentText = '' }">

                            <button
                                @click="if(commentText.trim()) { $wire.addComment({{ $post->id }}, commentText); commentText = '' }"
                                x-show="commentText.trim().length > 0"
                                class="text-blue-600 dark:text-blue-400 font-bold text-[11px] pr-1"
                            >
                                Kirim
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        @endforeach

        @if ($this->posts->hasMorePages())
             <div wire:infinite-scroll="nextPage" class="text-center py-8">
                <div class="animate-spin inline-block w-6 h-6 border-2 rounded-full border-gray-200 border-t-blue-500 mx-auto"></div>
            </div>
        @endif
    </div>
</div>
