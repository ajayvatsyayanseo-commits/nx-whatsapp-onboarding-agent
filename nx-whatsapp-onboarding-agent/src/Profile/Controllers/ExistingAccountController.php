<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Profile\Services\ExistingAccountService;

final readonly class ExistingAccountController
{
    public function __construct(private ExistingAccountService $service)
    {
    }

    public function checkExisting(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer|exists:onboarding_conversations,id',
        ]);

        $conversation = OnboardingConversation::query()->findOrFail(
            (int) $request->input('conversation_id')
        );

        $result = $this->service->handleExistingAccount($conversation);

        return response()->json($result);
    }

    public function handleHumanRequest(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer|exists:onboarding_conversations,id',
            'user_input' => 'required|string',
        ]);

        $conversation = OnboardingConversation::query()->findOrFail(
            (int) $request->input('conversation_id')
        );

        $result = $this->service->handleHumanRequest(
            $conversation,
            (string) $request->input('user_input')
        );

        return response()->json($result);
    }

    public function handleDashboardChoice(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer|exists:onboarding_conversations,id',
            'choice' => 'required|string|in:1,2,login,support',
        ]);

        $conversation = OnboardingConversation::query()->findOrFail(
            (int) $request->input('conversation_id')
        );

        $result = $this->service->handleDashboardChoice(
            $conversation,
            (string) $request->input('choice')
        );

        return response()->json($result);
    }
}
