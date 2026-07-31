<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XFeedback\Data\FeedbackDeliveryRecordData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

interface FeedbackDeliveryJournalWriterContract
{
    public function writeCreated(
        FeedbackIntentData $intent,
        FeedbackRecipientData $recipient,
        string $channel,
        string $deliveryKey,
        int $attempt,
    ): ExecutionJournalEntry;

    public function writeRecorded(FeedbackDeliveryRecordData $record): ExecutionJournalEntry;
}
