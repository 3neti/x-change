<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\PartnerApi;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Laravel\Passport\Token;
use LBHurtado\XChange\Enums\PartnerApiClientStatus;
use LBHurtado\XChange\Enums\PartnerApiOperatorCapability;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiGovernanceJournal;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiOperatorAuthority;
use LogicException;

final readonly class ChangePartnerApiClientStatus
{
    public function __construct(
        private PartnerApiOperatorAuthority $authority,
        private PartnerApiGovernanceJournal $journal,
    ) {}

    public function suspend(PartnerApiClient $client, Model $operator): PartnerApiClient
    {
        $this->authorize($operator, PartnerApiOperatorCapability::SuspendClients);

        return $this->change($client, PartnerApiClientStatus::Suspended, $operator);
    }

    public function revoke(PartnerApiClient $client, Model $operator): PartnerApiClient
    {
        $this->authorize($operator, PartnerApiOperatorCapability::RevokeClients);

        return $this->change($client, PartnerApiClientStatus::Revoked, $operator);
    }

    private function change(PartnerApiClient $client, PartnerApiClientStatus $status, Model $operator): PartnerApiClient
    {
        return DB::transaction(function () use ($client, $status, $operator): PartnerApiClient {
            $locked = PartnerApiClient::query()->lockForUpdate()->findOrFail($client->getKey());

            if ($locked->status === PartnerApiClientStatus::Revoked) {
                if ($status === PartnerApiClientStatus::Revoked) {
                    return $locked;
                }

                throw new LogicException('A revoked Partner API client cannot be reactivated.');
            }

            Token::query()->where('client_id', $locked->oauth_client_id)->update(['revoked' => true]);

            if ($status === PartnerApiClientStatus::Revoked) {
                Client::query()->whereKey($locked->oauth_client_id)->update(['revoked' => true]);
            }

            $locked->forceFill([
                'status' => $status,
                'suspended_at' => $status === PartnerApiClientStatus::Suspended ? now() : $locked->suspended_at,
                'revoked_at' => $status === PartnerApiClientStatus::Revoked ? now() : null,
            ])->save();
            $this->journal->recordClient(
                $locked,
                $status === PartnerApiClientStatus::Suspended
                    ? 'partner_api.client.suspended'
                    : 'partner_api.client.revoked',
                $operator,
            );

            return $locked->refresh();
        }, 3);
    }

    private function authorize(Model $operator, PartnerApiOperatorCapability $capability): void
    {
        if (! $this->authority->allows($operator, $capability)) {
            throw new AuthorizationException('Partner API client governance authority is required.');
        }
    }
}
