<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'image_path', 'show_image', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'show_image' => 'boolean'];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /** Homepage card image: the uploaded one, else the built-in picture for this slug, else none. */
    public function imageUrl(): ?string
    {
        if (! $this->show_image) {
            return null;
        }
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }
        $builtIn = 'images/categories/'.$this->slug.'.webp';

        return file_exists(public_path($builtIn)) ? asset($builtIn) : null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
