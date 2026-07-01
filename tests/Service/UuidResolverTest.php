<?php
// file generated with AI assistance: Claude Code - 2026-07-01 15:25:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Service;

use Dmstr\ApiPlatformUtils\Service\UuidResolver;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\TestCase;

/**
 * The partial-UUID lookup SQL must match the platform's UUID storage:
 * BINARY(16) + HEX()/BIN_TO_UUID() on MySQL, native `uuid` + `::text` on
 * PostgreSQL. A MySQL-only query threw `function bin_to_uuid(uuid) does not
 * exist` on PostgreSQL; these tests guard against a regression.
 */
final class UuidResolverTest extends TestCase
{
    public function testPostgresSqlCastsToTextAndAvoidsMysqlFunctions(): void
    {
        $sql = UuidResolver::buildPartialUuidSql(new PostgreSQLPlatform(), 'za7_api_configuration', 'id');

        self::assertStringContainsString('id::text', $sql);
        self::assertStringContainsString("REPLACE(LOWER(id::text), '-', '')", $sql);
        self::assertStringNotContainsString('BIN_TO_UUID', $sql);
        self::assertStringNotContainsString('HEX(', $sql);
    }

    public function testMysqlSqlUsesHexFunctions(): void
    {
        $sql = UuidResolver::buildPartialUuidSql(new MySQL80Platform(), 'za7_api_configuration', 'id');

        self::assertStringContainsString('BIN_TO_UUID(id)', $sql);
        self::assertStringContainsString('HEX(id)', $sql);
    }
}
