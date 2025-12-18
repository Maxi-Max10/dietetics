# Documentación funcional — Dietetic

Fecha: 17/12/2025

## 1) Visión general

Dietetic es una app web PHP con:

- **Login** por email/contraseña (tabla `users`).
- **Dashboard** para **crear facturas** con ítems (productos, cantidades y precios).
- **Generación de documento** de factura:
  - Preferente: **PDF con plantilla** (`src/pdf/boceto.pdf`) vía **FPDI/FPDF**.
  - Alternativa: **PDF con Dompdf** si está instalado.
  - Fallback: **HTML descargable** si no hay librerías.
- **Envío por email** de la factura como adjunto (PHPMailer si está disponible, o `mail()` como fallback).
- **Reportes**:
  - Ventas (resumen + listado)
  - Clientes (agrupado)
  - Productos vendidos (agrupado)
  - Exportación: **CSV / XML / XLSX** (XLSX requiere PhpSpreadsheet)

La app corre típicamente en hosting (ej. Hostinger) y usa `.htaccess` para:

- Bloquear acceso a carpetas internas (`src/`, `database/`, `vendor/`).
- Proveer **URLs amigables** (ej. `/sales` en lugar de `/sales.php`).

## 2) Secciones de la app (pantallas / endpoints)

### 2.1) Inicio (`/`)

**Archivo**: `public/index.php`

**Funcionalidad**

- Si el usuario **ya está logueado**, redirige a **Dashboard** (`/dashboard.php`).
- Si **no** está logueado, redirige a **Login** (`/login.php`).

**Objetivo**

- Servir como “landing” simple que decide el flujo según la sesión.

---

### 2.2) Login (`/login.php`)

**Archivo**: `public/login.php`

**Qué ve el usuario**

- Formulario con:
  - Email
  - Contraseña
  - Botón “Login”

**Qué hace el backend**

1. Si ya existe sesión con usuario (`auth_user_id()`), redirige a `/dashboard.php`.
2. En POST:
   - Lee `email`, `password` y `csrf_token`.
   - Valida **CSRF**.
   - Valida campos requeridos.
   - Intenta conectar a DB y ejecutar login.
3. Si el login es correcto:
   - Regenera ID de sesión (mitiga fijación de sesión).
   - Guarda `$_SESSION['user_id']`.
   - Marca `$_SESSION['preload_dashboard'] = 1` (para un preload visual al entrar al dashboard).
   - Redirige a `/dashboard.php`.

**Mensajes de error**

- CSRF inválido: “Sesión inválida. Recargá e intentá de nuevo.”
- Campos vacíos: “Completá email y contraseña.”
- Credenciales incorrectas.
- Errores de DB: muestra mensajes “seguros” (host incorrecto, DB inexistente, access denied, etc.). En entorno no-production agrega detalle.

---

### 2.3) Dashboard / Facturación (`/dashboard` o `/dashboard.php`)

**Archivo**: `public/dashboard.php`

**Acceso**

- Requiere login: `auth_require_login()`.

**Qué ve el usuario**

- Barra superior con accesos a:
  - Ventas
  - Clientes
  - Productos
  - Ingresos
  - Egresos
  - Stock
  - Salir
- Formulario “Administración de facturas” → “Nueva factura” con:
  - Nombre del cliente (requerido)
  - Email del cliente (requerido)
  - DNI (opcional; se guarda si la DB soporta la columna)
  - Detalle/observaciones (opcional)
  - Tabla de ítems (producto, cantidad, precio) con:
    - botón “Agregar producto”
    - botón “×” para eliminar filas (al menos 1 fila queda)
- Acciones finales:
  - “Guardar y descargar”
  - “Guardar y enviar por email”

**Qué hace el backend**

En POST, protegido por CSRF:

1. Lee los campos del cliente, detalle e ítems.
2. Normaliza ítems:
   - Ignora filas completamente vacías.
   - Valida descripción obligatoria.
   - Convierte cantidad y precio permitiendo coma o punto.
   - Calcula totales en centavos.
3. Crea la factura en DB:
   - Inserta en `invoices`.
   - Inserta filas en `invoice_items`.
   - Maneja transacción (commit/rollback).
   - Moneda fija: actualmente usa `ARS`.
4. Genera el archivo descargable (preferentemente PDF):
   - Intenta `invoice_build_download()`.
   - Si la salida no es PDF cuando se esperaba (mime inesperado), lo registra y muestra error “Factura guardada pero no se pudo generar el PDF”.
5. Según acción:
   - `download`: envía el archivo como descarga.
   - `email`: envía email con adjunto (PDF/HTML según lo que haya generado).
   - caso default: muestra “Factura guardada (ID …)”.

**Comportamiento del PDF**

- Si existe `src/pdf/boceto.pdf`, la app intenta **sí o sí** usar el método de plantilla (FPDI). Si falla, devuelve un HTML con checklist de instalación de dependencias (para que no pase “silenciosamente” a otro método).
- Si no hay plantilla:
  - Usa Dompdf si está instalado.
  - Si no, devuelve HTML descargable.

**Email**

- Usa PHPMailer si está instalado (via Composer).
- Si no, usa `mail()` con MIME multiparte.
- Soporta SMTP si se definieron variables/constantes (SMTP_HOST, SMTP_USER, etc.).

---

### 2.4) Ventas (`/sales`, `/sales/{period}`, `/sales/{period}/{limit}`)

**Archivo**: `public/sales.php`

**Acceso**

- Requiere login.

**Qué ve el usuario**

- Selector de período:
  - Día, Semana, Mes, Año
- Resumen por moneda:
  - Total
  - Cantidad de facturas
- Tabla de facturas del período:
  - ID
  - Cliente
  - Total
  - Fecha
- Buscador `q` (en servidor) y limitador `limit`.
- Botones de exportación:
  - CSV
  - XML
  - XLSX

**Qué hace el backend**

- Determina el rango (`sales_period`) según período.
- Detecta si la DB tiene columna DNI (`invoices_supports_customer_dni`).
- Calcula:
  - `sales_summary_filtered`: agrupa por moneda.
  - `sales_list_filtered`: lista facturas ordenadas por fecha desc.
- Si `format` es `csv/xml/xlsx`, devuelve el archivo directamente.

**Notas del buscador**

- El parámetro `q` filtra por nombre/email y también por DNI si existe la columna.
- Además hay un “live filter” en JS que filtra en el front la tabla ya cargada.

---

### 2.5) Clientes (reporte) (`/customers`, `/customers/{period}`, `/customers/{period}/{limit}`)

**Archivo**: `public/customers.php`

**Acceso**

- Requiere login.

**Qué ve el usuario**

- Selector de período (día/semana/mes/año).
- Buscador por nombre/email/DNI.
- Selector de cantidad (20/50/100/120).
- Exportación CSV/XML/XLSX.
- Tabla agregada por cliente mostrando:
  - Nombre, Email, DNI
  - Cantidad de facturas
  - Total acumulado
  - Última compra

**Qué hace el backend**

- Consulta `invoices` agrupando por cliente + moneda (+ DNI si existe).
- Ordena por total descendente.
- Exporta en el formato solicitado.

---

### 2.6) Productos vendidos (reporte) (`/products`, `/products/{period}`, `/products/{period}/{limit}`)

**Archivo**: `public/products.php`

**Acceso**

- Requiere login.

**Qué ve el usuario**

- Selector de período.
- Buscador por descripción de producto.
- Selector de cantidad.
- Exportación CSV/XML/XLSX.
- Tabla agregada mostrando:
  - Producto (+ moneda)
  - Cantidad total vendida
  - Facturas en las que aparece
  - Total acumulado

**Qué hace el backend**

- Join entre `invoice_items` e `invoices`.
- Agrupa por descripción + moneda.
- Suma cantidades y totales.

---

### 2.7) Logout (`/logout.php`)

**Archivo**: `public/logout.php`

**Acceso / método**

- Solo responde a **POST**.
- Valida CSRF.

**Qué hace**

- Limpia la sesión y la cookie.
- Redirige a `/login.php`.

---

### 2.8) Logo (`/logo.png`)

**Archivo**: `public/logo.php` + regla en `.htaccess`

**Qué hace**

- La app sirve el logo como `/logo.png`, pero realmente lo obtiene desde `src/img/logo.png`.
- Esto evita exponer `src/` en el hosting (porque `src/` está bloqueado).
- Agrega cache HTTP (ETag / Last-Modified) para performance.

---

### 2.9) Ingresos (`/income`, `/income/{period}`, `/income/{period}/{limit}`)

**Archivo**: `public/income.php`

**Qué es**

- Un **reporte** de ingresos calculado automáticamente desde las ventas.

**Cómo se calcula**

- Suma de `invoice_items.line_total_cents` dentro del período seleccionado.
- Agrupado por `invoices.currency`.

**Qué ve el usuario**

- Selector de período: Día / Semana / Mes / Año.
- Buscador `q` (producto o cliente) y selector `limit`.
- Totales por moneda.
- Tabla con los últimos productos vendidos (items) con su subtotal.

**Importante**

- No hay carga manual de ingresos: los ingresos “salen” de las facturas.

---

### 2.10) Egresos (`/expense`)

**Archivo**: `public/expense.php`

- Registro manual de gastos.
- Permite cargar descripción, monto, moneda y fecha.
- Muestra totales por moneda e historial.

---

### 2.11) Stock (`/stock`)

**Archivo**: `public/stock.php`

- Alta de ítems de stock (nombre, SKU opcional, unidad y cantidad).
- Ajustes por delta (+/-) con validación para evitar stock negativo.
- Integración con facturas: al guardar una venta, si la **descripción del ítem** coincide con el **nombre** (o SKU) de un ítem de stock, se descuenta automáticamente la cantidad vendida.

## 3) Funcionalidad interna (módulos)

### 3.1) Bootstrap y carga global

**Archivo**: `src/bootstrap.php`

- Carga configuración desde `src/config.php`.
- Fuerza zona horaria (por default Argentina) para que “Hoy” y reportes coincidan con el horario real del negocio.
- Inicializa sesión con cookies seguras:
  - `httponly`
  - `samesite=Lax`
  - `secure` si está HTTPS
- Aplica headers de seguridad básicos (X-Frame-Options, nosniff, etc.).
- Si existe `vendor/autoload.php`, lo carga (Composer).
- Incluye los módulos (db, auth, csrf, invoices, pdf, mail, sales, reports).
- Incluye los módulos (db, auth, csrf, invoices, pdf, mail, sales, reports, finance, stock).
- Define helper `e()` para escapar HTML.

### 3.2) Configuración

**Archivo**: `src/config.php` + `config.local.php` (no versionado)

- La config puede venir de variables de entorno o constantes definidas en `config.local.php`.
- Claves principales:
  - `app.name`, `app.env`, `app.base_url`
  - `db.host`, `db.name`, `db.user`, `db.pass`, `db.charset`
  - `security.session_name`

### 3.3) Base de datos

**Archivo**: `src/db.php`

- Crea un `PDO` singleton con:
  - `ERRMODE_EXCEPTION`
  - `FETCH_ASSOC`
  - sin emulación de prepared statements
- Ajusta zona horaria MySQL a `-03:00` cuando se puede (para reportes por fecha).

### 3.4) Autenticación

**Archivo**: `src/auth.php`

- `auth_login($pdo, $email, $password)`:
  - Busca usuario por email.
  - Valida hash con `password_verify`.
  - Regenera sesión y guarda `user_id`.
- `auth_require_login()`:
  - Redirige al login si no hay sesión.
- `auth_logout()`:
  - Limpia `$_SESSION`, cookie y destruye la sesión.

### 3.5) Protección CSRF

**Archivo**: `src/csrf.php`

- `csrf_token()` genera y persiste un token en sesión.
- `csrf_verify($token)` valida con `hash_equals`.

### 3.6) Facturas (modelo + validación)

**Archivo**: `src/invoices.php`

- `invoices_create(...)`:
  - Valida cliente y email.
  - Valida ítems: descripción, cantidad > 0, precio >= 0.
  - Calcula total en centavos.
  - Inserta factura + ítems en transacción.
  - Soporta DNI si existe la columna en DB.
- `invoices_supports_customer_dni($pdo)`:
  - Detecta columna `customer_dni` por INFORMATION_SCHEMA.
- `invoices_get(...)`:
  - Trae factura + items por `id` y `created_by`.

### 3.7) Render de factura (HTML)

**Archivo**: `src/invoice_render.php`

- Construye un HTML completo de factura (tabla + total + detalle).
- Maneja el logo de dos formas:
  - `asset_mode=data` (data URI base64) ideal para email.
  - `asset_mode=file` (`file://...`) útil para PDF con Dompdf.
- Intenta mejorar compatibilidad del logo (re-encode a PNG con GD si se puede).

### 3.8) Generación y descarga (PDF/HTML)

**Archivo**: `src/invoice_pdf.php`

- `invoice_build_download($data)` retorna:
  - `bytes`, `filename`, `mime`, y opcional `generator`.
- Estrategia:
  1) Si existe método de plantilla → intenta FPDI.
     - Si falla y existe `src/pdf/boceto.pdf`, devuelve HTML con diagnóstico.
  2) Si está Dompdf → genera PDF.
  3) Si no, devuelve HTML.
- `invoice_send_download(...)` envía headers anti-cache y entrega el archivo.

### 3.9) PDF con plantilla (FPDI)

**Archivo**: `src/invoice_pdf_template.php`

- Usa `src/pdf/boceto.pdf` como fondo.
- Escribe encima:
  - número de factura y fecha
  - datos de cliente (incluye DNI si existe)
  - tabla de ítems (con paginación)
  - total y detalle
- Tiene modo debug por grilla para calibrar coordenadas:
  - `INVOICE_PDF_DEBUG=1`

### 3.10) Email

**Archivo**: `src/mailer.php`

- `mail_send_with_attachment(...)`:
  - Valida email destino.
  - Sanitiza subject/from para evitar header injection.
  - Usa PHPMailer si existe; si hay SMTP configurado, lo activa.
  - Si no existe PHPMailer, arma un MIME multiparte y usa `mail()`.

### 3.11) Reportes

**Archivos**: `src/sales.php`, `src/reports.php`

- Rango de períodos (`sales_period`):
  - Day: 00:00 → +1 día
  - Week ISO: lunes 00:00 → +1 semana
  - Month: primer día del mes → +1 mes
  - Year: 01/01 → +1 año
- Ventas:
  - `sales_summary_filtered`: total por moneda.
  - `sales_list_filtered`: listado de facturas (con filtro por cliente/email/DNI).
  - exporta CSV/XML/XLSX.
- Clientes:
  - `reports_customers_list`: agrupación por cliente.
  - exporta CSV/XML/XLSX.
- Productos:
  - `reports_products_list`: agrupación por descripción.
  - exporta CSV/XML/XLSX.

## 4) URLs amigables y seguridad del servidor

**Archivo**: `.htaccess`

- Redirige `/logo.png` a `public/logo.php`.
- Bloquea acceso directo a:
  - `src/`
  - `database/`
  - `vendor/`
- Define rutas sin extensión:
  - `/dashboard`
  - `/sales`, `/sales/{period}`, `/sales/{period}/{limit}`
  - `/customers`, `/customers/{period}`, `/customers/{period}/{limit}`
  - `/products`, `/products/{period}`, `/products/{period}/{limit}`
  - `/income`, `/income/{period}`, `/income/{period}/{limit}`
  - `/expense`
  - `/stock`
- Bloquea archivos sensibles: `config.local.php`, ejemplos de config, README, composer.

## 5) Base de datos (modelo)

**Archivo**: `database/schema.sql`

Tablas:

- `users`:
  - `email` (único)
  - `password_hash` (bcrypt recomendado)
- `invoices`:
  - `created_by` (FK a users)
  - `customer_name`, `customer_email`, `customer_dni` (opcional)
  - `detail`
  - `currency`
  - `total_cents`
  - `created_at`
- `invoice_items`:
  - `invoice_id` (FK a invoices)
  - `description`
  - `quantity`
  - `unit_price_cents`, `line_total_cents`

- `finance_entries`:
  - Asientos manuales para finanzas (en el uso actual, principalmente **egresos**).
  - `entry_type` (income/expense), `amount_cents`, `currency`, `entry_date`, `created_by`.

- `stock_items`:
  - Ítems de stock por usuario.
  - `name`, `sku` (opcional), `unit`, `quantity`, `created_by`.

Índices relevantes para performance:

- `idx_invoices_created_by_created_at` (reportes por usuario y rango)
- `idx_invoices_customer_dni` (si se usa búsqueda por DNI)

## 6) Dependencias (qué habilitan)

Instalación típica:

- `composer install --no-dev --optimize-autoloader`

Librerías usadas si están disponibles:

- PDF con plantilla: `setasign/fpdi` (+ `setasign/fpdf`).
- PDF por HTML: `dompdf/dompdf`.
- XLSX: `phpoffice/phpspreadsheet`.
- Email robusto: `phpmailer/phpmailer`.

Si faltan dependencias:

- PDF puede caer a HTML.
- XLSX falla con mensaje “Falta instalar dependencias para XLSX”.
- Email usa `mail()` (puede depender del hosting).

---

## 7) Alcances y limitaciones actuales

- No hay gestión de roles/permisos: “Admin” es un label visual, la autorización real es “logueado / no logueado”.
- No hay ABM de clientes o productos: se ingresan productos como texto en la factura y los reportes agregan por descripción.
- La moneda se usa en facturas como `ARS` desde el dashboard.
- La fecha se toma de `created_at` (timestamp de MySQL).
