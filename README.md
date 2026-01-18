# Dietetic (Login + Dashboard)

## Documentación funcional
Ver [docs/FUNCIONALIDAD_APP.md](docs/FUNCIONALIDAD_APP.md).

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

### Datos del cliente en Pedidos (opcional)
Si querés que el carrito guarde **Email** y **DNI** del cliente en los pedidos públicos, asegurate de tener estas columnas en `customer_orders`.

Si ya tenías la tabla creada, podés migrar con:

```sql
ALTER TABLE customer_orders
	ADD COLUMN customer_email VARCHAR(190) NULL AFTER customer_phone,
	ADD COLUMN customer_dni VARCHAR(32) NULL AFTER customer_email;
```

### DNI (opcional)
Si querés poder **guardar y buscar por DNI** en Ventas, asegurate de tener la columna `customer_dni` en `invoices`.

Si ya tenías la tabla creada, podés migrar con:

```sql
ALTER TABLE invoices
	ADD COLUMN customer_dni VARCHAR(32) NULL AFTER customer_email,
	ADD KEY idx_invoices_customer_dni (customer_dni);
```

### Performance (recomendado)
Los reportes filtran por `created_by` y por rango de `created_at`. En hosting, si tenés muchas facturas, conviene agregar este índice compuesto:

```sql
ALTER TABLE invoices
  ADD KEY idx_invoices_created_by_created_at (created_by, created_at);
```

## Dependencias (PDF + Email)
Para **descargar en PDF** y **enviar por email con adjunto** se usan librerías via Composer:

```bash
composer install --no-dev --optimize-autoloader
```

### Reportes (CSV / XML / XLSX)
La pantalla **Ventas** permite exportar reportes. Para generar **XLSX** se usa `phpoffice/phpspreadsheet` (vía Composer).

Luego subí la carpeta `vendor/` al hosting (si no podés ejecutar Composer en Hostinger).

### Si en Hostinger te descarga un HTML que dice “Error de PDF (plantilla)”
Eso significa que **sí encontró** `src/pdf/boceto.pdf`, pero **NO encontró FPDI** (porque falta `vendor/`).

Solución (la más simple y confiable):
1. En tu PC (Windows), instalá Composer y ejecutá en la carpeta del proyecto:

```bash
composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
```

2. Verificá que exista `vendor/autoload.php` y `vendor/setasign/fpdi/`.
3. Subí la carpeta `vendor/` al hosting en el mismo nivel que `composer.json`.
	- Opción A: `vendor/` va al mismo nivel que `src/` (fuera de `public_html/`).
	- Opción B: `vendor/` va dentro de `public_html/vendor/` (porque `src/bootstrap.php` busca `public_html/vendor/autoload.php`).

Nota: si estás usando Git Deployments y Hostinger intenta correr Composer y falla, igualmente podés subir `vendor/` por File Manager/FTP después del deploy.

Si NO instalás Composer:
- Descargar funciona igual, pero como HTML (`.html`) en vez de PDF.
- Enviar por email intenta usar `mail()` (puede depender de la configuración del hosting).

### composer.lock
Si usás deploy por Git en Hostinger, conviene versionar `composer.lock` (commitearlo) para que las instalaciones sean reproducibles.

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
- Ventas: `/sales.php`
- Clientes (reporte): `/customers.php`
- Productos vendidos (reporte): `/products.php`
- Lista de precios (cliente): `/lista_precios.php`
- Pedidos (admin): `/pedidos.php`
- Logout: botón "Salir" en el dashboard

## Logo en factura
La factura usa el logo desde `src/img/logo.jpg` (se incrusta en base64 para que funcione en PDF/email).
Reemplazá ese archivo por tu logo manteniendo el nombre `logo.jpg`.
