<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| BMS (Bulk Messaging Solutions) Gateway
|--------------------------------------------------------------------------
|
| Transport credentials only. Sender ID and message wording are admin config
| keys (auth.otp.sms.sender_id, auth.otp.sms.message_template) per Constitution
| Law 2; the API key is env-only and never enters admin_config
| (engineering-standards §9, §11).
|
| NOTE ON THE HOST: BMS is the current brand of mNotify, and the API still
| answers on api.mnotify.com. A bms.africa host does NOT serve this API. If the
| base URL below looks like the wrong vendor, it is not — see the provider
| docblock.
|
| Read via config('bms.*') so config:cache keeps working.
|
*/

return [

    'api_key' => env('BMS_API_KEY', ''),

    'base_url' => env('BMS_BASE_URL', 'https://api.mnotify.com'),

    // This call sits inside the OTP request/response cycle (ADR-0008 explains
    // why it is not queued), so this ceiling is also the worst case a user
    // waits on the "sending code" spinner.
    'timeout_seconds' => (int) env('BMS_TIMEOUT_SECONDS', 10),

];
