<?php
// file generated with AI assistance: Claude Code - 2026-10-08 12:50:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use Doctrine\ORM\Mapping as ORM;

/** Explicit OrderFilter on class level (title) and property level (author.name). */
#[ORM\Entity]
#[ApiFilter(OrderFilter::class, properties: ['title' => 'ASC'])]
class ExplicitOrderBook
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string')]
    public string $title;

    #[ORM\Column(type: 'integer')]
    public int $pages;

    #[ORM\ManyToOne(targetEntity: Author::class)]
    #[ApiFilter(OrderFilter::class, properties: ['name'])]
    public ?Author $author = null;
}
