<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\DTO;

readonly class DatabaseInfoDTO
{
    public function __construct(
        public string $driver,
        public string $suggestedType,
        public array $requiredExtensions,
        public string $defaultVersion = '',
    ) {}

    public static function fromDriver(string $driver): self
    {
        return match ($driver) {
            'mysql' => new self(
                driver: 'mysql',
                suggestedType: 'mysql',
                requiredExtensions: ['pdo_mysql', 'mysqli'],
                defaultVersion: '8.0',
            ),
            'mariadb' => new self(
                driver: 'mariadb',
                suggestedType: 'mariadb',
                requiredExtensions: ['pdo_mysql', 'mysqli'],
                defaultVersion: '10.11',
            ),
            'pgsql' => new self(
                driver: 'pgsql',
                suggestedType: 'postgresql',
                requiredExtensions: ['pdo_pgsql', 'pgsql'],
                defaultVersion: '16',
            ),
            'sqlsrv' => new self(
                driver: 'sqlsrv',
                suggestedType: 'mssql',
                requiredExtensions: ['pdo_sqlsrv', 'sqlsrv'],
                defaultVersion: '2022-latest',
            ),
            'mongodb' => new self(
                driver: 'mongodb',
                suggestedType: 'mongodb',
                requiredExtensions: ['mongodb'],
                defaultVersion: '7.0',
            ),
            'oci8', 'oracle' => new self(
                driver: 'oracle',
                suggestedType: 'oracle',
                requiredExtensions: ['oci8', 'pdo_oci'],
                defaultVersion: '21.3.0-xe',
            ),
            'sqlite' => new self(
                driver: 'sqlite',
                suggestedType: 'sqlite',
                requiredExtensions: ['pdo_sqlite', 'sqlite3'],
                defaultVersion: '',
            ),
            default => new self(
                driver: $driver,
                suggestedType: $driver,
                requiredExtensions: [],
                defaultVersion: '',
            ),
        };
    }

    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'suggested_type' => $this->suggestedType,
            'required_extensions' => $this->requiredExtensions,
            'default_version' => $this->defaultVersion,
        ];
    }
}
