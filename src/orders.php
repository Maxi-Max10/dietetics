<?php

declare(strict_types=1);

/**
 * Pedidos públicos (lista de precios + encargo para retiro).
 */

function orders_supports_tables(PDO $pdo): bool
{
    static $cache = null;
    if (is_bool($cache)) {
        return $cache;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ('customer_orders','customer_order_items')"
        );
        $stmt->execute();
        $cache = ((int)$stmt->fetchColumn() >= 2);
        return $cache;
    } catch (Throwable $e) {
        $cache = false;
        return false;
    }
}

function orders_supports_item_customer_fields(PDO $pdo): bool
{
    static $cache = null;
    if (is_bool($cache)) {
        return $cache;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'customer_order_items'
               AND COLUMN_NAME IN ('customer_email','customer_dni')"
        );
        $stmt->execute();
        $cache = ((int)$stmt->fetchColumn() >= 2);
        return $cache;
    } catch (Throwable $e) {
        $cache = false;
        return false;
    }
}

function orders_count_status(PDO $pdo, int $createdBy, string $status = ''): int
{
    if (!orders_supports_tables($pdo)) {
        return 0;
    }

    $createdBy = (int)$createdBy;
    if ($createdBy <= 0) {
        return 0;
    }

    $status = trim($status);

    $sql = 'SELECT COUNT(*) FROM customer_orders WHERE created_by = :user_id';
    $params = ['user_id' => $createdBy];

    if ($status !== '') {
        $sql .= ' AND status = :status';
        $params['status'] = $status;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function orders_count_new(PDO $pdo, int $createdBy): int
{
    return orders_count_status($pdo, $createdBy, 'new');
}

function orders_invoice_marker(int $orderId): string
{
    return '[order:' . (int)$orderId . ']';
}

function orders_find_invoice_for_order(PDO $pdo, int $createdBy, int $orderId): int
{
    $createdBy = (int)$createdBy;
    $orderId = (int)$orderId;
    if ($createdBy <= 0 || $orderId <= 0) {
        return 0;
    }

    // Evitar duplicados: buscamos un marcador en el campo detail.
    $needle = '%' . orders_invoice_marker($orderId) . '%';

    try {
        $stmt = $pdo->prepare(
            'SELECT id
             FROM invoices
             WHERE created_by = :user_id
               AND COALESCE(detail, \'\') LIKE :needle
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $createdBy,
            'needle' => $needle,
        ]);
        return (int)($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        // Si la tabla no existe (setup parcial) o falla la query, no bloqueamos.
        return 0;
    }
}

function orders_sync_invoice_customer_fields(PDO $pdo, int $createdBy, int $invoiceId, string $customerName, string $customerEmail, string $customerDni, string $customerPhone, string $customerAddress): void
{
    $createdBy = (int)$createdBy;
    $invoiceId = (int)$invoiceId;
    if ($createdBy <= 0 || $invoiceId <= 0) {
        return;
    }

    $supportsDni = invoices_supports_customer_dni($pdo);
    $supportsPhone = invoices_supports_customer_phone($pdo);
    $supportsAddress = invoices_supports_customer_address($pdo);

    $cols = ['customer_name', 'customer_email'];
    if ($supportsDni) {
        $cols[] = 'customer_dni';
    }
    if ($supportsPhone) {
        $cols[] = 'customer_phone';
    }
    if ($supportsAddress) {
        $cols[] = 'customer_address';
    }

    $stmt = $pdo->prepare('SELECT ' . implode(', ', $cols) . ' FROM invoices WHERE id = :id AND created_by = :created_by LIMIT 1');
    $stmt->execute(['id' => $invoiceId, 'created_by' => $createdBy]);
    $inv = $stmt->fetch();
    if (!$inv || !is_array($inv)) {
        return;
    }

    $set = [];
    $params = ['id' => $invoiceId, 'created_by' => $createdBy];

    $newName = trim($customerName);
    if ($newName !== '' && trim((string)($inv['customer_name'] ?? '')) === '') {
        $set[] = 'customer_name = :customer_name';
        $params['customer_name'] = $newName;
    }

    $newEmail = trim($customerEmail);
    if ($newEmail !== '' && trim((string)($inv['customer_email'] ?? '')) === '') {
        $set[] = 'customer_email = :customer_email';
        $params['customer_email'] = $newEmail;
    }

    $newDni = trim($customerDni);
    if ($supportsDni && $newDni !== '' && trim((string)($inv['customer_dni'] ?? '')) === '') {
        $set[] = 'customer_dni = :customer_dni';
        $params['customer_dni'] = $newDni;
    }

    $newPhone = trim($customerPhone);
    if ($supportsPhone && $newPhone !== '' && trim((string)($inv['customer_phone'] ?? '')) === '') {
        $set[] = 'customer_phone = :customer_phone';
        $params['customer_phone'] = $newPhone;
    }

    $newAddress = trim($customerAddress);
    if ($supportsAddress && $newAddress !== '' && trim((string)($inv['customer_address'] ?? '')) === '') {
        $set[] = 'customer_address = :customer_address';
        $params['customer_address'] = $newAddress;
    }

    if (count($set) === 0) {
        return;
    }

    $upd = $pdo->prepare('UPDATE invoices SET ' . implode(', ', $set) . ' WHERE id = :id AND created_by = :created_by');
    $upd->execute($params);
}

function orders_public_catalog_owner_id(PDO $pdo, array $config): int
{
    $cfg = $config['public_catalog'] ?? [];
    $raw = (string)($cfg['user_id'] ?? '');
    $raw = trim($raw);

    if ($raw !== '' && ctype_digit($raw)) {
        $id = (int)$raw;
        if ($id > 0) {
            return $id;
        }
    }

    // Fallback: primer usuario (instalación single-admin)
    $stmt = $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1');
    $id = (int)$stmt->fetchColumn();
    if ($id <= 0) {
        throw new RuntimeException('No hay usuarios creados. Creá un usuario admin primero.');
    }
    return $id;
}

function orders_parse_qty(string|float|int $qty): float
{
    $s = trim((string)$qty);
    if ($s === '') {
        throw new InvalidArgumentException('Cantidad inválida.');
    }

    // Aceptar coma decimal
    $s = str_replace(',', '.', $s);

    if (!preg_match('/^[0-9]+(?:\.[0-9]{1,3})?$/', $s)) {
        throw new InvalidArgumentException('Cantidad inválida.');
    }

    $v = (float)$s;
    if (!is_finite($v) || $v <= 0) {
        throw new InvalidArgumentException('Cantidad inválida.');
    }

    // límites razonables
    if ($v > 9999) {
        throw new InvalidArgumentException('Cantidad demasiado grande.');
    }

    return $v;
}

/**
 * @param array<int, array{product_id:int, quantity:string|float|int}> $items
 * @return array{order_id:int,total_cents:int,currency:string}
 */
function orders_create_public(PDO $pdo, int $createdBy, string $customerName, string $customerPhone, string $customerAddress, string $notes, array $items, string $customerEmail = '', string $customerDni = ''): array
{
    if (!orders_supports_tables($pdo)) {
        throw new RuntimeException('No se encontraron las tablas de pedidos. Ejecutá database/schema.sql.');
    }

    $createdBy = (int)$createdBy;
    if ($createdBy <= 0) {
        throw new InvalidArgumentException('Usuario inválido.');
    }

    $customerName = trim($customerName);
    if ($customerName === '') {
        throw new InvalidArgumentException('Nombre requerido.');
    }
    $nameLen = function_exists('mb_strlen') ? (int)mb_strlen($customerName, 'UTF-8') : strlen($customerName);
    if ($nameLen > 190) {
        throw new InvalidArgumentException('Nombre demasiado largo.');
    }

    $customerPhone = trim($customerPhone);
    if ($customerPhone !== '' && (function_exists('mb_strlen') ? (int)mb_strlen($customerPhone, 'UTF-8') : strlen($customerPhone)) > 40) {
        throw new InvalidArgumentException('Teléfono demasiado largo.');
    }

    $customerAddress = trim($customerAddress);
    if ($customerAddress !== '' && (function_exists('mb_strlen') ? (int)mb_strlen($customerAddress, 'UTF-8') : strlen($customerAddress)) > 255) {
        throw new InvalidArgumentException('Dirección demasiado larga.');
    }

    $notes = trim($notes);

    if (!is_array($items) || count($items) === 0) {
        throw new InvalidArgumentException('No hay productos en el pedido.');
    }

    $productIds = [];
    $qtyByProduct = [];

    foreach ($items as $it) {
        $pid = (int)($it['product_id'] ?? 0);
        if ($pid <= 0) {
            throw new InvalidArgumentException('Producto inválido.');
        }
        $qty = orders_parse_qty($it['quantity'] ?? '');

        $productIds[] = $pid;
        $qtyByProduct[$pid] = ($qtyByProduct[$pid] ?? 0.0) + $qty;
    }

    $productIds = array_values(array_unique(array_map('intval', $productIds)));
    if (count($productIds) === 0) {
        throw new InvalidArgumentException('No hay productos en el pedido.');
    }

    // Traer productos válidos del catálogo del usuario
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $sql = 'SELECT id, name, COALESCE(description, "") AS description, price_cents, currency FROM catalog_products WHERE created_by = ? AND id IN (' . $placeholders . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$createdBy], $productIds));
    $rows = $stmt->fetchAll();

    $byId = [];
    foreach ($rows as $r) {
        $id = (int)($r['id'] ?? 0);
        if ($id > 0) {
            $byId[$id] = $r;
        }
    }

    // Validar que existan todos
    foreach ($productIds as $pid) {
        if (!isset($byId[$pid])) {
            throw new RuntimeException('Uno o más productos ya no están disponibles. Actualizá la lista y probá de nuevo.');
        }
    }

    // Moneda: si el catálogo mezcla monedas, tomamos la primera y seguimos (simple)
    $currency = (string)($rows[0]['currency'] ?? 'ARS');
    if ($currency === '') {
        $currency = 'ARS';
    }

    $orderItems = [];
    $totalCents = 0;

    foreach ($productIds as $pid) {
        $r = $byId[$pid];
        $qty = (float)($qtyByProduct[$pid] ?? 0.0);
        if ($qty <= 0) {
            continue;
        }

        $unitPriceCents = (int)($r['price_cents'] ?? 0);
        $lineTotal = (int)round($unitPriceCents * $qty);
        if ($lineTotal < 0) {
            $lineTotal = 0;
        }

        $label = trim((string)($r['name'] ?? ''));
        if ($label === '') {
            $label = 'Producto #' . $pid;
        }

        $orderItems[] = [
            'product_id' => $pid,
            'description' => $label,
            'quantity' => $qty,
            'unit_price_cents' => $unitPriceCents,
            'line_total_cents' => $lineTotal,
        ];

        $totalCents += $lineTotal;
    }

    if (count($orderItems) === 0) {
        throw new InvalidArgumentException('No hay productos en el pedido.');
    }

    $pdo->beginTransaction();
    try {
        $paramsBase = [
            'created_by' => $createdBy,
            'customer_name' => $customerName,
            'customer_phone' => ($customerPhone === '' ? null : $customerPhone),
            'customer_address' => ($customerAddress === '' ? null : $customerAddress),
            'notes' => ($notes === '' ? null : $notes),
            'currency' => $currency,
            'total_cents' => $totalCents,
            'status' => 'new',
        ];

        // Compatibilidad: si la DB no tiene aún customer_email/customer_dni, no rompemos el insert.
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO customer_orders (created_by, customer_name, customer_phone, customer_email, customer_dni, customer_address, notes, currency, total_cents, status)
                 VALUES (:created_by, :customer_name, :customer_phone, :customer_email, :customer_dni, :customer_address, :notes, :currency, :total_cents, :status)'
            );
            $stmt->execute($paramsBase + [
                'customer_email' => trim($customerEmail) !== '' ? trim($customerEmail) : null,
                'customer_dni' => trim($customerDni) !== '' ? trim($customerDni) : null,
            ]);
        } catch (Throwable $e) {
            $stmt = $pdo->prepare(
                'INSERT INTO customer_orders (created_by, customer_name, customer_phone, customer_address, notes, currency, total_cents, status)
                 VALUES (:created_by, :customer_name, :customer_phone, :customer_address, :notes, :currency, :total_cents, :status)'
            );
            $stmt->execute($paramsBase);
        }

        $orderId = (int)$pdo->lastInsertId();
        if ($orderId <= 0) {
            throw new RuntimeException('No se pudo crear el pedido.');
        }

        $supportsItemCustomerFields = orders_supports_item_customer_fields($pdo);
        if ($supportsItemCustomerFields) {
            $itemStmt = $pdo->prepare(
                'INSERT INTO customer_order_items (order_id, product_id, description, customer_email, customer_dni, quantity, unit_price_cents, line_total_cents)
                 VALUES (:order_id, :product_id, :description, :customer_email, :customer_dni, :quantity, :unit_price_cents, :line_total_cents)'
            );
        } else {
            $itemStmt = $pdo->prepare(
                'INSERT INTO customer_order_items (order_id, product_id, description, quantity, unit_price_cents, line_total_cents)
                 VALUES (:order_id, :product_id, :description, :quantity, :unit_price_cents, :line_total_cents)'
            );
        }

        foreach ($orderItems as $it) {
            $params = [
                'order_id' => $orderId,
                'product_id' => (int)$it['product_id'],
                'description' => (string)$it['description'],
                'quantity' => (string)$it['quantity'],
                'unit_price_cents' => (int)$it['unit_price_cents'],
                'line_total_cents' => (int)$it['line_total_cents'],
            ];
            if ($supportsItemCustomerFields) {
                $params['customer_email'] = trim($customerEmail) !== '' ? trim($customerEmail) : null;
                $params['customer_dni'] = trim($customerDni) !== '' ? trim($customerDni) : null;
            }
            $itemStmt->execute($params);
        }

        $pdo->commit();
        return ['order_id' => $orderId, 'total_cents' => $totalCents, 'currency' => $currency];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * @return array<int, array{id:int,customer_name:string,customer_phone:string,customer_address:string,notes:string,currency:string,total_cents:int,status:string,created_at:string}>
 */
function orders_list(PDO $pdo, int $createdBy, string $status = '', int $limit = 200): array
{
    if (!orders_supports_tables($pdo)) {
        throw new RuntimeException('No se encontraron las tablas de pedidos.');
    }

    $createdBy = (int)$createdBy;
    $limit = max(1, min(500, (int)$limit));
    $status = trim($status);

    $where = 'created_by = :created_by';
    $params = ['created_by' => $createdBy];

    if ($status !== '') {
        $where .= ' AND status = :status';
        $params['status'] = $status;
    }

    $stmt = $pdo->prepare(
        'SELECT id, customer_name, COALESCE(customer_phone, "") AS customer_phone, COALESCE(customer_address, "") AS customer_address,
                COALESCE(notes, "") AS notes, currency, total_cents, status, created_at
         FROM customer_orders
         WHERE ' . $where . '
         ORDER BY created_at DESC, id DESC
         LIMIT ' . $limit
    );
    $stmt->execute($params);

    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int)($r['id'] ?? 0),
            'customer_name' => (string)($r['customer_name'] ?? ''),
            'customer_phone' => (string)($r['customer_phone'] ?? ''),
            'customer_address' => (string)($r['customer_address'] ?? ''),
            'notes' => (string)($r['notes'] ?? ''),
            'currency' => (string)($r['currency'] ?? 'ARS'),
            'total_cents' => (int)($r['total_cents'] ?? 0),
            'status' => (string)($r['status'] ?? 'new'),
            'created_at' => (string)($r['created_at'] ?? ''),
        ];
    }
    return $out;
}

/**
 * Listado de pedidos en un rango [start, end) por created_at.
 *
 * @return array<int, array{id:int,customer_name:string,customer_phone:string,customer_address:string,notes:string,currency:string,total_cents:int,status:string,created_at:string}>
 */
function orders_list_between(PDO $pdo, int $createdBy, DateTimeImmutable $start, DateTimeImmutable $end, string $status = '', int $limit = 200): array
{
    if (!orders_supports_tables($pdo)) {
        throw new RuntimeException('No se encontraron las tablas de pedidos.');
    }

    $createdBy = (int)$createdBy;
    $limit = max(1, min(500, (int)$limit));
    $status = trim($status);

    $where = 'created_by = :created_by AND created_at >= :start AND created_at < :end';
    $params = [
        'created_by' => $createdBy,
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
    ];

    if ($status !== '') {
        $where .= ' AND status = :status';
        $params['status'] = $status;
    }

    $stmt = $pdo->prepare(
        'SELECT id, customer_name, COALESCE(customer_phone, "") AS customer_phone, COALESCE(customer_address, "") AS customer_address,
                COALESCE(notes, "") AS notes, currency, total_cents, status, created_at
         FROM customer_orders
         WHERE ' . $where . '
         ORDER BY created_at DESC, id DESC
         LIMIT ' . $limit
    );
    $stmt->execute($params);

    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int)($r['id'] ?? 0),
            'customer_name' => (string)($r['customer_name'] ?? ''),
            'customer_phone' => (string)($r['customer_phone'] ?? ''),
            'customer_address' => (string)($r['customer_address'] ?? ''),
            'notes' => (string)($r['notes'] ?? ''),
            'currency' => (string)($r['currency'] ?? 'ARS'),
            'total_cents' => (int)($r['total_cents'] ?? 0),
            'status' => (string)($r['status'] ?? 'new'),
            'created_at' => (string)($r['created_at'] ?? ''),
        ];
    }
    return $out;
}

/** @return array{order:array,items:array<int,array>} */
function orders_get(PDO $pdo, int $createdBy, int $orderId): array
{
    if (!orders_supports_tables($pdo)) {
        throw new RuntimeException('No se encontraron las tablas de pedidos.');
    }

    $stmt = $pdo->prepare('SELECT * FROM customer_orders WHERE id = :id AND created_by = :created_by LIMIT 1');
    $stmt->execute(['id' => (int)$orderId, 'created_by' => (int)$createdBy]);
    $order = $stmt->fetch();
    if (!$order) {
        throw new RuntimeException('Pedido no encontrado.');
    }

    if (orders_supports_item_customer_fields($pdo)) {
        $itemStmt = $pdo->prepare(
            'SELECT it.product_id, it.description, it.customer_email, it.customer_dni, it.quantity, it.unit_price_cents, it.line_total_cents,
                    COALESCE(cp.unit, "") AS unit
             FROM customer_order_items it
             LEFT JOIN catalog_products cp ON cp.id = it.product_id AND cp.created_by = :created_by
             WHERE it.order_id = :order_id
             ORDER BY it.id ASC'
        );
    } else {
        $itemStmt = $pdo->prepare(
            'SELECT it.product_id, it.description, it.quantity, it.unit_price_cents, it.line_total_cents,
                    COALESCE(cp.unit, "") AS unit
             FROM customer_order_items it
             LEFT JOIN catalog_products cp ON cp.id = it.product_id AND cp.created_by = :created_by
             WHERE it.order_id = :order_id
             ORDER BY it.id ASC'
        );
    }
    $itemStmt->execute(['order_id' => (int)$orderId, 'created_by' => (int)$createdBy]);
    $items = $itemStmt->fetchAll();

    return ['order' => $order, 'items' => $items];
}

function orders_update_status(PDO $pdo, int $createdBy, int $orderId, string $status): int
{
    if (!orders_supports_tables($pdo)) {
        throw new RuntimeException('No se encontraron las tablas de pedidos.');
    }

    $allowed = ['new', 'confirmed', 'cancelled', 'fulfilled'];
    $status = trim($status);
    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('Estado inválido.');
    }

    $createdBy = (int)$createdBy;
    $orderId = (int)$orderId;

    // Cargar pedido y items (también valida existencia).
    $g = orders_get($pdo, $createdBy, $orderId);
    $order = is_array($g['order'] ?? null) ? $g['order'] : [];
    $items = is_array($g['items'] ?? null) ? $g['items'] : [];
    $currentStatus = (string)($order['status'] ?? 'new');

    $invoiceId = 0;

    // Si pasa a "Entregado", registramos la venta en invoices + invoice_items.
    // Si ya estaba entregado, sincronizamos datos faltantes hacia la factura existente.
    if ($status === 'fulfilled') {
        $invoiceId = orders_find_invoice_for_order($pdo, $createdBy, $orderId);

        if ($invoiceId <= 0) {
            $customerName = (string)($order['customer_name'] ?? '');
            $customerPhone = (string)($order['customer_phone'] ?? '');
            $customerEmail = (string)($order['customer_email'] ?? '');
            $customerDni = (string)($order['customer_dni'] ?? '');
            $customerAddress = (string)($order['customer_address'] ?? '');
            $notes = trim((string)($order['notes'] ?? ''));
            $currency = (string)($order['currency'] ?? 'ARS');

            // Fallback: si la tabla customer_orders todavía no tiene email/DNI,
            // intentamos tomarlos de customer_order_items (si existen columnas).
            if ((trim($customerEmail) === '' || trim($customerDni) === '') && count($items) > 0) {
                $first = is_array($items[0] ?? null) ? $items[0] : [];
                if (trim($customerEmail) === '' && isset($first['customer_email'])) {
                    $customerEmail = (string)($first['customer_email'] ?? '');
                }
                if (trim($customerDni) === '' && isset($first['customer_dni'])) {
                    $customerDni = (string)($first['customer_dni'] ?? '');
                }
            }

            $detailLines = [];
            $detailLines[] = 'Pedido #' . $orderId . ' (retiro)';
            if ($notes !== '') {
                $detailLines[] = 'Notas: ' . $notes;
            }
            $detailLines[] = orders_invoice_marker($orderId);
            $detail = implode("\n", $detailLines);

            $unitByProductId = [];
            $productIds = [];
            foreach ($items as $it) {
                $pid = (int)($it['product_id'] ?? 0);
                if ($pid > 0) {
                    $productIds[] = $pid;
                }
            }

            $productIds = array_values(array_unique($productIds));
            if (count($productIds) > 0) {
                try {
                    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
                    $stmtUnits = $pdo->prepare('SELECT id, COALESCE(unit, "") AS unit FROM catalog_products WHERE created_by = ? AND id IN (' . $placeholders . ')');
                    $stmtUnits->execute(array_merge([$createdBy], $productIds));
                    foreach ($stmtUnits->fetchAll() as $r) {
                        $pid = (int)($r['id'] ?? 0);
                        $u = trim((string)($r['unit'] ?? ''));
                        if ($pid > 0 && $u !== '') {
                            $unitByProductId[$pid] = $u;
                        }
                    }
                } catch (Throwable $e) {
                    // Si falla (tabla inexistente o query), seguimos con 'u'.
                }
            }

            $invItems = [];
            foreach ($items as $it) {
                $desc = trim((string)($it['description'] ?? ''));
                $qty = (string)($it['quantity'] ?? '1');
                $lineTotalCents = (int)($it['line_total_cents'] ?? 0);
                $unitPriceCents = (int)($it['unit_price_cents'] ?? 0);
                $pid = (int)($it['product_id'] ?? 0);
                if ($desc === '') {
                    continue;
                }
                if ($lineTotalCents <= 0) {
                    throw new InvalidArgumentException('No se puede generar venta: hay items con precio 0.');
                }

                // invoices_create interpreta unit_price como PRECIO BASE (u/kg/l) en moneda.
                $priceBase = number_format($unitPriceCents / 100, 2, '.', '');
                $unit = 'u';
                if ($pid > 0 && isset($unitByProductId[$pid]) && trim((string)$unitByProductId[$pid]) !== '') {
                    $unit = invoice_normalize_unit((string)$unitByProductId[$pid]);
                }

                $invItems[] = [
                    'description' => $desc,
                    'quantity' => $qty,
                    'unit' => $unit,
                    'unit_price' => $priceBase,
                ];
            }

            if (count($invItems) === 0) {
                throw new InvalidArgumentException('No se puede generar venta: el pedido no tiene items válidos.');
            }

            // Esto crea la factura y, si hay stock_items compatibles, descuenta stock automáticamente.
            $invoiceId = invoices_create(
                $pdo,
                $createdBy,
                $customerName,
                $customerEmail,
                $detail,
                $invItems,
                $currency,
                $customerDni,
                $customerPhone,
                $customerAddress
            );
        }

        // Con la factura creada o encontrada, intentamos completar datos faltantes.
        if ($invoiceId > 0) {
            $customerName = (string)($order['customer_name'] ?? '');
            $customerPhone = (string)($order['customer_phone'] ?? '');
            $customerEmail = (string)($order['customer_email'] ?? '');
            $customerDni = (string)($order['customer_dni'] ?? '');
            $customerAddress = (string)($order['customer_address'] ?? '');

            if ((trim($customerEmail) === '' || trim($customerDni) === '') && count($items) > 0) {
                $first = is_array($items[0] ?? null) ? $items[0] : [];
                if (trim($customerEmail) === '' && isset($first['customer_email'])) {
                    $customerEmail = (string)($first['customer_email'] ?? '');
                }
                if (trim($customerDni) === '' && isset($first['customer_dni'])) {
                    $customerDni = (string)($first['customer_dni'] ?? '');
                }
            }

            orders_sync_invoice_customer_fields($pdo, $createdBy, $invoiceId, $customerName, $customerEmail, $customerDni, $customerPhone, $customerAddress);
        }
    }

    $stmt = $pdo->prepare('UPDATE customer_orders SET status = :status WHERE id = :id AND created_by = :created_by');
    $stmt->execute(['status' => $status, 'id' => $orderId, 'created_by' => $createdBy]);

    return $invoiceId;
}
