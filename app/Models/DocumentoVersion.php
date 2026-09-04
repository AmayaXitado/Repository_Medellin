<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoVersion extends Model
{
    use HasFactory;

    protected $table = 'documento_versiones';

    protected $fillable = [
        'documento_id',
        'numero',
        'ruta',
        'nombre_original',
        'extension',
        'mime',
        'tamano',
        'hash',
        'comentario',
        'subido_por',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'tamano' => 'integer',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    /**
     * Tamaño legible sin depender de ext-intl (que Number::fileSize sí exige
     * y no siempre está activa en instalaciones de PHP en Windows).
     */
    public function getTamanoLegibleAttribute(): string
    {
        $bytes = max(0, (int) $this->tamano);
        $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($unidades) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) $bytes : number_format($bytes, 1, ',', '.')).' '.$unidades[$i];
    }
}
