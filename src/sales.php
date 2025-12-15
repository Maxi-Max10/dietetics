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
    return sales_summary_filtered($pdo, $userId, $start, $end, '', invoices_supports_customer_dni($pdo));

}

/**
 * @return array<int, array{currency:string,count:int,total_cents:int}>
 */
function sales_summary_filtered(PDO $pdo, int $userId, DateTimeImmutable $start, DateTimeImmutable $end, string $search, bool $hasDni): array
{
    $search = trim($search);

    $where = 'created_by = :user_id AND created_at >= :start AND created_at < :end';
    $params = [
        'user_id' => $userId,
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
    ];

    if ($search !== '') {
        $where .= ' AND (customer_name LIKE :q OR customer_email LIKE :q' . ($hasDni ? ' OR customer_dni LIKE :q' : '') . ')';
        $params['q'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare(
        'SELECT currency, COUNT(*) AS cnt, COALESCE(SUM(total_cents), 0) AS total_cents
         FROM invoices
         WHERE ' . $where . '
         GROUP BY currency
         ORDER BY currency ASC'
    );

    $stmt->execute($params);

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
 * @return array<int, array{id:int,customer_name:string,customer_email:string,customer_dni:string,total_cents:int,currency:string,created_at:string}>
 */
function sales_list(PDO $pdo, int $userId, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    return sales_list_filtered($pdo, $userId, $start, $end, '', invoices_supports_customer_dni($pdo));
}

/**
 * @return array<int, array{id:int,customer_name:string,customer_email:string,customer_dni:string,total_cents:int,currency:string,created_at:string}>
 */
function sales_list_filtered(PDO $pdo, int $userId, DateTimeImmutable $start, DateTimeImmutable $end, string $search, bool $hasDni): array
{
    $search = trim($search);

    $select = 'id, customer_name, customer_email, total_cents, currency, created_at';
    if ($hasDni) {
        $select .= ', customer_dni';
    }

    $where = 'created_by = :user_id AND created_at >= :start AND created_at < :end';
    $params = [
        'user_id' => $userId,
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
    ];

    if ($search !== '') {
        $where .= ' AND (customer_name LIKE :q OR customer_email LIKE :q' . ($hasDni ? ' OR customer_dni LIKE :q' : '') . ')';
        $params['q'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare(
        'SELECT ' . $select . '
         FROM invoices
         WHERE ' . $where . '
         ORDER BY created_at DESC, id DESC'
    );

    $stmt->execute($params);

    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int)($r['id'] ?? 0),
            'customer_name' => (string)($r['customer_name'] ?? ''),
            'customer_email' => (string)($r['customer_email'] ?? ''),
            'customer_dni' => (string)($r['customer_dni'] ?? ''),
            'total_cents' => (int)($r['total_cents'] ?? 0),
            'currency' => (string)($r['currency'] ?? 'ARS'),
            'created_at' => (string)($r['created_at'] ?? ''),
        ];
    }

    return $out;
}

/**
 * @param array<int, array{id:int,customer_name:string,customer_email:string,customer_dni:string,total_cents:int,currency:string,created_at:string}> $rows
 */
function sales_export_csv(array $rows, string $filename = 'ventas.csv'): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');

    // BOM para Excel
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'wb');
    if (!is_resource($out)) {
        throw new RuntimeException('No se pudo generar el CSV.');
    }

    fputcsv($out, ['id', 'customer_name', 'customer_email', 'customer_dni', 'currency', 'total', 'total_cents', 'created_at']);
    foreach ($rows as $r) {
        $total = number_format(((int)$r['total_cents']) / 100, 2, '.', '');
        fputcsv($out, [
            (string)$r['id'],
            (string)$r['customer_name'],
            (string)$r['customer_email'],
            (string)$r['customer_dni'],
            (string)$r['currency'],
            $total,
            (string)$r['total_cents'],
            (string)$r['created_at'],
        ]);
    }
    fclose($out);
}

/**
 * @param array<int, array{id:int,customer_name:string,customer_email:string,customer_dni:string,total_cents:int,currency:string,created_at:string}> $rows
 */
function sales_export_xml(array $rows, array $meta, string $filename = 'ventas.xml'): void
{
    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');

    $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $period = $esc((string)($meta['period'] ?? ''));
    $start = $esc((string)($meta['start'] ?? ''));
    $end = $esc((string)($meta['end'] ?? ''));
    $q = $esc((string)($meta['q'] ?? ''));

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<sales period=\"{$period}\" start=\"{$start}\" end=\"{$end}\" query=\"{$q}\">\n";
    foreach ($rows as $r) {
        $id = (string)$r['id'];
        $name = $esc((string)$r['customer_name']);
        $email = $esc((string)$r['customer_email']);
        $dni = $esc((string)$r['customer_dni']);
        $currency = $esc((string)$r['currency']);
        $totalCents = (string)$r['total_cents'];
        $createdAt = $esc((string)$r['created_at']);
        echo "  <invoice id=\"" . $esc($id) . "\" currency=\"{$currency}\" total_cents=\"" . $esc($totalCents) . "\" created_at=\"{$createdAt}\">";
        echo "<customer name=\"{$name}\" email=\"{$email}\" dni=\"{$dni}\" />";
        echo "</invoice>\n";
    }
    echo "</sales>\n";
}

/**
 * @param array<int, array{id:int,customer_name:string,customer_email:string,customer_dni:string,total_cents:int,currency:string,created_at:string}> $rows
 */
function sales_export_xlsx(array $rows, string $filename = 'ventas.xlsx'): void
{
    if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        throw new RuntimeException('Falta instalar dependencias para XLSX (PhpSpreadsheet).');
    }

    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Ventas');

    $headers = ['id', 'customer_name', 'customer_email', 'customer_dni', 'currency', 'total', 'total_cents', 'created_at'];
    $sheet->fromArray($headers, null, 'A1');

    $rowIndex = 2;
    foreach ($rows as $r) {
        $total = ((int)$r['total_cents']) / 100;
        $sheet->fromArray([
            (int)$r['id'],
            (string)$r['customer_name'],
            (string)$r['customer_email'],
            (string)$r['customer_dni'],
            (string)$r['currency'],
            $total,
            (int)$r['total_cents'],
            (string)$r['created_at'],
        ], null, 'A' . $rowIndex);
        $rowIndex++;
    }

    // Formato numérico para la columna total (F)
    $sheet->getStyle('F2:F' . max(2, $rowIndex - 1))
        ->getNumberFormat()
        ->setFormatCode('0.00');

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');

    $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
}
