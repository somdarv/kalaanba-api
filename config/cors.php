<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| The Kalaanba frontend is NOT a BFF: the browser calls this API directly
| and holds its Sanctum bearer token client-side. CORS is therefore
| load-bearing, unlike the hub, where every call is server-rendered.
|
| Without this file Laravel falls back to `allowed_origins => ['*']`, which
| works but is needlessly permissive on a production API. Pin it to the one
| origin that is allowed to call us.
|
| `supports_credentials` stays false: bearer tokens are sent in the
| Authorization header, not cookies, so credentialed requests are not needed.
| Enabling it alongside a wildcard origin would be a vulnerability.
|
| X-Request-Id is exposed so browser-side error reporting can quote the same
| correlation id that appears in our logs and Sentry events.
|
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Request-Id'],

    'max_age' => 0,

    'supports_credentials' => false,

];
