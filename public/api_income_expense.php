<?php
// Endpoint para datos de ingresos vs egresos por rango de fechas
require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json');

if (!auth_is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$config = app_config();
$userId = (int)auth_user_id();
$pdo = db($config);

$start = isset($_GET['start']) ? $_GET['start'] : '';
$end = isset($_GET['end']) ? $_GET['end'] : '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    http_response_code(400);
    echo json_encode(['error' => 'Fechas inválidas']);
    exit;
}

// Obtener ingresos
$stmtInc = $pdo->prepare('SELECT entry_date, SUM(amount_cents) AS total FROM finance_entries WHERE created_by = :user AND entry_type = "income" AND entry_date >= :start AND entry_date <= :end GROUP BY entry_date ORDER BY entry_date ASC');
$stmtInc->execute(['user' => $userId, 'start' => $start, 'end' => $end]);
$ingresos = [];
while ($row = $stmtInc->fetch()) {
    $ingresos[$row['entry_date']] = (int)$row['total'];
}

// Obtener egresos
$stmtExp = $pdo->prepare('SELECT entry_date, SUM(amount_cents) AS total FROM finance_entries WHERE created_by = :user AND entry_type = "expense" AND entry_date >= :start AND entry_date <= :end GROUP BY entry_date ORDER BY entry_date ASC');
$stmtExp->execute(['user' => $userId, 'start' => $start, 'end' => $end]);
$egresos = [];
while ($row = $stmtExp->fetch()) {
    $egresos[$row['entry_date']] = (int)$row['total'];
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
    $data['ingresos'][] = isset($ingresos[$d]) ? $ingresos[$d] / 100 : 0;
    $data['egresos'][] = isset($egresos[$d]) ? $egresos[$d] / 100 : 0;
}

echo json_encode($data);
