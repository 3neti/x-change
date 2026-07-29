<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XCampaign\Contracts\CampaignWorksheetRepository;
use LBHurtado\XCampaign\Data\CampaignWorksheetData;
use LBHurtado\XCampaign\Data\CampaignWorksheetRowData;
use LBHurtado\XChange\Actions\Campaigns\IssueCampaignWorksheetApprovalPayCode;
use LBHurtado\XChange\Services\Campaigns\CampaignWorksheetAuthorizationExecutionService;
use LBHurtado\XChange\Tests\Fakes\User;

it('plans beneficiaries after a distinct authenticated officer authorizes the approval Pay Code', function () {
    $issuer = campaignOfficerAuthorizationUser();
    $officer = campaignOfficerAuthorizationUser('09173011987');
    $repository = app(CampaignWorksheetRepository::class);

    $worksheet = $repository->put(new CampaignWorksheetData(
        reference: 'campaign-officer-authorization-'.Str::lower(Str::random(8)),
        ownerType: $issuer->getMorphClass(),
        ownerId: (string) $issuer->getKey(),
        profile: 'payroll',
        name: 'Officer Authorization Test',
        rows: [
            new CampaignWorksheetRowData(null, 1, ['mobile' => '09173011987'], 12_500),
            new CampaignWorksheetRowData(null, 2, ['mobile' => '09178889999'], 7_500),
        ],
    ));

    $repository->freeze((string) $worksheet->reference, $issuer->getMorphClass(), (string) $issuer->getKey());

    $this->actingAs($issuer);
    $authorization = app(IssueCampaignWorksheetApprovalPayCode::class)->handle((string) $worksheet->reference, $issuer);
    $voucher = Voucher::query()->where('code', $authorization->approval_pay_code)->sole();

    $this->actingAs($officer);
    $result = app(CampaignWorksheetAuthorizationExecutionService::class)->execute($voucher, [
        'mobile' => '09173011987',
    ]);

    $authorization->refresh()->load('worksheet');

    expect($result->status)->toBe('authorized')
        ->and($result->meta['planned_count'])->toBe(2)
        ->and($authorization->status)->toBe('authorized')
        ->and($authorization->worksheet?->status)->toBe('authorized')
        ->and($authorization->fulfillments()->where('status', 'planned')->count())->toBe(2)
        ->and($authorization->fulfillments()->whereNotNull('pay_code')->count())->toBe(0)
        ->and($authorization->fulfillments()->whereNotNull('provider_transfer_reference')->count())->toBe(0);
});

function campaignOfficerAuthorizationUser(?string $mobile = null): User
{
    $user = User::query()->create([
        'name' => 'Campaign Officer',
        'email' => 'campaign-officer-'.Str::uuid().'@example.test',
        'password' => Hash::make('password'),
    ]);

    if ($mobile !== null) {
        $user->forceFill(['mobile' => $mobile])->save();
    }

    return $user;
}
