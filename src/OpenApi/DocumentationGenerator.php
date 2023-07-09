<?php
declare(strict_types=1);

namespace Szemul\Robo\OpenApi;

use Robo\Collection\CollectionBuilder;
use Robo\Task\Base\Exec;
use Szemul\Robo\ApplicationConfig;

class DocumentationGenerator
{
    private GeneratorInterface $generator;

    public function __construct(GeneratorInterface $generator)
    {
        $this->generator = $generator;
    }

    /**
     * @param CollectionBuilder[]|Exec[] $roboTasks
     */
    public function generate(array $roboTasks, ApplicationConfig ...$configs): void
    {
        foreach ($configs as $index => $config) {
            $currentTask    = $roboTasks[$index];
            $targetJsonPath = $config->getTargetJsonPath();

            foreach ($config->getCodePaths() as $path) {
                $currentTask->arg($path);
            }
            $currentTask->arg($config->getEntryPointPath());

            $currentTask->option('--output')
                ->arg($targetJsonPath)
                ->run();

            $jsonContent = json_decode(file_get_contents($targetJsonPath), true);

            $this->generator->addErrorsToOpenApiJsonContent($jsonContent, $config);

            file_put_contents($targetJsonPath, json_encode($jsonContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $currentTask = null;
        }
    }
}
