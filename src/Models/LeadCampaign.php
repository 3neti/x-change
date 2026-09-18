<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LBHurtado\XCampaign\Models\EndpointCampaign;

/**
 * Retains the existing model identity and the x-change template relationship.
 */
class LeadCampaign extends EndpointCampaign
{
    /**
     * @return BelongsTo<PayCodeTemplate, $this>
     */
    public function payCodeTemplate(): BelongsTo
    {
        return $this->belongsTo(PayCodeTemplate::class, 'pay_code_template_id');
    }
}
