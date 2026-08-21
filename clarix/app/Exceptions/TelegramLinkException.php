<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The two ways linking a Telegram account can be refused.
 *
 * Modelled as one exception carrying a status rather than as two classes,
 * because the controller's only job is to turn a refusal into a status code,
 * and a single type keeps the catch site from growing a branch per outcome.
 *
 * invalidCode() deliberately covers three distinct facts — no such code, the
 * code expired, the code was already used — behind one sentence. Telling them
 * apart would tell an attacker whether a guess had ever been a real code,
 * which is exactly the oracle a short human-typed code cannot afford.
 */
class TelegramLinkException extends RuntimeException
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
