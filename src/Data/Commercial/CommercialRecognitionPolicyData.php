<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Commercial;

use JsonException;

final readonly class CommercialRecognitionPolicyData
{
    /**
     * @param  list<string>  $billableEventReferences
     */
    public function __construct(
        public string $reference,
        public int $version,
        public array $billableEventReferences,
        public string $trigger,
        public string $timing,
    ) {
        if (trim($this->reference) === '' || $this->version < 1) {
            throw new \DomainException('A recognition policy requires a reference and positive version.');
        }

        if ($this->billableEventReferences === []
            || collect($this->billableEventReferences)->contains(
                static fn (mixed $reference): bool => ! is_string($reference) || trim($reference) === '',
            )) {
            throw new \DomainException('A recognition policy requires explicit Billable Event references.');
        }

        if (count(array_unique($this->billableEventReferences)) !== count($this->billableEventReferences)) {
            throw new \DomainException('Recognition policy Billable Event references must be unique.');
        }

        if (trim($this->trigger) === '' || ! in_array($this->timing, ['immediate', 'deferred'], true)) {
            throw new \DomainException('A recognition policy requires a trigger and supported timing.');
        }
    }

    /**
     * @return array{
     *     reference:string,
     *     version:int,
     *     billable_event_references:list<string>,
     *     trigger:string,
     *     timing:string
     * }
     */
    public function toArray(): array
    {
        return [
            'reference' => trim($this->reference),
            'version' => $this->version,
            'billable_event_references' => array_values($this->billableEventReferences),
            'trigger' => trim($this->trigger),
            'timing' => $this->timing,
        ];
    }

    /** @throws JsonException */
    public function snapshotHash(): string
    {
        return hash('sha256', json_encode(
            $this->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }
}
