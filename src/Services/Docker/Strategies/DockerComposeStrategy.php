<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Strategies;

use Alive2Tinker\DockerBuilder\DTO\DockerConfigDTO;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\DockerComposeGenerator;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\DockerfileGenerator;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\DockerIgnoreGenerator;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\NginxConfigGenerator;

class DockerComposeStrategy implements DeploymentStrategyInterface
{
    public function getRequiredFiles(): array
    {
        return [
            'Dockerfile',
            'docker-compose.yml',
            '.dockerignore',
            '.docker/nginx/nginx.conf',
            '.docker/nginx/ssl.conf',
            '.docker/php/php.ini',
            '.docker/supervisor/supervisord.conf',
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
        $services = $this->buildServices($config);

        return [
            'services' => $services,
            'networks' => $this->buildNetworks($config),
            'volumes' => $this->buildVolumes($config),
        ];
    }

    protected function buildServices(DockerConfigDTO $config): array
    {
        $services = [];

        // App service
        $services['app'] = [
            'build' => [
                'context' => '.',
                'dockerfile' => 'Dockerfile',
                'target' => 'production',
            ],
            'container_name' => $config->appName.'-app',
            'restart' => 'unless-stopped',
            'depends_on' => [],
            'environment' => $this->getAppEnvironment($config),
            'volumes' => [
                '.:/var/www/html',
                './.docker/php/php.ini:/usr/local/etc/php/conf.d/custom.ini:ro',
            ],
            'networks' => ['app-network'],
        ];

        // Nginx service (if needed)
        if ($config->requiresNginx()) {
            $services['nginx'] = [
                'image' => 'nginx:alpine',
                'container_name' => $config->appName.'-nginx',
                'restart' => 'unless-stopped',
                'ports' => $this->getNginxPorts($config),
                'volumes' => [
                    '.:/var/www/html:ro',
                    './.docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro',
                ],
                'depends_on' => ['app'],
                'networks' => ['app-network'],
            ];

            if ($config->ssl !== null && $config->ssl->isEnabled()) {
                $services['nginx']['volumes'][] = './.docker/nginx/ssl.conf:/etc/nginx/conf.d/ssl.conf:ro';

                if ($config->ssl->isLetsEncrypt()) {
                    $services['nginx']['volumes'][] = './certbot/conf:/etc/letsencrypt:ro';
                    $services['nginx']['volumes'][] = './certbot/www:/var/www/certbot:ro';
                } elseif ($config->ssl->isCustom()) {
                    $services['nginx']['volumes'][] = $config->ssl->certPath.':/etc/ssl/certs/ssl.crt:ro';
                    $services['nginx']['volumes'][] = $config->ssl->keyPath.':/etc/ssl/private/ssl.key:ro';
                }
            }

            $services['app']['depends_on'][] = 'nginx';
        }

        // Database service
        if ($config->hasDatabase() && $config->database !== null) {
            $dbConfig = $config->database;
            $services[$dbConfig->type] = [
                'image' => $dbConfig->getDockerImage(),
                'container_name' => $config->appName.'-'.$dbConfig->type,
                'restart' => 'unless-stopped',
                'ports' => [$dbConfig->getDefaultPort().':'.$dbConfig->getDefaultPort()],
                'environment' => $dbConfig->getDefaultEnvironment(),
                'volumes' => [$dbConfig->type.'-data:/var/lib/'.$this->getDbDataPath($dbConfig->type)],
                'networks' => ['app-network'],
            ];

            $services['app']['depends_on'][] = $dbConfig->type;
        }

        // Redis service
        if ($config->hasRedis()) {
            $services['redis'] = [
                'image' => 'redis:alpine',
                'container_name' => $config->appName.'-redis',
                'restart' => 'unless-stopped',
                'ports' => ['6379:6379'],
                'volumes' => ['redis-data:/data'],
                'networks' => ['app-network'],
            ];

            $services['app']['depends_on'][] = 'redis';
        }

        // Worker service
        if ($config->hasWorker()) {
            $services['worker'] = [
                'build' => [
                    'context' => '.',
                    'dockerfile' => 'Dockerfile',
                    'target' => 'production',
                ],
                'container_name' => $config->appName.'-worker',
                'restart' => 'unless-stopped',
                'command' => 'php artisan queue:work --sleep=3 --tries=3 --max-time=3600',
                'depends_on' => ['app'],
                'environment' => $this->getAppEnvironment($config),
                'volumes' => ['.:/var/www/html'],
                'networks' => ['app-network'],
            ];
        }

        // Scheduler service
        if ($config->hasScheduler()) {
            $services['scheduler'] = [
                'build' => [
                    'context' => '.',
                    'dockerfile' => 'Dockerfile',
                    'target' => 'production',
                ],
                'container_name' => $config->appName.'-scheduler',
                'restart' => 'unless-stopped',
                'command' => 'php artisan schedule:work',
                'depends_on' => ['app'],
                'environment' => $this->getAppEnvironment($config),
                'volumes' => ['.:/var/www/html'],
                'networks' => ['app-network'],
            ];
        }

        // Certbot service for Let's Encrypt
        if ($config->ssl !== null && $config->ssl->isLetsEncrypt()) {
            $services['certbot'] = [
                'image' => 'certbot/certbot',
                'container_name' => $config->appName.'-certbot',
                'volumes' => [
                    './certbot/conf:/etc/letsencrypt',
                    './certbot/www:/var/www/certbot',
                ],
                'entrypoint' => "/bin/sh -c 'trap exit TERM; while :; do certbot renew; sleep 12h & wait \$\${!}; done;'",
            ];
        }

        return $services;
    }

    protected function buildNetworks(DockerConfigDTO $config): array
    {
        return [
            'app-network' => [
                'driver' => 'bridge',
            ],
        ];
    }

    protected function buildVolumes(DockerConfigDTO $config): array
    {
        $volumes = [];

        if ($config->hasDatabase() && $config->database !== null) {
            $volumes[$config->database->type.'-data'] = ['driver' => 'local'];
        }

        if ($config->hasRedis()) {
            $volumes['redis-data'] = ['driver' => 'local'];
        }

        return $volumes;
    }

    protected function getAppEnvironment(DockerConfigDTO $config): array
    {
        return [
            'APP_ENV' => '${APP_ENV:-production}',
            'APP_DEBUG' => '${APP_DEBUG:-false}',
            'APP_URL' => '${APP_URL}',
            'DB_CONNECTION' => '${DB_CONNECTION}',
            'DB_HOST' => '${DB_HOST}',
            'DB_PORT' => '${DB_PORT}',
            'DB_DATABASE' => '${DB_DATABASE}',
            'DB_USERNAME' => '${DB_USERNAME}',
            'DB_PASSWORD' => '${DB_PASSWORD}',
            'CACHE_DRIVER' => $config->hasRedis() ? 'redis' : '${CACHE_DRIVER:-file}',
            'QUEUE_CONNECTION' => $config->hasRedis() ? 'redis' : '${QUEUE_CONNECTION:-sync}',
            'SESSION_DRIVER' => $config->hasRedis() ? 'redis' : '${SESSION_DRIVER:-file}',
            'REDIS_HOST' => $config->hasRedis() ? 'redis' : '${REDIS_HOST:-127.0.0.1}',
        ];
    }

    protected function getNginxPorts(DockerConfigDTO $config): array
    {
        $ports = ['80:80'];

        if ($config->ssl !== null && $config->ssl->isEnabled()) {
            $ports[] = '443:443';
        }

        return $ports;
    }

    protected function getDbDataPath(string $type): string
    {
        return match ($type) {
            'mysql', 'mariadb' => 'mysql',
            'postgresql', 'pgsql' => 'postgresql/data',
            'mongodb' => 'mongo',
            default => $type,
        };
    }

    public function getType(): string
    {
        return 'compose';
    }

    public function getOutputDirectory(): string
    {
        return '';
    }
}
