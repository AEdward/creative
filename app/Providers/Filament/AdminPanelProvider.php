<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Support\InitialsAvatarProvider;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\AuthenticateAdminPanel;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\FontProviders\LocalFontProvider;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Creative Lab Academy')
            ->brandLogo(fn () => asset('images/brand/wordmark-on-light-60.webp'))
            ->darkModeBrandLogo(fn () => asset('images/brand/wordmark-on-dark-60.webp'))
            ->brandLogoHeight('1.6rem')
            ->favicon(asset('images/brand/favicon-32.png'))
            ->emailVerification()
            ->colors([
                'primary' => Color::hex('#7138F5'),
                'info' => Color::hex('#3054F5'),
            ])
            ->darkMode(false)
            ->font('Poppins', provider: LocalFontProvider::class)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->sidebarWidth('17rem')
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => new HtmlString('<link rel="stylesheet" href="'.asset('css/admin-theme.css').'?v='.@filemtime(public_path('css/admin-theme.css')).'">'))
            ->renderHook(PanelsRenderHook::SIDEBAR_LOGO_AFTER, fn () => '<span class="cla-panel-tag">ADMIN PANEL</span>')
            ->navigationGroups(['Platform'])
            ->navigationItems([
                NavigationItem::make('Instructors')->url(fn () => route('instructors.index'))->icon('heroicon-o-identification')->sort(4),
                NavigationItem::make('Enrollments & coupons')->url(fn () => route('academy-admin'))->icon('heroicon-o-user-plus')->sort(5),
                NavigationItem::make('Reviews')->url(fn () => route('admin.reviews'))->icon('heroicon-o-star')->sort(11),
                NavigationItem::make('Support queue')->url(fn () => route('admin.support'))->icon('heroicon-o-chat-bubble-left-right')->sort(12),
                NavigationItem::make('Analytics & settings')->url(fn () => route('academy-admin'))->icon('heroicon-o-cog-6-tooth')->sort(13),
                NavigationItem::make('Website')->url(fn () => route('home'), shouldOpenInNewTab: true)->icon('heroicon-o-globe-alt')->group('Platform'),
                NavigationItem::make('View as Student')->url(fn () => route('dashboard'), shouldOpenInNewTab: true)->icon('heroicon-o-eye')->group('Platform'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticateAdminPanel::class,
                EnsureAccountActive::class,
            ]);
    }
}
