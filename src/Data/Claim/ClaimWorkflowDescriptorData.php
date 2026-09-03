<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use LBHurtado\XChange\Enums\ClaimAuthenticationMode;
use Spatie\LaravelData\Data;

final class ClaimWorkflowDescriptorData extends Data
{
    public bool $requires_authenticated_officer;

    /**
     * @param  array<string, scalar|null>  $review
     * @param  list<string>  $required_claim_fields
     */
    public function __construct(
        public string $key,
        public bool $requires_mobile,
        public bool $requires_destination,
        public bool $requires_amount,
        public string $title,
        public string $description,
        public string $confirmation_label,
        public ClaimAuthenticationMode $authentication_mode = ClaimAuthenticationMode::None,
        public array $required_claim_fields = [],
        public bool $skip_form_flow_splash = false,
        public array $review = [],
        public ?string $confirmation_title = null,
    ) {
        $this->requires_authenticated_officer = $this->authentication_mode
            === ClaimAuthenticationMode::AuthenticatedOfficer;
    }
}
