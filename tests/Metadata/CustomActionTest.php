<?php
// file generated with AI assistance: Claude Code - 2026-11-26 13:30:36 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Metadata;

use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\Post;
use Dmstr\ApiPlatformUtils\Metadata\CustomAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CustomActionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function templates(): iterable
    {
        // template, expected by default, expected with ignoreFormatPlaceholder
        yield 'empty' => ['', false, false];
        yield 'root' => ['/', false, false];
        yield 'collection' => ['/things', false, false];
        yield 'collection with format' => ['/things{._format}', false, false];
        yield 'collection with trailing slash' => ['/things/', false, false];
        yield 'item' => ['/things/{id}', false, false];
        yield 'item with format' => ['/things/{id}{._format}', false, false];
        yield 'collection action' => ['/things/scan', true, true];
        yield 'collection action with format' => ['/things/scan{._format}', true, true];
        yield 'item action' => ['/things/{id}/approve', true, true];
        yield 'nested item' => ['/things/{id}/parts/{partId}', true, true];
        yield 'format alone' => ['/{._format}', false, false];
        yield 'format as segment after collection' => ['/things/{._format}', false, false];
        // The only shape where the option changes the result.
        yield 'format as segment after item' => ['/things/{id}/{._format}', true, false];
    }

    #[DataProvider('templates')]
    public function testClassifiesHttpOperationsByUriTemplate(string $template, bool $default, bool $ignoringFormat): void
    {
        $operation = new Post(uriTemplate: $template);

        self::assertSame($default, CustomAction::isCustomAction($operation));
        self::assertSame($ignoringFormat, CustomAction::isCustomAction($operation, ignoreFormatPlaceholder: true));
    }

    public function testOperationWithoutUriTemplateIsNotCustom(): void
    {
        self::assertFalse(CustomAction::isCustomAction(new Post()));
    }

    public function testNonHttpOperationIsNotCustom(): void
    {
        self::assertFalse(CustomAction::isCustomAction(new Query(name: 'things/scan/now')));
        self::assertFalse(CustomAction::isCustomAction(new Query(name: 'things/scan/now'), ignoreFormatPlaceholder: true));
    }
}
