<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Redis Stream key prefix
    |--------------------------------------------------------------------------
    | Every event stream key is composed as: <prefix>.<engine>
    | e.g. "kalaanba.events.match" for events in the match engine.
    |
    | Config key: eventbus.stream_prefix
    | Scope: global
    | Approval level: platform
    */
    'stream_prefix' => env('EVENTBUS_STREAM_PREFIX', 'kalaanba.events'),

    /*
    |--------------------------------------------------------------------------
    | Maximum relay attempts before a row is considered poisoned
    |--------------------------------------------------------------------------
    | Rows with attempts >= this value are skipped by the relay.
    | Increase only after investigating the root cause.
    |
    | Config key: eventbus.max_relay_attempts
    | Scope: global
    | Approval level: platform
    */
    'max_relay_attempts' => (int) env('EVENTBUS_MAX_RELAY_ATTEMPTS', 5),

];
