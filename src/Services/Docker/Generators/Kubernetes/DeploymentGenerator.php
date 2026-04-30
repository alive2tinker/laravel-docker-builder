<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Generators\Kubernetes;

use Alive2Tinker\DockerBuilder\DTO\DeploymentTargetDTO;
use Alive2Tinker\DockerBuilder\DTO\DockerConfigDTO;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\AbstractFileGenerator;

class DeploymentGenerator extends AbstractFileGenerator
{
    public function __construct(
        DockerConfigDTO $config,
        protected readonly DeploymentTargetDTO $target,
    ) {
        parent::__construct($config);
    }

    protected function getTemplatePath(): string
    {
        return 'docker-builder::templates.kubernetes.deployment';
    }

    public function getOutputPath(): string
    {
        return 'k8s/deployment.yaml';
    }

    protected function getTemplateData(): array
    {
        return [
            'config' => $this->config,
            'app_name' => $this->config->appName,
            'namespace' => $this->target->getNamespace(),
            'replicas' => $this->target->getReplicas(),
            'php_version' => $this->config->phpVersion,
            'has_worker' => $this->config->hasWorker(),
            'has_scheduler' => $this->config->hasScheduler(),
            'has_redis' => $this->config->hasRedis(),
            'database' => $this->config->database,
        ];
    }
}
