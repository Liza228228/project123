<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        $roles = Role::query()
            ->whereNotIn('id', [5, 7])
            ->orderBy('name')
            ->get();

        return view('auth.register', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'surname' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'patronymic' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'role_id' => ['required', 'integer', 'exists:roles,id', 'not_in:5,7'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'surname.required' => 'Укажите фамилию.',
            'name.required' => 'Укажите имя.',
            'patronymic.required' => 'Укажите отчество.',
            'email.required' => 'Укажите адрес почты.',
            'email.email' => 'Введите корректный адрес электронной почты.',
            'email.unique' => 'Эта почта уже занята. Войдите или укажите другой адрес.',
            'role_id.required' => 'Выберите роль.',
            'role_id.exists' => 'Выбранная роль недоступна.',
            'password.required' => 'Придумайте пароль.',
            'password.confirmed' => 'Пароли не совпадают. Введите одинаковые значения.',
        ]);

        $user = User::create([
            'surname' => $request->surname,
            'name' => $request->name,
            'patronymic' => $request->patronymic,
            'email' => $request->email,
            'role_id' => $request->role_id,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
