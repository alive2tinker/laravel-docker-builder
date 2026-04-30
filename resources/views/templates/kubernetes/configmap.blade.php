apiVersion: v1
kind: ConfigMap
metadata:
  name: {{ $app_name }}-config
  namespace: {{ $namespace }}
data:
  APP_ENV: "production"
  APP_DEBUG: "false"
  LOG_CHANNEL: "stderr"
  LOG_LEVEL: "error"
  DB_CONNECTION: "{{ $database_type === 'postgresql' ? 'pgsql' : ($database_type === 'mssql' ? 'sqlsrv' : $database_type) }}"
@if($has_redis)
  CACHE_DRIVER: "redis"
  QUEUE_CONNECTION: "redis"
  SESSION_DRIVER: "redis"
  REDIS_HOST: "redis"
  REDIS_PORT: "6379"
@else
  CACHE_DRIVER: "file"
  QUEUE_CONNECTION: "sync"
  SESSION_DRIVER: "file"
@endif
  FILESYSTEM_DISK: "local"
  BROADCAST_DRIVER: "log"
---
apiVersion: v1
kind: Secret
metadata:
  name: {{ $app_name }}-secrets
  namespace: {{ $namespace }}
type: Opaque
stringData:
  APP_KEY: "${APP_KEY}"
  DB_HOST: "${DB_HOST}"
  DB_PORT: "${DB_PORT}"
  DB_DATABASE: "${DB_DATABASE}"
  DB_USERNAME: "${DB_USERNAME}"
  DB_PASSWORD: "${DB_PASSWORD}"
@if($has_redis)
  REDIS_PASSWORD: "${REDIS_PASSWORD}"
@endif
