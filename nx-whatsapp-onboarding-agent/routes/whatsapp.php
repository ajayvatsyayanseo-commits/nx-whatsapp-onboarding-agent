<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NxTutors\WhatsAppOnboarding\WhatsApp\Controllers\WebhookEventController;
use NxTutors\WhatsAppOnboarding\WhatsApp\Controllers\WebhookVerificationController;

Route::prefix(config('whatsapp_onboarding.route_prefix', 'whatsapp/onboarding'))
    ->name('nxtutors.whatsapp-onboarding.')
    ->group(static function (): void {
        Route::get('/webhook', WebhookVerificationController::class)->name('webhook.verify');
        Route::post('/webhook', WebhookEventController::class)->name('webhook.events');
    });
