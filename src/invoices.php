<?php

declare(strict_types=1);

/**
 * @param array<int, array{description:string, quantity:string|float|int, unit?:string, unit_price:string|float|int}> $items
 */
function invoices_create(PDO $pdo, int $createdBy, string $customerName, string $customerEmail, string $detail, array $items, string $currency = 'ARS', string $customerDni = ''): int
{
    if ($customerName === '' || $customerEmail === '') {
        throw new InvalidArgumentException('Cliente inválido.');
    }

    if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
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

    $normalized = [];
    $totalCents = 0;

    foreach ($items as $item) {
        $description = trim((string)($item['description'] ?? ''));
        $qtyRaw = $item['quantity'] ?? 1;
        $unitSelectionRaw = (string)($item['unit'] ?? 'u');
        $unitPriceRaw = $item['unit_price'] ?? 0;

        if ($description === '') {
            throw new InvalidArgumentException('Cada item debe tener descripción.');
        }

        $quantity = (float)str_replace(',', '.', (string)$qtyRaw);
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Cantidad inválida.');
        }

        $unitKey = strtolower(trim((string)$unitSelectionRaw));
        $unitKey = match ($unitKey) {
            '', 'cant', 'cantidad', 'unid', 'unidad', 'u', 'und' => 'u',
            'g', 'gr', 'gramo', 'gramos' => 'g',
            'kg', 'kilo', 'kilos' => 'kg',
            'ml', 'mililitro', 'mililitros' => 'ml',
            'l', 'lt', 'litro', 'litros' => 'l',
            default => 'u',
        };

        // Permite ingresar precio con decimales.
        $unitPrice = (float)str_replace(',', '.', (string)$unitPriceRaw);
        if ($unitPrice <= 0) {
            throw new InvalidArgumentException('Precio unitario inválido.');
        }

        $unitCents = (int)round($unitPrice * 100);
        $lineCents = (int)round($quantity * $unitCents);
        $totalCents += $lineCents;

        $normalized[] = [
            'description' => $description,
            'quantity' => $quantity,
            'unit' => $unitKey,
            'unit_price_cents' => $unitCents,
            'line_total_cents' => $lineCents,
        ];
    }

    $pdo->beginTransaction();
    try {
        $supportsDni = invoices_supports_customer_dni($pdo);

        if ($supportsDni) {
            $stmt = $pdo->prepare('INSERT INTO invoices (created_by, customer_name, customer_email, customer_dni, detail, currency, total_cents) VALUES (:created_by, :customer_name, :customer_email, :customer_dni, :detail, :currency, :total_cents)');
            $stmt->execute([
                'created_by' => $createdBy,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_dni' => $dni === '' ? null : $dni,
                'detail' => $detail === '' ? null : $detail,
                'currency' => strtoupper($currency) ?: 'USD',
                'total_cents' => $totalCents,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO invoices (created_by, customer_name, customer_email, detail, currency, total_cents) VALUES (:created_by, :customer_name, :customer_email, :detail, :currency, :total_cents)');
            $stmt->execute([
                'created_by' => $createdBy,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'detail' => $detail === '' ? null : $detail,
                'currency' => strtoupper($currency) ?: 'USD',
                'total_cents' => $totalCents,
            ]);
        }

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
                'SELECT id
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

                stock_adjust($pdo, $createdBy, $itemId, -1 * (float)$line['quantity']);
            }
        }

        $pdo->commit();
        return $invoiceId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
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
