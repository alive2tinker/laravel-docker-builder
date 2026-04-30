<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Builders;

use Alive2Tinker\DockerBuilder\DTO\BaseImageDTO;
use Alive2Tinker\DockerBuilder\DTO\DatabaseConfigDTO;
use Alive2Tinker\DockerBuilder\DTO\DeploymentTargetDTO;
use Alive2Tinker\DockerBuilder\DTO\DetectionResultDTO;
use Alive2Tinker\DockerBuilder\DTO\DockerConfigDTO;
use Alive2Tinker\DockerBuilder\DTO\ServiceDTO;
use Alive2Tinker\DockerBuilder\DTO\SslConfigDTO;
use Alive2Tinker\DockerBuilder\Services\Docker\Support\ExtensionMapper;
use InvalidArgumentException;

class DockerConfigBuilder
{
    protected string $appName = 'laravel-app';

    protected string $phpVersion = '8.3';

    protected array $phpExtensions = [];

    protected ?BaseImageDTO $baseImage = null;

    protected ?DatabaseConfigDTO $database = null;

    protected ?SslConfigDTO $ssl = null;

    /** @var ServiceDTO[] */
    protected array $services = [];

    /** @var DeploymentTargetDTO[] */
    protected array $deploymentTargets = [];

    protected ?DetectionResultDTO $detectionResults = null;

    protected array $systemDependencies = [];

    protected ?string $nodeVersion = null;

    protected string $packageManager = 'npm';

    protected array $ports = [];

    protected ExtensionMapper $extensionMapper;

    public function __construct()
    {
        $this->extensionMapper = new ExtensionMapper;
    }

    public function withAppName(string $appName): self
    {
        $clone = clone $this;
        $clone->appName = $appName;

        return $clone;
    }

    public function withDetectionResults(DetectionResultDTO $results): self
    {
        $clone = clone $this;
        $clone->detectionResults = $results;
        $clone->phpVersion = $results->phpVersion;
        $clone->phpExtensions = $results->detectedExtensions;
        $clone->systemDependencies = $results->getAllSystemDependencies();

        return $clone;
    }

    public function withPhpVersion(string $version): self
    {
        $clone = clone $this;
        $clone->phpVersion = $version;

        return $clone;
    }

    public function withPhpExtensions(array $extensions): self
    {
        $clone = clone $this;
        $clone->phpExtensions = $extensions;

        // Update system dependencies based on extensions
        $clone->systemDependencies = $clone->extensionMapper->getAllSystemPackages($extensions);

        return $clone;
    }

    public function addPhpExtension(string $extension): self
    {
        $clone = clone $this;
        $clone->phpExtensions[] = $extension;
        $clone->phpExtensions = array_unique($clone->phpExtensions);

        // Update system dependencies
        $clone->systemDependencies = $clone->extensionMapper->getAllSystemPackages($clone->phpExtensions);

        return $clone;
    }

    public function withBaseImage(BaseImageDTO|string $typeOrDto, ?string $variant = null): self
    {
        $clone = clone $this;

        if ($typeOrDto instanceof BaseImageDTO) {
            $clone->baseImage = $typeOrDto;
        } else {
            $clone->baseImage = new BaseImageDTO(
                type: $typeOrDto,
                phpVersion: $clone->phpVersion,
                variant: $variant ?? 'debian',
            );
        }

        return $clone;
    }

    public function withDatabase(DatabaseConfigDTO|string|null $typeOrDto, string $version = '', bool $includeContainer = true): self
    {
        $clone = clone $this;

        if ($typeOrDto === null) {
            $clone->database = null;

            return $clone;
        }

        if ($typeOrDto instanceof DatabaseConfigDTO) {
            $clone->database = $typeOrDto;
        } elseif ($includeContainer) {
            $clone->database = new DatabaseConfigDTO(
                type: $typeOrDto,
                version: $version,
                includeContainer: true,
            );
        } else {
            $clone->database = DatabaseConfigDTO::remote($typeOrDto);
        }

        // Add database extensions to the list
        if ($clone->database !== null) {
            $dbExtensions = $clone->database->getRequiredExtensions();
            $clone->phpExtensions = array_unique(array_merge($clone->phpExtensions, $dbExtensions));
            $clone->systemDependencies = $clone->extensionMapper->getAllSystemPackages($clone->phpExtensions);
        }

        return $clone;
    }

    public function withRemoteDatabase(string $type): self
    {
        return $this->withDatabase($type, '', false);
    }

    public function withSsl(SslConfigDTO|string $typeOrDto, array $options = []): self
    {
        $clone = clone $this;

        if ($typeOrDto instanceof SslConfigDTO) {
            $clone->ssl = $typeOrDto;
        } else {
            $clone->ssl = match ($typeOrDto) {
                'none' => SslConfigDTO::none(),
                'letsencrypt' => SslConfigDTO::letsEncrypt(
                    domain: $options['domain'] ?? '',
                    email: $options['email'] ?? '',
                ),
                'self-signed' => SslConfigDTO::selfSigned(
                    domain: $options['domain'] ?? null,
                ),
                'custom' => SslConfigDTO::custom(
                    certPath: $options['cert_path'] ?? '',
                    keyPath: $options['key_path'] ?? '',
                    domain: $options['domain'] ?? null,
                ),
                default => SslConfigDTO::none(),
            };
        }

        return $clone;
    }

    public function withService(ServiceDTO|string $typeOrDto, array $config = []): self
    {
        $clone = clone $this;

        if ($typeOrDto instanceof ServiceDTO) {
            $service = $typeOrDto;
        } else {
            $service = match ($typeOrDto) {
                'redis' => ServiceDTO::redis($config['version'] ?? 'alpine'),
                'memcached' => ServiceDTO::memcached($config['version'] ?? 'alpine'),
                'node' => ServiceDTO::node($config['version'] ?? '20'),
                'worker' => ServiceDTO::worker(),
                'scheduler' => ServiceDTO::scheduler(),
                'mailpit' => ServiceDTO::mailpit(),
                default => new ServiceDTO(name: $typeOrDto, type: $typeOrDto, config: $config),
            };
        }

        $clone->services[] = $service;

        // If node is added, set the node version
        if ($service->type === 'node') {
            $clone->nodeVersion = $service->config['version'] ?? '20';
        }

        // If redis is added, ensure redis extension is present
        if ($service->type === 'redis') {
            $clone->phpExtensions = array_unique(array_merge($clone->phpExtensions, ['redis']));
        }

        return $clone;
    }

    public function withServices(array $services): self
    {
        $clone = clone $this;

        foreach ($services as $type => $config) {
            if ($config instanceof ServiceDTO) {
                $clone = $clone->withService($config);
            } elseif (is_string($config)) {
                $clone = $clone->withService($config);
            } else {
                $clone = $clone->withService($type, $config);
            }
        }

        return $clone;
    }

    public function withNodeVersion(string $version): self
    {
        $clone = clone $this;
        $clone->nodeVersion = $version;

        return $clone;
    }

    public function withPackageManager(string $manager): self
    {
        $clone = clone $this;
        $clone->packageManager = $manager;

        return $clone;
    }

    public function withPorts(array $ports): self
    {
        $clone = clone $this;
        $clone->ports = array_merge($clone->ports, $ports);

        return $clone;
    }

    public function withPort(string $service, int $port): self
    {
        $clone = clone $this;
        $clone->ports[$service] = $port;

        return $clone;
    }

    public function withDeploymentTarget(DeploymentTargetDTO|string $typeOrDto, array $options = []): self
    {
        $clone = clone $this;

        if ($typeOrDto instanceof DeploymentTargetDTO) {
            $target = $typeOrDto;
        } else {
            $target = match ($typeOrDto) {
                'compose' => DeploymentTargetDTO::compose(),
                'swarm' => DeploymentTargetDTO::swarm($options['replicas'] ?? 1),
                'kubernetes' => DeploymentTargetDTO::kubernetes(
                    namespace: $options['namespace'] ?? 'default',
                    replicas: $options['replicas'] ?? 1,
                ),
                default => DeploymentTargetDTO::compose(),
            };
        }

        $clone->deploymentTargets[] = $target;

        return $clone;
    }

    public function build(): DockerConfigDTO
    {
        $this->validate();

        $targets = ! empty($this->deploymentTargets)
            ? $this->deploymentTargets
            : [DeploymentTargetDTO::compose()];

        return new DockerConfigDTO(
            appName: $this->appName,
            phpVersion: $this->phpVersion,
            phpExtensions: array_unique($this->phpExtensions),
            baseImage: $this->baseImage ?? new BaseImageDTO(
                type: 'fpm-nginx',
                phpVersion: $this->phpVersion,
            ),
            database: $this->database,
            ssl: $this->ssl,
            services: $this->services,
            deploymentTargets: $targets,
            detectionResults: $this->detectionResults ?? new DetectionResultDTO(
                phpVersion: $this->phpVersion,
                detectedExtensions: $this->phpExtensions,
                extensionDependencies: [],
            ),
            systemDependencies: $this->systemDependencies,
            nodeVersion: $this->nodeVersion,
            packageManager: $this->packageManager,
            ports: $this->ports,
        );
    }

    protected function validate(): void
    {
        if (empty($this->phpVersion)) {
            throw new InvalidArgumentException('PHP version is required');
        }

        if (! preg_match('/^\d+\.\d+$/', $this->phpVersion)) {
            throw new InvalidArgumentException('PHP version must be in format X.Y (e.g., 8.3)');
        }

        if ($this->ssl !== null && $this->ssl->isLetsEncrypt()) {
            if (empty($this->ssl->domain)) {
                throw new InvalidArgumentException('Domain is required for Let\'s Encrypt SSL');
            }
            if (empty($this->ssl->email)) {
                throw new InvalidArgumentException('Email is required for Let\'s Encrypt SSL');
            }
        }

        if ($this->ssl !== null && $this->ssl->isCustom()) {
            if (empty($this->ssl->certPath) || empty($this->ssl->keyPath)) {
                throw new InvalidArgumentException('Certificate and key paths are required for custom SSL');
            }
        }
    }

    public function reset(): self
    {
        return new self;
    }

    public static function create(): self
    {
        return new self;
    }
}
