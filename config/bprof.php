<?php

return [
    'enabled' => env('BPROF_ENABLED', false),
    'viewer_url' => env('BPROF_VIEWER_URL', 'http://localhost:1337'),
    'server_name' => env('BPROF_SERVER_NAME', gethostname()),
    'db_connection' => env('BPROF_DB_CONNECTION', 'mysql'),
    'db_table' => env('BPROF_DB_TABLE', 'bprof_traces'),
    'ignored_paths' => [
        '/',
        'telescope-api*',
        'nova-api*',
        'telescope',
        'api/telescope*'
    ],
    'ignored_packages' => [
        base_path('packages'),
        base_path('vendor/laravel'),
        base_path('vendor/sentry'),
    ]
];
