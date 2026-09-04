<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Etiqueta extends Model
{
    use HasFactory;

    protected $table = 'etiquetas';

    protected $fillable = ['dependencia_id', 'nombre', 'slug'];

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    public function documentos(): BelongsToMany
    {
        return $this->belongsToMany(Documento::class, 'documento_etiqueta');
    }

    /**
     * Convierte "actas, 2026, comité" en ids de etiquetas, creando las que falten.
     *
     * @return array<int, int>
     */
    public static function resolverDesdeTexto(?string $texto, int $dependenciaId): array
    {
        if (blank($texto)) {
            return [];
        }

        $ids = [];

        foreach (explode(',', $texto) as $nombre) {
            $nombre = trim($nombre);

            if ($nombre === '') {
                continue;
            }

            $etiqueta = static::firstOrCreate(
                ['dependencia_id' => $dependenciaId, 'slug' => Str::slug($nombre)],
                ['nombre' => $nombre],
            );

            $ids[] = $etiqueta->id;
        }

        return array_unique($ids);
    }
}
