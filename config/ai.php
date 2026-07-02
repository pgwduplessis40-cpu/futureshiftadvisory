<?php

declare(strict_types=1);

use App\Services\Ai\Claude\AnthropicClaudeClient;

$monthlyBudgetUsd = env('AI_MONTHLY_BUDGET_USD');
$usdToNzdRate = env('AI_USD_TO_NZD_RATE');

return [
    'default_provider' => env('AI_PROVIDER', 'anthropic'),

    'active_provider' => env('AI_PROVIDER', 'anthropic'),

    'force_fake' => (bool) env('AI_FORCE_FAKE', false),

    'providers' => [
        'anthropic' => [
            'display_name' => 'Anthropic Claude',
            'integration_key' => 'anthropic',
            'client' => AnthropicClaudeClient::class,
            'status' => 'available',
        ],
    ],

    'costs' => [
        'monthly_budget_usd' => $monthlyBudgetUsd === null ? null : (float) $monthlyBudgetUsd,
        'usd_to_nzd_rate' => $usdToNzdRate === null ? null : (float) $usdToNzdRate,
    ],

    'pricing' => [
        'anthropic' => [
            'default' => [
                'input_usd_per_mtok' => (float) env('ANTHROPIC_DEFAULT_INPUT_USD_PER_MTOK', 3.0),
                'output_usd_per_mtok' => (float) env('ANTHROPIC_DEFAULT_OUTPUT_USD_PER_MTOK', 15.0),
                'cache_write_5m_usd_per_mtok' => (float) env('ANTHROPIC_DEFAULT_CACHE_WRITE_5M_USD_PER_MTOK', 3.75),
                'cache_read_usd_per_mtok' => (float) env('ANTHROPIC_DEFAULT_CACHE_READ_USD_PER_MTOK', 0.30),
            ],
            'models' => [
                'claude-fable-5' => [
                    'input_usd_per_mtok' => 10.0,
                    'output_usd_per_mtok' => 50.0,
                    'cache_write_5m_usd_per_mtok' => 12.50,
                    'cache_read_usd_per_mtok' => 1.0,
                ],
                'claude-mythos-5' => [
                    'input_usd_per_mtok' => 10.0,
                    'output_usd_per_mtok' => 50.0,
                    'cache_write_5m_usd_per_mtok' => 12.50,
                    'cache_read_usd_per_mtok' => 1.0,
                ],
                'claude-opus-4-8' => [
                    'input_usd_per_mtok' => 5.0,
                    'output_usd_per_mtok' => 25.0,
                    'cache_write_5m_usd_per_mtok' => 6.25,
                    'cache_read_usd_per_mtok' => 0.50,
                ],
                'claude-opus-4-7' => [
                    'input_usd_per_mtok' => 5.0,
                    'output_usd_per_mtok' => 25.0,
                    'cache_write_5m_usd_per_mtok' => 6.25,
                    'cache_read_usd_per_mtok' => 0.50,
                ],
                'claude-opus-4-6' => [
                    'input_usd_per_mtok' => 5.0,
                    'output_usd_per_mtok' => 25.0,
                    'cache_write_5m_usd_per_mtok' => 6.25,
                    'cache_read_usd_per_mtok' => 0.50,
                ],
                'claude-opus-4-5' => [
                    'input_usd_per_mtok' => 5.0,
                    'output_usd_per_mtok' => 25.0,
                    'cache_write_5m_usd_per_mtok' => 6.25,
                    'cache_read_usd_per_mtok' => 0.50,
                ],
                'claude-sonnet-4-6' => [
                    'input_usd_per_mtok' => 3.0,
                    'output_usd_per_mtok' => 15.0,
                    'cache_write_5m_usd_per_mtok' => 3.75,
                    'cache_read_usd_per_mtok' => 0.30,
                ],
                'claude-sonnet-4-5' => [
                    'input_usd_per_mtok' => 3.0,
                    'output_usd_per_mtok' => 15.0,
                    'cache_write_5m_usd_per_mtok' => 3.75,
                    'cache_read_usd_per_mtok' => 0.30,
                ],
                'claude-haiku-4-5' => [
                    'input_usd_per_mtok' => 1.0,
                    'output_usd_per_mtok' => 5.0,
                    'cache_write_5m_usd_per_mtok' => 1.25,
                    'cache_read_usd_per_mtok' => 0.10,
                ],
            ],
        ],
    ],
];
