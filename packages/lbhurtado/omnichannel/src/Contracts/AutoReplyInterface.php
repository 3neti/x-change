<?php

namespace LBHurtado\OmniChannel\Contracts;

interface AutoReplyInterface
{
    /**
     * Handles auto-replies for SMS commands.
     *
     * @param string $from    The sender's mobile number.
     * @param string $to      The receiver's mobile number (shortcode).
     * @param string $message The received SMS message.
     * @return string|null    The response message or null to allow further processing.
     */
    public function reply(string $from, string $to, string $message): ?string;
}
