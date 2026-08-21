<?php

namespace App\Http\Requests\Api;

use App\Services\TelegramLinkService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorisation is the middleware's job here, not this class's: the caller is
 * the bot, already proven by signature, and the code inside the body is what
 * names the person. So this validates shape only.
 */
class VerifyTelegramLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Length is checked after normalisation, so a code typed with a
            // dash or a stray space is not rejected before it is cleaned up.
            'code' => ['required', 'string', 'max:64'],

            // Telegram ids exceed 32 bits, so this is bounded as a big integer
            // rather than left to a default that would reject real accounts.
            'chat_id' => ['required', 'integer', 'min:1', 'max:9223372036854775807'],
        ];
    }

    public function code(): string
    {
        return TelegramLinkService::normalize((string) $this->input('code'));
    }

    public function chatId(): int
    {
        return (int) $this->input('chat_id');
    }
}
