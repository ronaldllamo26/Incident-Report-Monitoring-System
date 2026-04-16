<?php
/**
 * PDF Export — Single Incident Report
 * Access: Admin only
 * Route:  /irms/portal/admin/export_pdf.php?id=X
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Incident.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id    = (int)($_GET['id'] ?? 0);
$model = new Incident();
$inc   = $model->getById($id);

if (!$inc) {
    header('Location: /irms/portal/admin/incidents.php'); exit;
}

$attachments = $model->getAttachments($id);
$logs        = $model->getStatusLogs($id);
$responses   = $model->getResponses($id);

// ── Helpers ────────────────────────────────────────────────
$stLabel = ['pending'=>'Pending','in_progress'=>'In Progress','resolved'=>'Resolved','closed'=>'Closed'];
$sevLabel = ['low'=>'Low','medium'=>'Medium','high'=>'High','critical'=>'Critical'];
$sevColor = ['low'=>'#166534','medium'=>'#92400e','high'=>'#9a3412','critical'=>'#CE1126'];
$sevBg    = ['low'=>'#dcfce7','medium'=>'#fef3c7','high'=>'#ffedd5','critical'=>'#fee2e2'];
$stBg     = ['pending'=>'#fef3c7','in_progress'=>'#dbeafe','resolved'=>'#dcfce7','closed'=>'#f3f4f6'];
$stColor  = ['pending'=>'#92400e','in_progress'=>'#1e40af','resolved'=>'#166534','closed'=>'#374151'];
$tlColor  = ['pending'=>'#d97706','in_progress'=>'#1e293b','resolved'=>'#16a34a','closed'=>'#6b7280'];

$curSt  = $inc['status'];
$curSev = $inc['severity'];
$genDate = date('F j, Y \a\t g:i A');
$trackingNum = $inc['tracking_number'] ?? 'N/A';

// ── Build HTML for PDF ─────────────────────────────────────
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 18mm 20mm 18mm 20mm; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #1e293b;
           line-height: 1.5; background: #fff; }

    /* Header */
    .page-header { border-bottom: 3px solid #1e293b; padding-bottom: 16px; margin-bottom: 24px; }
    .header-top { display: block; width: 100%; overflow: hidden; margin-bottom: 12px; }
    .header-left { float: left; }
    .header-right { float: right; text-align: right; }
    .clear { clear: both; }
    .org-name { font-size: 16pt; font-weight: bold; color: #1e293b; }
    .org-sub { font-size: 9pt; color: #64748b; margin-top: 2px; }
    .doc-type { font-size: 10pt; font-weight: bold; color: #CE1126;
                text-transform: uppercase; letter-spacing: 1px; }
    .gen-date { font-size: 8pt; color: #64748b; margin-top: 4px; }

    /* Reference bar */
    .ref-bar { background: #1e293b; color: #fff; padding: 12px 16px;
               border-radius: 6px; margin-bottom: 24px; font-size: 9.5pt;
               overflow: hidden; }
    .ref-bar .left { float: left; }
    .ref-bar .right { float: right; }

    /* Status + Severity badges */
    .badge { display: inline-block; padding: 3px 10px; border-radius: 4px;
             font-size: 8.5pt; font-weight: bold; }

    /* Section heading */
    .section-head { font-size: 9pt; font-weight: bold; color: #64748b;
                    text-transform: uppercase; letter-spacing: 0.6px;
                    border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;
                    margin: 28px 0 14px; }

    /* Info table */
    .info-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    .info-table td { padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 9.5pt; vertical-align: top; }
    .info-table tr:last-child td { border-bottom: none; }
    .info-table .lbl { width: 160px; color: #64748b; font-weight: bold;
                       font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.4px; padding-right: 16px; }

    /* Description box */
    .desc-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
                padding: 16px 18px; font-size: 9.5pt; line-height: 1.6; color: #374151; margin-bottom: 16px; }

    /* Timeline */
    .tl-item { padding: 12px 0 12px 20px; border-left: 2px solid #e2e8f0;
               position: relative; margin-bottom: 8px; }
    .tl-dot { position: absolute; left: -6px; top: 16px; width: 10px; height: 10px;
              border-radius: 50%; }
    .tl-status { font-weight: bold; font-size: 10pt; }
    .tl-meta { font-size: 8.5pt; color: #64748b; margin-top: 4px; }
    .tl-remark { font-size: 9pt; color: #374151; background: #f8fafc;
                 border: 1px solid #e2e8f0; border-radius: 5px;
                 padding: 8px 12px; margin-top: 8px; }

    /* Response */
    .response-item { border: 1px solid #e2e8f0; border-radius: 6px;
                     padding: 12px 16px; margin-bottom: 12px; background: #fff; }
    .response-name { font-weight: bold; font-size: 9.5pt; color: #1e293b; }
    .response-date { font-size: 8.5pt; color: #64748b; margin-top: 2px; }
    .response-msg  { font-size: 9.5pt; color: #374151; margin-top: 8px; line-height: 1.5; }

    /* Footer */
    .page-footer { margin-top: 32px; border-top: 1px solid #e2e8f0;
                   padding-top: 12px; font-size: 8pt; color: #94a3b8;
                   text-align: center; }
    .confidential { background: #fef2f2; border: 1px solid #fecaca;
                    text-align: center; padding: 10px; font-size: 8.5pt;
                    color: #991b1b; border-radius: 5px; margin-top: 24px; font-weight: bold; }
</style>
</head>
<body>

<!-- Header -->
<div class="page-header">
    <div class="header-top">
        <div class="header-left">
            <div class="org-name">QC-ALERTO</div>
            <div class="org-sub">Quezon City Incident Report &amp; Monitoring System</div>
        </div>
        <div class="header-right">
            <div class="doc-type">Incident Report</div>
            <div class="gen-date">Generated: <?= $genDate ?></div>
            <div class="gen-date">By: <?= htmlspecialchars(currentUser()['name']) ?></div>
        </div>
        <div class="clear"></div>
    </div>
</div>

<!-- Reference Bar -->
<div class="ref-bar">
    <div class="left">
        <strong>Report #<?= str_pad($id,5,'0',STR_PAD_LEFT) ?></strong>
        &nbsp;&nbsp;&bull;&nbsp;&nbsp;
        Tracking: <?= htmlspecialchars($trackingNum) ?>
    </div>
    <div class="right">
        <span class="badge" style="background:<?= $stBg[$curSt]??'#f3f4f6' ?>;color:<?= $stColor[$curSt]??'#374151' ?>;">
            <?= $stLabel[$curSt] ?? ucfirst($curSt) ?>
        </span>
        &nbsp;
        <span class="badge" style="background:<?= $sevBg[$curSev]??'#f3f4f6' ?>;color:<?= $sevColor[$curSev]??'#374151' ?>;">
            <?= $sevLabel[$curSev] ?? ucfirst($curSev) ?> Severity
        </span>
    </div>
    <div class="clear"></div>
</div>

<!-- Incident Info -->
<div class="section-head">Incident Information</div>
<table class="info-table">
    <tr>
        <td class="lbl">Title</td>
        <td><strong><?= htmlspecialchars($inc['title']) ?></strong></td>
    </tr>
    <tr>
        <td class="lbl">Category</td>
        <td><?= htmlspecialchars($inc['category_name']) ?></td>
    </tr>
    <tr>
        <td class="lbl">Location</td>
        <td><?= htmlspecialchars($inc['location']) ?></td>
    </tr>
    <?php if ($inc['latitude'] && $inc['longitude']): ?>
    <tr>
        <td class="lbl">Coordinates</td>
        <td><?= number_format($inc['latitude'],6) ?>, <?= number_format($inc['longitude'],6) ?></td>
    </tr>
    <?php endif; ?>
    <tr>
        <td class="lbl">Date Reported</td>
        <td><?= date('F j, Y g:i A', strtotime($inc['reported_at'])) ?></td>
    </tr>
    <tr>
        <td class="lbl">Current Status</td>
        <td><strong><?= $stLabel[$curSt] ?? ucfirst($curSt) ?></strong></td>
    </tr>
    <tr>
        <td class="lbl">Severity</td>
        <td><?= $sevLabel[$curSev] ?? ucfirst($curSev) ?></td>
    </tr>
    <?php if ($inc['is_anonymous']): ?>
    <tr>
        <td class="lbl">Type</td>
        <td>Anonymous Report</td>
    </tr>
    <?php endif; ?>
</table>

<!-- Description -->
<div class="section-head">Description</div>
<div class="desc-box"><?= nl2br(htmlspecialchars($inc['description'])) ?></div>

<!-- Reporter Info -->
<div class="section-head">Reporter Information</div>
<table class="info-table">
    <tr>
        <td class="lbl">Name</td>
        <td><?= htmlspecialchars($inc['reporter_name'] ?: 'Anonymous') ?></td>
    </tr>
    <?php if ($inc['reporter_email']): ?>
    <tr>
        <td class="lbl">Email</td>
        <td><?= htmlspecialchars($inc['reporter_email']) ?></td>
    </tr>
    <?php endif; ?>
    <?php if ($inc['reporter_phone']): ?>
    <tr>
        <td class="lbl">Phone</td>
        <td><?= htmlspecialchars($inc['reporter_phone']) ?></td>
    </tr>
    <?php endif; ?>
</table>

<!-- Assigned Responder -->
<div class="section-head">Assigned Responder</div>
<table class="info-table">
    <tr>
        <td class="lbl">Responder</td>
        <td><?= htmlspecialchars($inc['responder_name'] ?: 'Unassigned') ?></td>
    </tr>
</table>

<!-- Status Timeline -->
<div class="section-head">Status History</div>
<?php if (empty($logs)): ?>
    <p style="font-size:9pt;color:#94a3b8;">Walang status change log.</p>
<?php else: ?>
    <?php foreach ($logs as $log):
        $dotColor = $tlColor[$log['new_status']] ?? '#64748b';
        $statusText = $stLabel[$log['new_status']] ?? ucwords(str_replace('_',' ',$log['new_status']));
    ?>
    <div class="tl-item">
        <div class="tl-dot" style="background:<?= $dotColor ?>;border:2px solid #fff;box-shadow:0 0 0 2px <?= $dotColor ?>;"></div>
        <div class="tl-status"><?= $statusText ?></div>
        <div class="tl-meta">
            <?= date('F j, Y — g:i A', strtotime($log['changed_at'])) ?>
            <?php if (!empty($log['changed_by_name'])): ?>
                &bull; <?= htmlspecialchars($log['changed_by_name']) ?>
            <?php endif; ?>
        </div>
        <?php if ($log['remarks']): ?>
            <div class="tl-remark"><?= htmlspecialchars($log['remarks']) ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Responses -->
<?php if (!empty($responses)): ?>
<div class="section-head">Responder Updates / Messages</div>
<?php foreach ($responses as $r): ?>
    <div class="response-item">
        <div class="response-name"><?= htmlspecialchars($r['responder_name']) ?></div>
        <div class="response-date"><?= date('F j, Y — g:i A', strtotime($r['responded_at'])) ?></div>
        <div class="response-msg"><?= nl2br(htmlspecialchars($r['message'])) ?></div>
    </div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Confidentiality Notice -->
<div class="confidential">
    CONFIDENTIAL — For official use only. This document is generated by the QC-ALERTO system.
    Unauthorized distribution is prohibited.
</div>

<!-- Footer -->
<div class="page-footer">
    QC-ALERTO — Quezon City Incident Report &amp; Monitoring System
    &nbsp;&bull;&nbsp; Report #<?= str_pad($id,5,'0',STR_PAD_LEFT) ?>
    &nbsp;&bull;&nbsp; Generated <?= $genDate ?>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

// ── Render PDF ─────────────────────────────────────────────
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'QC-ALERTO_Report_' . str_pad($id,5,'0',STR_PAD_LEFT) . '_' . date('Ymd') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
