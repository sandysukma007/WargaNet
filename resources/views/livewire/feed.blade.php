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

<div class="space-y-12 pb-20">
    <!-- Header/Create Post Section -->
    <div class="rounded-3xl bg-indigo-600 p-8 text-white shadow-2xl shadow-indigo-200">
        <h2 class="text-3xl font-bold tracking-tight">Apa yang terjadi di sekitarmu?</h2>
        <p class="mt-2 text-indigo-100 opacity-90 text-lg">Bagikan pemikiranmu secara anonim ke seluruh dunia.</p>

        <div class="mt-8">
            <livewire:create-post />
        </div>
    </div>

    <!-- Feed Section -->
    <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-1 max-w-2xl mx-auto">
        @foreach ($this->posts as $post)
            <article class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition-all hover:shadow-xl hover:shadow-slate-200/50">
                <!-- Image Wrapper -->
                <div class="relative aspect-video w-full overflow-hidden bg-slate-100">
                    <img src="{{ $post->image_url }}" alt="Post image" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
                </div>

                <!-- Post Content -->
                <div class="p-6">
                    <p class="text-slate-800 text-lg leading-relaxed">{{ $post->caption }}</p>

                    <!-- Interactions -->
                    <div class="mt-8 flex items-center justify-between border-t border-slate-100 pt-6">
                        <div class="flex items-center gap-6">
                            <button wire:click="like({{ $post->id }})" class="flex items-center gap-2 font-semibold text-slate-500 transition-colors hover:text-emerald-500">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.633 10.5c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 012.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 00.322-1.672V3a.75.75 0 01.75-.75A2.25 2.25 0 0116.5 4.5c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 01-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 00-1.423-.23H5.904M14.25 9h2.25M5.904 18.75c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 01-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 10.203 4.167 9.75 5 9.75h1.053c.472 0 .745.551.5.96a12.204 12.204 0 00-1.5 4.125 12.01 12.01 0 00.351 3.915z" />
                                </svg>
                                <span>{{ $post->likes }}</span>
                            </button>

                            <button wire:click="dislike({{ $post->id }})" class="flex items-center gap-2 font-semibold text-slate-500 transition-colors hover:text-rose-500">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.367 13.5c-.806 0-1.533.446-2.031 1.08a9.041 9.041 0 0 1-2.861 2.4c-.723.384-1.35.956-1.653 1.715a4.498 4.498 0 0 0-.322 1.672V21a.75.75 0 0 1-.75.75A2.25 2.25 0 0 1 7.5 19.5c0-1.152.26-2.243.723-3.218.266-.558-.107-1.282-.725-1.282H4.372c-1.026 0-1.945-.694-2.054-1.715a12.155 12.155 0 0 1-.068-1.285c0-2.843.992-5.454 2.649-7.521.388-.482.987-.729 1.605-.729H10.52c.483 0 .964.078 1.423.23l3.114 1.04a4.501 4.501 0 0 0 1.423.23H18.096c-.083-.205-.173-.405-.27-.602-.197-.4.078-.898.523-.898h.908c.889 0 1.713.518 1.972 1.368a12 12 0 0 1 .521 3.507c0 1.553-.295 3.036-.831 4.398-.213.547-.993 1-1.826 1h-1.053c-.472 0-.745-.551-.5-.96a12.204 12.204 0 0 0 1.5-4.125 12.01 12.01 0 0 0-.351-3.915z" />
                                </svg>
                                <span>{{ $post->dislikes }}</span>
                            </button>
                        </div>

                        <div class="text-sm font-bold text-indigo-600 bg-indigo-50 px-3 py-1 rounded-full">
                            {{ $post->comments->count() }} Komentar
                        </div>
                    </div>

                    <!-- Comments List -->
                    <div class="mt-8 space-y-4">
                        @foreach ($post->comments as $comment)
                            <div class="rounded-2xl bg-slate-50 p-4 border border-slate-100">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ $comment->nickname }}</span>
                                </div>
                                <p class="text-slate-600">{{ $comment->content }}</p>
                            </div>
                        @endforeach

                        <!-- Comment Input -->
                        <div class="relative mt-6">
                            <input type="text"
                                   placeholder="Tulis balasan..."
                                   x-on:keydown.enter="$wire.addComment({{ $post->id }}, $event.target.value); $event.target.value = ''"
                                   class="w-full rounded-2xl border-slate-100 bg-slate-50 py-3 pl-4 pr-12 text-sm focus:border-indigo-300 focus:ring-0">
                            <div class="absolute right-4 top-1/2 -translate-y-1/2">
                                <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</div>