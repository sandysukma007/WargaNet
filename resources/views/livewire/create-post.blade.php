<?php

use App\Models\Post;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

new class extends Component
{
    use WithFileUploads;

    public $image;
    public $caption = '';

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

    public function save()
    {
        $ip = request()->ip();

        if (Cache::has('banned_' . $ip)) {
            session()->flash('error', 'Anda masih diblokir sementara karena menggunakan kata kasar.');
            return;
        }

        if (Cache::has('last_upload_' . $ip)) {
            $timeRemaining = Cache::get('last_upload_time_' . $ip) ? now()->diffInMinutes(Cache::get('last_upload_time_' . $ip)->addHour()) : 60;
            session()->flash('error', 'Silakan tunggu sekitar ' . $timeRemaining . ' menit lagi untuk memposting.');
            return;
        }

        if ($this->hasProfanity($this->caption)) {
            Cache::put('banned_' . $ip, true, now()->addHour());
            session()->flash('error', 'Kata kasar terdeteksi! Anda diblokir dari memposting selama 1 jam.');
            return;
        }
        $this->validate([
            'image' => 'required|image', 
            'caption' => 'nullable|string|max:1000',
        ]);

        try {
            // Upload to Supabase Storage (S3 Disk)
            // Use a clean, random filename to avoid special characters breaking Supabase Storage
            $extension = $this->image->getClientOriginalExtension() ?: 'jpg';
            $fileName = time() . '_' . \Illuminate\Support\Str::random(10) . '.' . $extension;
            $path = 'photos/' . $fileName;
            
            try {
                // Manually fetch the content and put it, bypassing the buggy S3 CopyObject API in Supabase
                $contents = Storage::disk('s3')->get($this->image->path());
                Storage::disk('s3')->put($path, $contents);
            } catch (\Exception $e) {
                throw new \Exception('Gagal mengunggah file ke Supabase: ' . $e->getMessage());
            }
            
            if (!$path) {
                throw new \Exception('Gagal mengunggah file ke Supabase.');
            }

            // Manual construction of the public URL to avoid logic errors
            $url = 'https://lwdgrjxgwtcqfctqbbbu.supabase.co/storage/v1/object/public/' . env('AWS_BUCKET') . '/' . $path;

            Post::create([
                'image_url' => $url,
                'caption' => $this->caption,
            ]);

            Cache::put('last_upload_' . $ip, true, now()->addHour());
            Cache::put('last_upload_time_' . $ip, now(), now()->addHour());

            $this->reset(['image', 'caption']);
            $this->dispatch('post-created');

        } catch (\Exception $e) {
            session()->flash('error', 'Gagal memproses postingan: ' . $e->getMessage());
        }
    }
};
?>

<div class="bg-white p-4 sm:p-5 border-b border-gray-200 sm:rounded-xl sm:border sm:shadow-sm">
    @if (session()->has('error'))
        <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-600 border border-red-100">
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit.prevent="save" class="space-y-4">
        <div class="flex items-start gap-4">
            <label class="flex-shrink-0 cursor-pointer overflow-hidden rounded-lg border border-gray-200 bg-gray-50 flex items-center justify-center h-[72px] w-[72px] hover:bg-gray-100 transition relative">
                @if ($image && !is_string($image) && $image->isPreviewable())
                    <img src="{{ $image->temporaryUrl() }}" class="h-full w-full object-cover">
                @else
                    <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                @endif
                <input type="file" wire:model="image" class="absolute inset-0 opacity-0 cursor-pointer" accept="image/*">
            </label>
            
            <div class="flex-grow pt-1">
                <textarea wire:model="caption" rows="2" class="w-full resize-none border-none bg-transparent p-0 text-sm md:text-base text-gray-900 placeholder-gray-400 focus:ring-0" placeholder="Tulis caption mu..."></textarea>
            </div>
        </div>

        @error('image') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror
        @error('caption') <span class="text-xs text-red-500 block">{{ $message }}</span> @enderror
        
        <div class="flex justify-end pt-3 border-t border-gray-100 mt-2">
            <button type="submit" class="rounded-lg bg-black px-5 py-1.5 text-sm font-semibold text-white hover:bg-gray-800 transition" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Bagikan</span>
                <span wire:loading wire:target="save">Memproses...</span>
            </button>
        </div>
    </form>
</div>