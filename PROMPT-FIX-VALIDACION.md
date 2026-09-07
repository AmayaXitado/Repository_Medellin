# Corregir: el formulario de enlaces muestra «validation.after»

Al generar un enlace de carga, el formulario responde con el texto literal
`validation.after` en vez de un mensaje legible.

Ya diagnostiqué la causa. **Son dos problemas independientes** que se manifiestan
juntos. Arregla los dos.

---

## Problema 1 — Faltan las traducciones (afecta a TODA la aplicación)

### Causa

El archivo `.env` tiene:

```
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
```

pero **el proyecto no tiene carpeta `lang/`**. Laravel 11 y 12 no la traen publicada por
defecto. Cuando una regla de validación no encuentra su traducción, y el idioma de
respaldo tampoco existe, Laravel imprime la clave cruda: `validation.after`.

Esto **no es exclusivo del formulario de enlaces**. Cualquier regla que no tenga un
mensaje personalizado está mostrando su clave en toda la aplicación:
`validation.required`, `validation.email`, `validation.max.file`… No se había notado
porque varios Form Requests definen mensajes literales en `messages()`, que sí se ven
bien. Verifícalo tú mismo enviando cualquier formulario vacío.

### Arreglo

1. Publica los archivos de idioma:

   ```
   php artisan lang:publish
   ```

   Eso crea `lang/en/` con `validation.php`, `auth.php`, `passwords.php` y
   `pagination.php`.

2. Crea `lang/es/` traduciendo esos archivos. No hace falta traducir las 90 reglas de
   Laravel: cubre las que la aplicación realmente usa, que son estas —

   `required`, `string`, `integer`, `boolean`, `email`, `date`, `after`,
   `after_or_equal`, `before_or_equal`, `min` (numeric, string, file, array),
   `max` (numeric, string, file, array), `mimes`, `mimetypes`, `file`, `unique`,
   `exists`, `confirmed`, `current_password`, `enum`, `in`, `nullable`, `numeric`,
   `image`, `uuid`

   — y deja el resto tal como venga, en inglés. Es preferible un mensaje en inglés
   entendible a una clave cruda.

   Cuida los marcadores `:attribute`, `:date`, `:max`, `:values`. Si los rompes, los
   mensajes salen a medias.

   Redáctalos en el tono del proyecto: directos y en segunda persona.
   «El campo :attribute debe ser una fecha posterior a :date.»

3. Cambia el respaldo en `.env` **y** en `.env.example`:

   ```
   APP_FALLBACK_LOCALE=en
   ```

   Con el respaldo en `es`, cualquier hueco en la traducción vuelve a mostrar la clave
   cruda. Con `en`, muestra el mensaje en inglés — feo, pero legible y depurable.

4. Limpia la caché de configuración, o el cambio no surte efecto:

   ```
   php artisan config:clear
   ```

### Verificación

Envía vacíos el formulario de ingreso, el de subir documento y el de crear usuario.
Ningún mensaje debe contener la palabra `validation.`

---

## Problema 2 — La regla `after:now` rechaza la fecha de hoy

### Causa

En `app/Http/Requests/GuardarEnlaceCargaRequest.php`:

```php
'expira_at' => ['nullable', 'date', 'after:now'],
```

El formulario usa un campo de fecha, que envía solo el día: `2026-09-15`. Laravel lo
interpreta como `2026-09-15 00:00:00` — la medianoche de ese día.

Si el usuario elige **hoy**, el valor es la medianoche de hoy, que ya pasó. `after:now`
lo rechaza. Y por el Problema 1, en vez de explicarlo, imprime `validation.after`.

Es un fallo de diseño, no solo de validación: un enlace que "vence el 15 de septiembre"
debería servir durante todo el 15, no morir a las 00:00 de ese día.

### Arreglo

Normaliza la fecha al **final del día elegido** antes de validar, y cambia la regla.

En `GuardarEnlaceCargaRequest`:

```php
protected function prepareForValidation(): void
{
    if ($this->filled('expira_at')) {
        // El campo envía solo la fecha. Un enlace que vence el día X
        // debe servir durante todo el día X, no hasta su medianoche.
        $this->merge([
            'expira_at' => Carbon::parse($this->input('expira_at'))->endOfDay(),
        ]);
    }
}
```

Y la regla:

```php
'expira_at' => ['nullable', 'date', 'after_or_equal:today'],
```

Consideraciones:

- Si `Carbon::parse()` recibe basura, lanza excepción antes de validar y el usuario ve
  un error 500 en vez de un mensaje. Envuélvelo para que un valor inválido pase tal cual
  y sea la regla `date` la que lo rechace con un mensaje decente.
- La aplicación corre con `APP_TIMEZONE=America/Bogota`. `today` y `endOfDay()` usan esa
  zona. Confirma que el `casts()` de `EnlaceCarga` tenga `expira_at` como `datetime`,
  para que `estaDisponible()` compare en la misma zona horaria.
- Revisa el `<input>` en la vista de creación del enlace. Si es `type="date"`, esta
  solución es la correcta. Si Code lo hizo `type="datetime-local"`, entonces no
  normalices y deja `after:now` — pero elige uno de los dos caminos, no mezcles.
- Agrega `min` al input con la fecha de hoy, para que el navegador ni siquiera ofrezca
  fechas pasadas.

### Verificación

- Crear un enlace **sin** fecha de vencimiento funciona.
- Crear uno con la fecha de **hoy** funciona, y el enlace sirve durante todo el día.
- Crear uno con una fecha **pasada** se rechaza con un mensaje en español legible.
- Un enlace vencido devuelve la pantalla genérica de «no disponible».

---

## Busca el mismo error en otros lados

Revisa todos los Form Requests por reglas de fecha con el mismo problema:

```
grep -rn "after:\|before:\|after_or_equal\|before_or_equal" app/Http/Requests/
```

En particular `GuardarDocumentoRequest` tiene `fecha_documento` con
`before_or_equal:today`. Ese caso es el inverso y probablemente esté bien —un acta
fechada hoy debe aceptarse—, pero compruébalo: si compara contra `today` a medianoche y
el campo llega como fecha pura, funciona; verifica que efectivamente sea así.

---

## Pruebas

Agrega a la suite:

- Crear un enlace con `expira_at` igual a hoy: se acepta.
- Crear con fecha pasada: se rechaza, y el mensaje **no** contiene `validation.`
- Un enlace con `expira_at` = hoy sigue disponible a las 23:00 de hoy.
- Un enlace con `expira_at` = ayer no está disponible.

---

## Nota

El `.env` con `APP_FALLBACK_LOCALE=es` sin archivos de idioma lo configuré yo al iniciar
el proyecto. Es la causa de que todos los mensajes de validación salgan como claves
crudas. El Problema 1 no es culpa de tu implementación de los enlaces: solo fue donde se
hizo visible.
