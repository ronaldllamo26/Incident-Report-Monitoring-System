<aside class="sidebar">
    <div class="brand-box">
        <img src="/irms/assets/img/QC_LOGO_CIRCLE.png" alt="QC">
        <span class="brand-name">QC-ALERTO</span>
    </div>

    <ul class="nav-menu">
        <li class="nav-item">
            <a href="/irms/portal/responder/dashboard.php" class="nav-link-c <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-grid-fill"></i> Command Center
            </a>
        </li>
        <li class="nav-item">
            <a href="/irms/portal/responder/profile.php" class="nav-link-c <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
                <i class="bi bi-person-fill"></i> My Profile
            </a>
        </li>
        <li class="nav-item mt-4">
            <a href="/irms/controllers/AuthController.php?action=logout" class="nav-link-c" style="color: var(--qc-red);">
                <i class="bi bi-box-arrow-right"></i> Sign Out
            </a>
        </li>
    </ul>

    <div style="position: absolute; bottom: 24px; left: 24px; right: 24px;">
        <div class="glass-panel" style="padding: 16px; border-radius: 16px; background: rgba(255,255,255,0.05);">
            <div style="font-size: 11px; color: var(--text-dim); text-transform: uppercase; font-weight: 700; margin-bottom: 8px;">Responder</div>
            <div style="font-size: 14px; font-weight: 700;"><?= htmlspecialchars($user['name'] ?? 'User') ?></div>
            <div style="font-size: 11px; color: var(--text-dim);"><?= date('M d, Y') ?></div>
        </div>
    </div>
</aside>
