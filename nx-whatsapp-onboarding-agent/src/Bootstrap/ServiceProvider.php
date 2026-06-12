<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Bootstrap;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use NxTutors\WhatsAppOnboarding\Cache\CacheKeyBuilder;
use NxTutors\WhatsAppOnboarding\Contracts\HumanHandoffInterface;
use NxTutors\WhatsAppOnboarding\Contracts\InputExtractorInterface;
use NxTutors\WhatsAppOnboarding\Contracts\MalwareScannerInterface;
use NxTutors\WhatsAppOnboarding\Contracts\AdminNotificationInterface;
use NxTutors\WhatsAppOnboarding\Contracts\MessageSenderInterface;
use NxTutors\WhatsAppOnboarding\Contracts\MediaStorageInterface;
use NxTutors\WhatsAppOnboarding\Contracts\OtpServiceInterface;
use NxTutors\WhatsAppOnboarding\Contracts\PolicyGuardInterface;
use NxTutors\WhatsAppOnboarding\Contracts\ProfileWriterInterface;
use NxTutors\WhatsAppOnboarding\Contracts\StateRepositoryInterface;
use NxTutors\WhatsAppOnboarding\Conversation\Repositories\PostgresStateRepository;
use NxTutors\WhatsAppOnboarding\Conversation\Services\HumanHandoffService;
use NxTutors\WhatsAppOnboarding\Conversation\Services\NoopInputExtractor;
use NxTutors\WhatsAppOnboarding\Profile\Services\CompositeProfileWriter;
use NxTutors\WhatsAppOnboarding\Security\AbuseDetection\NoopMalwareScanner;
use NxTutors\WhatsAppOnboarding\Security\Guards\TermsUrlPolicyGuard;
use NxTutors\WhatsAppOnboarding\Security\Guards\PolicyGuardService;
use NxTutors\WhatsAppOnboarding\Security\Otp\DatabaseOtpService;
use NxTutors\WhatsAppOnboarding\Observability\Notifications\CompositeAdminNotifier;
use NxTutors\WhatsAppOnboarding\WhatsApp\Services\MetaMessageSender;
use NxTutors\WhatsAppOnboarding\WhatsApp\Services\WhatsAppMediaService;

final class ServiceProvider extends LaravelServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/whatsapp_onboarding.php', 'whatsapp_onboarding');
        $this->mergeConfigFrom(__DIR__ . '/../../config/state_machine.php', 'whatsapp_onboarding_state_machine');
        $this->mergeConfigFrom(__DIR__ . '/../../config/security.php', 'whatsapp_onboarding_security');
        $this->mergeConfigFrom(__DIR__ . '/../../config/observability.php', 'whatsapp_onboarding_observability');
        $this->mergeConfigFrom(__DIR__ . '/../../config/cost_limits.php', 'whatsapp_onboarding_cost_limits');

        $this->app->bind(ClientInterface::class, Client::class);
        $this->app->singleton(CacheKeyBuilder::class);
        $this->app->bind(StateRepositoryInterface::class, PostgresStateRepository::class);
        $this->app->bind(MessageSenderInterface::class, MetaMessageSender::class);
        $this->app->bind(MediaStorageInterface::class, WhatsAppMediaService::class);
        $this->app->bind(InputExtractorInterface::class, NoopInputExtractor::class);
        $this->app->bind(MalwareScannerInterface::class, NoopMalwareScanner::class);
        $this->app->bind(ProfileWriterInterface::class, CompositeProfileWriter::class);
        $this->app->bind(OtpServiceInterface::class, DatabaseOtpService::class);
        $this->app->bind(PolicyGuardInterface::class, PolicyGuardService::class);
        $this->app->bind(AdminNotificationInterface::class, CompositeAdminNotifier::class);
        $this->app->bind(HumanHandoffInterface::class, HumanHandoffService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/whatsapp.php');
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/messages', 'nx-whatsapp-onboarding');

        if ($this->app->runningInConsole()) {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/console.php');
            $this->publishes([
                __DIR__ . '/../../config' => config_path('nx-whatsapp-onboarding'),
            ], 'nx-whatsapp-onboarding-config');
        }

        if ($this->app->environment('production')) {
            $this->app->make(PolicyGuardInterface::class)->assertSafeConfiguration();
        }
    }
}
