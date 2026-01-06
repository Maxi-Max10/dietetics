<?php

declare(strict_types=1);

/**
 * Catálogo de productos (lista de precios).
 */

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
        return $cache;
    } catch (Throwable $e) {
        $cache = false;
        return false;
    }
}

/**
 * @return array<int, array{id:int,name:string,price_cents:int,currency:string,updated_at:string,created_at:string}>
 */
function catalog_list(PDO $pdo, int $createdBy, string $search = '', int $limit = 200): array
{
    $limit = max(1, min(500, (int)$limit));
    $search = trim($search);

    $where = 'created_by = :created_by';
    $params = ['created_by' => $createdBy];

    if ($search !== '') {
        $where .= ' AND name LIKE :q';
        $params['q'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, price_cents, currency, updated_at, created_at
         FROM catalog_products
         WHERE ' . $where . '
         ORDER BY name ASC, id ASC
         LIMIT ' . $limit
    );
    $stmt->execute($params);

    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int)($r['id'] ?? 0),
            'name' => (string)($r['name'] ?? ''),
            'price_cents' => (int)($r['price_cents'] ?? 0),
            'currency' => (string)($r['currency'] ?? 'ARS'),
            'updated_at' => (string)($r['updated_at'] ?? ''),
            'created_at' => (string)($r['created_at'] ?? ''),
        ];
    }
    return $out;
}

/** @return array{id:int,name:string,price_cents:int,currency:string,updated_at:string,created_at:string} */
function catalog_get(PDO $pdo, int $createdBy, int $id): array
{
    $id = (int)$id;
    if ($id <= 0) {
        throw new InvalidArgumentException('Producto inválido.');
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, price_cents, currency, updated_at, created_at
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
        'price_cents' => (int)($r['price_cents'] ?? 0),
        'currency' => (string)($r['currency'] ?? 'ARS'),
        'updated_at' => (string)($r['updated_at'] ?? ''),
        'created_at' => (string)($r['created_at'] ?? ''),
    ];
}

function catalog_create(PDO $pdo, int $createdBy, string $name, string|float|int $price, string $currency = 'ARS'): int
{
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Nombre requerido.');
    }
    $nameLen = function_exists('mb_strlen') ? (int)mb_strlen($name, 'UTF-8') : strlen($name);
    if ($nameLen > 190) {
        throw new InvalidArgumentException('Nombre demasiado largo.');
    }

    $priceFloat = (float)str_replace(',', '.', (string)$price);
    if (!is_finite($priceFloat) || $priceFloat < 0) {
        throw new InvalidArgumentException('Precio inválido.');
    }
    $priceCents = (int)round($priceFloat * 100);

    $currency = strtoupper(trim($currency));
    if ($currency === '') {
        $currency = 'ARS';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO catalog_products (created_by, name, price_cents, currency)
         VALUES (:created_by, :name, :price_cents, :currency)'
    );
    $stmt->execute([
        'created_by' => $createdBy,
        'name' => $name,
        'price_cents' => $priceCents,
        'currency' => $currency,
    ]);

    return (int)$pdo->lastInsertId();
}

function catalog_update(PDO $pdo, int $createdBy, int $id, string $name, string|float|int $price, string $currency = 'ARS'): void
{
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

    $priceFloat = (float)str_replace(',', '.', (string)$price);
    if (!is_finite($priceFloat) || $priceFloat < 0) {
        throw new InvalidArgumentException('Precio inválido.');
    }
    $priceCents = (int)round($priceFloat * 100);

    $currency = strtoupper(trim($currency));
    if ($currency === '') {
        $currency = 'ARS';
    }

    $stmt = $pdo->prepare(
        'UPDATE catalog_products
         SET name = :name, price_cents = :price_cents, currency = :currency
         WHERE id = :id AND created_by = :created_by'
    );
    $stmt->execute([
        'id' => $id,
        'created_by' => $createdBy,
        'name' => $name,
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
