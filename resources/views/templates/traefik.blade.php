serversTransport:
  insecureSkipVerify: true

providers:
  swarm:
    endpoint: "unix:///var/run/docker.sock"
    exposedByDefault: false

entryPoints:
  web:
    address: ":80"
    http:
      redirections:
        entrypoint:
          to: websecure
          scheme: https

  websecure:
    address: ":443"

accessLog: {}

log:
  level: ERROR

api:
  dashboard: true
  insecure: true

@if($is_letsencrypt)
certificatesResolvers:
  letsencrypt:
    acme:
      email: "{{ $letsencrypt_email }}"
      storage: "/certificates/acme.json"
      httpChallenge:
        entryPoint: web
@endif
