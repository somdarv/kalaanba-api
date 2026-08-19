<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SMSOnlineGH Gateway
|--------------------------------------------------------------------------
|
| Transport credentials only. Everything a human would want to tune without a
| deploy — the sender ID, the message wording, which provider is even active —
| lives in Admin Configuration instead (Constitution Law 2), because those are
| nomenclature and policy, not secrets.
|
| The API key is env-only and never enters `admin_config`
| (engineering-standards §9, §11). Same split as `config/cloudinary.php`.
|
| Read via `config('smsonlinegh.*')` so `config:cache` keeps working.
|
| Wire format and its traps are documented on
| {@see Kalaanba\Support\Auth\Otp\SmsOnlineGhOtpProvider}.
|
*/

return [

    'api_key' => env('SMSONLINEGH_API_KEY', ''),

    // A property of the account, not of the code — resellers can be issued a
    // white-label host. The standard host is correct for our account.
    'base_url' => env('SMSONLINEGH_BASE_URL', 'https://api.smsonlinegh.com'),

    // Deliberately short. This call sits inside the OTP request/response cycle
    // (see the provider docblock for why it is not queued), so the ceiling here
    // is also the worst case a user waits on the "sending code" spinner.
    'timeout_seconds' => (int) env('SMSONLINEGH_TIMEOUT_SECONDS', 10),

];
