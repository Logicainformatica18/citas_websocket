<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
 public function index(Request $request)
{
    $users = User::with('roles')->orderBy('id', 'desc')->paginate(10);
    $roles = Role::all();

    if ($request->wantsJson()) {
        return response()->json([
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    return Inertia::render('users/index', [
        'users' => $users,
        'roles' => $roles,
    ]);
}


    public function syncRoles(Request $request, $id)
    {
        $request->validate([
            'roles' => 'array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        $user = User::findOrFail($id);
        $user->syncRoles($request->roles);

        return response()->json(['message' => 'Roles actualizados correctamente']);
    }

    public function store(Request $request)
    {
        $request->validate([
            'dni' => 'nullable|string|max:100',
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'names' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'sex' => 'nullable|string|max:1',
            'datebirth' => 'nullable|date',
            'cellphone' => 'nullable|string|max:20',
            'role' => 'nullable|string|exists:roles,name',
            'photo' => 'nullable|file|max:2048',
        ]);

        $user = new User();
        $user->fill($request->only(['dni', 'firstname', 'lastname', 'names', 'email', 'sex', 'datebirth', 'cellphone']));
        $user->password = Hash::make($request->password);

        if ($request->hasFile('photo')) {
            $user->photo = fileStore($request->file('photo'), 'imageusers');
        }

        $user->save();

        if ($request->role) {
            $user->assignRole($request->role);
        }

        return response()->json(['message' => '✅ Usuario creado', 'user' => $user]);
    }

    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);
        return response()->json(['user' => $user]);
    }

    public function fetchPaginated()
    {
        $users = User::with('roles')->latest()->paginate(10);
        return response()->json(['users' => $users]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'names' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'sex' => 'nullable|string|max:1',
            'datebirth' => 'nullable|date',
            'cellphone' => 'nullable|string|max:20',
            'role' => 'nullable|string|exists:roles,name',
            'photo' => 'nullable|file|max:2048',
        ]);

        $user->fill($request->except(['password', 'role', 'photo']));

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        if ($request->hasFile('photo')) {
            $user->photo = fileUpdate($request->file('photo'), 'imageusers', $user->photo);
        }

        $user->save();

        if ($request->has('role')) {
            $user->syncRoles([$request->role]);
        }

        return response()->json(['message' => '✅ Usuario actualizado', 'user' => $user]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => '✅ Usuario eliminado']);
    }
}
