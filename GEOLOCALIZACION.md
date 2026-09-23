# Fotos georreferenciadas

Cuando alguien envía una **foto tomada con la cámara** desde un enlace de carga, el
navegador le estampa encima una franja con la hora, el lugar y los tres logotipos del
Comité. Es evidencia de que la foto se tomó ahí y entonces, no un sello decorativo:
por eso solo se aplica a lo que llega por el botón **Tomar foto**, nunca a lo que se
elige de la galería o del explorador de archivos.

```
22/09/26, 10:26:26 a. m.
Barbosa, Antioquia · 6.404943, -75.407571
```

---

## Qué pasa, en orden

1. **Al abrir el enlace**, si la vista tiene botón de cámara, se pide la ubicación de
   una vez. No se espera a la foto: en iPhone el aviso de permiso salía justo al
   volver de la cámara, y la espera se agotaba mientras la persona tocaba «Permitir».
2. **Al llegar las coordenadas**, se consulta el nombre del municipio en segundo
   plano, para que ya esté listo cuando haga falta.
3. **Al tocar «Tomar foto»** se vuelve a pedir la ubicación. Esto es lo que permite
   recuperarse sin recargar cuando alguien negó el permiso y luego lo activó.
4. **Al volver de la cámara** se dibuja la foto en un `<canvas>`, se le añade la
   franja y se sustituye el archivo del formulario por el resultado.
5. **Al enviar**, la fecha del sello viaja en un campo paralelo a `archivo[]` y queda
   guardada en la ficha del documento.

Si la ubicación no llega, la foto se estampa igual con el texto «Ubicación no
disponible». Nunca se bloquea el envío por no tener señal.

## El código

| Archivo | Qué hace |
|---|---|
| `resources/js/foto-georreferencial.js` | Permiso, coordenadas, municipio y dibujo de la franja |
| `resources/js/campo-archivo.js` | Llama al estampado y lleva la fecha al formulario |
| `resources/views/components/campo-archivo.blade.php` | Botones de cámara, aviso de permiso y campo `tomadas[]` |
| `app/Http/Controllers/EnvioPublicoController.php` | Valida y guarda la fecha declarada |

## Qué queda guardado

| Dato | Dónde | De dónde sale |
|---|---|---|
| Hora del sello | `recepciones.tomada_at` | Reloj del teléfono |
| Fecha de la foto | `documentos.fecha_documento` | La misma, sin la hora |
| Nodo | `recepciones.nodo` | Lo elige quien envía en el formulario |
| Coordenadas y municipio | Solo dibujados en la imagen | Navegador y OpenStreetMap |

La hora llega con el huso del teléfono (normalmente UTC) y se convierte a
`APP_TIMEZONE` antes de guardarla. Una fecha futura se descarta: un reloj adelantado
no debe fechar documentos en el futuro.

**Las coordenadas no son consultables.** Viven dentro del JPG. Si algún día hay que
buscar o filtrar por lugar, hay que guardarlas en columnas propias, con el mismo
mecanismo que ya usa `tomada_at`.

## Hasta dónde llega esto como prueba

Todo lo de la franja lo declara el dispositivo de quien envía, igual que su nombre o
su entidad. Un teléfono con la hora cambiada estampa esa hora, y las coordenadas se
pueden falsificar con herramientas de desarrollador. **No es una prueba forense.**

Lo que sí da fe es lo que registra el servidor: `recepciones.created_at` («Recibido
el»), la IP de origen y la huella SHA-256 del archivo. La franja sirve para el uso
cotidiano —saber dónde y cuándo se levantó un acta—, no para un litigio.

## El servicio externo

El nombre del municipio sale de **Nominatim**, el geocodificador de OpenStreetMap.
Se consulta desde el teléfono de quien envía, no desde el servidor.

```
https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=12&accept-language=es&lat=…&lon=…
```

Qué implica:

- **Es gratuito y sin llave**, pero pide uso moderado (del orden de una consulta por
  segundo). Para el volumen de esta aplicación sobra. Si algún día se dispara, toca
  pasar a un servicio con llave o a una instancia propia.
- **Las coordenadas salen hacia un tercero.** No va con ellas ningún dato de la
  persona, pero conviene que esté dicho en la política de tratamiento de datos.
- **Si falla, no pasa nada.** Hay un tope de 4 segundos; vencido, la foto sale con las
  coordenadas solas.
- **No hay contrato de servicio.** Si Nominatim se cae, se pierde el nombre del
  municipio, nunca la foto ni las coordenadas.

### Por qué el municipio no es un campo directo

OpenStreetMap no usa una etiqueta única para el municipio, y en Colombia la que suele
acertar es `county`. Comprobado contra el servicio real:

| Coordenadas | Lo que devuelve OSM | Lo que se estampa |
|---|---|---|
| Barbosa (rural) | `city: Platanito Parte Baja`, `county: Barbosa` | Barbosa, Antioquia |
| Medellín | `city: Perímetro Urbano Medellín` | Medellín, Antioquia |
| Itagüí | `town: Itagüí` | Itagüí, Antioquia |
| Bogotá | `city: Bogotá ciudad` | Bogotá, Bogotá, Distrito Capital |

De ahí el orden `county → city → town → village → municipality` y la limpieza de los
nombres del DANE que trae pegados. Está en `nombreMunicipio()`.

## Permisos: lo que el código no puede resolver

La geolocalización exige **HTTPS**. En `http://` solo funciona con `localhost`, y el
navegador no avisa: simplemente falla.

Ningún navegador permite volver a mostrar el aviso de permiso una vez que se negó. Lo
único que se puede hacer —y es lo que hace la página— es decir dónde se devuelve,
según el dispositivo:

| Dispositivo | Dónde se reactiva |
|---|---|
| Android | Candado junto a la dirección → Permisos → Ubicación. Si no aparece: Ajustes → Aplicaciones → el navegador → Permisos |
| iPhone / iPad | «aA» en la barra → Configuración del sitio web → Ubicación. Además: Ajustes → Privacidad → Localización → Sitios web de Safari |
| Escritorio | Ícono junto a la dirección de la página |

En Chrome hay una trampa extra: si se cierra el aviso tres veces sin responder, el
sitio queda bloqueado solo, sin que nadie haya dicho que no.

## Probar en un teléfono real, sin desplegar

`localhost` cuenta como sitio seguro, así que el camino corto es reenviar el puerto
por USB en vez de montar un túnel:

1. En el teléfono: activa **Opciones de desarrollador** y **Depuración USB**.
2. Conéctalo por USB y acepta el aviso.
3. En Chrome del PC: `chrome://inspect/#devices` → **Port forwarding** → `8000` →
   `localhost:8000`.
4. Abre `http://localhost:8000/enviar/<token>` en el teléfono.

En esa misma pantalla, **inspect** abre la consola del celular en el PC, que es donde
se ven los errores de ubicación.

Para iPhone hace falta un túnel HTTPS (`ngrok http 8000`), y entonces hay que ajustar
`APP_URL`, el Redirect URI de Authentik y la confianza en el proxy.

## Pruebas automáticas

```bash
php artisan test --filter=EnvioPublicoTest
```

Cubren que la fecha estampada llega a `tomada_at` y a `fecha_documento`, y que un
reloj adelantado no fecha nada en el futuro. El estampado en sí no tiene pruebas
automáticas: vive en el navegador y el proyecto no tiene runner de JavaScript.
