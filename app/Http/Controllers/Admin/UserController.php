<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class UserController extends Controller
{
    private const STAFF_ROLES = ['mkt', 'juridico', 'rh'];
    private const ALL_ROLES = ['user', 'mkt', 'juridico', 'rh', 'admin'];

    public function index(): View
    {
        $users = User::query()
            ->orderByDesc('created_at')
            ->get();

        return view('admin.users', [
            'users' => $users,
            'staffRoles' => self::STAFF_ROLES,
            'roles' => self::ALL_ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'cpf' => Cpf::normalize($request->input('cpf')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'cpf' => ['required', 'string', 'size:11', 'unique:'.User::class, new Cpf()],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:'.implode(',', self::STAFF_ROLES)],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'cpf' => $data['cpf'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
        ]);

        return back()->with('status', 'Funcionario criado.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($request->has('cpf')) {
            $request->merge([
                'cpf' => Cpf::normalize($request->input('cpf')),
            ]);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
            'cpf' => [
                'sometimes',
                'required',
                'string',
                'size:11',
                Rule::unique(User::class)->ignore($user->id),
                new Cpf(),
            ],
            'role' => ['sometimes', 'required', 'in:'.implode(',', self::ALL_ROLES)],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ]);

        if (array_key_exists('role', $data)) {
            if ($request->user()?->id === $user->id && $data['role'] !== 'admin') {
                return back()->with('status', 'Voce nao pode remover seu proprio acesso admin.');
            }
        }

        $updates = [];
        if (array_key_exists('name', $data)) {
            $updates['name'] = $data['name'];
        }
        if (array_key_exists('email', $data)) {
            $updates['email'] = $data['email'];
        }
        if (array_key_exists('cpf', $data)) {
            $updates['cpf'] = $data['cpf'];
        }
        if (array_key_exists('role', $data)) {
            $updates['role'] = $data['role'];
        }
        if (! empty($data['password'] ?? '')) {
            $updates['password'] = Hash::make($data['password']);
        }

        if (! empty($updates)) {
            $user->update($updates);
        }

        return back()->with('status', 'Usuario atualizado.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()?->id === $user->id) {
            return back()->with('status', 'Voce nao pode excluir seu proprio usuario.');
        }

        $user->delete();

        return back()->with('status', 'Usuario excluido.');
    }
}
