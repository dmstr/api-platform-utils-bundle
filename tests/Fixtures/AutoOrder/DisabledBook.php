<?php
// file generated with AI assistance: Claude Code - 2026-10-08 12:50:00 UTC

declare(strict_types=1);

namespace Dmstr\ApiPlatformUtils\Tests\Fixtures\AutoOrder;

use Dmstr\ApiPlatformUtils\Attribute\AutoOrder;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[AutoOrder(enabled: false)]
class DisabledBook
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    public int $id;

    #[ORM\Column(type: 'string')]
    public string $name;
}
