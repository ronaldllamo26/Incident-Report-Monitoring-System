<?php
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../../config/db.php';

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS mock_sms_outbox (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20),
    message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'delivered'
)");

// Group logs by phone number to create "conversations"
$threads = $pdo->query("
    SELECT phone_number, MAX(sent_at) as last_msg, COUNT(*) as msg_count 
    FROM mock_sms_outbox 
    GROUP BY phone_number 
    ORDER BY last_msg DESC
")->fetchAll();

$selectedPhone = $_GET['phone'] ?? ($threads[0]['phone_number'] ?? '');
$messages = [];
if ($selectedPhone) {
    $stmt = $pdo->prepare("SELECT * FROM mock_sms_outbox WHERE phone_number = ? ORDER BY sent_at ASC");
    $stmt->execute([$selectedPhone]);
    $messages = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SMS Simulation Center — IRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <?php include __DIR__ . '/../../includes/sidebar_style.php'; ?>
    <style>
        :root {
            --phone-bg: #f4f4f4;
            --bubble-sent: #007aff;
            --bubble-text: #ffffff;
            --sidebar-width: 320px;
        }

        .sms-layout {
            display: flex;
            height: calc(100vh - 70px);
            background: #fff;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.05);
        }

        /* Contacts Sidebar */
        .sms-sidebar {
            width: var(--sidebar-width);
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            background: #fff;
        }
        .sms-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .thread-list {
            flex: 1;
            overflow-y: auto;
        }
        .thread-item {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
        }
        .thread-item:hover { background: #f8fafc; }
        .thread-item.active { background: #eff6ff; border-left: 4px solid #3b82f6; }
        
        .avatar-circle {
            width: 44px; height: 44px;
            background: #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #64748b;
        }
        .thread-item.active .avatar-circle { background: #3b82f6; color: #fff; }

        /* Phone Mockup Area */
        .phone-container {
            flex: 1;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .phone-mockup {
            width: 340px;
            height: 680px;
            background: #000;
            border-radius: 40px;
            padding: 12px;
            position: relative;
            box-shadow: 0 50px 100px -20px rgba(50,50,93,0.25), 0 30px 60px -30px rgba(0,0,0,0.3);
            border: 4px solid #334155;
        }
        .phone-screen {
            width: 100%;
            height: 100%;
            background: #fff;
            border-radius: 30px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .phone-header {
            padding: 40px 15px 15px;
            background: #f9f9f9;
            border-bottom: 1px solid #eee;
            text-align: center;
        }
        .phone-header .name { font-weight: 700; font-size: 14px; display: block; }
        .phone-header .status { font-size: 10px; color: #22c55e; }

        .chat-body {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        /* Message Bubbles */
        .msg-bubble {
            max-width: 85%;
            padding: 10px 14px;
            font-size: 13px;
            line-height: 1.4;
            position: relative;
            word-wrap: break-word;
        }
        .msg-sent {
            align-self: flex-end;
            background: var(--bubble-sent);
            color: #fff;
            border-radius: 18px 18px 2px 18px;
        }
        .msg-time {
            font-size: 9px;
            margin-top: 4px;
            opacity: 0.7;
            text-align: right;
        }

        .phone-footer {
            padding: 15px;
            border-top: 1px solid #eee;
            background: #f9f9f9;
            font-size: 12px;
            color: #94a3b8;
            text-align: center;
        }

        /* Notch */
        .phone-notch {
            position: absolute;
            top: 12px;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 25px;
            background: #000;
            border-bottom-left-radius: 15px;
            border-bottom-right-radius: 15px;
            z-index: 10;
        }
    </style>
</head>
<body class="bg-light">
<div class="d-flex">
    <?php include __DIR__ . '/../../includes/sidebar_admin.php'; ?>
    
    <div class="main-content">
        <div class="top-nav">
            <h6 class="fw-semibold mb-0">SMS Notification Simulation Center</h6>
            <?php include __DIR__ . '/../../includes/notification_bell.php'; ?>
        </div>

        <div class="p-4">
            <div class="sms-layout shadow-sm">
                <!-- Thread Sidebar -->
                <div class="sms-sidebar">
                    <div class="sms-sidebar-header">
                        <h6 class="fw-bold mb-0">Conversations</h6>
                        <small class="text-muted"><?= count($threads) ?> active numbers</small>
                    </div>
                    <div class="thread-list">
                        <?php if (empty($threads)): ?>
                            <div class="p-4 text-center text-muted small">No active simulations.</div>
                        <?php else: ?>
                            <?php foreach ($threads as $t): ?>
                                <a href="?phone=<?= urlencode($t['phone_number']) ?>" 
                                   class="thread-item <?= $selectedPhone === $t['phone_number'] ? 'active' : '' ?>">
                                    <div class="avatar-circle">
                                        <i class="bi bi-person"></i>
                                    </div>
                                    <div class="flex-grow-1 overflow-hidden">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold small"><?= htmlspecialchars($t['phone_number']) ?></span>
                                            <span class="text-muted" style="font-size: 9px;"><?= date('h:i A', strtotime($t['last_msg'])) ?></span>
                                        </div>
                                        <div class="text-muted small text-truncate">
                                            <?= $t['msg_count'] ?> messages recorded
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Phone Mockup Display -->
                <div class="phone-container">
                    <?php if (!$selectedPhone): ?>
                        <div class="text-center text-muted">
                            <i class="bi bi-phone fs-1 d-block mb-3 opacity-25"></i>
                            Select a number to view simulation
                        </div>
                    <?php else: ?>
                        <div class="phone-mockup">
                            <div class="phone-notch"></div>
                            <div class="phone-screen">
                                <div class="phone-header">
                                    <span class="name"><?= htmlspecialchars($selectedPhone) ?></span>
                                    <span class="status">● Active Simulation</span>
                                </div>
                                <div class="chat-body" id="chatBody">
                                    <?php foreach ($messages as $msg): ?>
                                        <div class="msg-bubble msg-sent">
                                            <?= htmlspecialchars($msg['message']) ?>
                                            <div class="msg-time">
                                                <?= date('h:i A', strtotime($msg['sent_at'])) ?> · Delivered
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="phone-footer">
                                    <i class="bi bi-info-circle me-1"></i> Simulator generated message
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto scroll to bottom of chat
    const chatBody = document.getElementById('chatBody');
    if (chatBody) {
        chatBody.scrollTop = chatBody.scrollHeight;
    }
</script>
</body>
</html>
