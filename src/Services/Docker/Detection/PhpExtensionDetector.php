<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Detection;

use Alive2Tinker\DockerBuilder\Services\Docker\Support\ExtensionMapper;
use Illuminate\Support\Facades\File;
use RuntimeException;

class PhpExtensionDetector
{
    protected ?array $composerJson = null;

    protected ?array $composerLock = null;

    public function __construct(
        protected ExtensionMapper $extensionMapper,
        protected string $basePath = '',
    ) {
        if ($this->basePath === '') {
            $this->basePath = base_path();
        }
    }

    public function detect(): array
    {
        $extensions = [];

        // Get extensions from composer.json require
        $extensions = array_merge($extensions, $this->getExtensionsFromRequire());

        // Get extensions from installed packages
        $extensions = array_merge($extensions, $this->getExtensionsFromPackages());

        // Add common Laravel extensions
        $extensions = array_merge($extensions, $this->getCommonLaravelExtensions());

        return array_unique(array_filter($extensions));
    }

    protected function getExtensionsFromRequire(): array
    {
        $composerJson = $this->getComposerJson();
        $require = array_merge(
            $composerJson['require'] ?? [],
            $composerJson['require-dev'] ?? [],
        );

        $extensions = [];

        foreach ($require as $package => $version) {
            // Direct ext- requirements
            if (str_starts_with($package, 'ext-')) {
                $extensions[] = substr($package, 4);
            }
        }

        return $extensions;
    }

    protected function getExtensionsFromPackages(): array
    {
        $composerJson = $this->getComposerJson();
        $require = array_merge(
            $composerJson['require'] ?? [],
            $composerJson['require-dev'] ?? [],
        );

        $extensions = [];

        foreach (array_keys($require) as $package) {
            $packageExtensions = $this->extensionMapper->getExtensionsForPackage($package);
            $extensions = array_merge($extensions, $packageExtensions);
        }

        return $extensions;
    }

    protected function getCommonLaravelExtensions(): array
    {
        return [
            'bcmath',
            'ctype',
            'fileinfo',
            'json',
            'mbstring',
            'openssl',
            'pdo',
            'tokenizer',
            'xml',
            'curl',
        ];
    }

    public function getExtensionDependencies(array $extensions): array
    {
        $dependencies = [];

        foreach ($extensions as $extension) {
            $deps = $this->extensionMapper->getInstallationDependencies($extension);
            if (! empty($deps)) {
                $dependencies[$extension] = $deps;
            }
        }

        return $dependencies;
    }

    public function getExtensionInstallMethod(string $extension): string
    {
        return $this->extensionMapper->getInstallMethod($extension);
    }

    public function requiresCustomInstall(string $extension): bool
    {
        return $this->extensionMapper->requiresCustomInstall($extension);
    }

    public function getCustomInstallScript(string $extension): ?string
    {
        return $this->extensionMapper->getCustomInstallScript($extension);
    }

    public function getDefaultExtensions(): array
    {
        return [
            'bcmath' => 'BCMath (arbitrary precision mathematics)',
            'ctype' => 'Character type checking',
            'curl' => 'cURL (HTTP client)',
            'dom' => 'Document Object Model',
            'fileinfo' => 'File information',
            'gd' => 'Image processing',
            'intl' => 'Internationalization',
            'mbstring' => 'Multibyte string',
            'openssl' => 'OpenSSL encryption',
            'pcntl' => 'Process control (for queues)',
            'pdo' => 'PHP Data Objects',
            'redis' => 'Redis client',
            'soap' => 'SOAP protocol',
            'xml' => 'XML parsing',
            'zip' => 'ZIP compression',
        ];
    }

    public function getDatabaseExtensions(): array
    {
        return [
            'pdo_mysql' => 'MySQL/MariaDB',
            'pdo_pgsql' => 'PostgreSQL',
            'pdo_sqlite' => 'SQLite',
            'pdo_sqlsrv' => 'Microsoft SQL Server',
            'oci8' => 'Oracle Database',
            'pdo_oci' => 'Oracle PDO',
            'mongodb' => 'MongoDB',
        ];
    }

    protected function getComposerJson(): array
    {
        if ($this->composerJson !== null) {
            return $this->composerJson;
        }

        $composerPath = $this->basePath.'/composer.json';

        if (! File::exists($composerPath)) {
            throw new RuntimeException("composer.json not found at: {$composerPath}");
        }

        $content = File::get($composerPath);
        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON in composer.json: '.json_last_error_msg());
        }

        $this->composerJson = $decoded;

        return $this->composerJson;
    }
}
