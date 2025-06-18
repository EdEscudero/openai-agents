<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    /*
    |--------------------------------------------------------------------------
    | Default Agent Settings
    |--------------------------------------------------------------------------
    |
    | These options allow you to configure your default agent. They correspond
    | to options available in the OpenAI Agents Python SDK.
    |
    */

    'default' => [
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
        'temperature' => env('OPENAI_TEMPERATURE', 0.7),
        'top_p' => env('OPENAI_TOP_P', 1.0),
    ],

    'tracing' => [
        'enabled' => env('AGENTS_TRACING', false),
        'processors' => [
            // callable list of trace processors
        ],
    ],
];
