<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\Visit;
use App\Services\BackupService;
use App\Services\HostedBackupService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // `artisan serve` passes only a few environment variables to the web server. Without the
        // Windows temp folder PHP cannot receive uploads, so every attachment would be rejected.
        ServeCommand::$passthroughVariables = array_values(array_unique([
            ...ServeCommand::$passthroughVariables, 'TEMP', 'TMP', 'TMPDIR',
        ]));

        // On SQLite a backup is a copy of the database file. A registry on a database server
        // writes the same archives, but builds them from its tables and keeps them in storage.
        $this->app->bind(BackupService::class, fn (): BackupService => config('database.connections.'.config('database.default').'.driver') === 'sqlite'
            ? new BackupService
            : new HostedBackupService);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Behind a Cloudflare Tunnel every request arrives from localhost with the visitor's
        // address and https scheme in forwarded headers; trusting it keeps redirects on that address.
        if ($trustedProxies = config('app.trusted_proxies')) {
            TrustProxies::at($trustedProxies);

            // Visitors through the tunnel cannot reach the Vite dev server on this computer,
            // so they get the built assets (run `npm run build` after changes) while it keeps running.
            if (request()->hasHeader('X-Forwarded-Host')) {
                Vite::useHotFile(storage_path('framework/vite-tunnel.hot'));
            }
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Relation::morphMap([
            'patient' => Patient::class,
            'visit' => Visit::class,
            'treatment' => Treatment::class,
            'attachment' => Attachment::class,
        ]);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
