<?php

declare(strict_types=1);

namespace Alive2Tinker\DockerBuilder\Services\Docker\Generators;

use Alive2Tinker\DockerBuilder\DTO\DockerConfigDTO;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\File;
use RuntimeException;

abstract class AbstractFileGenerator
{
    protected DockerConfigDTO $config;

    protected ViewFactory $viewFactory;

    public function __construct(DockerConfigDTO $config)
    {
        $this->config = $config;
        $this->viewFactory = app(ViewFactory::class);
    }

    /**
     * Get the template path for the Blade view.
     */
    abstract protected function getTemplatePath(): string;

    /**
     * Get the output file path relative to the project root.
     */
    abstract public function getOutputPath(): string;

    /**
     * Get the data to pass to the template.
     */
    abstract protected function getTemplateData(): array;

    /**
     * Generate the file and write it to disk.
     */
    public function generate(): string
    {
        $content = $this->renderTemplate();
        $outputPath = $this->getAbsoluteOutputPath();

        $this->ensureDirectoryExists(dirname($outputPath));
        $this->writeFile($outputPath, $content);

        return $outputPath;
    }

    /**
     * Render the template and return the content.
     */
    protected function renderTemplate(): string
    {
        $templatePath = $this->getTemplatePath();
        $data = $this->getTemplateData();

        if (! view()->exists($templatePath)) {
            throw new RuntimeException("Template not found: {$templatePath}");
        }

        return $this->viewFactory->make($templatePath, $data)->render();
    }

    /**
     * Get the absolute path for the output file.
     */
    protected function getAbsoluteOutputPath(): string
    {
        return base_path($this->getOutputPath());
    }

    /**
     * Ensure the directory exists.
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    /**
     * Write content to file.
     */
    protected function writeFile(string $path, string $content): void
    {
        File::put($path, $content);
    }

    /**
     * Check if the output file already exists.
     */
    public function outputExists(): bool
    {
        return File::exists($this->getAbsoluteOutputPath());
    }

    /**
     * Delete the output file if it exists.
     */
    public function deleteOutput(): bool
    {
        $path = $this->getAbsoluteOutputPath();

        if (File::exists($path)) {
            return File::delete($path);
        }

        return true;
    }
}
