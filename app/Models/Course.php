<?php

namespace App\Models;

use App\Services\Media\MediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Course extends Model
{
    use HasFactory;

    protected $fillable = ['instructor_id', 'instructor_name', 'category_id', 'slug', 'title', 'summary', 'description', 'outcomes', 'requirements', 'level', 'language', 'price_minor', 'currency', 'is_free', 'status', 'published_at', 'thumbnail_key'];

    protected function casts(): array
    {
        return ['is_free' => 'boolean', 'price_minor' => 'integer', 'published_at' => 'datetime', 'outcomes' => 'array', 'requirements' => 'array'];
    }

    public function hasReadyCurriculum(): bool
    {
        $lessons = $this->lessons()->where('published', true)->with(['videoAsset', 'assessment'])->get();

        // Every visible step must be complete: text has content, a video is uploaded and verified,
        // a quiz has questions, an assignment has its brief.
        return $lessons->isNotEmpty() && $lessons->every(fn (Lesson $lesson) => match ($lesson->type) {
            'text' => collect($lesson->textPages())->contains(fn ($page) => filled(trim($page))),
            'video' => $lesson->videoAsset?->status === 'ready' && $lesson->videoAsset->course_id === $this->id && app(MediaStorage::class)->available(),
            'quiz' => $lesson->assessment?->course_id === $this->id && ! empty($lesson->assessment->questions),
            'assignment' => $lesson->assessment?->course_id === $this->id && filled(trim((string) $lesson->assessment->instructions)),
            default => false,
        });
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /** Name shown to learners: a typed name (instructor without an account) or the assigned account's name. */
    public function getInstructorDisplayNameAttribute(): string
    {
        return (string) ($this->instructor_name ?: $this->instructor?->name);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())->whereHas('category', fn (Builder $category) => $category->where('active', true));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function priceLabel(): string
    {
        return $this->is_free ? 'Free' : $this->currency.' '.intdiv($this->price_minor, 100).'.'.str_pad((string) ($this->price_minor % 100), 2, '0', STR_PAD_LEFT);
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CourseReview::class);
    }
}
