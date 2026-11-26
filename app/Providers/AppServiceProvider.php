<?php

namespace App\Providers;

use App\Models\Dominio;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use OwenIt\Auditing\Events\AuditCustom;
use Spatie\ModelStatus\Events\StatusUpdated;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (App::environment('production')) {
            URL::forceScheme('https');
        }

        // Datas em português ("há 12 minutos"); o fuso fica em config/app.php (APP_TIMEZONE)
        Date::setLocale(config('app.locale'));

        // Atrás do Traefik: confia no proxy para obter o IP real do cliente (TRUSTED_PROXIES)
        if (config('dns.proxies_confiaveis')) {
            TrustProxies::at(config('dns.proxies_confiaveis'));
        }

        // Quem pode pedir domínio: servidores e docentes da unidade (gates do senhaunica-socialite)
        Gate::define('solicitante', function ($user) {
            return Gate::forUser($user)->any(['senhaunica.servidor', 'senhaunica.docente']);
        });

        // Quem pode trocar o IP: admin sempre; o solicitante enquanto pendente ou aprovado
        // (se trocar depois de aprovado, volta para pendente: ver DominioController@trocarIp)
        Gate::define('trocarIp', function ($user, Dominio $dominio) {
            return Gate::forUser($user)->allows('admin')
                || ($dominio->user_id == $user->id && in_array($dominio->status, ['pendente', 'aprovado']));
        });

        // Toda mudança de status fica na auditoria, com o usuário que fez a mudança
        Event::listen(StatusUpdated::class, function (StatusUpdated $event) {
            $model = $event->model;
            $model->auditEvent = 'status';
            $model->isCustomEvent = true;
            $model->auditCustomOld = ['status' => $event->oldStatus?->name];
            $model->auditCustomNew = ['status' => $event->newStatus->name, 'observacao' => $event->newStatus->reason];
            Event::dispatch(new AuditCustom($model));
        });
    }
}
