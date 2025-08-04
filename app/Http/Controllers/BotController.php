<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BotController extends Controller
{
public function analyzeImages(Request $request)
{
    $request->validate([
        'images' => 'required|array',
        'images.*' => 'required|image|max:4096',
    ]);

    $results = [];

    foreach ($request->file('images') as $image) {
        $base64 = base64_encode(file_get_contents($image->getRealPath()));
        $mime = $image->getMimeType();
        $filename = $image->getClientOriginalName();

        $response = Http::withToken(env('OPENAI_API_KEY'))->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => 'Describe brevemente el contenido del voucher.'],
                        ['type' => 'image_url', 'image_url' => [
                            'url' => "data:$mime;base64,$base64"
                        ]]
                    ]
                ]
            ],
            'max_tokens' => 500,
            'temperature' => 0.2,
        ]);

        $content = $response->json('choices.0.message.content');

        $results[] = [
            'filename' => $filename,
            'response' => $content ?? 'Sin respuesta',
        ];
    }

    return response()->json(['data' => $results]);
}

}
