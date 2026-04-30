apiVersion: v1
kind: Service
metadata:
  name: {{ $app_name }}
  namespace: {{ $namespace }}
  labels:
    app: {{ $app_name }}
spec:
  type: ClusterIP
  ports:
    - port: 80
      targetPort: 80
      protocol: TCP
      name: http
  selector:
    app: {{ $app_name }}
---
@if($has_redis)
apiVersion: v1
kind: Service
metadata:
  name: redis
  namespace: {{ $namespace }}
  labels:
    app: redis
spec:
  type: ClusterIP
  ports:
    - port: 6379
      targetPort: 6379
      protocol: TCP
      name: redis
  selector:
    app: redis
@endif
