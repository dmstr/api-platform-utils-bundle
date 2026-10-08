<?php
// file generated with AI assistance: Claude Code - 2026-10-08 12:50:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use Doctrine\ORM\Mapping as ORM;

/** Class-level OrderFilter without properties: covers every property. */
#[ORM\Entity]
#[ApiFilter(OrderFilter::class)]
class OrderAllBook
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string')]
    public string $name;
}
