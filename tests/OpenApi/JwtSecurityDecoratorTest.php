<?php
// file generated with AI assistance: Claude Code - 2026-07-02 19:00:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\OpenApi;
use Dmstr\ApiPlatformUtils\OpenApi\JwtSecurityDecorator;
use PHPUnit\Framework\TestCase;

final class JwtSecurityDecoratorTest extends TestCase
{
    private const string OIDC_URL = 'http://keycloak.test/realms/acme/.well-known/openid-configuration';

    public function testAddsBearerJwtSchemeAndGlobalSecurity(): void
    {
        $openApi = (new JwtSecurityDecorator($this->innerFactory(), 'my token hint'))();

        $schemes = $openApi->getComponents()->getSecuritySchemes();
        self::assertArrayHasKey('JWT', (array) $schemes);
        self::assertSame('http', $schemes['JWT']['type']);
        self::assertSame('bearer', $schemes['JWT']['scheme']);
        self::assertSame('my token hint', $schemes['JWT']['description']);
        self::assertSame([['JWT' => []]], $openApi->getSecurity());
    }

    public function testOmitsOidcSchemeWithoutDiscoveryUrl(): void
    {
        $openApi = (new JwtSecurityDecorator($this->innerFactory()))();

        self::assertArrayNotHasKey('OIDC', (array) $openApi->getComponents()->getSecuritySchemes());
    }

    public function testAddsOidcSchemeWithDiscoveryUrl(): void
    {
        $openApi = (new JwtSecurityDecorator($this->innerFactory(), 'jwt', self::OIDC_URL))();

        $schemes = $openApi->getComponents()->getSecuritySchemes();
        self::assertArrayHasKey('OIDC', (array) $schemes);
        self::assertSame('openIdConnect', $schemes['OIDC']['type']);
        self::assertSame(self::OIDC_URL, $schemes['OIDC']['openIdConnectUrl']);
        self::assertSame([['JWT' => []], ['OIDC' => []]], $openApi->getSecurity());
    }

    private function innerFactory(): OpenApiFactoryInterface
    {
        $openApi = new OpenApi(new Info('Test API', '1.0.0'), [], new Paths(), new Components());

        $factory = $this->createStub(OpenApiFactoryInterface::class);
        $factory->method('__invoke')->willReturn($openApi);

        return $factory;
    }
}
