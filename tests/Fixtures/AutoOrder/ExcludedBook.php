<?php
// file generated with AI assistance: Claude Code - 2026-10-08 12:50:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder;

use Dmstr\ApiPlatformUtils\Attribute\AutoOrder;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[AutoOrder(exclude: ['name', 'author'])]
class ExcludedBook
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string')]
    public string $name;

    #[ORM\Column(type: 'integer')]
    public int $pages;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Author::class)]
    public ?Author $author = null;
}
