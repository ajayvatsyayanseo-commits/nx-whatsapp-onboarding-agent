<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NxTutors\WhatsAppOnboarding\Health\HealthCheckService;

Route::prefix('api/nx-whatsapp-onboarding')
    ->name('nxtutors.whatsapp-onboarding.api.')
    ->group(static function (): void {
        Route::get('/health', static fn (HealthCheckService $health): array => $health->live())->name('health');
    });

Route::get('/health/live', static fn (HealthCheckService $health): array => $health->live())->name('nxtutors.whatsapp-onboarding.health.live');
Route::get('/health/ready', static fn (HealthCheckService $health): array => $health->ready())->name('nxtutors.whatsapp-onboarding.health.ready');
Route::get('/health/deep', static fn (HealthCheckService $health): array => $health->deep())
    ->middleware('auth')
    ->name('nxtutors.whatsapp-onboarding.health.deep');
