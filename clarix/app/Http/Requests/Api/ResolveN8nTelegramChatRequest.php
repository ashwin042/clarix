<?php

namespace App\Http\Requests\Api;

use App\Services\N8nTelegramLinkService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Same posture as VerifyN8nTelegramLinkRequest: the pipeline is already proven
 * by its shared key, so this only bounds the shape of what it sent.
 */
class ResolveN8nTelegramChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'chat_id' => ['required', 'string', 'max:64', 'regex:/^-?\d{1,19}$/'],
        ];
    }

    /** See VerifyN8nTelegramLinkRequest: n8n may forward chat_id as a number. */
    protected function prepareForValidation(): void
    {
        if ($this->has('chat_id') && is_int($this->input('chat_id'))) {
            $this->merge(['chat_id' => (string) $this->input('chat_id')]);
        }
    }

    public function chatId(): string
    {
        return N8nTelegramLinkService::normalizeChatId((string) $this->input('chat_id'));
    }
}
