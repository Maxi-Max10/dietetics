<?php

declare(strict_types=1);

function stock_create_item(PDO $pdo, int $createdBy, string $name, string $sku = '', string $unit = '', string|int|float $quantity = 0): int
{
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Nombre requerido.');
    }

    $sku = trim($sku);
    $unit = trim($unit);

    $qty = (float)str_replace(',', '.', (string)$quantity);
    if (!is_finite($qty)) {
        throw new InvalidArgumentException('Cantidad inválida.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO stock_items (created_by, name, sku, unit, quantity)
         VALUES (:created_by, :name, :sku, :unit, :quantity)'
    );

    $stmt->execute([
        'created_by' => $createdBy,
        'name' => $name,
        'sku' => ($sku === '' ? null : $sku),
        'unit' => ($unit === '' ? null : $unit),
        'quantity' => number_format($qty, 2, '.', ''),
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * @return array<int, array{id:int,name:string,sku:string,unit:string,quantity:string,updated_at:string}>
 */
function stock_list_items(PDO $pdo, int $createdBy, string $q = '', int $limit = 100): array
{
    $q = trim($q);
    $limit = max(1, min(200, (int)$limit));

    $where = 'created_by = :created_by';
    $params = ['created_by' => $createdBy];

    if ($q !== '') {
        $where .= ' AND (name LIKE :q OR sku LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, COALESCE(sku, \'\') AS sku, COALESCE(unit, \'\') AS unit, quantity, updated_at
         FROM stock_items
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
            'sku' => (string)($r['sku'] ?? ''),
            'unit' => (string)($r['unit'] ?? ''),
            'quantity' => (string)($r['quantity'] ?? '0.00'),
            'updated_at' => (string)($r['updated_at'] ?? ''),
        ];
    }

    return $out;
}

function stock_adjust(PDO $pdo, int $createdBy, int $itemId, string|int|float $delta): void
{
    $itemId = (int)$itemId;
    if ($itemId <= 0) {
        throw new InvalidArgumentException('Item inválido.');
    }

    $d = (float)str_replace(',', '.', (string)$delta);
    if (!is_finite($d) || $d == 0.0) {
        throw new InvalidArgumentException('Ajuste inválido.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT quantity FROM stock_items WHERE id = :id AND created_by = :created_by LIMIT 1');
        $stmt->execute(['id' => $itemId, 'created_by' => $createdBy]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Item no encontrado.');
        }

        $current = (float)($row['quantity'] ?? 0);
        $new = $current + $d;
        if ($new < 0) {
            throw new InvalidArgumentException('El stock no puede quedar negativo.');
        }

        $upd = $pdo->prepare('UPDATE stock_items SET quantity = :quantity WHERE id = :id AND created_by = :created_by');
        $upd->execute([
            'quantity' => number_format($new, 2, '.', ''),
            'id' => $itemId,
            'created_by' => $createdBy,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
