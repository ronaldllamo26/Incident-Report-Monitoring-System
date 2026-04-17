<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$userName = htmlspecialchars($user['name'] ?? $_SESSION['name'] ?? 'Admin');
$userInitial = strtoupper(substr(strip_tags($userName), 0, 1));
?>
<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="sidebar" id="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="d-flex align-items-center gap-10" style="gap:10px;">
            <img src="/irms/assets/img/QC_LOGO_CIRCLE.png" alt="QC"
                 style="width:30px;height:30px;object-fit:contain;flex-shrink:0;">
            <div>
                <div class="sidebar-brand-name">QC-ALERTO</div>
                <div class="sidebar-brand-role">Admin Panel</div>
            </div>
            <!-- Close btn — mobile only -->
            <button class="btn btn-sm d-md-none ms-auto"
                    style="color:#94a3b8;background:none;border:none;padding:2px 4px;"
                    onclick="toggleSidebar()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>

    <!-- Nav -->
    <nav class="flex-column nav px-1 mt-1" style="flex:1;">
        <a href="/irms/portal/admin/dashboard.php"
           class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="/irms/portal/admin/incidents.php"
           class="nav-link <?= $currentPage === 'incidents.php' ? 'active' : '' ?>">
            <i class="bi bi-exclamation-triangle"></i> Incidents
        </a>
        <a href="/irms/portal/admin/users.php"
           class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Users
        </a>
        <a href="/irms/portal/admin/profile.php"
           class="nav-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
            <i class="bi bi-person-badge"></i> My Profile
        </a>
        <a href="/irms/portal/admin/categories.php"
           class="nav-link <?= $currentPage === 'categories.php' ? 'active' : '' ?>">
            <i class="bi bi-tags"></i> Categories
        </a>
        <a href="/irms/portal/admin/reports.php"
           class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line"></i> Reports
        </a>
        <a href="/irms/portal/admin/audit_logs.php"
           class="nav-link <?= $currentPage === 'audit_logs.php' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Audit Logs
        </a>
        <a href="/irms/portal/admin/backup.php"
           class="nav-link <?= $currentPage === 'backup.php' ? 'active' : '' ?>">
            <i class="bi bi-database-lock"></i> Backup System
        </a>
        <a href="/irms/portal/admin/sms_logs.php"
           class="nav-link <?= $currentPage === 'sms_logs.php' ? 'active' : '' ?>">
            <i class="bi bi-chat-dots"></i> SMS Simulation
        </a>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-av"><?= $userInitial ?></div>
            <div class="sidebar-user-name"><?= $userName ?></div>
        </div>
        <a href="/irms/controllers/AuthController.php?action=logout" class="sidebar-logout">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>

</div>