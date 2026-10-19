<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * unique:users,email without the case sensitivity: on Postgres 'Ana@x.com' and 'ana@x.com'
 * are different strings, so the plain rule let one person register twice.
 */
class UniqueEmail implements ValidationRule
{
    public function __construct(
        private string $column = 'email',
        private ?int $ignoreUserId = null,
        private ?string $message = null,
    ) {
        if (!in_array($column, ['email', 'gmail'], true)) {
            throw new \InvalidArgumentException("UniqueEmail cannot check the {$column} column.");
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $taken = User::whereRaw("lower({$this->column}) = ?", [User::normalizeEmail((string) $value)])
            ->when($this->ignoreUserId, fn ($query) => $query->whereKeyNot($this->ignoreUserId))
            ->exists();

        if ($taken) {
            $this->message ? $fail($this->message) : $fail('validation.unique')->translate();
        }
    }
}
