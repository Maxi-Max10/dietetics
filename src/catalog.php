<?php

declare(strict_types=1);

/**
 * Catálogo de productos (lista de precios).
 */

function catalog_parse_price_to_cents(string|float|int $price): int
{
    $raw = trim((string)$price);
    if ($raw === '') {
        throw new InvalidArgumentException('Precio inválido.');
    }

    $s = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
    $s = str_replace(['$', '€'], '', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    $s = is_string($s) ? trim($s) : trim($raw);

    // Aceptar miles con espacio (ej: "13 000")
    $sNoSpaces = str_replace(' ', '', $s);
    if ($sNoSpaces !== '') {
        $s = $sNoSpaces;
    }

    $mult = 1.0;
    if (preg_match('/^([0-9]+(?:[\.,][0-9]{1,2})?)(?:\s*)?(mil|miles|k)\b/u', $s, $m) === 1) {
        $mult = 1000.0;
        $s = (string)$m[1];
    }

    // Normalizar separadores
    $hasDot = str_contains($s, '.');
    $hasComma = str_contains($s, ',');
    if ($hasDot && $hasComma) {
        // 13.000,50 -> 13000.50
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif ($hasComma && !$hasDot) {
        // 13,50 -> 13.50
        $s = str_replace(',', '.', $s);
    } else {
        // dejar '.' como decimal
    }

    // Validar formato numérico final
    if (preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/', $s) !== 1) {
        throw new InvalidArgumentException('Precio inválido.');
    }

    $value = (float)$s;
    if (!is_finite($value) || $value < 0) {
        throw new InvalidArgumentException('Precio inválido.');
    }

    $cents = (int)round(($value * $mult) * 100);
    return $cents;
}

function catalog_supports_table(PDO $pdo): bool
{
    static $cache = null;
    if (is_bool($cache)) {
        return $cache;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :t
             LIMIT 1"
        );
        $stmt->execute(['t' => 'catalog_products']);
        $cache = (bool)$stmt->fetchColumn();
        if ($cache) {
            return true;
        }

        catalog_try_create_table($pdo);

        $stmt->execute(['t' => 'catalog_products']);
        $cache = (bool)$stmt->fetchColumn();
        return $cache;
    } catch (Throwable $e) {
        $cache = false;
        return false;
    }
}

function catalog_try_create_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS catalog_products (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_by INT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            description VARCHAR(255) NULL,
            unit VARCHAR(24) NULL,
            price_cents INT UNSIGNED NOT NULL DEFAULT 0,
            currency CHAR(3) NOT NULL DEFAULT 'ARS',
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_catalog_products_user_name (created_by, name),
            KEY idx_catalog_products_created_by (created_by),
            CONSTRAINT fk_catalog_products_users
              FOREIGN KEY (created_by) REFERENCES users(id)
              ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function catalog_ensure_description_column(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    if (!catalog_supports_table($pdo)) {
        throw new RuntimeException('No se encontró la tabla del catálogo.');
    }

    $stmt = $pdo->prepare(
        "SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :t
           AND COLUMN_NAME = :c
         LIMIT 1"
    );
    $stmt->execute(['t' => 'catalog_products', 'c' => 'description']);
    $has = (bool)$stmt->fetchColumn();
    if ($has) {
        $done = true;
        return;
    }

    try {
        $pdo->exec('ALTER TABLE catalog_products ADD COLUMN description VARCHAR(255) NULL AFTER name');
    } catch (Throwable $e) {
        throw new RuntimeException(
            'Falta la columna description en catalog_products. Ejecutá: ALTER TABLE catalog_products ADD COLUMN description VARCHAR(255) NULL AFTER name;',
            0,
            $e
        );
    }

    $done = true;
}

function catalog_normalize_unit(string $unit): string
{
    $u = trim($unit);
    if ($u === '') {
        return '';
    }

    $u = function_exists('mb_strtolower') ? mb_strtolower($u, 'UTF-8') : strtolower($u);
    $u = str_replace(['.', ' '], '', $u);

    // Alias comunes
    if (in_array($u, ['u', 'un', 'uni', 'unidad', 'unidades', 'unit'], true)) {
        return 'un';
    }
    if (in_array($u, ['kg', 'kilo', 'kilos', 'kgs'], true)) {
        return 'kg';
    }
    if (in_array($u, ['g', 'gr', 'gramo', 'gramos'], true)) {
        return 'g';
    }
    if (in_array($u, ['l', 'lt', 'lts', 'litro', 'litros'], true)) {
        return 'l';
    }
    if (in_array($u, ['ml', 'mililitro', 'mililitros'], true)) {
        return 'ml';
    }

    // Si no coincide, rechazar (evitamos texto libre largo)
    throw new InvalidArgumentException('Unidad inválida. Usá: un, kg, g, l o ml.');
}

function catalog_ensure_unit_column(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    if (!catalog_supports_table($pdo)) {
        throw new RuntimeException('No se encontró la tabla del catálogo.');
    }

    $stmt = $pdo->prepare(
        "SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :t
           AND COLUMN_NAME = :c
         LIMIT 1"
    );
    $stmt->execute(['t' => 'catalog_products', 'c' => 'unit']);
    $has = (bool)$stmt->fetchColumn();
    if ($has) {
        $done = true;
        return;
    }

    try {
        $pdo->exec('ALTER TABLE catalog_products ADD COLUMN unit VARCHAR(24) NULL AFTER description');
    } catch (Throwable $e) {
        throw new RuntimeException(
            'Falta la columna unit en catalog_products. Ejecutá: ALTER TABLE catalog_products ADD COLUMN unit VARCHAR(24) NULL AFTER description;',
            0,
            $e
        );
    }

    $done = true;
}

/**
 * @return array<int, array{id:int,name:string,description:string,price_cents:int,currency:string,updated_at:string,created_at:string}>
 */
function catalog_list(PDO $pdo, int $createdBy, string $search = '', int $limit = 200): array
{
    catalog_ensure_description_column($pdo);
    catalog_ensure_unit_column($pdo);

    $limit = max(1, min(500, (int)$limit));
    $search = trim($search);

    $where = 'created_by = :created_by';
    $params = ['created_by' => $createdBy];

    $rows = null;
    $lastError = null;

    if ($search !== '') {
        $params['q'] = '%' . $search . '%';

        $searchVariants = [
            ' AND (CONVERT(name USING utf8mb4) LIKE CONVERT(:q USING utf8mb4) OR CONVERT(description USING utf8mb4) LIKE CONVERT(:q USING utf8mb4))',
            ' AND (CONVERT(name USING utf8) LIKE CONVERT(:q USING utf8) OR CONVERT(description USING utf8) LIKE CONVERT(:q USING utf8))',
            ' AND (name LIKE :q OR description LIKE :q)',
        ];

        foreach ($searchVariants as $searchWhere) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT id, name, description, COALESCE(unit, "") AS unit, price_cents, currency, updated_at, created_at
                     FROM catalog_products
                     WHERE ' . $where . $searchWhere . '
                     ORDER BY name ASC, id ASC
                     LIMIT ' . $limit
                );
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
                break;
            } catch (Throwable $e) {
                $lastError = $e;
            }
        }
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, name, description, COALESCE(unit, "") AS unit, price_cents, currency, updated_at, created_at
             FROM catalog_products
             WHERE ' . $where . '
             ORDER BY name ASC, id ASC
             LIMIT ' . $limit
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    }

    if ($rows === null) {
        throw $lastError ?? new RuntimeException('No se pudo cargar el catálogo.');
    }
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int)($r['id'] ?? 0),
            'name' => (string)($r['name'] ?? ''),
            'description' => (string)($r['description'] ?? ''),
            'unit' => (string)($r['unit'] ?? ''),
            'price_cents' => (int)($r['price_cents'] ?? 0),
            'currency' => (string)($r['currency'] ?? 'ARS'),
            'updated_at' => (string)($r['updated_at'] ?? ''),
            'created_at' => (string)($r['created_at'] ?? ''),
        ];
    }
    return $out;
}

/** @return array{id:int,name:string,description:string,price_cents:int,currency:string,updated_at:string,created_at:string} */
function catalog_get(PDO $pdo, int $createdBy, int $id): array
{
    catalog_ensure_description_column($pdo);
    catalog_ensure_unit_column($pdo);

    $id = (int)$id;
    if ($id <= 0) {
        throw new InvalidArgumentException('Producto inválido.');
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, description, COALESCE(unit, "") AS unit, price_cents, currency, updated_at, created_at
         FROM catalog_products
         WHERE id = :id AND created_by = :created_by
         LIMIT 1'
    );
    $stmt->execute(['id' => $id, 'created_by' => $createdBy]);
    $r = $stmt->fetch();
    if (!$r) {
        throw new RuntimeException('Producto no encontrado.');
    }

    return [
        'id' => (int)($r['id'] ?? 0),
        'name' => (string)($r['name'] ?? ''),
        'description' => (string)($r['description'] ?? ''),
        'unit' => (string)($r['unit'] ?? ''),
        'price_cents' => (int)($r['price_cents'] ?? 0),
        'currency' => (string)($r['currency'] ?? 'ARS'),
        'updated_at' => (string)($r['updated_at'] ?? ''),
        'created_at' => (string)($r['created_at'] ?? ''),
    ];
}

function catalog_create(PDO $pdo, int $createdBy, string $name, string|float|int $price, string $currency = 'ARS', string $description = '', string $unit = ''): int
{
    catalog_ensure_description_column($pdo);
    catalog_ensure_unit_column($pdo);

    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Nombre requerido.');
    }
    $nameLen = function_exists('mb_strlen') ? (int)mb_strlen($name, 'UTF-8') : strlen($name);
    if ($nameLen > 190) {
        throw new InvalidArgumentException('Nombre demasiado largo.');
    }

    $priceCents = catalog_parse_price_to_cents($price);

    $currency = strtoupper(trim($currency));
    if ($currency === '') {
        $currency = 'ARS';
    }

    $description = trim($description);
    $descLen = function_exists('mb_strlen') ? (int)mb_strlen($description, 'UTF-8') : strlen($description);
    if ($descLen > 255) {
        throw new InvalidArgumentException('Descripción demasiado larga.');
    }

    $unitNorm = '';
    if (trim($unit) !== '') {
        $unitNorm = catalog_normalize_unit($unit);
    }

    $unitLen = function_exists('mb_strlen') ? (int)mb_strlen($unitNorm, 'UTF-8') : strlen($unitNorm);
    if ($unitLen > 24) {
        throw new InvalidArgumentException('Unidad demasiado larga.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO catalog_products (created_by, name, description, unit, price_cents, currency)
         VALUES (:created_by, :name, :description, :unit, :price_cents, :currency)'
    );
    $stmt->execute([
        'created_by' => $createdBy,
        'name' => $name,
        'description' => ($description === '' ? null : $description),
        'unit' => ($unitNorm === '' ? null : $unitNorm),
        'price_cents' => $priceCents,
        'currency' => $currency,
    ]);

    return (int)$pdo->lastInsertId();
}

function catalog_update(PDO $pdo, int $createdBy, int $id, string $name, string|float|int $price, string $currency = 'ARS', string $description = '', string $unit = ''): void
{
    catalog_ensure_description_column($pdo);
    catalog_ensure_unit_column($pdo);

    $id = (int)$id;
    if ($id <= 0) {
        throw new InvalidArgumentException('Producto inválido.');
    }

    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Nombre requerido.');
    }
    $nameLen = function_exists('mb_strlen') ? (int)mb_strlen($name, 'UTF-8') : strlen($name);
    if ($nameLen > 190) {
        throw new InvalidArgumentException('Nombre demasiado largo.');
    }

    $priceCents = catalog_parse_price_to_cents($price);

    $currency = strtoupper(trim($currency));
    if ($currency === '') {
        $currency = 'ARS';
    }

    $description = trim($description);
    $descLen = function_exists('mb_strlen') ? (int)mb_strlen($description, 'UTF-8') : strlen($description);
    if ($descLen > 255) {
        throw new InvalidArgumentException('Descripción demasiado larga.');
    }

    $unitNorm = '';
    if (trim($unit) !== '') {
        $unitNorm = catalog_normalize_unit($unit);
    }

    $unitLen = function_exists('mb_strlen') ? (int)mb_strlen($unitNorm, 'UTF-8') : strlen($unitNorm);
    if ($unitLen > 24) {
        throw new InvalidArgumentException('Unidad demasiado larga.');
    }

    $stmt = $pdo->prepare(
        'UPDATE catalog_products
         SET name = :name, description = :description, unit = :unit, price_cents = :price_cents, currency = :currency
         WHERE id = :id AND created_by = :created_by'
    );
    $stmt->execute([
        'id' => $id,
        'created_by' => $createdBy,
        'name' => $name,
        'description' => ($description === '' ? null : $description),
        'unit' => ($unitNorm === '' ? null : $unitNorm),
        'price_cents' => $priceCents,
        'currency' => $currency,
    ]);

    if ($stmt->rowCount() === 0) {
        // Puede ser que no exista o que no haya cambios; validamos existencia.
        catalog_get($pdo, $createdBy, $id);
    }
}

function catalog_delete(PDO $pdo, int $createdBy, int $id): void
{
    $id = (int)$id;
    if ($id <= 0) {
        throw new InvalidArgumentException('Producto inválido.');
    }

    $stmt = $pdo->prepare('DELETE FROM catalog_products WHERE id = :id AND created_by = :created_by');
    $stmt->execute(['id' => $id, 'created_by' => $createdBy]);
}
