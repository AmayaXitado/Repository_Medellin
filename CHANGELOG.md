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
- Reporte de turnos para Excel: tiempo en el sitio, descanso, horas trabajadas, ordinarias, extra y si las extras fueron aprobadas.
- Se pueden cargar hasta 300 archivos de una vez: se envían solos por pasos de 50, con barra de progreso, y si se corta la conexión se retoma sin duplicar.
- En el explorador se marcan varios documentos y carpetas con un check y se copian o mueven a otra carpeta de una vez, sin descargar ni volver a subir.
- Carpetas bajo custodia: Coordinación asigna una carpeta a un grupo (por ejemplo, los líderes de un nodo) y solo ese grupo la ve y la trabaja; abrir sus documentos queda registrado.
- Las fotos tomadas con la cámara llevan un sello visible: hora grande, fecha, dirección (calle, barrio, municipio), y coordenadas.
- Una hora mal marcada se corrige; la original queda en el historial.
- Con el turno del día cerrado, el enlace ya no deja volver a marcar.
- El menú se organiza por grupos que se pliegan, en el orden del trabajo.
- La versión del sistema aparece en el menú lateral, y los avisos importantes llegan por la campana.
