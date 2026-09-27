<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

final class CommercialInvoicingAuthorityInspector
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $authority = (array) config('x-change.commercial.invoicing_authority', []);
        $taxRegistration = mb_strtolower(trim((string) ($authority['tax_registration'] ?? '')));
        $documentType = mb_strtolower(trim((string) ($authority['document_type'] ?? '')));
        $expectedDocumentType = match ($taxRegistration) {
            'vat' => 'vat_invoice',
            'non_vat' => 'non_vat_invoice',
            default => null,
        };
        $identityComplete = collect([
            'issuer_legal_name',
            'issuer_tin',
            'issuer_registered_address',
            'authority_reference',
            'tax_profile_reference',
            'effective_at',
        ])->every(static fn (string $key): bool => filled($authority[$key] ?? null));
        $documentMatchesRegistration = $expectedDocumentType !== null
            && $documentType === $expectedDocumentType;
        $ready = ($authority['status'] ?? null) === 'approved'
            && ($authority['jurisdiction'] ?? null) === 'PH'
            && $identityComplete
            && $documentMatchesRegistration
            && ($authority['invoice_every_charge'] ?? null) === true;

        return [
            'schema' => 'x-change.commercial-invoicing-authority-status.v1',
            'status' => $authority['status'] ?? null,
            'jurisdiction' => $authority['jurisdiction'] ?? null,
            'ready' => $ready,
            'identity_complete' => $identityComplete,
            'issuer_reference_hash' => filled($authority['issuer_legal_name'] ?? null)
                ? hash('sha256', trim((string) $authority['issuer_legal_name']))
                : null,
            'tin_configured' => filled($authority['issuer_tin'] ?? null),
            'registered_address_configured' => filled($authority['issuer_registered_address'] ?? null),
            'tax_registration' => $taxRegistration !== '' ? $taxRegistration : null,
            'document_type' => $documentType !== '' ? $documentType : null,
            'document_matches_registration' => $documentMatchesRegistration,
            'authority_reference' => $authority['authority_reference'] ?? null,
            'tax_profile_reference' => $authority['tax_profile_reference'] ?? null,
            'effective_at' => $authority['effective_at'] ?? null,
            'invoice_every_charge' => ($authority['invoice_every_charge'] ?? null) === true,
            'message' => $ready
                ? 'The Philippine invoicing authority profile is complete and approved.'
                : 'Customer charging remains blocked until the host invoicing entity and BIR authority are complete and approved.',
        ];
    }
}
