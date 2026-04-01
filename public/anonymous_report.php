<?php
require_once __DIR__ . '/../includes/auth.php';  // para sa citizen/ at portal/
require_once __DIR__ . '/../includes/auth.php'; // para sa portal/admin/ at portal/responder/
require_once __DIR__ . '/../config/db.php';

// Pag naka-login na, i-redirect sa normal report form
if (isLoggedIn()) {
    header('Location: /irms/citizen/report.php');
    exit;
}

$cats  = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mag-report ng Insidente — IRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; }
        .brand-bar { background: #1e293b; padding: 14px 0; }
        #map { height: 320px; border-radius: 8px; border: 1px solid #dee2e6; z-index: 0; }
        .map-instruction { font-size: 12px; color: #6c757d; margin-top: 6px; }
        .preview-img { width: 80px; height: 80px; object-fit: cover;
                       border-radius: 6px; border: 1px solid #dee2e6; }
        #image-preview { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        #search-results { position: absolute; width: 100%; z-index: 9999; }
        .search-wrapper { position: relative; }
        .tracking-card { background: #1e293b; color: #fff;
                         border-radius: 12px; padding: 24px; text-align: center; }
        .tracking-number { font-size: 28px; font-weight: 700;
                           letter-spacing: 4px; color: #34d399; font-family: monospace; }
    </style>
</head>
<body>

<!-- Brand bar -->
<div class="brand-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="text-white fw-semibold">
            <i class="bi bi-shield-check me-2"></i>IRMS
            <span class="text-secondary ms-2" style="font-size:12px;">
                Incident Report & Monitoring System
            </span>
        </div>
        <div class="d-flex gap-2">
            <a href="/irms/public/track.php"
               class="btn btn-outline-light btn-sm">
                <i class="bi bi-search me-1"></i> I-track ang Report
            </a>
            <a href="/irms/citizen/login.php"
               class="btn btn-light btn-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login
            </a>
        </div>
    </div>
</div>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <!-- Info banner -->
            <div class="alert alert-info d-flex gap-2 py-2 mb-4">
                <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                <div class="small">
                    <strong>Hindi kailangan mag-login para mag-report.</strong>
                    Pagkatapos mag-submit, bibigyan ka ng <strong>tracking number</strong>
                    para ma-monitor ang status ng iyong report kahit walang account.
                </div>
            </div>

            <div class="mb-4">
                <h5 class="fw-semibold mb-0">Mag-report ng Insidente</h5>
                <p class="text-muted small">
                    Punan ang form nang tama at detalyado para mas mabilis na matugunan.
                </p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form action="/irms/controllers/AnonReportController.php"
                          method="POST" enctype="multipart/form-data" id="report-form">

                        <!-- Reporter info (optional) -->
                        <div class="mb-4 p-3 bg-light rounded">
                            <p class="small fw-medium mb-1">
                                <i class="bi bi-person me-1"></i>
                                Iyong Impormasyon
                                <span class="text-muted fw-normal">(optional pero recommended)</span>
                            </p>
                            <p class="text-muted small mb-3">
                                Para makontak ka namin kung kailangan ng additional info.
                                Hindi ito ipapakita sa publiko.
                            </p>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <input type="text" name="anon_name" class="form-control form-control-sm"
                                           placeholder="Pangalan (optional)">
                                </div>
                                <div class="col-md-4">
                                    <input type="email" name="anon_email" class="form-control form-control-sm"
                                           placeholder="Email (optional)">
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="anon_phone" class="form-control form-control-sm"
                                           placeholder="Phone (optional)">
                                </div>
                            </div>
                        </div>

                        <!-- Incident details -->
                        <div class="mb-3">
                            <label class="form-label small fw-medium">
                                Pamagat ng Insidente <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="title" class="form-control"
                                placeholder="Maikling paglalarawan ng insidente" required>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">
                                    Kategorya <span class="text-danger">*</span>
                                </label>
                                <select name="category_id" class="form-select" required>
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

                        <div class="mb-3">
                            <label class="form-label small fw-medium">
                                Detalyadong Paglalarawan <span class="text-danger">*</span>
                            </label>
                            <textarea name="description" class="form-control" rows="4"
                                placeholder="Ilarawan ang nangyari nang detalyado — ano, sino, kailan, paano..." required></textarea>
                        </div>

                        <!-- Location search -->
                        <div class="mb-3">
                            <label class="form-label small fw-medium">
                                Hanapin ang Lokasyon <span class="text-danger">*</span>
                            </label>
                            <div class="search-wrapper">
                                <div class="input-group">
                                    <input type="text" id="search-input" class="form-control"
                                        placeholder="I-type ang address, barangay, o landmark...">
                                    <button type="button" class="btn btn-primary"
                                            onclick="searchLocation()">
                                        <i class="bi bi-search"></i> Hanapin
                                    </button>
                                </div>
                                <div id="search-results" class="list-group shadow-sm"></div>
                            </div>
                            <p class="map-instruction">
                                <i class="bi bi-info-circle me-1"></i>
                                I-type ang lugar tapos piliin sa listahan —
                                o direktang i-click ang mapa para mag-pin.
                            </p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-medium">
                                Kumpirmahin ang Address <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="location" id="location-input"
                                class="form-control"
                                placeholder="Awtomatikong mapupuno pagkatapos mag-pin..." required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-medium">
                                I-pin ang Eksaktong Lokasyon
                            </label>
                            <div id="map"></div>
                            <div class="d-flex align-items-center gap-2 mt-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        onclick="useMyLocation(event)">
                                    <i class="bi bi-geo-alt me-1"></i> Gamitin ang aking lokasyon
                                </button>
                                <span id="pin-status" class="small text-muted"></span>
                            </div>
                            <input type="hidden" name="latitude"  id="latitude">
                            <input type="hidden" name="longitude" id="longitude">
                        </div>

                        <!-- Photo upload -->
                        <div class="mb-4">
                            <label class="form-label small fw-medium">
                                Mag-upload ng Larawan
                                <span class="text-muted fw-normal">(optional, max 5 photos)</span>
                            </label>
                            <input type="file" name="photos[]" class="form-control"
                                accept="image/*" multiple onchange="previewImages(event)">
                            <div id="image-preview"></div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-1"></i> I-submit ang Report
                            </button>
                            <a href="/irms/index.php" class="btn btn-outline-secondary">
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
var map = L.map('map').setView([14.5995, 120.9842], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors', maxZoom: 19
}).addTo(map);

var marker = null;

function placeMarker(lat, lng, label) {
    if (marker) map.removeLayer(marker);
    marker = L.marker([lat, lng], { draggable: true }).addTo(map);
    marker.bindPopup(label || 'Pinned location').openPopup();
    document.getElementById('latitude').value  = lat.toFixed(8);
    document.getElementById('longitude').value = lng.toFixed(8);
    document.getElementById('pin-status').innerHTML =
        '<i class="bi bi-check-circle-fill text-success me-1"></i>Na-pin na ang lokasyon.';
    if (label) document.getElementById('location-input').value = label;
    marker.on('dragend', function(e) {
        var pos = e.target.getLatLng();
        document.getElementById('latitude').value  = pos.lat.toFixed(8);
        document.getElementById('longitude').value = pos.lng.toFixed(8);
        reverseGeocode(pos.lat, pos.lng);
    });
}

map.on('click', function(e) {
    placeMarker(e.latlng.lat, e.latlng.lng);
    reverseGeocode(e.latlng.lat, e.latlng.lng);
});

var searchTimeout = null;
var searchInput   = document.getElementById('search-input');
var resultsBox    = document.getElementById('search-results');

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    var q = this.value.trim();
    if (q.length < 3) { resultsBox.innerHTML = ''; return; }
    searchTimeout = setTimeout(function() { doSearch(q); }, 400);
});

searchInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); searchLocation(); }
});

function searchLocation() {
    var q = searchInput.value.trim();
    if (!q) return;
    doSearch(q);
}

function doSearch(query) {
    resultsBox.innerHTML =
        '<div class="list-group-item text-muted small">' +
        '<span class="spinner-border spinner-border-sm me-2"></span>Naghahanap...</div>';
    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=5&addressdetails=1&q='
        + encodeURIComponent(query) + '&countrycodes=ph', {
        headers: { 'Accept-Language': 'en', 'User-Agent': 'IRMS-Capstone' }
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        resultsBox.innerHTML = '';
        if (!data.length) {
            resultsBox.innerHTML =
                '<div class="list-group-item text-muted small">Walang nahanap.</div>';
            return;
        }
        data.forEach(function(place) {
            var item    = document.createElement('button');
            item.type   = 'button';
            item.className = 'list-group-item list-group-item-action py-2';
            var name    = place.name || place.display_name.split(',')[0];
            item.innerHTML =
                '<div class="small fw-medium">' + escHtml(name) + '</div>' +
                '<div style="font-size:11px;color:#6c757d;">' +
                escHtml(place.display_name) + '</div>';
            item.addEventListener('click', function() {
                var lat = parseFloat(place.lat);
                var lng = parseFloat(place.lon);
                map.setView([lat, lng], 17);
                placeMarker(lat, lng, place.display_name);
                searchInput.value    = name;
                resultsBox.innerHTML = '';
            });
            resultsBox.appendChild(item);
        });
    })
    .catch(function() {
        resultsBox.innerHTML =
            '<div class="list-group-item text-danger small">Error. Subukan ulit.</div>';
    });
}

function reverseGeocode(lat, lng) {
    fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat='
        + lat + '&lon=' + lng, {
        headers: { 'Accept-Language': 'en', 'User-Agent': 'IRMS-Capstone' }
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data && data.display_name) {
            document.getElementById('location-input').value = data.display_name;
            if (marker) marker.bindPopup(data.display_name).openPopup();
        }
    }).catch(function() {});
}

function useMyLocation(event) {
    if (!navigator.geolocation) {
        alert('Hindi sinusuportahan ng browser mo ang geolocation.');
        return;
    }
    var btn = event.currentTarget;
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Kumuha ng lokasyon...';
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            var lat = pos.coords.latitude;
            var lng = pos.coords.longitude;
            map.setView([lat, lng], 17);
            placeMarker(lat, lng);
            reverseGeocode(lat, lng);
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-geo-alt me-1"></i> Gamitin ang aking lokasyon';
        },
        function() {
            alert('Hindi makuha ang lokasyon. I-allow ang location permission.');
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-geo-alt me-1"></i> Gamitin ang aking lokasyon';
        }
    );
}

document.addEventListener('click', function(e) {
    if (!resultsBox.contains(e.target) && e.target !== searchInput) {
        resultsBox.innerHTML = '';
    }
});

function previewImages(event) {
    var preview = document.getElementById('image-preview');
    preview.innerHTML = '';
    var files = event.target.files;
    if (files.length > 5) {
        alert('Maximum 5 photos lang.');
        event.target.value = '';
        return;
    }
    Array.from(files).forEach(function(file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img       = document.createElement('img');
            img.src       = e.target.result;
            img.className = 'preview-img';
            preview.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
}

document.getElementById('report-form').addEventListener('submit', function(e) {
    if (!document.getElementById('latitude').value) {
        e.preventDefault();
        alert('I-pin muna ang lokasyon sa mapa bago mag-submit.');
    }
});

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>