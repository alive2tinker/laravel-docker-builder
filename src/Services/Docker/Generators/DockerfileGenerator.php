<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Generators;

use Alive2Tinker\DockerBuilder\Services\Docker\Support\ExtensionMapper;

class DockerfileGenerator extends AbstractFileGenerator
{
    protected function getTemplatePath(): string
    {
        return 'docker-builder::templates.dockerfile';
    }

    public function getOutputPath(): string
    {
        return 'Dockerfile';
    }

    protected function getTemplateData(): array
    {
        $extensionMapper = new ExtensionMapper;
        $categorized = $extensionMapper->categorizeExtensions($this->config->getAllExtensions());

        return [
            'config' => $this->config,
            'php_version' => $this->config->phpVersion,
            'base_image' => $this->config->baseImage->getFullImageName(),
            'base_image_type' => $this->config->baseImage->type,
            'extensions' => $this->config->getAllExtensions(),
            'standard_extensions' => $categorized['standard'],
            'pecl_extensions' => $categorized['pecl'],
            'custom_extensions' => $categorized['custom'],
            'system_packages' => $this->getSystemPackages(),
            'requires_nginx' => $this->config->requiresNginx(),
            'requires_oracle_client' => $this->config->requiresOracleClient(),
            'requires_mssql_driver' => $this->config->requiresMssqlDriver(),
            'node_version' => $this->config->nodeVersion,
            'package_manager' => $this->config->packageManager,
            'has_worker' => $this->config->hasWorker(),
            'has_scheduler' => $this->config->hasScheduler(),
            'oracle_install_script' => $this->config->requiresOracleClient() ? $extensionMapper->getCustomInstallScript('oci8') : null,
            'mssql_install_script' => $this->config->requiresMssqlDriver() ? $extensionMapper->getCustomInstallScript('sqlsrv') : null,
        ];
    }

    protected function getSystemPackages(): array
    {
        $packages = $this->config->systemDependencies;

        // Always include common packages for Laravel apps
        $commonPackages = [
            'git',
            'curl',
            'zip',
            'unzip',
            'libpng-dev',
            'libonig-dev',
            'libxml2-dev',
            'libcurl4-openssl-dev',
        ];

        return array_unique(array_merge($commonPackages, $packages));
    }
}
