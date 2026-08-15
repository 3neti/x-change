<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PartnerApi;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Models\PartnerApiClient;
use LogicException;

class PartnerApiRequestContext
{
    protected ?PartnerApiClient $client = null;

    public function setClient(PartnerApiClient $client): void
    {
        $this->client = $client;
    }

    public function client(): PartnerApiClient
    {
        return $this->client
            ?? throw new LogicException('Partner API request context has not been authenticated.');
    }

    public function issuer(): Model
    {
        $issuer = $this->client()->issuer;

        return $issuer instanceof Model
            ? $issuer
            : throw new LogicException('Partner API client has no resolvable issuer Account.');
    }
}
