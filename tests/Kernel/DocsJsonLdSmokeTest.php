<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:25:10 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Kernel;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Boots API Platform with this bundle and requests the real Hydra
 * documentation: guards the kernel test setup itself.
 */
final class DocsJsonLdSmokeTest extends KernelTestCase
{
    use HydraDocumentationTrait;

    public function testDocsJsonLdIsServedWithFixtureClass(): void
    {
        $response = self::requestHydraDocumentation();

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringStartsWith('application/ld+json', (string) $response->headers->get('Content-Type'));

        $documentation = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($documentation);
        self::assertArrayHasKey('hydra:supportedClass', $documentation);
        self::assertArrayHasKey('Widget', self::supportedClassesByTitle($documentation));
    }
}
