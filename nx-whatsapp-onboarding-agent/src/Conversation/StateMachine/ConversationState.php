<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\StateMachine;

enum ConversationState: string
{
    case New = 'NEW';
    case WaitingRoleSelection = 'WAITING_ROLE_SELECTION';
    case StudentCollecting = 'STUDENT_COLLECTING';
    case TutorCollecting = 'TUTOR_COLLECTING';
    case WaitingReviewConfirmation = 'WAITING_REVIEW_CONFIRMATION';
    case WaitingTermsAcceptance = 'WAITING_TERMS_ACCEPTANCE';
    case WaitingOtp = 'WAITING_OTP';
    case ReadyToCreateProfile = 'READY_TO_CREATE_PROFILE';
    case CreatingProfile = 'CREATING_PROFILE';
    case Completed = 'COMPLETED';
    case HumanHandoff = 'HUMAN_HANDOFF';
    case Cancelled = 'CANCELLED';
    case Expired = 'EXPIRED';
    case ErrorRecoverable = 'ERROR_RECOVERABLE';
    case ErrorFinal = 'ERROR_FINAL';

    case StudentName = 'STUDENT_NAME';
    case StudentEmail = 'STUDENT_EMAIL';
    case StudentDob = 'STUDENT_DOB';
    case StudentGender = 'STUDENT_GENDER';
    case StudentClassType = 'STUDENT_CLASS_TYPE';
    case StudentForClass = 'STUDENT_FOR_CLASS';
    case StudentBudget = 'STUDENT_BUDGET';
    case StudentAddress = 'STUDENT_ADDRESS';
    case StudentCity = 'STUDENT_CITY';
    case StudentDistrict = 'STUDENT_DISTRICT';
    case StudentState = 'STUDENT_STATE';
    case StudentPincode = 'STUDENT_PINCODE';
    case StudentProfileDesc = 'STUDENT_PROFILE_DESC';

    case TutorName = 'TUTOR_NAME';
    case TutorEmail = 'TUTOR_EMAIL';
    case TutorDob = 'TUTOR_DOB';
    case TutorGender = 'TUTOR_GENDER';
    case TutorEducation = 'TUTOR_EDUCATION';
    case TutorOtherEducation = 'TUTOR_OTHER_EDUCATION';
    case TutorExperience = 'TUTOR_EXPERIENCE';
    case TutorDegreeUpload = 'TUTOR_DEGREE_UPLOAD';
    case TutorClassType = 'TUTOR_CLASS_TYPE';
    case TutorForClass = 'TUTOR_FOR_CLASS';
    case TutorBudgetOrFee = 'TUTOR_BUDGET_OR_FEE';
    case TutorAddress = 'TUTOR_ADDRESS';
    case TutorCity = 'TUTOR_CITY';
    case TutorDistrict = 'TUTOR_DISTRICT';
    case TutorState = 'TUTOR_STATE';
    case TutorPincode = 'TUTOR_PINCODE';
    case TutorDocumentType = 'TUTOR_DOCUMENT_TYPE';
    case TutorDocumentNumber = 'TUTOR_DOCUMENT_NUMBER';
    case TutorFrontImageUpload = 'TUTOR_FRONT_IMAGE_UPLOAD';
    case TutorBackImageUpload = 'TUTOR_BACK_IMAGE_UPLOAD';
    case TutorProfileTitle = 'TUTOR_PROFILE_TITLE';
    case TutorProfileDesc = 'TUTOR_PROFILE_DESC';
    case TutorProDesc = 'TUTOR_PRO_DESC';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::HumanHandoff,
            self::Cancelled,
            self::Expired,
            self::ErrorFinal,
        ], true);
    }

    public function isStudentField(): bool
    {
        return str_starts_with($this->value, 'STUDENT_');
    }

    public function isTutorField(): bool
    {
        return str_starts_with($this->value, 'TUTOR_');
    }
}
