# Git
.git
.gitignore
.gitattributes

# CI/CD
.github
.gitlab-ci.yml
Jenkinsfile

# Docker
Dockerfile*
docker-compose*
.docker
.dockerignore

# IDE
.idea
.vscode
*.swp
*.swo
.DS_Store

# Dependencies
/vendor
/node_modules

# Testing
/tests
phpunit.xml
.phpunit.result.cache
.phpunit.cache
/coverage

# Build artifacts
/public/hot
/public/storage
/storage/*.key
/storage/logs/*
/storage/framework/cache/*
/storage/framework/sessions/*
/storage/framework/views/*

# Environment files
.env
.env.*
!.env.example
!.env.docker

# Logs
*.log
npm-debug.log*
yarn-debug.log*
yarn-error.log*

# Documentation
*.md
!README.md
/docs

# Misc
Homestead.json
Homestead.yaml
auth.json
.editorconfig
.styleci.yml

@if($has_kubernetes)
# Kubernetes specific
/k8s/*.yaml.bak
@endif

# Laravel specific
bootstrap/cache/*.php
storage/app/public/*
