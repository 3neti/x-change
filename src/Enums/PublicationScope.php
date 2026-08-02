<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PublicationScope: string
{
    case Build = 'build';
    case Install = 'install';
    case Advanced = 'advanced';
}
