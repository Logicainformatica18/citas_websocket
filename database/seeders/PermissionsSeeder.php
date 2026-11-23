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
        // Idempotente: crea solo los permisos que falten.
        foreach ([...$legacy, ...$app] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Administrador: todo menos Canal.* (sin permiso de canal, el backend
        // respeta el canal elegido en el formulario).
        $this->createRole('Administrador', Permission::where('name', 'not like', 'Canal.%')->pluck('name')->all());

        // Agente de atención (call center / WhatsApp).
        $this->createRole('ATC', [
            'solicitudes.ver', 'solicitudes.actualizar', 'solicitudes.acciones_avanzadas',
            'generar_ticket', 'reserva', 'clientes', 'Canal.whatsapp',
        ]);

        // Agente de oficina presencial: el backend ignora el canal para este rol.
        $this->createRole('ATC_Oficina', [
            'solicitudes.ver', 'solicitudes.actualizar', 'generar_ticket', 'recepcion', 'clientes',
        ]);

        // Rol original: todos los permisos legacy excepto "usuarios" (+ ver solicitudes).
        $this->createRole('Asistente', [
            ...array_values(array_diff($legacy, ['usuarios'])),
            'solicitudes.ver',
        ]);
    }

    /**
     * Crea el rol con sus permisos. Si ya existe (p. ej. en producción) NO se
     * modifican sus permisos, para no pisar configuraciones hechas desde /roles.
     */
    private function createRole(string $name, array $permissions): void
    {
        if (Role::where('name', $name)->where('guard_name', 'web')->exists()) {
            $this->command?->line("Rol {$name} ya existe: no se modifican sus permisos.");

            return;
        }

        Role::create(['name' => $name, 'guard_name' => 'web'])->syncPermissions($permissions);
    }
}
