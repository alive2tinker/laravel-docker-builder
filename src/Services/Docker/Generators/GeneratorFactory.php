<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Generators;

use Alive2Tinker\DockerBuilder\DTO\DockerConfigDTO;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\Kubernetes\ConfigMapGenerator;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\Kubernetes\DeploymentGenerator;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\Kubernetes\IngressGenerator;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\Kubernetes\ServiceGenerator;
use Alive2Tinker\DockerBuilder\Services\Docker\Strategies\DeploymentStrategyInterface;
use Alive2Tinker\DockerBuilder\Services\Docker\Strategies\DockerComposeStrategy;
use Alive2Tinker\DockerBuilder\Services\Docker\Strategies\DockerSwarmStrategy;
use Alive2Tinker\DockerBuilder\Services\Docker\Strategies\KubernetesStrategy;

class GeneratorFactory
{
    /**
     * Create generators based on all deployment targets.
     *
     * @return AbstractFileGenerator[]
     */
    public function createGenerators(DockerConfigDTO $config): array
    {
        $generators = [];

        // Always include base generators
        $generators[] = new DockerfileGenerator($config);
        $generators[] = new DockerIgnoreGenerator($config);

        // Add Nginx generator if needed
        if ($config->requiresNginx()) {
            $generators[] = new NginxConfigGenerator($config);
        }

        // Add generators for each deployment target
        foreach ($config->deploymentTargets as $target) {
            $strategy = $this->getStrategyForTarget($target->type);

            if ($strategy instanceof DockerComposeStrategy) {
                $generators[] = new DockerComposeGenerator($config, $strategy);
            }

            if ($strategy instanceof DockerSwarmStrategy) {
                $generators[] = new DockerComposeGenerator($config, $strategy);
                $generators[] = new TraefikConfigGenerator($config);
            }

            if ($strategy instanceof KubernetesStrategy) {
                $generators[] = new DeploymentGenerator($config, $target);
                $generators[] = new ServiceGenerator($config, $target);
                $generators[] = new ConfigMapGenerator($config, $target);
                $generators[] = new IngressGenerator($config, $target);
            }
        }

        return $generators;
    }

    /**
     * Get the deployment strategy for a target type.
     */
    public function getStrategyForTarget(string $type): DeploymentStrategyInterface
    {
        return match ($type) {
            'swarm' => new DockerSwarmStrategy,
            'kubernetes' => new KubernetesStrategy,
            default => new DockerComposeStrategy,
        };
    }

    /**
     * Get all files that will be generated.
     */
    public function getGeneratedFiles(DockerConfigDTO $config): array
    {
        $generators = $this->createGenerators($config);
        $files = [];

        foreach ($generators as $generator) {
            $files[] = $generator->getOutputPath();
        }

        return $files;
    }
}
