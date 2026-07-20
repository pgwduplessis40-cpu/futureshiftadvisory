<?php

return [
    'dashboard_launch_timing' => (bool) env(
        'DASHBOARD_LAUNCH_TIMING',
        in_array(env('APP_ENV', 'production'), ['local', 'testing', 'test'], true),
    ),
];
