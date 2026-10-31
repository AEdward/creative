<?php

namespace App\Filament\Pages;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\VideoAsset;
use Filament\Pages\Page;

class MediaLibrary extends Page
{
    protected string $view = 'filament.pages.media-library';

    protected static ?string $navigationLabel = 'Media Library';

    protected static ?string $title = 'Media Library';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 7;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('administer-academy');
    }

    protected function getViewData(): array
    {
        return [
            'videos' => VideoAsset::query()->latest()->paginate(20),
            'courses' => Course::orderBy('title')->get(['id', 'title', 'slug']),
            'lessons' => Lesson::whereNotNull('video_asset_id')->pluck('title', 'video_asset_id'),
        ];
    }
}
