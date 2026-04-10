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
    <div class="space-y-8">
        @foreach ($this->posts as $post)
            @php
                $userAction = $this->userInteractions[$post->id] ?? null;
                $nickname  = 'Warga-' . substr(md5($post->ip_address ?? $post->id), 0, 4);
            @endphp
            <article class="post-card">
                {{-- Card Header --}}
                <div class="px-4 py-3 flex items-center justify-between border-b border-gray-50 dark:border-gray-800/50">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-500 to-indigo-500 flex items-center justify-center text-white text-xs font-bold">
                            {{ substr($nickname, 6, 1) }}
                        </div>
                        <span class="font-bold text-sm text-gray-900 dark:text-gray-100">{{ $nickname }}</span>
                    </div>
                    <span class="text-[10px] font-medium text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-800 px-2 py-0.5 rounded-full">
                        {{ $post->created_at->diffForHumans(null, true) }}
                    </span>
                </div>

                <!-- Multi-image Slider (Compact) -->
                @php
                    $images = $post->image_url; 
                @endphp
                @if (count($images) > 0)
                <div x-data="{ 
                    activeSlide: 0, 
                    slides: {{ count($images) }},
                    next() { this.activeSlide = (this.activeSlide + 1) % this.slides },
                    prev() { this.activeSlide = (this.activeSlide - 1 + this.slides) % this.slides }
                }" class="relative w-full bg-gray-50 dark:bg-gray-800/50 overflow-hidden group border-b border-gray-50 dark:border-gray-800/50">
                    
                    <div class="flex transition-transform duration-300 ease-out" :style="'transform: translateX(-' + (activeSlide * 100) + '%)'">
                        @foreach ($images as $url)
                            <div class="w-full flex-shrink-0 flex justify-center items-center min-h-[200px] sm:min-h-[280px] max-h-[350px]">
                                <img src="{{ $url }}" alt=""
                                     class="w-full h-auto max-h-[350px] object-contain"
                                     loading="lazy">
                            </div>
                        @endforeach
                    </div>

                    @if (count($images) > 1)
                        <button type="button" @click="prev" class="absolute left-2 top-1/2 -translate-y-1/2 bg-white/80 dark:bg-black/40 text-gray-800 dark:text-white p-1 rounded-full shadow-sm backdrop-blur-sm transition opacity-0 group-hover:opacity-100 z-10">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <button type="button" @click="next" class="absolute right-2 top-1/2 -translate-y-1/2 bg-white/80 dark:bg-black/40 text-gray-800 dark:text-white p-1 rounded-full shadow-sm backdrop-blur-sm transition opacity-0 group-hover:opacity-100 z-10">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>

                        <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1 z-10">
                            <template x-for="i in slides" :key="i-1">
                                <div class="h-1 w-1 rounded-full transition-all" :class="activeSlide === (i-1) ? 'bg-blue-600 w-2.5' : 'bg-gray-300 dark:bg-gray-600'"></div>
                            </template>
                        </div>
                    @endif
                </div>
                @endif

                <!-- Post Content -->
                <div class="p-4">
                    <!-- Interactions -->
                    <div class="flex items-center gap-3 mb-3">
                        <button wire:click="like({{ $post->id }})" class="action-btn {{ $userAction === 'like' ? 'action-btn-active' : '' }}">
                           <span class="text-lg leading-none">{{ $userAction === 'like' ? '❤️' : '🤍' }}</span>
                           <span class="text-xs font-bold">{{ $post->likes }}</span>
                        </button>

                        <button wire:click="dislike({{ $post->id }})" class="action-btn {{ $userAction === 'dislike' ? 'action-btn-active' : '' }}">
                           <span class="text-lg leading-none">{{ $userAction === 'dislike' ? '👎' : '👎🏻' }}</span>
                           <span class="text-xs font-bold">{{ $post->dislikes }}</span>
                        </button>
                    </div>

                    @if($post->caption)
                        <div class="text-sm leading-relaxed text-gray-800 dark:text-gray-200 mb-3">
                            {!! $this->renderCaption($post->caption) !!}
                        </div>
                    @endif

                    <!-- Comments Section -->
                    <div class="space-y-2 pt-3 border-t border-gray-50 dark:border-gray-800/50">
                        @if($post->comments->count() > 3)
                            <button class="text-gray-400 dark:text-gray-500 text-xs font-semibold hover:text-blue-500 transition-colors">
                                Lihat {{ $post->comments->count() - 3 }} komentar lainnya
                            </button>
                        @endif
                        
                        <div class="space-y-1.5">
                            @foreach ($post->comments->take(3) as $comment)
                                <div class="text-xs leading-relaxed flex items-start gap-1.5">
                                    <span class="font-bold text-gray-900 dark:text-gray-100">{{ $comment->nickname }}</span>
                                    <span class="text-gray-600 dark:text-gray-400">{{ $comment->content }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Input Section --}}
                        <div class="relative mt-3 flex items-center gap-2 group" x-data="{ commentText: '' }">
                            <input type="text"
                                   x-model="commentText"
                                   placeholder="Balas..."
                                   class="flex-grow bg-gray-50 dark:bg-gray-800/50 border-none rounded-lg px-3 py-1.5 text-xs focus:ring-1 focus:ring-blue-500/30 placeholder-gray-400 dark:placeholder-gray-500 transition-all"
                                   x-on:keydown.enter="if(commentText.trim()) { $wire.addComment({{ $post->id }}, commentText); commentText = '' }">
                            
                            <button 
                                @click="if(commentText.trim()) { $wire.addComment({{ $post->id }}, commentText); commentText = '' }"
                                x-show="commentText.trim().length > 0"
                                class="text-blue-600 dark:text-blue-400 font-bold text-xs pr-1"
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
                <div class="animate-spin inline-block w-6 h-6 border-4 rounded-full border-gray-300 border-t-blue-600 mx-auto"></div>
            </div>
        @endif
    </div>
</div>
