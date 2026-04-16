<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Fetch categories for the filter dropdown
$cats = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Basic Stats for the top bar
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN `status` = 'resolved' OR `status` = 'closed' THEN 1 ELSE 0 END) as resolved
    FROM incidents 
    WHERE status != 'rejected'
")->fetch();

$total = $stats['total'] ?? 0;
$resolved = $stats['resolved'] ?? 0;
// Calculate resolution rate
$rate = $total > 0 ? round(($resolved / $total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="fil">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QC-ALERTO — Public Map & Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; margin: 0; padding: 0; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        
        .brand-bar { background: #1e293b; border-bottom: 3px solid #F5A623; padding: 14px 0; flex-shrink: 0; }
        
        /* Stats strip */
        .stats-bar { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 10px 0; flex-shrink: 0; font-size: 13px; }
        .stat-item { display: inline-flex; align-items: center; gap: 8px; margin-right: 24px; color: #475569; }
        .stat-number { font-weight: 700; font-size: 15px; color: #0f172a; }
        
        .main-container { flex: 1; display: flex; position: relative; overflow: hidden; }
        
        /* Sidebar layout for desktop */
        .sidebar-filters { 
            width: 320px; background: #fff; border-right: 1px solid #e2e8f0; 
            padding: 20px; overflow-y: auto; display: flex; flex-direction: column; z-index: 10;
        }
        
        /* Map Area */
        .map-area { flex: 1; position: relative; }
        #map { width: 100%; height: 100%; z-index: 1; background: #e2e8f0; }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .main-container { flex-direction: column; }
            .sidebar-filters { width: 100%; padding: 15px; border-right: none; border-bottom: 1px solid #e2e8f0; max-height: 40vh; }
            .map-area { height: 60vh; }
        }

        .toggle-group { display: flex; background: #f1f5f9; padding: 4px; border-radius: 8px; margin-bottom: 20px; }
        .toggle-btn { 
            flex: 1; text-align: center; padding: 8px 0; font-size: 13px; font-weight: 600; 
            color: #64748b; border-radius: 6px; cursor: pointer; transition: all 0.2s; border: none; background: transparent;
        }
        .toggle-btn.active { background: #fff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        
        .filter-label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: block; }
        
        /* Legends */
        .legend-box { background: rgba(255,255,255,0.9); padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 12px; margin-top: auto; }
        
        /* Loading Overlay */
        #loader { 
            position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.7); 
            z-index: 1000; display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .spinner-border { color: #f59e0b; }
        
        /* QC Ribbon */
        #qc-badge {
            position: absolute; top: 10px; right: 10px;
            z-index: 1000; background: rgba(0,45,122,0.92); color: #F5A623;
            font-size: 11px; font-weight: 700; padding: 5px 14px; border-radius: 20px;
            letter-spacing: 0.8px; border: 1px solid rgba(245,166,35,0.4); pointer-events: none;
        }
    </style>
</head>
<body>

<!-- Brand bar -->
<div class="brand-bar">
    <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
        <a href="/irms/index.php" class="text-white text-decoration-none fw-semibold d-flex align-items-center gap-2">
            <img src="/irms/assets/img/QC_LOGO_CIRCLE.png" style="height:36px;width:36px;object-fit:contain;" alt="QC-ALERTO">
            <span>QC-ALERTO</span>
            <span class="text-secondary ms-1 d-none d-md-inline" style="font-size:12px;">Public Transparency Map</span>
        </a>
        <div class="d-flex gap-2">
            <a href="/irms/public/report.php" class="btn btn-warning btn-sm fw-bold">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> I-Report
            </a>
            <a href="/irms/index.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-house-door me-1 d-none d-sm-inline-block"></i> 
                <i class="bi bi-arrow-left d-inline-block d-sm-none"></i>
                <span class="d-none d-sm-inline-block">Home</span>
            </a>
        </div>
    </div>
</div>

<!-- Stats bar -->
<div class="stats-bar d-none d-md-block">
    <div class="container-fluid px-4">
        <div class="stat-item">
            <i class="bi bi-activity text-primary" style="font-size:16px;"></i> 
            <span>Total Insidente: <span class="stat-number"><?= number_format($total) ?></span></span>
        </div>
        <div class="stat-item">
            <i class="bi bi-check-circle-fill text-success" style="font-size:16px;"></i> 
            <span>Resolusyon: <span class="stat-number text-success"><?= $rate ?>%</span></span>
        </div>
        <div class="stat-item ms-auto text-muted" style="font-size:11px;">
            <i class="bi bi-shield-lock-fill"></i> Data is anonymized for privacy and security.
        </div>
    </div>
</div>

<div class="main-container">

    <!-- Filters Sidebar -->
    <div class="sidebar-filters">
        <h5 class="fw-bold mb-3 d-flex align-items-center gap-2" style="font-size:16px;">
            <i class="bi bi-sliders"></i> Mga Settings ng Mapa
        </h5>
        
        <div class="toggle-group">
            <button class="toggle-btn active" id="btn-markers" onclick="setMapMode('markers')">
                <i class="bi bi-geo-alt-fill"></i> Markers
            </button>
            <button class="toggle-btn" id="btn-heatmap" onclick="setMapMode('heatmap')">
                <i class="bi bi-fire"></i> Heatmap
            </button>
        </div>
        
        <form id="filter-form">
            <div class="mb-3">
                <label class="filter-label">Kategorya</label>
                <select name="category" class="form-select form-select-sm" onchange="fetchMapData()">
                    <option value="">Lahat ng Kategorya</option>
                    <?php foreach ($cats as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="filter-label">Kalubhaan (Severity)</label>
                <select name="severity" class="form-select form-select-sm" onchange="fetchMapData()">
                    <option value="">Lahat</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="filter-label">Status</label>
                <select name="status" class="form-select form-select-sm" onchange="fetchMapData()">
                    <option value="">Lahat ng Aktibo at Tapos</option>
                    <option value="in_progress">In Progress</option>
                    <option value="pending">Pending</option>
                    <option value="resolved">Resolved</option>
                </select>
            </div>
        </form>

        <div class="legend-box mt-auto">
            <div class="fw-bold mb-2 pb-1 border-bottom"><i class="bi bi-info-circle"></i> Map Legend</div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-circle-fill text-danger"></i> Critical / High Emergency
            </div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-circle-fill text-warning"></i> Medium Warning
            </div>
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-circle-fill text-success"></i> Low / Resolved
            </div>
        </div>
    </div>

    <!-- Map Area -->
    <div class="map-area">
        <div id="loader">
            <div class="spinner-border mb-2" role="status"></div>
            <div class="fw-bold text-secondary" style="font-size:13px;">Kumukuha ng data...</div>
        </div>
        <div id="qc-badge">📍 QUEZON CITY</div>
        <div id="map"></div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
<!-- Leaflet Heatmap Plugin -->
<script src="https://cdn.jsdelivr.net/npm/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>

<script>
// Fix Leaflet broken default icons when pulling from CDN
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png'
});

// Initialization
var map = L.map('map', { 
    center: [14.6760, 121.0437], 
    zoom: 12, 
    minZoom: 12, 
    maxZoom: 18 
});

L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    maxZoom: 19,
    attribution: '© CARTO'
}).addTo(map);

var mapMode = 'markers'; // 'markers' or 'heatmap'
var markersLayer = L.layerGroup().addTo(map);
var heatLayer = null;
var currentData = [];

// Apply Dark Inverted QC Mask
fetch('/irms/qc_boundary.geojson').then(r => r.json()).then(data => {
    var qcFeature = data.features[0];
    var bounds = L.geoJSON(qcFeature).getBounds();
    map.fitBounds(bounds);
    
    var coords = qcFeature.geometry.type === 'MultiPolygon' 
        ? qcFeature.geometry.coordinates[0][0] 
        : qcFeature.geometry.coordinates[0];
    
    var qcLatLngs = coords.map(c => [c[1], c[0]]);
    var world = [[-90,-180],[-90,180],[90,180],[90,-180],[-90,-180]];
    
    // Inverted Masking
    L.polygon([world, qcLatLngs], {
        fillColor: '#0f172a',
        fillOpacity: 0.85,
        stroke: true,
        color: '#F5A623',
        weight: 2,
        interactive: false
    }).addTo(map);
    
    // Initial fetch
    fetchMapData();
}).catch(e => console.error("Could not load boundary", e));

function setMapMode(mode) {
    document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('btn-' + mode).classList.add('active');
    mapMode = mode;
    renderData();
}

function fetchMapData() {
    document.getElementById('loader').style.display = 'flex';
    var formData = new FormData(document.getElementById('filter-form'));
    var searchParams = new URLSearchParams(formData).toString();
    
    fetch('/irms/ajax/get_public_heatmap_data.php?' + searchParams)
        .then(res => res.json())
        .then(response => {
            if(response.status === 'success') {
                currentData = response.data;
                renderData();
            } else {
                alert("Error fetching map data");
            }
        })
        .finally(() => {
            document.getElementById('loader').style.display = 'none';
        });
}

function renderData() {
    // Clear existing
    markersLayer.clearLayers();
    if(heatLayer) {
        map.removeLayer(heatLayer);
        heatLayer = null;
    }
    
    if(currentData.length === 0) return;

    if(mapMode === 'markers') {
        renderMarkers();
    } else {
        renderHeatmap();
    }
}

function renderMarkers() {
    currentData.forEach(inc => {
        var markerColor = getColor(inc.severity, inc.status);
        
        var circleMarker = L.circleMarker([inc.lat, inc.lng], {
            radius: 7,
            fillColor: markerColor,
            color: '#fff',
            weight: 1,
            opacity: 1,
            fillOpacity: 0.8
        });

        var popupContent = `
            <div style="font-family:'Inter',sans-serif; min-width:180px;">
                <div style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:4px;">
                    ${inc.category_name}
                </div>
                <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:6px;">
                    ${inc.title}
                </div>
                <div style="font-size:11px; margin-bottom:4px;">
                    <span style="display:inline-block;padding:2px 6px;border-radius:4px;background:#f1f5f9;color:#475569;font-weight:600;">
                        ${inc.status.replace('_', ' ').toUpperCase()}
                    </span>
                </div>
                <div style="font-size:10px;color:#94a3b8;">
                    <i class="bi bi-clock me-1"></i> ${inc.timestamp}
                </div>
            </div>
        `;
        circleMarker.bindPopup(popupContent);
        markersLayer.addLayer(circleMarker);
    });
}

function renderHeatmap() {
    // Standardize heat points [lat, lng, intensity]
    var heatPoints = currentData.map(inc => {
        // Higher severity = brighter/hotter point
        var intensity = 0.4;
        if(inc.severity === 'critical') intensity = 1.0;
        else if(inc.severity === 'high') intensity = 0.8;
        else if(inc.severity === 'medium') intensity = 0.6;
        
        return [inc.lat, inc.lng, intensity];
    });

    heatLayer = L.heatLayer(heatPoints, {
        radius: 25,
        blur: 15,
        maxZoom: 15,
        max: 1.0,
        gradient: {
            0.4: 'blue', 
            0.6: 'cyan', 
            0.7: 'lime', 
            0.8: 'yellow', 
            1.0: 'red'
        }
    }).addTo(map);
}

function getColor(severity, status) {
    if(status === 'resolved' || status === 'closed') return '#10b981'; // Green
    if(severity === 'critical' || severity === 'high') return '#ef4444'; // Red
    if(severity === 'medium') return '#f59e0b'; // Amber
    return '#3b82f6'; // Blue
}
</script>
</body>
</html>
