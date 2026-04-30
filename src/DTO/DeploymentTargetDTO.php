<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\DTO;

readonly class DeploymentTargetDTO
{
    public function __construct(
        public string $type,
        public array $options = [],
    ) {}

    public function isCompose(): bool
    {
        return $this->type === 'compose';
    }

    public function isSwarm(): bool
    {
        return $this->type === 'swarm';
    }

    public function isKubernetes(): bool
    {
        return $this->type === 'kubernetes';
    }

    public function getReplicas(): int
    {
        return $this->options['replicas'] ?? 1;
    }

    public function getNamespace(): string
    {
        return $this->options['namespace'] ?? 'default';
    }

    public function getRequiredFiles(): array
    {
        return match ($this->type) {
            'compose' => [
                'Dockerfile',
                'docker-compose.yml',
                '.dockerignore',
            ],
            'swarm' => [
                'Dockerfile',
                'docker-compose.yml',
                'docker-compose.swarm.yml',
                '.dockerignore',
            ],
            'kubernetes' => [
                'Dockerfile',
                '.dockerignore',
                'k8s/deployment.yaml',
                'k8s/service.yaml',
                'k8s/configmap.yaml',
                'k8s/ingress.yaml',
            ],
            default => [],
        };
    }

    public static function compose(): self
    {
        return new self(type: 'compose');
    }

    public static function swarm(int $replicas = 1): self
    {
        return new self(
            type: 'swarm',
            options: ['replicas' => $replicas],
        );
    }

    public static function kubernetes(string $namespace = 'default', int $replicas = 1): self
    {
        return new self(
            type: 'kubernetes',
            options: [
                'namespace' => $namespace,
                'replicas' => $replicas,
            ],
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            options: $data['options'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'options' => $this->options,
            'required_files' => $this->getRequiredFiles(),
        ];
    }
}
