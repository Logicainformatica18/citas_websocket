<?php

namespace App\Http\Controllers;
use App\Models\Article;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ArticlesExport;
use Illuminate\Support\Facades\Log;
class ArticleController extends Controller
{

  public function index(Request $request)
{
    $articles = Article::latest()->orderBy('id','desc')->paginate(7);

    if ($request->wantsJson()) {
        return response()->json([
            'data' => $articles->items(),
            'current_page' => $articles->currentPage(),
            'last_page' => $articles->lastPage(),
        ]);
    }

    return Inertia::render('articles/index', [
        'articles' => $articles,
    ]);
}

 public function fetchPaginated(Request $request)
{
    $query = Article::query();

    if ($request->has('transfer_id')) {
        $query->where('transfer_id', $request->transfer_id);
    }

    $articles = $query->latest()->orderByDesc('id')->paginate($request->input('per_page', 7));

    return response()->json([
        'data' => $articles->items(),
        'current_page' => $articles->currentPage(),
        'last_page' => $articles->lastPage(),
    ]);
}

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'details' => 'nullable|string',
            'quanty' => 'nullable|integer|min:0',
            'price' => 'nullable|numeric|min:0',
            'code' => 'nullable|string|max:50',
            'condition' => 'nullable|string|max:50',
            'state' => 'nullable|string|max:50',
            'transfer_id' => 'required|exists:transfers,id',
            'product_id' => 'required|exists:products,id',

            'file_1' => 'nullable|file|max:2048',
            'file_2' => 'nullable|file|max:2048',
            'file_3' => 'nullable|file|max:2048',
            'file_4' => 'nullable|file|max:2048',
        ]);

        $article = new Article();
        $article->title = $request->title;
        $article->description = $request->description;
        $article->details = $request->details;
        $article->quanty = $request->quanty;
        $article->price = $request->price;
        $article->code = $request->code;
        $article->condition = $request->condition;
        $article->state = $request->state;
        $article->transfer_id = $request->transfer_id;
        $article->product_id = $request->product_id;

        foreach (['file_1', 'file_2', 'file_3', 'file_4'] as $field) {
            if ($request->hasFile($field)) {
                $article->$field = fileStore($request->file($field), 'uploads');
            }
        }

        $article->save();

        return response()->json([
            'message' => '✅ Artículo creado correctamente',
            'article' => $article,
        ]);
    }
  
    
    public function bulkStore(Request $request)
    {
        $request->validate([
            'transfer_id' => 'required|exists:transfers,id',
            'articles' => 'required|array|min:1',
            'articles.*.description' => 'required|string|max:255',
            'articles.*.quanty' => 'required|integer|min:1',
            'articles.*.product_id' => 'required|exists:products,id',
            'articles.*.title' => 'nullable|string|max:255',
            'articles.*.details' => 'nullable|string',
            'articles.*.price' => 'nullable|numeric|min:0',
            'articles.*.code' => 'nullable|string|max:50',
            'articles.*.condition' => 'nullable|string|max:50',
            'articles.*.state' => 'nullable|string|max:50',
            'articles.*.file_1' => 'nullable|file|max:2048',
            'articles.*.file_2' => 'nullable|file|max:2048',
            'articles.*.file_3' => 'nullable|file|max:2048',
            'articles.*.file_4' => 'nullable|file|max:2048',
        ]);
    
        foreach ($request->articles as $index => $articleData) {
            $article = new Article();
            $article->title = $articleData['title'] ?? $articleData['description'];
            $article->description = $articleData['description'];
            $article->details = $articleData['details'] ?? null;
            $article->quanty = $articleData['quanty'];
            $article->price = $articleData['price'] ?? 0;
            $article->code = $articleData['code'] ?? '';
            $article->condition = $articleData['condition'] ?? '';
            $article->state = $articleData['state'] ?? '';
            $article->transfer_id = $request->transfer_id;
            $article->product_id = $articleData['product_id'];
    
            // ✅ Procesar imágenes (file_1, file_2, file_3, file_4)
            foreach (['file_1', 'file_2', 'file_3', 'file_4'] as $field) {
                if ($request->hasFile("articles.$index.$field")) {
                    $article->$field = fileStore($request->file("articles.$index.$field"), 'uploads');
                }
            }
    
            $article->save();
        }
    
        return response()->json(['message' => '✅ Artículos guardados correctamente']);
    }
    
    

    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'details' => 'nullable|string',
            'quanty' => 'nullable|integer|min:0',
            'price' => 'nullable|numeric|min:0',
            'code' => 'nullable|string|max:50',
            'condition' => 'nullable|string|max:50',
            'state' => 'nullable|string|max:50',
            'product_id' => 'required|exists:products,id', // ✅ validación agregada
    
            'file_1' => 'nullable|file|max:2048',
            'file_2' => 'nullable|file|max:2048',
            'file_3' => 'nullable|file|max:2048',
            'file_4' => 'nullable|file|max:2048',
        ]);
    
        $article = Article::findOrFail($id);
    
        $article->fill($request->except('file_1', 'file_2', 'file_3', 'file_4'));
    
        foreach (['file_1', 'file_2', 'file_3', 'file_4'] as $field) {
            if ($request->hasFile($field)) {
                Log::debug("📂 Procesando {$field}", [
                    'originalName' => $request->file($field)->getClientOriginalName(),
                    'size' => $request->file($field)->getSize(),
                    'type' => $request->file($field)->getMimeType(),
                ]);
    
                $article->$field = fileUpdate(
                    $request->file($field),
                    'uploads',
                    $article->$field
                );
            } else {
                Log::debug("⚠️ No se recibió {$field}");
            }
        }
    
        $article->save();
    
        Log::info("✅ Artículo actualizado", ['id' => $article->id]);
    
        return response()->json(['article' => $article], 200);
    }
    


    public function show($id)
{
    $article = Article::findOrFail($id);
    return response()->json(['article' => $article]);
}

    public function destroy($id)
    {
        Article::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
    public function bulkDelete(Request $request)
{
    $ids = $request->input('ids', []);
    Article::whereIn('id', $ids)->delete();

    return response()->json(['message' => 'Eliminados correctamente']);
}
public function exportExcel($id)

{
    return Excel::download(new ArticlesExport($id), "transfer_{$id}_articulos.xlsx");
}
}
