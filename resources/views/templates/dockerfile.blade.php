#############################################
# Base Stage
#############################################
FROM {{ $base_image }} AS base

LABEL maintainer="{{ $config->appName }}"

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
USER root
RUN apt-get update && apt-get install -y \
@foreach($system_packages as $package)
    {{ $package }} \
@endforeach
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

@if(count($standard_extensions) > 0)
# Install standard PHP extensions
RUN docker-php-ext-install \
@foreach($standard_extensions as $ext)
    {{ $ext }} \
@endforeach
    && docker-php-ext-enable \
@foreach($standard_extensions as $ext)
    {{ $ext }}{{ !$loop->last ? ' \\' : '' }}
@endforeach

@endif
@if(count($pecl_extensions) > 0)
# Install PECL extensions
RUN pecl install \
@foreach($pecl_extensions as $ext)
    {{ $ext }} \
@endforeach
    && docker-php-ext-enable \
@foreach($pecl_extensions as $ext)
    {{ $ext }}{{ !$loop->last ? ' \\' : '' }}
@endforeach

@endif
@if($requires_oracle_client)
# Install Oracle Instant Client and OCI8
{!! $oracle_install_script !!}

@endif
@if($requires_mssql_driver)
# Install Microsoft SQL Server drivers
{!! $mssql_install_script !!}

@endif
# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

#############################################
# Development Stage
#############################################
FROM base AS development

ARG USER_ID=1000
ARG GROUP_ID=1000

USER root

# Create user with specified UID/GID
RUN groupmod -o -g ${GROUP_ID} www-data && \
    usermod -o -u ${USER_ID} -g www-data www-data

@if($node_version)
# Install Node.js for development
RUN curl -fsSL https://deb.nodesource.com/setup_{{ $node_version }}.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g npm@latest

@endif
USER www-data

#############################################
# CI Stage
#############################################
FROM base AS ci

USER root

# CI runs as root for flexibility
ENV COMPOSER_ALLOW_SUPERUSER=1

#############################################
# Production Stage
#############################################
FROM base AS production

USER root

# Copy application files
COPY --chown=www-data:www-data . /var/www/html

# Install production dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

@if($node_version)
# Build frontend assets
@if($package_manager === 'yarn')
RUN curl -fsSL https://deb.nodesource.com/setup_{{ $node_version }}.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g yarn && \
    yarn install --frozen-lockfile && \
    yarn build && \
    rm -rf node_modules
@elseif($package_manager === 'pnpm')
RUN curl -fsSL https://deb.nodesource.com/setup_{{ $node_version }}.x | bash - && \
    apt-get install -y nodejs && \
    npm install -g pnpm && \
    pnpm install --frozen-lockfile && \
    pnpm run build && \
    rm -rf node_modules
@elseif($package_manager === 'bun')
RUN curl -fsSL https://bun.sh/install | bash && \
    export BUN_INSTALL="$HOME/.bun" && \
    export PATH="$BUN_INSTALL/bin:$PATH" && \
    bun install --frozen-lockfile && \
    bun run build && \
    rm -rf node_modules
@else
RUN curl -fsSL https://deb.nodesource.com/setup_{{ $node_version }}.x | bash - && \
    apt-get install -y nodejs && \
    npm ci && \
    npm run build && \
    rm -rf node_modules
@endif

@endif
# Set correct permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data

# Expose port
EXPOSE 9000

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD php artisan --version || exit 1

CMD ["php-fpm"]
