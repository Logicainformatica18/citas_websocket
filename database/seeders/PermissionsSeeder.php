<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles y permisos. Incluye los permisos originales del seeder y todos los
 * que el código consulta:
 *  - backend: generar_ticket, Canal.* (SupportController@store), rol ATC / ATC_Oficina
 *  - frontend: administrar, reserva, recepcion, solicitudes.ver|actualizar|eliminar|acciones_avanzadas,
 *              Canal.whatsapp_presencial
 */
class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $legacy = [
            'clientes', 'usuarios', 'blogs', 'proyectos', 'imagenes', 'secciones',
            'editar_plantilla', 'editar_inicio', 'editar_nosotros', 'editar_blog',
            'editar_proyectos', 'editar_contactos', 'agregar', 'editar', 'actualizar', 'eliminar',
        ];
        $app = [
            'administrar', 'reserva', 'recepcion', 'generar_ticket',
            'solicitudes.ver', 'solicitudes.actualizar', 'solicitudes.eliminar', 'solicitudes.acciones_avanzadas',
            'Canal.whatsapp', 'Canal.presencial', 'Canal.whatsapp_presencial',
        ];
        foreach ([...$legacy, ...$app] as $name) {
            Permission::create(['name' => $name]);
        }

        // Administrador: todo menos Canal.* (sin permiso de canal, el backend
        // respeta el canal elegido en el formulario).
        Role::create(['name' => 'Administrador'])->syncPermissions(
            Permission::where('name', 'not like', 'Canal.%')->get()
        );

        // Agente de atención (call center / WhatsApp).
        Role::create(['name' => 'ATC'])->syncPermissions([
            'solicitudes.ver', 'solicitudes.actualizar', 'solicitudes.acciones_avanzadas',
            'generar_ticket', 'reserva', 'clientes', 'Canal.whatsapp',
        ]);

        // Agente de oficina presencial: el backend ignora el canal para este rol.
        Role::create(['name' => 'ATC_Oficina'])->syncPermissions([
            'solicitudes.ver', 'solicitudes.actualizar', 'generar_ticket', 'recepcion', 'clientes',
        ]);

        // Rol original: todos los permisos legacy excepto "usuarios" (+ ver solicitudes).
        Role::create(['name' => 'Asistente'])->syncPermissions([
            ...array_values(array_diff($legacy, ['usuarios'])),
            'solicitudes.ver',
        ]);
    }
}
