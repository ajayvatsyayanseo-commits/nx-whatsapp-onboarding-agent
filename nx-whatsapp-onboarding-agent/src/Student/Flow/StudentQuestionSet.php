<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Student\Flow;

use NxTutors\WhatsAppOnboarding\Conversation\StateMachine\ConversationState;

final class StudentQuestionSet
{
    public function questionFor(ConversationState $state): string
    {
        return [
            ConversationState::StudentName->value => __('nx-whatsapp-onboarding::student.name'),
            ConversationState::StudentEmail->value => __('nx-whatsapp-onboarding::student.email'),
            ConversationState::StudentDob->value => __('nx-whatsapp-onboarding::student.dob'),
            ConversationState::StudentGender->value => __('nx-whatsapp-onboarding::student.gender'),
            ConversationState::StudentClassType->value => __('nx-whatsapp-onboarding::student.class_type'),
            ConversationState::StudentForClass->value => __('nx-whatsapp-onboarding::student.for_class'),
            ConversationState::StudentBudget->value => __('nx-whatsapp-onboarding::student.budget'),
            ConversationState::StudentAddress->value => __('nx-whatsapp-onboarding::student.address'),
            ConversationState::StudentCity->value => __('nx-whatsapp-onboarding::student.city'),
            ConversationState::StudentDistrict->value => __('nx-whatsapp-onboarding::student.district'),
            ConversationState::StudentState->value => __('nx-whatsapp-onboarding::student.state'),
            ConversationState::StudentPincode->value => __('nx-whatsapp-onboarding::student.pincode'),
            ConversationState::StudentProfileDesc->value => __('nx-whatsapp-onboarding::student.profile_desc'),
        ][$state->value] ?? __('nx-whatsapp-onboarding::common.reply_help');
    }
}
