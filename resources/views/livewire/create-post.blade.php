<?php

use App\Models\Post;
use App\Models\Ban;
use App\Models\Hashtag;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;

new class extends Component
{
    use WithFileUploads;

    public $rawImage;
    public $compressedImage;
    public $caption = '';
    public string $hashtagQuery = '';
    public bool $showSuggestions = false;

    #[Computed]
    public function blocked(): array
    {
        $ip = request()->ip();

        if (Ban::isBanned($ip)) {
            return [
                'banned'  => true,
                'message' => 'Anda diblokir selama ' . Ban::minutesRemaining($ip) . ' menit lagi karena menggunakan kata kasar.',
            ];
        }

        $lastPost = Post::where('ip_address', $ip)
            ->where('created_at', '>=', now()->subHour())
            ->latest()
            ->first();

        if ($lastPost) {
            $nextAllowed = $lastPost->created_at->addHour();
            $minutesLeft = (int) now()->diffInMinutes($nextAllowed) + 1;
            return [
                'banned'  => true,
                'message' => "Anda baru saja memposting. Silakan tunggu sekitar {$minutesLeft} menit lagi.",
            ];
        }

        return ['banned' => false, 'message' => ''];
    }

    #[Computed]
    public function hashtagSuggestions()
    {
        if (strlen($this->hashtagQuery) < 1) return collect();
        return Hashtag::suggest($this->hashtagQuery, 6);
    }

    #[Computed]
    public function popularHashtags()
    {
        return Hashtag::popular(8);
    }

    public function updatedCaption(string $value): void
    {
        // Detect if last word being typed starts with #
        $words = explode(' ', $value);
        $lastWord = end($words);

        if (str_starts_with($lastWord, '#') && strlen($lastWord) > 1) {
            $this->hashtagQuery = substr($lastWord, 1);
            $this->showSuggestions = true;
        } else {
            $this->hashtagQuery = '';
            $this->showSuggestions = false;
        }
    }

    public function appendHashtag(string $tag): void
    {
        // Replace the partially typed hashtag at end of caption with the selected one
        $words = explode(' ', $this->caption);
        array_pop($words);
        $this->caption = implode(' ', $words) . ' #' . $tag . ' ';
        $this->hashtagQuery = '';
        $this->showSuggestions = false;
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

    public function save()
    {
        $ip = request()->ip();
        $blocked = $this->blocked;

        if ($blocked['banned']) {
            $this->dispatch('error', message: $blocked['message']);
            return;
        }

        if ($this->hasProfanity($this->caption)) {
            Ban::banIp($ip);
            $this->dispatch('error', message: 'Kata kasar terdeteksi! Anda diblokir dari memposting selama 1 jam.');
            return;
        }

        if (!$this->compressedImage) {
            $this->dispatch('error', message: 'Gambar wajib diunggah.');
            return;
        }

        $this->validate([
            'compressedImage' => 'image|max:5120', // 5MB max
            'caption' => 'nullable|string|max:1000',
        ]);

        try {
            $extension = $this->compressedImage->getClientOriginalExtension() ?? pathinfo($this->compressedImage->getClientOriginalName() ?? 'image.jpg', PATHINFO_EXTENSION) ?: 'jpg';
            $fileName  = time() . '_' . \Illuminate\Support\Str::random(10) . '.' . $extension;
            $path      = 'photos/' . $fileName;

            try {
                if (method_exists($this->compressedImage, 'path')) {
                    // Livewire TemporaryUploadedFile
                    $contents = Storage::disk('s3')->get($this->compressedImage->path());
                } else {
                    // Browser File object from JS compression
                    $contents = file_get_contents($this->compressedImage->getPathname());
                }
                Storage::disk('s3')->put($path, $contents);
            } catch (\Exception $e) {
                throw new \Exception('Gagal mengunggah file ke Supabase: ' . $e->getMessage());
            }

            $url = 'https://lwdgrjxgwtcqfctqbbbu.supabase.co/storage/v1/object/public/' . env('AWS_BUCKET') . '/' . $path;

            $newPost = Post::create([
                'image_url'  => $url,
                'caption'    => $this->caption,
                'ip_address' => $ip,
            ]);

            // Save hashtag usage counts
            if ($this->caption) {
                Hashtag::syncFromCaption($this->caption);
            }

            Cache::put('last_upload_' . $ip, true, now()->addHour());
            Cache::put('last_upload_time_' . $ip, now(), now()->addHour());

            Event::dispatch(new PostCreated($newPost));

            $this->reset(['rawImage', 'compressedImage', 'caption', 'hashtagQuery', 'showSuggestions']);
            $this->dispatch('post-created');

        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Gagal memproses postingan: ' . $e->getMessage());
        }
    }
};
?>

<div class="bg-white dark:bg-gray-900 p-4 sm:p-5 border-b border-gray-200 dark:border-gray-700 sm:rounded-xl sm:border sm:shadow-sm dark:shadow-black/10">

    @php $blocked = $this->blocked; @endphp

    @if ($blocked['banned'])
        <div class="mb-4 flex items-start gap-3 rounded-lg bg-amber-50 border border-amber-200 p-3">
            <span class="text-lg">⏳</span>
            <p class="text-sm text-amber-800 font-medium">{{ $blocked['message'] }}</p>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 flex items-start gap-3 rounded-lg bg-red-50 border border-red-200 p-3">
            <span class="text-lg">🚫</span>
            <p class="text-sm text-red-700 font-medium">{{ session('error') }}</p>
        </div>
    @endif

    <form wire:submit.prevent="save" class="space-y-4">
        <div class="flex items-start gap-4">
            <label class="flex-shrink-0 cursor-pointer overflow-hidden rounded-lg border border-gray-200 bg-gray-50 flex items-center justify-center h-[72px] w-[72px] hover:bg-gray-100 transition relative {{ $blocked['banned'] ? 'opacity-40 pointer-events-none' : '' }}">
                @if (($rawImage || $compressedImage) && ($compressedImage ? $compressedImage->isPreviewable() : true))
                    <img id="image-preview" src="{{ $compressedImage ? $compressedImage->temporaryUrl() : '' }}" class="h-full w-full object-cover">
                @else
                    <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                @endif
                <input type="file" id="image-input" class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*" {{ $blocked['banned'] ? 'disabled' : '' }}>
            </label>

            <script>
            document.getElementById('image-input').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = function(ev) {
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');

                        // Max dimension 1920px, maintain aspect
                        const maxSize = 1920;
                        let { width, height } = img;
                        if (width > height) {
                            if (width > maxSize) {
                                height *= maxSize / width;
                                width = maxSize;
                            }
                        } else {
                            if (height > maxSize) {
                                width *= maxSize / height;
                                height = maxSize;
                            }
                        }

                        canvas.width = width;
                        canvas.height = height;
                        ctx.drawImage(img, 0, 0, width, height);

                        canvas.toBlob(function(blob) {
                            const compressedFile = new File([blob], file.name.replace(/\.[^/.]+$/, '.jpg'), { type: 'image/jpeg' });
                            @this.set('rawImage', file.name); // Trigger reactivity
                            @this.upload('compressedImage', compressedFile, (uploadedFilename) => {
                                document.getElementById('image-preview').src = URL.createObjectURL(blob);
                            });
                        }, 'image/jpeg', 0.8);
                    };
                    img.src = ev.target.result;
                };
                reader.readAsDataURL(file);
            });
            </script>

            <div class="flex-grow pt-1 relative">
                <textarea
                    wire:model.live="caption"
                    rows="2"
                    class="w-full resize-none border-none bg-transparent p-0 text-sm md:text-base text-gray-900 placeholder-gray-400 focus:ring-0 {{ $blocked['banned'] ? 'opacity-40' : '' }}"
                    placeholder="{{ $blocked['banned'] ? 'Sedang dalam jeda posting...' : 'Tulis caption mu... gunakan #hashtag' }}"
                    {{ $blocked['banned'] ? 'disabled' : '' }}
                ></textarea>

                {{-- Hashtag Suggestions Dropdown --}}
                @if ($showSuggestions && $this->hashtagSuggestions->count() > 0)
                    <div class="absolute z-20 left-0 right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">
                        @foreach ($this->hashtagSuggestions as $tag)
                            <button type="button"
                                wire:click="appendHashtag('{{ $tag->name }}')"
                                class="w-full flex items-center justify-between px-3 py-2 text-sm hover:bg-gray-50 text-left transition-colors">
                                <span class="font-medium text-blue-600">#{{ $tag->name }}</span>
                                <span class="text-xs text-gray-400 bg-gray-100 rounded-full px-2 py-0.5">{{ $tag->count }}x</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        @error('rawImage') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror
        @error('compressedImage') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror
        @error('caption') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror

        {{-- Popular Hashtags --}}
        @if (!$blocked['banned'] && $this->popularHashtags->count() > 0)
            <div class="flex flex-wrap gap-1.5 pt-1">
                @foreach ($this->popularHashtags as $tag)
                    <button type="button"
                        wire:click="appendHashtag('{{ $tag->name }}')"
                        class="inline-flex items-center gap-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-full px-2.5 py-1 transition-colors">
                        <span class="text-blue-500 font-semibold">#{{ $tag->name }}</span>
                        <span class="text-gray-400">{{ $tag->count }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        <div class="flex justify-end pt-3 border-t border-gray-100 mt-2">
            @if ($blocked['banned'])
                <button type="button" disabled class="rounded-lg bg-gray-200 px-5 py-1.5 text-sm font-semibold text-gray-400 cursor-not-allowed">
                    ⏳ Tunggu dulu...
                </button>
            @else
                <button type="submit" class="rounded-lg bg-black px-5 py-1.5 text-sm font-semibold text-white hover:bg-gray-800 transition" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Bagikan</span>
                    <span wire:loading wire:target="save">Memproses...</span>
                </button>
            @endif
        </div>
    </form>
</div>