<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Comment;
class CommentController extends Controller
{
    public function index($supportDetailId)
{
    $comments = Comment::where('support_detail_id', $supportDetailId)
        ->with(['user.roles']) // carga el usuario y sus roles
        ->latest()
        ->get();

    return response()->json($comments);
}



public function store(Request $request)
{
    $request->validate([
        'support_detail_id' => 'required|exists:support_details,id',
        'comment' => 'required|string',
    ]);

    $comment = Comment::create([
        'support_detail_id' => $request->support_detail_id,
        'user_id' => auth()->id(),
        'comment' => $request->comment,
    ]);

    $comment->load('user.roles');

    return response()->json([
        'message' => 'Comentario agregado correctamente',
        'comment' => [
            'id' => $comment->id,
            'comment' => $comment->comment,
            'created_at' => Carbon::parse($comment->created_at)
                ->timezone('America/Lima')
                ->format('d/m/Y H:i'),
            'user' => [
                'names' => $comment->user->names,
                'roles' => $comment->user->roles->map(fn ($r) => ['name' => $r->name]),
            ],
        ],
    ]);
}

}
