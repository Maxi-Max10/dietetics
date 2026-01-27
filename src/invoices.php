<?php

declare(strict_types=1);

/**
 * Normaliza la unidad ingresada a una clave estándar.
 * u | g | kg | ml | l
 */
function invoice_normalize_unit(string $unit): string
{
    $u = strtolower(trim($unit));
    return match ($u) {
        '', 'cant', 'cantidad', 'unid', 'unidad', 'u', 'und' => 'u',
        'g', 'gr', 'gramo', 'gramos' => 'g',
        'kg', 'kilo', 'kilos' => 'kg',
        'ml', 'mililitro', 'mililitros' => 'ml',
        'l', 'lt', 'litro', 'litros' => 'l',
        default => 'u',
    };
}

/**
 * @return 'count'|'mass'|'volume'
 */
function invoice_unit_group(string $unitKey): string
{
    $u = invoice_normalize_unit($unitKey);
    return match ($u) {
        'g', 'kg' => 'mass',
        'ml', 'l' => 'volume',
        default => 'count',
    };
}

function invoice_convert_quantity(float $quantity, string $fromUnitKey, string $toUnitKey): float
{
    if (!is_finite($quantity) || $quantity < 0) {
        throw new InvalidArgumentException('Cantidad inválida.');
    }

    $from = invoice_normalize_unit($fromUnitKey);
    $to = invoice_normalize_unit($toUnitKey);

    if ($from === $to) {
        return $quantity;
    }

    $fromGroup = invoice_unit_group($from);
    $toGroup = invoice_unit_group($to);
    if ($fromGroup !== $toGroup) {
        throw new InvalidArgumentException('Unidades incompatibles (' . $from . ' → ' . $to . ').');
    }

    if ($fromGroup === 'mass') {
        $grams = ($from === 'kg') ? ($quantity * 1000.0) : $quantity;
        return ($to === 'kg') ? ($grams / 1000.0) : $grams;
    }

    if ($fromGroup === 'volume') {
        $ml = ($from === 'l') ? ($quantity * 1000.0) : $quantity;
        return ($to === 'l') ? ($ml / 1000.0) : $ml;
    }

    // count: no conversion possible/needed
    return $quantity;
}

/**
 * Calcula precio unitario mostrado (por la unidad elegida) y subtotal en centavos
 * a partir de un precio base por unidad/kg/l.
 *
 * Reglas:
 * - u, kg, l => subtotal = price_base * quantity
 * - g, ml    => subtotal = price_base * (quantity / 1000)
 *
 * @return array{unit_price_cents:int, line_total_cents:int}
 */
function invoice_compute_line_from_base_price(string $unit, float $quantity, float $priceBase): array
{
    $unitKey = invoice_normalize_unit($unit);

    if (!is_finite($quantity) || $quantity <= 0) {
        throw new InvalidArgumentException('Cantidad inválida.');
    }

    if (!is_finite($priceBase) || $priceBase <= 0) {
        throw new InvalidArgumentException('Precio inválido.');
    }

    // Precio base por u/kg/l. Convertir a precio por unidad seleccionada.
    $unitPrice = $priceBase;
    if ($unitKey === 'g' || $unitKey === 'ml') {
        $unitPrice = $priceBase / 1000.0;
    }

    $unitPriceCents = (int)round($unitPrice * 100);
    $lineTotalCents = (int)round($unitPrice * $quantity * 100);

    return [
        'unit_price_cents' => $unitPriceCents,
        'line_total_cents' => $lineTotalCents,
    ];
}

/**
 * @param array<int, array{description:string, quantity:string|float|int, unit?:string, unit_price:string|float|int}> $items
 */
function invoices_create(PDO $pdo, int $createdBy, string $customerName, string $customerEmail, string $detail, array $items, string $currency = 'ARS', string $customerDni = '', string $customerPhone = '', string $customerAddress = ''): int
{
    $customerName = trim($customerName);
    $customerEmail = trim($customerEmail);

    $customerPhone = trim($customerPhone);

    if ($customerName === '') {
        throw new InvalidArgumentException('Cliente inválido.');
    }

    if ($customerPhone !== '') {
        $phoneLen = function_exists('mb_strlen') ? (int)mb_strlen($customerPhone, 'UTF-8') : strlen($customerPhone);
        if ($phoneLen > 40) {
            throw new InvalidArgumentException('Teléfono demasiado largo.');
        }

        if (!preg_match('/^[0-9+\-\s().]+$/', $customerPhone)) {
            throw new InvalidArgumentException('Teléfono inválido.');
        }
    }

    // Email opcional: si viene, validarlo.
    if ($customerEmail !== '' && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Email de cliente inválido.');
    }

    if (count($items) === 0) {
        throw new InvalidArgumentException('Agregá al menos 1 producto.');
    }

    $dni = trim($customerDni);
    $dniLen = function_exists('mb_strlen') ? (int)mb_strlen($dni, 'UTF-8') : strlen($dni);
    if ($dni !== '' && $dniLen > 32) {
        throw new InvalidArgumentException('DNI demasiado largo.');
    }

    $customerAddress = trim($customerAddress);
    $addressLen = function_exists('mb_strlen') ? (int)mb_strlen($customerAddress, 'UTF-8') : strlen($customerAddress);
    if ($customerAddress !== '' && $addressLen > 255) {
        throw new InvalidArgumentException('Domicilio demasiado largo.');
    }

    $normalized = [];
    $totalCents = 0;

    foreach ($items as $item) {
        $description = trim((string)($item['description'] ?? ''));
        $qtyRaw = $item['quantity'] ?? 1;
        $unitSelectionRaw = (string)($item['unit'] ?? 'u');
        $priceBaseRaw = $item['unit_price'] ?? 0;

        if ($description === '') {
            throw new InvalidArgumentException('Cada item debe tener descripción.');
        }

        $quantity = (float)str_replace(',', '.', (string)$qtyRaw);
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Cantidad inválida.');
        }

        $unitKey = invoice_normalize_unit((string)$unitSelectionRaw);

        // Interpretamos el campo "Precio" como PRECIO BASE (por unidad/kg/l)
        $priceBase = (float)str_replace(',', '.', (string)$priceBaseRaw);
        if ($priceBase <= 0) {
            throw new InvalidArgumentException('Precio inválido.');
        }

        $calc = invoice_compute_line_from_base_price($unitKey, $quantity, $priceBase);
        $totalCents += $calc['line_total_cents'];

        $normalized[] = [
            'description' => $description,
            'quantity' => $quantity,
            'unit' => $unitKey,
            'unit_price_cents' => $calc['unit_price_cents'],
            'line_total_cents' => $calc['line_total_cents'],
        ];
    }

    $pdo->beginTransaction();
    try {
        $supportsDni = invoices_supports_customer_dni($pdo);

        $supportsPhone = invoices_supports_customer_phone($pdo);

        $supportsAddress = invoices_supports_customer_address($pdo);

        $detailDb = $detail;
        if (!$supportsPhone && $customerPhone !== '') {
            $prefix = 'Tel: ' . $customerPhone;
            $detailDb = ($detailDb === '') ? $prefix : ($prefix . "\n" . $detailDb);
        }

        if (!$supportsAddress && $customerAddress !== '') {
            $prefix = 'Domicilio: ' . $customerAddress;
            $detailDb = ($detailDb === '') ? $prefix : ($prefix . "\n" . $detailDb);
        }

        $cols = ['created_by', 'customer_name', 'customer_email'];
        $vals = [':created_by', ':customer_name', ':customer_email'];
        $params = [
            'created_by' => $createdBy,
            'customer_name' => $customerName,
            // Guardamos string vacío si el email no fue provisto (columna NOT NULL en algunos setups)
            'customer_email' => $customerEmail,
        ];

        if ($supportsPhone) {
            $cols[] = 'customer_phone';
            $vals[] = ':customer_phone';
            $params['customer_phone'] = $customerPhone === '' ? null : $customerPhone;
        }

        if ($supportsDni) {
            $cols[] = 'customer_dni';
            $vals[] = ':customer_dni';
            $params['customer_dni'] = $dni === '' ? null : $dni;
        }

        if ($supportsAddress) {
            $cols[] = 'customer_address';
            $vals[] = ':customer_address';
            $params['customer_address'] = $customerAddress === '' ? null : $customerAddress;
        }

        $cols[] = 'detail';
        $vals[] = ':detail';
        $params['detail'] = $detailDb === '' ? null : $detailDb;

        $cols[] = 'currency';
        $vals[] = ':currency';
        $params['currency'] = strtoupper($currency) ?: 'USD';

        $cols[] = 'total_cents';
        $vals[] = ':total_cents';
        $params['total_cents'] = $totalCents;

        $sql = 'INSERT INTO invoices (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $invoiceId = (int)$pdo->lastInsertId();

        $supportsItemUnit = invoices_items_supports_unit($pdo);
        if ($supportsItemUnit) {
            $itemStmt = $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, quantity, unit, unit_price_cents, line_total_cents) VALUES (:invoice_id, :description, :quantity, :unit, :unit_price_cents, :line_total_cents)');
        } else {
            $itemStmt = $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price_cents, line_total_cents) VALUES (:invoice_id, :description, :quantity, :unit_price_cents, :line_total_cents)');
        }

        foreach ($normalized as $line) {
            $desc = (string)$line['description'];
            $unitKey = (string)$line['unit'];
            if (!$supportsItemUnit && $unitKey !== 'u') {
                $desc .= ' (' . $unitKey . ')';
            }

            $params = [
                'invoice_id' => $invoiceId,
                'description' => $desc,
                'quantity' => number_format((float)$line['quantity'], 2, '.', ''),
                'unit_price_cents' => $line['unit_price_cents'],
                'line_total_cents' => $line['line_total_cents'],
            ];

            if ($supportsItemUnit) {
                $params['unit'] = $unitKey;
            }

            $itemStmt->execute($params);
        }

        // Stock (opcional): si existe un ítem con mismo nombre o SKU que la descripción,
        // descuenta automáticamente la cantidad vendida.
        // Nota: si no hay match, no hace nada. Si hay match pero no alcanza, lanza error y se revierte toda la factura.
        try {
            $hasStock = (bool)$pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_items' LIMIT 1")->fetchColumn();
        } catch (Throwable $e) {
            $hasStock = false;
        }

        if ($hasStock && function_exists('stock_adjust')) {
            $findStock = $pdo->prepare(
                'SELECT id, COALESCE(unit, \'\') AS unit
                 FROM stock_items
                 WHERE created_by = :created_by
                                     AND (LOWER(name) = LOWER(:q_name) OR sku = :q_sku)
                 ORDER BY id ASC
                 LIMIT 1'
            );

            foreach ($normalized as $line) {
                $q = trim((string)$line['description']);
                if ($q === '') {
                    continue;
                }

                $findStock->execute(['created_by' => $createdBy, 'q_name' => $q, 'q_sku' => $q]);
                $row = $findStock->fetch();
                if (!$row) {
                    continue;
                }

                $itemId = (int)($row['id'] ?? 0);
                if ($itemId <= 0) {
                    continue;
                }

                $soldUnitKey = (string)($line['unit'] ?? 'u');
                $stockUnitRaw = trim((string)($row['unit'] ?? ''));
                $stockUnitKey = invoice_normalize_unit($stockUnitRaw);

                $qtySold = (float)($line['quantity'] ?? 0);
                try {
                    $qtyInStockUnit = invoice_convert_quantity($qtySold, $soldUnitKey, $stockUnitKey);
                } catch (InvalidArgumentException $e) {
                    throw new InvalidArgumentException('No se pudo descontar stock para "' . $q . '": ' . $e->getMessage());
                }
                if ($qtyInStockUnit <= 0) {
                    continue;
                }

                stock_adjust($pdo, $createdBy, $itemId, -1 * $qtyInStockUnit);
            }
        }

        $pdo->commit();
        return $invoiceId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function invoices_supports_customer_address(PDO $pdo): bool
{
    static $cache = null;
    if (is_bool($cache)) {
        return $cache;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'invoices'
               AND COLUMN_NAME = :col
             LIMIT 1"
        );
        $stmt->execute(['col' => 'customer_address']);
        $cache = (bool)$stmt->fetchColumn();
        return $cache;
    } catch (Throwable $e) {
        $cache = false;
        return false;
    }
}

function invoices_supports_customer_dni(PDO $pdo): bool
{
    static $cache = null;
    if (is_bool($cache)) {
        return $cache;
    }

    try {
        // Nota: en MySQL, muchos comandos SHOW no funcionan bien con placeholders.
        // Usamos INFORMATION_SCHEMA para detección confiable.
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'invoices'
               AND COLUMN_NAME = :col
             LIMIT 1"
        );
        $stmt->execute(['col' => 'customer_dni']);
        $cache = (bool)$stmt->fetchColumn();
        return $cache;
    } catch (Throwable $e) {
        // Si no se puede consultar metadata, asumimos que no existe.
        $cache = false;
        return false;
    }
}

function invoices_supports_customer_phone(PDO $pdo): bool
{
    static $cache = null;
    if (is_bool($cache)) {
        return $cache;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'invoices'
               AND COLUMN_NAME = :col
             LIMIT 1"
        );
        $stmt->execute(['col' => 'customer_phone']);
        $cache = (bool)$stmt->fetchColumn();
        return $cache;
    } catch (Throwable $e) {
        $cache = false;
        return false;
    }
}

function invoices_items_supports_unit(PDO $pdo): bool
{
    static $cache = null;
    if (is_bool($cache)) {
        return $cache;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'invoice_items'
               AND COLUMN_NAME = :col
             LIMIT 1"
        );
        $stmt->execute(['col' => 'unit']);
        $cache = (bool)$stmt->fetchColumn();
        return $cache;
    } catch (Throwable $e) {
        $cache = false;
        return false;
    }
}

function invoices_get(PDO $pdo, int $invoiceId, int $createdBy): array
{
    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = :id AND created_by = :created_by LIMIT 1');
    $stmt->execute(['id' => $invoiceId, 'created_by' => $createdBy]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        throw new RuntimeException('Factura no encontrada.');
    }

    if (invoices_items_supports_unit($pdo)) {
        $itemStmt = $pdo->prepare("SELECT description, quantity, COALESCE(unit, '') AS unit, unit_price_cents, line_total_cents FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY id ASC");
    } else {
        $itemStmt = $pdo->prepare('SELECT description, quantity, unit_price_cents, line_total_cents FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY id ASC');
    }
    $itemStmt->execute(['invoice_id' => $invoiceId]);
    $items = $itemStmt->fetchAll();

    return ['invoice' => $invoice, 'items' => $items];
}

function money_format_cents(int $cents, string $currency = 'USD'): string
{
    $amount = $cents / 100;
    $symbol = match (strtoupper($currency)) {
        'ARS' => '$',
        'USD' => '$',
        'EUR' => '€',
        default => strtoupper($currency) . ' ',
    };

    return $symbol . number_format($amount, 2, ',', '.');
}
