<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Same posture as VerifyTelegramLinkRequest: the bot is already proven by its
 * signature, so this only bounds the shape of what it sent.
 */
class ResolveTelegramChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'chat_id' => ['required', 'integer', 'min:1', 'max:9223372036854775807'],
        ];
    }

    public function chatId(): int
    {
        return (int) $this->input('chat_id');
    }
}
