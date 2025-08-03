<?php

namespace App\Data;

use Illuminate\Foundation\Inspiring;
use Spatie\LaravelData\Data;
use Illuminate\Support\Str;

class MessageData extends Data
{
    public string $from = '';
    public string $to = '';

    public function getFrom(): string
    {
        return $this->from;
    }

    public function setFrom(string $from): static
    {
        $this->from = $from;

        return $this;
    }

    public function getTo(): string
    {
        return $this->to;
    }

    public function setTo(string $to): static
    {
        $this->to = $to;

        return $this;
    }

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

    public static function inspiring(): static
    {
        $subject = 'Quote';
        $title = '';
        [$body, $from] = str(Inspiring::quotes()->random())->explode('-');
        $body = Str::wordWrap($body, characters: 40, break: "\n");
        $closing = 'Ayus!';
        $message = static::from(compact('subject','title', 'body', 'closing'));
        $message->setFrom($from);

        return $message;
    }
}
