<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        return Inertia::render('roles/index', [
    'roles' => Role::with('permissions')->paginate(10),
    'all_permissions' => Permission::all(), // 👈 cambia aquí
    'user_permissions' => auth()->user()
        ? auth()->user()->getAllPermissions()->pluck('name')->toArray()
        : [],
    'sidebarOpen' => request()->cookie('sidebar_state', 'true') === 'true',
    'auth' => [
        'user' => auth()->user(),
        'role' => optional(auth()->user()->roles()->first())->name,
    ],
]);


    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create(['name' => $request->name]);
        $role->syncPermissions($request->permissions);

        return response()->json(['message' => '✅ Rol creado correctamente', 'role' => $role->load('permissions')]);
    }

    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        return response()->json(['role' => $role]);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $role->id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->name = $request->name;
        $role->save();
        $role->syncPermissions($request->permissions);

        return response()->json(['message' => '✅ Rol actualizado correctamente', 'role' => $role->load('permissions')]);
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();

        return response()->json(['success' => true]);
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        Role::whereIn('id', $ids)->delete();

        return response()->json(['message' => 'Roles eliminados correctamente']);
    }
}
