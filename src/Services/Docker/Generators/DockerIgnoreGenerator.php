<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Generators;

class DockerIgnoreGenerator extends AbstractFileGenerator
{
    protected function getTemplatePath(): string
    {
        return 'docker-builder::templates.dockerignore';
    }

    public function getOutputPath(): string
    {
        return '.dockerignore';
    }

    protected function getTemplateData(): array
    {
        $deploymentTypes = array_map(fn ($t) => $t->type, $this->config->deploymentTargets);

        return [
            'config' => $this->config,
            'has_node' => $this->config->hasNode(),
            'deployment_types' => $deploymentTypes,
            'has_kubernetes' => $this->config->hasDeploymentTarget('kubernetes'),
        ];
    }
}
