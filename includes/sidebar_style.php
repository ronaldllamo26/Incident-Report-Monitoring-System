<style>
    #map { height: 400px; border-radius: 8px; border: 1px solid #dee2e6; }
    .sidebar {
        width: 220px;
        min-height: 100vh;
        background: #1e293b;
        transition: transform 0.3s ease;
        z-index: 1040;
    }
    .sidebar .nav-link { color: #94a3b8; font-size: 14px; padding: 10px 20px; border-radius: 6px; margin: 2px 8px; }
    .sidebar .nav-link:hover, .sidebar .nav-link.active { background: #334155; color: #fff; }
    .sidebar .nav-link i { width: 20px; }
    .main-content { flex: 1; overflow-y: auto; min-width: 0; }
    .top-nav { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 12px 24px; position: sticky; top: 0; z-index: 100; }
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