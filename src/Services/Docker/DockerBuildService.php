<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker;

use Alive2Tinker\DockerBuilder\DTO\DockerConfigDTO;
use Alive2Tinker\DockerBuilder\Services\Docker\Builders\DockerConfigBuilder;
use Alive2Tinker\DockerBuilder\Services\Docker\Detection\EnvironmentDetector;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\GeneratorFactory;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DockerBuildService
{
    protected EnvironmentDetector $detector;

    protected DockerConfigBuilder $builder;

    protected GeneratorFactory $generatorFactory;

    protected array $generatedFiles = [];

    public function __construct(
        ?EnvironmentDetector $detector = null,
        ?DockerConfigBuilder $builder = null,
        ?GeneratorFactory $generatorFactory = null,
    ) {
        $this->detector = $detector ?? new EnvironmentDetector;
        $this->builder = $builder ?? new DockerConfigBuilder;
        $this->generatorFactory = $generatorFactory ?? new GeneratorFactory;
    }

    /**
     * Detect the current project environment.
     */
    public function detect(): array
    {
        $results = $this->detector->detect();

        return [
            'detection_results' => $results,
            'existing_files' => $this->detector->detectExistingDockerFiles(),
            'app_name' => $this->detector->getAppName(),
        ];
    }

    /**
     * Get the environment detector.
     */
    public function getDetector(): EnvironmentDetector
    {
        return $this->detector;
    }

    /**
     * Get the config builder.
     */
    public function getBuilder(): DockerConfigBuilder
    {
        return $this->builder;
    }

    /**
     * Build the configuration from collected options.
     */
    public function buildConfiguration(array $options): DockerConfigDTO
    {
        $detectionResults = $options['detection_results'] ?? $this->detector->detect();

        $builder = $this->builder
            ->withAppName($options['app_name'] ?? 'laravel-app')
            ->withDetectionResults($detectionResults)
            ->withPhpVersion($options['php_version'] ?? $detectionResults->phpVersion)
            ->withPhpExtensions($options['php_extensions'] ?? $detectionResults->detectedExtensions)
            ->withBaseImage($options['base_image'] ?? 'fpm-nginx', $options['base_image_variant'] ?? 'debian');

        // Database configuration
        if (isset($options['database'])) {
            if ($options['database_remote'] ?? false) {
                $builder = $builder->withRemoteDatabase($options['database']);
            } else {
                $builder = $builder->withDatabase(
                    $options['database'],
                    $options['database_version'] ?? '',
                    true
                );
            }
        }

        // SSL configuration
        if (isset($options['ssl_type'])) {
            $builder = $builder->withSsl($options['ssl_type'], [
                'domain' => $options['ssl_domain'] ?? '',
                'email' => $options['ssl_email'] ?? '',
                'cert_path' => $options['ssl_cert_path'] ?? '',
                'key_path' => $options['ssl_key_path'] ?? '',
            ]);
        }

        // Additional services
        if (! empty($options['services'])) {
            foreach ($options['services'] as $service => $config) {
                if (is_string($config)) {
                    $builder = $builder->withService($config);
                } else {
                    $builder = $builder->withService($service, $config);
                }
            }
        }

        // Node.js
        if (! empty($options['node_version'])) {
            $builder = $builder->withNodeVersion($options['node_version']);
        }

        // Package manager detection
        $packageManager = $this->detectPackageManager();
        $builder = $builder->withPackageManager($packageManager);

        // Deployment target
        $builder = $builder->withDeploymentTarget(
            $options['deployment_target'] ?? 'compose',
            $options['deployment_options'] ?? []
        );

        return $builder->build();
    }

    /**
     * Generate all Docker configuration files.
     */
    public function generateFiles(DockerConfigDTO $config): array
    {
        $this->generatedFiles = [];

        try {
            $generators = $this->generatorFactory->createGenerators($config);

            foreach ($generators as $generator) {
                $path = $generator->generate();
                $this->generatedFiles[] = $path;
            }

            // Generate additional configuration files
            $this->generateAdditionalFiles($config);

            return $this->generatedFiles;
        } catch (\Throwable $e) {
            // Rollback: delete any generated files
            $this->rollbackGeneratedFiles();

            throw new RuntimeException(
                "Failed to generate Docker files: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Generate additional configuration files.
     */
    protected function generateAdditionalFiles(DockerConfigDTO $config): void
    {
        // Generate PHP configuration
        $this->generatePhpConfig($config);

        // Generate supervisor configuration for workers
        if ($config->hasWorker() || $config->hasScheduler()) {
            $this->generateSupervisorConfig($config);
        }

        // Generate .env.docker template
        $this->generateEnvTemplate($config);

        // Generate self-signed certificates if needed
        if ($config->ssl !== null && $config->ssl->isSelfSigned()) {
            $this->generateSslScript($config);
        }

        // Generate Let's Encrypt init script
        if ($config->ssl !== null && $config->ssl->isLetsEncrypt()) {
            $this->generateLetsEncryptScript($config);
        }
    }

    /**
     * Generate PHP configuration file.
     */
    protected function generatePhpConfig(DockerConfigDTO $config): void
    {
        $content = view('docker-builder::templates.php-ini', [
            'config' => $config,
        ])->render();

        $path = base_path('.docker/php/php.ini');
        $this->ensureDirectoryExists(dirname($path));
        File::put($path, $content);
        $this->generatedFiles[] = $path;
    }

    /**
     * Generate supervisor configuration.
     */
    protected function generateSupervisorConfig(DockerConfigDTO $config): void
    {
        $content = view('docker-builder::templates.supervisor', [
            'config' => $config,
            'has_worker' => $config->hasWorker(),
            'has_scheduler' => $config->hasScheduler(),
        ])->render();

        $path = base_path('.docker/supervisor/supervisord.conf');
        $this->ensureDirectoryExists(dirname($path));
        File::put($path, $content);
        $this->generatedFiles[] = $path;
    }

    /**
     * Generate .env.docker template.
     */
    protected function generateEnvTemplate(DockerConfigDTO $config): void
    {
        $content = view('docker-builder::templates.env-docker', [
            'config' => $config,
        ])->render();

        $path = base_path('.env.docker');
        File::put($path, $content);
        $this->generatedFiles[] = $path;
    }

    /**
     * Generate SSL certificate generation script.
     */
    protected function generateSslScript(DockerConfigDTO $config): void
    {
        $content = view('docker-builder::templates.generate-ssl', [
            'config' => $config,
            'domain' => $config->ssl->domain ?? 'localhost',
        ])->render();

        $path = base_path('.docker/scripts/generate-ssl.sh');
        $this->ensureDirectoryExists(dirname($path));
        File::put($path, $content);
        File::chmod($path, 0755);
        $this->generatedFiles[] = $path;
    }

    /**
     * Generate Let's Encrypt initialization script.
     */
    protected function generateLetsEncryptScript(DockerConfigDTO $config): void
    {
        $content = view('docker-builder::templates.letsencrypt-init', [
            'config' => $config,
            'domain' => $config->ssl->domain,
            'email' => $config->ssl->email,
        ])->render();

        $path = base_path('.docker/scripts/init-letsencrypt.sh');
        $this->ensureDirectoryExists(dirname($path));
        File::put($path, $content);
        File::chmod($path, 0755);
        $this->generatedFiles[] = $path;
    }

    /**
     * Clean existing Docker configuration files.
     */
    public function cleanExistingConfigs(): array
    {
        $deletedFiles = [];
        $filesToDelete = [
            'Dockerfile',
            'docker-compose.yml',
            'docker-compose.yaml',
            'docker-compose.dev.yml',
            'docker-compose.prod.yml',
            'docker-compose.override.yml',
            'docker-compose.swarm.yml',
            '.dockerignore',
        ];

        $directoriesToDelete = [
            '.docker',
            '.infrastructure',
            'k8s',
            'kubernetes',
        ];

        foreach ($filesToDelete as $file) {
            $path = base_path($file);
            if (File::exists($path)) {
                File::delete($path);
                $deletedFiles[] = $file;
            }
        }

        foreach ($directoriesToDelete as $dir) {
            $path = base_path($dir);
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
                $deletedFiles[] = $dir;
            }
        }

        return $deletedFiles;
    }

    /**
     * Rollback generated files on error.
     */
    protected function rollbackGeneratedFiles(): void
    {
        foreach ($this->generatedFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
            }
        }

        $this->generatedFiles = [];
    }

    /**
     * Ensure directory exists.
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    /**
     * Get list of files that will be generated.
     */
    public function getFilesToGenerate(DockerConfigDTO $config): array
    {
        return $this->generatorFactory->getGeneratedFiles($config);
    }

    /**
     * Get list of already generated files.
     */
    public function getGeneratedFiles(): array
    {
        return $this->generatedFiles;
    }

    /**
     * Detect the package manager used by the project.
     */
    protected function detectPackageManager(): string
    {
        // Check for yarn.lock first (prioritize yarn if both exist)
        if (File::exists(base_path('yarn.lock'))) {
            return 'yarn';
        }

        // Check for pnpm-lock.yaml
        if (File::exists(base_path('pnpm-lock.yaml'))) {
            return 'pnpm';
        }

        // Check for bun.lockb
        if (File::exists(base_path('bun.lockb'))) {
            return 'bun';
        }

        // Default to npm
        return 'npm';
    }
}
