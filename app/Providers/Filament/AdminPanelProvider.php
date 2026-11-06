<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Support\InitialsAvatarProvider;
use App\Http\Middleware\AuthenticateAdminPanel;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\RequireTwoFactor;
use App\Support\Admin;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(Admin::path())
            ->domain(Admin::domain())
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
            // The private-video uploader (Media Library) lives in its own Vite entry so the admin panel can load it.
            ->renderHook(PanelsRenderHook::BODY_END, fn () => Vite::__invoke('resources/js/video-upload.js'))
            ->renderHook(PanelsRenderHook::SIDEBAR_LOGO_AFTER, fn () => '<span class="cla-panel-tag">ADMIN PANEL</span>')
            ->navigationGroups(['Platform'])
            ->navigationItems([
                NavigationItem::make('Website')->url(fn () => route('home'), shouldOpenInNewTab: true)->icon('heroicon-o-globe-alt')->group('Platform'),
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
                RequireTwoFactor::class,
            ]);
    }
}
