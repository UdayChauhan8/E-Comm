<?php

namespace App\Providers;

use App\Events\OrderActionEvent;
use App\Listeners\SendOrderActionEmail;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderActionEvent::class => [
            SendOrderActionEmail::class,
        ],
    ];
}
