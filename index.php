<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { redirectByRole(); }
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QC-ALERTO — Quezon City Incident Report & Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --qc-blue:   #003DA5;
            --qc-blue2:  #002D7A;
            --qc-blue3:  #1A5CC8;
            --qc-gold:   #F5A623;
            --qc-gold2:  #D4891A;
            --qc-red:    #CC0000;
            --qc-red2:   #A30000;
            --bg:        #F2F4F8;
            --white:     #FFFFFF;
            --text:      #111827;
            --text2:     #374151;
            --muted:     #6B7280;
            --border:    #DDE2EE;
            --border2:   #C5CEDF;
        }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        /* ── TOP BAR ─────────────────────────────── */
        .top-bar {
            background: var(--qc-blue2);
            padding: 6px 0;
        }
        .top-bar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11.5px;
            color: rgba(255,255,255,0.7);
        }
        .flag-ph { display: flex; align-items: center; gap: 6px; font-weight: 500; }
        .hotline-quick { display: flex; align-items: center; gap: 16px; }
        .hotline-quick span { display: flex; align-items: center; gap: 5px; }
        .hotline-quick strong { color: var(--qc-gold); }

        /* ── NAVBAR ──────────────────────────────── */
        .main-nav {
            background: var(--white);
            border-bottom: 3px solid var(--qc-gold);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
        }
        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 80px;
        }

        /* Logo — wide version (navbar) */
        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .nav-logo-img {
            height: 80px;
            width: auto;
            object-fit: contain;
        }
        .nav-logo-circle {
            height: 80px;
            width: 80px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .nav-logo-divider {
            width: 1px;
            height: 32px;
            background: var(--border);
        }
        .nav-logo-sub {
            font-size: 11px;
            color: var(--muted);
            font-weight: 500;
            line-height: 1.3;
            max-width: 160px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .nav-link-btn {
            font-size: 13px;
            font-weight: 500;
            color: var(--text2);
            text-decoration: none;
            padding: 7px 13px;
            border-radius: 6px;
            transition: all 0.15s;
        }
        .nav-link-btn:hover { color: var(--qc-blue); background: rgba(0,61,165,0.06); }
        .nav-report-btn {
            background: var(--qc-red);
            color: #fff !important;
            padding: 9px 18px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 7px;
            margin-left: 6px;
            transition: all 0.15s;
        }
        .nav-report-btn:hover { background: var(--qc-red2); transform: translateY(-1px); }
        .hamburger {
            display: none;
            background: none;
            border: 1px solid var(--border);
            color: var(--text);
            width: 38px; height: 38px;
            border-radius: 7px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
        }

        /* Mobile drawer */
        .mob-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 998;
        }
        .mob-overlay.open { display: block; }
        .mob-drawer {
            position: fixed;
            top: 0; right: -300px;
            width: 300px; height: 100%;
            background: var(--white);
            z-index: 999;
            padding: 20px;
            transition: right 0.25s ease;
            box-shadow: -4px 0 24px rgba(0,0,0,0.15);
            overflow-y: auto;
        }
        .mob-drawer.open { right: 0; }
        .mob-drawer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        .mob-close {
            background: none;
            border: 1px solid var(--border);
            width: 32px; height: 32px;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            font-size: 14px;
            color: var(--text);
        }
        .mob-nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            color: var(--text2);
            margin-bottom: 4px;
            transition: all 0.15s;
        }
        .mob-nav-link:hover { background: rgba(0,61,165,0.06); color: var(--qc-blue); }
        .mob-nav-report { background: var(--qc-red); color: #fff !important; margin-top: 8px; }
        .mob-nav-report:hover { background: var(--qc-red2) !important; }

        @media (max-width: 991px) {
            .nav-links { display: none; }
            .hamburger { display: flex; }
        }

        /* ── HERO ────────────────────────────────── */
        .hero {
            /* QC Banner image with dark blue overlay */
            background:
            linear-gradient(
            135deg,
            rgba(0,10,40,0.75) 0%,
            rgba(0,20,70,0.65) 50%,
            rgba(0,10,40,0.70) 100%
            ),
            url('/irms/assets/img/QC_BANNER.png') center center / cover no-repeat;
            padding: 72px 0 80px;
            position: relative;
            overflow: hidden;
        }
        /* Subtle diagonal stripes on top of image */
        .hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                repeating-linear-gradient(
                    -45deg,
                    transparent, transparent 60px,
                    rgba(255,255,255,0.012) 60px,
                    rgba(255,255,255,0.012) 120px
                );
            pointer-events: none;
        }
        .hero-z { position: relative; z-index: 1; }

        .qc-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(245,166,35,0.15);
            border: 1px solid rgba(245,166,35,0.3);
            color: var(--qc-gold);
            font-size: 11px;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 4px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }
        .qc-chip .live {
            width: 6px; height: 6px;
            background: var(--qc-gold);
            border-radius: 50%;
            animation: livepulse 1.5s infinite;
        }
        @keyframes livepulse { 0%,100%{opacity:1;} 50%{opacity:0.25;} }

        .hero-title {
            font-size: clamp(34px, 5vw, 58px);
            font-weight: 800;
            color: #fff;
            line-height: 1.08;
            letter-spacing: -1px;
            margin-bottom: 18px;
        }
        .hero-title .gold { color: var(--qc-gold); }
        .hero-desc {
            font-size: 16px;
            color: rgba(255,255,255,0.7);
            line-height: 1.75;
            max-width: 480px;
            margin-bottom: 36px;
        }
        .hero-btn-primary {
            background: var(--qc-red);
            color: #fff;
            padding: 14px 26px;
            border-radius: 7px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 20px rgba(204,0,0,0.4);
        }
        .hero-btn-primary:hover {
            background: var(--qc-red2);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(204,0,0,0.5);
        }
        .hero-btn-secondary {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 14px 26px;
            border-radius: 7px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .hero-btn-secondary:hover {
            background: rgba(255,255,255,0.18);
            color: #fff;
            border-color: rgba(255,255,255,0.4);
        }

        /* Stats */
        .hero-stats {
            display: flex;
            gap: 0;
            margin-top: 48px;
            padding-top: 28px;
            border-top: 1px solid rgba(255,255,255,0.12);
        }
        .stat-col { flex: 1; padding-right: 24px; }
        .stat-col:not(:last-child) {
            border-right: 1px solid rgba(255,255,255,0.12);
            margin-right: 24px;
        }
        .stat-col .n { font-size: 28px; font-weight: 800; color: var(--qc-gold); line-height: 1; }
        .stat-col .l { font-size: 11px; color: rgba(255,255,255,0.45); margin-top: 4px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Action panel */
        .action-panel {
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 24px 64px rgba(0,0,0,0.25);
        }
        .ap-header {
            background: var(--qc-blue2);
            padding: 18px 22px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 2px solid var(--qc-gold);
        }
        .ap-header-icon {
            width: 38px; height: 38px;
            background: rgba(245,166,35,0.15);
            border: 1px solid rgba(245,166,35,0.3);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: var(--qc-gold);
            font-size: 17px;
            flex-shrink: 0;
        }
        .ap-title { font-size: 15px; font-weight: 700; color: #fff; }
        .ap-sub   { font-size: 11.5px; color: rgba(255,255,255,0.5); margin-top: 2px; }
        .ap-body  { padding: 18px 20px; }

        .ap-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 8px;
            text-decoration: none;
            margin-bottom: 7px;
            transition: all 0.15s;
        }
        .ap-btn-icon {
            width: 34px; height: 34px;
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }
        .ap-btn-red { background: var(--qc-red); color: #fff; }
        .ap-btn-red:hover { background: var(--qc-red2); color: #fff; }
        .ap-btn-red .ap-btn-icon { background: rgba(255,255,255,0.15); color: #fff; }
        .ap-btn-red .ap-btn-sub  { color: rgba(255,255,255,0.65); }

        .ap-btn-light { background: var(--bg); color: var(--text); border: 1px solid var(--border); }
        .ap-btn-light:hover { background: rgba(0,61,165,0.05); border-color: var(--border2); color: var(--qc-blue); }
        .ap-btn-light .ap-btn-icon { background: rgba(0,61,165,0.08); color: var(--qc-blue); }
        .ap-btn-light .ap-btn-sub  { color: var(--muted); }

        .ap-btn-label { font-size: 13px; font-weight: 600; line-height: 1.2; }
        .ap-btn-sub   { font-size: 11px; font-weight: 400; margin-top: 1px; }
        .ap-divider   { border: none; border-top: 1px solid var(--border); margin: 10px 0; }

        .staff-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: #F7F9FF;
            border: 1px solid var(--border);
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.15s;
        }
        .staff-row:hover { background: rgba(0,61,165,0.05); border-color: var(--border2); }
        .staff-row-icon {
            width: 30px; height: 30px;
            background: rgba(0,61,165,0.08);
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            color: var(--qc-blue);
            font-size: 14px;
            flex-shrink: 0;
        }
        .staff-row-text  { flex: 1; }
        .staff-row-label { font-size: 12px; font-weight: 600; color: var(--text2); }
        .staff-row-sub   { font-size: 11px; color: var(--muted); }
        .staff-row-arrow { color: var(--muted); font-size: 13px; }

        /* ── GOLD BAR ────────────────────────────── */
        .gold-bar { background: var(--qc-gold); padding: 12px 0; border-top: 1px solid var(--qc-gold2); }
        .gb-items { display: flex; align-items: center; justify-content: center; gap: 32px; flex-wrap: wrap; }
        .gb-item  { display: flex; align-items: center; gap: 7px; font-size: 12.5px; font-weight: 700; color: var(--qc-blue2); }
        .gb-item i { font-size: 14px; }

        /* ── SECTIONS ────────────────────────────── */
        .section-pad { padding: 72px 0; }
        .sec-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--qc-blue);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sec-label::before {
            content: '';
            display: inline-block;
            width: 18px; height: 2px;
            background: var(--qc-gold);
            border-radius: 2px;
        }
        .sec-title {
            font-size: clamp(24px, 3.5vw, 36px);
            font-weight: 800;
            color: var(--text);
            margin-bottom: 12px;
            letter-spacing: -0.5px;
            line-height: 1.1;
        }
        .sec-sub { font-size: 15px; color: var(--muted); line-height: 1.7; }
        .section-divider { border: none; border-top: 1px solid var(--border); margin: 0; }

        /* ── HOTLINES ────────────────────────────── */
        .hotline-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px;
            height: 100%;
            position: relative;
            overflow: hidden;
            transition: all 0.15s;
        }
        .hotline-card:hover {
            border-color: var(--border2);
            box-shadow: 0 4px 16px rgba(0,0,0,0.07);
            transform: translateY(-2px);
        }
        .hc-stripe { position: absolute; top:0; left:0; right:0; height:3px; border-radius:10px 10px 0 0; }
        .hc-icon   { width:40px; height:40px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:17px; margin-bottom:10px; }
        .hc-name   { font-size:13px; font-weight:700; color:var(--text); margin-bottom:3px; }
        .hc-desc   { font-size:11.5px; color:var(--muted); margin-bottom:8px; line-height:1.4; }
        .hc-num    { font-size:20px; font-weight:800; letter-spacing:0.5px; }

        /* Featured QC 122 card */
        .hotline-featured {
            background: var(--qc-blue);
            border: none;
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .hf-icon {
            width: 52px; height: 52px;
            background: rgba(245,166,35,0.15);
            border: 1px solid rgba(245,166,35,0.3);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            color: var(--qc-gold);
            flex-shrink: 0;
        }
        .hf-body { flex: 1; }
        .hf-tag  { font-size:11px; font-weight:700; color:var(--qc-gold); letter-spacing:1px; text-transform:uppercase; margin-bottom:3px; }
        .hf-name { font-size:16px; font-weight:800; color:#fff; margin-bottom:2px; }
        .hf-desc { font-size:13px; color:rgba(255,255,255,0.55); }
        .hf-num  { font-size:52px; font-weight:900; color:var(--qc-gold); letter-spacing:-1px; flex-shrink:0; }

        /* ── STEPS ───────────────────────────────── */
        .step-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 24px;
            height: 100%;
            position: relative;
            transition: all 0.15s;
        }
        .step-card:hover { border-color: var(--qc-blue); box-shadow: 0 4px 20px rgba(0,61,165,0.09); }
        .step-bg-num { position:absolute; top:10px; right:14px; font-size:48px; font-weight:900; color:rgba(0,61,165,0.05); line-height:1; }
        .step-icon-wrap { width:44px; height:44px; background:rgba(0,61,165,0.07); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:19px; color:var(--qc-blue); margin-bottom:14px; }
        .step-title { font-size:14px; font-weight:700; color:var(--text); margin-bottom:6px; }
        .step-desc  { font-size:13px; color:var(--muted); line-height:1.6; }

        /* ── CATEGORIES ──────────────────────────── */
        .cat-row {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.15s;
        }
        .cat-row:hover { border-color: var(--border2); box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        .cat-icon { width:38px; height:38px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0; }
        .cat-name { font-size:13px; font-weight:700; color:var(--text); }
        .cat-resp { font-size:11px; color:var(--muted); margin-top:1px; }

        /* ── FAQ ─────────────────────────────────── */
        .faq-item { background:var(--white); border:1px solid var(--border); border-radius:9px; margin-bottom:6px; overflow:hidden; transition:border-color 0.15s; }
        .faq-item.open { border-color:var(--qc-blue); }
        .faq-q { padding:16px 18px; font-size:14px; font-weight:600; color:var(--text); cursor:pointer; display:flex; justify-content:space-between; align-items:center; gap:12px; user-select:none; }
        .faq-q i { color:var(--muted); font-size:16px; flex-shrink:0; transition:all 0.2s; }
        .faq-item.open .faq-q { color:var(--qc-blue); }
        .faq-item.open .faq-q i { transform:rotate(45deg); color:var(--qc-blue); }
        .faq-a { display:none; padding:14px 18px 16px; font-size:13.5px; color:var(--muted); line-height:1.75; border-top:1px solid var(--border); }
        .faq-a.open { display:block; }

        /* ── CTA ─────────────────────────────────── */
        .cta-block {
            background:
                linear-gradient(135deg, rgba(0,29,80,0.92), rgba(0,45,122,0.88)),
                url('/irms/assets/img/QC_BANNER.png') center / cover no-repeat;
            border-radius: 14px;
            padding: 56px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;   
        }
        .cta-block::before {
            content: '';
            position: absolute; inset: 0;
            background: repeating-linear-gradient(-45deg, transparent, transparent 60px, rgba(255,255,255,0.012) 60px, rgba(255,255,255,0.012) 120px);
            pointer-events: none;
        }
        .cta-inner { position: relative; z-index: 1; }
        .cta-block h2 { font-size:32px; font-weight:800; color:#fff; margin-bottom:12px; letter-spacing:-0.5px; }
        .cta-block p  { font-size:15px; color:rgba(255,255,255,0.6); margin-bottom:28px; max-width:440px; margin-left:auto; margin-right:auto; }

        /* ── FOOTER ──────────────────────────────── */
        footer {
            background: var(--qc-blue2);
            border-top: 3px solid var(--qc-gold);
            padding: 48px 0 24px;
        }
        .footer-logo { height: 40px; width: auto; object-fit: contain; filter: brightness(0) invert(1); opacity: 0.9; }
        .footer-sub  { font-size:12px; color:rgba(255,255,255,0.4); margin-top:6px; }
        .footer-h    { font-size:10.5px; font-weight:700; color:rgba(255,255,255,0.35); letter-spacing:1.5px; text-transform:uppercase; margin-bottom:12px; }
        .footer-a    { display:block; font-size:13px; color:rgba(255,255,255,0.55); text-decoration:none; margin-bottom:6px; transition:color 0.15s; }
        .footer-a:hover { color:rgba(255,255,255,0.9); }
        .footer-hl   { font-size:13px; color:rgba(255,255,255,0.55); margin-bottom:6px; display:flex; align-items:center; gap:7px; }
        .footer-hl strong { color:#fff; }
        .footer-bottom { border-top:1px solid rgba(255,255,255,0.08); margin-top:32px; padding-top:18px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
        .footer-copy   { font-size:11.5px; color:rgba(255,255,255,0.3); }
        .footer-staff  { font-size:11.5px; color:rgba(255,255,255,0.35); text-decoration:none; display:flex; align-items:center; gap:5px; transition:color 0.15s; }
        .footer-staff:hover { color:rgba(255,255,255,0.65); }

        /* Mobile */
        @media (max-width:768px) {
            .hotline-quick { display:none; }
            .hero-stats { flex-wrap:wrap; }
            .stat-col { min-width:45%; margin-bottom:12px; }
            .gb-items { gap:14px; }
            .cta-block { padding:40px 20px; }
            .cta-block h2 { font-size:26px; }
            .hf-num { font-size:36px; }
        }
    </style>
</head>
<body>

<!-- ── TOP BAR ─────────────────────────────────────── -->
<div class="top-bar">
    <div class="container">
        <div class="top-bar-inner">
            <div class="flag-ph">🇵🇭 Opisyal na Website — Lungsod ng Quezon City</div>
            <div class="hotline-quick">
                <span><i class="bi bi-telephone-fill"></i> Emergency: <strong>911</strong></span>
                <span><i class="bi bi-headset"></i> QC Helpline: <strong>122</strong></span>
                <span><i class="bi bi-fire"></i> BFP-QC: <strong>(02) 8928-1900</strong></span>
            </div>
        </div>
    </div>
</div>

<!-- ── NAVBAR ──────────────────────────────────────── -->
<nav class="main-nav">
    <div class="container">
        <div class="nav-inner">
            <a href="/irms/index.php" class="nav-logo">
                <!-- Circular logo (icon) -->
                <img src="/irms/assets/img/QC_LOGO_CIRCLE.png"
                     alt="QC-ALERTO" class="nav-logo-circle">
                <!-- Divider -->
                <div class="nav-logo-divider"></div>
                <!-- Wide logo -->
                <img src="/irms/assets/img/QC_LOGO.png"
                alt="QC-ALERTO — Bawat Report, May Aksyon"
                class="nav-logo-img">
            </a>
            <div class="nav-links">
                <a href="#how-it-works" class="nav-link-btn">Paano Gumagana</a>
                <a href="#hotlines"     class="nav-link-btn">Mga Hotline</a>
                <a href="#categories"   class="nav-link-btn">Kategorya</a>
                <a href="#faq"          class="nav-link-btn">FAQ</a>
                <a href="/irms/public/report.php" class="nav-report-btn">
                    <i class="bi bi-megaphone-fill"></i> Mag-report
                </a>
            </div>
            <button class="hamburger" onclick="openMobile()">
                <i class="bi bi-list"></i>
            </button>
        </div>
    </div>
</nav>

<!-- Mobile drawer -->
<div class="mob-overlay" id="mobOverlay" onclick="closeMobile()"></div>
<div class="mob-drawer" id="mobDrawer">
    <div class="mob-drawer-header">
        <img src="/irms/assets/img/QC_LOGO_CIRCLE.png" alt="QC-ALERTO"
             style="height:32px;width:32px;object-fit:contain;">
        <button class="mob-close" onclick="closeMobile()"><i class="bi bi-x-lg"></i></button>
    </div>
    <a href="#how-it-works" class="mob-nav-link" onclick="closeMobile()">
        <i class="bi bi-info-circle" style="color:var(--qc-blue);"></i> Paano Gumagana
    </a>
    <a href="#hotlines" class="mob-nav-link" onclick="closeMobile()">
        <i class="bi bi-telephone" style="color:var(--qc-blue);"></i> Mga Hotline
    </a>
    <a href="#categories" class="mob-nav-link" onclick="closeMobile()">
        <i class="bi bi-grid" style="color:var(--qc-blue);"></i> Kategorya
    </a>
    <a href="#faq" class="mob-nav-link" onclick="closeMobile()">
        <i class="bi bi-question-circle" style="color:var(--qc-blue);"></i> FAQ
    </a>
    <a href="/irms/public/report.php" class="mob-nav-link mob-nav-report" onclick="closeMobile()">
        <i class="bi bi-megaphone-fill"></i> Mag-report ng Insidente
    </a>
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);">
        <a href="/irms/portal/login.php" class="mob-nav-link" onclick="closeMobile()"
           style="color:var(--muted);font-size:13px;">
            <i class="bi bi-shield-lock" style="color:var(--muted);"></i> Staff Portal
        </a>
        <a href="/irms/citizen/login.php" class="mob-nav-link" onclick="closeMobile()"
           style="color:var(--muted);font-size:13px;">
            <i class="bi bi-person-circle" style="color:var(--muted);"></i> Citizen Login
        </a>
    </div>
</div>

<!-- ── HERO (QC Banner background) ─────────────────── -->
<section class="hero">
    <div class="container hero-z">
        <div class="row align-items-center g-5">

            <!-- Left: Text content -->
            <div class="col-lg-7">
                <div class="qc-chip">
                    <span class="live"></span>
                    Quezon City — Opisyal na Sistema
                </div>
                <h1 class="hero-title">
                    Bawat Report,<br>
                    May Aksyon sa<br>
                    <span class="gold">Quezon City.</span>
                </h1>
                <p class="hero-desc">
                    Ang QC-ALERTO ay ang opisyal na incident reporting at monitoring system
                    ng Lungsod ng Quezon City. Libre, available 24/7, at direktang napupunta
                    sa tamang ahensya ang bawat report ng mamamayan.
                </p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="/irms/public/report.php" class="hero-btn-primary">
                        <i class="bi bi-megaphone-fill"></i> Mag-report ng Insidente
                    </a>
                    <a href="/irms/public/track.php" class="hero-btn-secondary">
                        <i class="bi bi-search"></i> I-track ang Report
                    </a>
                </div>
                <div class="hero-stats">
                    <div class="stat-col">
                        <div class="n">100%</div>
                        <div class="l">Libre</div>
                    </div>
                    <div class="stat-col">
                        <div class="n">24/7</div>
                        <div class="l">Available</div>
                    </div>
                    <div class="stat-col">
                        <div class="n">Auto</div>
                        <div class="l">Assignment</div>
                    </div>
                    <div class="stat-col">
                        <div class="n">SLA</div>
                        <div class="l">Response Time</div>
                    </div>
                </div>
            </div>

            <!-- Right: Action panel -->
            <div class="col-lg-5">
                <div class="action-panel">
                    <div class="ap-header">
                        <div class="ap-header-icon">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                        <div>
                            <div class="ap-title">May Insidente sa QC?</div>
                            <div class="ap-sub">Hindi kailangan ng account — libre at mabilis</div>
                        </div>
                    </div>
                    <div class="ap-body">
                        <a href="/irms/public/report.php" class="ap-btn ap-btn-red">
                            <div class="ap-btn-icon"><i class="bi bi-megaphone-fill"></i></div>
                            <div style="flex:1;">
                                <div class="ap-btn-label">Mag-report ng Insidente</div>
                                <div class="ap-btn-sub">Anonymous — may tracking number</div>
                            </div>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="/irms/public/track.php" class="ap-btn ap-btn-light">
                            <div class="ap-btn-icon"><i class="bi bi-search"></i></div>
                            <div style="flex:1;">
                                <div class="ap-btn-label">I-track ang Iyong Report</div>
                                <div class="ap-btn-sub">Gamitin ang tracking number</div>
                            </div>
                            <i class="bi bi-arrow-right" style="color:var(--muted);font-size:13px;"></i>
                        </a>
                        <a href="/irms/citizen/login.php" class="ap-btn ap-btn-light">
                            <div class="ap-btn-icon"><i class="bi bi-person-circle"></i></div>
                            <div style="flex:1;">
                                <div class="ap-btn-label">Citizen Login / Register</div>
                                <div class="ap-btn-sub">Para sa mas detalyadong tracking</div>
                            </div>
                            <i class="bi bi-arrow-right" style="color:var(--muted);font-size:13px;"></i>
                        </a>
                        <hr class="ap-divider">
                        <a href="/irms/portal/login.php" class="staff-row">
                            <div class="staff-row-icon"><i class="bi bi-shield-lock-fill"></i></div>
                            <div class="staff-row-text">
                                <div class="staff-row-label">Staff Portal — Admin / Responder</div>
                                <div class="staff-row-sub">Para sa mga opisyal ng Quezon City</div>
                            </div>
                            <i class="bi bi-box-arrow-in-right staff-row-arrow"></i>
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ── GOLD BAR ─────────────────────────────────────── -->
<div class="gold-bar">
    <div class="container">
        <div class="gb-items">
            <div class="gb-item"><i class="bi bi-clock-fill"></i> Critical: 30 minuto response</div>
            <div class="gb-item"><i class="bi bi-lightning-fill"></i> High: 2 oras response</div>
            <div class="gb-item"><i class="bi bi-cpu-fill"></i> Auto-assign sa tamang ahensya</div>
            <div class="gb-item"><i class="bi bi-bell-fill"></i> Real-time notifications</div>
            <div class="gb-item"><i class="bi bi-shield-check-fill"></i> Secure at confidential</div>
        </div>
    </div>
</div>

<!-- ── HOTLINES ────────────────────────────────────── -->
<section class="section-pad" id="hotlines">
    <div class="container">
        <div class="row g-0 align-items-end mb-5">
            <div class="col-md-7">
                <div class="sec-label">Emergency Hotlines</div>
                <h2 class="sec-title">Sino ang Tatawagan?</h2>
                <p class="sec-sub">
                    Para sa mga life-threatening emergencies, tumawag AGAD bago mag-report online.
                    Ang QC-ALERTO ay para sa documented incident reports na may tracking.
                </p>
            </div>
            <div class="col-md-5 text-md-end">
                <div style="background:#FFF5F5;border:1px solid #FECACA;border-radius:8px;
                     padding:12px 16px;display:inline-block;">
                    <div style="font-size:12px;color:var(--qc-red);font-weight:700;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Life-threatening emergencies
                    </div>
                    <div style="font-size:12px;color:var(--muted);margin-top:3px;">
                        Tumawag AGAD — huwag mag-online report muna
                    </div>
                </div>
            </div>
        </div>

        <!-- QC Helpline 122 — featured -->
        <div class="hotline-featured mb-3">
            <div class="hf-icon"><i class="bi bi-headset"></i></div>
            <div class="hf-body">
                <div class="hf-tag">QC Official Helpline</div>
                <div class="hf-name">QC Helpline — Quezon City</div>
                <div class="hf-desc">Opisyal na hotline ng Lungsod ng Quezon City — lahat ng concerns, complaints, at emergencies</div>
            </div>
            <div class="hf-num">122</div>
        </div>

        <div class="row g-3">
            <?php
            $hotlines = [
                ['bi-telephone-fill','#CC0000','rgba(204,0,0,0.07)',
                 '911','National Emergency','Lahat ng emergency — sunog, krimen, aksidente'],
                ['bi-fire','#f59e0b','rgba(245,158,11,0.07)',
                 '(02) 8928-1900','BFP — Quezon City','Sunog at fire-related emergencies'],
                ['bi-shield-fill','#2563eb','rgba(37,99,235,0.07)',
                 '(02) 8924-4626','PNP — Quezon City','Krimen, robbery, missing persons'],
                ['bi-heart-pulse-fill','#dc2626','rgba(220,38,38,0.07)',
                 '(02) 8988-4242','QCDRRMO','Disaster response at calamities'],
                ['bi-droplet-fill','#0891b2','rgba(8,145,178,0.07)',
                 '(02) 8441-1111','Maynilad — QC','Baha, water service interruption'],
                ['bi-lightning-fill','#ca8a04','rgba(202,138,4,0.07)',
                 '16211','Meralco','Power outage sa Quezon City'],
                ['bi-hospital-fill','#16a34a','rgba(22,163,74,0.07)',
                 '(02) 8921-3330','QC General Hospital','Medical emergencies sa QC'],
                ['bi-sign-merge-right-fill','#7c3aed','rgba(124,58,237,0.07)',
                 '136','MMDA','Road accidents at traffic incidents'],
            ];
            foreach ($hotlines as [$icon,$color,$bg,$num,$name,$desc]):
            ?>
            <div class="col-6 col-md-3">
                <div class="hotline-card">
                    <div class="hc-stripe" style="background:<?= $color ?>;"></div>
                    <div class="hc-icon" style="background:<?= $bg ?>;color:<?= $color ?>;">
                        <i class="bi <?= $icon ?>"></i>
                    </div>
                    <div class="hc-name"><?= $name ?></div>
                    <div class="hc-desc"><?= $desc ?></div>
                    <div class="hc-num" style="color:<?= $color ?>;"><?= $num ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<hr class="section-divider">

<!-- ── HOW IT WORKS ──────────────────────────────────── -->
<section class="section-pad" id="how-it-works">
    <div class="container">
        <div class="text-center mb-5">
            <div class="sec-label" style="justify-content:center;">Proseso</div>
            <h2 class="sec-title">Paano Gumagana ang QC-ALERTO</h2>
            <p class="sec-sub mx-auto" style="max-width:520px;">
                Simple, transparent, at mabilis. Mula sa pag-report hanggang sa resolution.
            </p>
        </div>
        <div class="row g-3">
            <?php
            $steps = [
                ['01','bi-pencil-square','Mag-report ng Insidente',
                 'Pumunta sa report form. Hindi kailangan ng account. I-fill ang detalye, i-pin ang eksaktong lokasyon sa mapa, at mag-upload ng larawan kung mayroon.'],
                ['02','bi-cpu-fill','Auto-assign sa Tamang Ahensya',
                 'Awtomatikong pinipili ng sistema ang tamang ahensya. Sunog → BFP-QC. Baha → QCDRRMO. Krimen → PNP-QC. May SLA deadline per severity level.'],
                ['03','bi-bell-fill','Nakatanggap ng Tracking Number',
                 'Pagkatapos mag-submit, makatatanggap ka ng unique QC-ALERTO tracking number para ma-monitor ang status kahit walang account.'],
                ['04','bi-person-check-fill','Responder Nag-aaksyon',
                 'Ang assigned responder ay makakakita ng report at mag-uupdate ng status — Pending → In Progress → Resolved.'],
                ['05','bi-check-circle-fill','Na-resolve ang Insidente',
                 'Pagkatapos matugunan, i-mark bilang Resolved at pwede kang mag-rate ng serbisyo ng responder para sa continuous improvement.'],
                ['06','bi-bar-chart-fill','Data para sa Mas Mabuting QC',
                 'Ang bawat insidente ay naka-record para malaman ng LGU kung saan madalas nagkakaroon ng mga problema at mapabuti ang response time.'],
            ];
            foreach ($steps as [$num,$icon,$title,$desc]):
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="step-card">
                    <div class="step-bg-num"><?= $num ?></div>
                    <div class="step-icon-wrap"><i class="bi <?= $icon ?>"></i></div>
                    <div class="step-title"><?= $title ?></div>
                    <div class="step-desc"><?= $desc ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<hr class="section-divider">

<!-- ── CATEGORIES ───────────────────────────────────── -->
<section class="section-pad" id="categories">
    <div class="container">
        <div class="row g-5 align-items-start">
            <div class="col-lg-4">
                <div class="sec-label">Mga Kategorya</div>
                <h2 class="sec-title">Anong Uri ng Insidente?</h2>
                <p class="sec-sub">
                    Ang QC-ALERTO ay sumusuporta sa iba't ibang uri ng insidente.
                    Bawat kategorya ay may nakatalagang ahensya ng QC at SLA deadline.
                </p>
                <a href="/irms/public/report.php"
                   style="display:inline-flex;align-items:center;gap:7px;margin-top:20px;
                          background:var(--qc-red);color:#fff;padding:12px 22px;border-radius:7px;
                          font-size:14px;font-weight:700;text-decoration:none;transition:all 0.15s;">
                    <i class="bi bi-megaphone-fill"></i> Mag-report Ngayon
                </a>
            </div>
            <div class="col-lg-8">
                <div class="row g-2">
                    <?php
                    $cats = [
                        ['bi-fire','#CC0000','rgba(204,0,0,0.07)','Fire Incident','BFP — QC Fire District'],
                        ['bi-water','#0891b2','rgba(8,145,178,0.07)','Flood','QCDRRMO / Maynilad'],
                        ['bi-car-front-fill','#ca8a04','rgba(202,138,4,0.07)','Road Accident','MMDA / PNP-QC'],
                        ['bi-shield-exclamation','#dc2626','rgba(220,38,38,0.07)','Crime / Theft','PNP — Quezon City'],
                        ['bi-hospital-fill','#16a34a','rgba(22,163,74,0.07)','Medical Emergency','QC General Hospital'],
                        ['bi-lightning-fill','#ca8a04','rgba(202,138,4,0.07)','Power Outage','Meralco / QCDRRMO'],
                        ['bi-person-exclamation','#7c3aed','rgba(124,58,237,0.07)','Missing Person','PNP — Quezon City'],
                        ['bi-cone-striped','#0d9488','rgba(13,148,136,0.07)','Infrastructure','DPWH-QC / QCDRRMO'],
                        ['bi-three-dots','#6b7280','rgba(107,114,128,0.07)','Other','Assigned by Admin'],
                    ];
                    foreach ($cats as [$icon,$color,$bg,$name,$resp]):
                    ?>
                    <div class="col-6 col-sm-4">
                        <div class="cat-row">
                            <div class="cat-icon" style="background:<?= $bg ?>;color:<?= $color ?>;">
                                <i class="bi <?= $icon ?>"></i>
                            </div>
                            <div>
                                <div class="cat-name"><?= $name ?></div>
                                <div class="cat-resp"><?= $resp ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<hr class="section-divider">

<!-- ── FAQ ──────────────────────────────────────────── -->
<section class="section-pad" id="faq">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <div class="sec-label">FAQ</div>
                <h2 class="sec-title">Mga Madalas na Tanong</h2>
                <p class="sec-sub">
                    May katanungan pa? Makipag-ugnayan sa aming admin
                    sa pamamagitan ng QC Helpline <strong>122</strong>.
                </p>
            </div>
            <div class="col-lg-8">
                <?php
                $faqs = [
                    ['Libre ba ang QC-ALERTO?',
                     'Oo, 100% libre ang paggamit ng QC-ALERTO. Ito ay isang serbisyo ng Lungsod ng Quezon City para sa lahat ng mamamayan. Walang bayad, walang registration fee.'],
                    ['Kailangan ko bang mag-login para mag-report?',
                     'Hindi. Pwede kang mag-report kahit walang account. Bibigyan ka ng tracking number para ma-monitor ang status. Kung gusto mo ng mas detalyadong history, pwede kang mag-register ng libreng citizen account.'],
                    ['Gaano katagal bago matugunan ang aking report?',
                     'Depende sa severity: Critical — 30 minuto, High — 2 oras, Medium — 24 oras, Low — 72 oras. Kung nalampasan ang deadline, awtomatikong nag-eescalate sa admin ng QC.'],
                    ['Ligtas ba ang aking personal na impormasyon?',
                     'Oo. Ang iyong pangalan, email, at phone number ay hindi ipapakita sa publiko. Ginagamit lamang ito para makontak ka ng aming responders kung kailangan ng karagdagang impormasyon.'],
                    ['Pwede ba akong mag-report kahit hindi QC resident?',
                     'Oo. Pwede kang mag-report ng anumang insidente na nangyayari sa loob ng Quezon City kahit hindi ka residente dito.'],
                    ['Ano ang pagkakaiba ng QC-ALERTO at QC Helpline 122?',
                     'Ang QC Helpline 122 ay para sa verbal na reklamo at tulong na kailangan ng real-time response. Ang QC-ALERTO ay para sa documented incident reports na may photo evidence, location tracking, at audit trail.'],
                    ['Paano ko malalaman kung na-resolve na ang aking report?',
                     'Gamit ang iyong tracking number sa track.php, pwede mong i-check ang status anytime. Kung nagbigay ka ng email, makakatanggap ka rin ng notification sa bawat status update.'],
                ];
                foreach ($faqs as [$q,$a]):
                ?>
                <div class="faq-item" onclick="toggleFaq(this)">
                    <div class="faq-q"><?= $q ?><i class="bi bi-plus-lg"></i></div>
                    <div class="faq-a"><?= $a ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<hr class="section-divider">

<!-- ── CTA ──────────────────────────────────────────── -->
<section class="section-pad">
    <div class="container">
        <div class="cta-block">
            <div class="cta-inner">
                <div style="display:inline-flex;align-items:center;gap:7px;background:rgba(245,166,35,0.15);
                     border:1px solid rgba(245,166,35,0.3);color:var(--qc-gold);font-size:11px;
                     font-weight:700;padding:5px 12px;border-radius:4px;letter-spacing:1px;
                     text-transform:uppercase;margin-bottom:16px;">
                    <i class="bi bi-shield-check-fill"></i> Para sa Mamamayan ng Quezon City
                </div>
                <h2>Mag-report ng Insidente Ngayon</h2>
                <p>Ang bawat report ay nakakatulong sa pagpapabuti ng kaligtasan ng ating lungsod. Libre, mabilis, at epektibo.</p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="/irms/public/report.php" class="hero-btn-primary">
                        <i class="bi bi-megaphone-fill"></i> Mag-report ng Insidente
                    </a>
                    <a href="/irms/public/track.php" class="hero-btn-secondary">
                        <i class="bi bi-search"></i> I-track ang Report
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── FOOTER ────────────────────────────────────────── -->
<footer>
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <!-- Wide logo inverted white for dark footer -->
                <img src="/irms/assets/img/QC_LOGO.png" alt="QC-ALERTO" class="footer-logo mb-3">
                <div class="footer-sub">
                    Quezon City Incident Report & Monitoring System<br>
                    Opisyal na sistema ng Lungsod ng Quezon City.<br>
                    Binuo sa ilalim ng QC e-Services at i-Governance.
                </div>
            </div>
            <div class="col-md-4">
                <div class="footer-h">Mabilis na Links</div>
                <a href="/irms/public/report.php"    class="footer-a">→ Mag-report ng Insidente</a>
                <a href="/irms/public/track.php"     class="footer-a">→ I-track ang Report</a>
                <a href="/irms/citizen/login.php"    class="footer-a">→ Citizen Login</a>
                <a href="/irms/citizen/register.php" class="footer-a">→ Mag-register</a>
                <a href="https://quezoncity.gov.ph" target="_blank" class="footer-a">→ quezoncity.gov.ph ↗</a>
            </div>
            <div class="col-md-4">
                <div class="footer-h">Emergency Hotlines</div>
                <div class="footer-hl"><i class="bi bi-headset" style="color:var(--qc-gold);"></i> QC Helpline: <strong>122</strong></div>
                <div class="footer-hl"><i class="bi bi-telephone-fill" style="color:#ef4444;"></i> National Emergency: <strong>911</strong></div>
                <div class="footer-hl"><i class="bi bi-fire" style="color:#f59e0b;"></i> BFP-QC: <strong>(02) 8928-1900</strong></div>
                <div class="footer-hl"><i class="bi bi-shield-fill" style="color:#3b82f6;"></i> PNP-QC: <strong>(02) 8924-4626</strong></div>
                <div class="footer-hl"><i class="bi bi-heart-pulse-fill" style="color:#10b981;"></i> QCDRRMO: <strong>(02) 8988-4242</strong></div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-copy">
                © <?= date('Y') ?> QC-ALERTO — Quezon City Incident Report & Monitoring System.
            </div>
            <a href="/irms/portal/login.php" class="footer-staff">
                <i class="bi bi-shield-lock"></i> Staff Portal
            </a>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openMobile() {
    document.getElementById('mobDrawer').classList.add('open');
    document.getElementById('mobOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeMobile() {
    document.getElementById('mobDrawer').classList.remove('open');
    document.getElementById('mobOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) { if(e.key==='Escape') closeMobile(); });

function toggleFaq(card) {
    var a    = card.querySelector('.faq-a');
    var icon = card.querySelector('.faq-q i');
    var open = card.classList.contains('open');
    document.querySelectorAll('.faq-item.open').forEach(function(el) {
        el.classList.remove('open');
        el.querySelector('.faq-a').classList.remove('open');
        el.querySelector('.faq-q i').className = 'bi bi-plus-lg';
    });
    if (!open) {
        card.classList.add('open');
        a.classList.add('open');
        icon.className = 'bi bi-dash-lg';
    }
}
</script>
</body>
</html>