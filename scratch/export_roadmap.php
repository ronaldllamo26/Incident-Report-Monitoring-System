<?php
/**
 * IRMS Roadmap PDF Exporter
 * Uses the project's existing Dompdf library to generate a professional roadmap.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

// Content Design
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #1e293b; background: #f8fafc; padding: 40px; }
        .header { text-align: center; border-bottom: 3px solid #F5A623; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #111827; margin: 0; font-size: 28px; }
        .header p { color: #64748b; margin: 5px 0 0; font-size: 14px; }
        
        .section { margin-bottom: 25px; background: #fff; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .section-title { font-size: 18px; font-weight: bold; margin-bottom: 15px; display: flex; align-items: center; }
        .priority-red { color: #dc2626; }
        .priority-yellow { color: #d97706; }
        .priority-blue { color: #2563eb; }
        
        .feature-item { margin-bottom: 15px; padding-left: 10px; border-left: 3px solid #dee2e6; }
        .feature-name { font-weight: bold; font-size: 15px; margin-bottom: 4px; }
        .feature-desc { font-size: 13px; color: #475569; line-height: 1.5; }
        
        .footer { text-align: center; margin-top: 40px; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 15px; }
        .icon { font-size: 18px; margin-right: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>QC-ALERTO: Next-Gen Roadmap</h1>
        <p>Incident Report & Monitoring System — Project Modernization 2026</p>
    </div>

    <div class="section">
        <div class="section-title priority-red">🔴 HIGH PRIORITY: Security Hardening</div>
        
        <div class="feature-item" style="border-left-color: #dc2626;">
            <div class="feature-name">Two-Factor Authentication (2FA)</div>
            <div class="feature-desc">Adds an essential layer of security for Admin and Responder accounts. Requires an email OTP code for every login to prevent unauthorized access.</div>
        </div>

        <div class="feature-item" style="border-left-color: #dc2626;">
            <div class="feature-name">Advanced Audit & Privacy Logs</div>
            <div class="feature-desc">Comprehensive tracking of who viewed what incident. Essential for data privacy compliance and internal accountability.</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title priority-yellow">🟡 MEDIUM PRIORITY: Operational Excellence</div>
        
        <div class="feature-item" style="border-left-color: #f5a623;">
            <div class="feature-name">Multi-Channel Dispatch Alerts</div>
            <div class="feature-desc">Automatic SMS and Email alerts sent directly to responders when a high-priority incident is reported in their assigned zone.</div>
        </div>

        <div class="feature-item" style="border-left-color: #f5a623;">
            <div class="feature-name">AI Incident Clustering</div>
            <div class="feature-desc">Uses Llama 3 capability to group multiple reports of the same incident into a single Master Ticket, reducing dashboard clutter and improving response focus.</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title priority-blue">🔵 BONUS: Visibility & Analytics</div>
        
        <div class="feature-item" style="border-left-color: #2563eb;">
            <div class="feature-name">Predictive Hotspot Calendar</div>
            <div class="feature-desc">Historical calendar view and trend analyzer to help QC-ALERTO anticipate high-incident days based on weather and historical records.</div>
        </div>
    </div>

    <div class="footer">
        Generated on ' . date('F d, Y') . ' | Developed by Antigravity AI for SynTuxz
    </div>
</body>
</html>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$output = $dompdf->output();
file_put_contents(__DIR__ . '/../IRMS_Modernization_Roadmap.pdf', $output);

echo "Success: IRMS_Modernization_Roadmap.pdf has been generated in the root directory.";
