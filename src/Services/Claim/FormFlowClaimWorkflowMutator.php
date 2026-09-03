<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Composer\InstalledVersions;
use LBHurtado\FormFlowManager\Data\FormFlowInstructionsData;
use LBHurtado\XChange\Data\Claim\ClaimWorkflowDescriptorData;
use LBHurtado\XChange\Services\MoneyIssuer\MoneyIssuerOptionPresenter;

final class FormFlowClaimWorkflowMutator
{
    public function __construct(
        private readonly MoneyIssuerOptionPresenter $moneyIssuers,
    ) {}

    public function apply(
        FormFlowInstructionsData $instructions,
        ClaimWorkflowDescriptorData $workflow,
        ?string $authenticatedMobile = null,
        ?string $settlementRail = null,
    ): FormFlowInstructionsData {
        return FormFlowInstructionsData::from($this->mutate(
            $instructions->toArray(),
            $workflow,
            $authenticatedMobile,
            $settlementRail,
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function mutate(
        array $payload,
        ClaimWorkflowDescriptorData $workflow,
        ?string $authenticatedMobile = null,
        ?string $settlementRail = null,
    ): array {

        $payload['title'] = $workflow->title;
        $payload['description'] = $workflow->description;
        $payload['metadata'] = array_replace_recursive((array) ($payload['metadata'] ?? []), [
            'claim_workflow' => $this->workflowPayload($workflow),
        ]);

        $payload['steps'] = array_map(
            fn (array $step): array => $this->applyToStep($step, $workflow, $authenticatedMobile, $settlementRail),
            (array) ($payload['steps'] ?? []),
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    private function applyToStep(
        array $step,
        ClaimWorkflowDescriptorData $workflow,
        ?string $authenticatedMobile,
        ?string $settlementRail,
    ): array {
        $step['config'] = (array) ($step['config'] ?? []);
        $step['config']['claim_workflow'] = $this->workflowPayload($workflow);
        $step['config'] = $this->applyClaimUiContract($step['config']);

        if (($workflow->review['onboarding'] ?? false) === true) {
            $step['config']['app_name'] = config('app.name', 'X-Change');
        }

        if (($step['handler'] ?? null) === 'otp'
            && ($workflow->review['onboarding'] ?? false) === true) {
            $step['config']['purpose'] = config(
                'x-change.onboarding.identity_otp.purpose',
                'onboarding.account',
            );
        }

        if (($step['config']['step_name'] ?? null) !== 'wallet_info') {
            $step['config']['fields'] = $this->markRequiredFields(
                (array) ($step['config']['fields'] ?? []),
                $workflow,
            );

            return $step;
        }

        $step['config']['title'] = $workflow->title;
        $step['config']['description'] = $workflow->description;
        $step['config']['auto_sync'] = ['enabled' => false];
        $rail = $settlementRail ?? $this->settlementRail((array) ($step['config']['fields'] ?? []));
        $step['config']['fields'] = array_values(array_map(
            fn (array $field): array => $this->applyToField(
                $field,
                $workflow,
                $authenticatedMobile,
                $rail,
            ),
            array_filter(
                (array) ($step['config']['fields'] ?? []),
                fn (array $field): bool => $this->shouldKeepField($field, $workflow),
            ),
        ));

        return $step;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function applyClaimUiContract(array $config): array
    {
        $variant = (string) config(
            'x-change.claim.experience_ui.variant',
            config('form-flow.ui.variant', 'default'),
        );
        $actionPlacement = config('x-change.claim.experience_ui.action_placement')
            ?: 'viewport_bottom';

        $config['ui_variant'] ??= $variant;
        $config['action_placement'] ??= $actionPlacement;
        $config['ui_layout'] = array_replace_recursive(
            (array) config('x-change.claim.experience_ui.layout', []),
            (array) ($config['ui_layout'] ?? []),
        );
        $config['app_name'] ??= config('x-change.claim.experience_ui.brand.app_name', 'Pay Code');
        $config['app_logo'] ??= config(
            'x-change.claim.experience_ui.brand.app_logo',
            '/vendor/x-change/images/pay-code/pay-code-logo.svg',
        );
        $config['package_versions'] ??= $this->claimUiPackageVersions();
        $config['show_package_versions'] ??= (bool) config(
            'x-change.claim.experience_ui.show_package_versions',
            false,
        );

        return $config;
    }

    /**
     * @return list<array{name: string, version: string}>
     */
    private function claimUiPackageVersions(): array
    {
        return array_values(array_filter(array_map(
            fn (string $package): ?array => $this->installedPackageVersion($package),
            [
                '3neti/x-change',
                '3neti/form-flow',
                '3neti/form-handler-kyc',
                '3neti/form-handler-location',
                '3neti/form-handler-otp',
                '3neti/form-handler-selfie',
                '3neti/form-handler-signature',
            ],
        )));
    }

    /**
     * @return array{name: string, version: string}|null
     */
    private function installedPackageVersion(string $package): ?array
    {
        if (! InstalledVersions::isInstalled($package)) {
            return null;
        }

        $version = InstalledVersions::getPrettyVersion($package);

        if (! is_string($version) || $version === '') {
            return null;
        }

        return [
            'name' => $package,
            'version' => $version,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    private function markRequiredFields(
        array $fields,
        ClaimWorkflowDescriptorData $workflow,
    ): array {
        return array_values(array_map(
            static function (array $field) use ($workflow): array {
                if (in_array($field['name'] ?? null, $workflow->required_claim_fields, true)) {
                    $field['required'] = true;
                }

                return $field;
            },
            $fields,
        ));
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function shouldKeepField(array $field, ClaimWorkflowDescriptorData $workflow): bool
    {
        return match ($field['name'] ?? null) {
            'amount' => $workflow->requires_amount,
            'settlement_rail', 'bank_code', 'account_number' => $workflow->requires_destination,
            'mobile' => $workflow->requires_mobile,
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function applyToField(
        array $field,
        ClaimWorkflowDescriptorData $workflow,
        ?string $authenticatedMobile,
        string $rail,
    ): array {
        if (
            ($workflow->review['onboarding'] ?? false) === true
            && ($field['group'] ?? null) === 'redeemer'
        ) {
            $field['group'] = 'account';
        }

        if (in_array($field['name'] ?? null, $workflow->required_claim_fields, true)) {
            $field['required'] = true;
        }

        if (
            ($field['name'] ?? null) === 'mobile'
            && $workflow->requires_authenticated_officer
            && filled($authenticatedMobile)
        ) {
            $field['default'] = $authenticatedMobile;
            $field['readonly'] = true;
            $field['persist'] = false;
        }

        if (($field['name'] ?? null) === 'bank_code') {
            $field['institution_options'] = $this->moneyIssuers->forRail($rail);
            $field['help_text'] = 'Choose the receiving bank or wallet by name.';
            $field['persist'] = false;
        }

        if (($field['name'] ?? null) === 'settlement_rail') {
            $field['default'] = $rail;
            $field['readonly'] = true;
            $field['persist'] = false;
        }

        return $field;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private function settlementRail(array $fields): string
    {
        foreach ($fields as $field) {
            if (($field['name'] ?? null) === 'settlement_rail') {
                return (string) ($field['default'] ?? 'INSTAPAY');
            }
        }

        return 'INSTAPAY';
    }

    /**
     * @return array<string, mixed>
     */
    private function workflowPayload(ClaimWorkflowDescriptorData $workflow): array
    {
        return [
            'key' => $workflow->key,
            'title' => $workflow->title,
            'description' => $workflow->description,
            'requires_mobile' => $workflow->requires_mobile,
            'requires_destination' => $workflow->requires_destination,
            'requires_amount' => $workflow->requires_amount,
            'requires_authenticated_officer' => $workflow->requires_authenticated_officer,
            'authentication_mode' => $workflow->authentication_mode->value,
            'required_claim_fields' => $workflow->required_claim_fields,
            'confirmation_label' => $workflow->confirmation_label,
            'confirmation_title' => $workflow->confirmation_title,
            'skip_form_flow_splash' => $workflow->skip_form_flow_splash,
            'review' => $workflow->review,
        ];
    }
}
