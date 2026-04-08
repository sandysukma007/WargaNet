<?php

use App\Models\Post;
use App\Models\Ban;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;

new class extends Component
{
    use WithFileUploads;

    public $image;
    public $caption = '';

    #[Computed]
    public function blocked(): array
    {
        $ip = request()->ip();

        // Check profanity ban first
        if (Ban::isBanned($ip)) {
            return [
                'banned'  => true,
                'message' => 'Anda diblokir selama ' . Ban::minutesRemaining($ip) . ' menit lagi karena menggunakan kata kasar.',
            ];
        }

        // Check rate limit: has this IP uploaded in the last hour?
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
            session()->flash('error', $blocked['message']);
            return;
        }

        if ($this->hasProfanity($this->caption)) {
            Ban::banIp($ip);
            session()->flash('error', 'Kata kasar terdeteksi! Anda diblokir dari memposting selama 1 jam.');
            return;
        }

        $this->validate([
            'image'   => 'required|image',
            'caption' => 'nullable|string|max:1000',
        ]);

        try {
            $extension = $this->image->getClientOriginalExtension() ?: 'jpg';
            $fileName  = time() . '_' . \Illuminate\Support\Str::random(10) . '.' . $extension;
            $path      = 'photos/' . $fileName;

            try {
                $contents = Storage::disk('s3')->get($this->image->path());
                Storage::disk('s3')->put($path, $contents);
            } catch (\Exception $e) {
                throw new \Exception('Gagal mengunggah file ke Supabase: ' . $e->getMessage());
            }

            if (!$path) {
                throw new \Exception('Gagal mengunggah file ke Supabase.');
            }

            $url = 'https://lwdgrjxgwtcqfctqbbbu.supabase.co/storage/v1/object/public/' . env('AWS_BUCKET') . '/' . $path;

            Post::create([
                'image_url'  => $url,
                'caption'    => $this->caption,
                'ip_address' => $ip,
            ]);

            $this->reset(['image', 'caption']);
            $this->dispatch('post-created');

        } catch (\Exception $e) {
            session()->flash('error', 'Gagal memproses postingan: ' . $e->getMessage());
        }
    }
};
?>

<div class="bg-white p-4 sm:p-5 border-b border-gray-200 sm:rounded-xl sm:border sm:shadow-sm">
    
    @php $blocked = $this->blocked; @endphp

    {{-- Ban / Rate-limit Warning Banner --}}
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
                @if ($image && !is_string($image) && $image->isPreviewable())
                    <img src="{{ $image->temporaryUrl() }}" class="h-full w-full object-cover">
                @else
                    <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                @endif
                <input type="file" wire:model="image" class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*" {{ $blocked['banned'] ? 'disabled' : '' }}>
            </label>
            
            <div class="flex-grow pt-1">
                <textarea
                    wire:model="caption"
                    rows="2"
                    class="w-full resize-none border-none bg-transparent p-0 text-sm md:text-base text-gray-900 placeholder-gray-400 focus:ring-0 {{ $blocked['banned'] ? 'opacity-40' : '' }}"
                    placeholder="{{ $blocked['banned'] ? 'Sedang dalam jeda posting...' : 'Tulis caption mu...' }}"
                    {{ $blocked['banned'] ? 'disabled' : '' }}
                ></textarea>
            </div>
        </div>

        @error('image') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror
        @error('caption') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror

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