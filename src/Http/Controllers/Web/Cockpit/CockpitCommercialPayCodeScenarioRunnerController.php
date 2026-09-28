<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Data\TreasuryPositionData;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\RunCommercialPayCodeScenarioRequest;
use LBHurtado\XChange\Lifecycle\Output\NullLifecycleOutput;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioEngine;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Models\CommercialPrincipal;

final class CockpitCommercialPayCodeScenarioRunnerController extends Controller
{
    public function show(
        Request $request,
        CommercialOperatorAuthorityContract $authority,
        TreasuryAccountPortfolioProvisioningContract $portfolios,
    ): Response {
        $this->ensureEnabled();
        $maker = $request->user();
        abort_unless($maker instanceof Model, 403);
        $runReference = trim($request->string('run')->toString());

        return Inertia::render('x-change/cockpit/CommercialPayCodeScenarioRunner', [
            'commercial_principal' => $this->principal($portfolios),
            'maker' => $this->actor($maker),
            'maker_ready' => $authority->allows($maker, CommercialOperatorCapability::PreparePayCodes),
            'checkers' => $this->checkers($maker),
            'run' => $runReference === '' ? null : $this->run($maker, $runReference),
        ]);
    }

    public function store(
        RunCommercialPayCodeScenarioRequest $request,
        LifecycleScenarioEngine $engine,
    ): RedirectResponse {
        $this->ensureEnabled();
        $maker = $request->user();
        abort_unless($maker instanceof Model, 403);
        $phase = (string) $request->validated('phase');
        $runReference = trim((string) $request->validated('run_reference'));
        if ($runReference === '') {
            $runReference = 'COMM-'.Str::upper(Str::random(12));
        }

        $result = $engine->run(
            command: app(Command::class),
            scenarioKey: 'commercial_pay_code_issuance',
            options: new LifecycleScenarioRunOptions(
                maker: (string) $maker->getKey(),
                checker: (string) $request->integer('checker'),
                amount: 50,
                runReference: $runReference,
                phase: $phase,
                confirmCheckerApproval: $phase === 'approve',
                json: true,
            ),
            output: new NullLifecycleOutput,
        );

        if ($result->exitCode !== Command::SUCCESS) {
            return back()->withErrors([
                'scenario' => (string) ($result->payload['message'] ?? 'The lifecycle scenario could not continue.'),
            ]);
        }

        return to_route('x-change.cockpit.campaigns.commercial-pay-code-scenario-runner.show', [
            'run' => $runReference,
        ]);
    }

    /** @return array<string, mixed> */
    private function principal(TreasuryAccountPortfolioProvisioningContract $portfolios): array
    {
        $principal = CommercialPrincipal::query()
            ->where('reference', (string) config('x-change.commercial.principal.reference'))
            ->first();

        return [
            'reference' => $principal?->reference,
            'legal_name' => $principal?->legal_name,
            'active' => $principal?->active === true,
            'funding_source' => 'Commercial Principal Client Funds',
            'revenue_account_excluded' => true,
            'balances' => $principal instanceof CommercialPrincipal
                ? $this->principalBalances($principal, $portfolios)
                : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function checkers(Model $maker): array
    {
        $modelClass = (string) config('auth.providers.users.model');
        if (! is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        $ids = CommercialOperatorAuthorization::query()
            ->where('operator_type', $maker->getMorphClass())
            ->where('capability', CommercialOperatorCapability::ApprovePayCodes->value)
            ->currentlyValid()
            ->pluck('operator_id');

        return $modelClass::query()
            ->whereKey($ids)
            ->whereKeyNot($maker->getKey())
            ->get()
            ->map(fn (Model $checker): array => $this->actor($checker))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function actor(Model $actor): array
    {
        return [
            'id' => (string) $actor->getKey(),
            'name' => (string) ($actor->getAttribute('name') ?: 'Operator '.$actor->getKey()),
            'mobile' => (string) ($actor->getAttribute('mobile') ?: ''),
        ];
    }

    /** @return array<string, mixed>|null */
    private function run(Model $maker, string $runReference): ?array
    {
        $worksheet = CampaignWorksheet::query()
            ->with(['authorizations.fulfillments'])
            ->where('owner_type', $maker->getMorphClass())
            ->where('owner_id', (string) $maker->getKey())
            ->where('metadata->lifecycle->run_reference', $runReference)
            ->first();

        if (! $worksheet instanceof CampaignWorksheet) {
            return null;
        }

        $authorization = $worksheet->authorizations->sortByDesc('id')->first();
        $fulfillment = $authorization?->fulfillments->first();
        $voucher = is_string($fulfillment?->pay_code)
            ? Voucher::query()->where('code', $fulfillment->pay_code)->first()
            : null;
        $phase = data_get($voucher?->metadata, 'treasury.terminal_release.terminal_reason') === 'cancelled'
            ? 'cancelled'
            : ($fulfillment?->pay_code !== null ? 'issued' : 'awaiting_checker');

        return [
            'reference' => $runReference,
            'phase' => $phase,
            'amount' => '₱50.00',
            'worksheet_reference' => (string) $worksheet->reference,
            'approval_pay_code' => $authorization?->approval_pay_code,
            'authorization_reference' => $authorization?->reference,
            'funding_source' => 'Commercial Principal Client Funds',
            'pay_code' => $fulfillment?->pay_code,
            'steps' => [
                ['label' => '₱100 simulated provider-backed funds credited', 'complete' => true],
                ['label' => 'Maker prepared frozen instruction', 'complete' => true],
                ['label' => 'Checker independently approved', 'complete' => $authorization?->status === 'authorized'],
                ['label' => 'Commercial Principal Client Funds authorized', 'complete' => $authorization?->status === 'authorized'],
                ['label' => '₱50 Pay Code issued and reserved', 'complete' => $fulfillment?->pay_code !== null],
                ['label' => 'Cancellation returned reserve to Commercial Client Funds', 'complete' => $phase === 'cancelled'],
            ],
        ];
    }

    /** @return array<string, int> */
    private function principalBalances(
        CommercialPrincipal $principal,
        TreasuryAccountPortfolioProvisioningContract $portfolios,
    ): array {
        $balances = collect($portfolios->provision($principal, ['netbank-primary'])->positions)
            ->mapWithKeys(static fn (TreasuryPositionData $position): array => [
                $position->purpose->value => $position->balanceMinor,
            ]);
        $revenueAccount = $principal->wallets()
            ->where('slug', (string) config('x-change.commercial.principal.revenue_account_slug'))
            ->first();

        return [
            'client_funds_minor' => (int) $balances->get(TreasuryPositionPurpose::ClientFunds->value, 0),
            'pay_code_reserve_minor' => (int) $balances->get(TreasuryPositionPurpose::PayCodeReserve->value, 0),
            'revenue_minor' => (int) data_get($revenueAccount, 'balanceInt', 0),
        ];
    }

    private function ensureEnabled(): void
    {
        abort_unless(
            (bool) config('x-change.lifecycle.commercial_pay_code.browser_enabled', ! app()->isProduction()),
            404,
        );
    }
}
