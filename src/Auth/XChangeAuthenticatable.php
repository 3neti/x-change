<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Auth;

use Bavix\Wallet\Interfaces\Customer;
use Illuminate\Foundation\Auth\User as Authenticatable;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;
use LBHurtado\XChange\Auth\Concerns\HasXChangePrincipalCapabilities;

abstract class XChangeAuthenticatable extends Authenticatable implements Customer, HasMobileChannel
{
    use HasXChangePrincipalCapabilities;
}
