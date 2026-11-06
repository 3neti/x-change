<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LBHurtado\SettlementEnvelope\Services\DriverService;
use LBHurtado\SettlementEnvelope\Services\EnvelopeService;
use LBHurtado\Voucher\Contracts\GeneratesVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Data\VoucherMetadataData;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Contracts\PayCodeIssuanceContract;
use LBHurtado\XChange\Contracts\RiderSplashArtworkSnapshotterContract;
use LBHurtado\XChange\Contracts\RiderStampArtifactStoreContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;
use LBHurtado\XChange\Services\Cockpit\CockpitPosSaleReferenceService;
use LBHurtado\XChange\Services\Commercial\CommercialPricingAcceptanceGuard;
use LBHurtado\XChange\Services\Payment\PayCodePaymentLinkResolver;
use LBHurtado\XChange\Services\Settlement\IssuerInvoiceDrafts;
use LBHurtado\XChange\Services\Stamp\StampProviderIdentity;

class PayCodeIssuanceService implements PayCodeIssuanceContract
{
    public function __construct(
        protected RiderSplashArtworkSnapshotterContract $splashArtwork,
        protected RiderStampArtifactStoreContract $stampArtifacts,
        protected PayCodePaymentLinkResolver $paymentLinks,
        protected WalletAccessContract $wallets,
        protected CockpitPosSaleReferenceService $posSaleReferences,
    ) {}

    public function issue(mixed $issuer, array $input): array
    {
        if (! $issuer instanceof Authenticatable) {
            throw new PayCodeIssuanceFailed('Pay Code issuance requires a valid authenticatable issuer.');
        }

        $input = app(VoucherIssuancePayloadNormalizer::class)->normalize($input);
        $input = $this->withCollectionWalletContext($issuer, $input);
        $this->assertCollectionWalletPresentForCollectible($input);
        if (data_get($input, 'execution.schema') === 'voucher.execution.v2' && is_array(data_get($input, 'pricing'))) {
            app(CommercialPricingAcceptanceGuard::class)->assertV2Decision(
                data_get($input, 'pricing'),
                app(EstimatePayCodeCost::class)->handle($input),
            );
        }
        $instructions = VoucherInstructionsData::createFromAttribs($input);
        $this->restoreCustomMetadata($instructions, $input);
        $this->restorePreparedSplashArtworkSnapshot($instructions, $input);
        $identity = app(StampProviderIdentity::class)->resolve(data_get($input, 'provider'));
        $instructions->metadata ??= VoucherMetadataData::from([]);
        $custom = (array) $instructions->metadata->custom;
        unset($custom['stamp_provider']);
        if ($identity !== null) {
            $custom['stamp_provider'] = $identity['code'];
        }
        $instructions->metadata->custom = $custom;

        /** @var Authenticatable|null $previousUser */
        $previousUser = Auth::user();

        $createdFiles = [];
        try {
            Auth::setUser($issuer);

            return DB::transaction(function () use ($input, $instructions, $issuer, &$createdFiles): array {
                $generated = app(GeneratesVouchers::class)->handle($instructions);
                $issued = $generated->first();

                if (! $issued) {
                    throw new PayCodeIssuanceFailed('Pay Code issuance did not return a voucher.');
                }

                $requirements = data_get($input, 'execution.document_requirements') ?? [];
                $invoiceDraft = data_get($input, 'issuer_invoice_draft');
                $invoiceDrafts = data_get($input, 'issuer_invoice_drafts', []);
                $paymentDocuments = data_get($input, 'payment_details.documents', []);
                if (! is_array($invoiceDrafts) || ! is_array($paymentDocuments) || count($invoiceDrafts) !== count($paymentDocuments)) {
                    throw ValidationException::withMessages(['issuer_invoice_drafts' => 'The payment documents do not match their uploads.']);
                }
                $isPhilhealth = data_get($input, 'execution.schema') === 'voucher.execution.v2'
                    && data_get($input, 'execution.driver') === 'settlement_envelope'
                    && data_get($input, 'execution.settlement.driver') === 'philhealth-bst';
                if ($isPhilhealth) {
                    foreach ($generated as $voucher) {
                        app(EnvelopeService::class)->create(
                            referenceCode: (string) $voucher->code,
                            driverId: 'philhealth.bst.demo',
                            driverVersion: '1.0.0',
                            reference: $voucher,
                            actor: $issuer instanceof Model ? $issuer : null,
                        );
                    }
                } elseif (in_array(data_get($input, 'voucher_type'), ['payable', 'settlement'], true)
                    || $requirements !== [] || is_string($invoiceDraft) || $invoiceDrafts !== []) {
                    $driver = app(DriverService::class)->load('x-change.documents', '1.0.0');
                    foreach ($requirements as $requirement) {
                        if ($driver->getDocumentType($requirement['type']) === null) {
                            throw ValidationException::withMessages(['execution.document_requirements' => 'A requested document type is not supported.']);
                        }
                    }
                    foreach ($generated as $voucher) {
                        app(EnvelopeService::class)->create(
                            referenceCode: (string) $voucher->code,
                            driverId: 'x-change.documents',
                            driverVersion: '1.0.0',
                            reference: $voucher,
                            actor: $issuer instanceof Model ? $issuer : null,
                        );
                    }
                }

                if (is_string($invoiceDraft)) {
                    $orderKey = data_get($input, '_meta.idempotency_key');
                    if (! $issuer instanceof Model || ! is_string($orderKey) || $orderKey === '') {
                        throw ValidationException::withMessages(['issuer_invoice_draft' => 'The invoice order could not be verified.']);
                    }
                    $stored = app(IssuerInvoiceDrafts::class)->attach($issuer, $issued, $invoiceDraft, $orderKey, $isPhilhealth ? 'HOSPITAL_BILL' : 'INVOICE');
                    if ($stored !== null) {
                        $createdFiles[] = $stored;
                        $register = data_get($input, '_meta.register_issuance_file');
                        if ($register instanceof \Closure) {
                            $register($stored);
                        }
                    }
                }
                if ($invoiceDrafts !== []) {
                    $orderKey = data_get($input, '_meta.idempotency_key');
                    if (! $issuer instanceof Model || ! is_string($orderKey) || $orderKey === '' || is_string($invoiceDraft)) {
                        throw ValidationException::withMessages(['issuer_invoice_drafts' => 'The document order could not be verified.']);
                    }
                    foreach ($invoiceDrafts as $index => $reference) {
                        $documentId = data_get($paymentDocuments, $index.'.id');
                        if (! is_string($reference) || ! is_string($documentId)) {
                            throw ValidationException::withMessages(['issuer_invoice_drafts' => 'The document order is invalid.']);
                        }
                        $stored = app(IssuerInvoiceDrafts::class)->attach($issuer, $issued, $reference, $orderKey, $isPhilhealth ? 'HOSPITAL_BILL' : 'INVOICE', $documentId, $index + 1);
                        if ($stored !== null) {
                            $createdFiles[] = $stored;
                            $register = data_get($input, '_meta.register_issuance_file');
                            if ($register instanceof \Closure) {
                                $register($stored);
                            }
                        }
                    }
                }

                $this->splashArtwork->assertStored($issued);
                $this->persistNamedSliceMetadata($issued, $input);
                $this->posSaleReferences->record($issued, $issuer, $input);

                $code = (string) $issued->code;
                $redeemPath = $this->redeemPath($code);
                $redeemUrl = $this->redeemUrl($redeemPath);
                $paymentLinks = $this->paymentLinks->forVoucher($issued);
                $stampUrl = data_get($input, 'voucher_type') === 'payable' ? ($paymentLinks['pay'] ?? $redeemUrl) : $redeemUrl;
                $this->stampArtifacts->materialize($issued, $stampUrl);

                return [
                    'voucher_id' => $issued->id,
                    'code' => $code,
                    'issued_at' => $issued->created_at?->toRfc3339String() ?? now()->toRfc3339String(),
                    'amount' => (data_get($input, 'voucher_type') === 'payable'
                        || data_get($input, 'metadata.flow_type') === 'collectible')
                        ? data_get($instructions->toArray(), 'target_amount')
                        : data_get($instructions->toArray(), 'cash.amount'),
                    'currency' => data_get($instructions->toArray(), 'cash.currency'),
                    'links' => [
                        'redeem' => $redeemUrl,
                        'redeem_path' => $redeemPath,
                        ...$paymentLinks,
                    ],
                    'metadata' => $issued->metadata ?? null,
                ];
            });
        } catch (\Throwable $exception) {
            foreach ($createdFiles as $stored) {
                Storage::disk($stored['disk'])->delete($stored['path']);
            }
            throw $exception;
        } finally {
            if ($previousUser instanceof Authenticatable) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetGuards();
            }
        }
    }

    protected function persistNamedSliceMetadata(mixed $voucher, array $input): void
    {
        $slices = data_get($input, 'metadata.custom.named_slices');

        if (! is_array($slices) || $slices === []) {
            return;
        }

        $metadata = is_array($voucher->metadata ?? null) ? $voucher->metadata : [];

        data_set($metadata, 'instructions.metadata.custom.named_slices', $slices);
        data_set($metadata, 'instructions.metadata.custom.named_slice_policy', data_get($input, 'metadata.custom.named_slice_policy', [
            'mode' => 'named',
            'selection' => 'one_or_many',
            'enforced' => true,
        ]));

        $voucher->forceFill([
            'metadata' => $metadata,
        ])->save();

        $voucher->refresh();
    }

    /**
     * Preserve extension metadata accepted by the issuance contract but omitted
     * while the nested Voucher metadata DTO is reconstructed.
     *
     * @param  array<string, mixed>  $input
     */
    protected function restoreCustomMetadata(
        VoucherInstructionsData $instructions,
        array $input,
    ): void {
        $custom = data_get($input, 'metadata.custom');

        if (! is_array($custom) || $custom === []) {
            return;
        }

        $instructions->metadata ??= VoucherMetadataData::from([]);
        $existing = is_array($instructions->metadata->custom)
            ? $instructions->metadata->custom
            : [];
        $instructions->metadata->custom = array_replace_recursive($existing, $custom);
    }

    protected function redeemPath(string $code): string
    {
        if (Route::has('x-change.claim.show')) {
            return route('x-change.claim.show', ['code' => $code], false);
        }

        $path = trim((string) config('x-change.routes.paths.redeem', 'disburse'), '/');

        return '/'.$path.'?code='.urlencode($code);
    }

    protected function redeemUrl(string $path): string
    {
        $base = rtrim((string) config('app.url', ''), '/');

        return $base === '' ? $path : $base.$path;
    }

    protected function withCollectionWalletContext(Authenticatable $issuer, array $input): array
    {
        if (! $this->requiresCollectionWallet($input)) {
            return $input;
        }

        data_set($input, 'metadata.issuer_id', (string) $issuer->getAuthIdentifier());
        data_set(
            $input,
            'metadata.collection_wallet_id',
            (string) $this->wallets->resolveForUser($issuer)->getKey(),
        );

        return $input;
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    protected function assertCollectionWalletPresentForCollectible(array $input): void
    {
        if (! $this->requiresCollectionWallet($input)) {
            return;
        }

        if (filled(data_get($input, 'metadata.collection_wallet_id'))) {
            return;
        }

        throw ValidationException::withMessages([
            'metadata.collection_wallet_id' => 'A collection wallet is required for payable Pay Codes.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function requiresCollectionWallet(array $input): bool
    {
        return in_array(data_get($input, 'voucher_type'), ['payable', 'settlement'], true)
            || data_get($input, 'metadata.flow_type') === 'collectible';
    }

    /**
     * Preserve the server-prepared descriptor that the Voucher DTO's validation
     * boundary intentionally omits from arbitrary issuance input.
     *
     * @param  array<string, mixed>  $input
     */
    protected function restorePreparedSplashArtworkSnapshot(
        VoucherInstructionsData $instructions,
        array $input,
    ): void {
        $snapshot = data_get(
            $input,
            'metadata.custom.rider_splash_artwork',
        );

        if (! is_array($snapshot) || $snapshot === []) {
            return;
        }

        $metadata = $instructions->metadata
            ?? VoucherMetadataData::from([]);
        $custom = is_array($metadata->custom) ? $metadata->custom : [];
        $custom['rider_splash_artwork'] = $snapshot;
        $metadata->custom = $custom;
        $instructions->metadata = $metadata;
    }
}
