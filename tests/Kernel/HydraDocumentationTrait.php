<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:25:10 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Kernel;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fetches `/api/docs.jsonld` from the booted TestKernel and gives access to
 * the decoded document and its supported classes. Use in a
 * KernelTestCase whose KERNEL_CLASS is TestKernel.
 */
trait HydraDocumentationTrait
{
    protected static function requestHydraDocumentation(): Response
    {
        $kernel = static::bootKernel();

        return $kernel->handle(Request::create('/api/docs.jsonld', 'GET', server: [
            'HTTP_ACCEPT' => 'application/ld+json',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function fetchHydraDocumentation(): array
    {
        $response = static::requestHydraDocumentation();
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * Supported classes keyed by `hydra:title` (the resource shortName).
     *
     * @param array<string, mixed> $documentation
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function supportedClassesByTitle(array $documentation): array
    {
        $classes = [];
        foreach ($documentation['hydra:supportedClass'] ?? [] as $class) {
            $classes[(string) ($class['hydra:title'] ?? '')] = $class;
        }

        return $classes;
    }
}
