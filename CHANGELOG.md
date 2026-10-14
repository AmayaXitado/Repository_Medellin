# Cambios de Documenta

Cada versión que llega a producción se anuncia sola por la campana a todos los
usuarios, con el texto de su sección. Escribe la sección **antes** de fusionar a
`main`: el número es el que tendrá (mira el menú lateral en pruebas: `v2.N-beta`
quiere decir que la próxima es `v2.N`).

Cada viñeta, corta y para quien no programa.

## v2.1

- Turnos: las personas de campo marcan entrada y salida con el QR o el link del componente, solo con su cédula.
- Evidencias: en los enlaces de carga de un componente basta la cédula; los datos se llenan una sola vez.
- Coordinación ve quién está en turno, las marcaciones, y valida las novedades (horas extra, turnos cortos, sin salida).
- Reporte de turnos para Excel: tiempo en el sitio, descanso, horas trabajadas, ordinarias, extra y si las extras fueron aprobadas.
- Las horas extra se calculan sobre el trabajo real, descontando el descanso: nada en turnos de hasta 4 h, 30 min hasta 6 h y 1 h en turnos más largos.
- Una hora mal marcada se corrige desde Novedades o Marcaciones; la original queda en el historial.
- Con el turno del día cerrado, el enlace ya no deja volver a marcar.
- El menú se organiza por grupos que se pliegan.
- La versión del sistema aparece en el menú lateral.
