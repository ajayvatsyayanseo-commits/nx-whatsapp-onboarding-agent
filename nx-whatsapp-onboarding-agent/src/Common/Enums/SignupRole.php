<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Common\Enums;

enum SignupRole: string
{
    case Student = 'student';
    case Tutor = 'tutor';
}
