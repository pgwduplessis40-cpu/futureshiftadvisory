<?php

declare(strict_types=1);

return [
    'active_learning' => env('FEATURE_ACTIVE_LEARNING', false),
    'require_approval' => env('LEARNING_REQUIRE_APPROVAL', true),
];
