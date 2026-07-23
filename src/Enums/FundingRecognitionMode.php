<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum FundingRecognitionMode: string
{
    case ObserveOnly = 'observe_only';
    case Supervised = 'supervised';
    case Automatic = 'automatic';
}
