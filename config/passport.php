<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Passport will use when
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'api',

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Passport uses encryption keys while generating secure access tokens for
    | your application. By default, the keys are stored as local files but
    | can be set via environment variables when that is more convenient.
    |
    | INJECT THESE AT RUNTIME IN ANY DEPLOYED ENVIRONMENT.
    |
    | The file-based default (storage/oauth-*.key) is fine for local development
    | and wrong everywhere else, because the container filesystem is ephemeral and
    | per-replica. Two consequences, both silent:
    |
    |   - A key pair generated at container start differs per replica, so a token
    |     minted by one replica fails validation on the next — an intermittent
    |     401 that load-balances.
    |   - A key pair regenerated on deploy invalidates every token already issued,
    |     signing out every user on every release.
    |
    | Generate ONCE per environment (`php artisan passport:keys`, then read the
    | files), store them as secrets, and inject them here. Never commit them and
    | never bake them into an image layer. app:check-config fails production when
    | neither the environment variables nor the key files are present.
    |
    | MULTI-LINE PEM CONTENT NEEDS NO SPECIAL HANDLING HERE. Many secret stores
    | cannot carry a literal newline, so keys are often stored with "\n" escapes
    | instead — and Passport already un-escapes them itself
    | (PassportServiceProvider::makeCryptKey does `str_replace('\n', "\n", ...)`),
    | falling back to the key FILES when the value is empty. A normalising closure
    | here would only duplicate the framework.
    |
    */

    'private_key' => env('PASSPORT_PRIVATE_KEY'),

    'public_key' => env('PASSPORT_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Passport Database Connection
    |--------------------------------------------------------------------------
    |
    | By default, Passport's models will utilize your application's default
    | database connection. If you wish to use a different connection you
    | may specify the configured name of the database connection here.
    |
    */

    'connection' => env('PASSPORT_CONNECTION'),

];
