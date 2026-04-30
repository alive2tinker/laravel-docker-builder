<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Generators;

class TraefikConfigGenerator extends AbstractFileGenerator
{
    protected function getTemplatePath(): string
    {
        return 'docker-builder::templates.traefik';
    }

    public function getOutputPath(): string
    {
        return '.docker/traefik/traefik.yml';
    }

    protected function getTemplateData(): array
    {
        return [
            'config' => $this->config,
            'app_name' => $this->config->appName,
            'ssl' => $this->config->ssl,
            'ssl_enabled' => $this->config->ssl !== null && $this->config->ssl->isEnabled(),
            'is_letsencrypt' => $this->config->ssl?->isLetsEncrypt() ?? false,
            'letsencrypt_email' => $this->config->ssl?->email ?? '',
            'domain' => $this->config->ssl?->domain ?? '${APP_DOMAIN}',
        ];
    }
}
