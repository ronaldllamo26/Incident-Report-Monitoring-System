<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('citizen');
require_once __DIR__ . '/../config/db.php';

$cats  = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$error = $_GET['error'] ?? '';

// ── DUPLICATE WARNING DATA ─────────────────────────────
$showDuplicate = isset($_GET['duplicate']) && $_GET['duplicate'] == '1';
$dupId         = htmlspecialchars($_GET['dup_id']       ?? '');
$dupTracking   = htmlspecialchars($_GET['dup_tracking'] ?? '');
$dupTitle      = htmlspecialchars($_GET['dup_title']    ?? '');
$dupStatus     = htmlspecialchars($_GET['dup_status']   ?? '');
$dupLocation   = htmlspecialchars($_GET['dup_location'] ?? '');
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mag-report ng Insidente — QC-ALERTO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" rel="stylesheet">
    <style>
        #map {
            height: 360px;
            border-radius: 8px;
            border: 2px solid #003DA5;
            z-index: 0;
            background: #0f172a;
        }
        @media (max-width: 768px) { #map { height: 280px; } }
        .map-instruction { font-size: 12px; color: #6c757d; margin-top: 6px; }
        .preview-img { width: 80px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid #dee2e6; }
        #image-preview { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        #search-results { position: absolute; width: 100%; z-index: 9999; }
        .search-wrapper { position: relative; }
        .qc-outside-alert {
            display: none; align-items: center; gap: 8px;
            background: #fff3cd; border: 1px solid #ffc107;
            border-left: 4px solid #CC0000; border-radius: 6px;
            padding: 8px 12px; font-size: 12px; color: #856404; margin-top: 8px;
        }
        .qc-outside-alert.show { display: flex; }
        .glass-controls {
            position: absolute; bottom: 20px; right: 20px; z-index: 1000;
            background: rgba(255,255,255,0.25); backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.18); border-radius: 12px;
            padding: 8px; display: flex; flex-direction: column; gap: 6px;
            box-shadow: 0 8px 32px 0 rgba(0,0,0,0.2);
        }
        .glass-btn {
            background: rgba(255,255,255,0.85); border: none; border-radius: 8px;
            padding: 8px 12px; font-size: 11px; font-weight: 600; color: #333;
            cursor: pointer; transition: all 0.2s ease;
            display: flex; align-items: center; gap: 6px;
        }
        .glass-btn:hover { background: #fff; transform: translateY(-1px); }
        .glass-btn.active { background: #003DA5; color: #fff; }
        #map-wrapper {
            position: relative; border-radius: 10px; overflow: hidden;
            border: 2px solid #003DA5; box-shadow: 0 4px 16px rgba(0,61,165,0.15);
        }
        #qc-badge {
            position: absolute; top: 10px; left: 50%; transform: translateX(-50%);
            z-index: 1000; background: rgba(0,45,122,0.92); color: #F5A623;
            font-size: 11px; font-weight: 700; padding: 5px 14px; border-radius: 20px;
            letter-spacing: 0.8px; border: 1px solid rgba(245,166,35,0.4);
            white-space: nowrap; pointer-events: none;
        }

        /* ── DUPLICATE WARNING STYLES ───────────────────── */
        .dup-warning-card {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .dup-warning-header {
            display: flex; align-items: center; gap: 10px; margin-bottom: 14px;
        }
        .dup-warning-icon {
            width: 36px; height: 36px; background: #fef3c7; border-radius: 8px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .dup-incident-box {
            background: #fff; border: 1px solid #fde68a;
            border-radius: 8px; padding: 14px 16px; margin-bottom: 14px;
        }
        .dup-status-badge {
            display: inline-block; font-size: 11px; font-weight: 600;
            padding: 2px 8px; border-radius: 4px;
        }

        /* ── REALTIME DUPLICATE WARNING ─────────────────── */
        #realtime-dup-warning {
            background: #fffbeb; border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b; border-radius: 8px;
            padding: 10px 14px; margin-top: 8px; font-size: 13px;
            display: none;
        }
        #realtime-dup-warning.show { display: block; }
    </style>
</head>
<body class="bg-light">

<!-- Navbar -->
<nav class="navbar navbar-dark" style="background:#002D7A;border-bottom:3px solid #F5A623;">
    <div class="container">
        <a class="navbar-brand fw-semibold d-flex align-items-center gap-2"
           href="/irms/citizen/dashboard.php">
            <img src="/irms/assets/img/QC_LOGO_CIRCLE.png"
                 style="height:32px;width:32px;object-fit:contain;" alt="">
            QC-ALERTO
        </a>
        <a href="/irms/citizen/dashboard.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>
</nav>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="mb-4">
                <h5 class="fw-semibold mb-0">Mag-report ng Insidente</h5>
                <p class="text-muted small">Punan ang form at i-pin ang lokasyon sa mapa ng Quezon City.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- ── DUPLICATE WARNING CARD ───────────────── -->
            <?php if ($showDuplicate && $dupId): ?>
            <div class="dup-warning-card">
                <div class="dup-warning-header">
                    <div class="dup-warning-icon">
                        <i class="bi bi-exclamation-triangle-fill" style="color:#d97706;font-size:16px;"></i>
                    </div>
                    <div>
                        <div style="font-size:14px;font-weight:700;color:#92400e;">
                            May katulad na report na nafile ngayon
                        </div>
                        <div style="font-size:12px;color:#b45309;">
                            May similar na insidente ang naireport sa parehong lugar kamakailan lang
                        </div>
                    </div>
                </div>

                <!-- Existing incident info -->
                <div class="dup-incident-box">
                    <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">
                        Existing Report
                    </div>
                    <div style="font-size:14px;font-weight:700;color:#111827;margin-bottom:8px;">
                        <?= $dupTitle ?>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span style="font-size:11px;background:#f3f4f6;color:#6b7280;padding:3px 8px;border-radius:4px;font-weight:500;">
                            <i class="bi bi-hash"></i> <?= $dupTracking ?>
                        </span>
                        <?php
                        $statusColors = [
                            'pending'     => 'background:#fef3c7;color:#d97706;',
                            'in_progress' => 'background:#dbeafe;color:#1d4ed8;',
                            'resolved'    => 'background:#dcfce7;color:#16a34a;',
                        ];
                        $sc = $statusColors[$dupStatus] ?? 'background:#f3f4f6;color:#6b7280;';
                        ?>
                        <span class="dup-status-badge" style="<?= $sc ?>">
                            <?= ucfirst(str_replace('_', ' ', $dupStatus)) ?>
                        </span>
                    </div>
                    <div style="font-size:12px;color:#6b7280;">
                        <i class="bi bi-geo-alt me-1"></i><?= $dupLocation ?>
                    </div>
                </div>

                <div style="font-size:13px;color:#78350f;margin-bottom:14px;">
                    <strong>Gusto mo pa ring mag-submit ng bagong report?</strong><br>
                    Kung iba ang iyong insidente sa nabanggit sa itaas, pwede kang mag-proceed.
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="/irms/public/track.php?tracking=<?= urlencode($dupTracking) ?>"
                       class="btn btn-sm" style="background:#003DA5;color:#fff;font-weight:600;">
                        <i class="bi bi-search me-1"></i> I-track ang Existing Report
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-secondary fw-medium"
                            onclick="proceedDespiteDuplicate()">
                        <i class="bi bi-arrow-right me-1"></i> Iba ito — Ituloy pa rin
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form action="/irms/controllers/IncidentController.php?action=submit"
                          method="POST" enctype="multipart/form-data" id="report-form">

                        <!-- Title -->
                        <div class="mb-3">
                            <label class="form-label small fw-medium">
                                Pamagat ng Insidente <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="title" class="form-control"
                                placeholder="Maikling paglalarawan ng insidente" required>
                        </div>

                        <!-- Category + Severity -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">
                                    Kategorya <span class="text-danger">*</span>
                                </label>
                                <!-- DAGDAG: onchange="checkDuplicate()" -->
                                <select name="category_id" class="form-select"
                                        onchange="checkDuplicate()" required>
                                    <option value="">-- Pumili ng kategorya --</option>
                                    <?php foreach ($cats as $c): ?>
                                        <option value="<?= $c['id'] ?>">
                                            <?= htmlspecialchars($c['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">
                                    Kalubhaan <span class="text-danger">*</span>
                                </label>
                                <select name="severity" class="form-select" required>
                                    <option value="">-- Pumili --</option>
                                    <option value="low">Low — Hindi masyadong urgent</option>
                                    <option value="medium">Medium — Kailangan ng aksyon</option>
                                    <option value="high">High — Nakakaapekto sa marami</option>
                                    <option value="critical">Critical — Emergency!</option>
                                </select>
                            </div>
                        </div>

                        <!-- Realtime duplicate warning — lalabas dito -->
                        <div id="realtime-dup-warning"></div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label class="form-label small fw-medium">
                                Detalyadong Paglalarawan <span class="text-danger">*</span>
                            </label>
                            <textarea name="description" class="form-control" rows="4"
                                placeholder="Ilarawan ang nangyari nang detalyado..."
                                required></textarea>
                        </div>

                        <!-- Location search -->
                        <div class="mb-3">
                            <label class="form-label small fw-medium">
                                Hanapin ang Lokasyon sa Quezon City <span class="text-danger">*</span>
                            </label>
                            <div class="search-wrapper">
                                <div class="input-group">
                                    <input type="text" id="search-input" class="form-control"
                                        placeholder="I-type ang barangay, street, o landmark sa QC...">
                                    <button type="button" class="btn btn-primary" onclick="handleSearch()">
                                        <i class="bi bi-search"></i> Hanapin
                                    </button>
                                </div>
                                <div id="search-results" class="list-group shadow-sm"></div>
                            </div>
                            <div class="qc-outside-alert" id="search-outside-alert">
                                <i class="bi bi-exclamation-triangle-fill" style="color:#CC0000;flex-shrink:0;"></i>
                                <span><strong>Nasa labas ng Quezon City</strong> ang hinahanap mo.</span>
                            </div>
                            <p class="map-instruction">
                                <i class="bi bi-info-circle me-1"></i>
                                Quezon City area <strong>lang</strong> ang makikita at mapipili sa mapa.
                            </p>
                        </div>

                        <!-- Confirmed Address -->
                        <div class="mb-3">
                            <label class="form-label small fw-medium">
                                Kumpirmahin ang Address <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="location" id="location-input"
                                class="form-control"
                                placeholder="Awtomatikong mapupuno pagkatapos mag-pin..." required>
                        </div>

                        <!-- Map -->
                        <div class="mb-3">
                            <label class="form-label small fw-medium d-flex align-items-center gap-2 mb-2">
                                I-pin ang Eksaktong Lokasyon
                                <span class="badge" style="background:#003DA5;color:#F5A623;font-size:10px;font-weight:700;">
                                    QUEZON CITY ONLY
                                </span>
                            </label>
                            <div id="map-wrapper">
                                <div id="qc-badge">📍 QUEZON CITY — QC-ALERTO MAP</div>
                                <div class="glass-controls">
                                    <button type="button" class="glass-btn active" onclick="window.changeMapStyle('light', this)">
                                        <i class="bi bi-map"></i> Street
                                    </button>
                                    <button type="button" class="glass-btn" onclick="window.changeMapStyle('dark', this)">
                                        <i class="bi bi-moon-stars"></i> Dark
                                    </button>
                                    <button type="button" class="glass-btn" onclick="window.changeMapStyle('satellite', this)">
                                        <i class="bi bi-globe"></i> Satellite
                                    </button>
                                </div>
                                <div id="map"></div>
                            </div>
                            <div class="qc-outside-alert" id="map-outside-alert">
                                <i class="bi bi-exclamation-triangle-fill" style="color:#CC0000;flex-shrink:0;"></i>
                                <span><strong>Hindi pwede</strong> — nasa labas ng Quezon City. I-click ang loob ng QC para mag-pin.</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 mt-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        onclick="handleMyLocation(event)">
                                    <i class="bi bi-geo-alt me-1"></i> Gamitin ang aking lokasyon
                                </button>
                                <span id="pin-status" class="small"></span>
                            </div>
                            <input type="hidden" name="latitude"  id="latitude">
                            <input type="hidden" name="longitude" id="longitude">
                        </div>

                        <!-- Photo Upload -->
                        <div class="mb-4">
                            <label class="form-label small fw-medium">
                                Mag-upload ng Larawan
                                <span class="text-muted fw-normal">(optional, max 5 photos)</span>
                            </label>
                            <input type="file" name="photos[]" id="photo-input"
                                class="form-control" accept="image/*"
                                multiple onchange="previewImages(event)">
                            <div id="image-preview"></div>
                        </div>

                        <!-- Buttons -->
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-1"></i> I-submit ang Report
                            </button>
                            <a href="/irms/citizen/dashboard.php" class="btn btn-outline-secondary">
                                Kanselahin
                            </a>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ================= MAP INIT =================
var map = L.map('map', {
    center: [14.6760, 121.0437],
    zoom: 12, minZoom: 12, maxZoom: 18,
    maxBoundsViscosity: 1.0
});

window.mapLayers = {
    'light':     L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',   { maxZoom:19, attribution:'© CARTO' }),
    'dark':      L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',    { maxZoom:19, attribution:'© CARTO' }),
    'satellite': L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom:19, attribution:'© Esri' })
};
var currentMapLayer = window.mapLayers['light'].addTo(map);
var mapMask;

window.changeMapStyle = function(type, btnObj) {
    document.querySelectorAll('.glass-btn').forEach(b => b.classList.remove('active'));
    btnObj.classList.add('active');
    map.removeLayer(currentMapLayer);
    currentMapLayer = window.mapLayers[type].addTo(map);
    if (mapMask) {
        var colors = { dark: ['#0f172a', 0.85], satellite: ['#000000', 0.70], light: ['#f1f5f9', 0.85] };
        mapMask.setStyle({ fillColor: colors[type][0], fillOpacity: colors[type][1] });
    }
};

var qcBoundaryLayer, qcFeature, marker;

fetch('/irms/qc_boundary.geojson')
    .then(r => r.json())
    .then(data => {
        qcFeature = data.features[0];
        qcBoundaryLayer = L.geoJSON(qcFeature, {
            style: { color:'#E63946', weight:4, fillOpacity:0 }
        }).addTo(map);
        var bounds = qcBoundaryLayer.getBounds();
        map.fitBounds(bounds);
        map.setMaxBounds(bounds);

        var coords = qcFeature.geometry.type === 'MultiPolygon'
            ? qcFeature.geometry.coordinates[0][0]
            : qcFeature.geometry.coordinates[0];
        var qcLatLngs = coords.map(c => [c[1], c[0]]);
        var world = [[-90,-180],[-90,180],[90,180],[90,-180],[-90,-180]];
        mapMask = L.polygon([world, qcLatLngs], {
            fillColor:'#f1f5f9', fillOpacity:0.85, stroke:false, interactive:false
        }).addTo(map);

        fetch('/irms/qc_barangays.geojson').then(res => {
            if (!res.ok) throw new Error('no barangays');
            return res.json();
        }).then(barangayData => {
            L.geoJSON(barangayData, {
                style: { color:'#0056b3', weight:1.5, fillColor:'#3b82f6', fillOpacity:0.1 },
                onEachFeature: function(feature, layer) {
                    layer.on('mouseover', () => layer.setStyle({ fillOpacity:0.3, weight:2 }));
                    layer.on('mouseout',  () => layer.setStyle({ fillOpacity:0.1, weight:1.5 }));
                    if (feature.properties && feature.properties.name) {
                        layer.bindTooltip(feature.properties.name, { className:'barangay-label', sticky:true });
                    }
                }
            }).addTo(map);
        }).catch(() => {});
    })
    .catch(err => console.error('Error loading QC data:', err));

// ================= POINT CHECKER =================
window.checkInsideQC = function(lat, lng) {
    if (!qcFeature) return false;
    var coords = qcFeature.geometry.type === 'MultiPolygon'
        ? qcFeature.geometry.coordinates[0][0]
        : qcFeature.geometry.coordinates[0];
    var x = lng, y = lat, inside = false;
    for (var i = 0, j = coords.length-1; i < coords.length; j = i++) {
        var xi=coords[i][0], yi=coords[i][1], xj=coords[j][0], yj=coords[j][1];
        if (((yi>y) !== (yj>y)) && (x < (xj-xi)*(y-yi)/(yj-yi)+xi)) inside = !inside;
    }
    return inside;
};

// ================= CLICK + PIN =================
function pinLocation(lat, lng, label) {
    if (marker) map.removeLayer(marker);
    marker = L.marker([lat, lng]).addTo(map);
    document.getElementById('latitude').value  = lat;
    document.getElementById('longitude').value = lng;
    document.getElementById('location-input').value = label || (lat.toFixed(5) + ', ' + lng.toFixed(5));
    document.getElementById('pin-status').innerHTML =
        '<span class="text-success"><i class="bi bi-check-circle-fill"></i> Naka-pin na po!</span>';
    // ── DUPLICATE CHECK after pinning ──
    checkDuplicate();
}

map.on('click', function(e) {
    if (!qcFeature) return;
    if (!window.checkInsideQC(e.latlng.lat, e.latlng.lng)) {
        var a = document.getElementById('map-outside-alert');
        a.classList.add('show');
        setTimeout(() => a.classList.remove('show'), 3000);
        return;
    }
    document.getElementById('map-outside-alert').classList.remove('show');
    pinLocation(e.latlng.lat, e.latlng.lng);
});

// ================= SEARCH =================
function handleSearch() {
    var query = document.getElementById('search-input').value.trim();
    if (!query) return;
    var resultsDiv = document.getElementById('search-results');
    resultsDiv.innerHTML = '<div class="list-group-item text-muted">Naghahanap sa QC...</div>';

    function fetchLocation(q, isFallback) {
        var url = 'https://nominatim.openstreetmap.org/search?format=json&q='
            + encodeURIComponent(q + ', Quezon City, Philippines')
            + '&viewbox=120.98,14.78,121.15,14.59&bounded=1&limit=5';
        fetch(url).then(r => r.json()).then(data => {
            resultsDiv.innerHTML = '';
            if (!data.length) {
                if (!isFallback && query.match(/^[0-9]+\s+/)) {
                    fetchLocation(query.replace(/^[0-9]+\s+/, ''), true);
                } else {
                    resultsDiv.innerHTML = '<div class="list-group-item text-danger">Walang nahanap sa QC.</div>';
                }
                return;
            }
            data.forEach(item => {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action py-2';
                btn.innerHTML = '<strong>' + item.display_name.split(',')[0] + '</strong><br>'
                    + '<small class="text-muted">' + item.display_name + '</small>';
                btn.onclick = function() {
                    var lat = parseFloat(item.lat), lon = parseFloat(item.lon);
                    if (!window.checkInsideQC(lat, lon)) {
                        alert('Nasa labas ng QC ang adres na ito.');
                        return;
                    }
                    map.setView([lat, lon], 17);
                    pinLocation(lat, lon, item.display_name);
                    document.getElementById('search-input').value = item.display_name.split(',')[0];
                    resultsDiv.innerHTML = '';
                };
                resultsDiv.appendChild(btn);
            });
        }).catch(() => {
            resultsDiv.innerHTML = '<div class="list-group-item text-danger">Error. Subukan ulit.</div>';
        });
    }
    fetchLocation(query, false);
}

document.getElementById('search-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); handleSearch(); }
});
document.addEventListener('click', function(e) {
    var ri = document.getElementById('search-results');
    var si = document.getElementById('search-input');
    if (ri && si && !ri.contains(e.target) && e.target !== si) ri.innerHTML = '';
});

// ================= MY LOCATION =================
function handleMyLocation(event) {
    if (!navigator.geolocation) { alert('Hindi sinusuportahan ng browser mo ang geolocation.'); return; }
    var btn = event.currentTarget;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Kumuha ng lokasyon...';
    navigator.geolocation.getCurrentPosition(function(pos) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-geo-alt me-1"></i> Gamitin ang aking lokasyon';
        var lat = pos.coords.latitude, lng = pos.coords.longitude;
        if (!window.checkInsideQC(lat, lng)) {
            document.getElementById('map-outside-alert').classList.add('show');
            return;
        }
        map.setView([lat, lng], 17);
        pinLocation(lat, lng);
    }, function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-geo-alt me-1"></i> Gamitin ang aking lokasyon';
        alert('Hindi makuha ang lokasyon. I-allow ang location permission.');
    }, { enableHighAccuracy:true, timeout:10000 });
}

// ================= IMAGE PREVIEW =================
function previewImages(event) {
    var preview = document.getElementById('image-preview');
    preview.innerHTML = '';
    var files = event.target.files;
    if (files.length > 5) { alert('Maximum 5 photos lang.'); event.target.value = ''; return; }
    Array.from(files).forEach(function(file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.createElement('img');
            img.src = e.target.result; img.className = 'preview-img';
            preview.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
}

// ═══════════════════════════════════════════════════════
//  DUPLICATE DETECTION SYSTEM
// ═══════════════════════════════════════════════════════

var dupCheckTimer = null;

function checkDuplicate() {
    var lat = document.getElementById('latitude').value;
    var lng = document.getElementById('longitude').value;
    var cat = document.querySelector('[name="category_id"]').value;

    // Need both location AND category para mag-check
    if (!lat || !lng || !cat) return;

    clearTimeout(dupCheckTimer);
    dupCheckTimer = setTimeout(function() {
        fetch('/irms/ajax/check_duplicate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'lat=' + lat + '&lng=' + lng + '&category=' + cat
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var warnEl = document.getElementById('realtime-dup-warning');
            if (!data.duplicate) {
                // Walang duplicate — hide warning
                warnEl.classList.remove('show');
                warnEl.innerHTML = '';
                return;
            }
            // May duplicate — show inline warning
            var inc = data.incident;
            warnEl.innerHTML =
                '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">' +
                '<i class="bi bi-exclamation-triangle-fill" style="color:#d97706;font-size:16px;flex-shrink:0;"></i>' +
                '<strong style="color:#92400e;">May katulad na report na — ' + inc.distance_meters + 'm ang layo</strong>' +
                '</div>' +
                '<div style="color:#78350f;margin-bottom:4px;">' +
                '<strong>' + inc.title + '</strong> <span style="font-size:11px;background:#fef3c7;color:#d97706;padding:2px 6px;border-radius:3px;font-weight:600;">' + inc.status.replace('_',' ') + '</span>' +
                '</div>' +
                '<div style="font-size:12px;color:#92400e;margin-bottom:8px;">' +
                '<i class="bi bi-hash"></i> ' + inc.tracking_number + ' &nbsp;|&nbsp; ' +
                '<i class="bi bi-clock"></i> ' + inc.created_at +
                '</div>' +
                '<div style="font-size:12px;color:#78350f;">' +
                'Baka duplicate ito. I-check muna bago mag-submit.' +
                '</div>';
            warnEl.classList.add('show');
        })
        .catch(function() {}); // silent — hindi mag-block ng form
    }, 800);
}

// Proceed despite duplicate — adds hidden input
function proceedDespiteDuplicate() {
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'force_proceed';
    input.value = '1';
    document.getElementById('report-form').appendChild(input);

    // Hide warning card
    var card = document.querySelector('.dup-warning-card');
    if (card) card.remove();

    // Show confirmation message
    var notice = document.createElement('div');
    notice.className = 'alert alert-info py-2 small mb-3';
    notice.innerHTML = '<i class="bi bi-info-circle me-1"></i>' +
        'Okay. Ita-tag ang iyong report bilang possible duplicate. I-submit na ang form.';
    document.querySelector('.card-body').prepend(notice);
    notice.scrollIntoView({ behavior: 'smooth' });
}

// ================= FORM VALIDATION =================
document.getElementById('report-form').addEventListener('submit', function(e) {
    var lat = document.getElementById('latitude').value;
    var lng = document.getElementById('longitude').value;
    if (!lat || !lng) {
        e.preventDefault();
        alert('I-pin muna ang lokasyon sa mapa ng Quezon City.');
        return;
    }
    if (window.checkInsideQC && !window.checkInsideQC(parseFloat(lat), parseFloat(lng))) {
        e.preventDefault();
        document.getElementById('map-outside-alert').classList.add('show');
        alert('Hindi pwedeng mag-submit — nasa labas ng Quezon City ang lokasyon.');
    }
});
</script>
</body>
</html>