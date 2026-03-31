<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { redirectByRole(); }
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IRMS — Incident Report & Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        :root {
            --navy:   #0f172a;
            --navy2:  #1e293b;
            --navy3:  #334155;
            --accent: #ef4444;
            --accent2:#dc2626;
            --teal:   #0d9488;
            --text:   #f8fafc;
            --muted:  #94a3b8;
            --border: rgba(255,255,255,0.08);
        }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--navy);
            color: var(--text);
            margin: 0;
        }

        /* ── NAVBAR ─────────────────────────────── */
        .navbar-irms {
            background: rgba(15,23,42,0.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 14px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .navbar-brand-text {
            font-size: 18px;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.3px;
        }
        .navbar-brand-text span { color: var(--accent); }
        .nav-pill {
            font-size: 13px;
            font-weight: 500;
            color: var(--muted);
            padding: 6px 14px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .nav-pill:hover { color: var(--text); background: rgba(255,255,255,0.06); }

        /* ── HERO ────────────────────────────────── */
        .hero {
            min-height: calc(100vh - 60px);
            display: flex;
            align-items: center;
            padding: 60px 0;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -200px; right: -200px;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(239,68,68,0.08) 0%, transparent 70%);
            pointer-events: none;
        }
        .hero::after {
            content: '';
            position: absolute;
            bottom: -100px; left: -100px;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(13,148,136,0.06) 0%, transparent 70%);
            pointer-events: none;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.2);
            color: #fca5a5;
            font-size: 12px;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 20px;
            margin-bottom: 20px;
            letter-spacing: 0.5px;
        }
        .hero-badge .dot {
            width: 6px; height: 6px;
            background: var(--accent);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%,100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.4); }
        }
        .hero-title {
            font-size: clamp(36px, 5vw, 56px);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -1px;
            margin-bottom: 20px;
        }
        .hero-title .highlight { color: var(--accent); }
        .hero-desc {
            font-size: 17px;
            color: var(--muted);
            line-height: 1.7;
            margin-bottom: 36px;
            max-width: 480px;
        }
        .btn-report {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 14px 28px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 20px rgba(239,68,68,0.3);
        }
        .btn-report:hover {
            background: var(--accent2);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 6px 24px rgba(239,68,68,0.4);
        }
        .btn-track {
            background: transparent;
            color: var(--text);
            border: 1px solid rgba(255,255,255,0.15);
            padding: 14px 28px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-track:hover {
            color: #fff;
            border-color: rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.05);
        }

        /* Stats row */
        .stats-row {
            display: flex;
            gap: 32px;
            margin-top: 48px;
            padding-top: 32px;
            border-top: 1px solid var(--border);
        }
        .stat-item .num {
            font-size: 28px;
            font-weight: 800;
            color: var(--text);
        }
        .stat-item .lbl {
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        /* Action card */
        .action-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 32px;
            backdrop-filter: blur(8px);
        }
        .action-card-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .action-card-sub {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 24px;
        }
        .btn-full-red {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 13px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        .btn-full-red:hover { background: var(--accent2); color: #fff; }
        .btn-full-outline {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        .btn-full-outline:hover {
            color: #fff;
            border-color: rgba(255,255,255,0.25);
            background: rgba(255,255,255,0.05);
        }
        .staff-box {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 20px;
            margin-top: 10px;
        }
        .staff-label {
            font-size: 11px;
            color: var(--muted);
            text-align: center;
            margin-bottom: 10px;
        }
        .btn-staff {
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            transition: all 0.2s;
        }
        .btn-staff:hover {
            color: var(--text);
            border-color: rgba(255,255,255,0.2);
        }

        /* ── SECTION STYLES ──────────────────────── */
        section { padding: 80px 0; }
        .section-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--accent);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .section-title {
            font-size: clamp(28px, 4vw, 40px);
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 16px;
        }
        .section-sub {
            font-size: 16px;
            color: var(--muted);
            max-width: 520px;
        }
        .divider { border-color: var(--border); margin: 0; }

        /* ── EMERGENCY NUMBERS ───────────────────── */
        .hotline-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            transition: all 0.2s;
            height: 100%;
        }
        .hotline-card:hover {
            background: rgba(255,255,255,0.07);
            border-color: rgba(255,255,255,0.15);
            transform: translateY(-2px);
        }
        .hotline-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            margin-bottom: 12px;
        }
        .hotline-name {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .hotline-desc {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 10px;
        }
        .hotline-num {
            font-size: 22px;
            font-weight: 800;
            font-family: monospace;
            letter-spacing: 1px;
        }

        /* ── HOW IT WORKS ────────────────────────── */
        .step-card {
            position: relative;
            padding: 28px;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 16px;
            height: 100%;
            transition: all 0.2s;
        }
        .step-card:hover {
            background: rgba(255,255,255,0.05);
            border-color: rgba(255,255,255,0.12);
        }
        .step-num {
            font-size: 48px;
            font-weight: 800;
            color: rgba(255,255,255,0.06);
            position: absolute;
            top: 16px; right: 20px;
            line-height: 1;
        }
        .step-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            margin-bottom: 16px;
        }
        .step-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .step-desc {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.6;
        }

        /* ── CATEGORIES ──────────────────────────── */
        .cat-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border);
            border-radius: 12px;
            transition: all 0.2s;
        }
        .cat-item:hover {
            background: rgba(255,255,255,0.06);
            border-color: rgba(255,255,255,0.12);
        }
        .cat-icon {
            width: 42px; height: 42px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .cat-name {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .cat-responder {
            font-size: 11px;
            color: var(--muted);
        }

        /* ── FAQ ─────────────────────────────────── */
        .faq-item {
            border-bottom: 1px solid var(--border);
            padding: 20px 0;
        }
        .faq-q {
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
        }
        .faq-a {
            font-size: 14px;
            color: var(--muted);
            line-height: 1.7;
            margin-top: 12px;
            display: none;
        }
        .faq-a.open { display: block; }

        /* ── CTA SECTION ─────────────────────────── */
        .cta-section {
            background: linear-gradient(135deg, rgba(239,68,68,0.08), rgba(13,148,136,0.05));
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 60px 40px;
            text-align: center;
        }

        /* ── FOOTER ──────────────────────────────── */
        footer {
            background: rgba(0,0,0,0.3);
            border-top: 1px solid var(--border);
            padding: 32px 0;
        }

        /* ── CITIZEN LOGIN LINK ───────────────────── */
        .citizen-login-link {
            font-size: 13px;
            color: var(--muted);
            text-align: center;
            margin-top: 12px;
        }
        .citizen-login-link a {
            color: #93c5fd;
            text-decoration: none;
        }
        .citizen-login-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<!-- ── NAVBAR ──────────────────────────────────────── -->
<nav class="navbar-irms">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="navbar-brand-text">
            <i class="bi bi-shield-check me-2" style="color:var(--accent)"></i>
            IRM<span>S</span>
        </div>
        <div class="d-flex align-items-center gap-1">
            <a href="#how-it-works" class="nav-pill">Paano Gumagana</a>
            <a href="#hotlines" class="nav-pill">Mga Hotline</a>
            <a href="#categories" class="nav-pill">Kategorya</a>
            <a href="#faq" class="nav-pill">FAQ</a>
            <a href="/irms/public/report.php"
               class="btn-report ms-2" style="padding:8px 18px;font-size:13px;">
                <i class="bi bi-megaphone" style="font-size:14px;"></i>
                Mag-report
            </a>
        </div>
    </div>
</nav>

<!-- ── HERO ────────────────────────────────────────── -->
<section class="hero">
    <div class="container">
        <div class="row align-items-center g-5">

            <!-- Left -->
            <div class="col-lg-7">
                <div class="hero-badge">
                    <span class="dot"></span>
                    SISTEMA NG KOMUNIDAD
                </div>
                <h1 class="hero-title">
                    I-report ang mga<br>
                    Insidente sa iyong<br>
                    <span class="highlight">Komunidad</span>
                </h1>
                <p class="hero-desc">
                    Libre, mabilis, at epektibo. Hindi kailangan ng account para mag-report.
                    Awtomatikong napupunta sa tamang ahensya ang bawat insidente.
                </p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="/irms/public/report.php" class="btn-report">
                        <i class="bi bi-megaphone"></i> Mag-report ng Insidente
                    </a>
                    <a href="/irms/public/track.php" class="btn-track">
                        <i class="bi bi-search"></i> I-track ang Report
                    </a>
                </div>
                <div class="stats-row">
                    <div class="stat-item">
                        <div class="num">100%</div>
                        <div class="lbl">Libre</div>
                    </div>
                    <div class="stat-item">
                        <div class="num">24/7</div>
                        <div class="lbl">Available</div>
                    </div>
                    <div class="stat-item">
                        <div class="num">Auto</div>
                        <div class="lbl">Assignment</div>
                    </div>
                    <div class="stat-item">
                        <div class="num">SLA</div>
                        <div class="lbl">Response Time</div>
                    </div>
                </div>
            </div>

            <!-- Right: Action card -->
            <div class="col-lg-5">
                <div class="action-card">
                    <div class="action-card-title">May Insidente?</div>
                    <div class="action-card-sub">
                        Mag-report ngayon — hindi kailangan ng account.
                        Bibigyan ka ng tracking number para ma-monitor ang status.
                    </div>
                    <a href="/irms/public/report.php" class="btn-full-red">
                        <i class="bi bi-megaphone"></i> Mag-report ng Insidente
                    </a>
                    <a href="/irms/public/track.php" class="btn-full-outline">
                        <i class="bi bi-search"></i> I-track ang Iyong Report
                    </a>
                    <a href="/irms/citizen/login.php" class="btn-full-outline">
                        <i class="bi bi-person-circle"></i> Citizen Login / Register
                    </a>
                    <div class="staff-box">
                        <div class="staff-label">Para sa mga staff at opisyal ng gobyerno</div>
                        <a href="/irms/portal/login.php" class="btn-staff">
                            <i class="bi bi-shield-lock"></i> Staff Portal — Admin / Responder
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<hr class="divider">

<!-- ── EMERGENCY HOTLINES ───────────────────────────── -->
<section id="hotlines">
    <div class="container">
        <div class="section-label">Emergency Hotlines</div>
        <div class="row align-items-end g-0 mb-5">
            <div class="col-md-7">
                <h2 class="section-title mb-2">Sino ang Tatawagan?</h2>
                <p class="section-sub mb-0">
                    Para sa mga emergency na kailangan ng agarang tulong,
                    tawagan ang mga sumusunod na hotline. Para sa mga hindi emergency
                    na insidente, gamitin ang aming online reporting system.
                </p>
            </div>
            <div class="col-md-5 text-md-end">
                <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);
                     border-radius:10px;padding:12px 16px;display:inline-block;">
                    <div style="font-size:12px;color:#fca5a5;font-weight:600;">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Para sa emergency na sitwasyon
                    </div>
                    <div style="font-size:13px;color:var(--muted);margin-top:4px;">
                        Tumawag AGAD bago mag-report online
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-3">
            <?php
            $hotlines = [
    ['bi-telephone-fill','911','National Emergency','bg-danger bg-opacity-10','#ef4444','Lahat ng emergency — sunog, krimen, aksidente'],
    ['bi-fire','160','Bureau of Fire Protection (BFP)','bg-warning bg-opacity-10','#f59e0b','Sunog at fire-related emergencies'],
    ['bi-shield-fill','117','Philippine National Police (PNP)','bg-primary bg-opacity-10','#3b82f6','Krimen, robbery, missing persons'],
    ['bi-heart-pulse-fill','143','Philippine Red Cross','bg-danger bg-opacity-10','#ef4444','Medical emergencies at disaster response'],
    ['bi-water','8-870-0325','NDRRMC','bg-teal bg-opacity-10','#0d9488','Floods, earthquakes, natural disasters'],
    ['bi-lightning-fill','16211','Meralco Power Outage','bg-warning bg-opacity-10','#f59e0b','Power interruption at electrical issues'],
    ['bi-hospital-fill','0917-571-8433','Department of Health (DOH)','bg-success bg-opacity-10','#10b981','Health emergencies at medical concerns'],
    ['bi-sign-merge-right-fill','136','MMDA (Metro Manila)','bg-info bg-opacity-10','#06b6d4','Road accidents at traffic incidents'],
];
            foreach ($hotlines as [$icon, $num, $name, $bg, $color, $desc]):
            ?>
            <div class="col-6 col-md-3">
                <div class="hotline-card">
                    <div class="hotline-icon <?= $bg ?>" style="color:<?= $color ?>">
                    <i class="bi <?= $icon ?>" style="font-size:20px;"></i>
                    </div>
                    <div class="hotline-name"><?= $name ?></div>
                    <div class="hotline-desc"><?= $desc ?></div>
                    <div class="hotline-num" style="color:<?= $color ?>"><?= $num ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<hr class="divider">

<!-- ── HOW IT WORKS ──────────────────────────────────── -->
<section id="how-it-works">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-label">Proseso</div>
            <h2 class="section-title">Paano Gumagana ang IRMS</h2>
            <p class="section-sub mx-auto">
                Simple, mabilis, at transparent. Mula sa pag-report hanggang sa resolution,
                nandito kami sa bawat hakbang.
            </p>
        </div>
        <div class="row g-3">
            <?php
            $steps = [
    ['01','bi-pencil-square','bg-danger bg-opacity-10','#ef4444',
     'Mag-report ng Insidente',
     'Pumunta sa report form. Hindi kailangan ng account. I-fill ang detalye — ano ang nangyari, saan, at gaano ka-urgent. I-pin ang eksaktong lokasyon sa mapa at mag-upload ng larawan kung mayroon.'],
    ['02','bi-cpu','bg-warning bg-opacity-10','#f59e0b',
     'Auto-assign sa Tamang Responder',
     'Awtomatikong pinipili ng sistema ang tamang responder base sa kategorya ng insidente. Sunog → BFP. Baha → NDRRMC. Krimen → PNP. May SLA deadline para sa bawat severity level.'],
    ['03','bi-bell-fill','bg-primary bg-opacity-10','#3b82f6',
     'Nakatanggap ng Tracking Number',
     'Pagkatapos mag-submit, makatatanggap ka ng unique tracking number. Gamitin ito para ma-monitor ang status ng iyong report kahit walang account. May email notification din kung nagbigay ng email.'],
    ['04','bi-person-check-fill','bg-teal bg-opacity-10','#0d9488',
     'Responder Nag-aaksyon',
     'Ang assigned responder ay makakakita ng iyong report at mag-uupdate ng status. Makakatanggap ka ng notification sa bawat pagbabago — Pending → In Progress → Resolved.'],
    ['05','bi-check-circle-fill','bg-success bg-opacity-10','#10b981',
     'Na-resolve ang Insidente',
     'Pagkatapos matugunan ang insidente, i-mark ito bilang Resolved at bibigyan ka ng pagkakataon na mag-rate ng 1-5 stars ang serbisyo ng responder para sa mas magandang improvement.'],
    ['06','bi-bar-chart-fill','bg-purple bg-opacity-10','#8b5cf6',
     'Naka-record para sa Analytics',
     'Ang bawat insidente ay naka-log sa sistema para sa future analytics — para malaman ng LGU kung saan madalas nagkakaroon ng incidents at kung paano mapapabuti ang response time.'],
];
            foreach ($steps as [$num, $icon, $bg, $color, $title, $desc]):
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="step-card">
                    <div class="step-num"><?= $num ?></div>
                    <div class="step-icon <?= $bg ?>" style="color:<?= $color ?>">
    <i class="bi <?= $icon ?>" style="font-size:22px;"></i>
</div>
                    <div class="step-title"><?= $title ?></div>
                    <div class="step-desc"><?= $desc ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<hr class="divider">

<!-- ── INCIDENT CATEGORIES ───────────────────────────── -->
<section id="categories">
    <div class="container">
        <div class="row g-5 align-items-start">
            <div class="col-lg-4">
                <div class="section-label">Mga Kategorya</div>
                <h2 class="section-title">Anong Uri ng Insidente ang Pwedeng I-report?</h2>
                <p style="font-size:14px;color:var(--muted);line-height:1.7;">
                    Ang sistema ay sumusuporta sa iba't ibang uri ng insidente.
                    Bawat kategorya ay may nakatalagang default responder at
                    SLA deadline para masiguradong mabilis na matutugunan.
                </p>
                <a href="/irms/public/report.php" class="btn-report mt-3">
                    <i class="bi bi-megaphone"></i> Mag-report Ngayon
                </a>
            </div>
            <div class="col-lg-8">
                <div class="row g-2">
                    <?php
                    $cats = [
    ['bi-fire','Fire Incident','Bureau of Fire Protection (BFP)','bg-danger bg-opacity-10','#ef4444'],
    ['bi-water','Flood','NDRRMC / LDRRMO','bg-info bg-opacity-10','#06b6d4'],
    ['bi-car-front-fill','Road Accident','PNP / MMDA','bg-warning bg-opacity-10','#f59e0b'],
    ['bi-shield-exclamation','Crime / Theft','Philippine National Police (PNP)','bg-danger bg-opacity-10','#dc2626'],
    ['bi-hospital-fill','Medical Emergency','Department of Health (DOH)','bg-success bg-opacity-10','#10b981'],
    ['bi-lightning-fill','Power Outage','Distribution Utility / Meralco','bg-warning bg-opacity-10','#eab308'],
    ['bi-person-exclamation','Missing Person','Philippine National Police (PNP)','bg-purple bg-opacity-10','#8b5cf6'],
    ['bi-cone-striped','Infrastructure','DPWH / Local Engineering Office','bg-teal bg-opacity-10','#0d9488'],
    ['bi-three-dots','Other','Assigned by Admin','bg-secondary bg-opacity-10','#6b7280'],
];
                    foreach ($cats as [$icon, $name, $responder, $bg, $color]):
                    ?>
                    <div class="col-6 col-sm-4">
                        <div class="cat-item">
                            <div class="cat-icon <?= $bg ?>" style="color:<?= $color ?>">
    <i class="bi <?= $icon ?>" style="font-size:18px;"></i>
</div>
                            <div>
                                <div class="cat-name"><?= $name ?></div>
                                <div class="cat-responder"><?= $responder ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<hr class="divider">

<!-- ── FAQ ──────────────────────────────────────────── -->
<section id="faq">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <div class="section-label">FAQ</div>
                <h2 class="section-title">Mga Madalas na Tanong</h2>
                <p style="font-size:14px;color:var(--muted);line-height:1.7;">
                    May katanungan ka pa ba? Huwag mag-atubiling gamitin ang sistema
                    o makipag-ugnayan sa aming admin.
                </p>
            </div>
            <div class="col-lg-8">
                <?php
                $faqs = [
                    ['Libre ba ang IRMS?',
                     'Oo, 100% libre ang paggamit ng IRMS. Walang bayad, walang registration fee, at walang hidden charges. Ito ay isang public service para sa komunidad.'],
                    ['Kailangan ko bang mag-login para mag-report?',
                     'Hindi! Pwede kang mag-report kahit walang account. Pagkatapos mag-submit, bibigyan ka ng tracking number para ma-monitor ang status ng iyong report. Kung gusto mo ng mas detalyadong history at notifications, pwede kang mag-register ng libreng account.'],
                    ['Gaano katagal bago matugunan ang aking report?',
                     'Depende sa severity: Critical — 30 minuto, High — 2 oras, Medium — 24 oras, Low — 72 oras. Ang mga ito ay SLA (Service Level Agreement) deadlines na sinisigurado ng sistema. Pag nalampasan ang deadline, awtomatikong nag-eescalate sa admin.'],
                    ['Ligtas ba ang aking personal na impormasyon?',
                     'Oo. Ang iyong pangalan, email, at phone number ay hindi ipapakita sa publiko. Ginagamit lamang ito ng aming responders para makontak ka kung kailangan ng karagdagang impormasyon tungkol sa iyong report.'],
                    ['Pwede ba akong mag-report ng insidente na hindi sa aking lugar?',
                     'Oo. Pwede kang mag-report ng insidente kahit saan — basta i-pin mo ang eksaktong lokasyon sa mapa. Halimbawa, nakita mo ang isang aksidente habang nagbibiyahe — pwede mo itong i-report agad.'],
                    ['Ano ang mangyayari sa aking report pagkatapos ko mag-submit?',
                     'Awtomatikong mare-receive ng admin ang iyong report, ia-assign sa tamang responder, at magsisimulang tumugon. Matatanggap mo ang email notifications (kung nagbigay ng email) sa bawat status update — Pending, In Progress, at Resolved.'],
                    ['Paano ko malalaman kung na-resolve na ang aking report?',
                     'Gamit ang iyong tracking number, pwede mong i-check ang status anytime sa track.php. Kung nagbigay ka ng email, makakatanggap ka rin ng notification pag may pagbabago sa status ng iyong report.'],
                ];
                foreach ($faqs as [$q, $a]):
                ?>
                <div class="faq-item">
                    <div class="faq-q" onclick="toggleFaq(this)">
                        <?= $q ?>
                        <i class="bi bi-plus-circle" style="color:var(--muted);font-size:18px;flex-shrink:0;"></i>
                    </div>
                    <div class="faq-a"><?= $a ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<hr class="divider">

<!-- ── CTA SECTION ───────────────────────────────────── -->
<section>
    <div class="container">
        <div class="cta-section">
            <div class="section-label" style="margin-bottom:16px;">
                Handa ka na ba?
            </div>
            <h2 class="section-title" style="font-size:36px;margin-bottom:16px;">
                Mag-report ng Insidente Ngayon
            </h2>
            <p style="font-size:16px;color:var(--muted);margin-bottom:32px;max-width:480px;margin-left:auto;margin-right:auto;">
                Ang bawat report ay nakakatulong sa pagpapabuti ng kaligtasan
                ng ating komunidad. Libre, mabilis, at epektibo.
            </p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="/irms/public/report.php" class="btn-report">
                    <i class="bi bi-megaphone"></i> Mag-report ng Insidente
                </a>
                <a href="/irms/public/track.php" class="btn-track">
                    <i class="bi bi-search"></i> I-track ang Report
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ── FOOTER ────────────────────────────────────────── -->
<footer>
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-md-4">
                <div class="navbar-brand-text mb-2">
                    <i class="bi bi-shield-check me-2" style="color:var(--accent)"></i>
                    IRM<span style="color:var(--accent)">S</span>
                </div>
                <div style="font-size:13px;color:var(--muted);">
                    Incident Report & Monitoring System<br>
                    Para sa mas ligtas na komunidad.
                </div>
            </div>
            <div class="col-md-4">
                <div style="font-size:12px;color:var(--muted);margin-bottom:8px;font-weight:600;letter-spacing:1px;">
                    MABILIS NA LINKS
                </div>
                <div class="d-flex flex-column gap-1">
                    <a href="/irms/public/report.php" style="font-size:13px;color:var(--muted);text-decoration:none;">
                        → Mag-report ng Insidente
                    </a>
                    <a href="/irms/public/track.php" style="font-size:13px;color:var(--muted);text-decoration:none;">
                        → I-track ang Report
                    </a>
                    <a href="/irms/citizen/login.php" style="font-size:13px;color:var(--muted);text-decoration:none;">
                        → Citizen Login
                    </a>
                    <a href="/irms/citizen/register.php" style="font-size:13px;color:var(--muted);text-decoration:none;">
                        → Mag-register
                    </a>
                </div>
            </div>
            <div class="col-md-4">
                <div style="font-size:12px;color:var(--muted);margin-bottom:8px;font-weight:600;letter-spacing:1px;">
                    EMERGENCY HOTLINES
                </div>
                <div class="d-flex flex-column gap-1">
                    <span style="font-size:13px;color:var(--muted);display:flex;align-items:center;gap:6px;">
    <i class="bi bi-telephone-fill" style="color:#ef4444;"></i> National Emergency: <strong style="color:var(--text)">911</strong>
</span>
<span style="font-size:13px;color:var(--muted);display:flex;align-items:center;gap:6px;">
    <i class="bi bi-fire" style="color:#f59e0b;"></i> BFP: <strong style="color:var(--text)">160</strong>
</span>
<span style="font-size:13px;color:var(--muted);display:flex;align-items:center;gap:6px;">
    <i class="bi bi-shield-fill" style="color:#3b82f6;"></i> PNP: <strong style="color:var(--text)">117</strong>
</span>
<span style="font-size:13px;color:var(--muted);display:flex;align-items:center;gap:6px;">
    <i class="bi bi-heart-pulse-fill" style="color:#ef4444;"></i> Red Cross: <strong style="color:var(--text)">143</strong>
</span>
                </div>
            </div>
        </div>
        <hr style="border-color:var(--border);margin:24px 0;">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div style="font-size:12px;color:var(--muted);">
                © <?= date('Y') ?> IRMS — Incident Report & Monitoring System
            </div>
            <a href="/irms/portal/login.php"
               style="font-size:12px;color:var(--muted);text-decoration:none;display:flex;align-items:center;gap:5px;">
                <i class="bi bi-shield-lock"></i> Staff Portal
            </a>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleFaq(el) {
    var answer = el.nextElementSibling;
    var icon   = el.querySelector('i');
    answer.classList.toggle('open');
    icon.className = answer.classList.contains('open')
        ? 'bi bi-dash-circle'
        : 'bi bi-plus-circle';
    icon.style.color = answer.classList.contains('open') ? '#ef4444' : 'var(--muted)';
}
</script>
</body>
</html>