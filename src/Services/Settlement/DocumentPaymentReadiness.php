<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use InvalidArgumentException;

/** Evaluates document facts; callers must supply authoritative envelope data. */
final class DocumentPaymentReadiness
{
    /**
     * @param  list<array{type: string, timing: string, requires_acceptance: bool, contributor: string}>  $requirements
     * @param  list<array{type: string, review_status: string, contributor: string|null}>  $documents
     * @return list<string> Document types that prevent payment.
     */
    public function missingForPayment(array $requirements, array $documents): array
    {
        $missing = [];

        foreach ($requirements as $requirement) {
            $type = $requirement['type'] ?? null;
            $timing = $requirement['timing'] ?? null;
            $acceptance = $requirement['requires_acceptance'] ?? null;
            $contributor = $requirement['contributor'] ?? null;

            if (! is_string($type) || trim($type) === ''
                || ! in_array($timing, ['optional', 'before_payment', 'later_review'], true)
                || ! is_bool($acceptance)
                || ! in_array($contributor, ['payer', 'claimant', 'invited'], true)) {
                throw new InvalidArgumentException('Invalid document requirement.');
            }

            if ($timing !== 'before_payment') {
                continue;
            }

            $satisfied = false;
            foreach ($documents as $document) {
                $status = $document['review_status'] ?? null;
                if (($document['type'] ?? null) === $type
                    && ($document['contributor'] ?? null) === $contributor
                    && ($acceptance ? $status === 'accepted' : in_array($status, ['pending', 'accepted'], true))) {
                    $satisfied = true;
                    break;
                }
            }

            if (! $satisfied) {
                $missing[] = $type;
            }
        }

        return array_values(array_unique($missing));
    }
}
