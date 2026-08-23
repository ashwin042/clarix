<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The two ways linking a Telegram account to the task bot can be refused.
 *
 * A separate class from TelegramLinkException rather than a shared one, for the
 * same reason the whole integration is separate: the two bots answer to
 * different pipelines, and one day one of them will grow a third refusal that
 * the other must not inherit. The shape is copied on purpose so that a reader
 * who knows one recognises the other.
 *
 * invalidCode() deliberately covers three distinct facts — no such code, the
 * code expired, the code was already used — behind one sentence. Telling them
 * apart would tell an attacker whether a guess had ever been a real code, which
 * is exactly the oracle a short human-typed code cannot afford.
 */
class N8nTelegramLinkException extends RuntimeException
{
    protected function __construct(string $message, protected int $status)
    {
        parent::__construct($message);
    }

    public static function invalidCode(): self
    {
        return new self('That code is not valid. It may have expired or already been used.', 422);
    }

    public static function chatAlreadyLinked(): self
    {
        return new self('This Telegram account is already linked to another Clarix user. Disconnect it there first.', 409);
    }

    public function status(): int
    {
        return $this->status;
    }
}
