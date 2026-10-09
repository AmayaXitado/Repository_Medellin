# Fotografías de evidencia: tamaño y calidad

**En una frase:** las fotos que llegan por los enlaces de carga se guardan con
la calidad necesaria para leer un documento, y pesan entre 2 y 7 veces menos
que la foto original del teléfono.

---

## Por qué importa

Documenta guarda sobre todo **fotografías de evidencia**. Una foto no se puede
convertir en datos más livianos: hay que guardarla tal cual. Por eso las fotos
son casi todo el espacio que ocupa el sistema; el resto (nombres, fechas,
permisos, auditoría) pesa muy poco.

Los teléfonos actuales toman fotos cada vez más grandes. Una sola foto de un
teléfono de gama media pesa unos 2 MB; la de uno de gama alta, 15 MB o más.
Sin control, eso significa:

- Más espacio en el servidor y respaldos más lentos y costosos.
- Envíos más lentos para quien manda la evidencia, muchas veces con datos móviles.
- Revisiones más lentas para quien abre las fotos.

Y casi nada de ese tamaño extra aporta: una foto para revisar una evidencia no
necesita la resolución de una foto para imprimir en gran formato.

## Qué hace el sistema

Cuando alguien envía una foto por un enlace de carga, el sistema la ajusta
**automáticamente, en el teléfono, antes de enviarla**. La persona no tiene que
hacer nada ni elegir nada.

- La foto se reduce a un tamaño máximo de **2560 píxeles** por su lado más largo.
- Si la foto ya era más pequeña, **no se toca**.
- Las fotos tomadas con la cámara llevan además el sello con fecha, hora,
  municipio y coordenadas.

2560 píxeles alcanzan de sobra para leer la letra pequeña de un acta o de una
pantalla fotografiada.

## Una segunda barrera en el servidor

El ajuste anterior ocurre en el teléfono de quien envía, y un usuario con
conocimientos técnicos podría saltárselo. Por eso el servidor revisa cada foto
que llega y **rechaza cualquiera de más de 4096 píxeles**, con un mensaje que
explica el motivo.

Esta segunda barrera queda un poco más alta a propósito: si por alguna razón
el ajuste del teléfono no funcionara, una foto normal seguiría entrando, y
solo se bloquearían las desproporcionadas.

## Resultados medidos

Probado sobre el formulario real del sistema:

| Foto de entrada | Antes | Después | Ahorro |
|---|---|---|---|
| Teléfono de gama media (12 MP) | 2,1 MB | 0,9 MB | 2,3 veces menos |
| Teléfono de gama alta (24–48 MP) | 15,8 MB | 1,9 MB | 8 veces menos |
| Foto que ya era pequeña | 0,6 MB | 0,6 MB | Sin cambios |

**La calidad se mantiene.** Comparamos la misma zona de una foto de una
pantalla de computador —texto pequeño, que es lo primero que se pierde— en la
versión original y en la reducida, ampliadas al mismo tamaño. El texto se lee
igual de nítido en las dos.

## Qué significa en espacio de almacenamiento

Estimación con un promedio de 1 MB por foto ya ajustada, frente a unos 2 MB
sin ajustar:

| Fotos recibidas al mes | Espacio al año, sin ajuste | Espacio al año, con ajuste |
|---|---|---|
| 100 | ~2,5 GB | ~1,2 GB |
| 500 | ~12 GB | ~6 GB |
| 2.000 | ~50 GB | ~24 GB |

Con teléfonos de gama alta la diferencia es mucho mayor, porque sin ajuste cada
foto pesaría varias veces más.

> Son estimaciones: el peso real depende de lo fotografiado. Una escena con
> mucho detalle pesa más que una hoja en blanco.

## Lo que hay que saber

- **Aplica a los enlaces de carga**, que es por donde llega la evidencia de
  fuera. Los documentos que sube el personal con cuenta no tienen este ajuste
  todavía; se puede extender si se considera necesario.
- **Los PDF no se tocan.** El ajuste es solo para fotografías.
- **Los límites se pueden cambiar** sin modificar el sistema, si en algún
  momento se necesita más resolución o menos peso.

---

<sub>Detalle técnico de cómo funciona el sello y el ajuste: [`GEOLOCALIZACION.md`](GEOLOCALIZACION.md).</sub>
