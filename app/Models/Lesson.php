<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = ['video_asset_id', 'module_id', 'title', 'type', 'body', 'duration_seconds', 'is_preview', 'sort_order', 'published'];

    protected function casts(): array
    {
        return ['published' => 'boolean', 'is_preview' => 'boolean'];
    }

    public function resources(): HasMany
    {
        return $this->hasMany(LessonResource::class);
    }

    public function videoAsset(): BelongsTo
    {
        return $this->belongsTo(VideoAsset::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
