<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tutor\Services;

use NxTutors\WhatsAppOnboarding\Profile\Models\Register;

final class TutorDocumentReviewService
{
    public function queueReview(Register $register): void
    {
        // Hook for EventBridge/SQS integration when document review workflows are connected.
    }
}
