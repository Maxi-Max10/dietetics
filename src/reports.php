<?php

declare(strict_types=1);

/**
 * @return bool
 */
function reports_supports_customer_dni(PDO $pdo): bool
{
    return invoices_supports_customer_dni($pdo);
}

/**
 * @return array<int, array{customer_name:string,customer_email:string,customer_dni:string,invoices_count:int,total_cents:int,currency:string,last_purchase:string}>
 */
function reports_customers_list(PDO $pdo, int $userId, DateTimeImmutable $start, DateTimeImmutable $end, string $search = '', int $limit = 20): array
{
    $hasDni = reports_supports_customer_dni($pdo);
    $search = trim($search);
    $limit = max(1, (int)$limit);

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

    $selectDni = $hasDni ? 'customer_dni' : "''";

        // LIMIT con entero validado: evitamos placeholders por compatibilidad MySQL/PDO.
        $stmt = $pdo->prepare(
        'SELECT customer_name, customer_email, ' . $selectDni . ' AS customer_dni, currency,
                COUNT(*) AS invoices_count, COALESCE(SUM(total_cents), 0) AS total_cents, MAX(created_at) AS last_purchase
         FROM invoices
         WHERE ' . $where . '
         GROUP BY customer_name, customer_email, customer_dni, currency
            ORDER BY total_cents DESC, invoices_count DESC
            LIMIT ' . $limit
    );

    $stmt->execute($params);

    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'customer_name' => (string)($r['customer_name'] ?? ''),
            'customer_email' => (string)($r['customer_email'] ?? ''),
            'customer_dni' => (string)($r['customer_dni'] ?? ''),
            'currency' => (string)($r['currency'] ?? 'ARS'),
            'invoices_count' => (int)($r['invoices_count'] ?? 0),
            'total_cents' => (int)($r['total_cents'] ?? 0),
            'last_purchase' => (string)($r['last_purchase'] ?? ''),
        ];
    }

    return $out;
}

/**
 * @return array<int, array{description:string,quantity_sum:float,invoices_count:int,total_cents:int,currency:string}>
 */
function reports_products_list(PDO $pdo, int $userId, DateTimeImmutable $start, DateTimeImmutable $end, string $search = '', int $limit = 20): array
{
    $search = trim($search);
    $limit = max(1, (int)$limit);

    $where = 'inv.created_by = :user_id AND inv.created_at >= :start AND inv.created_at < :end';
    $params = [
        'user_id' => $userId,
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
    ];

    if ($search !== '') {
        $where .= ' AND (ii.description LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }

        // LIMIT con entero validado: evitamos placeholders por compatibilidad MySQL/PDO.
        $stmt = $pdo->prepare(
        'SELECT ii.description, inv.currency,
                COALESCE(SUM(ii.quantity), 0) AS quantity_sum,
                COUNT(DISTINCT inv.id) AS invoices_count,
                COALESCE(SUM(ii.line_total_cents), 0) AS total_cents
         FROM invoice_items ii
         INNER JOIN invoices inv ON inv.id = ii.invoice_id
         WHERE ' . $where . '
         GROUP BY ii.description, inv.currency
            ORDER BY total_cents DESC, quantity_sum DESC
            LIMIT ' . $limit
    );

    $stmt->execute($params);

    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'description' => (string)($r['description'] ?? ''),
            'currency' => (string)($r['currency'] ?? 'ARS'),
            'quantity_sum' => (float)($r['quantity_sum'] ?? 0),
            'invoices_count' => (int)($r['invoices_count'] ?? 0),
            'total_cents' => (int)($r['total_cents'] ?? 0),
        ];
    }

    return $out;
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function reports_export_csv(array $headers, array $rows, string $filename): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');

    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'wb');
    if (!is_resource($out)) {
        throw new RuntimeException('No se pudo generar el CSV.');
    }

    fputcsv($out, $headers);
    foreach ($rows as $r) {
        $line = [];
        foreach ($headers as $h) {
            $line[] = isset($r[$h]) ? (string)$r[$h] : '';
        }
        fputcsv($out, $line);
    }
    fclose($out);
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function reports_export_xml(string $root, string $item, array $rows, array $meta, string $filename): void
{
    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');

    $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo '<' . $root;
    foreach ($meta as $k => $v) {
        echo ' ' . $esc((string)$k) . '="' . $esc((string)$v) . '"';
    }
    echo ">\n";

    foreach ($rows as $r) {
        echo '  <' . $item;
        foreach ($r as $k => $v) {
            echo ' ' . $esc((string)$k) . '="' . $esc((string)$v) . '"';
        }
        echo " />\n";
    }

    echo '</' . $root . ">\n";
}

/**
 * @param array<int, string> $headers
 * @param array<int, array<string, mixed>> $rows
 */
function reports_export_xlsx(string $sheetName, array $headers, array $rows, string $filename): void
{
    if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        throw new RuntimeException('Falta instalar dependencias para XLSX (PhpSpreadsheet).');
    }

    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($sheetName);

    $sheet->fromArray($headers, null, 'A1');

    $rowIndex = 2;
    foreach ($rows as $r) {
        $line = [];
        foreach ($headers as $h) {
            $line[] = $r[$h] ?? '';
        }
        $sheet->fromArray($line, null, 'A' . $rowIndex);
        $rowIndex++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');

    $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
}
