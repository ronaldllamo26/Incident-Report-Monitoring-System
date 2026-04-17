<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline — QC-ALERTO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #111827;
            color: #fff;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-family: 'Inter', sans-serif;
        }
        .offline-box {
            max-width: 400px;
            padding: 30px;
        }
        .logo {
            width: 100px;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 15px rgba(59, 130, 246, 0.5));
        }
        .loader {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255,255,255,0.1);
            border-top: 3px solid #3b82f6;
            border-radius: 50%;
            display: inline-block;
            animation: spin 1s linear infinite;
            margin: 20px 0;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .btn-retry {
            background: #3b82f6;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 100px;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s;
        }
        .btn-retry:hover { background: #2563eb; transform: scale(1.05); }
    </style>
</head>
<body>
    <div class="offline-box">
        <img src="/irms/assets/img/QC_LOGO_CIRCLE.png" class="logo" alt="Logo">
        <h3 class="fw-bold">Nawalan ng Koneksyon</h3>
        <p class="text-muted small">Hindi maabot ang Command Center. Maaaring nawala ang internet o nag-expire ang Ngrok tunnel mo.</p>
        
        <div class="loader"></div>
        
        <br>
        <button class="btn-retry" onclick="window.location.reload()">
            <i class="bi bi-arrow-clockwise me-1"></i> Subukan Muli
        </button>
        
        <div class="mt-4 small text-muted">
            <i class="bi bi-shield-lock me-1"></i> QC-ALERTO Security Mode Active
        </div>
    </div>
</body>
</html>
