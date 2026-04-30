services:
  #############################################
  # Traefik Reverse Proxy
  #############################################
  traefik:
    image: traefik:v3.0
    ports:
      - target: 80
        published: 80
        protocol: tcp
        mode: host
      - target: 443
        published: 443
        protocol: tcp
        mode: host
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - traefik-certificates:/certificates
    configs:
      - source: traefik-config
        target: /etc/traefik/traefik.yml
    networks:
      - web-public
    deploy:
      placement:
        constraints:
          - node.role==manager
      update_config:
        parallelism: 1
        delay: 5s
        order: stop-first

  #############################################
  # Application Service
  #############################################
  app:
    image: ${{ '{' }}DOCKER_REGISTRY{{ '}' }}/{{ $app_name }}:${{ '{' }}IMAGE_TAG:-latest{{ '}' }}
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - PHP_OPCACHE_ENABLE=1
      - AUTORUN_ENABLED=true
@if($ssl && $ssl->isEnabled())
      - SSL_MODE=full
@endif
    volumes:
      - storage-private:/var/www/html/storage/app/private
      - storage-public:/var/www/html/storage/app/public
      - storage-logs:/var/www/html/storage/logs
    networks:
      - web-public
      - app-internal
    deploy:
      replicas: ${{ '{' }}REPLICAS:-1{{ '}' }}
      update_config:
        parallelism: 1
        delay: 10s
        order: start-first
        failure_action: rollback
      rollback_config:
        parallelism: 0
        order: stop-first
      restart_policy:
        condition: any
        delay: 5s
        max_attempts: 3
        window: 120s
      labels:
        - "traefik.enable=true"
        - "traefik.http.routers.{{ $app_name }}.rule=Host(`${{ '{' }}APP_DOMAIN{{ '}' }}`)"
        - "traefik.http.routers.{{ $app_name }}.entrypoints=websecure"
        - "traefik.http.routers.{{ $app_name }}.tls=true"
@if($ssl && $ssl->isLetsEncrypt())
        - "traefik.http.routers.{{ $app_name }}.tls.certresolver=letsencrypt"
@endif
        - "traefik.http.services.{{ $app_name }}.loadbalancer.server.port=80"
        - "traefik.http.services.{{ $app_name }}.loadbalancer.healthcheck.path=/up"
        - "traefik.http.services.{{ $app_name }}.loadbalancer.healthcheck.interval=30s"
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/up"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 60s

@if($has_worker)
  #############################################
  # Queue Worker Service
  #############################################
  worker:
    image: ${{ '{' }}DOCKER_REGISTRY{{ '}' }}/{{ $app_name }}:${{ '{' }}IMAGE_TAG:-latest{{ '}' }}
    command: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    volumes:
      - storage-private:/var/www/html/storage/app/private
      - storage-logs:/var/www/html/storage/logs
    networks:
      - app-internal
    deploy:
      replicas: 1
      restart_policy:
        condition: any
        delay: 5s

@endif
@if($has_scheduler)
  #############################################
  # Scheduler Service
  #############################################
  scheduler:
    image: ${{ '{' }}DOCKER_REGISTRY{{ '}' }}/{{ $app_name }}:${{ '{' }}IMAGE_TAG:-latest{{ '}' }}
    command: php artisan schedule:work
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    volumes:
      - storage-private:/var/www/html/storage/app/private
      - storage-logs:/var/www/html/storage/logs
    networks:
      - app-internal
    deploy:
      replicas: 1
      placement:
        constraints:
          - node.role==manager
      restart_policy:
        condition: any
        delay: 5s

@endif
@if($has_redis)
  #############################################
  # Redis Service
  #############################################
  redis:
    image: redis:alpine
    volumes:
      - redis-data:/data
    networks:
      - app-internal
    deploy:
      replicas: 1
      restart_policy:
        condition: any

@endif
#############################################
# Configs
#############################################
configs:
  traefik-config:
    file: ./.docker/traefik/traefik.yml

#############################################
# Networks
#############################################
networks:
  web-public:
    driver: overlay
    attachable: true
  app-internal:
    driver: overlay
    internal: true

#############################################
# Volumes
#############################################
volumes:
  traefik-certificates:
  storage-private:
  storage-public:
  storage-logs:
@if($has_redis)
  redis-data:
@endif
