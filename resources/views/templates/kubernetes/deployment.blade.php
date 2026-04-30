apiVersion: apps/v1
kind: Deployment
metadata:
  name: {{ $app_name }}
  namespace: {{ $namespace }}
  labels:
    app: {{ $app_name }}
spec:
  replicas: {{ $replicas }}
  selector:
    matchLabels:
      app: {{ $app_name }}
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxSurge: 25%
      maxUnavailable: 25%
  template:
    metadata:
      labels:
        app: {{ $app_name }}
    spec:
      containers:
        - name: {{ $app_name }}
          image: ${DOCKER_REGISTRY}/{{ $app_name }}:${IMAGE_TAG}
          ports:
            - containerPort: 80
              name: http
          envFrom:
            - configMapRef:
                name: {{ $app_name }}-config
            - secretRef:
                name: {{ $app_name }}-secrets
          resources:
            requests:
              cpu: "100m"
              memory: "256Mi"
            limits:
              cpu: "500m"
              memory: "512Mi"
          livenessProbe:
            httpGet:
              path: /up
              port: 80
            initialDelaySeconds: 30
            periodSeconds: 10
            timeoutSeconds: 5
            failureThreshold: 3
          readinessProbe:
            httpGet:
              path: /up
              port: 80
            initialDelaySeconds: 5
            periodSeconds: 5
            timeoutSeconds: 3
          volumeMounts:
            - name: storage
              mountPath: /var/www/html/storage/app
      volumes:
        - name: storage
          persistentVolumeClaim:
            claimName: {{ $app_name }}-storage
---
@if($has_worker)
apiVersion: apps/v1
kind: Deployment
metadata:
  name: {{ $app_name }}-worker
  namespace: {{ $namespace }}
  labels:
    app: {{ $app_name }}-worker
spec:
  replicas: 1
  selector:
    matchLabels:
      app: {{ $app_name }}-worker
  template:
    metadata:
      labels:
        app: {{ $app_name }}-worker
    spec:
      containers:
        - name: worker
          image: ${DOCKER_REGISTRY}/{{ $app_name }}:${IMAGE_TAG}
          command: ["php", "artisan", "queue:work", "--sleep=3", "--tries=3", "--max-time=3600"]
          envFrom:
            - configMapRef:
                name: {{ $app_name }}-config
            - secretRef:
                name: {{ $app_name }}-secrets
          resources:
            requests:
              cpu: "50m"
              memory: "128Mi"
            limits:
              cpu: "200m"
              memory: "256Mi"
          volumeMounts:
            - name: storage
              mountPath: /var/www/html/storage/app
      volumes:
        - name: storage
          persistentVolumeClaim:
            claimName: {{ $app_name }}-storage
---
@endif
@if($has_scheduler)
apiVersion: apps/v1
kind: Deployment
metadata:
  name: {{ $app_name }}-scheduler
  namespace: {{ $namespace }}
  labels:
    app: {{ $app_name }}-scheduler
spec:
  replicas: 1
  selector:
    matchLabels:
      app: {{ $app_name }}-scheduler
  template:
    metadata:
      labels:
        app: {{ $app_name }}-scheduler
    spec:
      containers:
        - name: scheduler
          image: ${DOCKER_REGISTRY}/{{ $app_name }}:${IMAGE_TAG}
          command: ["php", "artisan", "schedule:work"]
          envFrom:
            - configMapRef:
                name: {{ $app_name }}-config
            - secretRef:
                name: {{ $app_name }}-secrets
          resources:
            requests:
              cpu: "50m"
              memory: "128Mi"
            limits:
              cpu: "100m"
              memory: "256Mi"
---
@endif
@if($has_redis)
apiVersion: apps/v1
kind: Deployment
metadata:
  name: redis
  namespace: {{ $namespace }}
  labels:
    app: redis
spec:
  replicas: 1
  selector:
    matchLabels:
      app: redis
  template:
    metadata:
      labels:
        app: redis
    spec:
      containers:
        - name: redis
          image: redis:alpine
          ports:
            - containerPort: 6379
          resources:
            requests:
              cpu: "50m"
              memory: "64Mi"
            limits:
              cpu: "100m"
              memory: "128Mi"
          volumeMounts:
            - name: redis-data
              mountPath: /data
      volumes:
        - name: redis-data
          persistentVolumeClaim:
            claimName: redis-storage
@endif
