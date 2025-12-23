<?php
// Endpoint para datos de ingresos vs egresos por rango de fechas
require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json');

// Evitar que warnings/notices se impriman como HTML y rompan el JSON.
@ini_set('display_errors', '0');

if (auth_user_id() === null) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$config = app_config();
$userId = (int)auth_user_id();

try {
    $pdo = db($config);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo conectar a la base de datos']);
    exit;
}

$start = isset($_GET['start']) ? $_GET['start'] : '';
$end = isset($_GET['end']) ? $_GET['end'] : '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    http_response_code(400);
    echo json_encode(['error' => 'Fechas inválidas']);
    exit;
}

// Ingresos: ventas (facturas) + ingresos manuales (finance_entries)
$salesIncome = [];   // DATE(created_at) => total_cents
$manualIncome = [];  // entry_date => total_cents
$egresos = [];       // entry_date => total_cents

try {
    // Ventas (facturas) en el rango [start, end]
    $startDt = $start . ' 00:00:00';
    $endDt = (new DateTimeImmutable($end . ' 00:00:00'))->modify('+1 day')->format('Y-m-d H:i:s');

    $stmtSales = $pdo->prepare(
        'SELECT DATE(created_at) AS d, COALESCE(SUM(total_cents), 0) AS total
         FROM invoices
         WHERE created_by = :user AND created_at >= :start_dt AND created_at < :end_dt
         GROUP BY DATE(created_at)
         ORDER BY d ASC'
    );
    $stmtSales->execute(['user' => $userId, 'start_dt' => $startDt, 'end_dt' => $endDt]);
    while ($row = $stmtSales->fetch()) {
        $salesIncome[(string)$row['d']] = (int)$row['total'];
    }

    // Ingresos manuales
    $stmtInc = $pdo->prepare(
        'SELECT entry_date, COALESCE(SUM(amount_cents), 0) AS total
         FROM finance_entries
         WHERE created_by = :user AND entry_type = "income" AND entry_date >= :start AND entry_date <= :end
         GROUP BY entry_date
         ORDER BY entry_date ASC'
    );
    $stmtInc->execute(['user' => $userId, 'start' => $start, 'end' => $end]);
    while ($row = $stmtInc->fetch()) {
        $manualIncome[(string)$row['entry_date']] = (int)$row['total'];
    }

    // Egresos manuales
    $stmtExp = $pdo->prepare(
        'SELECT entry_date, COALESCE(SUM(amount_cents), 0) AS total
         FROM finance_entries
         WHERE created_by = :user AND entry_type = "expense" AND entry_date >= :start AND entry_date <= :end
         GROUP BY entry_date
         ORDER BY entry_date ASC'
    );
    $stmtExp->execute(['user' => $userId, 'start' => $start, 'end' => $end]);
    while ($row = $stmtExp->fetch()) {
        $egresos[(string)$row['entry_date']] = (int)$row['total'];
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron obtener los datos']);
    exit;
}

// Generar lista de fechas en el rango
$period = [];
$dtStart = new DateTime($start);
$dtEnd = new DateTime($end);
while ($dtStart <= $dtEnd) {
    $dateStr = $dtStart->format('Y-m-d');
    $period[] = $dateStr;
    $dtStart->modify('+1 day');
}

// Preparar datos para la gráfica
$data = [
    'labels' => $period,
    'ingresos' => [],
    'egresos' => []
];
foreach ($period as $d) {
    $incomeCents = (int)($salesIncome[$d] ?? 0) + (int)($manualIncome[$d] ?? 0);
    $data['ingresos'][] = $incomeCents > 0 ? ($incomeCents / 100) : 0;
    $data['egresos'][] = isset($egresos[$d]) ? $egresos[$d] / 100 : 0;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
