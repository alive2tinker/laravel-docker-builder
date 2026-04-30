<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Generators\Kubernetes;

use Alive2Tinker\DockerBuilder\DTO\DeploymentTargetDTO;
use Alive2Tinker\DockerBuilder\DTO\DockerConfigDTO;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\AbstractFileGenerator;

class IngressGenerator extends AbstractFileGenerator
{
    public function __construct(
        DockerConfigDTO $config,
        protected readonly DeploymentTargetDTO $target,
    ) {
        parent::__construct($config);
    }

    protected function getTemplatePath(): string
    {
        return 'docker-builder::templates.kubernetes.ingress';
    }

    public function getOutputPath(): string
    {
        return 'k8s/ingress.yaml';
    }

    protected function getTemplateData(): array
    {
        return [
            'config' => $this->config,
            'app_name' => $this->config->appName,
            'namespace' => $this->target->getNamespace(),
            'ssl' => $this->config->ssl,
            'ssl_enabled' => $this->config->ssl !== null && $this->config->ssl->isEnabled(),
            'is_letsencrypt' => $this->config->ssl?->isLetsEncrypt() ?? false,
            'domain' => $this->config->ssl?->domain ?? '${APP_DOMAIN}',
        ];
    }
}
