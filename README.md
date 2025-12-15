# Dietetic (Login + Dashboard)

## Estructura (opciones)

### Opción A (recomendada)
- Subir el contenido de `public/` a `public_html/`
- Subir `src/` fuera de `public_html/` (mismo nivel)

### Opción B (la que tenés ahora: todo dentro de `public_html/`)
- Subir TODO el proyecto dentro de `public_html/`
- Asegurarte de que existan en la raíz: `index.php`, `login.php`, `dashboard.php`, `logout.php`
- Subir `.htaccess` en la raíz para bloquear `src/` y `database/`

## Config (Hostinger)
1. Creá `config.local.php` usando `config.local.php.example` como guía.
2. Si usás Opción A: `config.local.php` va al mismo nivel que `public_html/`.
3. Si usás Opción B: `config.local.php` va dentro de `public_html/`.

## Base de datos
1. En phpMyAdmin ejecutá `database/schema.sql` en tu DB.
2. Creá un usuario inicial en la tabla `users`.

## Dependencias (PDF + Email)
Para **descargar en PDF** y **enviar por email con adjunto** se usan librerías via Composer:

```bash
composer install --no-dev --optimize-autoloader
```

Luego subí la carpeta `vendor/` al hosting (si no podés ejecutar Composer en Hostinger).

Si NO instalás Composer:
- Descargar funciona igual, pero como HTML (`.html`) en vez de PDF.
- Enviar por email intenta usar `mail()` (puede depender de la configuración del hosting).

## PDF con plantilla (boceto)
Si querés que la factura se imprima arriba de un PDF existente, el sistema usa `src/pdf/boceto.pdf` con FPDI.
- Dependencia: `setasign/fpdi` (via Composer)
- Las coordenadas de impresión se ajustan en `src/invoice_pdf_template.php`

### SMTP (opcional)
Si querés usar SMTP (más confiable), definí en `config.local.php`:
- `SMTP_HOST`, `SMTP_USER`, `SMTP_PASS`, `SMTP_PORT` (ej. 587), `SMTP_SECURE` (tls)
- `MAIL_FROM`, `MAIL_FROM_NAME`

### Crear password hash
Necesitás guardar `password_hash` (bcrypt). Podés generar el hash con este snippet en un entorno con PHP:

```php
<?php
echo password_hash('TU_PASSWORD', PASSWORD_DEFAULT);
```

Luego insertás el usuario (ejemplo):

```sql
INSERT INTO users (email, password_hash)
VALUES ('admin@tu-dominio.com', 'PEGAR_HASH_AQUI');
```

## URLs
- Login: `/login.php`
- Dashboard: `/dashboard.php`
- Logout: botón "Salir" en el dashboard

## Logo en factura
La factura usa el logo desde `src/img/logo.jpg` (se incrusta en base64 para que funcione en PDF/email).
Reemplazá ese archivo por tu logo manteniendo el nombre `logo.jpg`.
