<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Detection;

use Alive2Tinker\DockerBuilder\DTO\DatabaseInfoDTO;
use Illuminate\Support\Facades\File;

class DatabaseDetector
{
    protected ?array $composerJson = null;

    public function __construct(
        protected string $basePath = '',
    ) {
        if ($this->basePath === '') {
            $this->basePath = base_path();
        }
    }

    public function detect(): ?DatabaseInfoDTO
    {
        // First, try to detect from composer.json packages
        $fromPackages = $this->detectFromPackages();
        if ($fromPackages !== null) {
            return $fromPackages;
        }

        // Then try to detect from config/database.php
        $fromConfig = $this->detectFromConfig();
        if ($fromConfig !== null) {
            return $fromConfig;
        }

        // Finally try to detect from .env
        return $this->detectFromEnv();
    }

    protected function detectFromPackages(): ?DatabaseInfoDTO
    {
        $composerJson = $this->getComposerJson();
        $require = $composerJson['require'] ?? [];

        // Check for specific database packages
        $packageMapping = [
            'yajra/laravel-oci8' => 'oracle',
            'jenssegers/mongodb' => 'mongodb',
            'mongodb/laravel-mongodb' => 'mongodb',
            'doctrine/dbal' => null, // Generic, needs further detection
        ];

        foreach ($packageMapping as $package => $driver) {
            if (isset($require[$package]) && $driver !== null) {
                return DatabaseInfoDTO::fromDriver($driver);
            }
        }

        return null;
    }

    protected function detectFromConfig(): ?DatabaseInfoDTO
    {
        $configPath = $this->basePath.'/config/database.php';

        if (! File::exists($configPath)) {
            return null;
        }

        try {
            $content = File::get($configPath);

            // Look for default connection setting
            if (preg_match("/['\"]default['\"]\s*=>\s*env\(['\"]DB_CONNECTION['\"]\s*,\s*['\"](\w+)['\"]\)/", $content, $matches)) {
                return DatabaseInfoDTO::fromDriver($matches[1]);
            }

            // Look for hardcoded default
            if (preg_match("/['\"]default['\"]\s*=>\s*['\"](\w+)['\"]/", $content, $matches)) {
                return DatabaseInfoDTO::fromDriver($matches[1]);
            }
        } catch (\Exception $e) {
            // Ignore parsing errors
        }

        return null;
    }

    protected function detectFromEnv(): ?DatabaseInfoDTO
    {
        $envPath = $this->basePath.'/.env';

        if (! File::exists($envPath)) {
            return null;
        }

        try {
            $content = File::get($envPath);

            if (preg_match('/^DB_CONNECTION=(\w+)/m', $content, $matches)) {
                return DatabaseInfoDTO::fromDriver($matches[1]);
            }
        } catch (\Exception $e) {
            // Ignore parsing errors
        }

        return null;
    }

    public function getAvailableDatabases(): array
    {
        return [
            'mysql' => [
                'name' => 'MySQL',
                'versions' => ['8.4', '8.0', '5.7'],
                'default_version' => '8.0',
            ],
            'mariadb' => [
                'name' => 'MariaDB',
                'versions' => ['11.4', '10.11', '10.6'],
                'default_version' => '10.11',
            ],
            'postgresql' => [
                'name' => 'PostgreSQL',
                'versions' => ['17', '16', '15', '14'],
                'default_version' => '16',
            ],
            'sqlite' => [
                'name' => 'SQLite',
                'versions' => [],
                'default_version' => '',
            ],
            'oracle' => [
                'name' => 'Oracle Database',
                'versions' => ['21.3.0-xe', '18.4.0-xe'],
                'default_version' => '21.3.0-xe',
            ],
            'mongodb' => [
                'name' => 'MongoDB',
                'versions' => ['7.0', '6.0', '5.0'],
                'default_version' => '7.0',
            ],
            'mssql' => [
                'name' => 'Microsoft SQL Server',
                'versions' => ['2022-latest', '2019-latest'],
                'default_version' => '2022-latest',
            ],
        ];
    }

    protected function getComposerJson(): array
    {
        if ($this->composerJson !== null) {
            return $this->composerJson;
        }

        $composerPath = $this->basePath.'/composer.json';

        if (! File::exists($composerPath)) {
            return [];
        }

        $content = File::get($composerPath);
        $this->composerJson = json_decode($content, true) ?? [];

        return $this->composerJson;
    }
}
