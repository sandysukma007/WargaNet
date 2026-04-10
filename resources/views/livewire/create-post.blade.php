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
    public $currentImage;
    public $compressedImages = []; // Array to store multiple images
    public $caption = '';
    public string $hashtagQuery = '';
    public bool $showSuggestions = false;

    // Computed to get the first image for backward compatibility if needed, 
    // but better to use compressedImages directly in the view.
    #[Computed]
    public function firstImage()
    {
        return count($this->compressedImages) > 0 ? $this->compressedImages[0] : null;
    }

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

    public function addStagedImage(): void
    {
        if (!$this->currentImage) {
            return;
        }
        if (count($this->compressedImages) >= 10) {
            $this->dispatch('error', message: 'Maksimal 10 gambar per posting.');
            $this->currentImage = null;
            return;
        }
        $this->compressedImages[] = $this->currentImage;
        $this->currentImage = null;
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

        if (count($this->compressedImages) === 0) {
            $this->dispatch('error', message: 'Setidaknya satu gambar wajib diunggah.');
            return;
        }

        $this->validate([
            'compressedImages.*' => 'image|max:5120', // Each image max 5MB
            'caption' => 'nullable|string|max:1000',
        ]);

        try {
            $urls = [];
            
            foreach ($this->compressedImages as $image) {
                // Check for pornography using Sightengine for each image
                if (env('SIGHTENGINE_API_USER') && env('SIGHTENGINE_API_SECRET')) {
                    $params = array(
                        'media' => new \CurlFile($image->getRealPath()),
                        'models' => 'nudity-2.1',
                        'api_user' => env('SIGHTENGINE_API_USER'),
                        'api_secret' => env('SIGHTENGINE_API_SECRET'),
                    );

                    $ch = curl_init('https://api.sightengine.com/1.0/check.json');
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                    $response = curl_exec($ch);
                    curl_close($ch);

                    $output = json_decode($response, true);

                    if (isset($output['status']) && $output['status'] == 'success') {
                        $nudity = $output['nudity'];
                        if ($nudity['sexual_activity'] > 0.5 || $nudity['sexual_display'] > 0.5 || $nudity['erotica'] > 0.5) {
                            $this->dispatch('error', message: 'Salah satu gambar terdeteksi mengandung konten pornografi! Postingan dibatalkan.');
                            return;
                        }
                    }
                }

                $extension = $image->getClientOriginalExtension() ?: 'jpg';
                $fileName  = time() . '_' . \Illuminate\Support\Str::random(10) . '.' . $extension;
                $path      = 'photos/' . $fileName;

                try {
                    $contents = file_get_contents($image->getRealPath());
                    Storage::disk('s3')->put($path, $contents);
                } catch (\Exception $e) {
                    throw new \Exception('Gagal mengunggah file ke Supabase: ' . $e->getMessage());
                }

                $urls[] = 'https://lwdgrjxgwtcqfctqbbbu.supabase.co/storage/v1/object/public/' . env('AWS_BUCKET') . '/' . $path;
            }

            $newPost = Post::create([
                'image_url'  => $urls, // Array will be cast to JSON
                'caption'    => $this->caption,
                'ip_address' => $ip,
            ]);

            // Save hashtag usage counts
            if ($this->caption) {
                Hashtag::syncFromCaption($this->caption);
            }

            Cache::put('last_upload_' . $ip, true, now()->addHour());
            Cache::put('last_upload_time_' . $ip, now(), now()->addHour());

            $this->reset(['rawImage', 'compressedImages', 'caption', 'hashtagQuery', 'showSuggestions']);
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
        <div class="flex flex-col sm:flex-row gap-4 items-start">
            {{-- Left Side: Preview Section (Fixed 200x200) --}}
            <div class="flex-shrink-0 w-full sm:w-[200px]">
                @if (count($this->compressedImages) > 0)
                    <div x-data="{ 
                        activeSlide: 0, 
                        slides: {{ count($this->compressedImages) }},
                        next() { this.activeSlide = (this.activeSlide + 1) % this.slides },
                        prev() { this.activeSlide = (this.activeSlide - 1 + this.slides) % this.slides }
                    }" x-init="$watch('slides', value => { if(activeSlide >= value) activeSlide = 0 })"
                    class="relative w-[200px] h-[200px] mx-auto sm:mx-0 rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 group shadow-sm">
                        
                        {{-- Images --}}
                        <div class="h-full flex transition-transform duration-300 ease-out" :style="'width: ' + (slides * 100) + '%; transform: translateX(-' + (activeSlide * (100 / slides)) + '%)'">
                            @foreach ($this->compressedImages as $index => $image)
                                <div class="h-full flex-shrink-0 flex items-center justify-center" style="width: {{ 100 / count($this->compressedImages) }}%">
                                    <img src="{{ $image->temporaryUrl() }}" class="h-full w-full object-cover">
                                </div>
                            @endforeach
                        </div>

                        {{-- Slider Controls --}}
                        <template x-if="slides > 1">
                            <div class="absolute inset-0 flex items-center justify-between px-2 pointer-events-none">
                                <button type="button" @click="prev" class="pointer-events-auto bg-black/30 hover:bg-black/50 text-white p-1 rounded-full backdrop-blur-sm transition opacity-0 group-hover:opacity-100">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                </button>
                                <button type="button" @click="next" class="pointer-events-auto bg-black/30 hover:bg-black/50 text-white p-1 rounded-full backdrop-blur-sm transition opacity-0 group-hover:opacity-100">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </div>
                        </template>

                        {{-- Indicators --}}
                        <template x-if="slides > 1">
                            <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1">
                                <template x-for="i in slides" :key="i-1">
                                    <div class="h-1 w-1 rounded-full transition-all" :class="activeSlide === (i-1) ? 'bg-white w-2' : 'bg-white/50'"></div>
                                </template>
                            </div>
                        </template>

                        {{-- Remove All Button --}}
                        <button type="button" @click="$wire.set('compressedImages', []); $wire.set('rawImage', null)" 
                            class="absolute top-1 right-1 bg-black/50 hover:bg-black/70 text-white p-1 rounded-full backdrop-blur-sm transition z-10">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                @else
                    {{-- Empty State --}}
                    <label for="image-input" class="relative w-[200px] h-[200px] mx-auto sm:mx-0 rounded-xl overflow-hidden bg-gray-50 dark:bg-gray-800/50 border-2 border-dashed border-gray-200 dark:border-gray-700 flex flex-col items-center justify-center cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition group {{ $blocked['banned'] ? 'opacity-40 pointer-events-none' : '' }}">
                        <svg class="w-8 h-8 text-blue-500 mb-2 group-hover:scale-110 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tambah Foto</span>
                    </label>
                @endif
            </div>

            {{-- Right Side: Caption & Actions --}}
            <div class="flex-grow w-full flex flex-col sm:min-h-[200px]">
                <div class="relative flex-grow">
                    <textarea
                        wire:model.live="caption"
                        class="w-full h-full min-h-[100px] sm:min-h-[140px] resize-none border-none bg-transparent p-0 text-base text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-0 {{ $blocked['banned'] ? 'opacity-40' : '' }}"
                        placeholder="{{ $blocked['banned'] ? 'Sedang dalam jeda posting...' : 'Tulis caption mu... gunakan #hashtag' }}"
                        {{ $blocked['banned'] ? 'disabled' : '' }}
                    ></textarea>

                    {{-- Hashtag Suggestions --}}
                    @if ($showSuggestions && $this->hashtagSuggestions->count() > 0)
                        <div class="absolute z-20 left-0 right-0 top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg overflow-hidden">
                            @foreach ($this->hashtagSuggestions as $tag)
                                <button type="button" wire:click="appendHashtag('{{ $tag->name }}')" class="hashtag-suggestion dark:hover:bg-gray-700">
                                    <span class="font-medium text-blue-600">#{{ $tag->name }}</span>
                                    <span class="text-xs text-gray-400 bg-gray-100 dark:bg-gray-900 rounded-full px-2 py-0.5">{{ $tag->count }}x</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mt-auto space-y-3">
                    @error('currentImage') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror
                    @error('compressedImages.*') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror
                    @error('caption') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror

                    <div class="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-800">
                        <div class="flex items-center gap-1">
                            {{-- Image Upload --}}
                            <label class="p-2 text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition cursor-pointer {{ $blocked['banned'] ? 'opacity-40 pointer-events-none' : '' }}" title="Tambah Gambar">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <input type="file" id="image-input" class="hidden" accept="image/*" {{ $blocked['banned'] ? 'disabled' : '' }}>
                            </label>

                            {{-- Video/File Placeholders --}}
                            <div class="p-2 text-gray-300 cursor-not-allowed">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                            </div>
                            <div class="p-2 text-gray-300 cursor-not-allowed">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
                            </div>
                        </div>

                        <div>
                            @if ($blocked['banned'])
                                <button type="button" disabled class="rounded-full bg-gray-100 dark:bg-gray-800 px-6 py-2 text-sm font-bold text-gray-400 cursor-not-allowed border border-gray-200 dark:border-gray-700">
                                    ⏳ {{ Ban::minutesRemaining(request()->ip()) }}m
                                </button>
                            @else
                                <button type="submit" 
                                    class="rounded-full bg-blue-500 hover:bg-blue-600 px-8 py-2 text-sm font-bold text-white transition-all shadow-md hover:shadow-lg active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none" 
                                    wire:loading.attr="disabled"
                                    {{ count($this->compressedImages) === 0 ? 'disabled' : '' }}>
                                    <span wire:loading.remove wire:target="save">Bagikan</span>
                                    <span wire:loading wire:target="save">...</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.getElementById('image-input').addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const compressedBlob = await new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
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
                        canvas.toBlob((blob) => resolve(blob), 'image/jpeg', 0.8);
                    };
                    img.src = ev.target.result;
                };
                reader.readAsDataURL(file);
            });

            const compressedFile = new File([compressedBlob], file.name.replace(/\.[^/.]+$/, '.jpg'), { type: 'image/jpeg' });

            @this.upload('currentImage', compressedFile, () => {
                @this.call('addStagedImage');
            });

            e.target.value = '';
        });
        </script>
    </form>

    {{-- Popular Hashtags Moved Outside Form --}}
    @if (!$blocked['banned'] && $this->popularHashtags->count() > 0)
        <div class="px-4 py-3 flex flex-wrap gap-2">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider w-full mb-1">Populer</span>
            @foreach ($this->popularHashtags as $tag)
                <button type="button"
                    wire:click="appendHashtag('{{ $tag->name }}')"
                    class="hashtag-chip dark:bg-gray-800 dark:text-gray-300">
                    <span class="text-blue-500 font-semibold">#{{ $tag->name }}</span>
                    <span class="text-gray-400">{{ $tag->count }}</span>
                </button>
            @endforeach
        </div>
    @endif
</div>
