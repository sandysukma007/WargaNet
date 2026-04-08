<?php

use App\Models\Post;
use App\Models\Interaction;
use App\Models\Comment;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

new class extends Component
{
    #[On('post-created')]
    #[Computed]
    public function posts()
    {
        return Post::with(['comments'])->latest()->get();
    }

    public function like($postId)
    {
        $ip = request()->ip();
        $existing = Interaction::where('post_id', $postId)->where('ip_address', $ip)->first();

        if ($existing && $existing->type === 'like') {
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

    public function addComment($postId, $content)
    {
        if (trim($content) === '') return;

        Comment::create([
            'post_id' => $postId,
            'content' => $content,
            'nickname' => 'Warga-' . substr(md5(request()->ip()), 0, 4)
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
            <article class="bg-white border-y sm:border sm:rounded-xl border-gray-200 overflow-hidden">
                <!-- Image Wrapper -->
                <div class="relative w-full bg-gray-50 aspect-square">
                    <img src="{{ $post->image_url }}" alt="Post image" class="h-full w-full object-cover">
                </div>

                <!-- Post Content -->
                <div class="p-4 sm:p-5">
                    <!-- Interactions -->
                    <div class="flex items-center gap-4 mb-3">
                        <button wire:click="like({{ $post->id }})" class="flex items-center gap-1.5 text-gray-900 transition-colors pointer hover:text-gray-500">
                           <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                               <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                           </svg>
                           <span class="text-sm font-semibold">{{ $post->likes }}</span>
                        </button>
                        <button wire:click="dislike({{ $post->id }})" class="flex items-center gap-1.5 text-gray-900 transition-colors pointer hover:text-gray-500">
                           <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                               <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" />
                               <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                           </svg>
                           <span class="text-sm font-semibold">{{ $post->dislikes }}</span>
                        </button>
                    </div>

                    @if($post->caption)
                    <p class="text-gray-900 text-sm leading-relaxed mb-3"><span class="font-bold mr-1">anonim</span> {{ $post->caption }}</p>
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

                        <!-- Comment Input -->
                        <div class="relative mt-3 pt-3 border-t border-gray-100">
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