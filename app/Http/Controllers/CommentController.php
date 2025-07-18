<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\InternalState;
use Illuminate\Support\Facades\Log; // ✅ añadimos Log para registrar errores
class CommentController extends Controller
{


public function index($supportDetailId)
{
    $comments = Comment::where('support_detail_id', $supportDetailId)
        ->with(['user.roles', 'internalState']) // ✅ agregamos internalState
        ->latest()
        ->get();

      $internalStates = InternalState::select('id', 'description')
    ->where('description', '!=', 'Cerrado')
    ->get();

    return response()->json([
        'comments' => $comments,
        'internal_states' => $internalStates,
    ]);
}




public function store(Request $request)
{


    $request->validate([
        'support_detail_id'   => 'required|exists:support_details,id',
        'comment'             => 'required|string',
        'internal_state_id'   => 'required|exists:internal_states,id', // ✅ validación añadida
    ]);

    $comment = Comment::create([
        'support_detail_id'   => $request->support_detail_id,
        'user_id'             => auth()->id(),
        'comment'             => $request->comment,
        'internal_state_id'   => $request->internal_state_id, // ✅ nuevo campo
    ]);

    $comment->load('user.roles', 'internalState'); // ✅ carga relación

    return response()->json([
        'message' => 'Comentario agregado correctamente',
        'comment' => [
            'id'         => $comment->id,
            'comment'    => $comment->comment,
            'created_at' => Carbon::parse($comment->created_at)
                                ->timezone('America/Lima')
                                ->format('d/m/Y H:i'),
            'user'       => [
                'names' => $comment->user->names,
                'roles' => $comment->user->roles->map(fn ($r) => ['name' => $r->name]),
            ],
            'internal_state' => [
                'id'          => $comment->internalState?->id,
                'description' => $comment->internalState?->description,
            ],
        ],
    ]);
}

}
