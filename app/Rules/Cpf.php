<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cpf implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid($value)) {
            $fail('CPF invalido.');
        }
    }

    public static function normalize(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value);
    }

    public static function isValid(mixed $value): bool
    {
        $cpf = self::normalize($value);

        if (strlen($cpf) !== 11) {
            return false;
        }

        if (preg_match('/^(\\d)\\1{10}$/', $cpf)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $cpf[$i]) * (10 - $i);
        }
        $check = 11 - ($sum % 11);
        $digit1 = $check >= 10 ? 0 : $check;

        if ((int) $cpf[9] !== $digit1) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += ((int) $cpf[$i]) * (11 - $i);
        }
        $check = 11 - ($sum % 11);
        $digit2 = $check >= 10 ? 0 : $check;

        return (int) $cpf[10] === $digit2;
    }
}
