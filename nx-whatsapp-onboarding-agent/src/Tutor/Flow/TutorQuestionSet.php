<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tutor\Flow;

use NxTutors\WhatsAppOnboarding\Conversation\StateMachine\ConversationState;

final class TutorQuestionSet
{
    public function questionFor(ConversationState $state): string
    {
        return [
            ConversationState::TutorName->value => __('nx-whatsapp-onboarding::tutor.name'),
            ConversationState::TutorEmail->value => __('nx-whatsapp-onboarding::tutor.email'),
            ConversationState::TutorDob->value => __('nx-whatsapp-onboarding::tutor.dob'),
            ConversationState::TutorGender->value => __('nx-whatsapp-onboarding::tutor.gender'),
            ConversationState::TutorEducation->value => __('nx-whatsapp-onboarding::tutor.education'),
            ConversationState::TutorOtherEducation->value => __('nx-whatsapp-onboarding::tutor.other_education'),
            ConversationState::TutorExperience->value => __('nx-whatsapp-onboarding::tutor.experience'),
            ConversationState::TutorDegreeUpload->value => __('nx-whatsapp-onboarding::tutor.degree'),
            ConversationState::TutorClassType->value => __('nx-whatsapp-onboarding::tutor.class_type'),
            ConversationState::TutorForClass->value => __('nx-whatsapp-onboarding::tutor.for_class'),
            ConversationState::TutorBudgetOrFee->value => __('nx-whatsapp-onboarding::tutor.budget'),
            ConversationState::TutorAddress->value => __('nx-whatsapp-onboarding::tutor.address'),
            ConversationState::TutorCity->value => __('nx-whatsapp-onboarding::tutor.city'),
            ConversationState::TutorDistrict->value => __('nx-whatsapp-onboarding::tutor.district'),
            ConversationState::TutorState->value => __('nx-whatsapp-onboarding::tutor.state'),
            ConversationState::TutorPincode->value => __('nx-whatsapp-onboarding::tutor.pincode'),
            ConversationState::TutorDocumentType->value => __('nx-whatsapp-onboarding::tutor.document_type'),
            ConversationState::TutorDocumentNumber->value => __('nx-whatsapp-onboarding::tutor.document_number'),
            ConversationState::TutorFrontImageUpload->value => __('nx-whatsapp-onboarding::tutor.front_image'),
            ConversationState::TutorBackImageUpload->value => __('nx-whatsapp-onboarding::tutor.back_image'),
            ConversationState::TutorProfileTitle->value => __('nx-whatsapp-onboarding::tutor.profile'),
            ConversationState::TutorProfileDesc->value => __('nx-whatsapp-onboarding::tutor.profile_desc'),
            ConversationState::TutorProDesc->value => __('nx-whatsapp-onboarding::tutor.pro_desc'),
        ][$state->value] ?? __('nx-whatsapp-onboarding::common.reply_help');
    }
}
