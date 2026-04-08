<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hashtag extends Model
{
    protected $fillable = ['name', 'count'];

    /**
     * Extract all hashtags from a text string.
     * Returns an array like: ['viral', 'warganet', 'random']
     */
    public static function extractFrom(string $text): array
    {
        preg_match_all('/#([a-zA-Z0-9_\-]+)/u', $text, $matches);
        return array_unique(array_map('strtolower', $matches[1]));
    }

    /**
     * Increment usage count for each hashtag in the text.
     * Creates the hashtag if it doesn't exist yet.
     */
    public static function syncFromCaption(string $caption): void
    {
        $tags = static::extractFrom($caption);
        foreach ($tags as $tag) {
            static::firstOrCreate(['name' => $tag])->increment('count');
        }
    }

    /**
     * Get top hashtags by usage, limited to N results.
     */
    public static function popular(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return static::orderByDesc('count')->limit($limit)->get();
    }

    /**
     * Search hashtags by partial name (for suggestions).
     */
    public static function suggest(string $query, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('name', 'like', strtolower($query) . '%')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }
}
