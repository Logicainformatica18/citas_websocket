<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\InternalState;
use App\Events\RecordChanged;
use App\Models\Support;
use App\Models\SupportDetail;
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
            'support_detail_id' => 'required|exists:support_details,id',
            'comment' => 'required|string',
            'internal_state_id' => 'required|exists:internal_states,id', // ✅ validación añadida
        ]);

        $comment = Comment::create([
            'support_detail_id' => $request->support_detail_id,
            'user_id' => auth()->id(),
            'comment' => $request->comment,
            'internal_state_id' => $request->internal_state_id, // ✅ nuevo campo
        ]);
$file = $request->file('file_1');
if ($file) {
    $filename = fileStore($file, 'uploads', 'file'); // guarda en public/comments
    $comment->file_1 = $filename;
    $comment->save();
}
        $comment->load('user.roles', 'internalState'); // ✅ carga relación
        $support = Support::whereHas('details', function ($q) use ($comment) {
            $q->where('id', $comment->support_detail_id);
        })->firstOrFail();
        $support->load([
            'client:id_cliente,Razon_Social,telefono,email,direccion,dni',
            'creator:id,firstname,lastname,names',
            'details:id,support_id,subject,description,priority,type,status,reservation_time,attended_at,derived,Manzana,comment,attachment,project_id,area_id,id_motivos_cita,id_tipo_cita,id_dia_espera,internal_state_id,external_state_id,type_id,ticket,channel',
            'details.area:id_area,descripcion',
            'details.project:id_proyecto,descripcion',
            'details.motivoCita:id_motivos_cita,nombre_motivo',
            'details.tipoCita:id_tipo_cita,tipo',
            'details.diaEspera:id_dias_espera,dias',
            'details.internalState:id,description',
            'details.externalState:id,description',
            'details.supportType:id,description',
            'details.type:id,description',
            'details.lastComment.internalState:id,description',
        ]);

        broadcast(new RecordChanged('Support', 'updated', $support->toArray()))->toOthers();

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
                    'roles' => $comment->user->roles->map(fn($r) => ['name' => $r->name]),
                ],
                'internal_state' => [
                    'id' => $comment->internalState?->id,
                    'description' => $comment->internalState?->description,
                ],
            ],
        ]);
    }

}
