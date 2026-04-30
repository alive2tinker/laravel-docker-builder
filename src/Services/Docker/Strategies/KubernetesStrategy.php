<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Strategies;

use Alive2Tinker\DockerBuilder\DTO\DockerConfigDTO;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\DockerfileGenerator;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\DockerIgnoreGenerator;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\Kubernetes\ConfigMapGenerator;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\Kubernetes\DeploymentGenerator;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\Kubernetes\IngressGenerator;
use Alive2Tinker\DockerBuilder\Services\Docker\Generators\Kubernetes\ServiceGenerator;

class KubernetesStrategy implements DeploymentStrategyInterface
{
    public function getRequiredFiles(): array
    {
        return [
            'Dockerfile',
            '.dockerignore',
            'k8s/namespace.yaml',
            'k8s/deployment.yaml',
            'k8s/service.yaml',
            'k8s/configmap.yaml',
            'k8s/secret.yaml',
            'k8s/ingress.yaml',
            'k8s/pvc.yaml',
            'k8s/hpa.yaml',
        ];
    }

    public function getGenerators(): array
    {
        return [
            DockerfileGenerator::class,
            DockerIgnoreGenerator::class,
            DeploymentGenerator::class,
            ServiceGenerator::class,
            ConfigMapGenerator::class,
            IngressGenerator::class,
        ];
    }

    public function transformConfig(DockerConfigDTO $config): array
    {
        $target = $config->getDeploymentTarget('kubernetes');
        $namespace = $target?->getNamespace() ?? 'default';
        $replicas = $target?->getReplicas() ?? 1;

        return [
            'namespace' => $namespace,
            'app_name' => $config->appName,
            'replicas' => $replicas,
            'deployment' => $this->buildDeployment($config, $replicas),
            'service' => $this->buildService($config),
            'configmap' => $this->buildConfigMap($config),
            'ingress' => $this->buildIngress($config),
            'pvc' => $this->buildPersistentVolumeClaims($config),
            'hpa' => $this->buildHorizontalPodAutoscaler($config),
        ];
    }

    protected function buildDeployment(DockerConfigDTO $config, int $replicas): array
    {
        return [
            'apiVersion' => 'apps/v1',
            'kind' => 'Deployment',
            'metadata' => [
                'name' => $config->appName,
                'labels' => [
                    'app' => $config->appName,
                    'version' => 'v1',
                ],
            ],
            'spec' => [
                'replicas' => $replicas,
                'selector' => [
                    'matchLabels' => ['app' => $config->appName],
                ],
                'strategy' => [
                    'type' => 'RollingUpdate',
                    'rollingUpdate' => [
                        'maxSurge' => '25%',
                        'maxUnavailable' => '25%',
                    ],
                ],
                'template' => [
                    'metadata' => [
                        'labels' => ['app' => $config->appName],
                    ],
                    'spec' => [
                        'containers' => [
                            [
                                'name' => $config->appName,
                                'image' => '${DOCKER_REGISTRY}/'.$config->appName.':${IMAGE_TAG}',
                                'ports' => [['containerPort' => 80, 'name' => 'http']],
                                'envFrom' => [
                                    ['configMapRef' => ['name' => $config->appName.'-config']],
                                    ['secretRef' => ['name' => $config->appName.'-secrets']],
                                ],
                                'resources' => [
                                    'requests' => ['cpu' => '100m', 'memory' => '256Mi'],
                                    'limits' => ['cpu' => '500m', 'memory' => '512Mi'],
                                ],
                                'livenessProbe' => [
                                    'httpGet' => ['path' => '/up', 'port' => 80],
                                    'initialDelaySeconds' => 30,
                                    'periodSeconds' => 10,
                                    'timeoutSeconds' => 5,
                                ],
                                'readinessProbe' => [
                                    'httpGet' => ['path' => '/up', 'port' => 80],
                                    'initialDelaySeconds' => 5,
                                    'periodSeconds' => 5,
                                ],
                                'volumeMounts' => [
                                    ['name' => 'storage', 'mountPath' => '/var/www/html/storage/app'],
                                ],
                            ],
                        ],
                        'volumes' => [
                            [
                                'name' => 'storage',
                                'persistentVolumeClaim' => ['claimName' => $config->appName.'-storage'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function buildService(DockerConfigDTO $config): array
    {
        return [
            'apiVersion' => 'v1',
            'kind' => 'Service',
            'metadata' => [
                'name' => $config->appName,
                'labels' => ['app' => $config->appName],
            ],
            'spec' => [
                'type' => 'ClusterIP',
                'ports' => [
                    ['port' => 80, 'targetPort' => 80, 'protocol' => 'TCP', 'name' => 'http'],
                ],
                'selector' => ['app' => $config->appName],
            ],
        ];
    }

    protected function buildConfigMap(DockerConfigDTO $config): array
    {
        return [
            'apiVersion' => 'v1',
            'kind' => 'ConfigMap',
            'metadata' => [
                'name' => $config->appName.'-config',
            ],
            'data' => [
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'LOG_CHANNEL' => 'stderr',
                'CACHE_DRIVER' => $config->hasRedis() ? 'redis' : 'file',
                'QUEUE_CONNECTION' => $config->hasRedis() ? 'redis' : 'sync',
                'SESSION_DRIVER' => $config->hasRedis() ? 'redis' : 'file',
            ],
        ];
    }

    protected function buildIngress(DockerConfigDTO $config): array
    {
        $domain = $config->ssl?->domain ?? '${APP_DOMAIN}';
        $annotations = [
            'kubernetes.io/ingress.class' => 'nginx',
        ];

        if ($config->ssl !== null && $config->ssl->isLetsEncrypt()) {
            $annotations['cert-manager.io/cluster-issuer'] = 'letsencrypt-prod';
        }

        $spec = [
            'rules' => [
                [
                    'host' => $domain,
                    'http' => [
                        'paths' => [
                            [
                                'path' => '/',
                                'pathType' => 'Prefix',
                                'backend' => [
                                    'service' => [
                                        'name' => $config->appName,
                                        'port' => ['number' => 80],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($config->ssl !== null && $config->ssl->isEnabled()) {
            $spec['tls'] = [
                [
                    'hosts' => [$domain],
                    'secretName' => $config->appName.'-tls',
                ],
            ];
        }

        return [
            'apiVersion' => 'networking.k8s.io/v1',
            'kind' => 'Ingress',
            'metadata' => [
                'name' => $config->appName,
                'annotations' => $annotations,
            ],
            'spec' => $spec,
        ];
    }

    protected function buildPersistentVolumeClaims(DockerConfigDTO $config): array
    {
        return [
            'apiVersion' => 'v1',
            'kind' => 'PersistentVolumeClaim',
            'metadata' => [
                'name' => $config->appName.'-storage',
            ],
            'spec' => [
                'accessModes' => ['ReadWriteMany'],
                'storageClassName' => 'standard',
                'resources' => [
                    'requests' => ['storage' => '10Gi'],
                ],
            ],
        ];
    }

    protected function buildHorizontalPodAutoscaler(DockerConfigDTO $config): array
    {
        return [
            'apiVersion' => 'autoscaling/v2',
            'kind' => 'HorizontalPodAutoscaler',
            'metadata' => [
                'name' => $config->appName,
            ],
            'spec' => [
                'scaleTargetRef' => [
                    'apiVersion' => 'apps/v1',
                    'kind' => 'Deployment',
                    'name' => $config->appName,
                ],
                'minReplicas' => 1,
                'maxReplicas' => 10,
                'metrics' => [
                    [
                        'type' => 'Resource',
                        'resource' => [
                            'name' => 'cpu',
                            'target' => [
                                'type' => 'Utilization',
                                'averageUtilization' => 80,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getType(): string
    {
        return 'kubernetes';
    }

    public function getOutputDirectory(): string
    {
        return 'k8s';
    }
}
