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
 *
 * When an OIDC discovery URL is configured, an additional openIdConnect
 * scheme is emitted so spec consumers (Swagger UI, generated clients) can
 * bootstrap the auth flow from the spec alone. Note this is documentation
 * sugar — runtime clients should prefer RFC 9728 protected-resource metadata
 * for discovery, which stays available even where API docs are disabled.
 */
final class JwtSecurityDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated,
        private readonly string $description = 'JWT bearer token',
        private readonly ?string $openIdConnectUrl = null,
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

        // Security requirements are alternatives (OR semantics): a bearer
        // token satisfies either scheme, OIDC merely documents where to get it.
        $security = [['JWT' => []]];

        if (null !== $this->openIdConnectUrl && '' !== $this->openIdConnectUrl) {
            $schemas['OIDC'] = new \ArrayObject([
                'type' => 'openIdConnect',
                'openIdConnectUrl' => $this->openIdConnectUrl,
                'description' => 'OpenID Connect discovery — yields the same JWT the bearer scheme expects.',
            ]);
            $security[] = ['OIDC' => []];
        }

        $openApi = $openApi->withComponents(
            $openApi->getComponents()->withSecuritySchemes($schemas)
        );

        // Add global security requirement — Swagger UI shows lock icon on all operations
        $openApi = $openApi->withSecurity($security);

        return $openApi;
    }
}
