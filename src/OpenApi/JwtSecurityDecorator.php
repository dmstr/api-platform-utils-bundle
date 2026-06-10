<?php
// file generated with AI assistance: Claude Code - 2026-03-17

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Adds Bearer JWT security scheme to the OpenAPI documentation.
 * This replaces the api_keys-based scheme with a proper http/bearer scheme,
 * so Swagger UI shows lock icons and a clean "Bearer token" input field.
 */
final class JwtSecurityDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated,
        private readonly string $description = 'JWT bearer token',
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        // Replace security scheme: use http/bearer instead of apiKey
        $schemas = $openApi->getComponents()->getSecuritySchemes() ?? new \ArrayObject();
        $schemas['JWT'] = new \ArrayObject([
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
            'description' => $this->description,
        ]);

        $openApi = $openApi->withComponents(
            $openApi->getComponents()->withSecuritySchemes($schemas)
        );

        // Add global security requirement — Swagger UI shows lock icon on all operations
        $openApi = $openApi->withSecurity([['JWT' => []]]);

        return $openApi;
    }
}
