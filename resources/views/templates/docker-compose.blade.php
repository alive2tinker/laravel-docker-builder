services:
  #############################################
  # Application Service
  #############################################
  app:
    build:
      context: .
      dockerfile: Dockerfile
      target: production
    container_name: {{ $app_name }}-app
    restart: unless-stopped
@if($has_database || $has_redis)
    depends_on:
@if($has_database)
      - {{ $database->type }}
@endif
@if($has_redis)
      - redis
@endif
@endif
    environment:
      - APP_KEY=${{ '{' }}APP_KEY{{ '}' }}
      - APP_ENV=${{ '{' }}APP_ENV:-production{{ '}' }}
      - APP_DEBUG=${{ '{' }}APP_DEBUG:-false{{ '}' }}
      - APP_URL=${{ '{' }}APP_URL{{ '}' }}
      - DB_CONNECTION=${{ '{' }}DB_CONNECTION{{ '}' }}
      - DB_HOST=${{ '{' }}DB_HOST:-{{ $has_database ? $database->type : '127.0.0.1' }}{{ '}' }}
      - DB_PORT=${{ '{' }}DB_PORT:-{{ $has_database ? $database->getDefaultPort() : '3306' }}{{ '}' }}
      - DB_DATABASE=${{ '{' }}DB_DATABASE{{ '}' }}
      - DB_USERNAME=${{ '{' }}DB_USERNAME{{ '}' }}
      - DB_PASSWORD=${{ '{' }}DB_PASSWORD{{ '}' }}
@if($has_redis)
      - REDIS_HOST=redis
      - CACHE_DRIVER=redis
      - QUEUE_CONNECTION=redis
      - SESSION_DRIVER=redis
@endif
    volumes:
      - .:/var/www/html
      - ./.docker/php/php.ini:/usr/local/etc/php/conf.d/custom.ini:ro
    networks:
      - {{ $app_name }}-network

@if($requires_nginx)
  #############################################
  # Nginx Service
  #############################################
  nginx:
    image: nginx:alpine
    container_name: {{ $app_name }}-nginx
    restart: unless-stopped
    ports:
      - "{{ $web_port }}:80"
@if($ssl && $ssl->isEnabled())
      - "{{ $web_ssl_port }}:443"
@endif
    volumes:
      - .:/var/www/html:ro
      - ./.docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro
@if($ssl && $ssl->isLetsEncrypt())
      - ./certbot/conf:/etc/letsencrypt:ro
      - ./certbot/www:/var/www/certbot:ro
@elseif($ssl && $ssl->isCustom())
      - {{ $ssl->certPath }}:/etc/ssl/certs/ssl.crt:ro
      - {{ $ssl->keyPath }}:/etc/ssl/private/ssl.key:ro
@elseif($ssl && $ssl->isSelfSigned())
      - ./.docker/ssl:/etc/ssl/custom:ro
@endif
    depends_on:
      - app
    networks:
      - {{ $app_name }}-network

@endif
@if($has_database)
  #############################################
  # Database Service
  #############################################
  {{ $database->type }}:
    image: {{ $database->getDockerImage() }}
    container_name: {{ $app_name }}-{{ $database->type }}
    restart: unless-stopped
    ports:
      - "{{ $ports['database'] ?? $database->getDefaultPort() }}:{{ $database->getDefaultPort() }}"
    environment:
@foreach($database->getDefaultEnvironment() as $key => $value)
      - {{ $key }}={{ $value }}
@endforeach
    volumes:
      - {{ $database->type }}-data:/var/lib/{{ $database->type === 'postgresql' || $database->type === 'pgsql' ? 'postgresql/data' : ($database->type === 'mongodb' ? 'mongo' : 'mysql') }}
    networks:
      - {{ $app_name }}-network

@endif
@if($has_redis)
  #############################################
  # Redis Service
  #############################################
  redis:
    image: redis:alpine
    container_name: {{ $app_name }}-redis
    restart: unless-stopped
    ports:
      - "{{ $ports['redis'] ?? 6379 }}:6379"
    volumes:
      - redis-data:/data
    networks:
      - {{ $app_name }}-network
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 3

@endif
@if($has_worker)
  #############################################
  # Queue Worker Service
  #############################################
  worker:
    build:
      context: .
      dockerfile: Dockerfile
      target: production
    container_name: {{ $app_name }}-worker
    restart: unless-stopped
    command: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
    depends_on:
      - app
@if($has_redis)
      - redis
@endif
    environment:
      - APP_KEY=${{ '{' }}APP_KEY{{ '}' }}
      - APP_ENV=${{ '{' }}APP_ENV:-production{{ '}' }}
@if($has_redis)
      - REDIS_HOST=redis
      - QUEUE_CONNECTION=redis
@endif
    volumes:
      - .:/var/www/html
    networks:
      - {{ $app_name }}-network

@endif
@if($has_scheduler)
  #############################################
  # Scheduler Service
  #############################################
  scheduler:
    build:
      context: .
      dockerfile: Dockerfile
      target: production
    container_name: {{ $app_name }}-scheduler
    restart: unless-stopped
    command: php artisan schedule:work
    depends_on:
      - app
    environment:
      - APP_KEY=${{ '{' }}APP_KEY{{ '}' }}
      - APP_ENV=${{ '{' }}APP_ENV:-production{{ '}' }}
    volumes:
      - .:/var/www/html
    networks:
      - {{ $app_name }}-network

@endif
@if($has_ssr)
  #############################################
  # Inertia SSR Service
  #############################################
  ssr:
    build:
      context: .
      dockerfile: Dockerfile
      target: production
    container_name: {{ $app_name }}-ssr
    restart: unless-stopped
    command: php artisan inertia:start-ssr
    depends_on:
      - app
    environment:
      - APP_KEY=${{ '{' }}APP_KEY{{ '}' }}
      - APP_ENV=${{ '{' }}APP_ENV:-production{{ '}' }}
      - APP_URL=${{ '{' }}APP_URL{{ '}' }}
      - NODE_TLS_REJECT_UNAUTHORIZED=0
    volumes:
      - .:/var/www/html
    networks:
      - {{ $app_name }}-network

@endif
@if($ssl && $ssl->isLetsEncrypt())
  #############################################
  # Certbot Service (Let's Encrypt)
  #############################################
  certbot:
    image: certbot/certbot
    container_name: {{ $app_name }}-certbot
    volumes:
      - ./certbot/conf:/etc/letsencrypt
      - ./certbot/www:/var/www/certbot
    entrypoint: "/bin/sh -c 'trap exit TERM; while :; do certbot renew; sleep 12h & wait $${!}; done;'"

@endif
#############################################
# Networks
#############################################
networks:
  {{ $app_name }}-network:
    driver: bridge

#############################################
# Volumes
#############################################
volumes:
@if($has_database)
  {{ $database->type }}-data:
    driver: local
@endif
@if($has_redis)
  redis-data:
    driver: local
@endif
