<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake\Contributors;

use BackedEnum;
use LBHurtado\XChange\Contracts\Keepsake\InstanceKeepsakeContributor;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakeContext;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakeContribution;
use LBHurtado\XChange\Exceptions\InstanceKeepsakeException;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Services\Keepsake\CanonicalKeepsakeJson;

final readonly class PayCodeSummaryKeepsakeContributor implements InstanceKeepsakeContributor
{
    public function __construct(private CanonicalKeepsakeJson $json) {}

    public function key(): string
    {
        return 'pay-codes';
    }

    public function snapshotSchemaVersion(): int
    {
        return 1;
    }

    public function blueprintSchemaVersion(): ?int
    {
        return 1;
    }

    public function contribute(InstanceKeepsakeContext $context): InstanceKeepsakeContribution
    {
        if (! $context->includes('pay-codes')) {
            return new InstanceKeepsakeContribution($this->key(), 1, 1);
        }

        $payCodes = [];

        foreach ($context->vouchers as $voucher) {
            $model = $voucher['model'];
            $instructions = $model->getAttribute('instructions');
            $cash = is_object($instructions) ? ($instructions->cash ?? null) : null;
            $amount = is_object($cash) ? ($cash->amount ?? null) : null;

            $payCodes[] = [
                'reference' => $voucher['reference'],
                'account_reference' => $this->ownerReference($context, $model),
                'code_fingerprint' => $this->codeFingerprint((string) $model->getAttribute('code')),
                'state' => $this->stringValue($model->getAttribute('state')),
                'amount_minor' => is_numeric($amount) ? (int) round(((float) $amount) * 100) : null,
                'currency' => is_object($cash) && filled($cash->currency ?? null)
                    ? mb_strtoupper((string) $cash->currency)
                    : $context->currency,
                'created_at' => $model->getAttribute('created_at')?->toIso8601String(),
                'expires_at' => $model->getAttribute('expires_at')?->toIso8601String(),
                'redeemed_at' => $model->getAttribute('redeemed_at')?->toIso8601String(),
                'historical_only' => true,
                'restorable' => false,
            ];
        }

        $templates = $context->includes('blueprint')
            ? $this->templates($context)
            : [];

        return new InstanceKeepsakeContribution(
            key: $this->key(),
            snapshotSchemaVersion: 1,
            blueprintSchemaVersion: 1,
            snapshotFiles: [
                'snapshot/pay-codes.json' => $this->json->encode([
                    'schema' => 'x-change.instance-keepsake.pay-codes.v1',
                    'issued_codes_are_historical_only' => true,
                    'pay_codes' => $payCodes,
                ]),
            ],
            blueprintFiles: $context->includes('blueprint') ? [
                'blueprint/pay-code-templates.json' => $this->json->encode([
                    'schema' => 'x-change.instance-keepsake.pay-code-templates.v1',
                    'inert' => true,
                    'original_codes_included' => false,
                    'templates' => $templates,
                ]),
            ] : [],
            summary: [
                'pay_codes' => count($payCodes),
                'bootstrap_templates' => count($templates),
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    private function templates(InstanceKeepsakeContext $context): array
    {
        $templates = [];
        $ownerKeys = [];

        foreach ($context->users as $user) {
            $ownerKeys[$user['model']->getMorphClass().'|'.$user['model']->getKey()] = $user['reference'];
        }

        PayCodeTemplate::query()
            ->select(['id', 'owner_type', 'owner_id', 'reference', 'name', 'description', 'base_template_key', 'include_amount', 'include_purpose', 'status'])
            ->orderBy('id')
            ->lazyById((int) config('x-change.instance_keepsake.chunk_size', 100))
            ->each(function (PayCodeTemplate $template) use (&$templates, $ownerKeys): void {
                $ownerReference = $ownerKeys[$template->owner_type.'|'.$template->owner_id] ?? null;

                if ($ownerReference === null || $template->status !== 'active') {
                    return;
                }

                $templates[] = [
                    'reference' => 'template-'.str_pad((string) (count($templates) + 1), 6, '0', STR_PAD_LEFT),
                    'account_reference' => $ownerReference,
                    'name' => (string) $template->name,
                    'description' => filled($template->description) ? (string) $template->description : null,
                    'base_template_key' => (string) $template->base_template_key,
                    'include_amount' => (bool) $template->include_amount,
                    'include_purpose' => (bool) $template->include_purpose,
                    'instructions_included' => false,
                    'requires_review' => true,
                ];
            });

        return $templates;
    }

    private function ownerReference(InstanceKeepsakeContext $context, mixed $voucher): ?string
    {
        foreach ($context->users as $user) {
            if ($voucher->owner_type === $user['model']->getMorphClass()
                && (string) $voucher->owner_id === (string) $user['model']->getKey()) {
                return $user['reference'];
            }
        }

        return null;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    private function codeFingerprint(string $code): string
    {
        $key = (string) config('app.key');

        if ($key === '') {
            throw new InstanceKeepsakeException(
                'encryption_unavailable',
                'The application key is required to redact Pay Code credentials.',
            );
        }

        return hash_hmac('sha256', 'keepsake-pay-code|'.$code, $key);
    }
}
