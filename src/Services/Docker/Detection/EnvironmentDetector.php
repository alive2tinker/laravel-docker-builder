<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Detection;

use Alive2Tinker\DockerBuilder\DTO\DetectionResultDTO;
use Alive2Tinker\DockerBuilder\Services\Docker\Support\ExtensionMapper;
use Illuminate\Support\Facades\File;

class EnvironmentDetector
{
    protected PhpVersionDetector $phpVersionDetector;

    protected PhpExtensionDetector $phpExtensionDetector;

    protected DatabaseDetector $databaseDetector;

    public function __construct(
        ?PhpVersionDetector $phpVersionDetector = null,
        ?PhpExtensionDetector $phpExtensionDetector = null,
        ?DatabaseDetector $databaseDetector = null,
        protected string $basePath = '',
    ) {
        if ($this->basePath === '') {
            $this->basePath = base_path();
        }

        $extensionMapper = new ExtensionMapper;

        $this->phpVersionDetector = $phpVersionDetector ?? new PhpVersionDetector($this->basePath);
        $this->phpExtensionDetector = $phpExtensionDetector ?? new PhpExtensionDetector($extensionMapper, $this->basePath);
        $this->databaseDetector = $databaseDetector ?? new DatabaseDetector($this->basePath);
    }

    public function detect(?string $basePath = null): DetectionResultDTO
    {
        $phpVersion = $this->phpVersionDetector->detect();
        $extensions = $this->phpExtensionDetector->detect();
        $extensionDependencies = $this->phpExtensionDetector->getExtensionDependencies($extensions);
        $database = $this->databaseDetector->detect();
        $composerPackages = $this->getComposerPackages($basePath ?? $this->basePath);

        // Add database extensions if detected
        if ($database !== null) {
            $extensions = array_unique(array_merge($extensions, $database->requiredExtensions));
        }

        return new DetectionResultDTO(
            phpVersion: $phpVersion,
            detectedExtensions: $extensions,
            extensionDependencies: $extensionDependencies,
            database: $database,
            composerPackages: $composerPackages,
        );
    }

    public function getPhpVersionDetector(): PhpVersionDetector
    {
        return $this->phpVersionDetector;
    }

    public function getExtensionDetector(): PhpExtensionDetector
    {
        return $this->phpExtensionDetector;
    }

    public function getDatabaseDetector(): DatabaseDetector
    {
        return $this->databaseDetector;
    }

    protected function getComposerPackages(?string $basePath = null): array
    {
        $basePath = $basePath ?? $this->basePath;
        $composerPath = $basePath.'/composer.json';

        if (! File::exists($composerPath)) {
            return [];
        }

        $content = File::get($composerPath);
        $composerJson = json_decode($content, true);

        if ($composerJson === null) {
            return [];
        }

        return array_merge(
            array_keys($composerJson['require'] ?? []),
            array_keys($composerJson['require-dev'] ?? []),
        );
    }

    public function detectExistingDockerFiles(?string $basePath = null): array
    {
        $basePath = $basePath ?? $this->basePath;
        $files = [];

        $potentialFiles = [
            'Dockerfile',
            'docker-compose.yml',
            'docker-compose.yaml',
            'docker-compose.dev.yml',
            'docker-compose.prod.yml',
            'docker-compose.override.yml',
            '.dockerignore',
            '.docker',
            '.infrastructure',
            'k8s',
            'kubernetes',
        ];

        foreach ($potentialFiles as $file) {
            $path = $basePath.'/'.$file;
            if (File::exists($path)) {
                $files[] = $file;
            }
        }

        return $files;
    }

    public function getAppName(?string $basePath = null): string
    {
        $basePath = $basePath ?? $this->basePath;
        $appName = 'laravel-app';

        // Try to get from .env
        $envPath = $basePath.'/.env';
        if (File::exists($envPath)) {
            $content = File::get($envPath);
            if (preg_match('/^APP_NAME=(.+)$/m', $content, $matches)) {
                $appName = trim($matches[1], '"\'');
            }
        } else {
            // Try to get from config
            try {
                $appName = config('app.name', 'laravel-app');
            } catch (\Exception $e) {
                // Keep default
            }
        }

        return $this->sanitizeDockerName($appName);
    }

    /**
     * Sanitize a name for use in Docker (image names, container names, networks, etc.)
     * Docker names must be lowercase and can only contain a-z, 0-9, hyphens, underscores, and periods.
     */
    protected function sanitizeDockerName(string $name): string
    {
        // Convert to lowercase
        $name = strtolower($name);

        // Replace spaces and special characters with hyphens
        $name = preg_replace('/[^a-z0-9._-]/', '-', $name);

        // Remove consecutive hyphens
        $name = preg_replace('/-+/', '-', $name);

        // Trim hyphens from start and end
        $name = trim($name, '-');

        // Ensure it's not empty
        if (empty($name)) {
            return 'laravel-app';
        }

        return $name;
    }
}
