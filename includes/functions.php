<?php

// ── CSRF PROTECTION ──────────────────────────────────────

/**
 * Get or generate a CSRF token for the current session.
 */
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden CSRF form field.
 * Usage: <?= csrf_field() ?> inside any <form>
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Validate the CSRF token from a POST request.
 * Call at the top of every POST handler.
 * Returns true if valid, dies with 403 if not.
 */
function validate_csrf(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid or missing CSRF token.']));
    }

    return true;
}

// ── HELPERS ───────────────────────────────────────────────

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

// ── BRUTE FORCE / LOGIN RATE LIMITING ───────────────────────

/** Maximum failed login attempts before lockout */
define('MAX_LOGIN_ATTEMPTS', 5);

/** Lockout duration in minutes after hitting the limit */
define('LOCKOUT_MINUTES', 15);

/**
 * Check if a login attempt is currently blocked.
 * Tracks by both IP address AND email — blocks either axis of attack.
 *
 * @return array ['locked' => bool, 'seconds_left' => int]
 */
function isLoginLocked(PDO $pdo, string $ip, string $email): array {
    $identifiers = [
        'ip:'    . $ip,
        'email:' . strtolower(trim($email)),
    ];

    foreach ($identifiers as $ident) {
        $stmt = $pdo->prepare("
            SELECT failed_count, locked_until
            FROM login_attempts
            WHERE identifier = ?
        ");
        $stmt->execute([$ident]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || $row['locked_until'] === null) continue;

        $lockedUntil = strtotime($row['locked_until']);
        if (time() < $lockedUntil) {
            return [
                'locked'       => true,
                'seconds_left' => $lockedUntil - time(),
            ];
        }
    }

    return ['locked' => false, 'seconds_left' => 0];
}

/**
 * Record a failed login attempt for both IP and email.
 * Automatically locks the identifier once MAX_LOGIN_ATTEMPTS is reached.
 * Resets cleanly after a lockout window expires.
 */
function recordFailedAttempt(PDO $pdo, string $ip, string $email): void {
    $identifiers = [
        'ip:'    . $ip,
        'email:' . strtolower(trim($email)),
    ];

    foreach ($identifiers as $ident) {
        $stmt = $pdo->prepare("
            SELECT id, failed_count, locked_until
            FROM login_attempts
            WHERE identifier = ?
        ");
        $stmt->execute([$ident]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // First ever failed attempt for this identifier
            $pdo->prepare("
                INSERT INTO login_attempts (identifier, failed_count)
                VALUES (?, 1)
            ")->execute([$ident]);
        } else {
            // If previous lockout already expired — treat as fresh start
            $expired = $row['locked_until'] !== null
                && strtotime($row['locked_until']) < time();

            $newCount    = $expired ? 1 : $row['failed_count'] + 1;
            $lockedUntil = null;

            if ($newCount >= MAX_LOGIN_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s',
                    strtotime('+' . LOCKOUT_MINUTES . ' minutes'));
            }

            $pdo->prepare("
                UPDATE login_attempts
                SET failed_count  = ?,
                    locked_until  = ?,
                    last_attempt  = NOW()
                WHERE identifier  = ?
            ")->execute([$newCount, $lockedUntil, $ident]);
        }
    }
}

/**
 * Clear all failed attempts for an IP + email after a successful login.
 */
function clearLoginAttempts(PDO $pdo, string $ip, string $email): void {
    $identifiers = [
        'ip:'    . $ip,
        'email:' . strtolower(trim($email)),
    ];
    $placeholders = implode(',', array_fill(0, count($identifiers), '?'));
    $pdo->prepare("
        DELETE FROM login_attempts
        WHERE identifier IN ({$placeholders})
    ")->execute($identifiers);
}

// ── GENERAL PURPOSE RATE LIMITING ───────────────────────────

/**
 * Check if an identifier (e.g. IP address) has exceeded the allowed
 * number of hits for a given action within a rolling time window.
 *
 * @param  PDO    $pdo           Database connection
 * @param  string $identifier    Usually the requester's IP address
 * @param  string $action        Logical action name, e.g. 'anon_report'
 * @param  int    $maxHits       Max allowed hits within the window
 * @param  int    $windowSeconds Rolling window size in seconds (e.g. 3600 = 1 hour)
 * @return bool   true = rate limit exceeded, false = still allowed
 */
function isRateLimited(PDO $pdo, string $identifier, string $action, int $maxHits, int $windowSeconds): bool {
    $stmt = $pdo->prepare("
        SELECT hit_count, window_start
        FROM rate_limits
        WHERE identifier = ? AND action = ?
    ");
    $stmt->execute([$identifier, $action]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) return false; // No record yet — not limited

    // If the window has expired, this is a fresh start — not limited
    $windowExpiry = strtotime($row['window_start']) + $windowSeconds;
    if (time() > $windowExpiry) return false;

    // Window is still active — check count
    return (int) $row['hit_count'] >= $maxHits;
}

/**
 * Record a hit for a given identifier + action.
 * Increments the counter within the current window, or resets
 * the window and starts fresh if the previous one has expired.
 *
 * @param  PDO    $pdo           Database connection
 * @param  string $identifier    Usually the requester's IP address
 * @param  string $action        Logical action name, e.g. 'anon_report'
 * @param  int    $windowSeconds Rolling window size in seconds
 */
function recordRateHit(PDO $pdo, string $identifier, string $action, int $windowSeconds): void {
    $stmt = $pdo->prepare("
        SELECT hit_count, window_start
        FROM rate_limits
        WHERE identifier = ? AND action = ?
    ");
    $stmt->execute([$identifier, $action]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // First hit ever — create record
        $pdo->prepare("
            INSERT INTO rate_limits (identifier, action, hit_count, window_start)
            VALUES (?, ?, 1, NOW())
        ")->execute([$identifier, $action]);
    } else {
        $windowExpiry = strtotime($row['window_start']) + $windowSeconds;

        if (time() > $windowExpiry) {
            // Window expired — reset counter, start fresh window
            $pdo->prepare("
                UPDATE rate_limits
                SET hit_count = 1, window_start = NOW()
                WHERE identifier = ? AND action = ?
            ")->execute([$identifier, $action]);
        } else {
            // Still within window — increment
            $pdo->prepare("
                UPDATE rate_limits
                SET hit_count = hit_count + 1
                WHERE identifier = ? AND action = ?
            ")->execute([$identifier, $action]);
        }
    }
}

// ── SECURE FILE UPLOAD ───────────────────────────────────

/**
 * Validate an uploaded media file (Image or Video) with strict security checks.
 * 
 * Checks performed:
 *   1. File extension whitelist (Images + MP4/WebM)
 *   2. MIME type via finfo (reads magic bytes, NOT client-supplied)
 *   3. getimagesize() — ONLY if it is an image
 *   4. File size limit (default 50MB)
 *   5. Rejects double extensions (e.g. shell.php.mp4)
 *
 * @param string $tmpPath   The temporary file path ($_FILES['...']['tmp_name'])
 * @param string $origName  The original file name ($_FILES['...']['name'])
 * @param int    $maxSize   Max file size in bytes (default 50MB)
 * @return array ['valid' => bool, 'error' => string, 'mime' => string, 'ext' => string]
 */
function validateUploadedMedia(string $tmpPath, string $origName, int $maxSize = 52428800): array {
    $result = ['valid' => false, 'error' => '', 'mime' => '', 'ext' => ''];

    // ── 1. Extension whitelist ─────────────────────────
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts, true)) {
        $result['error'] = "Hindi allowed ang file type na '.{$ext}'. Allowed: " . implode(', ', $allowedExts);
        return $result;
    }

    // ── 2. Reject double/dangerous extensions ──────────
    // e.g. "shell.php.jpg", "backdoor.phtml.mp4"
    $dangerousExts = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'phps',
                      'cgi', 'pl', 'py', 'jsp', 'asp', 'aspx', 'exe', 'sh', 'bat', 'cmd', 'svg'];
    $nameParts = explode('.', strtolower($origName));
    array_pop($nameParts); // Remove the last (already checked) extension
    foreach ($nameParts as $part) {
        if (in_array($part, $dangerousExts, true)) {
            $result['error'] = 'Suspicious filename detected — bawal mag-upload ng ganitong file format.';
            return $result;
        }
    }

    // ── 3. File size check ─────────────────────────────
    if (!file_exists($tmpPath)) {
        $result['error'] = 'Upload failed — file not found.';
        return $result;
    }
    $fileSize = filesize($tmpPath);
    if ($fileSize > $maxSize) {
        $maxMB = round($maxSize / 1048576, 1);
        $result['error'] = "Masyadong malaki ang file. Maximum {$maxMB}MB lang per upload.";
        return $result;
    }
    if ($fileSize === 0) {
        $result['error'] = 'Empty file — walang laman ang file na in-upload.';
        return $result;
    }

    // ── 4. MIME type via finfo (magic bytes) ───────────
    $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/webm'
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($tmpPath);

    if (!in_array($detectedMime, $allowedMimes, true)) {
        $result['error'] = "Invalid file type. Detected: {$detectedMime}. Mga Photos at Valid Videos lang ang pwedeng isubmit.";
        return $result;
    }

    // ── 5. Verify integrity (Images only) ──────────────
    if (str_starts_with($detectedMime, 'image/')) {
        $imageInfo = @getimagesize($tmpPath);
        if ($imageInfo === false) {
            $result['error'] = 'Hindi ito valid na image file. Baka corrupted o hindi totoong larawan.';
            return $result;
        }
    }

    // ── 6. Cross-check: extension matches detected MIME ─
    $mimeToExt = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/gif'  => ['gif'],
        'image/webp' => ['webp'],
        'video/mp4'  => ['mp4'],
        'video/webm' => ['webm'],
    ];
    
    $validExtsForMime = $mimeToExt[$detectedMime] ?? [];
    if (!in_array($ext, $validExtsForMime, true)) {
        $result['error'] = "Security Violation: Ang file extension (.{$ext}) ay hindi tugma sa actual na laman/mime-type ({$detectedMime}).";
        return $result;
    }

    // ── ALL CHECKS PASSED ──────────────────────────────
    $result['valid'] = true;
    $result['mime']  = $detectedMime;
    $result['ext']   = $ext;
    return $result;
}

function createNotification(PDO $pdo, int $userId, string $title, string $message, ?int $incidentId = null): void {
    try {
        $pdo->prepare("
            INSERT INTO notifications (user_id, incident_id, title, message)
            VALUES (?, ?, ?, ?)
        ")->execute([$userId, $incidentId, $title, $message]);
    } catch (Exception $e) {
        // Silent fail
    }
}