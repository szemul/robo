<?php
declare(strict_types=1);

namespace Szemul\Robo\OpenApi;

class ErrorHelper
{
    public static function getApiErrorDocDescriptionByErrorCode(int $errorCode): string
    {
        return match ($errorCode) {
            400     => 'Bad request, failed to parse the request',
            401     => 'Authorization required for calling this endpoint',
            402     => 'Billing error, payment required',
            403     => 'The authenticated user has no permission for this operation',
            404     => 'Entity not found',
            422     => 'Unprocessable entity, the request contained invalid values. See params for details',
            default => 'Error',
        };
    }

    /** @return array<string,array<string,array<string,string>>|string> */
    public static function getApiDocErrorResponseDefinitionForCode(int $errorCode): array
    {
        $errorDefinition = [
            'properties' => [
                'errorCode'    => [
                    'type'        => 'string',
                    'description' => 'The code of the error',
                ],
                'errorMessage' => [
                    'type'        => 'string',
                    'description' => 'Description of the error',
                ],
            ],
            'type' => 'object',
        ];

        if (422 == $errorCode) {
            $errorDefinition['properties']['params'] = [
                'type'        => 'object',
                'description' => 'List of the invalid params where the property is the parameter name and the value is the describing the issue',
            ];
        }

        return $errorDefinition;
    }
}
