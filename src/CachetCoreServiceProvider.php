<?php

namespace Cachet;

use BladeUI\Icons\Factory;
use Cachet\Badger\BadgerServiceProvider;
use Cachet\Commands\AssetsCommand;
use Cachet\Commands\CheckComponentsCommand;
use Cachet\Commands\MakeUserCommand;
use Cachet\Commands\NotifyCompletedSchedulesCommand;
use Cachet\Commands\NotifyLongRunningIncidentsCommand;
use Cachet\Commands\PublishScheduledCommand;
use Cachet\Commands\RecordUptimeCommand;
use Cachet\Commands\SendBeaconCommand;
use Cachet\Commands\VersionCommand;
use Cachet\Database\Seeders\DatabaseSeeder;
use Cachet\Database\Seeders\DemoMetricSeeder;
use Cachet\Listeners\SendWebhookListener;
use Cachet\Listeners\WebhookCallEventListener;
use Cachet\Mcp\CachetServer;
use Cachet\Models\Component;
use Cachet\Models\ComponentCheck;
use Cachet\Models\ComponentGroup;
use Cachet\Models\ComponentUptimeDay;
use Cachet\Models\Group;
use Cachet\Models\Incident;
use Cachet\Models\IncidentTemplate;
use Cachet\Models\Metric;
use Cachet\Models\MetricPoint;
use Cachet\Models\Schedule;
use Cachet\Models\Subscriber;
use Cachet\Models\Update;
use Cachet\Models\WebhookAttempt;
use Cachet\Models\WebhookSubscription;
use Cachet\Policies\GroupPolicy;
use Cachet\Policies\ResourcePolicy;
use Cachet\Policies\UpdatePolicy;
use Cachet\Settings\AppSettings;
use Cachet\Settings\CustomizationSettings;
use Cachet\Settings\MailSettings;
use Cachet\Settings\ThemeSettings;
use Cachet\View\Composers\MailThemeComposer;
use Cachet\View\ViewManager;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Server;
use Dedoc\Scramble\Support\RouteInfo;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Mcp\Facades\Mcp;
use Spatie\LaravelSettings\Settings;
use Spatie\LaravelSettings\SettingsContainer;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;

class CachetCoreServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! defined('CACHET_PATH')) {
            define('CACHET_PATH', dirname(__DIR__).'/');
        }

        $this->app->singleton(Cachet::class);
        $this->app->singleton(ViewManager::class);
        $this->app->scoped(Status::class);
        $this->app->scoped(EffectiveComponentStatuses::class);
        $this->app->register(BadgerServiceProvider::class);

        $this->configureSettingsCache();
        $this->registerSettingsClasses();
    }

    /**
     * The settings classes shipped by Cachet.
     *
     * @var list<class-string<Settings>>
     */
    private const SETTINGS_CLASSES = [
        AppSettings::class,
        CustomizationSettings::class,
        MailSettings::class,
        ThemeSettings::class,
    ];

    /**
     * The permission resource guarding each model.
     *
     * @var array<class-string<Model>, string>
     */
    private const POLICY_RESOURCES = [
        Component::class => 'components',
        ComponentCheck::class => 'components',
        ComponentGroup::class => 'component-groups',
        Incident::class => 'incidents',
        IncidentTemplate::class => 'incident-templates',
        Metric::class => 'metrics',
        MetricPoint::class => 'metric-points',
        Schedule::class => 'schedules',
        Subscriber::class => 'subscribers',
        WebhookSubscription::class => 'webhooks',
    ];

    /**
     * Register Cachet's settings classes with spatie/laravel-settings.
     *
     * Auto-discovery only scans the host application, so without this every
     * resolution built a new instance and read the settings table, bypassing
     * the settings cache entirely (one query per rendered component).
     */
    private function registerSettingsClasses(): void
    {
        config()->set('settings.settings', array_values(array_unique([
            ...(array) config('settings.settings', []),
            ...self::SETTINGS_CLASSES,
        ])));
    }

    /**
     * Bind the settings classes as scoped instances when the settings provider registered first.
     */
    private function bindSettingsClasses(): void
    {
        if ($this->app->bound(AppSettings::class)) {
            return;
        }

        $this->app->make(SettingsContainer::class)->clearCache()->registerBindings();
    }

    /**
     * Enable spatie/laravel-settings' cache so settings are not read from the
     * database on every request. The cache is refreshed automatically when
     * settings are saved. Must run during registration, before the dashboard
     * panel provider resolves any settings.
     */
    private function configureSettingsCache(): void
    {
        if (config('cachet.settings_cache', true)) {
            config()->set('settings.cache.enabled', true);
        }
    }

    /**
     * Configure Laravel to respect forwarded request headers from Cachet's trusted proxies.
     */
    private function configureTrustedProxies(): void
    {
        $trustedProxies = config('cachet.trusted_proxies');

        if ($trustedProxies) {
            TrustProxies::at($trustedProxies);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bindSettingsClasses();

        if (! $this->app->configurationIsCached()) {
            $this->mergeConfigFrom(__DIR__.'/../config/cachet.php', 'cachet');
        }

        $this->configureTrustedProxies();

        Route::middlewareGroup('cachet', config('cachet.middleware', []));
        Route::middlewareGroup('cachet:api', config('cachet.api_middleware', []));
        Route::middlewareGroup('cachet:mcp', config('cachet.mcp_middleware', []));

        Relation::morphMap([
            'component' => Component::class,
            'component_group' => ComponentGroup::class,
            'incident' => Incident::class,
            'schedule' => Schedule::class,
            'subscriber' => Subscriber::class,
        ]);

        $this->registerPolicies();
        $this->registerCommands();
        $this->registerSchedules();
        $this->registerResources();
        $this->registerPublishing();
        $this->registerBladeComponents();
        $this->configureMail();

        Event::listen([
            'Cachet\Events\Incidents\*',
            'Cachet\Events\Components\*',
            'Cachet\Events\Subscribers\*',
            'Cachet\Events\Metrics\*',
        ], SendWebhookListener::class);
        Event::listen([WebhookCallSucceededEvent::class, WebhookCallFailedEvent::class], WebhookCallEventListener::class);

        FilamentColor::register([
            'cachet' => Color::generateV3Palette('rgb(4, 193, 71)'),
        ]);

        $this->configureScramble();
    }

    /**
     * Guard Cachet's models with group permissions.
     *
     * Most models share ResourcePolicy, so each gets a container binding
     * that builds it for its resource, e.g. "cachet.policy.incidents".
     */
    private function registerPolicies(): void
    {
        foreach (self::POLICY_RESOURCES as $model => $resource) {
            $binding = "cachet.policy.{$resource}";

            $this->app->singletonIf($binding, fn (): ResourcePolicy => new ResourcePolicy($resource));

            Gate::policy($model, $binding);
        }

        Gate::policy(Update::class, UpdatePolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
    }

    /**
     * Register the package's resources such as routes, migrations, etc.
     */
    private function registerResources(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cachet');

        if (config('cachet.run_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'cachet');

        $this->configureRateLimiting();
        $this->registerRoutes();
    }

    /**
     * Override the application's mail configuration with Cachet's mail settings, when configured.
     *
     * Deferred until the mailer is first resolved so requests that never send
     * mail do not pay for loading the mail settings.
     *
     * Wrapped in rescue() so a missing settings table (pre-setup, pre-migration) is not fatal.
     */
    private function configureMail(): void
    {
        $this->callAfterResolving('mail.manager', function (): void {
            $this->applyMailSettings();
        });
    }

    private function applyMailSettings(): void
    {
        rescue(function (): void {
            $settings = $this->app->make(MailSettings::class);

            if ($settings->from_address !== null) {
                config()->set('mail.from.address', $settings->from_address);
            }

            if ($settings->from_name !== null) {
                config()->set('mail.from.name', $settings->from_name);
            }

            if (! $settings->configured()) {
                return;
            }

            config()->set('mail.mailers.cachet', $settings->toMailerConfig());
            config()->set('mail.default', 'cachet');
        }, report: false);
    }

    /**
     * Configure the rate limiting for the application.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('cachet-api', function ($request) {
            return Limit::perMinute(config('cachet.api_rate_limit', 300))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('cachet-mcp', function ($request) {
            return Limit::perMinute(config('cachet.mcp_rate_limit', 300))
                ->by($request->user('sanctum')?->getKey() ?: $request->ip());
        });
    }

    /**
     * Register Cachet's routes.
     */
    private function registerRoutes(): void
    {
        $this->callAfterResolving('router', function (Router $router, Application $application) {
            $router->group([
                'domain' => config('cachet.domain'),
                'as' => 'cachet.api.',
                'prefix' => Cachet::path().'/api',
                'middleware' => ['cachet:api', 'throttle:cachet-api'],
            ], function (Router $router) {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            });

            if ($application->runningInConsole() || ! $application->routesAreCached()) {
                $router->group([
                    'domain' => config('cachet.domain'),
                    'as' => 'cachet.mcp.',
                    'prefix' => Cachet::path().'/mcp',
                    'middleware' => ['cachet:mcp', 'throttle:cachet-mcp'],
                ], function () {
                    Mcp::web('/', CachetServer::class)->name('server');
                });
            }

            Cachet::routes()
                ->register();
        });
    }

    /**
     * Register the package's publishable resources.
     */
    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/cachet.php' => config_path('cachet.php'),
        ], ['cachet', 'cachet-config']);

        $this->publishes([
            __DIR__.'/../resources/views/status-page/index.blade.php' => resource_path('views/vendor/cachet/status-page/index.blade.php'),
        ], ['cachet', 'cachet-views']);

        $this->publishes([
            __DIR__.'/../public/' => public_path('vendor/cachethq/cachet'),
        ], ['cachet', 'cachet-assets']);
    }

    /**
     * Register the package's Blade components.
     */
    private function registerBladeComponents(): void
    {
        // Shared lazily: resolving the settings reads the cache store, which may
        // not exist yet while commands such as migrate boot the app.
        view()->composer('*', fn (View $view) => $view->with('appSettings', app(AppSettings::class)));
        view()->composer('cachet::mail.*', MailThemeComposer::class);
        Blade::componentNamespace('Cachet\\View\\Components', 'cachet');

        $this->callAfterResolving(Factory::class, function (Factory $factory) {
            $factory->add('cachet', [
                'path' => __DIR__.'/../resources/svg',
                'prefix' => 'cachet',
            ]);
        });
    }

    /**
     * Register the package's commands.
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AssetsCommand::class,
                CheckComponentsCommand::class,
                MakeUserCommand::class,
                NotifyCompletedSchedulesCommand::class,
                NotifyLongRunningIncidentsCommand::class,
                PublishScheduledCommand::class,
                RecordUptimeCommand::class,
                SendBeaconCommand::class,
                VersionCommand::class,
            ]);

            AboutCommand::add('Cachet', fn () => [
                'Beacon' => AboutCommand::format(config('cachet.beacon'), console: fn ($value) => $value ? '<fg=yellow;options=bold>ENABLED</>' : 'OFF'),
                'Enabled' => AboutCommand::format(config('cachet.enabled'), console: fn ($value) => $value ? '<fg=yellow;options=bold>ENABLED</>' : 'OFF'),
                'Install ID' => app(AppSettings::class)->install_id,
                'Version' => app(Cachet::class)->version(),
            ]);
        }
    }

    /**
     * Register the package's schedules.
     */
    private function registerSchedules(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        if (! config('cachet.register_schedules', true)) {
            return;
        }

        $this->app->booted(function () {
            $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
            $demoMode = fn () => Cachet::demoMode();

            $schedule->command('cachet:beacon')->daily()->when(fn () => config('cachet.beacon'));

            $schedule->command('cachet:notify-long-running-incidents')->hourly();

            $schedule->command('cachet:notify-completed-schedules')->everyFiveMinutes();

            $schedule->command('cachet:publish-scheduled')->everyMinute();

            $schedule->command('cachet:record-uptime')->everyFifteenMinutes()->withoutOverlapping();

            $schedule->command('model:prune', [
                '--model' => [WebhookAttempt::class, ComponentCheck::class, ComponentUptimeDay::class],
            ])->daily();

            $schedule->command('db:seed', [
                '--class' => DatabaseSeeder::class,
                '--force',
            ])->everyThirtyMinutes()->when($demoMode);

            $schedule->command('db:seed', [
                '--class' => DemoMetricSeeder::class,
                '--force',
            ])->everyMinute()->when($demoMode);
        });
    }

    /**
     * Scramble is installed as dev dependency hence the class existence check.
     */
    private function configureScramble(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->info->description = 'API documentation for Cachet, the open source, self-hosted status page system.';

                $openApi->addServer(Server::make('https://v3.cachethq.io/api')->setDescription('The Cachet v3 demo server.'));
                $openApi->secure(SecurityScheme::http('bearer'));
            })
            ->withOperationTransformers(function (Operation $operation, RouteInfo $routeInfo) {
                $hasAuthMiddleware = collect($routeInfo->route->gatherMiddleware())->contains(fn ($m) => Str::startsWith($m, 'auth:'));

                if (! $hasAuthMiddleware) {
                    $operation->security = [];
                }
            });
    }
}
