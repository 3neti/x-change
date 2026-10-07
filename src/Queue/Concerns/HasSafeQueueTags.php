<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Queue\Concerns;

use LBHurtado\XChange\Queue\QueueTopologyManifest;

trait HasSafeQueueTags
{
    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return [
            'package:x-change',
            'lane:'.app(QueueTopologyManifest::class)->laneFor(static::class),
            'job:'.class_basename(static::class),
        ];
    }
}
