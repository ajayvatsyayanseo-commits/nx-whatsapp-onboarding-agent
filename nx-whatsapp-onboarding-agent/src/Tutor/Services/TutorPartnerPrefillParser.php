<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tutor\Services;

final class TutorPartnerPrefillParser
{
    /** @return array<string, string> */
    public function parse(?string $text): array
    {
        $text = (string) $text;
        if (! str_contains(mb_strtolower($text), 'tutor partner')) {
            return [];
        }

        $map = [
            'name' => 'name',
            'subjects' => 'for_class',
            'classes' => 'for_class',
            'experience' => 'experience',
            'location' => 'city',
            'preferred mode' => 'class_type',
            'hourly rate' => 'budget',
            'availability' => 'availability',
            'whatsapp number' => 'form_phone',
        ];

        $fields = ['role' => 'tutor', '_prefill_source' => 'website_prefill_untrusted'];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $target = $map[mb_strtolower($key)] ?? null;
            if ($target === null || $value === '') {
                continue;
            }

            $fields[$target] = isset($fields[$target]) && $target === 'for_class'
                ? $fields[$target] . '; ' . $value
                : $value;
        }

        return $fields;
    }
}
