<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Queue\Jobs\SendProfileGeneratedMessageJob;

final readonly class ProfileGenerationController
{
    public function generateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|integer|exists:onboarding_conversations,id',
            'role' => 'required|in:tutor,student',
        ]);

        $conversationId = (int) $request->input('conversation_id');
        $role = (string) $request->input('role');

        $conversation = OnboardingConversation::query()->findOrFail($conversationId);

        if ($conversation->role !== $role) {
            return response()->json([
                'success' => false,
                'error' => 'Conversation role mismatch',
            ], 422);
        }

        SendProfileGeneratedMessageJob::dispatch($conversationId, $role);

        return response()->json([
            'success' => true,
            'message' => 'Profile generation started. You will receive details on WhatsApp in 60 seconds.',
            'counter_duration' => 60,
            'conversation_id' => $conversationId,
        ]);
    }
}
