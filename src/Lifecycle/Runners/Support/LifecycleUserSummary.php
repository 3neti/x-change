<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners\Support;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\ModelChannel\Contracts\HasMobileChannel;

final class LifecycleUserSummary
{
    public function fromModel(Model $user): array
    {
        return [
            'id' => $user->getKey(),
            'email' => $user->getAttribute('email'),
            'mobile' => $user instanceof HasMobileChannel ? $user->getMobileChannel() : null,
        ];
    }
}
