<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureAccountActive;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
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
            ->brandLogo(fn () => asset('images/brand/logo-full.webp'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('images/brand/favicon-32.png'))
            ->emailVerification()
            ->colors([
                'primary' => Color::hex('#7138F5'),
                'info' => Color::hex('#3054F5'),
            ])
            ->navigationItems([
                NavigationItem::make('Academy operations')->url(fn () => route('academy-admin'))->icon('heroicon-o-chart-bar'),
                NavigationItem::make('Review moderation')->url(fn () => route('admin.reviews'))->icon('heroicon-o-star'),
                NavigationItem::make('Support queue')->url(fn () => route('admin.support'))->icon('heroicon-o-chat-bubble-left-right'),
                NavigationItem::make('Certificates')->url(fn () => route('certificates.index'))->icon('heroicon-o-academic-cap'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,

            ])
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
                Authenticate::class,
                EnsureAccountActive::class,
            ]);
    }
}
