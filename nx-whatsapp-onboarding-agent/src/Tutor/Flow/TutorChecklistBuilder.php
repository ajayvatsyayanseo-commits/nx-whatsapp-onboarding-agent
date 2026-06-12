<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tutor\Flow;

final class TutorChecklistBuilder
{
    public function build(): string
    {
        return "Next steps:\n1. Login to dashboard\n2. Complete profile photo\n3. Check document verification status\n4. Add courses/subjects\n5. Set availability\n6. Respond to student enquiries";
    }
}
