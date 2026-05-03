<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Generators;

use Alive2Tinker\DockerBuilder\DTO\DockerConfigDTO;
use Alive2Tinker\DockerBuilder\Services\Docker\Strategies\DeploymentStrategyInterface;
use Alive2Tinker\DockerBuilder\Services\Docker\Strategies\DockerComposeStrategy;
use Alive2Tinker\DockerBuilder\Services\Docker\Strategies\DockerSwarmStrategy;

class DockerComposeGenerator extends AbstractFileGenerator
{
    protected DeploymentStrategyInterface $strategy;

    public function __construct(DockerConfigDTO $config, ?DeploymentStrategyInterface $strategy = null)
    {
        parent::__construct($config);
        $this->strategy = $strategy ?? new DockerComposeStrategy;
    }

    protected function getTemplatePath(): string
    {
        if ($this->strategy instanceof DockerSwarmStrategy) {
            return 'docker-builder::templates.docker-compose-swarm';
        }

        return 'docker-builder::templates.docker-compose';
    }

    public function getOutputPath(): string
    {
        if ($this->strategy instanceof DockerSwarmStrategy) {
            return 'docker-compose.swarm.yml';
        }

        return 'docker-compose.yml';
    }

    protected function getTemplateData(): array
    {
        $transformed = $this->strategy->transformConfig($this->config);

        return [
            'config' => $this->config,
            'app_name' => $this->config->appName,
            'php_version' => $this->config->phpVersion,
            'services' => $transformed['services'] ?? [],
            'networks' => $transformed['networks'] ?? [],
            'volumes' => $transformed['volumes'] ?? [],
            'configs' => $transformed['configs'] ?? [],
            'secrets' => $transformed['secrets'] ?? [],
            'requires_nginx' => $this->config->requiresNginx(),
            'has_database' => $this->config->hasDatabase(),
            'database' => $this->config->database,
            'has_redis' => $this->config->hasRedis(),
            'has_worker' => $this->config->hasWorker(),
            'has_scheduler' => $this->config->hasScheduler(),
            'has_ssr' => $this->config->hasSsr(),
            'ssl' => $this->config->ssl,
            'is_swarm' => $this->strategy instanceof DockerSwarmStrategy,
            'ports' => $this->config->ports,
            'web_port' => $this->config->getWebPort(),
            'web_ssl_port' => $this->config->getWebSslPort(),
        ];
    }
}
