<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\Services;

enum HandoffReasonCode: string
{
    case UserRequested = 'USER_REQUESTED';
    case RepeatedInvalidInput = 'REPEATED_INVALID_INPUT';
    case DuplicateAccount = 'DUPLICATE_ACCOUNT';
    case DocumentReview = 'DOCUMENT_REVIEW';
    case PaymentOrLegal = 'PAYMENT_OR_LEGAL';
    case SafetyRisk = 'SAFETY_RISK';
    case SystemFailure = 'SYSTEM_FAILURE';
    case LowConfidence = 'LOW_CONFIDENCE';
}
