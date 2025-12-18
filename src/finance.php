<?php

declare(strict_types=1);

/**
 * Finanzas (ingresos/egresos) manuales.
 *
 * entry_type: income | expense
 */

function finance_create(PDO $pdo, int $createdBy, string $type, string $description, string|int|float $amount, string $currency = 'ARS', ?string $entryDate = null): int
{
    $type = strtolower(trim($type));
    if (!in_array($type, ['income', 'expense'], true)) {
        throw new InvalidArgumentException('Tipo inválido.');
    }

    $description = trim($description);
    if ($description === '') {
        throw new InvalidArgumentException('Descripción requerida.');
    }

    $amountFloat = (float)str_replace(',', '.', (string)$amount);
    if (!is_finite($amountFloat) || $amountFloat < 0) {
        throw new InvalidArgumentException('Monto inválido.');
    }

    $amountCents = (int)round($amountFloat * 100);

    $currency = strtoupper(trim($currency));
    if ($currency === '') {
        $currency = 'ARS';
    }

    $date = $entryDate !== null ? trim($entryDate) : '';
    if ($date === '') {
        $date = (new DateTimeImmutable('now'))->format('Y-m-d');
    }

    // Validación simple de formato de fecha
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('Fecha inválida.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO finance_entries (created_by, entry_type, description, amount_cents, currency, entry_date)
         VALUES (:created_by, :entry_type, :description, :amount_cents, :currency, :entry_date)'
    );
    $stmt->execute([
        'created_by' => $createdBy,
        'entry_type' => $type,
        'description' => $description,
        'amount_cents' => $amountCents,
        'currency' => $currency,
        'entry_date' => $date,
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * @return array<int, array{id:int,entry_type:string,description:string,amount_cents:int,currency:string,entry_date:string,created_at:string}>
 */
function finance_list(PDO $pdo, int $createdBy, string $type, int $limit = 50): array
{
    $type = strtolower(trim($type));
    if (!in_array($type, ['income', 'expense'], true)) {
        throw new InvalidArgumentException('Tipo inválido.');
    }

    $limit = max(1, min(200, (int)$limit));

    $stmt = $pdo->prepare(
        'SELECT id, entry_type, description, amount_cents, currency, entry_date, created_at
         FROM finance_entries
         WHERE created_by = :created_by AND entry_type = :entry_type
         ORDER BY entry_date DESC, id DESC
         LIMIT ' . $limit
    );

    $stmt->execute([
        'created_by' => $createdBy,
        'entry_type' => $type,
    ]);

    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int)($r['id'] ?? 0),
            'entry_type' => (string)($r['entry_type'] ?? ''),
            'description' => (string)($r['description'] ?? ''),
            'amount_cents' => (int)($r['amount_cents'] ?? 0),
            'currency' => (string)($r['currency'] ?? 'ARS'),
            'entry_date' => (string)($r['entry_date'] ?? ''),
            'created_at' => (string)($r['created_at'] ?? ''),
        ];
    }

    return $out;
}

/**
 * @return array<string, int> currency => total_cents
 */
function finance_total(PDO $pdo, int $createdBy, string $type): array
{
    $type = strtolower(trim($type));
    if (!in_array($type, ['income', 'expense'], true)) {
        throw new InvalidArgumentException('Tipo inválido.');
    }

    $stmt = $pdo->prepare(
        'SELECT currency, COALESCE(SUM(amount_cents), 0) AS total_cents
         FROM finance_entries
         WHERE created_by = :created_by AND entry_type = :entry_type
         GROUP BY currency
         ORDER BY currency ASC'
    );

    $stmt->execute([
        'created_by' => $createdBy,
        'entry_type' => $type,
    ]);

    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[(string)($r['currency'] ?? 'ARS')] = (int)($r['total_cents'] ?? 0);
    }

    return $out;
}
