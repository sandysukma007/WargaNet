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
            // Check for pornography using Sightengine
            if (env('SIGHTENGINE_API_USER') && env('SIGHTENGINE_API_SECRET')) {
                $params = array(
                    'media' => new \CurlFile($this->compressedImage->getRealPath()),
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
                    // If sexual content is detected (raw, partial or suggestive above 0.5)
                    if ($nudity['sexual_activity'] > 0.5 || $nudity['sexual_display'] > 0.5 || $nudity['erotica'] > 0.5) {
                        $this->dispatch('error', message: 'Konten pornografi terdeteksi! Postingan dibatalkan.');
                        return;
                    }
                }
            }

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
        {{-- Preview Section (Instagram-like) --}}
        @if ($compressedImage)
            <div class="relative w-full aspect-square sm:aspect-video rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <img id="image-preview" src="{{ $compressedImage->temporaryUrl() }}" class="h-full w-full object-contain">
                <button type="button" @click="$wire.set('compressedImage', null); $wire.set('rawImage', null)" 
                    class="absolute top-2 right-2 bg-black/50 hover:bg-black/70 text-white p-1.5 rounded-full backdrop-blur-sm transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        @endif

        <div class="flex items-start gap-4">
            {{-- User Avatar Placeholder --}}
            <div class="flex-shrink-0">
                <div class="h-10 w-10 rounded-full bg-gradient-to-tr from-yellow-400 to-purple-600 p-[2px]">
                    <div class="h-full w-full rounded-full bg-white dark:bg-gray-900 p-0.5">
                        <div class="h-full w-full rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-bold">
                            {{ substr(md5(request()->ip()), 0, 2) }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-grow pt-1 relative">
                <textarea
                    wire:model.live="caption"
                    rows="3"
                    class="w-full resize-none border-none bg-transparent p-0 text-base text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-0 {{ $blocked['banned'] ? 'opacity-40' : '' }}"
                    placeholder="{{ $blocked['banned'] ? 'Sedang dalam jeda posting...' : 'Tulis caption mu... gunakan #hashtag' }}"
                    {{ $blocked['banned'] ? 'disabled' : '' }}
                ></textarea>

                {{-- Hashtag Suggestions Dropdown --}}
                @if ($showSuggestions && $this->hashtagSuggestions->count() > 0)
                    <div class="absolute z-20 left-0 right-0 top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg overflow-hidden">
                        @foreach ($this->hashtagSuggestions as $tag)
                            <button type="button"
                                wire:click="appendHashtag('{{ $tag->name }}')"
                                class="hashtag-suggestion dark:hover:bg-gray-700">
                                <span class="font-medium text-blue-600">#{{ $tag->name }}</span>
                                <span class="text-xs text-gray-400 bg-gray-100 dark:bg-gray-900 rounded-full px-2 py-0.5">{{ $tag->count }}x</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        @error('rawImage') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror
        @error('compressedImage') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror
        @error('caption') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror

        {{-- Action Bar (Instagram-like) --}}
        <div class="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-1" x-data="{ showUploadMenu: false }">
                <div class="relative">
                    <button type="button" @click="showUploadMenu = !showUploadMenu"
                        class="p-2 text-gray-500 hover:text-black dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition {{ $blocked['banned'] ? 'opacity-40 pointer-events-none' : '' }}">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </button>

                    {{-- Upload Menu --}}
                    <div x-show="showUploadMenu" @click.away="showUploadMenu = false"
                        class="absolute z-30 left-0 bottom-full mb-2 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl py-2"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100">
                        
                        {{-- Image Upload --}}
                        <label class="flex items-center gap-3 px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-900 cursor-pointer transition-colors" @click="showUploadMenu = false">
                            <span class="text-xl">🖼️</span>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Unggah Gambar</span>
                            <input type="file" id="image-input" class="hidden" accept="image/*" {{ $blocked['banned'] ? 'disabled' : '' }}>
                        </label>

                        {{-- File Upload (Locked) --}}
                        <div class="flex items-center gap-3 px-4 py-2 opacity-40 cursor-not-allowed">
                            <span class="text-xl">📄</span>
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Unggah File</span>
                                <span class="text-[10px] text-amber-600 font-semibold">Segera hadir</span>
                            </div>
                        </div>

                        {{-- Video Upload (Locked) --}}
                        <div class="flex items-center gap-3 px-4 py-2 opacity-40 cursor-not-allowed">
                            <span class="text-xl">🎥</span>
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Unggah Video</span>
                                <span class="text-[10px] text-amber-600 font-semibold">Segera hadir</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Emoji / Location / etc placeholders --}}
                <button type="button" class="p-2 text-gray-400 cursor-not-allowed">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </button>
            </div>

            @if ($blocked['banned'])
                <button type="button" disabled class="rounded-lg bg-gray-100 dark:bg-gray-800 px-5 py-1.5 text-sm font-semibold text-gray-400 cursor-not-allowed">
                    ⏳ {{ Ban::minutesRemaining(request()->ip()) }}m
                </button>
            @else
                <button type="submit" 
                    class="rounded-full bg-blue-500 hover:bg-blue-600 px-6 py-1.5 text-sm font-bold text-white transition disabled:opacity-50 disabled:cursor-not-allowed" 
                    wire:loading.attr="disabled"
                    {{ !$compressedImage ? 'disabled' : '' }}>
                    <span wire:loading.remove wire:target="save">Posting</span>
                    <span wire:loading wire:target="save">...</span>
                </button>
            @endif
        </div>

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
                        @this.set('rawImage', file.name);
                        @this.upload('compressedImage', compressedFile);
                    }, 'image/jpeg', 0.8);
                };
                img.src = ev.target.result;
            };
            reader.readAsDataURL(file);
        });
        </script>
    </form>
</div>

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