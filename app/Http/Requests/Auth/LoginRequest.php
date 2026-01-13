<?php

namespace App\Http\Requests\Auth;

use App\Rules\Cpf;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->looksLikeEmail($value)) {
                        return;
                    }

                    if (! Cpf::isValid($value)) {
                        $fail('Informe um email ou CPF valido.');
                    }
                },
            ],
            'password' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $login = $this->input('login');

        if ($login !== null && ! $this->looksLikeEmail($login)) {
            $this->merge([
                'login' => Cpf::normalize($login),
            ]);
        }
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = $this->input('login');
        $credentials = ['password' => $this->input('password')];

        if ($this->looksLikeEmail($login)) {
            $credentials['email'] = Str::lower((string) $login);
        } else {
            $credentials['cpf'] = Cpf::normalize($login);
        }

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        $login = (string) $this->string('login');
        $login = $this->looksLikeEmail($login)
            ? Str::lower($login)
            : Cpf::normalize($login);

        return Str::transliterate($login.'|'.$this->ip());
    }

    private function looksLikeEmail(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}
