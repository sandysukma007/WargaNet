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

    public function removeImage($index): void
    {
        unset($this->compressedImages[$index]);
        $this->compressedImages = array_values($this->compressedImages);
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

        // Post must have either images or a caption
        if (count($this->compressedImages) === 0 && empty(trim($this->caption))) {
            $this->dispatch('error', message: 'Postingan tidak boleh kosong. Unggah foto atau tulis caption.');
            return;
        }

        $this->validate([
            'compressedImages.*' => 'nullable|image|max:5120', // Each image max 5MB
            'caption' => 'nullable|string|max:1000',
        ]);

        try {
            $urls = [];

            if (count($this->compressedImages) > 0) {
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

            $this->reset(['rawImage', 'currentImage', 'compressedImages', 'caption', 'hashtagQuery', 'showSuggestions']);
            $this->dispatch('post-created');

        } catch (\Exception $e) {
            $this->dispatch('error', message: 'Gagal memproses postingan: ' . $e->getMessage());
        }
    }
};
?>

<div class="post-card p-3 sm:p-4">
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

    <form wire:submit="save" class="space-y-4">
        <div class="flex gap-4 items-start">
            {{-- Left Side: Preview Section --}}
            <div class="flex-shrink-0" style="width: 100px; height: 100px; min-width: 100px; min-height: 100px;">
                @php
                    $imageCount = count($this->compressedImages);
                    $displayImages = array_slice($this->compressedImages, 0, 4);
                    $hiddenCount = $imageCount - 4;
                @endphp
                @if ($imageCount > 0)
                    <div class="relative w-full h-full rounded-xl overflow-hidden bg-gray-50 dark:bg-gray-800 shadow-sm hover:shadow-md group">
grid grid-cols-1 gap-1 h-full p-1
                            @foreach ($displayImages as $index => $image)
                                <div class="relative rounded overflow-hidden bg-gray-100 dark:bg-gray-700 h-full min-h-[24px]">
                                    <img src="{{ $image->temporaryUrl() }}" class="w-full h-full object-cover" alt="Preview {{ $index + 1 }}">
                                    <button wire:click="removeImage({{ $index }})" type="button"
                                        class="absolute -top-1 -right-1 bg-red-500 hover:bg-red-600 text-white w-4 h-4 rounded-full flex items-center justify-center text-xs shadow-md z-20 transition-all"
                                        title="Hapus foto ini">
                                        <span class="leading-none">×</span>
                                    </button>
                                </div>
                            @endforeach
                            @if ($hiddenCount > 0)
                                <div class="col-span-2 relative rounded overflow-hidden bg-gray-200 dark:bg-gray-700 h-full flex items-center justify-center">
                                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400">+{{ $hiddenCount }} lagi</span>
                                </div>
                            @else
                                @for ($i = count($displayImages); $i < 4; $i++)
                                    <div class="bg-gray-100 dark:bg-gray-700 rounded"></div>
                                @endfor
                            @endif
                        </div>
                        {{-- Remove All Button --}}
                        @if ($imageCount > 0)
                            <button wire:click="$set('compressedImages', [])" type="button"
                                class="absolute top-1 right-1 bg-black/50 hover:bg-black/70 text-white p-1 rounded-full backdrop-blur transition-all z-30 text-xs shadow-lg"
                                title="Hapus semua foto">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                @else
                    {{-- Empty State --}}
                    <label for="image-input" class="relative w-full h-full rounded-xl overflow-hidden bg-gray-50 dark:bg-gray-800/50 shadow-sm hover:shadow-md flex flex-col items-center justify-center cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 transition group {{ $blocked['banned'] ? 'opacity-40 pointer-events-none' : '' }}">
text-gray-500 mb-1 group-hover:scale-110
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="text-[8px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Foto</span>
                    </label>
                @endif
            </div>

            {{-- Right Side: Caption --}}
            <div class="flex-grow w-full">
                <div class="relative">
                    <textarea
                        wire:model.live="caption"
                        style="min-height: 80px;"
                        class="w-full resize-none border-none bg-transparent p-0 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-0 {{ $blocked['banned'] ? 'opacity-40' : '' }}"
                        placeholder="{{ $blocked['banned'] ? 'Sabar ya...' : 'Ada cerita apa hari ini?' }}"
                        {{ $blocked['banned'] ? 'disabled' : '' }}
                    ></textarea>
                </div>
            </div>
        </div>

        {{-- Bottom Actions --}}
        <div class="mt-4 pt-2 border-t border-gray-50 dark:border-gray-800/50 flex items-center justify-between">
            <div class="flex items-center gap-2">
                {{-- Quick Image Upload --}}
text-gray-600 bg-gray-100 dark:bg-gray-800
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-[10px] font-bold">Tambah Foto</span>
                    <input type="file" id="image-input" class="hidden" accept="image/*" {{ $blocked['banned'] ? 'disabled' : '' }}>
                </label>
            </div>

            @if (!$blocked['banned'])
                <button type="submit"
bg-gray-500 hover:bg-gray-600
                    wire:loading.attr="disabled"
                    {{ (count($this->compressedImages) === 0 && empty(trim($this->caption))) ? 'data-[disabled=true]' : '' }}
                    wire:target="save">
                    <span wire:loading.remove>Posting</span>
                    <span wire:loading>
                        <svg class="animate-spin -ml-1 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Memproses...
                    </span>
                </button>
            @endif
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
text-gray-500 font-semibold
                    <span class="text-gray-400">{{ $tag->count }}</span>
                </button>
            @endforeach
        </div>
    @endif
</div>
