<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChatbotMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxLength = (int) config('ai.chatbot.max_message_length', 2000);
        $maxHistory = (int) config('ai.chatbot.max_history_messages', 12);

        return [
            'message' => ['required', 'string', 'min:1', 'max:'.$maxLength],

            // The transcript is replayed to the model, so its shape is enforced
            // here and its content re-filtered in the service: only the user's
            // own turns and the assistant's own answers may come back.
            'history' => ['sometimes', 'array', 'max:'.$maxHistory],
            'history.*.role' => ['required', 'string', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required', 'string', 'max:'.$maxLength],
        ];
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    public function history(): array
    {
        return $this->validated('history', []);
    }

    public function message(): string
    {
        return trim($this->validated('message'));
    }
}
