<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required_without:correo',
            'correo'   => 'required_without:email',
            'password' => 'required'
        ]);

        $email = $request->input('correo') ?? $request->input('email');
        $password = $request->input('password');

        $user = User::with('rol')->where('correo', $email)->first();

        if (!$user || !Hash::check($password, $user->contrasenia)) {
            AuditLog::registrar('LOGIN_FAIL', null, ['descripcion' => 'Credenciales incorrectas', 'correo_intento' => $email]);
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        AuditLog::registrar('LOGIN_OK', $user->id, ['descripcion' => 'Inicio de sesión exitoso']);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Login exitoso',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => [
                'id'       => $user->id,
                'name'     => $user->name,
                'nombre'   => $user->nombre,
                'apellido' => $user->apellido,
                'email'    => $user->email,
                'correo'   => $user->correo,
                'role'     => $user->role,
                'rol'      => $user->rol?->nombre,
                'activo'   => $user->activo,
            ]
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->load('rol');
        }
        return response()->json([
            'id'       => $user->id,
            'name'     => $user->name,
            'nombre'   => $user->nombre,
            'apellido' => $user->apellido,
            'email'    => $user->email,
            'correo'   => $user->correo,
            'role'     => $user->role,
            'rol'      => $user->rol?->nombre,
            'activo'   => $user->activo,
        ]);
    }

    public function logout(Request $request)
    {
        AuditLog::registrar('LOGOUT', $request->user()->id, ['descripcion' => 'Cierre de sesión']);
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logout exitoso']);
    }
}
