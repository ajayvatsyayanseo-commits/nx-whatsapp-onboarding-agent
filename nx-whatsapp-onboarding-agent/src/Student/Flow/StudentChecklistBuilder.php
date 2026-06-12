<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Student\Flow;

final class StudentChecklistBuilder
{
    public function build(): string
    {
        return "Next steps:\n1. Login to dashboard\n2. Complete profile photo\n3. Add learning goals\n4. Browse tutors\n5. Book demo/session";
    }
}
