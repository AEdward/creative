<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = ['video_asset_id', 'assessment_id', 'module_id', 'title', 'type', 'body', 'pages', 'duration_seconds', 'is_preview', 'sort_order', 'published'];

    protected function casts(): array
    {
        return ['published' => 'boolean', 'is_preview' => 'boolean', 'pages' => 'array'];
    }

    public function resources(): HasMany
    {
        return $this->hasMany(LessonResource::class);
    }

    public function videoAsset(): BelongsTo
    {
        return $this->belongsTo(VideoAsset::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** Text pages in order; a text step written before pages existed is a single page made of its body. */
    public function textPages(): array
    {
        $pages = array_values(array_filter(array_map(fn ($page) => is_string($page) ? trim($page) : '', $this->pages ?? []), fn ($page) => $page !== ''));

        return $pages ?: [(string) $this->body];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
