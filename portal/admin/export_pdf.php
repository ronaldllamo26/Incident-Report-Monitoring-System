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
    @page { margin: 20mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #1e293b;
           line-height: 1.6; background: #fff; margin: 0; padding: 0; }
    .wrapper { padding: 10mm; border: 1px solid #f1f5f9; }

    /* Header */
    .page-header { border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 20px; }
    .header-top { display: block; width: 100%; overflow: hidden; }
    .header-left { float: left; width: 70%; }
    .header-right { float: right; width: 30%; text-align: right; }
    .clear { clear: both; }
    
    .gov-text { font-size: 8pt; text-transform: uppercase; color: #64748b; font-weight: bold; margin-bottom: 2px; }
    .org-name { font-size: 15pt; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; }
    .org-sub { font-size: 8.5pt; color: #475569; margin-top: 1px; font-weight: 500; }
    
    .doc-type { font-size: 11pt; font-weight: 900; color: #CE1126;
                text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 4px; }
    .gen-date { font-size: 7.5pt; color: #94a3b8; }

    /* Reference bar */
    .ref-bar { background: #0f172a; color: #fff; padding: 10px 14px;
               border-radius: 4px; margin-bottom: 20px; font-size: 9pt;
               overflow: hidden; }
    .ref-bar .left { float: left; }
    .ref-bar .right { float: right; }

    /* Status + Severity badges */
    .badge { display: inline-block; padding: 2px 8px; border-radius: 3px;
             font-size: 8pt; font-weight: bold; text-transform: uppercase; }

    /* Section heading */
    .section-head { font-size: 8.5pt; font-weight: 800; color: #334155;
                    text-transform: uppercase; letter-spacing: 0.8px;
                    background: #f1f5f9; padding: 6px 10px;
                    border-left: 4px solid #0f172a;
                    margin: 22px 0 12px; }

    /* Info table */
    .info-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    .info-table td { padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 9pt; vertical-align: top; }
    .info-table tr:last-child td { border-bottom: none; }
    .info-table .lbl { width: 150px; color: #64748b; font-weight: 700;
                       font-size: 8pt; text-transform: uppercase; padding-right: 12px; }

    /* Description box */
    .desc-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 4px;
                padding: 12px 14px; font-size: 9pt; line-height: 1.5; color: #334155; margin-bottom: 12px; }

    /* AI Formal Report Section */
    .ai-report-box { 
        background: #f8fafc; 
        border: 1px dashed #cbd5e1; 
        border-radius: 6px;
        padding: 15px; 
        font-family: 'Courier New', Courier, monospace; 
        font-size: 8.5pt; 
        color: #1e3a8a; 
        line-height: 1.6;
        margin-bottom: 20px;
        white-space: pre-wrap;
    }

    /* Timeline */
    .tl-item { padding: 8px 0 8px 18px; border-left: 1.5px solid #e2e8f0;
               position: relative; margin-bottom: 4px; }
    .tl-dot { position: absolute; left: -5.5px; top: 12px; width: 9px; height: 9px;
              border-radius: 50%; border: 2px solid #fff; }
    .tl-status { font-weight: bold; font-size: 9.5pt; color: #1e293b; }
    .tl-meta { font-size: 8pt; color: #94a3b8; margin-top: 2px; }
    .tl-remark { font-size: 8.5pt; color: #475569; font-style: italic; margin-top: 4px; padding-left: 4px; }

    /* Evidence Gallery */
    .evidence-grid { overflow: hidden; margin-top: 10px; }
    .evidence-item { float: left; width: 31%; margin-right: 2%; margin-bottom: 10px; text-align: center; }
    .evidence-img { width: 100%; height: 120px; object-fit: cover; border: 1px solid #e2e8f0; border-radius: 4px; }
    .evidence-cap { font-size: 7pt; color: #94a3b8; margin-top: 4px; }

    /* Footer */
    .page-footer { margin-top: 30px; border-top: 1px solid #e2e8f0;
                   padding-top: 10px; font-size: 7.5pt; color: #94a3b8;
                   text-align: center; }
    .confidential { background: #fef2f2; border: 1px solid #fecaca;
                    text-align: center; padding: 8px; font-size: 8pt;
                    color: #991b1b; border-radius: 4px; margin-top: 20px; font-weight: bold; 
                    text-transform: uppercase; letter-spacing: 0.5px; }
    
    .signature-area { margin-top: 40px; overflow: hidden; }
    .sig-box { float: left; width: 45%; border-top: 1px solid #000; padding-top: 5px; text-align: center; margin-top: 40px; }
    .sig-label { font-size: 8pt; font-weight: bold; text-transform: uppercase; }
    .sig-sub { font-size: 7pt; color: #64748b; }
</style>
</head>
<body>
<div class="wrapper">

<!-- Header -->
<div class="page-header">
    <div class="header-top">
        <div class="header-left">
            <div class="gov-text">Republic of the Philippines &bull; Quezon City</div>
            <div class="org-name">QC-ALERTO</div>
            <div class="org-sub">Incident Report &amp; Monitoring System (IRMS)</div>
        </div>
        <div class="header-right">
            <div class="doc-type">Case Spot Report</div>
            <div class="gen-date">Case #<?= str_pad($id,6,'0',STR_PAD_LEFT) ?></div>
            <div class="gen-date">Ref: <?= htmlspecialchars($trackingNum) ?></div>
        </div>
        <div class="clear"></div>
    </div>
</div>

<!-- Reference Bar -->
<div class="ref-bar">
    <div class="left">
        <strong>SECURITY CLASSIFICATION:</strong> OFFICIAL RECORD
    </div>
    <div class="right">
        <span class="badge" style="background:<?= $stBg[$curSt]??'#f3f4f6' ?>;color:<?= $stColor[$curSt]??'#374151' ?>;">
            <?= $stLabel[$curSt] ?? ucfirst($curSt) ?>
        </span>
        &nbsp;
        <span class="badge" style="background:<?= $sevBg[$curSev]??'#f3f4f6' ?>;color:<?= $sevColor[$curSev]??'#374151' ?>;">
            <?= $sevLabel[$curSev] ?? ucfirst($curSev) ?> PRIORITY
        </span>
    </div>
    <div class="clear"></div>
</div>

<!-- Incident Info -->
<div class="section-head">I. BASIC INCIDENT INFORMATION</div>
<table class="info-table">
    <tr>
        <td class="lbl">Incident Title</td>
        <td><strong><?= htmlspecialchars($inc['title']) ?></strong></td>
    </tr>
    <tr>
        <td class="lbl">Category / Type</td>
        <td><?= htmlspecialchars($inc['category_name']) ?></td>
    </tr>
    <tr>
        <td class="lbl">Incident Location</td>
        <td><?= htmlspecialchars($inc['location']) ?></td>
    </tr>
    <tr>
        <td class="lbl">Date/Time Reported</td>
        <td><?= date('F j, Y — h:i A', strtotime($inc['reported_at'])) ?></td>
    </tr>
    <?php if ($inc['resolved_at']): ?>
    <tr>
        <td class="lbl">Date/Time Resolved</td>
        <td><?= date('F j, Y — h:i A', strtotime($inc['resolved_at'])) ?></td>
    </tr>
    <?php endif; ?>
    <tr>
        <td class="lbl">Reported By</td>
        <td><?= htmlspecialchars($inc['reporter_name'] ?: 'Anonymous') ?> (<?= $inc['is_anonymous'] ? 'Anonymous' : 'Registered Citizen' ?>)</td>
    </tr>
    <tr>
        <td class="lbl">Assigned Responder</td>
        <td><?= htmlspecialchars($inc['responder_name'] ?: 'Pending Assignment') ?></td>
    </tr>
</table>

<div class="section-head">II. NARRATIVE DESCRIPTION</div>
<div class="desc-box"><?= nl2br(htmlspecialchars($inc['description'])) ?></div>

<?php if (!empty($inc['ai_formal_report'])): ?>
<div class="section-head">III. OFFICIAL DISCLOSURE (AI GENERATED)</div>
<div class="ai-report-box"><?= htmlspecialchars($inc['ai_formal_report']) ?></div>
<?php endif; ?>

<!-- Evidence Gallery (Optional - only if images exist) -->
<?php 
$images = array_filter($attachments, function($a) {
    return str_contains($a['file_type'] ?? '', 'image');
});
if (!empty($images)):
?>
<div class="section-head">IV. DOCUMENTARY EVIDENCE / EXHIBITS</div>
<div class="evidence-grid">
    <?php 
    $count = 0;
    foreach ($images as $img): 
        if ($count >= 6) break; // Limit to 6 for PDF space
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/irms/' . $img['file_path'];
    ?>
    <div class="evidence-item">
        <img src="<?= $fullPath ?>" class="evidence-img">
        <div class="evidence-cap"><?= ucfirst($img['stage'] ?? 'Initial') ?> Attachment</div>
    </div>
    <?php $count++; endforeach; ?>
    <div class="clear"></div>
</div>
<?php endif; ?>

<div class="section-head">V. CASE LIFELINE / ACTION TAKEN</div>
<?php if (empty($logs)): ?>
    <p style="font-size:8.5pt;color:#94a3b8;padding-left:10px;">No historical actions recorded.</p>
<?php else: ?>
    <?php foreach (array_slice(array_reverse($logs), 0, 8) as $log): // Show last 8 actions
        $dotColor = $tlColor[$log['new_status']] ?? '#64748b';
        $statusText = $stLabel[$log['new_status']] ?? ucwords(str_replace('_',' ',$log['new_status']));
    ?>
    <div class="tl-item">
        <div class="tl-dot" style="background:<?= $dotColor ?>;"></div>
        <div class="tl-status"><?= $statusText ?></div>
        <div class="tl-meta">
            <?= date('M d, Y · h:i A', strtotime($log['changed_at'])) ?> 
            &bull; Action by: <?= htmlspecialchars($log['changed_by_name'] ?? 'System/AI') ?>
        </div>
        <?php if ($log['remarks']): ?>
            <div class="tl-remark">"<?= htmlspecialchars($log['remarks']) ?>"</div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="signature-area">
    <div class="sig-box" style="float:left;">
        <div class="sig-label"><?= htmlspecialchars(currentUser()['name']) ?></div>
        <div class="sig-sub">Authorized Administrator</div>
        <div class="sig-sub">QC-ALERTO Digital Signature</div>
    </div>
    <div class="sig-box" style="float:right;">
        <div class="sig-label" style="color:#e2e8f0;">__________________________</div>
        <div class="sig-sub">Receiving Officer / Supervisor</div>
        <div class="sig-sub">Date &amp; Time Signed</div>
    </div>
    <div class="clear"></div>
</div>

<div class="confidential">
    Official Record — Prepared using QC-ALERTO IRMS AI Engine.
    Data integrity verified via system logs.
</div>

<div class="page-footer">
    Quezon City Incident Report &amp; Monitoring System &bull; Case #<?= str_pad($id,6,'0',STR_PAD_LEFT) ?>
    &bull; Page 1 of 1
</div>

</div> <!-- end wrapper -->
</body>
</html>
<?php
$html = ob_get_clean();

// ── Render PDF ─────────────────────────────────────────────
$options = new Options();
$options->set('isRemoteEnabled', true); // Enabled for local image paths if using full paths
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'QC-ALERTO_SpotReport_' . str_pad($id,6,'0',STR_PAD_LEFT) . '_' . date('Ymd') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
