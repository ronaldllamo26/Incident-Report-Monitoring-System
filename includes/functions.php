<?php

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function formatDate(string $date, string $format = 'M d, Y'): string {
    return date($format, strtotime($date));
}

function formatDateTime(string $date): string {
    return date('M d, Y g:i A', strtotime($date));
}

function statusBadge(string $status): string {
    $colors = [
        'pending'     => 'warning',
        'in_progress' => 'primary',
        'resolved'    => 'success',
        'closed'      => 'secondary',
    ];
    $color = $colors[$status] ?? 'secondary';
    $label = ucwords(str_replace('_', ' ', $status));
    return "<span class=\"badge bg-{$color}\">{$label}</span>";
}

function severityBadge(string $severity): string {
    $colors = [
        'low'      => 'success',
        'medium'   => 'warning',
        'high'     => 'danger',
        'critical' => 'dark',
    ];
    $color = $colors[$severity] ?? 'secondary';
    return "<span class=\"badge bg-{$color}\">" . ucfirst($severity) . "</span>";
}

function truncate(string $text, int $length = 50): string {
    return strlen($text) > $length
        ? substr($text, 0, $length) . '...'
        : $text;
}

function redirectWith(string $url, string $type, string $msg): void {
    header('Location: ' . $url . '?' . $type . '=' . urlencode($msg));
    exit;
}

/**
 * Log an action sa audit_logs table
 * FIXED: Proper nullable type hints para walang PHP warnings
 */
function logAudit(
    PDO     $pdo,
    ?int    $userId,
    string  $action,
    ?string $targetType = null,  // fixed: ?string
    ?int    $targetId   = null,  // fixed: ?int
    ?string $details    = null   // fixed: ?string
): void {
    try {
        // Support para sa proxied connections (e.g. ngrok, deployment)
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? null;

        // Kung may multiple IPs sa X-Forwarded-For, kuha lang ang una
        if ($ip && str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        $pdo->prepare("
            INSERT INTO audit_logs
                (user_id, action, target_type, target_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$userId, $action, $targetType, $targetId, $details, $ip]);

    } catch (Exception $e) {
        // Hindi papigilan ang system kahit mag-fail ang audit log
        // Silent fail lang — para hindi ma-interrupt ang main flow
    }
}

/**
 * Generate tracking number — format: IRMS-YYYYMMDD-XXXXX
 */
function generateTracking(): string {
    return 'IRMS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}