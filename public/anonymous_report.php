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
                           
        /* ── LIGTAS UI: GLASS MAP CONTROLS ──────────────── */
        .glass-controls {
            position: absolute;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 12px;
            padding: 8px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
        }
        
        .glass-btn {
            background: rgba(255, 255, 255, 0.85);
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 11px;
            font-weight: 600;
            color: #333;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .glass-btn:hover { background: #fff; transform: translateY(-1px); }
        .glass-btn.active { background: #003DA5; color: #fff; }
        
        /* ── MAP CONTAINER ─────────────────────────────── */
        #map-wrapper {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid #003DA5;
            box-shadow: 0 4px 16px rgba(0, 61, 165, 0.15);
        }

        #qc-badge {
            position: absolute;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            background: rgba(0, 45, 122, 0.92);
            color: #F5A623;
            font-size: 11px;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 20px;
            letter-spacing: 0.8px;
            border: 1px solid rgba(245, 166, 35, 0.4);
            white-space: nowrap;
            pointer-events: none;
        }
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
            <a href="/irms/index.php"
               class="btn btn-light btn-sm">
                <i class="bi bi-house-door me-1"></i> Dashboard
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
                            <label class="form-label small fw-medium d-flex align-items-center gap-2 mb-2">
                                I-pin ang Eksaktong Lokasyon
                                <span class="badge"
                                      style="background:#003DA5;color:#F5A623;font-size:10px;font-weight:700;">
                                    QUEZON CITY ONLY
                                </span>
                            </label>

                            <!-- Map wrapper with badge -->
                            <div id="map-wrapper">
                                <div id="qc-badge">📍 QUEZON CITY — QC-ALERTO MAP</div>
                                
                                <!-- Ligtas Style Map Controls -->
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
                            <!-- Outside QC map alert -->
                            <div class="qc-outside-alert" id="map-outside-alert" style="display:none; align-items: center; gap: 8px; background: #fff3cd; border: 1px solid #ffc107; border-left: 4px solid #CC0000; border-radius: 6px; padding: 8px 12px; font-size: 12px; color: #856404; margin-top: 8px;">
                                <i class="bi bi-exclamation-triangle-fill"
                                   style="color:#CC0000;flex-shrink:0;"></i>
                                <span>
                                    <strong>Hindi pwede</strong> — ang lokasyong ito ay
                                    nasa labas ng Quezon City. I-click ang loob ng QC para mag-pin.
                                </span>
                            </div>
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
// ================= MAP INIT =================
var map = L.map('map', {
    center: [14.6760, 121.0437],
    zoom: 12,
    minZoom: 12,      
    maxZoom: 18,
    maxBoundsViscosity: 1.0 
});

// 📌 BASE MAP LAYERS (Ligtas Style)
window.mapLayers = {
    'light': L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', { maxZoom: 19, attribution: '© CARTO' }),
    'dark': L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom: 19, attribution: '© CARTO' }),
    'satellite': L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Tiles © Esri' })
};

var currentMapLayer = window.mapLayers['light'].addTo(map);
var mapMask;

// Toggle Function
window.changeMapStyle = function(type, btnObj) {
    document.querySelectorAll('.glass-btn').forEach(btn => btn.classList.remove('active'));
    btnObj.classList.add('active');
    
    map.removeLayer(currentMapLayer);
    currentMapLayer = window.mapLayers[type].addTo(map);
    
    if (mapMask) {
        if (type === 'dark') {
            mapMask.setStyle({ fillColor: '#0f172a', fillOpacity: 0.85 }); // Dark theme mask
        } else if (type === 'satellite') {
            mapMask.setStyle({ fillColor: '#000000', fillOpacity: 0.70 }); // Satellite somewhat visible
        } else {
            mapMask.setStyle({ fillColor: '#f1f5f9', fillOpacity: 0.85 }); // Light theme mask
        }
    }
};

var qcBoundaryLayer;
var qcFeature;
var marker;

// Fetch the accurate QC boundary we downloaded to the server
fetch('/irms/qc_boundary.geojson')
    .then(response => response.json())
    .then(data => {
        qcFeature = data.features[0];
        
        // 1. Draw Quezon City Boundary (Outline - Zola style bold border)
        qcBoundaryLayer = L.geoJSON(qcFeature, {
            style: {
                color: '#E63946', // Glowing striking red like Ligtas
                weight: 4,
                fillOpacity: 0
            }
        }).addTo(map);

        // 2. Set max bounds
        var bounds = qcBoundaryLayer.getBounds();
        map.fitBounds(bounds);
        map.setMaxBounds(bounds); 
        
        // 3. MASKING (Tinatago yung ibang lungsod para QC lang talaga makita)
        var coordinates = qcFeature.geometry.type === 'MultiPolygon' 
            ? qcFeature.geometry.coordinates[0][0]
            : qcFeature.geometry.coordinates[0];
        
        var qcLatLngs = coordinates.map(c => [c[1], c[0]]);
        
        var world = [
            [-90, -180],
            [-90, 180],
            [90, 180],
            [90, -180],
            [-90, -180]
        ];

        mapMask = L.polygon([world, qcLatLngs], {
            fillColor: '#f1f5f9', // Same as website body background
            fillOpacity: 0.85,    // Slightly transparent parang sa Ligtas!
            stroke: false,
            interactive: false
        }).addTo(map);
        
        
        // ==========================================================
        // 📌 ZOLA-STYLE BARANGAYS OVERLAY (OPTIONAL PERO HIGHLY RECOMMENDED)
        // ==========================================================
        
        fetch('/irms/qc_barangays.geojson').then(res => {
            if (!res.ok) throw new Error("Wala pa yung qc_barangays.geojson");
            return res.json();
        }).then(barangayData => {
            L.geoJSON(barangayData, {
                style: {
                    color: '#0056b3',    // Light border natin for each barangay
                    weight: 1.5,
                    fillColor: '#3b82f6',// Zola-like data color
                    fillOpacity: 0.1     // Subtle background lang
                },
                onEachFeature: function (feature, layer) {
                    // Hover Magic Effect!
                    layer.on('mouseover', function (e) {
                        layer.setStyle({ fillOpacity: 0.3, weight: 2 });
                    });
                    layer.on('mouseout', function (e) {
                        layer.setStyle({ fillOpacity: 0.1, weight: 1.5 });
                    });

                    // Kung may pangalan na kasama sa data, gawin nating tooltip
                    if (feature.properties && feature.properties.name) {
                        layer.bindTooltip(feature.properties.name, {
                            className: 'barangay-label',
                            sticky: true
                        });
                    }
                }
            }).addTo(map);
        }).catch(err => console.log("Not loading barangays: " + err.message));

    })
    .catch(err => console.error("Error loading QC data:", err));

// ================= POINT CHECKER (TURF.JS) =================
window.checkInsideQC = function(lat, lng) {
    if (!qcFeature) return false;
    // turf logic inline kung walang turf.js included, otherwise we need to include it
    // Wait, report.php didn't have turf included! the map logic is inside qc-map.js initially.
    // I need to use the turf implementation if present or implement my own simpler ray-casting.
    
    // RAY-CASTING POINT IN POLYGON INSTEAD OF TURF SO WE DON'T NEED TO INCLUDE EXTERNAL SCRIPT:
    var coords = qcFeature.geometry.type === 'MultiPolygon' 
        ? qcFeature.geometry.coordinates[0][0]
        : qcFeature.geometry.coordinates[0];
        
    var x = lng, y = lat;
    var inside = false;
    for (var i = 0, j = coords.length - 1; i < coords.length; j = i++) {
        var xi = coords[i][0], yi = coords[i][1];
        var xj = coords[j][0], yj = coords[j][1];
        var intersect = ((yi > y) != (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
};

// ================= CLICK EVENTS =================
map.on('click', function (e) {
    if (!qcFeature) return;

    // Check if within bounds
    if (!window.checkInsideQC(e.latlng.lat, e.latlng.lng)) {
        var alertEl = document.getElementById('map-outside-alert');
        alertEl.style.display = 'flex';
        setTimeout(() => alertEl.style.display = 'none', 3000);
        return;
    }
    
    document.getElementById('map-outside-alert').style.display = 'none';

    if (marker) map.removeLayer(marker);
    marker = L.marker(e.latlng).addTo(map);

    document.getElementById('latitude').value = e.latlng.lat;
    document.getElementById('longitude').value = e.latlng.lng;

    // Auto-fill location dummy text so user doesn't have to guess
    document.getElementById('location-input').value = e.latlng.lat.toFixed(5) + ", " + e.latlng.lng.toFixed(5);
    document.getElementById('pin-status').innerHTML = '<span class="text-success" style="margin-left: 8px;"><i class="bi bi-check-circle-fill"></i> Naka-pin na po!</span>';
});

// Reset Map View
function resetMapView() {
    if (qcBoundaryLayer) {
        map.fitBounds(qcBoundaryLayer.getBounds());
    }
}

// ================= LOCATION SEARCH (QC LIMITED) =================
function searchLocation() {
    var query = document.getElementById('search-input').value.trim();
    if(!query) return;
    
    var resultsDiv = document.getElementById('search-results');
    resultsDiv.innerHTML = '<div class="list-group-item text-muted">Awtomatikong hinahanap...</div>';
    
    // Helper function para madaling ulitin yung fetch in case of failure (fallback)
    function fetchLocation(searchQuery, isFallback = false) {
        var url = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(searchQuery + ', Quezon City, Philippines') + '&viewbox=120.98,14.78,121.15,14.59&bounded=1&limit=5';
        
        fetch(url)
            .then(res => res.json())
            .then(data => {
                resultsDiv.innerHTML = '';
                
                if(data.length === 0) {
                    if (!isFallback && query.match(/^[0-9]+\s+/)) {
                        var noNumberQuery = query.replace(/^[0-9]+\s+/, '');
                        fetchLocation(noNumberQuery, true); // Retrying without number!
                    } else {
                        resultsDiv.innerHTML = '<div class="list-group-item text-danger">Walang eksaktong nahanap na lokasyon sa loob ng QC. Paki-check po ang spelling o pangalan ng kalsada.</div>';
                    }
                    return;
                }
                
                data.forEach(item => {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action py-2';
                    btn.innerHTML = '<strong>' + item.display_name.split(',')[0] + '</strong><br><small class="text-muted" style="font-size:11px;">' + item.display_name + '</small>';
                    
                    btn.onclick = function() {
                        var lat = parseFloat(item.lat);
                        var lon = parseFloat(item.lon);
                        
                        // Validation
                        if (window.checkInsideQC && !window.checkInsideQC(lat, lon)) {
                            alert("Ipagpaumanhin, ang adres ay nasa labas ng eksaktong borders ng QC.");
                            return;
                        }
                        
                        // AUTOMATIC PIN TO MAP!
                        map.setView([lat, lon], 17);
                        if (marker) map.removeLayer(marker);
                        marker = L.marker([lat, lon]).addTo(map);
                        
                        // FILL ALL FORMS
                        document.getElementById('latitude').value = lat;
                        document.getElementById('longitude').value = lon;
                        document.getElementById('location-input').value = item.display_name;
                        document.getElementById('search-input').value = item.display_name.split(',')[0];
                        document.getElementById('pin-status').innerHTML = '<span class="text-success" style="margin-left: 8px;"><i class="bi bi-check-circle-fill"></i> Tumpak! Naka-pin na po ang lokasyon!</span>';
                        
                        resultsDiv.innerHTML = ''; // close dropdown
                    };
                    
                    resultsDiv.appendChild(btn);
                });
            })
            .catch(err => {
                resultsDiv.innerHTML = '<div class="list-group-item text-danger">Error sa pagkuha ng lokasyon.</div>';
            });
    }

    // Start the initial search!
    fetchLocation(query);
}

document.getElementById('search-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchLocation();
    }
});

// ── CLOSE RESULTS ON OUTSIDE CLICK ────────────────────
document.addEventListener('click', function(e) {
    var searchInput = document.getElementById('search-input');
    var resultsBox    = document.getElementById('search-results');
    if (resultsBox && searchInput && !resultsBox.contains(e.target) && e.target !== searchInput) {
        resultsBox.innerHTML = '';
    }
});

// ── MY LOCATION ────────────────────────────────────────
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
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-geo-alt me-1"></i> Gamitin ang aking lokasyon';

            if (window.checkInsideQC && !window.checkInsideQC(lat, lng)) {
                var alertEl = document.getElementById('map-outside-alert');
                alertEl.style.display = 'flex';
                return;
            }

            map.setView([lat, lng], 17);
            if (marker) map.removeLayer(marker);
            marker = L.marker([lat, lng]).addTo(map);
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            document.getElementById('location-input').value = lat.toFixed(5) + ", " + lng.toFixed(5);
            document.getElementById('pin-status').innerHTML = '<span class="text-success" style="margin-left: 8px;"><i class="bi bi-check-circle-fill"></i> Naka-pin na po!</span>';
        },
        function() {
            alert('Hindi makuha ang lokasyon. I-allow ang location permission sa browser.');
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-geo-alt me-1"></i> Gamitin ang aking lokasyon';
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

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
            var img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'preview-img';
            preview.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
}

// ── FORM SUBMIT VALIDATION ─────────────────────────────
document.getElementById('report-form').addEventListener('submit', function(e) {
    var lat = document.getElementById('latitude').value;
    var lng = document.getElementById('longitude').value;

    if (!lat || !lng) {
        e.preventDefault();
        alert('I-pin muna ang lokasyon sa mapa ng Quezon City bago mag-submit.');
        return;
    }

    if (window.checkInsideQC && !window.checkInsideQC(parseFloat(lat), parseFloat(lng))) {
        e.preventDefault();
        document.getElementById('map-outside-alert').style.display = 'flex';
        alert('Hindi pwedeng mag-submit — ang lokasyon ay nasa labas ng Quezon City.');
    }
});

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>