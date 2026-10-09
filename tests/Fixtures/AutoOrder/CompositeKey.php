<?php
// file generated with AI assistance: Claude Code - 2026-10-08 12:50:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CompositeKey
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $a;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $b;

    #[ORM\Column(type: 'string')]
    public string $name;
}
