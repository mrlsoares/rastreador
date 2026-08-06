<?php

namespace App\Providers;

use App\Services\Protocols\ProtocolManager;
use App\Services\Protocols\Gt06Parser;
use App\Services\Protocols\Jt808Parser;
use App\Services\Protocols\TqParser;
use App\Services\Protocols\TrxParser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Conforme OCP (Clean Architecture §13): dependências concretas são
     * declaradas aqui, na camada mais externa. ProtocolManager conhece apenas
     * a interface, nunca as implementações concretas.
     */
    public function register(): void
    {
        $this->app->singleton(ProtocolManager::class, function () {
            return new ProtocolManager([
                new Gt06Parser(),
                new Jt808Parser(),
                new TqParser(),
                new TrxParser(),
                // Para adicionar um novo protocolo: apenas inclua a linha aqui.
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Rastreador::observe(\App\Observers\RastreadorObserver::class);

        $this->configureRateLimiters();

        // Rota /broadcasting/auth protegida por Sanctum (canais privados).
        Broadcast::routes(['middleware' => ['auth:sanctum']]);
    }

    /**
     * Limitadores de requisição nomeados (aplicados via middleware throttle:*).
     */
    private function configureRateLimiters(): void
    {
        // Leitura/CRUD autenticado: por usuário (fallback IP).
        RateLimiter::for('api', fn(Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Ingestão máquina-a-máquina: por IP de origem.
        RateLimiter::for('ingest', fn(Request $request) => Limit::perMinute(120)
            ->by($request->ip()));
    }
}
