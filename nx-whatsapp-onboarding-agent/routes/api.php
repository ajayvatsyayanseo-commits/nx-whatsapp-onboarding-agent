<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NxTutors\WhatsAppOnboarding\Health\HealthCheckService;

Route::prefix('api/nx-whatsapp-onboarding')
    ->name('nxtutors.whatsapp-onboarding.api.')
    ->group(static function (): void {
        Route::get('/health', static fn (HealthCheckService $health): array => $health->live())->name('health');
    });

Route::get('/health', static fn (HealthCheckService $health): array => $health->live())->name('nxtutors.whatsapp-onboarding.health.root');
Route::get('/health/live', static fn (HealthCheckService $health): array => $health->live())->name('nxtutors.whatsapp-onboarding.health.live');
Route::get('/health/ready', static fn (HealthCheckService $health): array => $health->ready())->name('nxtutors.whatsapp-onboarding.health.ready');
Route::get('/health/db', static function (HealthCheckService $health) {
    $status = $health->dbStatus();
    return response()->json($status, $status['ok'] ? 200 : 503);
})->name('nxtutors.whatsapp-onboarding.health.db');
Route::get('/health/whatsapp', static fn (HealthCheckService $health): array => $health->whatsappStatus())->name('nxtutors.whatsapp-onboarding.health.whatsapp');
Route::get('/health/internal-handoff', static fn (HealthCheckService $health): array => $health->internalHandoffStatus())->name('nxtutors.whatsapp-onboarding.health.internal-handoff');
Route::get('/health/deep', static fn (HealthCheckService $health): array => $health->deep())
    ->middleware('auth')
    ->name('nxtutors.whatsapp-onboarding.health.deep');
