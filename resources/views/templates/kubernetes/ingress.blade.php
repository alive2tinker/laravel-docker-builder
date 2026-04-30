apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: {{ $app_name }}
  namespace: {{ $namespace }}
  annotations:
    kubernetes.io/ingress.class: nginx
@if($is_letsencrypt)
    cert-manager.io/cluster-issuer: letsencrypt-prod
@endif
    nginx.ingress.kubernetes.io/proxy-body-size: "64m"
    nginx.ingress.kubernetes.io/proxy-read-timeout: "120"
    nginx.ingress.kubernetes.io/proxy-send-timeout: "120"
spec:
@if($ssl_enabled)
  tls:
    - hosts:
        - {{ $domain }}
      secretName: {{ $app_name }}-tls
@endif
  rules:
    - host: {{ $domain }}
      http:
        paths:
          - path: /
            pathType: Prefix
            backend:
              service:
                name: {{ $app_name }}
                port:
                  number: 80
---
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: {{ $app_name }}-storage
  namespace: {{ $namespace }}
spec:
  accessModes:
    - ReadWriteMany
  storageClassName: standard
  resources:
    requests:
      storage: 10Gi
---
@if($config->hasRedis())
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: redis-storage
  namespace: {{ $namespace }}
spec:
  accessModes:
    - ReadWriteOnce
  storageClassName: standard
  resources:
    requests:
      storage: 1Gi
---
@endif
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: {{ $app_name }}
  namespace: {{ $namespace }}
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: {{ $app_name }}
  minReplicas: 1
  maxReplicas: 10
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 80
