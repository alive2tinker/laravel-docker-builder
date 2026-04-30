<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Generators\Kubernetes;

use Alive2Tinker\DockerBuilder\DTO\DeploymentTargetDTO;
use Alive2Tinker\DockerBuilder\DTO\DockerConfigDTO;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\AbstractFileGenerator;

class ConfigMapGenerator extends AbstractFileGenerator
{
    public function __construct(
        DockerConfigDTO $config,
        protected readonly DeploymentTargetDTO $target,
    ) {
        parent::__construct($config);
    }

    protected function getTemplatePath(): string
    {
        return 'docker-builder::templates.kubernetes.configmap';
    }

    public function getOutputPath(): string
    {
        return 'k8s/configmap.yaml';
    }

    protected function getTemplateData(): array
    {
        return [
            'config' => $this->config,
            'app_name' => $this->config->appName,
            'namespace' => $this->target->getNamespace(),
            'php_version' => $this->config->phpVersion,
            'has_redis' => $this->config->hasRedis(),
            'database_type' => $this->config->database?->type ?? 'mysql',
        ];
    }
}
