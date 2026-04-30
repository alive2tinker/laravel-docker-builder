<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Detection;

use Illuminate\Support\Facades\File;
use RuntimeException;

class PhpVersionDetector
{
    protected ?array $composerJson = null;

    protected ?array $composerLock = null;

    public function __construct(
        protected string $basePath = '',
    ) {
        if ($this->basePath === '') {
            $this->basePath = base_path();
        }
    }

    public function detect(): string
    {
        // First, check composer.lock for the highest PHP version required by locked packages
        $lockVersion = $this->detectFromLock();
        if ($lockVersion !== null) {
            return $lockVersion;
        }

        // Fall back to composer.json requirement
        $composerJson = $this->getComposerJson();

        $phpRequirement = $composerJson['require']['php'] ?? null;

        if ($phpRequirement === null) {
            return $this->getDefaultVersion();
        }

        return $this->parseVersionConstraint($phpRequirement);
    }

    /**
     * Detect PHP version from composer.lock by finding the highest required version.
     */
    protected function detectFromLock(): ?string
    {
        $lockPath = $this->basePath.'/composer.lock';

        if (! File::exists($lockPath)) {
            return null;
        }

        $content = File::get($lockPath);
        $lock = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || $lock === null) {
            return null;
        }

        $this->composerLock = $lock;

        $highestVersion = '0.0';
        $packages = array_merge(
            $lock['packages'] ?? [],
            $lock['packages-dev'] ?? []
        );

        foreach ($packages as $package) {
            $phpRequirement = $package['require']['php'] ?? null;
            if ($phpRequirement === null) {
                continue;
            }

            $minVersion = $this->extractMinimumVersion($phpRequirement);
            if ($minVersion !== null && version_compare($minVersion, $highestVersion, '>')) {
                $highestVersion = $minVersion;
            }
        }

        if ($highestVersion === '0.0') {
            return null;
        }

        return $highestVersion;
    }

    /**
     * Extract the minimum PHP version from a constraint.
     * Handles constraints like: >=8.4, ^8.3, ~8.4.0, 8.3|8.4, etc.
     */
    protected function extractMinimumVersion(string $constraint): ?string
    {
        $versions = [];

        // Split by | or || for OR constraints
        $parts = preg_split('/\s*\|\|?\s*/', $constraint);

        foreach ($parts as $part) {
            $part = trim($part);

            // Handle >= constraint (minimum version)
            if (preg_match('/>=\s*(\d+\.\d+)/', $part, $matches)) {
                $versions[] = $matches[1];

                continue;
            }

            // Handle ~ constraint (tilde: allows patch versions)
            // ~8.4.0 means >=8.4.0 <8.5.0, so minimum is 8.4
            if (preg_match('/~(\d+\.\d+)/', $part, $matches)) {
                $versions[] = $matches[1];

                continue;
            }

            // Handle ^ constraint (caret: allows minor versions for >=1.0, patch for <1.0)
            // ^8.3 means >=8.3.0 <9.0.0, so minimum is 8.3
            if (preg_match('/\^(\d+\.\d+)/', $part, $matches)) {
                $versions[] = $matches[1];

                continue;
            }

            // Handle exact or plain version
            if (preg_match('/^(\d+\.\d+)/', $part, $matches)) {
                $versions[] = $matches[1];
            }
        }

        if (empty($versions)) {
            return null;
        }

        // For OR constraints, we need the minimum of the options
        // But for our purposes, we want to find packages that REQUIRE a high version
        // So we take the minimum from OR constraints (what the package actually needs)
        return min($versions);
    }

    protected function parseVersionConstraint(string $constraint): string
    {
        // Remove common constraint operators
        $cleaned = preg_replace('/[\^~>=<|,\s]/', '', $constraint);

        // Extract major.minor version
        if (preg_match('/^(\d+\.\d+)/', $cleaned, $matches)) {
            return $matches[1];
        }

        // Just major version (e.g., "8")
        if (preg_match('/^(\d+)/', $cleaned, $matches)) {
            return $matches[1].'.3'; // Default to .3 minor version
        }

        return $this->getDefaultVersion();
    }

    protected function getDefaultVersion(): string
    {
        // Return current PHP version as fallback
        return PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
    }

    public function getFullVersion(): string
    {
        return $this->detect().'.0';
    }

    public function getAvailableVersions(): array
    {
        return [
            '8.4' => 'PHP 8.4 (Latest)',
            '8.3' => 'PHP 8.3 (LTS)',
            '8.2' => 'PHP 8.2',
            '8.1' => 'PHP 8.1',
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
