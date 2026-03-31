<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirectByRole();
} else {
    // Landing page na may dalawang options
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IRMS — Incident Report & Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; }
        .hero { background: #1e293b; min-height: 100vh;
                display: flex; align-items: center; }
        .feature-icon { width: 48px; height: 48px; border-radius: 12px;
                        display: flex; align-items: center; justify-content: center;
                        font-size: 20px; margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="hero">
    <div class="container">
        <div class="row align-items-center g-5">

            <!-- Left: Branding -->
            <div class="col-lg-6 text-white">
                <div class="mb-4">
                    <i class="bi bi-shield-check fs-1 text-success"></i>
                </div>
                <h1 class="fw-bold mb-3">
                    Incident Report &<br>Monitoring System
                </h1>
                <p class="text-secondary fs-5 mb-4">
                    Mag-report ng mga insidente sa iyong komunidad.
                    Mabilis, libre, at epektibo.
                </p>

                <!-- Features -->
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="d-flex gap-3 align-items-start">
                            <div class="feature-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-geo-alt"></i>
                            </div>
                            <div>
                                <div class="small fw-medium">Map-based reporting</div>
                                <div class="text-secondary" style="font-size:12px;">
                                    I-pin ang eksaktong lokasyon
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex gap-3 align-items-start">
                            <div class="feature-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-lightning"></i>
                            </div>
                            <div>
                                <div class="small fw-medium">Auto-assign responder</div>
                                <div class="text-secondary" style="font-size:12px;">
                                    Awtomatikong napupunta sa tamang ahensya
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex gap-3 align-items-start">
                            <div class="feature-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-search"></i>
                            </div>
                            <div>
                                <div class="small fw-medium">Real-time tracking</div>
                                <div class="text-secondary" style="font-size:12px;">
                                    I-monitor ang status ng report
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex gap-3 align-items-start">
                            <div class="feature-icon bg-info bg-opacity-10 text-info">
                                <i class="bi bi-person-slash"></i>
                            </div>
                            <div>
                                <div class="small fw-medium">Walang login required</div>
                                <div class="text-secondary" style="font-size:12px;">
                                    Pwedeng mag-report kahit anonymous
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Action cards -->
            <div class="col-lg-5 offset-lg-1">
                <div class="card border-0 shadow-lg mb-3">
                    <div class="card-body p-4">
                        <div class="text-center mb-3">
                            <i class="bi bi-exclamation-triangle-fill text-warning fs-2"></i>
                            <h5 class="fw-semibold mt-2 mb-1">May Insidente?</h5>
                            <p class="text-muted small">
                                Mag-report ngayon — hindi kailangan ng account.
                            </p>
                        </div>
                        <a href="/irms/public/report.php"
                           class="btn btn-danger w-100 mb-2">
                            <i class="bi bi-megaphone me-1"></i>
                            Mag-report ng Insidente
                        </a>
                        <a href="/irms/public/track.php"
                           class="btn btn-outline-secondary w-100">
                            <i class="bi bi-search me-1"></i>
                            I-track ang Report
                        </a>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <p class="text-muted small text-center mb-2">
                            Para sa mga admin at responders
                        </p>
                        <a href="/irms/portal/login.php"
                           class="btn btn-outline-primary w-100 btn-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i>
                            Login sa System
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php } ?>