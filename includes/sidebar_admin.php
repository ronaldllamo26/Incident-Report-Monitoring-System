<?php
// Kunin ang current page para ma-highlight ang active link
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="sidebar d-flex flex-column py-3" id="sidebar">
    <div class="px-4 mb-4 d-flex justify-content-between align-items-center">
        <div>
            <div class="text-white fw-semibold fs-6">
                <i class="bi bi-shield-check me-2"></i>IRMS
            </div>
            <div class="text-secondary" style="font-size:11px;">Admin Panel</div>
        </div>
        <!-- Close button — mobile only -->
        <button class="btn btn-sm d-md-none"
                style="color:#94a3b8;background:none;border:none;"
                onclick="toggleSidebar()">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <nav class="flex-column nav">
        <a href="/irms/portal/admin/dashboard.php"
           class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2 me-2"></i> Dashboard
        </a>
        <a href="/irms/portal/admin/incidents.php"
           class="nav-link <?= $currentPage === 'incidents.php' ? 'active' : '' ?>">
            <i class="bi bi-exclamation-triangle me-2"></i> Incidents
        </a>
        <a href="/irms/portal/admin/users.php"
           class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">
            <i class="bi bi-people me-2"></i> Users
        </a>
        <a href="/irms/portal/admin/categories.php"
           class="nav-link <?= $currentPage === 'categories.php' ? 'active' : '' ?>">
            <i class="bi bi-tags me-2"></i> Categories
        </a>
        <a href="/irms/portal/admin/reports.php"
           class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-bar-graph me-2"></i> Reports
        </a>
        <!-- ── DAGDAG: Audit Logs ── -->
        <a href="/irms/portal/admin/audit_logs.php"
           class="nav-link <?= $currentPage === 'audit_logs.php' ? 'active' : '' ?>">
            <i class="bi bi-journal-text me-2"></i> Audit Logs
        </a>
    </nav>
    <div class="mt-auto px-3">
        <div class="text-secondary small px-2 mb-2">
            <i class="bi bi-person-circle me-1"></i>
            <?= htmlspecialchars($user['name'] ?? $_SESSION['name']) ?>
        </div>
        <a href="/irms/controllers/AuthController.php?action=logout"
           class="nav-link text-danger">
            <i class="bi bi-box-arrow-right me-2"></i> Logout
        </a>
    </div>
</div>