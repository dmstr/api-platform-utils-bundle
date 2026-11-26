<?php
// file generated with AI assistance: Claude Code - 2026-10-08 13:10:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\DependencyInjection;

use Dmstr\ApiPlatformUtils\DependencyInjection\ApiPlatformUtilsExtension;
use Dmstr\ApiPlatformUtils\Doctrine\Orm\Extension\StableOrderExtension;
use Dmstr\ApiPlatformUtils\Metadata\AutoOrderResourceMetadataCollectionFactory;
use Dmstr\ApiPlatformUtils\OpenApi\InvalidDefaultSchemaDecorator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class ApiPlatformUtilsExtensionTest extends TestCase
{
    private const BASE = ['credential_encryption' => ['key' => 'a2V5']];

    public function testSwitchesAreOffByDefault(): void
    {
        $container = $this->load([self::BASE]);

        self::assertFalse($container->hasDefinition(AutoOrderResourceMetadataCollectionFactory::class));
        self::assertFalse($container->hasDefinition(StableOrderExtension::class));
        self::assertFalse($container->getParameter('dmstr_api_platform_utils.auto_order.enabled'));
        self::assertFalse($container->getParameter('dmstr_api_platform_utils.stable_order.enabled'));
        self::assertSame(['name' => 'ASC', 'createdAt' => 'DESC'], $container->getParameter('dmstr_api_platform_utils.auto_order.default_order'));
        self::assertSame(
            ['name', 'title', 'label', 'displayName', 'email'],
            $container->getParameter('dmstr_api_platform_utils.relation_field_decorator.label_property_candidates'),
        );
    }

    public function testAutoOrderService(): void
    {
        $container = $this->load([self::BASE + ['auto_order' => ['enabled' => true, 'default_order' => ['title' => 'asc']]]]);

        $definition = $container->getDefinition(AutoOrderResourceMetadataCollectionFactory::class);
        self::assertSame(['api_platform.metadata.resource.metadata_collection_factory', null, 1100], \array_slice($definition->getDecoratedService(), 0, 3));
        self::assertSame(['title' => 'ASC'], $container->getParameter('dmstr_api_platform_utils.auto_order.default_order'));
    }

    public function testDefaultOrderIsReplacedNotMerged(): void
    {
        $container = $this->load([
            self::BASE + ['auto_order' => ['default_order' => ['name' => 'ASC', 'createdAt' => 'DESC']]],
            ['auto_order' => ['default_order' => ['createdAt' => 'DESC']]],
        ]);

        self::assertSame(['createdAt' => 'DESC'], $container->getParameter('dmstr_api_platform_utils.auto_order.default_order'));
    }

    public function testInvalidDirectionIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([self::BASE + ['auto_order' => ['default_order' => ['name' => 'up']]]]);
    }

    public function testStableOrderServiceTag(): void
    {
        $container = $this->load([self::BASE + ['stable_order' => ['enabled' => true]]]);

        self::assertSame(
            [['priority' => -40]],
            $container->getDefinition(StableOrderExtension::class)->getTag('api_platform.doctrine.orm.query_extension.collection'),
        );
    }

    public function testSchemaDefaultCleanupIsOnByDefaultAndCanBeSwitchedOff(): void
    {
        $on = $this->load([self::BASE]);
        self::assertTrue($on->hasDefinition(InvalidDefaultSchemaDecorator::class));
        self::assertSame(
            'api_platform.json_schema.schema_factory',
            $on->getDefinition(InvalidDefaultSchemaDecorator::class)->getDecoratedService()[0],
        );

        $off = $this->load([self::BASE + ['schema_default_cleanup' => ['enabled' => false]]]);
        self::assertFalse($off->hasDefinition(InvalidDefaultSchemaDecorator::class));
    }

    public function testLabelCandidatesOfSeveralConfigsAreDeduplicated(): void
    {
        $container = $this->load([
            self::BASE + ['relation_field_decorator' => ['label_property_candidates' => ['name', 'title']]],
            ['relation_field_decorator' => ['label_property_candidates' => ['name', 'title', 'email']]],
        ]);

        self::assertSame(
            ['name', 'title', 'email'],
            $container->getParameter('dmstr_api_platform_utils.relation_field_decorator.label_property_candidates'),
        );
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new ApiPlatformUtilsExtension())->load($configs, $container);

        return $container;
    }
}
