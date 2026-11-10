<?php

namespace App\Providers;

use App\Auth\ClientiUserProvider;
use App\Models\VarianteProdotto;
use App\Observers\VarianteProdottoObserver;
use App\Services\Channels\ChannelManager;
use App\Services\Erp\ErpManager;
use App\Services\PriceService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PriceService::class);
        $this->app->singleton(ErpManager::class);
        $this->app->singleton(ChannelManager::class);
    }

    public function boot(): void
    {
        VarianteProdotto::observe(VarianteProdottoObserver::class);

        // le risposte alle email del portale vanno al backoffice, non al mittente tecnico (senza casella)
        if ($replyTo = config('portale.mail_reply_to')) {
            Mail::alwaysReplyTo($replyTo, config('mail.from.name'));
        }

        // reset password di agenti e clienti B2B: il link porta alla pagina della SPA, il testo è in italiano
        // (il pannello admin Filament usa una sua notifica e non è toccato)
        Auth::provider('clienti-con-accesso', fn ($app, array $config) => new ClientiUserProvider($app['hash'], $config['model']));

        ResetPassword::createUrlUsing(fn ($notifiable, string $token) => rtrim((string) config('portale.spa_url'), '/')
            .'/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset()));

        ResetPassword::toMailUsing(fn ($notifiable, string $token) => (new MailMessage)
            ->subject('Reimposta la tua password')
            ->greeting('Ciao,')
            ->line('Hai chiesto di reimpostare la password del tuo accesso al Portale B2B.')
            ->action('Scegli una nuova password', rtrim((string) config('portale.spa_url'), '/')
                .'/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset()))
            ->line('Il link è valido per '.config('auth.passwords.agenti.expire').' minuti.')
            ->line('Se non sei stato tu, ignora questa email: la tua password resta invariata.')
            ->salutation('Portale B2B CDM'));
    }
}
