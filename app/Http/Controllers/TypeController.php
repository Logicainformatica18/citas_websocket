<?php

namespace App\Http\Controllers;

use App\Models\Type;
use Inertia\Inertia;
use Illuminate\Http\Request;

/**
 * Catálogo de tipos · REBANADA 1 del port de Encuestas.
 *
 * Calcado de UserController: validación inline con $request->validate(),
 * asignación propiedad por propiedad, findOrFail($id), paginate(10),
 * response()->json() en las mutaciones e index con doble modo vía
 * wantsJson().
 *
 * ------------------------------------------------------------------
 * QUÉ CAMBIA RESPECTO AL TypeController ORIGINAL
 * ------------------------------------------------------------------
 *
 * · create() DESAPARECE. En el origen devolvía view("typetable"), un
 *   fragmento Blade con la tabla, y todos los métodos terminaban en
 *   `return $this->create()`. Acá lo reemplaza fetchPaginated(): mismo
 *   concepto (refrescar solo la tabla), pero devolviendo JSON.
 *
 * · edit(Request) SE RENOMBRA a show($id). El origen tenía la semántica
 *   invertida: `edit` devolvía el modelo para llenar el formulario y
 *   `show` era una búsqueda. Se alinea con UserController::show.
 *
 * · show(Request) del origen SE DESCARTA. Hacía:
 *       Type::where('description', 'like', $show)->all()
 *   `all()` no existe en el Query Builder de Eloquent, así que ese método
 *   lanzaba BadMethodCallException siempre. Esa búsqueda nunca funcionó,
 *   por lo tanto descartarla no es una regresión.
 *
 * · Se agrega validación. El origen no validaba nada, y `description` es
 *   NOT NULL en la tabla: mandar el formulario vacío tiraba error 500 de
 *   MySQL. Ahora devuelve 422 con los mensajes.
 */
class TypeController extends Controller
{
    /**
     * Página Inertia + modo JSON, igual que UserController::index.
     */
    public function index(Request $request)
    {
        $types = Type::orderBy('id', 'desc')->paginate(10);

        if ($request->wantsJson()) {
            return response()->json([
                'types' => $types,
            ]);
        }

        return Inertia::render('types/index', [
            'types' => $types,
        ]);
    }

    /**
     * Tabla paginada para refrescar después de una mutación.
     *
     * NOTA: UserController::fetchPaginated usa latest() mientras index()
     * usa orderBy('id','desc'). Son órdenes distintos (created_at vs id),
     * así que la tabla puede reordenarse sola al guardar. Acá se usa
     * orderBy('id','desc') en los dos para que sea estable — vale la pena
     * revisar si querés el mismo ajuste en users y roles.
     */
    public function fetchPaginated()
    {
        $types = Type::orderBy('id', 'desc')->paginate(10);

        return response()->json([
            'types' => $types,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'detail'      => 'nullable|string|max:255',
        ]);

        $type = new Type();

        $type->description = $request->description;
        $type->detail      = $request->detail;

        $type->save();

        return response()->json([
            'message' => 'Tipo creado',
            'type'    => $type,
        ]);
    }

    /**
     * Devuelve un tipo para llenar el formulario de edición.
     * Equivale al edit(Request) del origen.
     */
    public function show($id)
    {
        $type = Type::findOrFail($id);

        return response()->json([
            'type' => $type,
        ]);
    }

    public function update(Request $request, $id)
    {
        $type = Type::findOrFail($id);

        $request->validate([
            'description' => 'required|string|max:255',
            'detail'      => 'nullable|string|max:255',
        ]);

        $type->description = $request->description;
        $type->detail      = $request->detail;

        $type->save();

        return response()->json([
            'message' => 'Tipo actualizado',
            'type'    => $type,
        ]);
    }

    public function destroy($id)
    {
        $type = Type::findOrFail($id);
        $type->delete();

        return response()->json([
            'message' => 'Tipo eliminado',
        ]);
    }
}
