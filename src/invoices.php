<?php

declare(strict_types=1);

/**
 * @param array<int, array{description:string, quantity:string|float|int, unit_price:string|float|int}> $items
 */
function invoices_create(PDO $pdo, int $createdBy, string $customerName, string $customerEmail, string $detail, array $items, string $currency = 'ARS'): int
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

    $normalized = [];
    $totalCents = 0;

    foreach ($items as $item) {
        $description = trim((string)($item['description'] ?? ''));
        $qtyRaw = $item['quantity'] ?? 1;
        $unitRaw = $item['unit_price'] ?? 0;

        if ($description === '') {
            throw new InvalidArgumentException('Cada item debe tener descripción.');
        }

        $quantity = (float)str_replace(',', '.', (string)$qtyRaw);
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Cantidad inválida.');
        }

        // Permite ingresar precio con decimales.
        $unitPrice = (float)str_replace(',', '.', (string)$unitRaw);
        if ($unitPrice < 0) {
            throw new InvalidArgumentException('Precio unitario inválido.');
        }

        $unitCents = (int)round($unitPrice * 100);
        $lineCents = (int)round($quantity * $unitCents);
        $totalCents += $lineCents;

        $normalized[] = [
            'description' => $description,
            'quantity' => $quantity,
            'unit_price_cents' => $unitCents,
            'line_total_cents' => $lineCents,
        ];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO invoices (created_by, customer_name, customer_email, detail, currency, total_cents) VALUES (:created_by, :customer_name, :customer_email, :detail, :currency, :total_cents)');
        $stmt->execute([
            'created_by' => $createdBy,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'detail' => $detail === '' ? null : $detail,
            'currency' => strtoupper($currency) ?: 'USD',
            'total_cents' => $totalCents,
        ]);

        $invoiceId = (int)$pdo->lastInsertId();

        $itemStmt = $pdo->prepare('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price_cents, line_total_cents) VALUES (:invoice_id, :description, :quantity, :unit_price_cents, :line_total_cents)');
        foreach ($normalized as $line) {
            $itemStmt->execute([
                'invoice_id' => $invoiceId,
                'description' => $line['description'],
                'quantity' => number_format((float)$line['quantity'], 2, '.', ''),
                'unit_price_cents' => $line['unit_price_cents'],
                'line_total_cents' => $line['line_total_cents'],
            ]);
        }

        $pdo->commit();
        return $invoiceId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
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

    $itemStmt = $pdo->prepare('SELECT description, quantity, unit_price_cents, line_total_cents FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY id ASC');
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
