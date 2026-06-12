<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\Services;

final readonly class IntentDetector
{
    public function __construct(private InputNormalizer $normalizer)
    {
    }

    public function isSignupIntent(?string $text): bool
    {
        $text = $this->normalizer->normalize($text);

        return str_contains($text, 'signup')
            || str_contains($text, 'sign up')
            || str_contains($text, 'register')
            || str_contains($text, 'registration');
    }

    public function detectRole(?string $text): ?string
    {
        $text = $this->normalizer->normalize($text);
        if ($text === '1' || str_contains($text, 'student')) {
            return 'student';
        }

        if ($text === '2' || str_contains($text, 'tutor') || str_contains($text, 'teacher')) {
            return 'tutor';
        }

        return null;
    }

    public function isAgreement(?string $text): bool
    {
        return $this->normalizer->normalize($text) === 'i agree';
    }

    public function isConfirmation(?string $text): bool
    {
        return in_array($this->normalizer->normalize($text), ['yes', 'y', 'confirm', 'ok', 'okay'], true);
    }
}
