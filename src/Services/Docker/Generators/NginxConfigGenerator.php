<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Generators;

class NginxConfigGenerator extends AbstractFileGenerator
{
    protected function getTemplatePath(): string
    {
        if ($this->config->ssl !== null && $this->config->ssl->isEnabled()) {
            return 'docker-builder::templates.nginx-ssl';
        }

        return 'docker-builder::templates.nginx';
    }

    public function getOutputPath(): string
    {
        return '.docker/nginx/nginx.conf';
    }

    protected function getTemplateData(): array
    {
        return [
            'config' => $this->config,
            'app_name' => $this->config->appName,
            'ssl' => $this->config->ssl,
            'ssl_enabled' => $this->config->ssl !== null && $this->config->ssl->isEnabled(),
            'ssl_type' => $this->config->ssl?->type ?? 'none',
            'domain' => $this->config->ssl?->domain ?? 'localhost',
            'is_letsencrypt' => $this->config->ssl?->isLetsEncrypt() ?? false,
            'is_self_signed' => $this->config->ssl?->isSelfSigned() ?? false,
            'is_custom_cert' => $this->config->ssl?->isCustom() ?? false,
            'cert_path' => $this->config->ssl?->certPath ?? '/etc/ssl/certs/ssl.crt',
            'key_path' => $this->config->ssl?->keyPath ?? '/etc/ssl/private/ssl.key',
        ];
    }

    /**
     * Generate additional SSL configuration file.
     */
    public function generateSslConfig(): ?string
    {
        if ($this->config->ssl === null || ! $this->config->ssl->isEnabled()) {
            return null;
        }

        $content = $this->viewFactory->make('docker-builder::templates.nginx-ssl-config', [
            'config' => $this->config,
            'ssl' => $this->config->ssl,
        ])->render();

        $outputPath = base_path('.docker/nginx/ssl.conf');
        $this->ensureDirectoryExists(dirname($outputPath));
        $this->writeFile($outputPath, $content);

        return $outputPath;
    }
}
