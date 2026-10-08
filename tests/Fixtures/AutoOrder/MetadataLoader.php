<?php
// file generated with AI assistance: Claude Code - 2026-10-08 12:50:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;

/** Builds Doctrine ClassMetadata from the fixture attributes, without an EntityManager. */
final class MetadataLoader
{
    /** @var array<class-string, ClassMetadata> */
    private static array $cache = [];

    /**
     * @param class-string $class
     */
    public static function load(string $class): ClassMetadata
    {
        if (!isset(self::$cache[$class])) {
            $metadata = new ClassMetadata($class);
            $metadata->initializeReflection(new RuntimeReflectionService());
            (new AttributeDriver([__DIR__]))->loadMetadataForClass($class, $metadata);
            self::$cache[$class] = $metadata;
        }

        return self::$cache[$class];
    }
}
