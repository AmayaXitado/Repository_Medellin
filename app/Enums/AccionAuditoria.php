<?php

namespace App\Enums;

enum AccionAuditoria: string
{
    case Ingreso = 'ingreso';
    case Salida = 'salida';
    case DocumentoCreado = 'documento.creado';
    case DocumentoActualizado = 'documento.actualizado';
    case DocumentoVersionSubida = 'documento.version_subida';
    case DocumentoDescargado = 'documento.descargado';
    case DocumentoInactivado = 'documento.inactivado';
    case DocumentoReactivado = 'documento.reactivado';
    case CarpetaCreada = 'carpeta.creada';
    case CarpetaActualizada = 'carpeta.actualizada';
    case CarpetaInactivada = 'carpeta.inactivada';
    case UsuarioCreado = 'usuario.creado';
    case UsuarioActualizado = 'usuario.actualizado';
    case UsuarioDesactivado = 'usuario.desactivado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ingreso => 'Inició sesión',
            self::Salida => 'Cerró sesión',
            self::DocumentoCreado => 'Cargó un documento',
            self::DocumentoActualizado => 'Actualizó los datos de un documento',
            self::DocumentoVersionSubida => 'Subió una versión nueva',
            self::DocumentoDescargado => 'Descargó un documento',
            self::DocumentoInactivado => 'Inactivó un documento',
            self::DocumentoReactivado => 'Reactivó un documento',
            self::CarpetaCreada => 'Creó una carpeta',
            self::CarpetaActualizada => 'Actualizó una carpeta',
            self::CarpetaInactivada => 'Inactivó una carpeta',
            self::UsuarioCreado => 'Creó un usuario',
            self::UsuarioActualizado => 'Actualizó un usuario',
            self::UsuarioDesactivado => 'Desactivó un usuario',
        };
    }
}
