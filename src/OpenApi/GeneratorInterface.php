<?php
declare(strict_types=1);

namespace Szemul\Robo\OpenApi;

use Szemul\Robo\ApplicationConfig;

interface GeneratorInterface
{
    /** @param array<string,mixed[]> $jsonContent */
    public function addErrorsToOpenApiJsonContent(array &$jsonContent, ApplicationConfig $config): void;
}
