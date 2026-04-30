<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\DTO;

readonly class DatabaseConfigDTO
{
    public function __construct(
        public string $type,
        public ?string $version,
        public bool $includeContainer = true,
        public ?int $port = null,
        public array $environment = [],
        public array $volumes = [],
    ) {}

    public function getDockerImage(): string
    {
        return match ($this->type) {
            'mysql' => "mysql:{$this->version}",
            'mariadb' => "mariadb:{$this->version}",
            'postgresql', 'pgsql' => "postgres:{$this->version}",
            'mongodb' => "mongo:{$this->version}",
            'mssql', 'sqlsrv' => "mcr.microsoft.com/mssql/server:{$this->version}",
            'oracle' => "container-registry.oracle.com/database/express:{$this->version}",
            default => '',
        };
    }

    public function getDefaultPort(): int
    {
        return $this->port ?? match ($this->type) {
            'mysql', 'mariadb' => 3306,
            'postgresql', 'pgsql' => 5432,
            'mongodb' => 27017,
            'mssql', 'sqlsrv' => 1433,
            'oracle' => 1521,
            'sqlite' => 0,
            default => 0,
        };
    }

    public function getRequiredExtensions(): array
    {
        return match ($this->type) {
            'mysql', 'mariadb' => ['pdo_mysql', 'mysqli'],
            'postgresql', 'pgsql' => ['pdo_pgsql', 'pgsql'],
            'mongodb' => ['mongodb'],
            'mssql', 'sqlsrv' => ['pdo_sqlsrv', 'sqlsrv'],
            'oracle' => ['oci8', 'pdo_oci'],
            'sqlite' => ['pdo_sqlite', 'sqlite3'],
            default => [],
        };
    }

    public function getDefaultEnvironment(): array
    {
        return match ($this->type) {
            'mysql' => [
                'MYSQL_ROOT_PASSWORD' => '${DB_PASSWORD}',
                'MYSQL_DATABASE' => '${DB_DATABASE}',
                'MYSQL_USER' => '${DB_USERNAME}',
                'MYSQL_PASSWORD' => '${DB_PASSWORD}',
            ],
            'mariadb' => [
                'MARIADB_ROOT_PASSWORD' => '${DB_PASSWORD}',
                'MARIADB_DATABASE' => '${DB_DATABASE}',
                'MARIADB_USER' => '${DB_USERNAME}',
                'MARIADB_PASSWORD' => '${DB_PASSWORD}',
            ],
            'postgresql', 'pgsql' => [
                'POSTGRES_DB' => '${DB_DATABASE}',
                'POSTGRES_USER' => '${DB_USERNAME}',
                'POSTGRES_PASSWORD' => '${DB_PASSWORD}',
            ],
            'mongodb' => [
                'MONGO_INITDB_ROOT_USERNAME' => '${DB_USERNAME}',
                'MONGO_INITDB_ROOT_PASSWORD' => '${DB_PASSWORD}',
                'MONGO_INITDB_DATABASE' => '${DB_DATABASE}',
            ],
            'mssql', 'sqlsrv' => [
                'ACCEPT_EULA' => 'Y',
                'SA_PASSWORD' => '${DB_PASSWORD}',
                'MSSQL_PID' => 'Express',
            ],
            'oracle' => [
                'ORACLE_PWD' => '${DB_PASSWORD}',
                'ORACLE_DATABASE' => '${DB_DATABASE}',
            ],
            default => [],
        };
    }

    public static function remote(string $type): self
    {
        return new self(
            type: $type,
            version: '',
            includeContainer: false,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            version: $data['version'] ?? '',
            includeContainer: $data['include_container'] ?? true,
            port: $data['port'] ?? null,
            environment: $data['environment'] ?? [],
            volumes: $data['volumes'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'version' => $this->version,
            'include_container' => $this->includeContainer,
            'port' => $this->getDefaultPort(),
            'image' => $this->getDockerImage(),
            'environment' => array_merge($this->getDefaultEnvironment(), $this->environment),
            'volumes' => $this->volumes,
            'required_extensions' => $this->getRequiredExtensions(),
        ];
    }
}
