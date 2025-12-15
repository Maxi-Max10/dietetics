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
