<?php

namespace App\Models;

use App\Models\Concerns\TamanoLegible;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoVersion extends Model
{
    use HasFactory, TamanoLegible;

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

}
