<?php
declare(strict_types=1);

namespace Szemul\Robo;

class ApplicationConfig
{
    /** @var string[] */
    private array $codePaths = [];
    private string $name;
    private string $entryPointPath;
    private string  $targetJsonPath;
    /** @var array<string,int[]> */
    private array $errorCodesByMethod = [];

    /** @param string[] $codePaths */
    public function __construct(string $name, array $codePaths, string $entryPointPath, string $targetJsonPath)
    {
        $this->name           = $name;
        $this->codePaths      = $codePaths;
        $this->entryPointPath = $entryPointPath;
        $this->targetJsonPath = $targetJsonPath;
    }

    public function setErrorCodes(string $method, int ...$errorCodes): self
    {
        $this->errorCodesByMethod[$method] = $errorCodes;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return string[] */
    public function getCodePaths(): array
    {
        return $this->codePaths;
    }

    public function getEntryPointPath(): string
    {
        return $this->entryPointPath;
    }

    public function getTargetJsonPath(): string
    {
        return $this->targetJsonPath;
    }

    /** @return int[] */
    public function getErrorCodes(string $method): array
    {
        return $this->errorCodesByMethod[$method] ?? [];
    }
}
