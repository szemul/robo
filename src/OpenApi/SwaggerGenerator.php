<?php
declare(strict_types=1);

namespace Szemul\Robo\OpenApi;

use Szemul\Robo\ApplicationConfig;

class SwaggerGenerator implements GeneratorInterface
{
    public function addErrorsToOpenApiJsonContent(array &$jsonContent, ApplicationConfig $config): void
    {
        $usedErrorCodes = [];

        foreach ($jsonContent['paths'] ?? [] as $path => $methods) {
            foreach ($methods as $method => $methodContent) {
                $errorCodes = array_merge(
                    $config->getErrorCodes($method),
                    $methodContent['x-errors'] ?? [],
                );

                foreach ($errorCodes as $errorCode) {
                    $usedErrorCodes[$errorCode] = 1;

                    // This status code is already documented, don't override
                    if (isset($methodContent['responses'][(string)$errorCode])) {
                        continue;
                    }

                    $methodContent['responses'][(string)$errorCode] = [
                        'description' => ErrorHelper::getApiErrorDocDescriptionByErrorCode($errorCode),
                        'schema'      => [
                            '$ref' => '#/definitions/Error' . $errorCode,
                        ],
                    ];
                }
                if (isset($methodContent['x-errors'])) {
                    unset($methodContent['x-errors']);
                }

                $jsonContent['paths'][$path][$method] = $methodContent;
            }
        }

        foreach ($usedErrorCodes as $errorCode => $value) {
            if (!isset($jsonContent['definitions']['Error' . $errorCode])) {
                $jsonContent['definitions']['Error' . $errorCode] = ErrorHelper::getApiDocErrorResponseDefinitionForCode(
                    $errorCode,
                );
            }
        }
    }
}
