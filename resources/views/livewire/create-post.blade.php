<?php

use App\Models\Post;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

new class extends Component
{
    use WithFileUploads;

    public $image;
    public $caption = '';

    public function save()
    {
        $this->validate([
            'image' => 'required|image', 
            'caption' => 'nullable|string|max:1000',
        ]);

        try {
            // Upload to Supabase Storage (S3 Disk)
            $fileName = time() . '_' . $this->image->getClientOriginalName();
            
            try {
                $path = Storage::disk('s3')->putFileAs('photos', $this->image, $fileName);
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

            $this->reset(['image', 'caption']);
            $this->dispatch('post-created');

        } catch (\Exception $e) {
            session()->flash('error', 'Gagal memproses postingan: ' . $e->getMessage());
        }
    }
};
?>

<div class="rounded-2xl bg-white/10 p-4 backdrop-blur-sm">
    @if (session()->has('error'))
        <div class="mb-4 rounded-xl bg-rose-500/20 p-3 text-sm font-bold text-rose-200 border border-rose-500/30">
            {{ session('error') }}
        </div>
    @endif

    <form wire:submit.prevent="save" class="space-y-4">
        <!-- Image Upload -->
        <div class="group relative flex min-h-[120px] items-center justify-center rounded-2xl border-2 border-dashed border-white/30 transition-colors hover:border-white/60">
            @if ($image && !is_string($image) && $image->isPreviewable())
                <img src="{{ $image->temporaryUrl() }}" class="h-40 w-full rounded-2xl object-cover p-2">
                <button type="button" wire:click="$set('image', null)" class="absolute right-4 top-4 rounded-full bg-rose-500 p-1 text-white shadow-lg">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            @elseif ($image)
                <div class="flex flex-col items-center gap-2 p-8 text-indigo-400">
                    <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span class="text-sm font-bold text-white">Foto Siap Unggah!</span>
                    <span class="text-xs font-semibold uppercase tracking-widest text-indigo-200">Pratinjau tidak muncul karena keterbatasan browser</span>
                </div>
                <button type="button" wire:click="$set('image', null)" class="absolute right-4 top-4 rounded-full bg-rose-500 p-1 text-white shadow-lg">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            @else
                <label class="flex cursor-pointer flex-col items-center gap-2">
                    <svg class="h-8 w-8 text-indigo-100" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15a2.25 2.25 0 002.25-2.25V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                    </svg>
                    <span class="text-sm font-medium text-indigo-100">Klik untuk upload foto</span>
                    <input type="file" wire:model="image" class="hidden">
                </label>
            @endif
        </div>
        @error('image') <span class="text-xs font-bold text-rose-400">{{ $message }}</span> @enderror

        <!-- Caption -->
        <textarea wire:model="caption"
                  placeholder="Berikan caption menarik..."
                  class="w-full rounded-2xl border-none bg-white/20 p-4 text-white placeholder-indigo-200 focus:ring-2 focus:ring-white/50"></textarea>
        @error('caption') <span class="text-xs font-bold text-rose-400">{{ $message }}</span> @enderror

        <!-- Submit Button -->
        <div class="flex justify-end">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="rounded-xl bg-white px-8 py-3 font-bold text-indigo-600 shadow-xl transition-all hover:scale-105 active:scale-95 disabled:opacity-50">
                <span wire:loading.remove>Bagikan Sekarang</span>
                <span wire:loading>Memproses...</span>
            </button>
        </div>
    </form>
</div>