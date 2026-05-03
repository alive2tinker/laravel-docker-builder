<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Console\Commands;

use Alive2Tinker\DockerBuilder\DTO\BaseImageDTO;
use Alive2Tinker\DockerBuilder\DTO\DatabaseConfigDTO;
use Alive2Tinker\DockerBuilder\DTO\DeploymentTargetDTO;
use Alive2Tinker\DockerBuilder\DTO\ServiceDTO;
use Alive2Tinker\DockerBuilder\DTO\SslConfigDTO;
use Alive2Tinker\DockerBuilder\Services\Docker\Builders\DockerConfigBuilder;
use Alive2Tinker\DockerBuilder\Services\Docker\Detection\EnvironmentDetector;
use Alive2Tinker\DockerBuilder\Services\Docker\DockerBuildService;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class DockerBuildCommand extends Command
{
    protected $signature = 'docker:build
                            {--force : Skip confirmation prompts}
                            {--clean : Remove existing Docker configuration before generating}';

    protected $description = 'Interactively generate Docker configuration for your Laravel application';

    public function __construct(
        private readonly DockerBuildService $buildService,
        private readonly EnvironmentDetector $detector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->displayWelcome();

        // Step 1: Detect environment
        $detectionResult = spin(
            fn () => $this->detector->detect(),
            'Analyzing your Laravel application...'
        );

        $existingFiles = $this->detector->detectExistingDockerFiles();

        // Handle existing Docker files
        if (! empty($existingFiles)) {
            $this->displayExistingFiles($existingFiles);

            if (! $this->option('force')) {
                $shouldClean = confirm(
                    label: 'Remove existing Docker configuration and generate fresh files?',
                    default: true
                );

                if (! $shouldClean) {
                    warning('Operation cancelled. No changes were made.');

                    return self::SUCCESS;
                }
            }
        }

        // Step 2: Display and confirm detection results
        $this->displayDetectionResults($detectionResult);

        // Step 3: PHP Version selection
        $phpVersion = $this->selectPhpVersion($detectionResult->phpVersion);

        // Step 4: PHP Extensions selection
        $phpExtensions = $this->selectPhpExtensions($detectionResult->detectedExtensions);

        // Step 5: Base image selection
        $baseImage = $this->selectBaseImage($phpVersion);

        // Step 6: Database configuration
        $databaseConfig = $this->configureDatabaseService($detectionResult->database?->driver);

        // Step 7: SSL configuration
        $sslConfig = $this->configureSsl();

        // Step 8: Additional services
        $services = $this->selectAdditionalServices();

        // Step 9: Deployment targets
        $deploymentTargets = $this->selectDeploymentTargets();

        // Step 10: Port configuration
        $ports = $this->configureServicePorts($databaseConfig, $services);

        // Step 11: Build configuration
        $appName = $this->detector->getAppName();

        $builder = new DockerConfigBuilder;
        $config = $builder
            ->withAppName($appName)
            ->withDetectionResults($detectionResult)
            ->withPhpVersion($phpVersion)
            ->withPhpExtensions($phpExtensions)
            ->withBaseImage($baseImage)
            ->withDatabase($databaseConfig)
            ->withSsl($sslConfig)
            ->withPorts($ports);

        foreach ($services as $service) {
            $config = $config->withService($service);
        }

        foreach ($deploymentTargets as $target) {
            $config = $config->withDeploymentTarget($target);
        }

        $dockerConfig = $config->build();

        // Step 12: Confirm and generate
        $this->displayConfigurationSummary($dockerConfig);

        if (! $this->option('force')) {
            $confirmed = confirm(
                label: 'Generate Docker configuration with these settings?',
                default: true
            );

            if (! $confirmed) {
                warning('Operation cancelled. No changes were made.');

                return self::SUCCESS;
            }
        }

        // Step 12: Clean existing files if needed
        if (! empty($existingFiles) || $this->option('clean')) {
            spin(
                fn () => $this->buildService->cleanExistingConfigs(),
                'Removing existing Docker configuration...'
            );
        }

        // Step 13: Generate files
        $generatedFiles = spin(
            fn () => $this->buildService->generateFiles($dockerConfig),
            'Generating Docker configuration...'
        );

        // Step 14: Display success
        $this->displaySuccess($generatedFiles, $dockerConfig);

        return self::SUCCESS;
    }

    private function displayWelcome(): void
    {
        $this->newLine();
        info('Docker Build Command');
        note('Generate production-ready Docker configuration for your Laravel application.');
        $this->newLine();
    }

    private function displayExistingFiles(array $files): void
    {
        warning('Existing Docker configuration detected:');
        foreach ($files as $file) {
            $this->line("  - {$file}");
        }
        $this->newLine();
    }

    private function displayDetectionResults($result): void
    {
        info('Environment Detection Results:');
        $this->line("  PHP Version: {$result->phpVersion}");

        if (! empty($result->detectedExtensions)) {
            $this->line('  Detected Extensions: '.implode(', ', array_slice($result->detectedExtensions, 0, 8)));
            if (count($result->detectedExtensions) > 8) {
                $this->line('    ...and '.(count($result->detectedExtensions) - 8).' more');
            }
        }

        if ($result->database) {
            $this->line("  Database: {$result->database->driver}");
        }

        $this->newLine();
    }

    private function selectPhpVersion(string $detected): string
    {
        $versions = ['8.4', '8.3', '8.2', '8.1', '8.0'];

        // Find detected version in list
        $detectedMajorMinor = $detected;
        if (preg_match('/^(\d+\.\d+)/', $detected, $matches)) {
            $detectedMajorMinor = $matches[1];
        }

        $defaultIndex = array_search($detectedMajorMinor, $versions, true);
        if ($defaultIndex === false) {
            $defaultIndex = 0;
        }

        return select(
            label: 'Select PHP version',
            options: $versions,
            default: $versions[$defaultIndex],
            hint: "Detected: {$detected}"
        );
    }

    private function selectPhpExtensions(array $detected): array
    {
        $commonExtensions = [
            'bcmath', 'ctype', 'curl', 'dom', 'exif', 'fileinfo', 'gd',
            'intl', 'mbstring', 'opcache', 'pcntl', 'pdo', 'pdo_mysql',
            'pdo_pgsql', 'redis', 'soap', 'sockets', 'xml', 'zip',
        ];

        // Merge detected with common, remove duplicates
        $allExtensions = array_unique(array_merge($detected, $commonExtensions));
        sort($allExtensions);

        $options = [];
        foreach ($allExtensions as $ext) {
            $label = $ext;
            if (in_array($ext, $detected, true)) {
                $label .= ' (detected)';
            }
            $options[$ext] = $label;
        }

        $selected = multiselect(
            label: 'Select PHP extensions to install',
            options: $options,
            default: $detected,
            hint: 'Space to toggle, Enter to confirm',
            scroll: 15
        );

        // Allow adding custom extensions
        $addCustom = confirm(
            label: 'Add custom PHP extensions?',
            default: false
        );

        if ($addCustom) {
            $custom = text(
                label: 'Enter additional extensions (comma-separated)',
                placeholder: 'oci8, sqlsrv, mongodb',
                hint: 'These will be added to the selected extensions'
            );

            if (! empty($custom)) {
                $customExtensions = array_map('trim', explode(',', $custom));
                $selected = array_merge($selected, $customExtensions);
            }
        }

        return array_unique($selected);
    }

    private function selectBaseImage(string $phpVersion): BaseImageDTO
    {
        $imageTypes = [
            'fpm-nginx' => 'PHP-FPM with Nginx (Recommended)',
            'fpm-apache' => 'PHP-FPM with Apache',
            'apache' => 'Apache with mod_php',
            'fpm' => 'PHP-FPM only (requires separate web server)',
            'cli' => 'CLI only (for workers/schedulers)',
        ];

        $type = select(
            label: 'Select base image type',
            options: $imageTypes,
            default: 'fpm-nginx'
        );

        $variants = [
            'alpine' => 'Alpine Linux (smaller image)',
            'bookworm' => 'Debian Bookworm (stable)',
            'bullseye' => 'Debian Bullseye (older stable)',
        ];

        $variant = select(
            label: 'Select image variant',
            options: $variants,
            default: 'alpine'
        );

        return new BaseImageDTO(
            type: $type,
            phpVersion: $phpVersion,
            variant: $variant
        );
    }

    private function configureDatabaseService(?string $detectedDriver): ?DatabaseConfigDTO
    {
        $databaseTypes = [
            'none' => 'No database container',
            'mysql' => 'MySQL',
            'mariadb' => 'MariaDB',
            'postgresql' => 'PostgreSQL',
            'mongodb' => 'MongoDB',
            'mssql' => 'Microsoft SQL Server',
            'oracle' => 'Oracle Database',
            'sqlite' => 'SQLite (no container needed)',
            'remote' => 'Remote database (configure via environment)',
        ];

        $default = match ($detectedDriver) {
            'mysql' => 'mysql',
            'mariadb' => 'mariadb',
            'pgsql' => 'postgresql',
            'mongodb' => 'mongodb',
            'sqlsrv' => 'mssql',
            'oci8' => 'oracle',
            'sqlite' => 'sqlite',
            default => 'none',
        };

        $selected = select(
            label: 'Select database service',
            options: $databaseTypes,
            default: $default,
            hint: $detectedDriver ? "Detected: {$detectedDriver}" : null
        );

        if ($selected === 'none' || $selected === 'remote') {
            return null;
        }

        if ($selected === 'sqlite') {
            return new DatabaseConfigDTO(
                type: 'sqlite',
                version: null,
                includeContainer: false
            );
        }

        $versions = $this->getDatabaseVersions($selected);
        $version = select(
            label: "Select {$selected} version",
            options: $versions,
            default: $versions[0]
        );

        $includeContainer = confirm(
            label: 'Include database container in Docker Compose?',
            default: true,
            hint: 'Select No if using a remote database server'
        );

        return new DatabaseConfigDTO(
            type: $selected,
            version: $version,
            includeContainer: $includeContainer
        );
    }

    private function getDatabaseVersions(string $type): array
    {
        return match ($type) {
            'mysql' => ['8.4', '8.0', '5.7'],
            'mariadb' => ['11.4', '10.11', '10.6'],
            'postgresql' => ['17', '16', '15', '14'],
            'mongodb' => ['8.0', '7.0', '6.0'],
            'mssql' => ['2022-latest', '2019-latest'],
            'oracle' => ['23-slim', '21-slim', '19-slim'],
            default => ['latest'],
        };
    }

    private function configureSsl(): SslConfigDTO
    {
        $sslTypes = [
            'none' => 'No SSL (HTTP only)',
            'letsencrypt' => "Let's Encrypt (automated certificates)",
            'self-signed' => 'Self-signed certificate (development)',
            'custom' => 'Custom certificate (provide your own)',
        ];

        $selected = select(
            label: 'Select SSL configuration',
            options: $sslTypes,
            default: 'none',
            hint: 'SSL is recommended for production deployments'
        );

        if ($selected === 'none') {
            return SslConfigDTO::none();
        }

        $domain = text(
            label: 'Enter your domain name',
            placeholder: 'example.com',
            required: true,
            validate: fn (string $value) => strlen($value) < 3
                ? 'Domain must be at least 3 characters'
                : null
        );

        if ($selected === 'letsencrypt') {
            $email = text(
                label: "Enter email for Let's Encrypt notifications",
                placeholder: 'admin@example.com',
                required: true,
                validate: fn (string $value) => ! filter_var($value, FILTER_VALIDATE_EMAIL)
                    ? 'Please enter a valid email address'
                    : null
            );

            return SslConfigDTO::letsEncrypt($domain, $email);
        }

        if ($selected === 'self-signed') {
            return SslConfigDTO::selfSigned($domain);
        }

        // Custom certificate
        $certPath = text(
            label: 'Path to SSL certificate file',
            placeholder: '/path/to/cert.pem',
            required: true
        );

        $keyPath = text(
            label: 'Path to SSL private key file',
            placeholder: '/path/to/key.pem',
            required: true
        );

        return SslConfigDTO::custom($certPath, $keyPath, $domain);
    }

    private function selectAdditionalServices(): array
    {
        $serviceOptions = [
            'redis' => 'Redis (cache, sessions, queues)',
            'memcached' => 'Memcached (cache)',
            'node' => 'Node.js (asset compilation)',
            'worker' => 'Queue Worker (background jobs)',
            'scheduler' => 'Task Scheduler (cron)',
            'ssr' => 'Inertia SSR (server-side rendering)',
            'mailpit' => 'Mailpit (local email testing)',
        ];

        $selected = multiselect(
            label: 'Select additional services',
            options: $serviceOptions,
            default: [],
            hint: 'Space to toggle, Enter to confirm'
        );

        $services = [];

        foreach ($selected as $serviceType) {
            $service = match ($serviceType) {
                'redis' => ServiceDTO::redis(
                    select(
                        label: 'Select Redis version',
                        options: ['7-alpine', '6-alpine'],
                        default: '7-alpine'
                    )
                ),
                'memcached' => ServiceDTO::memcached(),
                'node' => ServiceDTO::node(
                    str_replace('-alpine', '', select(
                        label: 'Select Node.js version',
                        options: ['22-alpine', '20-alpine', '18-alpine'],
                        default: '20-alpine'
                    ))
                ),
                'worker' => ServiceDTO::worker(),
                'scheduler' => ServiceDTO::scheduler(),
                'ssr' => ServiceDTO::ssr(),
                'mailpit' => ServiceDTO::mailpit(),
                default => null,
            };

            if ($service !== null) {
                $services[] = $service;
            }
        }

        return $services;
    }

    private function selectDeploymentTargets(): array
    {
        $targetOptions = [
            'compose' => 'Docker Compose (local/single-server)',
            'swarm' => 'Docker Swarm (multi-node orchestration)',
            'kubernetes' => 'Kubernetes (container orchestration)',
        ];

        $selected = multiselect(
            label: 'Select deployment targets',
            options: $targetOptions,
            default: ['compose'],
            hint: 'You can generate configs for multiple targets',
            required: true
        );

        $targets = [];

        foreach ($selected as $targetType) {
            $target = match ($targetType) {
                'compose' => DeploymentTargetDTO::compose(),
                'swarm' => DeploymentTargetDTO::swarm(
                    replicas: (int) text(
                        label: 'Number of app replicas for Swarm',
                        default: '2',
                        validate: fn (string $value) => ! is_numeric($value) || (int) $value < 1
                            ? 'Please enter a positive number'
                            : null
                    )
                ),
                'kubernetes' => DeploymentTargetDTO::kubernetes(
                    namespace: text(
                        label: 'Kubernetes namespace',
                        default: 'default',
                        placeholder: 'my-app'
                    ),
                    replicas: (int) text(
                        label: 'Number of app replicas for Kubernetes',
                        default: '2',
                        validate: fn (string $value) => ! is_numeric($value) || (int) $value < 1
                            ? 'Please enter a positive number'
                            : null
                    )
                ),
                default => null,
            };

            if ($target !== null) {
                $targets[] = $target;
            }
        }

        return $targets;
    }

    private function configureServicePorts(?DatabaseConfigDTO $database, array $services): array
    {
        $ports = [];

        // Check if user wants to customize ports
        $customizePorts = confirm(
            label: 'Customize service ports?',
            default: false,
            hint: 'Useful if default ports conflict with local services'
        );

        if (! $customizePorts) {
            return $ports;
        }

        // Web ports
        $webPort = text(
            label: 'HTTP port (default: 80)',
            default: '80',
            validate: fn (string $value) => ! is_numeric($value) || (int) $value < 1 || (int) $value > 65535
                ? 'Please enter a valid port number (1-65535)'
                : null
        );
        if ($webPort !== '80') {
            $ports['web'] = (int) $webPort;
        }

        $webSslPort = text(
            label: 'HTTPS port (default: 443)',
            default: '443',
            validate: fn (string $value) => ! is_numeric($value) || (int) $value < 1 || (int) $value > 65535
                ? 'Please enter a valid port number (1-65535)'
                : null
        );
        if ($webSslPort !== '443') {
            $ports['web_ssl'] = (int) $webSslPort;
        }

        // Database port
        if ($database !== null && $database->includeContainer) {
            $defaultDbPort = (string) $database->getDefaultPort();
            $dbPort = text(
                label: "Database port ({$database->type}, default: {$defaultDbPort})",
                default: $defaultDbPort,
                validate: fn (string $value) => ! is_numeric($value) || (int) $value < 1 || (int) $value > 65535
                    ? 'Please enter a valid port number (1-65535)'
                    : null
            );
            if ($dbPort !== $defaultDbPort) {
                $ports['database'] = (int) $dbPort;
            }
        }

        // Service ports
        foreach ($services as $service) {
            if (in_array($service->type, ['worker', 'scheduler', 'node', 'ssr'], true)) {
                continue; // These don't expose ports externally
            }

            $defaultPort = (string) $service->getDefaultPort();
            if ($defaultPort === '0') {
                continue;
            }

            $servicePort = text(
                label: "{$service->name} port (default: {$defaultPort})",
                default: $defaultPort,
                validate: fn (string $value) => ! is_numeric($value) || (int) $value < 1 || (int) $value > 65535
                    ? 'Please enter a valid port number (1-65535)'
                    : null
            );
            if ($servicePort !== $defaultPort) {
                $ports[$service->type] = (int) $servicePort;
            }
        }

        return $ports;
    }

    private function displayConfigurationSummary($config): void
    {
        $this->newLine();
        info('Configuration Summary:');
        $this->line("  App Name: {$config->appName}");
        $this->line("  PHP Version: {$config->phpVersion}");
        $this->line("  Base Image: {$config->baseImage->getFullImageName()}");
        $this->line('  Extensions: '.count($config->phpExtensions).' selected');

        if ($config->hasDatabase()) {
            $this->line("  Database: {$config->database->type} {$config->database->version}");
        }

        $this->line("  SSL: {$config->ssl->type}");

        $serviceNames = array_map(fn ($s) => $s->type, $config->services);
        if (! empty($serviceNames)) {
            $this->line('  Services: '.implode(', ', $serviceNames));
        }

        $targetNames = array_map(fn ($t) => $t->type, $config->deploymentTargets);
        $this->line('  Deployment: '.implode(', ', $targetNames));

        $this->newLine();
    }

    private function displaySuccess(array $generatedFiles, $config): void
    {
        $this->newLine();
        outro('Docker configuration generated successfully!');

        $this->newLine();
        info('Generated Files:');
        foreach ($generatedFiles as $file) {
            $relativePath = str_replace(base_path().'/', '', $file);
            $this->line("  - {$relativePath}");
        }

        $this->newLine();
        info('Next Steps:');

        $hasCompose = collect($config->deploymentTargets)->contains(fn ($t) => $t->type === 'compose');
        $hasSwarm = collect($config->deploymentTargets)->contains(fn ($t) => $t->type === 'swarm');
        $hasK8s = collect($config->deploymentTargets)->contains(fn ($t) => $t->type === 'kubernetes');

        $step = 1;

        $this->line("  {$step}. Review and customize the generated files as needed");
        $step++;

        $this->line("  {$step}. Copy .env.docker to .env and configure your environment");
        $step++;

        if ($config->ssl->type === 'self-signed') {
            $this->line("  {$step}. Generate SSL certificate: ./generate-ssl.sh");
            $step++;
        }

        if ($hasCompose) {
            $this->line("  {$step}. Build and start: docker compose up -d --build");
            $step++;

            if ($config->ssl->type === 'letsencrypt') {
                $this->line("  {$step}. Initialize SSL: ./init-letsencrypt.sh");
                $step++;
            }
        }

        if ($hasSwarm) {
            $this->line("  {$step}. Deploy to Swarm: docker stack deploy -c docker-compose.swarm.yml {$config->appName}");
            $step++;
        }

        if ($hasK8s) {
            $this->line("  {$step}. Apply Kubernetes: kubectl apply -f k8s/");
            $step++;
        }

        $this->newLine();
        note('For more information, see the generated README or visit the Laravel documentation.');
    }
}
