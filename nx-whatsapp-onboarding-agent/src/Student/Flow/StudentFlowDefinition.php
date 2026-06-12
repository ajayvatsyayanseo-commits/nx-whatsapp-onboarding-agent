<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Student\Flow;

use NxTutors\WhatsAppOnboarding\Conversation\StateMachine\ConversationState;

final class StudentFlowDefinition
{
    /** @return list<ConversationState> */
    public function states(): array
    {
        return [
            ConversationState::StudentName,
            ConversationState::StudentEmail,
            ConversationState::StudentDob,
            ConversationState::StudentGender,
            ConversationState::StudentClassType,
            ConversationState::StudentForClass,
            ConversationState::StudentBudget,
            ConversationState::StudentAddress,
            ConversationState::StudentCity,
            ConversationState::StudentDistrict,
            ConversationState::StudentState,
            ConversationState::StudentPincode,
            ConversationState::StudentProfileDesc,
        ];
    }

    public function fieldFor(ConversationState $state): ?string
    {
        return [
            ConversationState::StudentName->value => 'name',
            ConversationState::StudentEmail->value => 'email',
            ConversationState::StudentDob->value => 'dob',
            ConversationState::StudentGender->value => 'gender',
            ConversationState::StudentClassType->value => 'class_type',
            ConversationState::StudentForClass->value => 'for_class',
            ConversationState::StudentBudget->value => 'budget',
            ConversationState::StudentAddress->value => 'address',
            ConversationState::StudentCity->value => 'city',
            ConversationState::StudentDistrict->value => 'district',
            ConversationState::StudentState->value => 'state',
            ConversationState::StudentPincode->value => 'pincode',
            ConversationState::StudentProfileDesc->value => 'profile_desc',
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
        return in_array($field, ['budget', 'address', 'city', 'district', 'state', 'pincode', 'profile_desc', 'dob', 'gender'], true);
    }

    /** @return list<string> */
    public function requiredFields(): array
    {
        return ['phone', 'name', 'email', 'role', 'for_class', 'class_type'];
    }
}
