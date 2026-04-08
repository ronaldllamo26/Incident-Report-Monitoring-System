/**
 * QC-ONLY MAP — FINAL VERSION (WORKING)
 * Leaflet Map restricted strictly to Quezon City
 */

// ─────────────────────────────────────────
// 📍 QC GEOJSON (lng, lat format)
// ─────────────────────────────────────────
var QC_GEOJSON = {
    "type": "Feature",
    "geometry": {
        "type": "Polygon",
        "coordinates": [[
            [121.0312,14.7790],[121.0436,14.7791],[121.0580,14.7765],
            [121.0710,14.7730],[121.0890,14.7680],[121.1050,14.7590],
            [121.1150,14.7480],[121.1250,14.7350],[121.1380,14.7210],
            [121.1480,14.7060],[121.1560,14.6890],[121.1620,14.6720],
            [121.1680,14.6540],[121.1720,14.6350],[121.1740,14.6150],
            [121.1730,14.5960],[121.1700,14.5780],[121.1640,14.5600],
            [121.1540,14.5440],[121.1420,14.5310],[121.1280,14.5190],
            [121.1120,14.5090],[121.0950,14.5010],[121.0780,14.4950],
            [121.0600,14.4890],[121.0420,14.4840],[121.0240,14.4810],
            [121.0060,14.4820],[120.9950,14.4880],[120.9920,14.5020],
            [120.9940,14.5180],[120.9980,14.5350],[121.0000,14.5530],
            [120.9990,14.5710],[120.9970,14.5890],[120.9960,14.6070],
            [120.9970,14.6250],[121.0000,14.6430],[121.0020,14.6610],
            [121.0020,14.6790],[121.0010,14.6970],[121.0000,14.7140],
            [121.0010,14.7310],[121.0060,14.7470],[121.0150,14.7610],
            [121.0230,14.7720],[121.0312,14.7790]
        ]]
    }
};

// ─────────────────────────────────────────
// 📍 SETTINGS
// ─────────────────────────────────────────
var QC_CENTER = [14.6760, 121.0437];

// convert GeoJSON → Leaflet format
function getQCLatLngs() {
    return QC_GEOJSON.geometry.coordinates[0].map(function(coord) {
        return [coord[1], coord[0]]; // [lat, lng]
    });
}

// ─────────────────────────────────────────
// 📍 POINT-IN-POLYGON CHECK
// ─────────────────────────────────────────
function isInsideQC(lat, lng) {
    var coords = QC_GEOJSON.geometry.coordinates[0];
    var x = lng, y = lat;
    var inside = false;

    for (var i = 0, j = coords.length - 1; i < coords.length; j = i++) {
        var xi = coords[i][0], yi = coords[i][1];
        var xj = coords[j][0], yj = coords[j][1];

        var intersect = ((yi > y) !== (yj > y)) &&
            (x < (xj - xi) * (y - yi) / (yj - yi) + xi);

        if (intersect) inside = !inside;
    }

    return inside;
}

// ─────────────────────────────────────────
// 🚀 INIT MAP
// ─────────────────────────────────────────
function initQCMap(id) {

    var qcLatLngs = getQCLatLngs();

    var map = L.map(id, {
        center: QC_CENTER,
        zoom: 13,
        minZoom: 13,
        maxZoom: 19,
        zoomControl: true
    });

    // Base map
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // QC Polygon
    var qcPolygon = L.polygon(qcLatLngs, {
        color: '#003DA5',
        weight: 3,
        fill: false
    }).addTo(map);

    // Fit bounds EXACT QC
    map.fitBounds(qcPolygon.getBounds());

    // Lock bounds
    map.setMaxBounds(qcPolygon.getBounds());
    map.options.maxBoundsViscosity = 1.0;

    // ─────────────────────────────
    // 🌑 MASK (OUTSIDE QC)
    // ─────────────────────────────
    var world = [
        [-90,-180],
        [-90,180],
        [90,180],
        [90,-180],
        [-90,-180]
    ];

    var mask = L.polygon([world, qcLatLngs.slice().reverse()], {
        fillColor: '#94a3b8',
        fillOpacity: 1,
        stroke: false,
        interactive: false
    }).addTo(map);

    // ─────────────────────────────
    // 📍 CLICK (QC ONLY)
    // ─────────────────────────────
    var marker;

    map.on('click', function(e) {

        if (!isInsideQC(e.latlng.lat, e.latlng.lng)) {
            alert("QC ONLY PRE ❌");
            return;
        }

        if (marker) map.removeLayer(marker);

        marker = L.marker(e.latlng).addTo(map);
    });

    // Force stay inside
    map.on('drag', function() {
        map.panInsideBounds(qcPolygon.getBounds(), { animate: false });
    });

    return map;
}