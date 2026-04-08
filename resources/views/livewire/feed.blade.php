<?php

use App\Models\Post;
use App\Models\Interaction;
use App\Models\Comment;
use App\Models\Ban;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\WithPagination;

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
            session()->flash('comment_error', 'Anda masih diblokir karena sebelumnya menggunakan kata kasar.');
            return;
        }

        if ($this->hasProfanity($content)) {
            Ban::banIp($ip);
            session()->flash('comment_error', 'Kata kasar terdeteksi! Anda diblokir 1 jam.');
            return;
        }

        Comment::create([
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

{{-- wire:poll.15s = auto-refresh feed every 15 seconds --}}
<div class="space-y-6 pb-20" wire:poll.15s>
    <!-- Create Post -->
    <livewire:create-post />

    <!-- Feed Section -->
    <div class="space-y-6">
        @foreach ($this->posts as $post)
            @php
                $userAction = $this->userInteractions[$post->id] ?? null;
            @endphp
            <article class="bg-white dark:bg-gray-900 border-y sm:border sm:rounded-xl border-gray-200 dark:border-gray-700 overflow-hidden">
                <!-- Image -->
                <div class="relative w-full bg-gray-100 dark:bg-gray-800 flex justify-center items-center overflow-hidden max-h-[280px] sm:max-h-[420px] md:max-h-[560px]">
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
                           <span class="text-sm font-semibold {{ $userAction === 'like' ? 'text-red-500' : 'text-gray-700 dark:text-gray-300' }}">{{ $post->likes }}</span>
                        </button>

                        <button wire:click="dislike({{ $post->id }})" class="flex items-center gap-1.5 transition-all {{ $userAction === 'dislike' ? 'scale-110' : 'hover:scale-105' }}">
                           <span class="text-xl leading-none">{{ $userAction === 'dislike' ? '👎' : '👎🏻' }}</span>
                           <span class="text-sm font-semibold {{ $userAction === 'dislike' ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500' }}">{{ $post->dislikes }}</span>
                        </button>
                    </div>

                    @if($post->caption)
                    @php
                        $caption   = $post->caption;
                        $isLong    = mb_strlen($caption) > 30;
$short     = $isLong ? mb_substr($caption, 0, 30) : $caption;
                        $nickname  = 'Warga-' . substr(md5($post->ip_address ?? $post->id), 0, 4);
                    @endphp
                    <div x-data="{ expanded: false }" class="text-gray-900 dark:text-gray-100 text-sm leading-relaxed mb-3">
                            <span class="font-bold mr-1">{{ $nickname }}</span>

                        @if($isLong)
                            {{-- Collapsed: show first 30 chars --}}
                            <span x-show="!expanded">
                                {!! $this->renderCaption($short) !!}<span class="text-gray-400 dark:text-gray-500">...</span>
                                <button @click="expanded = true"
                                    class="text-gray-500 dark:text-gray-400 font-semibold ml-1 hover:text-gray-700 dark:hover:text-gray-300 transition-colors text-xs">
                                    Lihat selengkapnya
                                </button>
                            </span>

                            {{-- Expanded: show full caption with slide animation --}}
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

@if (session()->has('comment_error'))
                            <div class="text-xs font-semibold text-red-500 dark:text-red-400 mt-1">{{ session('comment_error') }}</div>
                        @endif

                        <div class="relative mt-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                            <input type="text"
                                   placeholder="Tambahkan komentar..."
                                   class="w-full px-3 py-2 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-transparent placeholder-gray-400 dark:placeholder-gray-500"
                                   x-on:keydown.enter="$wire.addComment({{ $post->id }}, $event.target.value); $event.target.value = ''">
                        </div>
                    </div>
                </div>
            </article>
            </article>
        @endforeach

@if ($this->posts->hasMorePages())
             <div wire:infinite-scroll="nextPage" class="text-center py-8">
                <div class="animate-spin inline-block w-6 h-6 border-4 rounded-full border-gray-300 border-t-blue-600 mx-auto"></div>
            </div>
        @endif
    </div>

    @if ($this->posts->hasMorePages())
        <div wire:infinite-scroll="nextPage"></div>
    @endif
</div>
