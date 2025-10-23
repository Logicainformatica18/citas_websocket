<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReportQuery extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'question',
        'interpreter',
        'component',
        'description',
        'tags',
        'is_active',
        'has_ai_response',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_active' => 'boolean',
        'has_ai_response' => 'boolean',
    ];

    /* -------------------------------------------------------------------------
     | 🔹 Relaciones
     * ----------------------------------------------------------------------*/

    /**
     * Relación con historial de chats (para trazabilidad)
     */
    public function chatHistories()
    {
        return $this->hasMany(ChatHistory::class);
    }

    /* -------------------------------------------------------------------------
     | 🔹 Scopes (filtros reutilizables)
     * ----------------------------------------------------------------------*/

    /**
     * Solo los registros activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Buscar por categoría
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Buscar por texto o tags
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('question', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhereJsonContains('tags', $term);
        });
    }

    /* -------------------------------------------------------------------------
     | 🔹 Métodos estáticos
     * ----------------------------------------------------------------------*/

    /**
     * Encuentra una pregunta que coincida con la consulta natural del usuario.
     */
    public static function matchUserQuery(string $message): ?self
    {
        $normalized = Str::lower($message);
        $queries = static::active()->get();

        return $queries->first(function ($q) use ($normalized) {
            if (Str::contains(Str::lower($q->question), $normalized)) {
                return true;
            }

            if (!empty($q->tags)) {
                foreach ($q->tags as $tag) {
                    if (Str::contains($normalized, Str::lower($tag))) {
                        return true;
                    }
                }
            }

            return false;
        });
    }

    /**
     * Devuelve todas las categorías disponibles
     */
    public static function getCategories(): array
    {
        return static::select('category')
            ->distinct()
            ->pluck('category')
            ->toArray();
    }

    /* -------------------------------------------------------------------------
     | 🔹 Helpers y mutators
     * ----------------------------------------------------------------------*/

    public function getDisplayNameAttribute()
    {
        return "{$this->category} → {$this->question}";
    }

    public function getShortDescriptionAttribute()
    {
        return Str::limit(strip_tags($this->description), 120);
    }

    /**
     * Determina si debe usarse IA para generar explicación
     */
    public function usesAI(): bool
    {
        return (bool) $this->has_ai_response;
    }

    /**
     * Devuelve un prompt base para IA basado en su descripción
     */
    public function getPromptForAI(): string
    {
        $desc = $this->description ?? 'Proporciona una explicación interpretativa del resultado obtenido.';
        return "Eres un asistente especializado en analítica de empleabilidad y tendencias tecnológicas. 
El usuario ha consultado: '{$this->question}'. 
Tu función es explicar con claridad los resultados obtenidos, contextualizando con el análisis de tendencias y métricas globales. 
Descripción del reporte: {$desc}";
    }

    /* -------------------------------------------------------------------------
     | 🔹 Utilitarios
     * ----------------------------------------------------------------------*/

    /**
     * Duplica un registro para edición rápida (versión temporal)
     */
    public function duplicate(): self
    {
        $copy = $this->replicate();
        $copy->question = '[Copia] ' . $this->question;
        $copy->is_active = false;
        $copy->save();

        return $copy;
    }

    /**
     * Exporta en formato estructurado (por ejemplo, para PDF o JSON)
     */
    public function toStructuredArray(): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'question' => $this->question,
            'interpreter' => $this->interpreter,
            'component' => $this->component,
            'description' => $this->description,
            'tags' => $this->tags,
            'is_active' => $this->is_active,
            'has_ai_response' => $this->has_ai_response,
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}
