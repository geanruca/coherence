<?php

namespace Geanruca\LaravelCoherence\Providers;

use Illuminate\Support\ServiceProvider;

class CoherenceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'coherence');

        $this->publishes([
            __DIR__ . '/../../config/coherence.php' => config_path('coherence.php'),
        ], 'coherence-config');

        if (! class_exists('CreateCoherenceChecksTable')) {
            $timestamp = date('Y_m_d_His');
            $this->publishes([
                __DIR__ . '/../../database/migrations/create_coherence_checks_table.php.stub' =>
                    database_path("migrations/{$timestamp}_create_coherence_checks_table.php"),
            ], 'coherence-migrations');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/coherence.php',
            'coherence'
        );
    }
}