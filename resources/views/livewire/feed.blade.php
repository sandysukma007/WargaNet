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
            $this->dispatch('comment_error', message: 'Anda masih diblokir karena sebelumnya menggunakan kata kasar.');
            return;
        }

        if ($this->hasProfanity($content)) {
            Ban::banIp($ip);
            $this->dispatch('comment_error', message: 'Kata kasar terdeteksi! Anda diblokir 1 jam.');
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
<div class="space-y-6 pb-20">
    <livewire:create-post />
    <div class="space-y-6">
        @foreach ($this->posts as $post)
            @php
                $userAction = $this->userInteractions[$post->id] ?? null;
                $nickname  = 'Warga-' . substr(md5($post->ip_address ?? $post->id), 0, 4);
            @endphp
            <article class="post-card">
                <!-- Multi-image Slider (Instagram-like) -->
                @php
                    $images = is_array($post->image_url) ? $post->image_url : [$post->image_url];
                @endphp
                <div x-data="{ 
                    activeSlide: 0, 
                    slides: {{ count($images) }},
                    next() { this.activeSlide = (this.activeSlide + 1) % this.slides },
                    prev() { this.activeSlide = (this.activeSlide - 1 + this.slides) % this.slides }
                }" class="relative w-full bg-gray-100 dark:bg-gray-800 overflow-hidden group">
                    
                    {{-- Image Container --}}
                    <div class="flex transition-transform duration-300 ease-out" :style="'transform: translateX(-' + (activeSlide * 100) + '%)'">
                        @foreach ($images as $url)
                            <div class="w-full flex-shrink-0 flex justify-center items-center bg-gray-100 dark:bg-gray-800 min-h-[280px] sm:min-h-[420px] md:min-h-[560px]">
                                <img src="{{ $url }}" alt=""
                                     class="w-full h-auto max-h-[280px] sm:max-h-[420px] md:max-h-[560px] object-contain"
                                     loading="lazy">
                            </div>
                        @endforeach
                    </div>

                    {{-- Controls --}}
                    @if (count($images) > 1)
                        <button type="button" @click="prev" class="absolute left-2 top-1/2 -translate-y-1/2 bg-black/20 hover:bg-black/40 text-white p-1.5 rounded-full backdrop-blur-sm transition opacity-0 group-hover:opacity-100 z-10">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <button type="button" @click="next" class="absolute right-2 top-1/2 -translate-y-1/2 bg-black/20 hover:bg-black/40 text-white p-1.5 rounded-full backdrop-blur-sm transition opacity-0 group-hover:opacity-100 z-10">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>

                        {{-- Indicators --}}
                        <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5 z-10">
                            <template x-for="i in slides" :key="i-1">
                                <div class="h-1.5 w-1.5 rounded-full transition-all shadow-sm" :class="activeSlide === (i-1) ? 'bg-white w-3' : 'bg-white/50'"></div>
                            </template>
                        </div>
                    @endif
                </div>

                <!-- Post Content -->
                <div class="p-4 sm:p-5">
                    <!-- Interactions -->
                    <div class="flex items-center gap-4 mb-3">
                        <button wire:click="like({{ $post->id }})" class="action-btn {{ $userAction === 'like' ? 'action-btn-active' : '' }}">
                           <span class="text-xl leading-none">{{ $userAction === 'like' ? '❤️' : '🤍' }}</span>
                           <span class="text-sm font-semibold {{ $userAction === 'like' ? 'text-red-500' : 'text-gray-700 dark:text-gray-300' }}">{{ $post->likes }}</span>
                        </button>

                        <button wire:click="dislike({{ $post->id }})" class="action-btn {{ $userAction === 'dislike' ? 'action-btn-active' : '' }}">
                           <span class="text-xl leading-none">{{ $userAction === 'dislike' ? '👎' : '👎🏻' }}</span>
                           <span class="text-sm font-semibold {{ $userAction === 'dislike' ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500' }}">{{ $post->dislikes }}</span>
                        </button>
                    </div>

                    @if($post->caption)
                        @php
                            $caption   = $post->caption;
                            $isLong    = mb_strlen($caption) > 30;
                            $short     = $isLong ? mb_substr($caption, 0, 30) : $caption;
                        @endphp
                        <div x-data="{ expanded: false }" class="caption-container">
                            <span class="font-bold mr-1">{{ $nickname }}</span>

                            @if($isLong)
                                {{-- Collapsed --}}
                                <span x-show="!expanded">
                                    {!! $this->renderCaption($short) !!}<span class="text-gray-400 dark:text-gray-500">...</span>
                                    <button @click="expanded = true"
                                        class="text-gray-500 dark:text-gray-400 font-semibold ml-1 hover:text-gray-700 dark:hover:text-gray-300 transition-colors text-xs">
                                        Lihat selengkapnya
                                    </button>
                                </span>

                                {{-- Expanded --}}
                                <span x-show="expanded"
                                    x-transition:enter="transition-all ease-out duration-300"
                                    x-transition:enter-start="opacity-0 max-h-0"
                                    x-transition:enter-end="opacity-100 max-h-96"
                                    x-transition:leave="transition-all ease-in duration-200"
                                    x-transition:leave-start="opacity-100 max-h-96"
                                    x-transition:leave-end="opacity-0 max-h-0"
                                    class="overflow-hidden inline">
                                    {!! $this->renderCaption($caption) !!}
                                    <button @click="expanded = false"
                                        class="text-gray-400 dark:text-gray-500 font-semibold ml-1 hover:text-gray-600 dark:hover:text-gray-400 transition-colors text-xs block mt-0.5">
                                        Lihat sedikit
                                    </button>
                                </span>
                            @else
                                {!! $this->renderCaption($caption) !!}
                            @endif
                        </div>
                    @endif

                    <!-- Comments -->
                    <div class="space-y-1">
                        @if($post->comments->count() > 0)
                            <div class="text-gray-500 dark:text-gray-400 text-[13px] mb-2">{{ $post->comments->count() }} komentar</div>
                        @endif
                        @foreach ($post->comments->take(3) as $comment)
                            <div class="text-sm text-gray-900 dark:text-gray-100 leading-tight">
                                <span class="font-bold mr-1">{{ $comment->nickname }}</span>{{ $comment->content }}
                            </div>
                        @endforeach

                        <div class="text-[11px] text-gray-400 dark:text-gray-500 uppercase tracking-wide mt-2 mb-1">
                            {{ $post->created_at->format('d M Y') }}
                        </div>

                        <div x-data="{ error: '', init() { $wire.on('comment_error', (msg) => { this.error = msg; setTimeout(() => this.error = '', 5000) }) } }" x-show="error" x-transition class="text-xs font-semibold text-red-500 mt-1 bg-red-50 p-2 rounded" x-text="error"></div>

                        <div class="relative mt-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                            <input type="text"
                                   placeholder="Tambahkan komentar..."
                                   class="comment-input"
                                   x-on:keydown.enter="$wire.addComment({{ $post->id }}, $event.target.value); $event.target.value = ''">
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
