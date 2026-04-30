<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\DTO;

readonly class BaseImageDTO
{
    public function __construct(
        public string $type,
        public string $phpVersion,
        public string $variant = 'debian',
    ) {}

    public function getFullImageName(): string
    {
        return match ($this->type) {
            'fpm-nginx' => "php:{$this->phpVersion}-fpm",
            'fpm-apache' => "php:{$this->phpVersion}-apache",
            'fpm' => "php:{$this->phpVersion}-fpm",
            'cli' => "php:{$this->phpVersion}-cli",
            default => "php:{$this->phpVersion}-fpm",
        };
    }

    public function requiresNginx(): bool
    {
        return $this->type === 'fpm-nginx' || $this->type === 'fpm';
    }

    public function requiresApache(): bool
    {
        return $this->type === 'fpm-apache';
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            phpVersion: $data['php_version'],
            variant: $data['variant'] ?? 'debian',
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'php_version' => $this->phpVersion,
            'variant' => $this->variant,
            'full_image' => $this->getFullImageName(),
            'requires_nginx' => $this->requiresNginx(),
            'requires_apache' => $this->requiresApache(),
        ];
    }
}
