<?php

namespace App\Providers\Filament;

use App\Models\SocialiteUser;
use App\Models\User;
use DutchCodingCompany\FilamentSocialite\FilamentSocialitePlugin;
use DutchCodingCompany\FilamentSocialite\Provider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Tricorder')
            ->login()
            ->plugin(
                FilamentSocialitePlugin::make()
                    ->providers([
                        Provider::make('google')
                            ->label('Google')
                            ->icon('heroicon-o-globe-alt')
                            ->color(Color::hex('#4285F4'))
                            ->outlined(false)
                            ->stateless(false),
                    ])
                    ->slug('admin')
                    // Empty allowlist ⇒ no auto-registration of new Google sign-ins
                    // (closed default). Existing users matched by email still log
                    // in regardless. The plugin captures `domainAllowList` at panel
                    // boot, so we override `authorizeUserUsing` to read the list
                    // live from config on every callback (keeps the value
                    // overridable per-request, e.g. in tests via `Config::set`).
                    ->registration(function (string $provider, SocialiteUserContract $oauthUser, ?\Illuminate\Contracts\Auth\Authenticatable $user): bool {
                        if ($user !== null) {
                            return true;
                        }

                        return count(config('services.filament_socialite.domain_allowlist', [])) > 0;
                    })
                    ->authorizeUserUsing(function (FilamentSocialitePlugin $plugin, SocialiteUserContract $oauthUser): bool {
                        $domains = config('services.filament_socialite.domain_allowlist', []);

                        // Empty allowlist: defer the gate to the registration
                        // callback (closed-by-default for new emails; existing
                        // users matched by email still get through).
                        if (count($domains) < 1) {
                            return true;
                        }

                        $email = $oauthUser->getEmail();

                        if ($email === null) {
                            return false;
                        }

                        return in_array(
                            Str::of($email)->afterLast('@')->lower()->__toString(),
                            $domains,
                            true,
                        );
                    })
                    ->domainAllowList(config('services.filament_socialite.domain_allowlist', []))
                    ->userModelClass(User::class)
                    ->socialiteUserModelClass(SocialiteUser::class)
            )
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
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
            ]);
    }
}
