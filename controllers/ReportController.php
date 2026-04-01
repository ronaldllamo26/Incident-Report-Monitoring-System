<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../libs/fpdf/fpdf.php';

$action = $_GET['action'] ?? '';

if ($action === 'export_pdf') {

    // Filters
    $dateFrom  = $_GET['date_from']   ?? date('Y-m-01');
    $dateTo    = $_GET['date_to']     ?? date('Y-m-d');
    $statusF   = $_GET['status']      ?? '';
    $catF      = $_GET['category_id'] ?? '';
    $sevF      = $_GET['severity']    ?? '';

    $where  = ["i.reported_at BETWEEN ? AND ?"];
    $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];

    if ($statusF) { $where[] = 'i.status = ?';      $params[] = $statusF; }
    if ($catF)    { $where[] = 'i.category_id = ?'; $params[] = $catF; }
    if ($sevF)    { $where[] = 'i.severity = ?';    $params[] = $sevF; }

     $sql = "
    SELECT i.*, c.name AS category_name,
           COALESCE(u.name, i.anon_name, 'Anonymous') AS reporter_name,
           a.name AS responder_name
    FROM incidents i
    JOIN categories c ON i.category_id = c.id
    LEFT JOIN users u ON i.reporter_id = u.id
    LEFT JOIN users a ON i.assigned_to = a.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY i.reported_at DESC
";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $incidents = $stmt->fetchAll();

    // Counts
    $counts = ['pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
    foreach ($incidents as $i) {
        if (isset($counts[$i['status']])) $counts[$i['status']]++;
    }

    // ── BUILD PDF ──────────────────────────────────────
    $pdf = new FPDF('L', 'mm', 'A4'); // Landscape para masya ang columns
    $pdf->AddPage();
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 15);

    // Header
    $pdf->SetFillColor(30, 41, 59);   // #1e293b
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 12, 'Incident Report & Monitoring System', 0, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 6,
        'Report Period: ' . date('M d, Y', strtotime($dateFrom)) .
        ' to ' . date('M d, Y', strtotime($dateTo)),
        0, 1, 'C', true);
    $pdf->Ln(4);

    // Summary boxes
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(241, 245, 249);

    $boxW = 65;
    $summaries = [
        ['Total Incidents', count($incidents), [30, 41, 59]],
        ['Pending',         $counts['pending'],     [245, 158, 11]],
        ['In Progress',     $counts['in_progress'], [59, 130, 246]],
        ['Resolved',        $counts['resolved'],    [16, 185, 129]],
    ];

    foreach ($summaries as $s) {
        [$label, $value, $color] = $s;
        $pdf->SetFillColor(...$color);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->Cell($boxW, 10, $value, 0, 0, 'C', true);
    }
    $pdf->Ln(10);

    foreach ($summaries as $s) {
        [$label, $value, $color] = $s;
        $pdf->SetFillColor(...$color);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell($boxW, 6, $label, 0, 0, 'C', true);
    }
    $pdf->Ln(10);
    $pdf->Ln(2);

    // Table header
    $pdf->SetFillColor(30, 41, 59);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 8);

    $cols = [
        ['#',          10],
        ['Title',      65],
        ['Category',   35],
        ['Severity',   22],
        ['Status',     25],
        ['Reporter',   35],
        ['Responder',  35],
        ['Date',       30],
    ];

    foreach ($cols as [$label, $w]) {
        $pdf->Cell($w, 7, $label, 0, 0, 'C', true);
    }
    $pdf->Ln();

    // Table rows
    $pdf->SetFont('Arial', '', 7.5);
    $fill = false;

    foreach ($incidents as $inc) {
        // Auto page break check
        if ($pdf->GetY() > 185) {
            $pdf->AddPage();
            // Repeat header
            $pdf->SetFillColor(30, 41, 59);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Arial', 'B', 8);
            foreach ($cols as [$label, $w]) {
                $pdf->Cell($w, 7, $label, 0, 0, 'C', true);
            }
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 7.5);
        }

        $pdf->SetTextColor(0, 0, 0);
        $bgColor = $fill ? [248, 250, 252] : [255, 255, 255];
        $pdf->SetFillColor(...$bgColor);

        $rowH = 6;

        $pdf->Cell(10, $rowH, $inc['id'],                                        0, 0, 'C', true);
        $pdf->Cell(65, $rowH, mb_substr($inc['title'], 0, 38),                   0, 0, 'L', true);
        $pdf->Cell(35, $rowH, mb_substr($inc['category_name'], 0, 20),           0, 0, 'L', true);
        $pdf->Cell(22, $rowH, ucfirst($inc['severity']),                         0, 0, 'C', true);
        $pdf->Cell(25, $rowH, ucwords(str_replace('_',' ',$inc['status'])),      0, 0, 'C', true);
        $pdf->Cell(35, $rowH, mb_substr($inc['reporter_name'], 0, 20),           0, 0, 'L', true);
        $pdf->Cell(35, $rowH, mb_substr($inc['responder_name'] ?? '—', 0, 20),   0, 0, 'L', true);
        $pdf->Cell(30, $rowH, date('M d, Y', strtotime($inc['reported_at'])),    0, 0, 'C', true);
        $pdf->Ln();

        $fill = !$fill;
    }

    // Footer line
    $pdf->Ln(4);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Line(10, $pdf->GetY(), 287, $pdf->GetY());
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'I', 7);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 5,
        'Generated by IRMS on ' . date('F d, Y g:i A') .
        ' | Total: ' . count($incidents) . ' incidents',
        0, 1, 'C');

    // Output
    $filename = 'IRMS_Report_' . date('Ymd_His') . '.pdf';
    $pdf->Output('D', $filename); // D = force download
    exit;
}