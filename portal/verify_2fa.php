<?php
require_once __DIR__ . '/../includes/auth.php';

// Safe check: If session user is already logged in, redirect away
if (isLoggedIn()) {
    redirectByRole();
}

// Redirect back to login if no 2FA session exists
if (!isset($_SESSION['2fa_pending_user_id'])) {
    header('Location: /irms/portal/login.php');
    exit;
}

$error   = $_GET['error']   ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>2FA Verification — IRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --qa-blue: #1e293b;
            --qa-blue-dark: #001A4A;
            --qa-orange: #F5A623;
            --text-main: #333333;
            --text-muted: #6c757d;
            --bg-body: #f8f9fa;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 250px;
            background: var(--qa-blue);
            z-index: 0;
            border-bottom: 4px solid var(--qa-orange);
        }
        .login-wrap {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
        }
        .brand {
            text-align: center;
            margin-bottom: 24px;
        }
        .brand-icon {
            width: 80px; height: 80px;
            margin: 0 auto 12px;
            background: #fff;
            border-radius: 50%;
            padding: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .brand-icon img {
            width: 100%; height: 100%;
            object-fit: contain;
        }
        .brand-title {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
            color: #fff;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .login-card {
            background: #ffffff;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        .form-label-custom {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 12px;
            display: block;
            text-align: center;
        }
        .otp-input-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 24px;
        }
        .otp-digit {
            width: 45px;
            height: 55px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            border: 1px solid #ced4da;
            border-radius: 10px;
            background: #f8f9fa;
            transition: all 0.2s;
        }
        .otp-digit:focus {
            outline: none;
            border-color: var(--qa-orange);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(245, 166, 35, 0.15);
        }
        .btn-verify {
            background: var(--qa-blue);
            color: #fff;
            border: none;
            padding: 13px 20px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            width: 100%;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 45, 122, 0.2);
        }
        .btn-verify:hover {
            background: var(--qa-blue-dark);
            transform: translateY(-1px);
        }
        .alert-custom {
            background: #f8d7da;
            border: 1px solid #f5c2c7;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13px;
            color: #842029;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .back-link {
            text-align: center;
            margin-top: 16px;
        }
        .back-link a {
            font-size: 13px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
        }
        .back-link a:hover { color: var(--qa-blue); }
        
        /* Hide arrows in number input */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
    </style>
</head>
<body>

<div class="login-wrap">
    <div class="brand">
        <div class="brand-icon">
            <img src="/irms/assets/img/QC_LOGO_CIRCLE.png" alt="QC Logo">
        </div>
        <div class="brand-title">QC-ALERTO</div>
        <div style="color:rgba(255,255,255,0.8); font-size:13px;">Security Verification</div>
    </div>

    <div class="login-card">
        <h5 class="text-center mb-4" style="font-weight:700;">Two-Factor Authentication</h5>
        <p class="text-center text-muted small mb-4">
            Nagpadala kami ng 6-digit code sa iyong email. 
            Pakitingnan ang iyong inbox (o spam folder).
        </p>

        <?php if ($error): ?>
            <div class="alert-custom">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="/irms/controllers/AuthController.php?action=verify2fa" method="POST" id="2fa-form">
            <?= csrf_field() ?>
            
            <label class="form-label-custom">Ilagay ang 6-digit OTP Code</label>
            
            <div class="otp-input-group">
                <input type="text" maxlength="1" class="otp-digit" pattern="\d*" inputmode="numeric" required autofocus>
                <input type="text" maxlength="1" class="otp-digit" pattern="\d*" inputmode="numeric" required>
                <input type="text" maxlength="1" class="otp-digit" pattern="\d*" inputmode="numeric" required>
                <input type="text" maxlength="1" class="otp-digit" pattern="\d*" inputmode="numeric" required>
                <input type="text" maxlength="1" class="otp-digit" pattern="\d*" inputmode="numeric" required>
                <input type="text" maxlength="1" class="otp-digit" pattern="\d*" inputmode="numeric" required>
            </div>
            
            <!-- Hidden field for actual combined OTP -->
            <input type="hidden" name="otp_code" id="full-otp">

            <button type="submit" class="btn-verify">
                <i class="bi bi-shield-check"></i>
                I-verify at Mag-login
            </button>
        </form>

        <div class="text-center mt-4">
            <span class="text-muted small">Walang natanggap?</span>
            <a href="javascript:location.reload()" class="small ms-1" style="color:var(--qa-orange); font-weight:700; text-decoration:none;">
                I-resend ang code
            </a>
        </div>
    </div>

    <div class="back-link">
        <a href="/irms/portal/login.php">
            <i class="bi bi-arrow-left"></i> Bumalik sa Login
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputs = document.querySelectorAll('.otp-digit');
    const fullOtpInput = document.getElementById('full-otp');
    const form = document.getElementById('2fa-form');

    inputs.forEach((input, index) => {
        // Handle input
        input.addEventListener('input', (e) => {
            if (e.target.value.length > 1) {
                e.target.value = e.target.value.slice(0, 1);
            }
            if (e.target.value && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
            combineOtp();
        });

        // Handle backspace
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                inputs[index - 1].focus();
            }
        });

        // Handle paste
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasteData = e.clipboardData.getData('text').slice(0, 6).split('');
            pasteData.forEach((char, i) => {
                if (inputs[i]) inputs[i].value = char;
            });
            combineOtp();
            if (pasteData.length > 0) {
                inputs[Math.min(pasteData.length, inputs.length - 1)].focus();
            }
        });
    });

    function combineOtp() {
        let code = '';
        inputs.forEach(input => code += input.value);
        fullOtpInput.value = code;
    }

    form.addEventListener('submit', (e) => {
        combineOtp();
        if (fullOtpInput.value.length !== 6) {
            e.preventDefault();
            alert('Pakikumpleto ang 6-digit code.');
        }
    });
});
</script>
</body>
</html>
