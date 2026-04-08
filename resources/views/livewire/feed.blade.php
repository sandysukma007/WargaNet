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
                <div class="relative w-full overflow-hidden bg-gray-50 flex justify-center items-center">
                    <!-- Maintain aspect, max-height 700px, no aggressive cropping -->
                    <img src="{{ $post->image_url }}" alt="Post image" class="w-full h-auto max-h-[700px] object-contain" loading="lazy">
                </div>

                <!-- Post Content -->
                <div class="p-4 sm:p-5">
                    <!-- Interactions -->
                    <div class="flex items-center gap-4 mb-3">
                        <button wire:click="like({{ $post->id }})" class="flex items-center gap-1.5 transition-colors pointer {{ $userAction === 'like' ? 'text-red-500' : 'text-gray-900 hover:text-gray-500' }}">
                           <svg class="h-6 w-6" fill="{{ $userAction === 'like' ? 'currentColor' : 'none' }}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                               <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                           </svg>
                           <span class="text-sm font-semibold">{{ $post->likes }}</span>
                        </button>
                        
                        <button wire:click="dislike({{ $post->id }})" class="flex items-center gap-1.5 transition-colors pointer {{ $userAction === 'dislike' ? 'text-black' : 'text-gray-900 hover:text-gray-500' }}">
                           <!-- Hand Thumb Down Icon -->
                           <svg class="h-6 w-6" fill="{{ $userAction === 'dislike' ? 'currentColor' : 'none' }}" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                               <path stroke-linecap="round" stroke-linejoin="round" d="M7.498 15.25H4.372c-1.026 0-1.945-.694-2.054-1.715a12.209 12.209 0 0 1-.068-1.285c0-2.843.992-5.454 2.649-7.521C5.313 4.247 5.912 4 6.53 4h2.88c.483 0 .964.078 1.423.23l3.114 1.04a4.501 4.501 0 0 0 1.423.23h1.294v-.008h-.008v.008c-.083-.205-.173-.405-.27-.602-.197-.4.078-.898.523-.898h.908c.889 0 1.713.518 1.972 1.368a12 12 0 0 1 .521 3.507c0 1.553-.295 3.036-.831 4.398C20.613 14.547 19.833 15 19 15h-1.053c-.472 0-.745-.551-.5-.96a12.204 12.204 0 0 0 1.5-4.125 12.01 12.01 0 0 0-.351-3.915Z" />
                           </svg>
                           <span class="text-sm font-semibold">{{ $post->dislikes }}</span>
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