<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Inertia\Inertia;
use Illuminate\Http\Request;

/**
 * Catálogo de categorías · REBANADA 2 del port de Encuestas.
 *
 * Calcado de TypeController y UserController: validación inline con
 * $request->validate(), asignación propiedad por propiedad, findOrFail($id),
 * paginate(10), response()->json() en las mutaciones e index con doble modo.
 */
class CategoryController extends Controller
{
    /**
     * Página Inertia + modo JSON, igual que TypeController::index.
     */
    public function index(Request $request)
    {
        $categories = Category::orderBy('id', 'desc')->paginate(10);

        if ($request->wantsJson()) {
            return response()->json([
                'categories' => $categories,
            ]);
        }

        return Inertia::render('categories/index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Tabla paginada para refrescar después de una mutación.
     */
    public function fetchPaginated()
    {
        $categories = Category::orderBy('id', 'desc')->paginate(10);

        return response()->json([
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'detail'      => 'nullable|string|max:255',
        ]);

        $category = new Category();

        $category->description = $request->description;
        $category->detail      = $request->detail;

        $category->save();

        return response()->json([
            'message' => 'Categoría creada',
            'category' => $category,
        ]);
    }

    /**
     * Devuelve una categoría para llenar el formulario de edición.
     */
    public function show($id)
    {
        $category = Category::findOrFail($id);

        return response()->json([
            'category' => $category,
        ]);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'description' => 'required|string|max:255',
            'detail'      => 'nullable|string|max:255',
        ]);

        $category->description = $request->description;
        $category->detail      = $request->detail;

        $category->save();

        return response()->json([
            'message' => 'Categoría actualizada',
            'category' => $category,
        ]);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json([
            'message' => 'Categoría eliminada',
        ]);
    }
}
