<?php
// Endpoint para KPIs del dashboard (sin recargar).
require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Evitar que warnings/notices se impriman como HTML y rompan el JSON.
@ini_set('display_errors', '0');

if (auth_user_id() === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = app_config();
$userId = (int)auth_user_id();

$tzAr = new DateTimeZone('America/Argentina/Buenos_Aires');
$todayPeriod = sales_period('day', $tzAr);

$kpiIncomeText = '—';
$kpiSalesCount = 0;
$kpiTopProducts = [];

try {
    $pdo = db($config);

    $summary = sales_summary($pdo, $userId, $todayPeriod['start'], $todayPeriod['end']);
    $kpiSalesCount = array_sum(array_map(static fn(array $r): int => (int)($r['count'] ?? 0), $summary));

    $incomeCents = 0;
    $incomeCurrency = 'ARS';
    if (count($summary) > 0) {
        foreach ($summary as $row) {
            $cur = strtoupper((string)($row['currency'] ?? 'ARS'));
            if ($cur === 'ARS') {
                $incomeCents = (int)($row['total_cents'] ?? 0);
                $incomeCurrency = 'ARS';
                break;
            }
        }

        if ($incomeCents === 0) {
            $first = $summary[0];
            $incomeCents = (int)($first['total_cents'] ?? 0);
            $incomeCurrency = strtoupper((string)($first['currency'] ?? 'ARS'));
        }
    }

    if ($kpiSalesCount > 0 || $incomeCents > 0) {
        $kpiIncomeText = money_format_cents($incomeCents, $incomeCurrency);
    }

    $stmtTop = $pdo->prepare(
        'SELECT ii.description AS description,
                COALESCE(SUM(ii.quantity), 0) AS qty
         FROM invoice_items ii
         INNER JOIN invoices i ON i.id = ii.invoice_id
         WHERE i.created_by = :user_id
           AND i.created_at >= :start
           AND i.created_at < :end
         GROUP BY ii.description
         ORDER BY qty DESC
         LIMIT 3'
    );

    $stmtTop->execute([
        'user_id' => $userId,
        'start' => $todayPeriod['start']->format('Y-m-d H:i:s'),
        'end' => $todayPeriod['end']->format('Y-m-d H:i:s'),
    ]);

    foreach ($stmtTop->fetchAll() as $r) {
        $qtyText = rtrim(rtrim(number_format((float)($r['qty'] ?? 0), 2, '.', ''), '0'), '.');
        $kpiTopProducts[] = [
            'description' => (string)($r['description'] ?? ''),
            'qty_text' => $qtyText,
        ];
    }
} catch (Throwable $e) {
    error_log('[dashboard_kpi_api_error] ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudieron obtener los KPIs'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'income_text' => $kpiIncomeText,
    'income_date' => $todayPeriod['start']->format('d/m/Y'),
    'sales_count' => $kpiSalesCount,
    'top_products' => $kpiTopProducts,
], JSON_UNESCAPED_UNICODE);
