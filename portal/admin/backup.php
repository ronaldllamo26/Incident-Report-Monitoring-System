<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../../config/db.php';

$error = '';
if (isset($_POST['action']) && $_POST['action'] === 'download') {
    $password = $_POST['admin_password'] ?? '';
    $user = currentUser();

    // Verify Password First
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $dbPass = $stmt->fetchColumn();

    if (!password_verify($password, $dbPass)) {
        $error = "Mali ang iyong password. Hindi awtorisado ang pag-backup.";
    } else {
        // Proceed with backup
        $tables = [];
        $result = $pdo->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $return = "-- IRMS Database Backup\n";
        $return .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($tables as $table) {
            $result = $pdo->query("SELECT * FROM $table");
            $num_fields = $result->columnCount();

            $return .= "DROP TABLE IF EXISTS $table;\n";
            $row2 = $pdo->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM);
            $return .= $row2[1] . ";\n\n";

            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $return .= "INSERT INTO $table VALUES(";
                for ($j = 0; $j < $num_fields; $j++) {
                    if (isset($row[$j])) {
                        $return .= '"' . addslashes($row[$j]) . '"';
                    } else {
                        $return .= 'NULL';
                    }
                    if ($j < ($num_fields - 1)) {
                        $return .= ',';
                    }
                }
                $return .= ");\n";
            }
            $return .= "\n\n";
        }

        // Download file
        $filename = 'irms_backup_' . date('Ymd_His') . '.sql';
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename=' . $filename);
        echo $return;
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database Backup — QC-ALERTO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <?php include __DIR__ . '/../../includes/sidebar_style.php'; ?>
</head>
<body class="bg-light">
<div class="d-flex">
    <?php include __DIR__ . '/../../includes/sidebar_admin.php'; ?>
    <div class="main-content">
        <div class="top-nav">
            <h6 class="fw-semibold mb-0">System Security & Backups</h6>
            <?php include __DIR__ . '/../../includes/notification_bell.php'; ?>
        </div>

        <div class="p-5 text-center">
            <div class="card border-0 shadow-sm mx-auto" style="max-width: 600px; border-radius: 24px; padding: 40px;">
                <div class="mb-4">
                    <i class="bi bi-shield-lock-fill text-primary" style="font-size: 80px;"></i>
                </div>
                <h3 class="fw-bold">Database Safeguard</h3>
                <p class="text-muted">I-secure ang iyong data. Kailangan ng password verification para makapag-download ng full SQL backup.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 small mt-3">
                        <i class="bi bi-x-circle-fill me-2"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form action="backup.php" method="POST" class="mt-4">
                    <input type="hidden" name="action" value="download">
                    <div class="mb-3">
                        <input type="password" name="admin_password" class="form-control text-center py-3" 
                               placeholder="Enter Admin Password" required 
                               style="border-radius: 12px; background: #f8f9fa; border: 1px solid #e2e8f0;">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold" style="border-radius: 16px;">
                        <i class="bi bi-cloud-download-fill me-2"></i> Authorize & Download Backup
                    </button>
                </form>
                
                <div class="mt-4 text-muted small">
                    Last check: <?= date('F j, Y — h:i A') ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
