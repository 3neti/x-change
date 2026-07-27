<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LBHurtado\XChange\Enums\FundingRequestType;
use LBHurtado\XChange\Enums\FundingTransferWindow;

class CreateCockpitFundingRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'funding_type' => mb_strtolower(trim((string) $this->input(
                'funding_type',
                FundingRequestType::Unspecified->value,
            ))),
            'currency' => mb_strtoupper(trim((string) $this->input('currency', 'PHP'))),
            'description' => trim((string) $this->input(
                'description',
                'Account funding requested by the Account holder.',
            )),
            'external_reference' => trim((string) $this->input('external_reference')),
            'transfer_window' => mb_strtolower(trim((string) $this->input(
                'transfer_window',
                FundingTransferWindow::Recent->value,
            ))),
            'requester_notes' => trim((string) $this->input('requester_notes')),
            'idempotency_key' => trim((string) $this->input('idempotency_key')),
            'evidence_document_type' => mb_strtoupper(trim(
                (string) $this->input(
                    'evidence_document_type',
                    'SUPPORTING_DOCUMENT',
                ),
            )),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'funding_type' => [
                'required',
                Rule::enum(FundingRequestType::class),
            ],
            'requested_value_minor' => ['required', 'integer', 'min:1', 'max:999999999999999'],
            'currency' => ['required', 'string', 'size:3'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'external_reference' => ['nullable', 'string', 'max:191'],
            'transfer_window' => [
                Rule::requiredIf(
                    fn (): bool => $this->input('funding_type')
                        === FundingRequestType::BankTransfer->value,
                ),
                'nullable',
                Rule::enum(FundingTransferWindow::class),
            ],
            'occurred_on' => ['nullable', 'date', 'before_or_equal:today'],
            'requester_notes' => ['nullable', 'string', 'max:4000'],
            'idempotency_key' => ['required', 'string', 'max:191'],
            'approved_value_minor' => ['prohibited'],
            'recognized_value_minor' => ['prohibited'],
            'provider_transaction_id' => ['prohibited'],
            'credit_amount_minor' => ['prohibited'],
            'provider_payload' => ['prohibited'],
            'evidence_document_type' => [
                'nullable',
                'required_with:evidence_document',
                Rule::in([
                    'BANK_TRANSFER_PROOF',
                    'CUSTODY_RECEIPT',
                    'ASSET_PHOTO',
                    'OWNERSHIP_DOCUMENT',
                    'VALUATION_DOCUMENT',
                    'SUPPORTING_DOCUMENT',
                ]),
            ],
            'evidence_document' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
                'max:10240',
            ],
            'attachment' => ['prohibited'],
        ];
    }
}
