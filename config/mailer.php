<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

// ── EMAIL CONFIGURATION ────────────────────────
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'llamo.ronald.estiler@gmail.com');   // ← palitan ng Gmail mo
define('MAIL_PASSWORD', 'rzekvltshxydaemb');    // ← yung App Password
define('MAIL_FROM',     'llamo.ronald.estiler@gmail.com');   // ← same sa username
define('MAIL_FROM_NAME','IRMS — Incident Report & Monitoring System');

/**
 * Gumawa ng configured PHPMailer instance
 */
function createMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host        = MAIL_HOST;
    $mail->SMTPAuth    = true;
    $mail->Username    = MAIL_USERNAME;
    $mail->Password    = MAIL_PASSWORD;
    $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port        = MAIL_PORT;
    $mail->CharSet     = 'UTF-8';
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    return $mail;
}

/**
 * Mag-send ng email — ginagamit sa buong system
 *
 * @param string|array $to      Single email or array of ['email' => 'name']
 * @param string       $subject Email subject
 * @param string       $body    HTML body
 * @param string       $altBody Plain text fallback
 */
function sendMail(
    string|array $to,
    string $subject,
    string $body,
    string $altBody = ''
): bool {
    try {
        $mail = createMailer();
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        // Support single email or array
        if (is_string($to)) {
            $mail->addAddress($to);
        } else {
            foreach ($to as $email => $name) {
                $mail->addAddress($email, $name);
            }
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log ang error pero hindi papigilan ang system
        error_log('Mailer Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Email Templates
 * Lahat ng email templates naka-centralize dito
 */

// ── TEMPLATE: Report Confirmation (para sa citizen) ───
function mailReportConfirmation(array $incident, string $tracking = ''): string {
    $trackingSection = $tracking
        ? "<p style='text-align:center;margin:20px 0;'>
               <strong style='font-size:24px;letter-spacing:4px;
               color:#1e293b;font-family:monospace;'>{$tracking}</strong>
           </p>
           <p style='text-align:center;font-size:12px;color:#666;'>
               I-save ang tracking number na ito para ma-monitor ang status ng iyong report.
           </p>"
        : '';

    $sevColors = [
        'low'      => '#16a34a',
        'medium'   => '#d97706',
        'high'     => '#dc2626',
        'critical' => '#1e293b',
    ];
    $sevColor = $sevColors[$incident['severity']] ?? '#666';

    return mailWrapper(
        'Report Confirmation',
        "
        <p>Mahal na <strong>" . htmlspecialchars($incident['reporter_name'] ?? 'Citizen') . "</strong>,</p>
        <p>Natanggap na namin ang iyong incident report. Magiging aksyon kami sa lalong madaling panahon.</p>

        <div style='background:#f8fafc;border-radius:8px;padding:16px;margin:20px 0;border:1px solid #e2e8f0;'>
            <table style='width:100%;font-size:14px;'>
                <tr>
                    <td style='color:#666;padding:4px 0;width:40%'>Pamagat</td>
                    <td style='font-weight:600;'>" . htmlspecialchars($incident['title']) . "</td>
                </tr>
                <tr>
                    <td style='color:#666;padding:4px 0;'>Kategorya</td>
                    <td>" . htmlspecialchars($incident['category_name'] ?? '') . "</td>
                </tr>
                <tr>
                    <td style='color:#666;padding:4px 0;'>Severity</td>
                    <td><span style='background:{$sevColor};color:#fff;padding:2px 8px;
                        border-radius:4px;font-size:12px;'>
                        " . ucfirst($incident['severity']) . "
                    </span></td>
                </tr>
                <tr>
                    <td style='color:#666;padding:4px 0;'>Lokasyon</td>
                    <td>" . htmlspecialchars($incident['location']) . "</td>
                </tr>
                " . (!empty($incident['sla_deadline']) ? "
                <tr>
                    <td style='color:#666;padding:4px 0;'>Response Deadline</td>
                    <td style='color:#dc2626;font-weight:600;'>
                        " . date('M d, Y g:i A', strtotime($incident['sla_deadline'])) . "
                    </td>
                </tr>" : "") . "
            </table>
        </div>

        {$trackingSection}

        <p style='font-size:13px;color:#666;'>
            Maaari mong i-track ang status ng iyong report gamit ang iyong tracking number sa aming website.
        </p>
        "
    );
}

// ── TEMPLATE: Status Update (para sa citizen) ─────────
function mailStatusUpdate(array $incident, string $newStatus, string $remarks = ''): string {
    $statusColors = [
        'pending'     => '#d97706',
        'in_progress' => '#2563eb',
        'resolved'    => '#16a34a',
        'closed'      => '#6b7280',
    ];
    $statusColor = $statusColors[$newStatus] ?? '#666';
    $statusLabel = ucwords(str_replace('_', ' ', $newStatus));

    $remarksSection = $remarks
        ? "<div style='background:#f0fdf4;border-left:4px solid #16a34a;
               padding:12px 16px;margin:16px 0;border-radius:0 8px 8px 0;'>
               <p style='font-size:13px;color:#166534;margin:0;'>
                   <strong>Mensahe mula sa Responder:</strong><br>
                   " . htmlspecialchars($remarks) . "
               </p>
           </div>"
        : '';

    return mailWrapper(
        'Status Update sa Iyong Report',
        "
        <p>Mahal na <strong>" . htmlspecialchars($incident['reporter_name'] ?? 'Citizen') . "</strong>,</p>
        <p>May update na sa iyong incident report:</p>

        <div style='background:#f8fafc;border-radius:8px;padding:16px;margin:20px 0;border:1px solid #e2e8f0;'>
            <p style='margin:0 0 8px;font-size:13px;color:#666;'>Report</p>
            <p style='margin:0 0 12px;font-weight:600;font-size:15px;'>
                " . htmlspecialchars($incident['title']) . "
            </p>
            <p style='margin:0 0 4px;font-size:13px;color:#666;'>Bagong Status</p>
            <span style='background:{$statusColor};color:#fff;padding:4px 12px;
                border-radius:4px;font-size:13px;font-weight:600;'>
                {$statusLabel}
            </span>
        </div>

        {$remarksSection}
        "
    );
}

// ── TEMPLATE: Responder Assignment ────────────────────
function mailResponderAssigned(array $incident, array $responder): string {
    $sevColors = [
        'low'      => '#16a34a',
        'medium'   => '#d97706',
        'high'     => '#dc2626',
        'critical' => '#1e293b',
    ];
    $sevColor = $sevColors[$incident['severity']] ?? '#666';

    return mailWrapper(
        'Bagong Assigned Incident',
        "
        <p>Mahal na <strong>" . htmlspecialchars($responder['name']) . "</strong>,</p>
        <p>May bagong incident na naka-assign sa iyo. Pakitingnan at aksyunan sa lalong madaling panahon.</p>

        <div style='background:#f8fafc;border-radius:8px;padding:16px;margin:20px 0;border:1px solid #e2e8f0;'>
            <table style='width:100%;font-size:14px;'>
                <tr>
                    <td style='color:#666;padding:4px 0;width:40%'>Incident #</td>
                    <td style='font-weight:600;'>{$incident['id']}</td>
                </tr>
                <tr>
                    <td style='color:#666;padding:4px 0;'>Pamagat</td>
                    <td style='font-weight:600;'>" . htmlspecialchars($incident['title']) . "</td>
                </tr>
                <tr>
                    <td style='color:#666;padding:4px 0;'>Kategorya</td>
                    <td>" . htmlspecialchars($incident['category_name'] ?? '') . "</td>
                </tr>
                <tr>
                    <td style='color:#666;padding:4px 0;'>Severity</td>
                    <td><span style='background:{$sevColor};color:#fff;padding:2px 8px;
                        border-radius:4px;font-size:12px;'>
                        " . ucfirst($incident['severity']) . "
                    </span></td>
                </tr>
                <tr>
                    <td style='color:#666;padding:4px 0;'>Lokasyon</td>
                    <td>" . htmlspecialchars($incident['location']) . "</td>
                </tr>
                " . (!empty($incident['sla_deadline']) ? "
                <tr>
                    <td style='color:#666;padding:4px 0;'>SLA Deadline</td>
                    <td style='color:#dc2626;font-weight:600;'>
                        " . date('M d, Y g:i A', strtotime($incident['sla_deadline'])) . "
                    </td>
                </tr>" : "") . "
            </table>
        </div>

        <div style='text-align:center;margin:24px 0;'>
            <a href='http://localhost/irms/portal/responder/view_incident.php?id={$incident['id']}'
               style='background:#1e293b;color:#fff;padding:12px 28px;border-radius:8px;
               text-decoration:none;font-weight:600;font-size:14px;'>
               Tingnan ang Incident
            </a>
        </div>
        "
    );
}

// ── TEMPLATE: Escalation Alert (para sa admin) ────────
function mailEscalationAlert(array $incident): string {
    return mailWrapper(
        '🚨 SLA Breach — Incident Escalated',
        "
        <div style='background:#fef2f2;border:1px solid #fecaca;border-radius:8px;
             padding:16px;margin-bottom:20px;'>
            <p style='color:#dc2626;font-weight:600;margin:0;font-size:15px;'>
                ⚠️ SLA Breached — Kailangan ng Immediate Action
            </p>
        </div>

        <p>Ang sumusunod na incident ay hindi pa nare-resolve at lumagpas na sa SLA deadline:</p>

        <div style='background:#f8fafc;border-radius:8px;padding:16px;margin:20px 0;border:1px solid #e2e8f0;'>
            <table style='width:100%;font-size:14px;'>
                <tr>
                    <td style='color:#666;padding:4px 0;width:40%'>Incident #</td>
                    <td style='font-weight:600;'>{$incident['id']}</td>
                </tr>
                <tr>
                    <td style='color:#666;padding:4px 0;'>Pamagat</td>
                    <td style='font-weight:600;'>" . htmlspecialchars($incident['title']) . "</td>
                </tr>
                <tr>
                    <td style='color:#666;padding:4px 0;'>Status</td>
                    <td>" . ucwords(str_replace('_',' ',$incident['status'])) . "</td>
                </tr>
                <tr>
                    <td style='color:#666;padding:4px 0;'>SLA Deadline</td>
                    <td style='color:#dc2626;font-weight:600;'>
                        " . date('M d, Y g:i A', strtotime($incident['sla_deadline'])) . "
                    </td>
                </tr>
                <tr>
                    <td style='color:#666;padding:4px 0;'>Assigned To</td>
                    <td>" . htmlspecialchars($incident['responder_name'] ?? 'Unassigned') . "</td>
                </tr>
            </table>
        </div>

        <div style='text-align:center;margin:24px 0;'>
            <a href='http://localhost/irms/portal/admin/view_incident.php?id={$incident['id']}'
               style='background:#dc2626;color:#fff;padding:12px 28px;border-radius:8px;
               text-decoration:none;font-weight:600;font-size:14px;'>
               Tingnan ang Incident
            </a>
        </div>
        "
    );
}

// ── BASE EMAIL WRAPPER ─────────────────────────────────
function mailWrapper(string $title, string $content): string {
    return "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'></head>
    <body style='margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;'>
        <div style='max-width:600px;margin:32px auto;'>

            <!-- Header -->
            <div style='background:#1e293b;padding:24px;border-radius:12px 12px 0 0;text-align:center;'>
                <h2 style='color:#fff;margin:0;font-size:18px;'>
                    🛡 IRMS
                </h2>
                <p style='color:#94a3b8;margin:4px 0 0;font-size:12px;'>
                    Incident Report & Monitoring System
                </p>
            </div>

            <!-- Body -->
            <div style='background:#fff;padding:28px 32px;border-left:1px solid #e2e8f0;
                        border-right:1px solid #e2e8f0;'>
                <h3 style='color:#1e293b;margin-top:0;'>{$title}</h3>
                {$content}
            </div>

            <!-- Footer -->
            <div style='background:#f8fafc;padding:16px;border-radius:0 0 12px 12px;
                        border:1px solid #e2e8f0;text-align:center;'>
                <p style='font-size:11px;color:#94a3b8;margin:0;'>
                    Ito ay automated email mula sa IRMS.
                    Huwag i-reply sa email na ito.
                </p>
                <p style='font-size:11px;color:#94a3b8;margin:4px 0 0;'>
                    © <?= date('Y') ?> IRMS — Incident Report & Monitoring System
                </p>
            </div>

        </div>
    </body>
    </html>
    ";
}