<?php

declare(strict_types=1);

/**
 * @return array{key:string,label:string,start:DateTimeImmutable,end:DateTimeImmutable}
 */
function sales_period(string $period, ?DateTimeZone $tz = null): array
{
    $tz = $tz ?? new DateTimeZone(date_default_timezone_get());
    $now = new DateTimeImmutable('now', $tz);

    $key = strtolower(trim($period));
    if (!in_array($key, ['day', 'week', 'month', 'year'], true)) {
        $key = 'day';
    }

    if ($key === 'day') {
        $start = $now->setTime(0, 0, 0);
        $end = $start->modify('+1 day');
        $label = 'Hoy (' . $start->format('d/m/Y') . ')';
    } elseif ($key === 'week') {
        // Semana ISO: lunes 00:00 a lunes siguiente 00:00.
        $start = $now->setTime(0, 0, 0)->modify('monday this week');
        $end = $start->modify('+1 week');
        $label = 'Semana (ISO) del ' . $start->format('d/m/Y') . ' al ' . $end->modify('-1 day')->format('d/m/Y');
    } elseif ($key === 'month') {
        $start = $now->setDate((int)$now->format('Y'), (int)$now->format('m'), 1)->setTime(0, 0, 0);
        $end = $start->modify('+1 month');
        $label = 'Mes de ' . $start->format('m/Y');
    } else { // year
        $start = $now->setDate((int)$now->format('Y'), 1, 1)->setTime(0, 0, 0);
        $end = $start->modify('+1 year');
        $label = 'Año ' . $start->format('Y');
    }

    return ['key' => $key, 'label' => $label, 'start' => $start, 'end' => $end];
}

/**
 * @return array<int, array{currency:string,count:int,total_cents:int}>
 */
function sales_summary(PDO $pdo, int $userId, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $stmt = $pdo->prepare(
        'SELECT currency, COUNT(*) AS cnt, COALESCE(SUM(total_cents), 0) AS total_cents
         FROM invoices
         WHERE created_by = :user_id AND created_at >= :start AND created_at < :end
         GROUP BY currency
         ORDER BY currency ASC'
    );

    $stmt->execute([
        'user_id' => $userId,
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
    ]);

    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'currency' => (string)($r['currency'] ?? 'ARS'),
            'count' => (int)($r['cnt'] ?? 0),
            'total_cents' => (int)($r['total_cents'] ?? 0),
        ];
    }

    return $out;
}

/**
 * @return array<int, array{id:int,customer_name:string,total_cents:int,currency:string,created_at:string}>
 */
function sales_list(PDO $pdo, int $userId, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $stmt = $pdo->prepare(
        'SELECT id, customer_name, total_cents, currency, created_at
         FROM invoices
         WHERE created_by = :user_id AND created_at >= :start AND created_at < :end
         ORDER BY created_at DESC, id DESC'
    );

    $stmt->execute([
        'user_id' => $userId,
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
    ]);

    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int)($r['id'] ?? 0),
            'customer_name' => (string)($r['customer_name'] ?? ''),
            'total_cents' => (int)($r['total_cents'] ?? 0),
            'currency' => (string)($r['currency'] ?? 'ARS'),
            'created_at' => (string)($r['created_at'] ?? ''),
        ];
    }

    return $out;
}
