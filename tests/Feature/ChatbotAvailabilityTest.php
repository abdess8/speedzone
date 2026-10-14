<?php

use App\Services\Chatbot\ChatbotService;

test('the assistant stays disabled in production even when the flag is on', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['ai.chatbot.enabled' => true]);

    expect(app(ChatbotService::class)->isEnabled())->toBeFalse();
});
