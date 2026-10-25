# Cambios de Documenta

Cada versión que llega a producción se anuncia sola por la campana a todos los
usuarios, con el texto de su sección. Escribe la sección **antes** de fusionar a
`main`: el número es el que tendrá (mira el menú lateral en pruebas: `v2.N-beta`
quiere decir que la próxima es `v2.N`).

Mientras no se fusione a `main`, todo lo nuevo se suma a la misma sección: es una
sola versión. La siguiente sección se abre después de esa fusión.

Cada viñeta, corta y para quien no programa.

## v2.1

- Turnos: las personas de campo marcan entrada y salida con el QR o el link de su componente, solo con su cédula.
- Evidencias: en los enlaces de carga de un componente basta la cédula; los datos se llenan una sola vez.
- Los líderes (rol Edición) gestionan los turnos: enlaces, personas de campo, quién está en turno, novedades y reporte. Coordinación crea los componentes (Calle, Básica…).
- Novedades de turno (horas extra, turno corto, sin salida, sin ubicación) llegan por la campana; se aprueban, se rechazan con motivo o se corrige la hora.
- Las horas extra se calculan sobre el trabajo real, descontando el descanso: nada en turnos de hasta 4 h, 30 min hasta 6 h y 1 h en turnos más largos.
- Reporte de turnos en Excel con dos pestañas: el detalle de cada turno (tiempo en el sitio, descanso, horas trabajadas, ordinarias, extra y si se aprobaron) y un resumen mensual por persona con días trabajados, horas de cada semana contra las 42 h (en rojo si pasan), extras aprobadas y pendientes, turnos cortos y cumplimiento.
- Se pueden cargar hasta 300 archivos de una vez: se envían solos por pasos de 50, con barra de progreso, y si se corta la conexión se retoma sin duplicar.
- En el explorador se marcan varios documentos (con su check) y carpetas (con «Seleccionar» en el menú ⋮ de cada una) y se copian o mueven a otra carpeta de una vez, sin descargar ni volver a subir.
- Carpetas bajo custodia: Coordinación asigna una carpeta a un grupo (por ejemplo, los líderes de un nodo) y solo ese grupo la ve y la trabaja; abrir sus documentos queda registrado.
- Las fotos tomadas con la cámara llevan un sello visible: hora grande, fecha, dirección (calle, barrio, municipio), y coordenadas.
- La auditoría muestra por dónde entró cada archivo (aplicación o enlace de carga) y a qué carpeta fue; se busca por nombre de archivo. Los registros anteriores se completan solos.
- Derivaciones y respuesta territorial: formulario por pasos, por enlace o QR, solo para cédulas registradas, con soporte y 3 evidencias fotográficas obligatorias con sello; tiempo de respuesta calculado (más de 3 h exige justificación); consulta, corrección y eliminación; reporte en Excel con encabezado negro y las fotos dentro de sus celdas (se ven sin cuenta de Documenta), y ZIP con las fotos en tamaño original.
- Derivaciones: llegan pendientes y el tiempo corre desde el requerimiento hasta que un líder las responde en Documenta («Marcar como solucionada»). Los líderes reciben un aviso al llegar y cada 30 minutos mientras sigan sin respuesta; pasadas las 3 horas, el aviso dice que está vencida. La lista muestra primero las pendientes, con su tiempo corriendo, y arriba el seguimiento: cuántas siguen sin respuesta (y vencidas), el cumplimiento, qué nodos tienen casos sin responder y quién ha respondido con su tiempo promedio.
- Notificaciones separadas en pestañas: Derivaciones, Turnos, Documentos y Avisos. El recordatorio de una derivación reemplaza al anterior del mismo caso y desaparece al responderla.
- Novedades de turno: botón «Descargar Excel» con el filtro de la pantalla. Las aprueban Coordinación, Administración y los usuarios de Edición con «Aprueba novedades de turno», que se marca persona por persona al editar el usuario; a ellos también les llegan por la campana.
- Los enlaces por cédula se crean en un solo lugar eligiendo para qué son: turnos o derivaciones.
- Los editores pueden inactivar documentos (con motivo) y carpetas; la custodia sigue protegiendo lo de otros nodos. Reactivar documentos sigue siendo de Administración.
- Al copiar, mover o elegir carpeta se ve la ruta completa («Calle / Nodo 2 / Octubre») y hay un buscador para no recorrer la lista a ojo.
- Si el servidor recibe menos archivos de los enviados (límite de PHP), la carga se detiene con un aviso claro en vez de guardar solo una parte; las tandas nunca pasan de ese límite.
- Dentro de una carpeta se pasa de un archivo a otro con las flechas (botones o teclas ← →), sin volver a la lista; se ve «3 de 25».
- Comentarios en cada archivo («la foto está mal tomada»): a quien lo subió, y a quienes ya comentaron, les llega por la campana con un enlace directo al comentario.
- Catálogo de la malla dentro de Documenta: tipos de turno (N1, T3, N7, C13, D, I… con horario y horas de nómina) y puntos críticos con «Ver en el mapa». Los manejan los líderes; se abren desde Personas de campo y En turno ahora. Tablas con búsqueda, filtros y columnas configurables.
- Usuarios: la lista es una tabla con búsqueda (también por documento con puntos), filtros por rol, estado y «aprueba novedades», y «Revocar» con confirmación. La ficha para dar acceso o editar va por secciones (persona, rol, contraseña) con un panel al lado que resume la cuenta (Authentik, último acceso) y el interruptor de cuenta activa.
- Mi perfil y la ficha de persona de campo (registrar y editar) con el mismo diseño de la de usuarios: tarjetas por tema y un panel lateral con el resumen y los botones. Los días de descanso se eligen tocando cada día; el tema, con tarjetas de claro, oscuro y sistema.
- Las barras de scroll (tablas anchas, listas) con los colores de Documenta, delgadas y en el tema claro u oscuro.
- Componentes y nodos en una sola pantalla: tabla con búsqueda y filtros, y un panel lateral para crear o editar el componente con sus reglas por secciones, los cargos como etiquetas y los nodos (con su zona) que se agregan, ordenan arrastrando y desactivan ahí mismo. Editar un componente ya no apaga las reglas del turno automático.
- Menú: «Componentes y nodos» pasa a Administración y «Derivaciones» a su propio grupo, Atención territorial.
- Turnos automáticos: cada persona tiene su turno, punto crítico y días de descanso habituales; al marcar, el turno se reconoce solo por la hora y queda la tardanza. Si alguien con turno no marca entrada a los 30 minutos, llega el aviso «No registró entrada» a líderes y Coordinación (y se cierra solo si después marca). Descansos, vacaciones, incapacidades y permisos se registran por fechas desde la ficha o justificando la novedad.
- El QR de un enlace puede ser de un punto crítico: quien marca con él queda en ese punto ese día.
- Manual de usuario con la imagen del Comité, para todos los roles (menú de la cuenta y Administración), con descarga en PDF lista para compartir.
- Una hora mal marcada se corrige; la original queda en el historial.
- Con el turno del día cerrado, el enlace ya no deja volver a marcar.
- El menú se organiza por grupos que se pliegan, en el orden del trabajo.
- La versión del sistema aparece en el menú lateral, y los avisos importantes llegan por la campana.
