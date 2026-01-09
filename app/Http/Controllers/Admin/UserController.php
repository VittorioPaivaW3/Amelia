<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:'.implode(',', self::STAFF_ROLES)],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
        ]);

        return back()->with('status', 'Funcionario criado.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:'.implode(',', self::ALL_ROLES)],
        ]);

        if ($request->user()?->id === $user->id && $data['role'] !== 'admin') {
            return back()->with('status', 'Voce nao pode remover seu proprio acesso admin.');
        }

        $user->update([
            'role' => $data['role'],
        ]);

        return back()->with('status', 'Permissao atualizada.');
    }
}
