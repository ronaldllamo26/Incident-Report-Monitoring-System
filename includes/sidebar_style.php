<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* ── Base ──────────────────────────────────────────────── */
    body { font-family: 'Inter', sans-serif !important; }

    /* ── Override Bootstrap primary → dark slate ────────────
       Affects all btn-primary, table-primary, bg-primary, etc. */
    :root {
        --bs-primary:         #1e293b;
        --bs-primary-rgb:     30,41,59;
        --bs-link-color:      #1e293b;
        --bs-link-hover-color:#0f172a;
    }
    .btn-primary        { background:#1e293b; border-color:#1e293b; }
    .btn-primary:hover,
    .btn-primary:focus  { background:#111827; border-color:#111827; }
    .btn-outline-primary { color:#1e293b; border-color:#1e293b; }
    .btn-outline-primary:hover { background:#1e293b; border-color:#1e293b; color:#fff; }
    .text-primary       { color:#1e293b !important; }
    .bg-primary         { background:#1e293b !important; }
    .border-primary     { border-color:#1e293b !important; }
    .badge.bg-primary   { background:#1e293b !important; }
    .bg-slate           { background:#1e293b !important; }
    .text-slate         { color:#1e293b !important; }
    .badge.bg-slate     { background:#1e293b !important; color:#fff; }

    /* btn-success stays green — good for positive actions */

    /* ── Sidebar ──────────────────────────────────────────── */
    .sidebar {
        width: 230px;
        min-height: 100vh;
        background: #1e293b;
        transition: transform 0.3s ease;
        z-index: 1040;
        border-right: 1px solid rgba(255,255,255,0.06);
        display: flex;
        flex-direction: column;
    }
    .sidebar-brand {
        padding: 18px 20px 14px;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        margin-bottom: 8px;
    }
    .sidebar-brand-name {
        font-size: 15px;
        font-weight: 700;
        color: #fff;
        letter-spacing: 0.3px;
    }
    .sidebar-brand-role {
        font-size: 10px;
        color: #CE1126;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 2px;
    }
    .sidebar .nav-link {
        color: #94a3b8;
        font-size: 13px;
        font-weight: 500;
        padding: 9px 14px;
        border-radius: 8px;
        margin: 2px 10px;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.15s;
        border-left: 3px solid transparent;
    }
    .sidebar .nav-link:hover {
        background: rgba(255,255,255,0.06);
        color: #e2e8f0;
    }
    .sidebar .nav-link.active {
        background: rgba(206,17,38,0.12);
        color: #fff;
        border-left-color: #CE1126;
        font-weight: 600;
    }
    .sidebar .nav-link i { font-size: 15px; flex-shrink: 0; }

    /* Sidebar footer user info */
    .sidebar-footer {
        margin-top: auto;
        padding: 14px 12px;
        border-top: 1px solid rgba(255,255,255,0.08);
    }
    .sidebar-user {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        border-radius: 8px;
        margin-bottom: 4px;
    }
    .sidebar-user-av {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #334155;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        color: #fff;
        flex-shrink: 0;
    }
    .sidebar-user-name {
        font-size: 12px;
        font-weight: 600;
        color: #e2e8f0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sidebar-logout {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        border-radius: 6px;
        font-size: 12px;
        color: #f87171;
        text-decoration: none;
        transition: background 0.15s;
    }
    .sidebar-logout:hover { background: rgba(248,113,113,0.1); color: #f87171; }

    /* ── Main Content ─────────────────────────────────────── */
    .main-content { flex: 1; overflow-y: auto; min-width: 0; background: #f4f6f9; }

    /* ── Top Nav ──────────────────────────────────────────── */
    .top-nav {
        background: #fff;
        border-bottom: 2px solid #e2e8f0;
        padding: 12px 24px;
        position: sticky;
        top: 0;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .top-nav-title {
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    /* ── Cards ────────────────────────────────────────────── */
    .card { border-radius: 10px !important; }
    .card.border-0 { box-shadow: 0 1px 4px rgba(0,0,0,0.06); }

    /* ── Map ──────────────────────────────────────────────── */
    #map { height: 400px; border-radius: 8px; border: 1px solid #dee2e6; }

    /* ── Mobile Overlay ───────────────────────────────────── */
    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1039;
    }
    .sidebar-overlay.show { display: block; }

    @media (max-width: 768px) {
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            height: 100vh;
            transform: translateX(-100%);
        }
        .sidebar.show { transform: translateX(0); }
        .hamburger { display: flex !important; }
    }
    @media (min-width: 769px) {
        .hamburger { display: none !important; }
        .sidebar-overlay { display: none !important; }
    }
</style>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}
</script>