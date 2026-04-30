<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Strategies;

use Alive2Tinker\DockerBuilder\DTO\DockerConfigDTO;

interface DeploymentStrategyInterface
{
    /**
     * Get the list of files that need to be generated for this deployment strategy.
     *
     * @return array<string> List of file paths relative to project root
     */
    public function getRequiredFiles(): array;

    /**
     * Get the list of generator class names needed for this strategy.
     *
     * @return array<string> List of fully qualified generator class names
     */
    public function getGenerators(): array;

    /**
     * Transform the configuration into template data for this specific strategy.
     */
    public function transformConfig(DockerConfigDTO $config): array;

    /**
     * Get the strategy type identifier.
     */
    public function getType(): string;

    /**
     * Get the output directory for this strategy's files.
     */
    public function getOutputDirectory(): string;
}
