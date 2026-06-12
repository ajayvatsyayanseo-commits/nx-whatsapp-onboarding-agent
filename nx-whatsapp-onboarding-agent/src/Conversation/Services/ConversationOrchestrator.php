<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\Services;

use Illuminate\Support\Facades\DB;
use NxTutors\WhatsAppOnboarding\Contracts\HumanHandoffInterface;
use NxTutors\WhatsAppOnboarding\Contracts\InputExtractorInterface;
use NxTutors\WhatsAppOnboarding\Contracts\MediaStorageInterface;
use NxTutors\WhatsAppOnboarding\Contracts\MessageSenderInterface;
use NxTutors\WhatsAppOnboarding\Contracts\OtpServiceInterface;
use NxTutors\WhatsAppOnboarding\Contracts\StateRepositoryInterface;
use NxTutors\WhatsAppOnboarding\Conversation\Repositories\RedisStateCache;
use NxTutors\WhatsAppOnboarding\Conversation\StateMachine\ConversationState;
use NxTutors\WhatsAppOnboarding\Conversation\StateMachine\StateMachineEngine;
use NxTutors\WhatsAppOnboarding\Observability\Logging\OnboardingAuditLogger;
use NxTutors\WhatsAppOnboarding\Profile\DTO\ProfileCreationCommand;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingEvent;
use NxTutors\WhatsAppOnboarding\Profile\Repositories\RegisterRepository;
use NxTutors\WhatsAppOnboarding\Profile\Services\DashboardLinkService;
use NxTutors\WhatsAppOnboarding\Profile\Services\ProfileCreationDispatcher;
use NxTutors\WhatsAppOnboarding\Profile\Services\ProfileFeatureFlagService;
use NxTutors\WhatsAppOnboarding\Profile\Services\ProfileReadinessGuard;
use NxTutors\WhatsAppOnboarding\Security\AbuseDetection\AbuseDetector;
use NxTutors\WhatsAppOnboarding\Security\AbuseDetection\MediaValidationService;
use NxTutors\WhatsAppOnboarding\Security\Encryption\SensitiveDraftCrypt;
use NxTutors\WhatsAppOnboarding\Security\Guards\InputGuardrailService;
use NxTutors\WhatsAppOnboarding\Security\PiiMasking\PiiMasker;
use NxTutors\WhatsAppOnboarding\Student\Flow\StudentChecklistBuilder;
use NxTutors\WhatsAppOnboarding\Student\Flow\StudentFlowDefinition;
use NxTutors\WhatsAppOnboarding\Student\Flow\StudentQuestionSet;
use NxTutors\WhatsAppOnboarding\Student\Validators\StudentFieldValidator;
use NxTutors\WhatsAppOnboarding\Tutor\Flow\TutorChecklistBuilder;
use NxTutors\WhatsAppOnboarding\Tutor\Flow\TutorFlowDefinition;
use NxTutors\WhatsAppOnboarding\Tutor\Flow\TutorQuestionSet;
use NxTutors\WhatsAppOnboarding\Tutor\Validators\TutorFieldValidator;
use NxTutors\WhatsAppOnboarding\WhatsApp\DTO\InboundWhatsAppMessage;
use NxTutors\WhatsAppOnboarding\WhatsApp\Services\MetaPayloadParser;
use NxTutors\WhatsAppOnboarding\WhatsApp\Services\WhatsAppOptOutService;
use NxTutors\WhatsAppOnboarding\WhatsApp\Services\WhatsAppTemplateService;
use Throwable;

final readonly class ConversationOrchestrator
{
    public function __construct(
        private StateRepositoryInterface $states,
        private RedisStateCache $cache,
        private MessageSenderInterface $messages,
        private CommandDetector $commands,
        private IntentDetector $intentDetector,
        private InputExtractorInterface $extractor,
        private MediaStorageInterface $mediaStorage,
        private FieldNormalizer $fieldNormalizer,
        private MetaPayloadParser $payloadParser,
        private StateMachineEngine $engine,
        private StudentFlowDefinition $studentFlow,
        private TutorFlowDefinition $tutorFlow,
        private StudentQuestionSet $studentQuestions,
        private TutorQuestionSet $tutorQuestions,
        private StudentFieldValidator $studentValidator,
        private TutorFieldValidator $tutorValidator,
        private ReviewSummaryBuilder $reviewSummary,
        private TermsAcceptanceService $terms,
        private OtpServiceInterface $otp,
        private RegisterRepository $registers,
        private ProfileReadinessGuard $readiness,
        private ProfileCreationDispatcher $profileCreation,
        private ProfileFeatureFlagService $featureFlags,
        private DashboardLinkService $dashboardLinks,
        private StudentChecklistBuilder $studentChecklist,
        private TutorChecklistBuilder $tutorChecklist,
        private CircuitBreakerService $circuitBreaker,
        private HumanHandoffInterface $humanHandoff,
        private OnboardingAuditLogger $audit,
        private AbuseDetector $abuseDetector,
        private MediaValidationService $mediaValidator,
        private PiiMasker $masker,
        private SensitiveDraftCrypt $draftCrypt,
        private InputGuardrailService $inputGuardrails,
        private WhatsAppOptOutService $optOuts,
        private WhatsAppTemplateService $templates,
    ) {
    }

    public function handle(OnboardingEvent $event): void
    {
        $message = $this->payloadParser->parseFirstMessage($event->payload ?? []);
        if ($message === null) {
            return;
        }

        DB::transaction(function () use ($event, $message): void {
            $phone = $this->fieldNormalizer->phone($message->fromPhone);
            $requestIp = (string) ($event->payload['_request_metadata']['ip'] ?? '');
            if ($this->optOuts->isStopCommand($message->text)) {
                $this->optOuts->optOut($phone);
                $this->messages->sendText($phone, __('nx-whatsapp-onboarding::common.stopped'));
                return;
            }

            if (! $this->inputGuardrails->allowsMessage($message->text, $phone, $requestIp !== '' ? $requestIp : null)) {
                return;
            }

            $conversation = $this->states->findOpenByPhoneForUpdate($phone);

            if ($conversation === null) {
                $this->handleNewConversation($event, $message, $phone);
                return;
            }

            $event->forceFill(['onboarding_conversation_id' => $conversation->id])->save();
            $this->handleExistingConversation($conversation, $event, $message);
        }, 3);
    }

    private function handleNewConversation(OnboardingEvent $event, InboundWhatsAppMessage $message, string $phone): void
    {
        $text = $message->text ?? '';
        $detected = $this->commands->detect($text);

        if (! $this->featureFlags->signupEnabled()) {
            $this->messages->sendText($phone, __('nx-whatsapp-onboarding::common.signup_disabled'));
            return;
        }

        if ($detected['command'] !== ConversationCommand::Signup && ! $this->intentDetector->isSignupIntent($text)) {
            $this->messages->sendText($phone, __('nx-whatsapp-onboarding::common.signup_hint'));
            return;
        }

        if ($this->registers->findByPhone($phone) !== null) {
            $conversation = $this->states->startOrResume($phone, [
                'current_state' => ConversationState::HumanHandoff->value,
                'status' => 'handoff',
                'context' => ['wa_phone' => $phone, 'phone' => $phone],
            ]);
            $event->forceFill(['onboarding_conversation_id' => $conversation->id])->save();
            $this->messages->sendText($phone, __('nx-whatsapp-onboarding::common.duplicate_phone_login_help'));
            $this->handoff($conversation, 'Duplicate phone account conflict.', HandoffReasonCode::DuplicateAccount);
            return;
        }

        $role = $this->intentDetector->detectRole($text);
        $conversation = $this->states->startOrResume($phone, [
            'current_state' => $role === null ? ConversationState::WaitingRoleSelection->value : $this->firstStateForRole($role)->value,
            'role' => $role,
            'context' => ['wa_phone' => $phone, 'phone' => $phone, 'role' => $role],
        ]);
        $event->forceFill(['onboarding_conversation_id' => $conversation->id])->save();
        $this->cache->put($conversation);

        if ($role === null) {
            $this->sendRoleSelection($phone);
            return;
        }

        if (! $this->featureFlags->roleEnabled($role)) {
            $this->messages->sendText($phone, __('nx-whatsapp-onboarding::common.role_disabled'));
            $this->handoff($conversation, "Role {$role} disabled by feature flag.", HandoffReasonCode::SystemFailure);
            return;
        }

        $this->messages->sendText($phone, $this->questionFor($role, $this->firstStateForRole($role)));
    }

    private function handleExistingConversation(OnboardingConversation $conversation, OnboardingEvent $event, InboundWhatsAppMessage $message): void
    {
        $text = $message->text ?? '';

        if ($this->abuseDetector->isSuspicious($text)) {
            $this->handoff($conversation, 'Suspected abuse or spam.', HandoffReasonCode::SafetyRisk);
            return;
        }

        $detected = $this->commands->detect($text);
        if ($this->handleGlobalCommand($conversation, $event, $message, $detected['command'], $detected['argument'])) {
            return;
        }

        $state = ConversationState::from($conversation->current_state);
        if ($state === ConversationState::WaitingRoleSelection) {
            $this->handleRole($conversation, $detected['command'], $text);
            return;
        }

        if ($state === ConversationState::WaitingReviewConfirmation) {
            $this->handleReviewConfirmation($conversation, $detected['command']);
            return;
        }

        if ($state === ConversationState::WaitingTermsAcceptance) {
            $this->handleTermsAcceptance($conversation, $event, $message, $detected['command']);
            return;
        }

        if ($state === ConversationState::WaitingOtp) {
            $this->handleOtp($conversation, $text);
            return;
        }

        if ($state === ConversationState::ReadyToCreateProfile || $state === ConversationState::CreatingProfile) {
            $this->createProfileAndSendLogin($conversation, $state === ConversationState::CreatingProfile);
            return;
        }

        $this->handleFieldInput($conversation, $message, $state);
    }

    private function handleGlobalCommand(OnboardingConversation $conversation, OnboardingEvent $event, InboundWhatsAppMessage $message, ConversationCommand $command, ?string $argument): bool
    {
        if (($conversation->context['_pending_restart'] ?? false) === true) {
            if ($command === ConversationCommand::Confirm) {
                $fresh = $this->states->transition($conversation, ConversationState::WaitingRoleSelection, [
                    'wa_phone' => $conversation->wa_phone,
                    'phone' => $conversation->wa_phone,
                    'role' => null,
                ]);
                $fresh->forceFill(['role' => null, 'context' => ['wa_phone' => $conversation->wa_phone, 'phone' => $conversation->wa_phone], 'field_attempts' => [], 'invalid_attempts' => 0])->save();
                $this->messages->sendText($conversation->wa_phone, __('nx-whatsapp-onboarding::common.restarted'));
                return true;
            }

            if ($command !== ConversationCommand::Restart) {
                $this->states->transition($conversation, ConversationState::from($conversation->current_state), ['_pending_restart' => false]);
                $this->messages->sendText($conversation->wa_phone, __('nx-whatsapp-onboarding::common.restart_not_confirmed'));
                return true;
            }
        }

        if ($command === ConversationCommand::Human) {
            $this->handoff($conversation, 'User requested human support.', HandoffReasonCode::UserRequested);
            return true;
        }

        if ($command === ConversationCommand::Cancel) {
            $this->states->cancel($conversation);
            $this->messages->sendText($conversation->wa_phone, __('nx-whatsapp-onboarding::common.cancelled'));
            return true;
        }

        if ($command === ConversationCommand::Restart) {
            $this->states->transition($conversation, ConversationState::from($conversation->current_state), ['_pending_restart' => true]);
            $this->messages->sendText($conversation->wa_phone, __('nx-whatsapp-onboarding::common.restart_confirm'));
            return true;
        }

        if ($command === ConversationCommand::Signup) {
            $this->sendCurrentStep($conversation);
            return true;
        }

        if ($command === ConversationCommand::Help) {
            $state = ConversationState::from($conversation->current_state);
            $this->messages->sendText($conversation->wa_phone, __('nx-whatsapp-onboarding::common.current_step_help', ['step' => $state->value]) . "\n" . $this->questionFor((string) $conversation->role, $state));
            return true;
        }

        if ($command === ConversationCommand::Review) {
            $this->messages->sendText($conversation->wa_phone, $this->reviewSummary->build($conversation->context ?? []));
            return true;
        }

        if ($command === ConversationCommand::Back) {
            $this->goBack($conversation);
            return true;
        }

        if ($command === ConversationCommand::Edit) {
            $this->editField($conversation, (string) $argument);
            return true;
        }

        return false;
    }

    private function handleRole(OnboardingConversation $conversation, ConversationCommand $command, string $text): void
    {
        $role = match ($command) {
            ConversationCommand::Student => 'student',
            ConversationCommand::Tutor => 'tutor',
            default => $this->intentDetector->detectRole($text),
        };

        if ($role === null) {
            $this->invalid($conversation, __('nx-whatsapp-onboarding::errors.invalid_role'), 'role');
            return;
        }

        if (! $this->featureFlags->roleEnabled($role)) {
            $this->messages->sendText($conversation->wa_phone, __('nx-whatsapp-onboarding::common.role_disabled'));
            $this->handoff($conversation, "Role {$role} disabled by feature flag.", HandoffReasonCode::SystemFailure);
            return;
        }

        $next = $this->firstStateForRole($role);
        $fresh = $this->transitionTo($conversation, $next, [
            'role' => $role,
            'wa_phone' => $conversation->wa_phone,
            'phone' => $this->masker->maskValue('phone', $conversation->wa_phone),
        ]);

        $fresh->forceFill(['role' => $role, 'terms_url' => $this->terms->termsUrlForRole($role)])->save();
        $this->messages->sendText($conversation->wa_phone, $this->questionFor($role, $next));
    }

    private function handleFieldInput(OnboardingConversation $conversation, InboundWhatsAppMessage $message, ConversationState $state): void
    {
        $role = (string) $conversation->role;
        $field = $this->fieldFor($role, $state);
        if ($field === null) {
            $this->handoff($conversation, 'Unsupported state reached.', HandoffReasonCode::SystemFailure);
            return;
        }

        $detected = $this->commands->detect($message->text);
        if ($detected['command'] === ConversationCommand::Skip) {
            $this->skipField($conversation, $state, $field);
            return;
        }

        $value = $this->valueForField($field, $message);
        if ($value === null) {
            $this->invalid($conversation, __('nx-whatsapp-onboarding::common.reply_help'), $field);
            return;
        }

        $extracted = $this->extractor->extract($value, $field);
        if (($extracted['confidence'] ?? 0.0) < 0.70) {
            $this->invalid($conversation, __('nx-whatsapp-onboarding::common.reply_help'), $field);
            return;
        }

        $value = $this->fieldNormalizer->normalize($field, (string) ($extracted['value'] ?? $value));
        $validator = $role === 'tutor' ? $this->tutorValidator : $this->studentValidator;
        $result = $validator->validate($field, $value);

        if (! $result->valid) {
            $this->invalid($conversation, implode("\n", $result->errors), $field);
            return;
        }

        if ($field === 'email' && $this->registers->findByEmail($value) !== null) {
            $this->invalid($conversation, __('nx-whatsapp-onboarding::common.duplicate_email_try_again'), $field);
            return;
        }

        if ($field === 'document_number' && $this->registers->findByDocumentNumber($value) !== null) {
            $this->handoff($conversation, 'Duplicate tutor document number requiring manual review.', HandoffReasonCode::DuplicateAccount);
            return;
        }

        if (in_array($field, ['degree', 'front_image', 'back_image'], true) && ! $this->mediaValidator->validate($message)) {
            $this->invalid($conversation, __('nx-whatsapp-onboarding::errors.unsupported_upload'), $field);
            return;
        }

        if (in_array($field, ['degree', 'front_image', 'back_image'], true)) {
            $value = $this->mediaStorage->storeFromWhatsAppMediaId($value, $field === 'degree' ? 'degree_certificate' : $field);
            $field = $field === 'degree' ? 'degree_certificate' : $field;
        }
        $value = $this->draftCrypt->encryptIfSensitive($field, $value);

        $this->circuitBreaker->reset($conversation, $field);
        $next = $this->nextStateAfterField($role, $state, $conversation);
        $context = [$field => $value];
        if (($conversation->context['_return_to_review'] ?? false) === true) {
            $context['_return_to_review'] = false;
            $next = ConversationState::WaitingReviewConfirmation;
        }

        $fresh = $this->transitionTo($conversation, $next, $context);
        if ($next === ConversationState::WaitingReviewConfirmation) {
            $this->sendReview($fresh);
            return;
        }

        $this->messages->sendText($fresh->wa_phone, $this->questionFor($role, $next));
    }

    private function handleReviewConfirmation(OnboardingConversation $conversation, ConversationCommand $command): void
    {
        if ($command !== ConversationCommand::Confirm) {
            $this->invalid($conversation, __('nx-whatsapp-onboarding::errors.review_not_confirmed'), 'review');
            return;
        }

        $url = $this->terms->termsUrlForRole((string) $conversation->role);
        $privacyUrl = $this->terms->privacyUrlForRole((string) $conversation->role);
        $this->transitionTo($conversation, ConversationState::WaitingTermsAcceptance, []);
        $conversation->forceFill(['terms_url' => $url])->save();
        $this->audit->log($conversation->refresh(), 'terms_shown', [
            'terms_url' => $url,
            'privacy_url' => $privacyUrl,
            'terms_version' => config('whatsapp_onboarding.terms.version'),
        ]);
        $this->messages->sendText($conversation->wa_phone, __('nx-whatsapp-onboarding::common.terms_prompt', ['url' => $url, 'privacy_url' => $privacyUrl]));
    }

    private function handleTermsAcceptance(OnboardingConversation $conversation, OnboardingEvent $event, InboundWhatsAppMessage $message, ConversationCommand $command): void
    {
        if ($command !== ConversationCommand::Agree) {
            $this->invalid($conversation, __('nx-whatsapp-onboarding::errors.terms_not_accepted'), 'terms');
            return;
        }

        $this->terms->accept($conversation, (string) $event->wa_message_id, [
            'webhook_timestamp' => optional($event->webhook_timestamp)->toIso8601String(),
            'event_id' => $event->id,
            'acceptance_text' => $message->text ?? '',
            'request' => $event->payload['_request_metadata'] ?? [],
        ]);
        $this->audit->log($conversation->refresh(), 'terms_accepted', ['message_id' => $event->wa_message_id]);
        $otp = $this->otp->issue($conversation);
        $this->transitionTo($conversation, ConversationState::WaitingOtp, []);
        $this->sendOtp($conversation->refresh(), $otp);
    }

    private function handleOtp(OnboardingConversation $conversation, string $text): void
    {
        $result = $this->otp->verify($conversation, $text);
        if ($result->tooManyAttempts) {
            $this->audit->log($conversation, 'otp_failed', ['reason' => 'too_many_attempts']);
            $this->handoff($conversation, 'Too many OTP attempts.', HandoffReasonCode::RepeatedInvalidInput);
            $this->messages->sendText($conversation->wa_phone, __('nx-whatsapp-onboarding::errors.otp_too_many_attempts'));
            return;
        }

        if ($result->expired) {
            $this->audit->log($conversation, 'otp_failed', ['reason' => 'expired']);
            $otp = $this->otp->issue($conversation);
            $this->messages->sendText($conversation->wa_phone, __('nx-whatsapp-onboarding::errors.otp_expired'));
            $this->sendOtp($conversation->refresh(), $otp, ['reason' => 'expired_resend']);
            return;
        }

        if (! $result->valid) {
            $this->audit->log($conversation, 'otp_failed', ['reason' => 'invalid']);
            $this->invalid($conversation, __('nx-whatsapp-onboarding::errors.invalid_otp'), 'otp');
            return;
        }

        $this->audit->log($conversation->refresh(), 'otp_verified');
        $fresh = $this->transitionTo($conversation, ConversationState::ReadyToCreateProfile, []);
        $this->createProfileAndSendLogin($fresh, false);
    }

    private function createProfileAndSendLogin(OnboardingConversation $conversation, bool $isRecovery): void
    {
        $context = array_merge($conversation->context ?? [], ['role' => $conversation->role, 'phone' => $conversation->wa_phone]);
        $ready = $this->readiness->check((string) $conversation->role, $context);

        if (! $ready->valid) {
            if ($ready->duplicateField !== null) {
                $this->messages->sendText($conversation->wa_phone, __('nx-whatsapp-onboarding::common.duplicate_conflict', ['field' => $ready->duplicateField]));
                $this->handoff($conversation, 'Duplicate ' . $ready->duplicateField . ' conflict requiring manual merge.', HandoffReasonCode::DuplicateAccount);
                return;
            }

            $this->audit->log($conversation, 'profile_readiness_missing', ['missing' => implode(',', $ready->missingFields)]);
            $this->messages->sendText($conversation->wa_phone, __('nx-whatsapp-onboarding::errors.missing_required_fields'));
            $this->handoff($conversation, 'Missing required fields before profile creation.', HandoffReasonCode::SystemFailure);
            return;
        }

        if ($isRecovery && $this->registers->findByPhone($conversation->wa_phone) !== null) {
            $this->handoff($conversation, 'Profile exists after interrupted credential delivery.');
            return;
        }

        try {
            $this->transitionTo($conversation, ConversationState::CreatingProfile, []);
            $fresh = $conversation->refresh();
            $result = $this->profileCreation->dispatchNow(new ProfileCreationCommand((int) $fresh->id, (string) $fresh->role));
            $this->states->markCompleted($conversation->refresh());
        } catch (Throwable $exception) {
            $this->audit->log($conversation, 'profile_creation_failed', ['error' => $exception->getMessage()]);
            $this->messages->sendText($conversation->wa_phone, __('nx-whatsapp-onboarding::common.profile_create_trouble'));
            $this->handoff($conversation, 'Profile creation failed after retry.', HandoffReasonCode::SystemFailure);
            return;
        }

        $role = (string) $conversation->role;
        $checklist = $role === 'tutor' ? $this->tutorChecklist->build() : $this->studentChecklist->build();
        $dashboard = $this->dashboardLinks->dashboardForRole($role, $result->register);
        $this->audit->log($conversation->refresh(), 'profile_created', ['user_id' => $result->register->user_id, 'status' => $result->register->status]);
        $this->audit->log($conversation->refresh(), 'temp_password_issued', ['user_id' => $result->register->user_id]);
        $this->audit->log($conversation->refresh(), 'dashboard_link_issued', ['magic_login' => (bool) config('whatsapp_onboarding.dashboard.magic_login_enabled')]);
        $messageKey = $role === 'tutor' && (string) $result->register->status === 'pending_review'
            ? 'nx-whatsapp-onboarding::common.signup_complete_tutor_pending'
            : 'nx-whatsapp-onboarding::common.signup_complete';
        $this->messages->sendText($conversation->wa_phone, __($messageKey, [
            'phone' => $this->masker->maskValue('phone', $conversation->wa_phone),
            'password' => $result->temporaryPassword,
            'dashboard' => $dashboard,
            'checklist' => $checklist,
        ]));
    }

    /** @param array<string, mixed> $metadata */
    private function sendOtp(OnboardingConversation $conversation, string $otp, array $metadata = []): void
    {
        $this->audit->log($conversation, 'otp_sent', $metadata);
        $this->messages->sendTemplate(
            $conversation->wa_phone,
            $this->templates->template('otp_message'),
            (string) config('whatsapp_onboarding.meta.template_language', 'en_US'),
            [[
                'type' => 'body',
                'parameters' => [[
                    'type' => 'text',
                    'text' => $otp,
                ]],
            ]],
        );
    }

    private function sendRoleSelection(string $phone): void
    {
        $body = __('nx-whatsapp-onboarding::common.welcome_choose_role');
        $this->messages->sendButtons($phone, $body, [
            ['id' => 'student', 'title' => 'Student'],
            ['id' => 'tutor', 'title' => 'Tutor'],
        ], $body);
    }

    private function sendReview(OnboardingConversation $conversation): void
    {
        $body = $this->reviewSummary->build($conversation->context ?? []) . "\n\n" . __('nx-whatsapp-onboarding::common.review_confirm_prompt');
        $this->messages->sendButtons($conversation->wa_phone, $body, [
            ['id' => 'confirm', 'title' => 'Confirm'],
            ['id' => 'edit', 'title' => 'Edit'],
            ['id' => 'human', 'title' => 'Human help'],
        ], $body);
    }

    private function sendCurrentStep(OnboardingConversation $conversation): void
    {
        $state = ConversationState::from($conversation->current_state);
        if ($state === ConversationState::WaitingRoleSelection || $state === ConversationState::New) {
            $this->sendRoleSelection($conversation->wa_phone);
            return;
        }

        if ($state === ConversationState::WaitingReviewConfirmation) {
            $this->sendReview($conversation);
            return;
        }

        $this->messages->sendText($conversation->wa_phone, $this->questionFor((string) $conversation->role, $state));
    }

    private function goBack(OnboardingConversation $conversation): void
    {
        $state = ConversationState::from($conversation->current_state);
        $previous = (string) $conversation->role === 'tutor'
            ? $this->tutorFlow->previousBefore($state)
            : $this->studentFlow->previousBefore($state);

        if ($previous === null) {
            $this->messages->sendText($conversation->wa_phone, __('nx-whatsapp-onboarding::common.back_not_available'));
            return;
        }

        $fresh = $this->transitionTo($conversation, $previous, []);
        $this->messages->sendText($fresh->wa_phone, $this->questionFor((string) $fresh->role, $previous));
    }

    private function editField(OnboardingConversation $conversation, string $field): void
    {
        $state = (string) $conversation->role === 'tutor'
            ? $this->tutorFlow->stateForField($field)
            : $this->studentFlow->stateForField($field);

        if ($state === null) {
            $this->messages->sendText($conversation->wa_phone, __('nx-whatsapp-onboarding::common.edit_unknown'));
            return;
        }

        $fresh = $this->transitionTo($conversation, $state, ['_return_to_review' => true]);
        $this->messages->sendText($fresh->wa_phone, $this->questionFor((string) $fresh->role, $state));
    }

    private function skipField(OnboardingConversation $conversation, ConversationState $state, string $field): void
    {
        $role = (string) $conversation->role;
        $optional = $role === 'tutor' ? $this->tutorFlow->isOptional($field) : $this->studentFlow->isOptional($field);
        if (! $optional) {
            $this->invalid($conversation, __('nx-whatsapp-onboarding::common.skip_not_allowed'), $field);
            return;
        }

        $next = $this->nextStateAfterField($role, $state, $conversation);
        $context = [$field => ''];
        if (($conversation->context['_return_to_review'] ?? false) === true) {
            $context['_return_to_review'] = false;
            $next = ConversationState::WaitingReviewConfirmation;
        }

        $fresh = $this->transitionTo($conversation, $next, $context);
        if ($next === ConversationState::WaitingReviewConfirmation) {
            $this->sendReview($fresh);
            return;
        }

        $this->messages->sendText($fresh->wa_phone, $this->questionFor($role, $next));
    }

    private function valueForField(string $field, InboundWhatsAppMessage $message): ?string
    {
        if (in_array($field, ['degree', 'front_image', 'back_image'], true)) {
            return (string) ($message->raw['image']['id'] ?? $message->raw['document']['id'] ?? $message->text ?? '');
        }

        return $message->text;
    }

    private function nextStateAfterField(string $role, ConversationState $state, OnboardingConversation $conversation): ConversationState
    {
        return $role === 'tutor' ? $this->tutorFlow->nextAfter($state) : $this->studentFlow->nextAfter($state);
    }

    /** @param array<string, mixed> $context */
    private function transitionTo(OnboardingConversation $conversation, ConversationState $to, array $context): OnboardingConversation
    {
        $from = ConversationState::from($conversation->current_state);
        $this->engine->transition($from, $to, 'inbound_message');
        $fresh = $this->states->transition($conversation, $to, $context);
        $this->cache->put($fresh);

        return $fresh;
    }

    private function invalid(OnboardingConversation $conversation, string $message, ?string $field = null): void
    {
        $this->circuitBreaker->recordInvalidAttempt($conversation, $field);
        if ($this->circuitBreaker->shouldHandoff($conversation->refresh(), $field)) {
            $this->handoff($conversation->refresh(), 'Too many invalid replies.', HandoffReasonCode::RepeatedInvalidInput);
            return;
        }

        $this->messages->sendText($conversation->wa_phone, $message);
    }

    private function handoff(OnboardingConversation $conversation, string $reason, ?HandoffReasonCode $reasonCode = null): void
    {
        $ticket = $this->humanHandoff->openTicket($conversation, $reason, $reasonCode?->value);
        $this->messages->sendText($conversation->wa_phone, __('nx-whatsapp-onboarding::common.handoff', ['ticket_id' => (string) $ticket->id]));
    }

    private function firstStateForRole(string $role): ConversationState
    {
        return $role === 'tutor' ? ConversationState::TutorName : ConversationState::StudentName;
    }

    private function fieldFor(string $role, ConversationState $state): ?string
    {
        return $role === 'tutor' ? $this->tutorFlow->fieldFor($state) : $this->studentFlow->fieldFor($state);
    }

    private function questionFor(string $role, ConversationState $state): string
    {
        return $role === 'tutor' ? $this->tutorQuestions->questionFor($state) : $this->studentQuestions->questionFor($state);
    }
}
