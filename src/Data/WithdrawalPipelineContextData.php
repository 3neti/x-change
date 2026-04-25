<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;

class WithdrawalPipelineContextData
{
    public function __construct(
        public Voucher $voucher,
        public array $payload,
        public ?Contact $contact = null,
    ) {}

    public function withContact(Contact $contact): self
    {
        $this->contact = $contact;

        return $this;
    }
}
