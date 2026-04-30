<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\DTO;

readonly class ServiceDTO
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $enabled = true,
        public array $config = [],
    ) {}

    public function getDockerImage(): string
    {
        return match ($this->type) {
            'redis' => $this->config['version'] ?? 'redis:alpine',
            'memcached' => $this->config['version'] ?? 'memcached:alpine',
            'node' => 'node:'.($this->config['version'] ?? '20').'-alpine',
            'mailpit' => 'axllent/mailpit:latest',
            'minio' => 'minio/minio:latest',
            default => '',
        };
    }

    public function getDefaultPort(): int
    {
        return $this->config['port'] ?? match ($this->type) {
            'redis' => 6379,
            'memcached' => 11211,
            'mailpit' => 8025,
            'minio' => 9000,
            default => 0,
        };
    }

    public function withPort(int $port): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            enabled: $this->enabled,
            config: array_merge($this->config, ['port' => $port]),
        );
    }

    public function getEnvironment(): array
    {
        return match ($this->type) {
            'redis' => [],
            'memcached' => [],
            'minio' => [
                'MINIO_ROOT_USER' => '${AWS_ACCESS_KEY_ID}',
                'MINIO_ROOT_PASSWORD' => '${AWS_SECRET_ACCESS_KEY}',
            ],
            default => $this->config['environment'] ?? [],
        };
    }

    public static function redis(string $version = 'alpine'): self
    {
        return new self(
            name: 'redis',
            type: 'redis',
            config: ['version' => "redis:{$version}"],
        );
    }

    public static function memcached(string $version = 'alpine'): self
    {
        return new self(
            name: 'memcached',
            type: 'memcached',
            config: ['version' => "memcached:{$version}"],
        );
    }

    public static function node(string $version = '20'): self
    {
        return new self(
            name: 'node',
            type: 'node',
            config: ['version' => $version],
        );
    }

    public static function worker(): self
    {
        return new self(
            name: 'worker',
            type: 'worker',
        );
    }

    public static function scheduler(): self
    {
        return new self(
            name: 'scheduler',
            type: 'scheduler',
        );
    }

    public static function mailpit(): self
    {
        return new self(
            name: 'mailpit',
            type: 'mailpit',
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: $data['type'],
            enabled: $data['enabled'] ?? true,
            config: $data['config'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'enabled' => $this->enabled,
            'image' => $this->getDockerImage(),
            'port' => $this->getDefaultPort(),
            'environment' => $this->getEnvironment(),
            'config' => $this->config,
        ];
    }
}
