<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Support;

class ExtensionMapper
{
    /**
     * Maps composer packages to their required PHP extensions.
     */
    protected array $packageExtensions = [
        // Database packages
        // Note: pdo_oci is not available in PHP 8.4+, use oci8 only
        'yajra/laravel-oci8' => ['oci8'],
        'jenssegers/mongodb' => ['mongodb'],
        'mongodb/laravel-mongodb' => ['mongodb'],

        // Cache & Queue
        'predis/predis' => ['redis'],
        'phpredis/phpredis' => ['redis'],

        // Image processing
        'intervention/image' => ['gd'],
        'intervention/image-laravel' => ['gd'],
        'spatie/image' => ['gd'],

        // HTTP & API
        'guzzlehttp/guzzle' => ['curl'],

        // SFTP & SSH
        'league/flysystem-sftp' => ['ssh2'],
        'league/flysystem-sftp-v3' => ['ssh2'],
        'phpseclib/phpseclib' => [],

        // SOAP
        'wsdltophp/packagegenerator' => ['soap'],

        // PDF
        'barryvdh/laravel-dompdf' => ['gd', 'dom'],
        'mpdf/mpdf' => ['gd', 'mbstring'],

        // Excel & Spreadsheet
        'maatwebsite/excel' => ['zip', 'gd', 'xml'],
        'phpoffice/phpspreadsheet' => ['zip', 'gd', 'xml'],
        'openspout/openspout' => ['zip'],

        // Backup & Log Viewer
        'spatie/laravel-backup' => ['zip'],
        'gboquizosanchez/filament-log-viewer' => ['zip'],

        // Internationalization
        'mcamara/laravel-localization' => ['intl'],
    ];

    /**
     * Maps extensions to their system dependencies and install requirements.
     */
    protected array $extensionDependencies = [
        // Oracle - libaio handled in install script for Debian compatibility
        'oci8' => [
            'type' => 'custom',
            'system_packages' => ['wget', 'unzip'],
            'requires_oracle_client' => true,
        ],
        'pdo_oci' => [
            'type' => 'custom',
            'system_packages' => [],
            'requires_oracle_client' => true,
        ],

        // Microsoft SQL Server
        'sqlsrv' => [
            'type' => 'pecl',
            'system_packages' => ['unixodbc-dev', 'gnupg2'],
            'requires_mssql_driver' => true,
        ],
        'pdo_sqlsrv' => [
            'type' => 'pecl',
            'system_packages' => ['unixodbc-dev', 'gnupg2'],
            'requires_mssql_driver' => true,
        ],

        // MongoDB
        'mongodb' => [
            'type' => 'pecl',
            'pecl_name' => 'mongodb',
            'system_packages' => [],
        ],

        // Redis
        'redis' => [
            'type' => 'pecl',
            'pecl_name' => 'redis',
            'system_packages' => [],
        ],

        // Image processing
        'gd' => [
            'type' => 'standard',
            'system_packages' => ['libpng-dev', 'libjpeg-dev', 'libfreetype6-dev', 'libwebp-dev'],
            'configure_options' => '--with-freetype --with-jpeg --with-webp',
        ],
        'imagick' => [
            'type' => 'pecl',
            'pecl_name' => 'imagick',
            'system_packages' => ['libmagickwand-dev'],
        ],

        // SSH
        'ssh2' => [
            'type' => 'pecl',
            'pecl_name' => 'ssh2',
            'system_packages' => ['libssh2-1-dev'],
        ],

        // Internationalization
        'intl' => [
            'type' => 'standard',
            'system_packages' => ['libicu-dev'],
        ],

        // SOAP
        'soap' => [
            'type' => 'standard',
            'system_packages' => ['libxml2-dev'],
        ],

        // ZIP
        'zip' => [
            'type' => 'standard',
            'system_packages' => ['libzip-dev'],
        ],

        // PostgreSQL
        'pgsql' => [
            'type' => 'standard',
            'system_packages' => ['libpq-dev'],
        ],
        'pdo_pgsql' => [
            'type' => 'standard',
            'system_packages' => ['libpq-dev'],
        ],

        // LDAP
        'ldap' => [
            'type' => 'standard',
            'system_packages' => ['libldap2-dev'],
        ],

        // XSL (xml is bundled, but xsl needs to be installed)
        'xsl' => [
            'type' => 'standard',
            'system_packages' => ['libxslt-dev'],
        ],

        // YAML
        'yaml' => [
            'type' => 'pecl',
            'pecl_name' => 'yaml',
            'system_packages' => ['libyaml-dev'],
        ],

        // IMAP
        'imap' => [
            'type' => 'standard',
            'system_packages' => ['libc-client-dev', 'libkrb5-dev'],
            'configure_options' => '--with-kerberos --with-imap-ssl',
        ],

        // GMP
        'gmp' => [
            'type' => 'standard',
            'system_packages' => ['libgmp-dev'],
        ],

        // Tidy
        'tidy' => [
            'type' => 'standard',
            'system_packages' => ['libtidy-dev'],
        ],

        // Gettext
        'gettext' => [
            'type' => 'standard',
            'system_packages' => [],
        ],

        // BZ2
        'bz2' => [
            'type' => 'standard',
            'system_packages' => ['libbz2-dev'],
        ],
    ];

    /**
     * Extensions that are bundled with PHP and don't need installation.
     */
    protected array $bundledExtensions = [
        'json',
        'pdo',
        'pdo_sqlite',
        'sqlite3',
        'tokenizer',
        'ctype',
        'session',
        'filter',
        'hash',
        'date',
        'pcre',
        'spl',
        'standard',
        'curl',       // Bundled in PHP Docker images
        'openssl',    // Bundled in PHP Docker images
        'mbstring',   // Bundled in PHP Docker images
        'fileinfo',   // Bundled in PHP Docker images
        'dom',        // Bundled in PHP Docker images
        'xml',        // Bundled in PHP Docker images (libxml)
        'simplexml',  // Bundled in PHP Docker images
        'xmlreader',  // Bundled in PHP Docker images
        'xmlwriter',  // Bundled in PHP Docker images
        'phar',       // Bundled in PHP Docker images
        'posix',      // Bundled in PHP Docker images
        'readline',   // Bundled in PHP Docker images
        'reflection', // Bundled in PHP Docker images
        'iconv',      // Bundled in PHP Docker images
        'sodium',     // Bundled in PHP 7.2+
        'ffi',        // Bundled in PHP 7.4+
    ];

    /**
     * Extensions that can be installed via docker-php-ext-install.
     */
    protected array $standardExtensions = [
        'bcmath',
        'calendar',
        'dba',
        'exif',
        'ftp',
        'gettext',
        'gd',
        'gmp',
        'imap',
        'intl',
        'ldap',
        'opcache',
        'pcntl',
        'pdo_mysql',
        'pdo_pgsql',
        'pgsql',
        'mysqli',
        'shmop',
        'soap',
        'sockets',
        'sysvmsg',
        'sysvsem',
        'sysvshm',
        'tidy',
        'xsl',
        'zip',
    ];

    public function getExtensionsForPackage(string $package): array
    {
        return $this->packageExtensions[$package] ?? [];
    }

    public function getInstallationDependencies(string $extension): array
    {
        if ($this->isBundled($extension)) {
            return [];
        }

        $deps = $this->extensionDependencies[$extension] ?? [];

        return $deps['system_packages'] ?? [];
    }

    public function getInstallMethod(string $extension): string
    {
        if ($this->isBundled($extension)) {
            return 'bundled';
        }

        if (in_array($extension, $this->standardExtensions, true)) {
            return 'standard';
        }

        $deps = $this->extensionDependencies[$extension] ?? [];

        return $deps['type'] ?? 'standard';
    }

    public function requiresCustomInstall(string $extension): bool
    {
        $deps = $this->extensionDependencies[$extension] ?? [];

        return ($deps['type'] ?? '') === 'custom';
    }

    public function requiresOracleClient(string $extension): bool
    {
        $deps = $this->extensionDependencies[$extension] ?? [];

        return $deps['requires_oracle_client'] ?? false;
    }

    public function requiresMssqlDriver(string $extension): bool
    {
        $deps = $this->extensionDependencies[$extension] ?? [];

        return $deps['requires_mssql_driver'] ?? false;
    }

    public function getPeclName(string $extension): string
    {
        $deps = $this->extensionDependencies[$extension] ?? [];

        return $deps['pecl_name'] ?? $extension;
    }

    public function getConfigureOptions(string $extension): string
    {
        $deps = $this->extensionDependencies[$extension] ?? [];

        return $deps['configure_options'] ?? '';
    }

    public function isBundled(string $extension): bool
    {
        return in_array($extension, $this->bundledExtensions, true);
    }

    public function isStandard(string $extension): bool
    {
        return in_array($extension, $this->standardExtensions, true);
    }

    public function getCustomInstallScript(string $extension): ?string
    {
        return match ($extension) {
            'oci8', 'pdo_oci' => $this->getOracleInstallScript(),
            'sqlsrv', 'pdo_sqlsrv' => $this->getMssqlInstallScript(),
            default => null,
        };
    }

    protected function getOracleInstallScript(): string
    {
        return <<<'BASH'
# Install Oracle Instant Client
# Note: libaio package name varies by Debian version (libaio1 or libaio1t64)
# Create symlink for libaio.so.1 if using libaio1t64 (Debian Trixie+)
RUN apt-get update && \
    (apt-get install -y libaio1 || apt-get install -y libaio1t64) && \
    apt-get clean && rm -rf /var/lib/apt/lists/* && \
    if [ -f /lib/x86_64-linux-gnu/libaio.so.1t64 ] && [ ! -f /lib/x86_64-linux-gnu/libaio.so.1 ]; then \
        ln -s /lib/x86_64-linux-gnu/libaio.so.1t64 /lib/x86_64-linux-gnu/libaio.so.1; \
    fi && \
    ldconfig

RUN mkdir -p /opt/oracle && \
    cd /opt/oracle && \
    wget -q https://download.oracle.com/otn_software/linux/instantclient/2340000/instantclient-basic-linux.x64-23.4.0.24.05.zip && \
    wget -q https://download.oracle.com/otn_software/linux/instantclient/2340000/instantclient-sdk-linux.x64-23.4.0.24.05.zip && \
    unzip -oq instantclient-basic-linux.x64-23.4.0.24.05.zip && \
    unzip -oq instantclient-sdk-linux.x64-23.4.0.24.05.zip && \
    rm -f *.zip && \
    echo /opt/oracle/instantclient_23_4 > /etc/ld.so.conf.d/oracle-instantclient.conf && \
    ldconfig

ENV LD_LIBRARY_PATH=/opt/oracle/instantclient_23_4
ENV ORACLE_HOME=/opt/oracle/instantclient_23_4

# Install OCI8 via PECL (required for PHP 8.4+)
# Note: PDO_OCI is no longer available in PHP 8.4+
# Use yajra/laravel-oci8 package for Laravel Oracle support
RUN echo "instantclient,/opt/oracle/instantclient_23_4" | pecl install oci8 && \
    docker-php-ext-enable oci8
BASH;
    }

    protected function getMssqlInstallScript(): string
    {
        return <<<'BASH'
# Install Microsoft ODBC Driver
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg && \
    curl -fsSL https://packages.microsoft.com/config/debian/12/prod.list | tee /etc/apt/sources.list.d/mssql-release.list && \
    apt-get update && \
    ACCEPT_EULA=Y apt-get install -y msodbcsql18 mssql-tools18 unixodbc-dev && \
    pecl install sqlsrv pdo_sqlsrv && \
    docker-php-ext-enable sqlsrv pdo_sqlsrv
BASH;
    }

    public function getAllSystemPackages(array $extensions): array
    {
        $packages = [];

        foreach ($extensions as $extension) {
            $deps = $this->getInstallationDependencies($extension);
            $packages = array_merge($packages, $deps);
        }

        return array_unique($packages);
    }

    public function categorizeExtensions(array $extensions): array
    {
        $result = [
            'bundled' => [],
            'standard' => [],
            'pecl' => [],
            'custom' => [],
        ];

        foreach ($extensions as $extension) {
            if ($this->isBundled($extension)) {
                $result['bundled'][] = $extension;
            } elseif ($this->requiresCustomInstall($extension)) {
                $result['custom'][] = $extension;
            } elseif ($this->getInstallMethod($extension) === 'pecl') {
                $result['pecl'][] = $extension;
            } else {
                $result['standard'][] = $extension;
            }
        }

        return $result;
    }
}
