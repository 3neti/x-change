<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Illuminate\Support\Str;

class MessageData extends Data
{
    public function __construct(
        public string $subject,
        public string $title,
        public string $body,
        public string $closing,
    ) {}

    public static function tryFrom(...$payloads): static|null
    {
        try {
            return self::from(...$payloads);
        } catch (\Throwable) {
            return null;
        }
    }

    public function withWrappedBody(int $characters = 40, string $break = "\n", bool $force = false): static
    {
        $hasLineBreaks = Str::contains($this->body, ["\n", "\r"]);

        $body = $hasLineBreaks && !$force
            ? $this->body
            : Str::wordWrap($this->body, characters: $characters, break: $break);

        return new static(
            $this->subject,
            $this->title,
            $body,
            $this->closing,
        );
    }
}
