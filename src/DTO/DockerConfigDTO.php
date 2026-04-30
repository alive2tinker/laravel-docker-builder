<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\DTO;

readonly class DockerConfigDTO
{
    /**
     * @param  ServiceDTO[]  $services
     * @param  DeploymentTargetDTO[]  $deploymentTargets
     */
    public function __construct(
        public string $appName,
        public string $phpVersion,
        public array $phpExtensions,
        public BaseImageDTO $baseImage,
        public ?DatabaseConfigDTO $database,
        public ?SslConfigDTO $ssl,
        public array $services,
        public array $deploymentTargets,
        public DetectionResultDTO $detectionResults,
        public array $systemDependencies = [],
        public ?string $nodeVersion = null,
        public string $packageManager = 'npm',
        public array $ports = [],
    ) {}

    public function hasDatabase(): bool
    {
        return $this->database !== null && $this->database->includeContainer;
    }

    public function hasRedis(): bool
    {
        return $this->hasService('redis');
    }

    public function hasWorker(): bool
    {
        return $this->hasService('worker');
    }

    public function hasScheduler(): bool
    {
        return $this->hasService('scheduler');
    }

    public function hasNode(): bool
    {
        return $this->nodeVersion !== null;
    }

    public function hasService(string $type): bool
    {
        foreach ($this->services as $service) {
            if ($service->type === $type && $service->enabled) {
                return true;
            }
        }

        return false;
    }

    public function getService(string $type): ?ServiceDTO
    {
        foreach ($this->services as $service) {
            if ($service->type === $type) {
                return $service;
            }
        }

        return null;
    }

    public function requiresNginx(): bool
    {
        return $this->baseImage->requiresNginx() || ($this->ssl !== null && $this->ssl->requiresNginxProxy());
    }

    public function requiresOracleClient(): bool
    {
        return in_array('oci8', $this->phpExtensions, true) || in_array('pdo_oci', $this->phpExtensions, true);
    }

    public function requiresMssqlDriver(): bool
    {
        return in_array('sqlsrv', $this->phpExtensions, true) || in_array('pdo_sqlsrv', $this->phpExtensions, true);
    }

    public function getAllExtensions(): array
    {
        $extensions = $this->phpExtensions;

        if ($this->database !== null) {
            $extensions = array_merge($extensions, $this->database->getRequiredExtensions());
        }

        return array_unique($extensions);
    }

    public function getEnabledServices(): array
    {
        return array_filter($this->services, fn (ServiceDTO $service) => $service->enabled);
    }

    public function hasDeploymentTarget(string $type): bool
    {
        foreach ($this->deploymentTargets as $target) {
            if ($target->type === $type) {
                return true;
            }
        }

        return false;
    }

    public function getDeploymentTarget(string $type): ?DeploymentTargetDTO
    {
        foreach ($this->deploymentTargets as $target) {
            if ($target->type === $type) {
                return $target;
            }
        }

        return null;
    }

    public function getPort(string $service, int $default): int
    {
        return $this->ports[$service] ?? $default;
    }

    public function getWebPort(): int
    {
        return $this->getPort('web', 80);
    }

    public function getWebSslPort(): int
    {
        return $this->getPort('web_ssl', 443);
    }

    public static function fromArray(array $data): self
    {
        $services = [];
        foreach ($data['services'] ?? [] as $serviceData) {
            $services[] = ServiceDTO::fromArray($serviceData);
        }

        $deploymentTargets = [];
        foreach ($data['deployment_targets'] ?? [] as $targetData) {
            $deploymentTargets[] = DeploymentTargetDTO::fromArray($targetData);
        }

        return new self(
            appName: $data['app_name'],
            phpVersion: $data['php_version'],
            phpExtensions: $data['php_extensions'] ?? [],
            baseImage: BaseImageDTO::fromArray($data['base_image']),
            database: isset($data['database']) ? DatabaseConfigDTO::fromArray($data['database']) : null,
            ssl: isset($data['ssl']) ? SslConfigDTO::fromArray($data['ssl']) : null,
            services: $services,
            deploymentTargets: $deploymentTargets,
            detectionResults: DetectionResultDTO::fromArray($data['detection_results']),
            systemDependencies: $data['system_dependencies'] ?? [],
            nodeVersion: $data['node_version'] ?? null,
            packageManager: $data['package_manager'] ?? 'npm',
            ports: $data['ports'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'app_name' => $this->appName,
            'php_version' => $this->phpVersion,
            'php_extensions' => $this->phpExtensions,
            'base_image' => $this->baseImage->toArray(),
            'database' => $this->database?->toArray(),
            'ssl' => $this->ssl?->toArray(),
            'services' => array_map(fn (ServiceDTO $s) => $s->toArray(), $this->services),
            'deployment_targets' => array_map(fn (DeploymentTargetDTO $t) => $t->toArray(), $this->deploymentTargets),
            'detection_results' => $this->detectionResults->toArray(),
            'system_dependencies' => $this->systemDependencies,
            'node_version' => $this->nodeVersion,
            'package_manager' => $this->packageManager,
            'ports' => $this->ports,
            'all_extensions' => $this->getAllExtensions(),
            'requires_nginx' => $this->requiresNginx(),
            'requires_oracle_client' => $this->requiresOracleClient(),
            'requires_mssql_driver' => $this->requiresMssqlDriver(),
        ];
    }
}
