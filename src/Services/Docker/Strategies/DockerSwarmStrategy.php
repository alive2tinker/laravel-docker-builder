<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Strategies;

use Alive2Tinker\DockerBuilder\DTO\DockerConfigDTO;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\DockerComposeGenerator;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\DockerfileGenerator;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\DockerIgnoreGenerator;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\NginxConfigGenerator;

class DockerSwarmStrategy implements DeploymentStrategyInterface
{
    public function getRequiredFiles(): array
    {
        return [
            'Dockerfile',
            'docker-compose.yml',
            'docker-compose.swarm.yml',
            '.dockerignore',
            '.docker/nginx/nginx.conf',
            '.docker/nginx/ssl.conf',
            '.docker/php/php.ini',
            '.docker/traefik/traefik.yml',
        ];
    }

    public function getGenerators(): array
    {
        return [
            DockerfileGenerator::class,
            DockerComposeGenerator::class,
            DockerIgnoreGenerator::class,
            NginxConfigGenerator::class,
        ];
    }

    public function transformConfig(DockerConfigDTO $config): array
    {
        $target = $config->getDeploymentTarget('swarm');
        $replicas = $target?->getReplicas() ?? 1;

        return [
            'services' => $this->buildServices($config, $replicas),
            'networks' => $this->buildNetworks(),
            'volumes' => $this->buildVolumes($config),
            'configs' => $this->buildConfigs($config),
            'secrets' => $this->buildSecrets($config),
        ];
    }

    protected function buildServices(DockerConfigDTO $config, int $replicas): array
    {
        $services = [];

        // Traefik service
        $services['traefik'] = [
            'image' => 'traefik:v3.0',
            'ports' => [
                ['target' => 80, 'published' => 80, 'protocol' => 'tcp', 'mode' => 'host'],
                ['target' => 443, 'published' => 443, 'protocol' => 'tcp', 'mode' => 'host'],
            ],
            'volumes' => [
                '/var/run/docker.sock:/var/run/docker.sock:ro',
                'traefik-certificates:/certificates',
            ],
            'configs' => [
                ['source' => 'traefik-config', 'target' => '/etc/traefik/traefik.yml'],
            ],
            'networks' => ['web-public'],
            'deploy' => [
                'placement' => ['constraints' => ['node.role==manager']],
                'update_config' => ['parallelism' => 1, 'delay' => '5s', 'order' => 'stop-first'],
            ],
        ];

        // App service
        $services['app'] = [
            'image' => '${DOCKER_REGISTRY}/'.$config->appName.':${IMAGE_TAG:-latest}',
            'environment' => $this->getAppEnvironment($config),
            'volumes' => [
                'storage-private:/var/www/html/storage/app/private',
                'storage-public:/var/www/html/storage/app/public',
                'storage-logs:/var/www/html/storage/logs',
            ],
            'networks' => ['web-public', 'app-internal'],
            'deploy' => [
                'replicas' => $replicas,
                'update_config' => [
                    'parallelism' => 1,
                    'delay' => '10s',
                    'order' => 'start-first',
                    'failure_action' => 'rollback',
                ],
                'rollback_config' => ['parallelism' => 0, 'order' => 'stop-first'],
                'restart_policy' => [
                    'condition' => 'any',
                    'delay' => '5s',
                    'max_attempts' => 3,
                    'window' => '120s',
                ],
                'labels' => $this->getTraefikLabels($config),
            ],
            'healthcheck' => [
                'test' => ['CMD', 'curl', '-f', 'http://localhost/up'],
                'interval' => '30s',
                'timeout' => '10s',
                'retries' => 3,
                'start_period' => '60s',
            ],
        ];

        // Worker service
        if ($config->hasWorker()) {
            $services['worker'] = [
                'image' => '${DOCKER_REGISTRY}/'.$config->appName.':${IMAGE_TAG:-latest}',
                'command' => 'php artisan queue:work --sleep=3 --tries=3 --max-time=3600',
                'environment' => $this->getAppEnvironment($config),
                'volumes' => [
                    'storage-private:/var/www/html/storage/app/private',
                    'storage-public:/var/www/html/storage/app/public',
                    'storage-logs:/var/www/html/storage/logs',
                ],
                'networks' => ['app-internal'],
                'deploy' => [
                    'replicas' => 1,
                    'restart_policy' => ['condition' => 'any', 'delay' => '5s'],
                ],
            ];
        }

        // Scheduler service
        if ($config->hasScheduler()) {
            $services['scheduler'] = [
                'image' => '${DOCKER_REGISTRY}/'.$config->appName.':${IMAGE_TAG:-latest}',
                'command' => 'php artisan schedule:work',
                'environment' => $this->getAppEnvironment($config),
                'volumes' => [
                    'storage-private:/var/www/html/storage/app/private',
                    'storage-logs:/var/www/html/storage/logs',
                ],
                'networks' => ['app-internal'],
                'deploy' => [
                    'replicas' => 1,
                    'placement' => ['constraints' => ['node.role==manager']],
                    'restart_policy' => ['condition' => 'any', 'delay' => '5s'],
                ],
            ];
        }

        // Redis service
        if ($config->hasRedis()) {
            $services['redis'] = [
                'image' => 'redis:alpine',
                'volumes' => ['redis-data:/data'],
                'networks' => ['app-internal'],
                'deploy' => [
                    'replicas' => 1,
                    'restart_policy' => ['condition' => 'any'],
                ],
            ];
        }

        return $services;
    }

    protected function buildNetworks(): array
    {
        return [
            'web-public' => ['driver' => 'overlay', 'attachable' => true],
            'app-internal' => ['driver' => 'overlay', 'internal' => true],
        ];
    }

    protected function buildVolumes(DockerConfigDTO $config): array
    {
        $volumes = [
            'traefik-certificates' => null,
            'storage-private' => null,
            'storage-public' => null,
            'storage-logs' => null,
        ];

        if ($config->hasRedis()) {
            $volumes['redis-data'] = null;
        }

        return $volumes;
    }

    protected function buildConfigs(DockerConfigDTO $config): array
    {
        return [
            'traefik-config' => [
                'file' => './.docker/traefik/traefik.yml',
            ],
        ];
    }

    protected function buildSecrets(DockerConfigDTO $config): array
    {
        return [
            'db-password' => [
                'external' => true,
            ],
            'app-key' => [
                'external' => true,
            ],
        ];
    }

    protected function getAppEnvironment(DockerConfigDTO $config): array
    {
        return [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'PHP_OPCACHE_ENABLE' => '1',
            'AUTORUN_ENABLED' => 'true',
            'SSL_MODE' => $config->ssl !== null && $config->ssl->isEnabled() ? 'full' : 'off',
        ];
    }

    protected function getTraefikLabels(DockerConfigDTO $config): array
    {
        $domain = $config->ssl?->domain ?? '${APP_DOMAIN}';

        $labels = [
            'traefik.enable=true',
            'traefik.http.routers.'.$config->appName.'.rule=Host(`'.$domain.'`)',
            'traefik.http.routers.'.$config->appName.'.entrypoints=websecure',
            'traefik.http.services.'.$config->appName.'.loadbalancer.server.port=80',
            'traefik.http.services.'.$config->appName.'.loadbalancer.healthcheck.path=/up',
            'traefik.http.services.'.$config->appName.'.loadbalancer.healthcheck.interval=30s',
        ];

        if ($config->ssl !== null && $config->ssl->isLetsEncrypt()) {
            $labels[] = 'traefik.http.routers.'.$config->appName.'.tls=true';
            $labels[] = 'traefik.http.routers.'.$config->appName.'.tls.certresolver=letsencrypt';
        }

        return $labels;
    }

    public function getType(): string
    {
        return 'swarm';
    }

    public function getOutputDirectory(): string
    {
        return '';
    }
}
