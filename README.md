# Dietetic (Login + Dashboard)

## Estructura
- `public/`: subir a `public_html/` (root del sitio)
- `src/`: subir **fuera** de `public_html/` (mismo nivel)
- `database/schema.sql`: crear tabla `users`

## Config (Hostinger)
1. En el File Manager, al mismo nivel que `public_html/`, creá la carpeta `src` y subí ahí el contenido de `src/`.
2. Subí el contenido de `public/` dentro de `public_html/`.
3. Creá `config.local.php` al mismo nivel que `public_html/` (root) usando `config.local.php.example` como guía.

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
