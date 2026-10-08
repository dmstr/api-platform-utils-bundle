<?php
// file generated with AI assistance: Claude Code - 2026-10-08 12:50:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder;

use Doctrine\ORM\Mapping as ORM;

/**
 * Covers the type whitelist: sortable scalars, an integer identifier,
 * text/json/vector columns, to-one relations with and without a label
 * candidate, and an inverse-side to-many.
 */
#[ORM\Entity]
class Book
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string')]
    public string $title;

    #[ORM\Column(type: 'decimal')]
    public string $price;

    #[ORM\Column(type: 'boolean')]
    public bool $available;

    #[ORM\Column(type: 'text')]
    public string $summary;

    #[ORM\Column(type: 'json')]
    public array $metadata;

    #[ORM\Column(type: 'vector')]
    public array $embedding;

    #[ORM\Column(type: 'string')]
    public string $secret;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Author::class)]
    public ?Author $author = null;

    #[ORM\ManyToOne(targetEntity: Publisher::class)]
    public ?Publisher $publisher = null;
}
