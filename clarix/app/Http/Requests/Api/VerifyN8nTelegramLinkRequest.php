<?php

namespace App\Http\Requests\Api;

use App\Services\N8nTelegramLinkService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorisation is the middleware's job here, not this class's: the caller is
 * the pipeline, already proven by its shared key, and the code inside the body
 * is what names the person. So this validates shape only.
 */
class VerifyN8nTelegramLinkRequest extends FormRequest
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

            // A string, matching the column. Telegram sends a JSON number, and
            // a workflow node may forward it as either — 'integer' would reject
            // the string spelling and 'numeric' would accept 1.5, so the shape
            // is pinned with a pattern instead. The leading minus is required:
            // group and channel ids are negative.
            'chat_id' => ['required', 'string', 'max:64', 'regex:/^-?\d{1,19}$/'],
        ];
    }

    /**
     * Telegram sends chat_id as a JSON number and n8n forwards whatever the
     * node produced, so the rules above would reject a perfectly ordinary
     * integer body. Casting before validation rather than loosening the rule
     * keeps one accepted shape in the column and one rule to read.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('chat_id') && is_int($this->input('chat_id'))) {
            $this->merge(['chat_id' => (string) $this->input('chat_id')]);
        }
    }

    public function code(): string
    {
        return N8nTelegramLinkService::normalize((string) $this->input('code'));
    }

    public function chatId(): string
    {
        return N8nTelegramLinkService::normalizeChatId((string) $this->input('chat_id'));
    }
}
