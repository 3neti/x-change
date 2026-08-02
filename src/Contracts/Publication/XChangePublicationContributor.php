<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts\Publication;

use LBHurtado\XChange\Data\Publication\PublicationDefinitionData;

interface XChangePublicationContributor
{
    /** @return iterable<PublicationDefinitionData> */
    public function publications(): iterable;
}
