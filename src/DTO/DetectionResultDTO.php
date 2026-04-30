<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\DTO;

readonly class DetectionResultDTO
{
    public function __construct(
        public string $phpVersion,
        public array $detectedExtensions,
        public array $extensionDependencies,
        public ?DatabaseInfoDTO $database = null,
        public array $composerPackages = [],
    ) {}

    public function getAllExtensions(): array
    {
        return array_unique(array_merge(
            $this->detectedExtensions,
            $this->database?->requiredExtensions ?? [],
        ));
    }

    public function getAllSystemDependencies(): array
    {
        $dependencies = [];

        foreach ($this->extensionDependencies as $extension => $deps) {
            if (is_array($deps)) {
                $dependencies = array_merge($dependencies, $deps);
            }
        }

        return array_unique($dependencies);
    }

    public function hasExtension(string $extension): bool
    {
        return in_array($extension, $this->detectedExtensions, true);
    }

    public function requiresOracleClient(): bool
    {
        return $this->hasExtension('oci8') || $this->hasExtension('pdo_oci');
    }

    public function requiresMssqlDriver(): bool
    {
        return $this->hasExtension('sqlsrv') || $this->hasExtension('pdo_sqlsrv');
    }

    public static function fromArray(array $data): self
    {
        return new self(
            phpVersion: $data['php_version'],
            detectedExtensions: $data['detected_extensions'] ?? [],
            extensionDependencies: $data['extension_dependencies'] ?? [],
            database: isset($data['database']) ? DatabaseInfoDTO::fromDriver($data['database']['driver']) : null,
            composerPackages: $data['composer_packages'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'php_version' => $this->phpVersion,
            'detected_extensions' => $this->detectedExtensions,
            'extension_dependencies' => $this->extensionDependencies,
            'database' => $this->database?->toArray(),
            'composer_packages' => $this->composerPackages,
            'all_extensions' => $this->getAllExtensions(),
            'all_system_dependencies' => $this->getAllSystemDependencies(),
            'requires_oracle_client' => $this->requiresOracleClient(),
            'requires_mssql_driver' => $this->requiresMssqlDriver(),
        ];
    }
}
