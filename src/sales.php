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
    $legacyCutoff = invoices_legacy_cutoff_string();
    $lineTotalSql = invoices_legacy_line_total_sql('inv', 'ii');

    $where = 'inv.created_by = :user_id AND inv.created_at >= :start AND inv.created_at < :end';
    $params = [
        'user_id' => $userId,
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
        'legacy_cutoff' => $legacyCutoff,
    ];

    if ($search !== '') {
        $where .= ' AND (inv.customer_name LIKE :q_name OR inv.customer_email LIKE :q_email' . ($hasDni ? ' OR inv.customer_dni LIKE :q_dni' : '') . ')';
        $like = '%' . $search . '%';
        $params['q_name'] = $like;
        $params['q_email'] = $like;
        if ($hasDni) {
            $params['q_dni'] = $like;
        }
    }

    $stmt = $pdo->prepare(
        'SELECT inv.currency, COUNT(DISTINCT inv.id) AS cnt, COALESCE(SUM(' . $lineTotalSql . '), 0) AS total_cents
         FROM invoices inv
         INNER JOIN invoice_items ii ON ii.invoice_id = inv.id
         WHERE ' . $where . '
         GROUP BY inv.currency
         ORDER BY inv.currency ASC'
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
function sales_list(PDO $pdo, int $userId, DateTimeImmutable $start, DateTimeImmutable $end, int $limit = 20): array
{
    return sales_list_filtered($pdo, $userId, $start, $end, '', invoices_supports_customer_dni($pdo), $limit);
}

/**
 * @return array<int, array{id:int,customer_name:string,customer_email:string,customer_dni:string,total_cents:int,currency:string,created_at:string,products?:string}>
 */
function sales_list_filtered(PDO $pdo, int $userId, DateTimeImmutable $start, DateTimeImmutable $end, string $search, bool $hasDni, int $limit = 20, bool $withItems = false): array
{
    $search = trim($search);
    $limit = max(1, (int)$limit);
    $legacyCutoff = invoices_legacy_cutoff_string();
    $lineTotalSql = invoices_legacy_line_total_sql('inv', 'ii');

    $select = 'inv.id, inv.customer_name, inv.customer_email, inv.currency, inv.created_at, COALESCE(SUM(' . $lineTotalSql . '), 0) AS total_cents';
    if ($hasDni) {
        $select .= ', inv.customer_dni';
    }

    $where = 'inv.created_by = :user_id AND inv.created_at >= :start AND inv.created_at < :end';
    $params = [
        'user_id' => $userId,
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
        'legacy_cutoff' => $legacyCutoff,
    ];

    if ($search !== '') {
        $where .= ' AND (inv.customer_name LIKE :q_name OR inv.customer_email LIKE :q_email' . ($hasDni ? ' OR inv.customer_dni LIKE :q_dni' : '') . ')';
        $like = '%' . $search . '%';
        $params['q_name'] = $like;
        $params['q_email'] = $like;
        if ($hasDni) {
            $params['q_dni'] = $like;
        }
    }

    // LIMIT con entero validado: evitamos placeholders por compatibilidad MySQL/PDO.
    $stmt = $pdo->prepare(
        'SELECT ' . $select . '
         FROM invoices inv
         LEFT JOIN invoice_items ii ON ii.invoice_id = inv.id
         WHERE ' . $where . '
         GROUP BY inv.id
         ORDER BY inv.created_at DESC, inv.id DESC
         LIMIT ' . $limit
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

    if ($withItems && count($out) > 0) {
        $invoiceIds = array_values(array_filter(array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $out)));
        if (count($invoiceIds) > 0) {
            $itemsMap = sales_items_map_for_invoices($pdo, $invoiceIds);
            foreach ($out as $i => $row) {
                $id = (int)($row['id'] ?? 0);
                if ($id > 0 && isset($itemsMap[$id])) {
                    $out[$i]['products'] = $itemsMap[$id];
                } else {
                    $out[$i]['products'] = '';
                }
            }
        }
    }

    return $out;
}

/**
 * @param array<int,int> $invoiceIds
 * @return array<int,string> invoice_id => items string
 */
function sales_items_map_for_invoices(PDO $pdo, array $invoiceIds): array
{
    $invoiceIds = array_values(array_unique(array_map('intval', $invoiceIds)));
    $invoiceIds = array_values(array_filter($invoiceIds, static fn(int $v): bool => $v > 0));
    if (count($invoiceIds) === 0) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
    $supportsUnit = function_exists('invoices_items_supports_unit') ? invoices_items_supports_unit($pdo) : false;

    if ($supportsUnit) {
        $sql = 'SELECT invoice_id, description, quantity, COALESCE(unit, "") AS unit FROM invoice_items WHERE invoice_id IN (' . $placeholders . ') ORDER BY invoice_id ASC, id ASC';
    } else {
        $sql = 'SELECT invoice_id, description, quantity, "" AS unit FROM invoice_items WHERE invoice_id IN (' . $placeholders . ') ORDER BY invoice_id ASC, id ASC';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($invoiceIds);
    $rows = $stmt->fetchAll();

    $formatQty = static function ($qty): string {
        $n = (float)str_replace(',', '.', (string)$qty);
        $s = number_format($n, 2, '.', '');
        return rtrim(rtrim($s, '0'), '.');
    };

    $byInvoice = [];
    foreach ($rows as $r) {
        $invoiceId = (int)($r['invoice_id'] ?? 0);
        if ($invoiceId <= 0) {
            continue;
        }
        $desc = trim((string)($r['description'] ?? ''));
        if ($desc === '') {
            continue;
        }

        $qty = $formatQty($r['quantity'] ?? '1');
        $unit = strtolower(trim((string)($r['unit'] ?? '')));
        $qtyLabel = $qty;
        if ($unit !== '' && $unit !== 'u') {
            $qtyLabel .= ' ' . $unit;
        }

        $label = $desc . ' (x' . $qtyLabel . ')';
        if (!isset($byInvoice[$invoiceId])) {
            $byInvoice[$invoiceId] = [];
        }
        $byInvoice[$invoiceId][] = $label;
    }

    $out = [];
    foreach ($byInvoice as $invoiceId => $items) {
        $out[(int)$invoiceId] = implode(', ', $items);
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
