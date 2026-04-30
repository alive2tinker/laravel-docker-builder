<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder;

use Alive2Tinker\DockerBuilder\Console\Commands\DockerBuildCommand;
use Alive2Tinker\DockerBuilder\Services\Docker\Builders\DockerConfigBuilder;
use Alive2Tinker\DockerBuilder\Services\Docker\Detection\EnvironmentDetector;
use Alive2Tinker\DockerBuilder\Services\Docker\DockerBuildService;
use Illuminate\Support\ServiceProvider;

class DockerBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnvironmentDetector::class, function () {
            return new EnvironmentDetector;
        });

        $this->app->singleton(DockerConfigBuilder::class, function () {
            return new DockerConfigBuilder;
        });

        $this->app->singleton(DockerBuildService::class, function ($app) {
            return new DockerBuildService(
                $app->make(EnvironmentDetector::class),
                $app->make(DockerConfigBuilder::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'docker-builder');

        if ($this->app->runningInConsole()) {
            $this->commands([
                DockerBuildCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/docker-builder'),
            ], 'docker-builder-views');
        }
    }
}
