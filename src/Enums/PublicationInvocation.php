<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PublicationInvocation: string
{
    case Tag = 'tag';
    case Provider = 'provider';
}
