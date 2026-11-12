<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Throwable;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->paginate(15);
        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', Password::min(8)],
            'role' => 'required|in:superadmin,admin,personal',
        ]);

        // Crear el usuario y marcar que debe cambiar su contraseña.
        $data['must_change_password'] = true;

        $user = User::create($data);

        try {
            // Enviar el enlace de restablecimiento mediante Laravel y Brevo.
            $status = PasswordBroker::sendResetLink([
                'email' => $user->email,
            ]);

            if ($status === PasswordBroker::RESET_LINK_SENT) {
                return redirect()
                    ->route('users.index')
                    ->with(
                        'success',
                        'Usuario creado. Se envió el correo para establecer su contraseña personal.'
                    );
            }

            return redirect()
                ->route('users.index')
                ->with(
                    'warning',
                    'Usuario creado, pero no se pudo generar el enlace. Verifica si puedes reenviarlo.'
                );

        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('users.index')
                ->with(
                    'warning',
                    'Usuario creado, pero ocurrió un error al enviar el correo. Revisa la configuración de Brevo.'
                );
        }
    }

    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::min(8)],
            'role' => 'required|in:superadmin,admin,personal',
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = bcrypt($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'Usuario actualizado');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Usuario eliminado');
    }
}