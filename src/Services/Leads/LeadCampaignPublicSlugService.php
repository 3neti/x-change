<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Leads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LBHurtado\XChange\Models\LeadCampaign;

final class LeadCampaignPublicSlugService
{
    public function merchantSlug(string $merchantDisplayName, Model $owner): string
    {
        $base = $this->baseSlug($merchantDisplayName, 'merchant-'.$owner->getKey());
        $candidate = $base;
        $suffix = 2;

        while (
            LeadCampaign::query()
                ->where('merchant_slug', $candidate)
                ->where(function ($query) use ($owner): void {
                    $query
                        ->where('owner_type', '!=', $owner->getMorphClass())
                        ->orWhere('owner_id', '!=', (string) $owner->getKey());
                })
                ->exists()
        ) {
            $candidate = $this->appendSuffix($base, (string) $suffix);
            $suffix++;
        }

        return $candidate;
    }

    public function endpointSlug(string $value): string
    {
        return $this->baseSlug($value, 'lead-campaign');
    }

    public function availableEndpointSlug(string $merchantSlug, string $endpointSlug): string
    {
        $base = $this->baseSlug($endpointSlug, 'lead-campaign');
        $candidate = $base;
        $suffix = 2;

        while (
            LeadCampaign::query()
                ->where('merchant_slug', $merchantSlug)
                ->where('endpoint_slug', $candidate)
                ->exists()
        ) {
            $candidate = $this->appendSuffix($base, (string) $suffix);
            $suffix++;
        }

        return $candidate;
    }

    private function baseSlug(string $value, string $fallback): string
    {
        $slug = Str::slug($value);

        if ($slug === '') {
            $slug = Str::slug($fallback);
        }

        return Str::limit($slug, 120, '');
    }

    private function appendSuffix(string $base, string $suffix): string
    {
        return Str::limit($base, max(1, 119 - strlen($suffix)), '').'-'.$suffix;
    }
}
