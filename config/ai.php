<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active provider
    |--------------------------------------------------------------------------
    |
    | Which ChatDriver answers the assistant. Both drivers expose the same tool
    | catalogue, so switching provider changes no application behaviour — only
    | the wire format and the bill.
    |
    | Supported: "gemini", "openai".
    |
    */

    'driver' => env('AI_DRIVER', 'gemini'),

    /*
    |--------------------------------------------------------------------------
    | Provider credentials
    |--------------------------------------------------------------------------
    */

    'providers' => [

        'gemini' => [
            // Google AI Studio key. Current keys are issued in the "AQ." format;
            // legacy "AIzaSy" keys work too. Both go in the x-goog-api-key
            // header — see GeminiDriver for why Bearer auth is not an option.
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            // Flash-Lite picks and chains our tools as reliably as the full
            // Flash model at roughly half the latency, and its free-tier quota
            // survives a real conversation — one assistant message can cost
            // three API calls once tools are involved.
            'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
            'temperature' => (float) env('GEMINI_TEMPERATURE', 0.2),
            // Thinking tokens are billed against this budget, so a limit sized
            // for a plain answer can be spent entirely on reasoning and return
            // an empty candidate.
            'max_tokens' => (int) env('GEMINI_MAX_TOKENS', 2048),
            // "low" keeps the assistant responsive; raise it if you start
            // asking it multi-step analytical questions.
            'thinking_level' => env('GEMINI_THINKING_LEVEL', 'low'),
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'temperature' => (float) env('OPENAI_TEMPERATURE', 0.2),
            'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 900),
            // Only needed on accounts that scope usage per organisation/project.
            'organization' => env('OPENAI_ORGANIZATION'),
            'project' => env('OPENAI_PROJECT'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Chatbot behaviour
    |--------------------------------------------------------------------------
    |
    | Provider-independent: these govern the conversation loop itself.
    |
    */

    'chatbot' => [
        // Master switch: the widget hides itself when the feature is disabled.
        'enabled' => (bool) env('CHATBOT_ENABLED', true),

        // Seconds to wait for a single completion call.
        'timeout' => (int) env('AI_TIMEOUT', 45),

        // How many completion round-trips a single user message may trigger.
        // Each extra round is one more batch of tool calls, so this caps both
        // the latency and the spend of a runaway conversation.
        'max_tool_rounds' => (int) env('CHATBOT_MAX_TOOL_ROUNDS', 4),

        // Turns of prior conversation replayed to the model. The client sends
        // the transcript, so this is also the trust boundary on its size.
        'max_history_messages' => (int) env('CHATBOT_MAX_HISTORY', 12),

        // Characters accepted in a single user message.
        'max_message_length' => (int) env('CHATBOT_MAX_MESSAGE_LENGTH', 2000),

        // Requests per minute, per user.
        'rate_limit' => (int) env('CHATBOT_RATE_LIMIT', 20),
    ],

];
