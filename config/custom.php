<?php

/*
|--------------------------------------------------------------------------
| Custom Config
|--------------------------------------------------------------------------
|
| This file contains the custom config variables.
| Avoid calling the env function outside the config files.
| We are using this file in favor of configuration caching to give our application a speed boost.
| https://laravel.com/docs/10.x/configuration#configuration-caching
|
| If you execute the config:cache command during your deployment process,
| you should be sure that you are only calling the env function from within your configuration files.
| Once the configuration has been cached, the .env file will not be loaded;
| therefore, the env function will only return external, system level environment variables.
|
*/

return [
    'app_domain' => env('APP_DOMAIN'),
    'app_frontend_url' => env('APP_FRONTEND_URL'),
    'sysad_email' => env('SYSAD_EMAIL'),
    'sysad_password' => env('SYSAD_PASSWORD'),
    'appad_email' => env('APPAD_EMAIL'),
    'appad_password' => env('APPAD_PASSWORD')
];
