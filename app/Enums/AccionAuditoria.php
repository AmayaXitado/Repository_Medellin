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
    case CarpetaReactivada = 'carpeta.reactivada';
    case UsuarioCreado = 'usuario.creado';
    case UsuarioActualizado = 'usuario.actualizado';
    case UsuarioDesactivado = 'usuario.desactivado';
    case RecepcionRecibida = 'recepcion.recibida';
    case RecepcionArchivada = 'recepcion.archivada';
    case RecepcionDescartada = 'recepcion.descartada';
    case RecepcionReasignada = 'recepcion.reasignada';
    case EnlaceCreado = 'enlace.creado';
    case EnlaceRevocado = 'enlace.revocado';

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
            self::CarpetaReactivada => 'Reactivó una carpeta',
            self::UsuarioCreado => 'Creó un usuario',
            self::UsuarioActualizado => 'Actualizó un usuario',
            self::UsuarioDesactivado => 'Desactivó un usuario',
            self::RecepcionRecibida => 'Recibió un archivo por un enlace de carga',
            self::RecepcionArchivada => 'Archivó un recibido en el repositorio',
            self::RecepcionDescartada => 'Descartó un recibido',
            self::RecepcionReasignada => 'Reasignó un recibido a otra bandeja',
            self::EnlaceCreado => 'Creó un enlace de carga',
            self::EnlaceRevocado => 'Revocó un enlace de carga',
        };
    }
}
