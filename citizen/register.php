<?php

require_once __DIR__ . '/../includes/auth.php';
if (isLoggedIn()) redirectByRole();

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register — IRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        :root {
            --qa-blue: #002D7A;
            --qa-blue-dark: #001A4A;
            --qa-orange: #F5A623;
            --qa-orange-hover: #D88E1B;
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
        
        /* City Skyline / Background Abstract (Optional subtle effect) */
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
            max-width: 480px;
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
        .brand-sub {
            font-size: 13px;
            color: rgba(255,255,255,0.9);
            margin-bottom: 12px;
        }
        .citizen-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--qa-orange);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 2px 8px rgba(245, 166, 35, 0.4);
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
            margin-bottom: 6px;
            display: block;
        }
        .form-control-custom {
            background: #f8f9fa;
            border: 1px solid #ced4da;
            border-radius: 10px;
            color: var(--text-main);
            font-size: 14px;
            padding: 12px 16px;
            width: 100%;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.2s;
        }
        .form-control-custom:focus {
            outline: none;
            border-color: var(--qa-blue);
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 45, 122, 0.15);
        }
        .form-control-custom::placeholder { color: #adb5bd; }
        .input-group-custom { position: relative; }
        .input-icon {
            position: absolute;
            left: 14px; top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 16px;
            pointer-events: none;
        }
        .form-control-custom.has-icon { padding-left: 42px; }
        .btn-login {
            background: var(--qa-blue);
            color: #fff;
            border: none;
            padding: 13px 20px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            width: 100%;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 45, 122, 0.2);
            margin-top: 20px;
        }
        .btn-login:hover {
            background: var(--qa-blue-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(0, 45, 122, 0.3);
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
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.2s;
            font-weight: 500;
        }
        .back-link a:hover { color: var(--qa-blue); }
    </style>
</head>
<body>

<div class="login-wrap">

    <!-- Brand -->
    <div class="brand">
        <div class="brand-icon">
            <img src="/irms/assets/img/QC_LOGO_CIRCLE.png" alt="QC Logo">
        </div>
        <div class="brand-title">QC-ALERTO</div>
        <div class="brand-sub">Gumawa ng account para ma-report ang mga insidente</div>
        <div class="citizen-badge">
            <i class="bi bi-people-fill" style="font-size:10px;"></i>
            Citizen Portal
        </div>
    </div>

    <!-- Login card -->
    <div class="login-card">

        <?php if ($error): ?>
            <div class="alert-custom">
                <i class="bi bi-x-circle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="/irms/controllers/AuthController.php?action=register" method="POST">
            
            <div class="mb-3">
                <label class="form-label-custom">Buong Pangalan <span class="text-danger">*</span></label>
                <div class="input-group-custom">
                    <i class="bi bi-person input-icon"></i>
                    <input type="text" name="name" class="form-control-custom has-icon"
                        placeholder="Juan dela Cruz" required autofocus>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label-custom">Email <span class="text-danger">*</span></label>
                <div class="input-group-custom">
                    <i class="bi bi-envelope input-icon"></i>
                    <input type="email" name="email" class="form-control-custom has-icon"
                        placeholder="email@example.com" required>
                </div>
            </div>
            
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label-custom">Phone Number</label>
                    <div class="input-group-custom">
                        <i class="bi bi-telephone input-icon"></i>
                        <input type="text" name="phone" class="form-control-custom has-icon"
                            placeholder="09XXXXXXXXX">
                    </div>
                </div>
                <div class="col-6">
                    <label class="form-label-custom">Address</label>
                    <div class="input-group-custom">
                        <i class="bi bi-geo-alt input-icon"></i>
                        <input type="text" name="address" class="form-control-custom has-icon"
                            placeholder="Barangay, Lungsod">
                    </div>
                </div>
            </div>
            
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label-custom">Password <span class="text-danger">*</span></label>
                    <div class="input-group-custom">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" name="password" id="password"
                            class="form-control-custom has-icon" placeholder="••••••••" required>
                    </div>
                </div>
                <div class="col-6">
                    <label class="form-label-custom">Confirm Password <span class="text-danger">*</span></label>
                    <div class="input-group-custom">
                        <i class="bi bi-shield-lock input-icon"></i>
                        <input type="password" name="confirm_password"
                            class="form-control-custom has-icon" placeholder="••••••••" required>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="bi bi-person-plus"></i>
                Mag-register
            </button>
        </form>

    </div>

    <!-- Back links -->
    <div class="back-link" style="margin-top:20px;">
        <span class="text-muted small">May account na?</span>
        <a href="/irms/citizen/login.php" style="color:var(--qa-blue); font-weight:700; margin-left: 4px;">
            Mag-login
        </a>
    </div>
    
    <div class="back-link" style="margin-top:8px;">
        <a href="/irms/index.php">
            <i class="bi bi-arrow-left"></i> Bumalik sa Public Portal
        </a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>