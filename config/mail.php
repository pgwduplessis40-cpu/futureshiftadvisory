<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin", "graph"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'graph' => [
            'transport' => 'graph',
            'auth_mode' => env('MICROSOFT_GRAPH_MAIL_AUTH_MODE', 'client_credentials'),
            'tenant' => env('MICROSOFT_GRAPH_MAIL_TENANT', env('MICROSOFT_GRAPH_TENANT', '')),
            'client_id' => env('MICROSOFT_GRAPH_MAIL_CLIENT_ID', env('MICROSOFT_GRAPH_CLIENT_ID', '')),
            'client_secret' => env('MICROSOFT_GRAPH_MAIL_CLIENT_SECRET', env('MICROSOFT_GRAPH_CLIENT_SECRET', '')),
            'from_address' => env('MICROSOFT_GRAPH_MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
            'base_url' => env('MICROSOFT_GRAPH_MAIL_BASE_URL', 'https://graph.microsoft.com/v1.0'),
            'authorize_url' => env('MICROSOFT_GRAPH_MAIL_AUTHORIZE_URL', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize'),
            'token_url' => env('MICROSOFT_GRAPH_MAIL_TOKEN_URL', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token'),
            'scope' => env('MICROSOFT_GRAPH_MAIL_SCOPE', 'https://graph.microsoft.com/.default'),
            'delegated_scopes' => array_values(array_filter(preg_split('/[\s,]+/', (string) env('MICROSOFT_GRAPH_MAIL_DELEGATED_SCOPES', 'offline_access User.Read Mail.Send')) ?: [])),
            'timeout' => (int) env('MICROSOFT_GRAPH_MAIL_TIMEOUT', 15),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel')),
    ],

    /*
    | Owner address — destination for public-site prospect-lead notifications.
    | Falls back to MAIL_FROM_ADDRESS so dev sends-to-log still work.
    */
    'owner_address' => env('OWNER_EMAIL', env('MAIL_FROM_ADDRESS')),

];
