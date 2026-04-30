<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\DTO;

readonly class SslConfigDTO
{
    public function __construct(
        public string $type,
        public ?string $domain = null,
        public ?string $email = null,
        public ?string $certPath = null,
        public ?string $keyPath = null,
    ) {}

    public function isEnabled(): bool
    {
        return $this->type !== 'none';
    }

    public function isLetsEncrypt(): bool
    {
        return $this->type === 'letsencrypt';
    }

    public function isSelfSigned(): bool
    {
        return $this->type === 'self-signed';
    }

    public function isCustom(): bool
    {
        return $this->type === 'custom';
    }

    public function requiresNginxProxy(): bool
    {
        return $this->isEnabled() && $this->type !== 'none';
    }

    public static function none(): self
    {
        return new self(type: 'none');
    }

    public static function letsEncrypt(string $domain, string $email): self
    {
        return new self(
            type: 'letsencrypt',
            domain: $domain,
            email: $email,
        );
    }

    public static function selfSigned(?string $domain = null): self
    {
        return new self(
            type: 'self-signed',
            domain: $domain,
        );
    }

    public static function custom(string $domain, string $certPath, string $keyPath): self
    {
        return new self(
            type: 'custom',
            domain: $domain,
            certPath: $certPath,
            keyPath: $keyPath,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            domain: $data['domain'] ?? null,
            email: $data['email'] ?? null,
            certPath: $data['cert_path'] ?? null,
            keyPath: $data['key_path'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'domain' => $this->domain,
            'email' => $this->email,
            'cert_path' => $this->certPath,
            'key_path' => $this->keyPath,
            'is_enabled' => $this->isEnabled(),
            'requires_nginx_proxy' => $this->requiresNginxProxy(),
        ];
    }
}
