<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tutor\Flow;

use NxTutors\WhatsAppOnboarding\Conversation\StateMachine\ConversationState;

final class TutorFlowDefinition
{
    /** @return list<ConversationState> */
    public function states(): array
    {
        return [
            ConversationState::TutorName,
            ConversationState::TutorEmail,
            ConversationState::TutorDob,
            ConversationState::TutorGender,
            ConversationState::TutorEducation,
            ConversationState::TutorOtherEducation,
            ConversationState::TutorExperience,
            ConversationState::TutorDegreeUpload,
            ConversationState::TutorClassType,
            ConversationState::TutorForClass,
            ConversationState::TutorBudgetOrFee,
            ConversationState::TutorAddress,
            ConversationState::TutorCity,
            ConversationState::TutorDistrict,
            ConversationState::TutorState,
            ConversationState::TutorPincode,
            ConversationState::TutorDocumentType,
            ConversationState::TutorDocumentNumber,
            ConversationState::TutorFrontImageUpload,
            ConversationState::TutorBackImageUpload,
            ConversationState::TutorProfileTitle,
            ConversationState::TutorProfileDesc,
            ConversationState::TutorProDesc,
        ];
    }

    public function fieldFor(ConversationState $state): ?string
    {
        return [
            ConversationState::TutorName->value => 'name',
            ConversationState::TutorEmail->value => 'email',
            ConversationState::TutorDob->value => 'dob',
            ConversationState::TutorGender->value => 'gender',
            ConversationState::TutorEducation->value => 'education',
            ConversationState::TutorOtherEducation->value => 'other_education',
            ConversationState::TutorExperience->value => 'experience',
            ConversationState::TutorDegreeUpload->value => 'degree',
            ConversationState::TutorClassType->value => 'class_type',
            ConversationState::TutorForClass->value => 'for_class',
            ConversationState::TutorBudgetOrFee->value => 'budget',
            ConversationState::TutorAddress->value => 'address',
            ConversationState::TutorCity->value => 'city',
            ConversationState::TutorDistrict->value => 'district',
            ConversationState::TutorState->value => 'state',
            ConversationState::TutorPincode->value => 'pincode',
            ConversationState::TutorDocumentType->value => 'document_type',
            ConversationState::TutorDocumentNumber->value => 'document_number',
            ConversationState::TutorFrontImageUpload->value => 'front_image',
            ConversationState::TutorBackImageUpload->value => 'back_image',
            ConversationState::TutorProfileTitle->value => 'profile',
            ConversationState::TutorProfileDesc->value => 'profile_desc',
            ConversationState::TutorProDesc->value => 'pro_desc',
        ][$state->value] ?? null;
    }

    public function nextAfter(ConversationState $state): ConversationState
    {
        $states = $this->states();
        foreach ($states as $index => $candidate) {
            if ($candidate === $state) {
                return $states[$index + 1] ?? ConversationState::WaitingReviewConfirmation;
            }
        }

        return ConversationState::HumanHandoff;
    }

    public function previousBefore(ConversationState $state): ?ConversationState
    {
        $states = $this->states();
        foreach ($states as $index => $candidate) {
            if ($candidate === $state) {
                return $states[$index - 1] ?? null;
            }
        }

        return null;
    }

    public function stateForField(string $field): ?ConversationState
    {
        foreach ($this->states() as $state) {
            if ($this->fieldFor($state) === $field) {
                return $state;
            }
        }

        return null;
    }

    public function isOptional(string $field): bool
    {
        return in_array($field, [
            'dob',
            'gender',
            'other_education',
            'degree',
            'budget',
            'address',
            'city',
            'district',
            'state',
            'pincode',
            'front_image',
            'back_image',
        ], true);
    }

    /** @return list<string> */
    public function requiredFields(): array
    {
        return ['phone', 'name', 'email', 'role', 'education', 'experience', 'for_class', 'class_type', 'document_type', 'document_number', 'profile', 'profile_desc', 'pro_desc'];
    }
}
