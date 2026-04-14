<!-- ═══════════════════════════════════════════════════════
     NOTIFICATION BELL COMPONENT
     I-include sa top-nav ng bawat page:
       <?php include __DIR__ . '/../../includes/notification_bell.php'; ?>

     Requirements:
       - User dapat naka-login na (isLoggedIn())
       - Bootstrap 5 at Bootstrap Icons dapat naka-load na
     ═══════════════════════════════════════════════════════ -->

<div class="position-relative" id="notif-wrapper">

    <!-- Bell button -->
    <button class="btn btn-sm btn-outline-secondary position-relative"
            id="notif-btn"
            onclick="toggleNotifDropdown()"
            title="Mga Notification">
        <i class="bi bi-bell fs-6"></i>
        <!-- Badge — hidden by default, lalabas pag may unread -->
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
              id="notif-badge"
              style="font-size:9px;display:none;min-width:16px;padding:2px 4px;">
            0
        </span>
    </button>

    <!-- Dropdown panel -->
    <div id="notif-dropdown"
         style="display:none;
                position:absolute;
                right:0;
                top:calc(100% + 8px);
                width:340px;
                background:#fff;
                border:1px solid #e2e8f0;
                border-radius:12px;
                box-shadow:0 8px 32px rgba(0,0,0,0.12);
                z-index:9999;
                overflow:hidden;">

        <!-- Header -->
        <div style="padding:12px 16px;border-bottom:1px solid #f1f5f9;
                    display:flex;align-items:center;justify-content:space-between;">
            <div style="font-size:14px;font-weight:700;color:#1e293b;">
                <i class="bi bi-bell me-1"></i> Notifications
            </div>
            <button onclick="markAllRead()"
                    style="font-size:11px;color:#6366f1;background:none;border:none;
                           cursor:pointer;font-weight:600;padding:0;"
                    id="mark-all-btn">
                Mark all as read
            </button>
        </div>

        <!-- Notification list -->
        <div id="notif-list"
             style="max-height:360px;overflow-y:auto;">
            <!-- Filled by JS -->
            <div style="text-align:center;padding:32px 16px;color:#94a3b8;font-size:13px;">
                <i class="bi bi-bell-slash d-block mb-2" style="font-size:24px;"></i>
                Walang notifications pa.
            </div>
        </div>

        <!-- Footer -->
        <div style="padding:10px 16px;border-top:1px solid #f1f5f9;text-align:center;">
            <a href="#" onclick="markAllRead(); return false;"
               style="font-size:12px;color:#6366f1;text-decoration:none;font-weight:600;">
                I-clear lahat
            </a>
        </div>
    </div>
</div>

<style>
.notif-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f8fafc;
    cursor: pointer;
    transition: background 0.15s;
    display: flex;
    gap: 10px;
    align-items: flex-start;
    text-decoration: none;
    color: inherit;
}
.notif-item:hover { background: #f8fafc; }
.notif-item.unread { background: #eff6ff; }
.notif-item.unread:hover { background: #dbeafe; }
.notif-icon {
    width: 36px; height: 36px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
}
.notif-title {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 2px;
    line-height: 1.3;
}
.notif-message {
    font-size: 12px;
    color: #64748b;
    line-height: 1.4;
    margin-bottom: 3px;
}
.notif-time {
    font-size: 11px;
    color: #94a3b8;
}
.notif-unread-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #6366f1;
    flex-shrink: 0;
    margin-top: 4px;
}
</style>

<script>
(function() {
    var pollingInterval = null;
    var isOpen = false;

    // ── TOGGLE DROPDOWN ──────────────────────────────
    window.toggleNotifDropdown = function() {
        var dropdown = document.getElementById('notif-dropdown');
        isOpen = !isOpen;
        dropdown.style.display = isOpen ? 'block' : 'none';
        if (isOpen) fetchNotifications();
    };

    // ── CLOSE ON OUTSIDE CLICK ───────────────────────
    document.addEventListener('click', function(e) {
        var wrapper = document.getElementById('notif-wrapper');
        if (wrapper && !wrapper.contains(e.target) && isOpen) {
            document.getElementById('notif-dropdown').style.display = 'none';
            isOpen = false;
        }
    });

    // ── FETCH NOTIFICATIONS ──────────────────────────
    function fetchNotifications() {
        fetch('/irms/ajax/get_notifications.php')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            updateBadge(data.count);
            renderNotifications(data.notifications);
        })
        .catch(function() {});
    }

    // ── UPDATE BADGE ─────────────────────────────────
    function updateBadge(count) {
        var badge = document.getElementById('notif-badge');
        if (!badge) return;
        if (count > 0) {
            badge.style.display = 'inline';
            badge.textContent   = count > 99 ? '99+' : count;
        } else {
            badge.style.display = 'none';
        }
    }

    // ── RENDER NOTIFICATIONS ──────────────────────────
    function renderNotifications(notifications) {
        var list = document.getElementById('notif-list');
        if (!list) return;

        if (!notifications || !notifications.length) {
            list.innerHTML =
                '<div style="text-align:center;padding:32px 16px;color:#94a3b8;font-size:13px;">' +
                '<i class="bi bi-bell-slash d-block mb-2" style="font-size:24px;"></i>' +
                'Walang notifications pa.</div>';
            return;
        }

        list.innerHTML = notifications.map(function(n) {
            var icon    = getNotifIcon(n.title);
            var unread  = !n.is_read;
            var link    = n.incident_id
                ? getIncidentLink(n.incident_id)
                : '#';

            return '<a href="' + link + '" class="notif-item ' + (unread ? 'unread' : '') + '"' +
                   ' onclick="markRead(' + n.id + ', this, event)">' +
                   '<div class="notif-icon" style="background:' + icon.bg + ';color:' + icon.color + ';">' +
                   '<i class="bi ' + icon.icon + '"></i>' +
                   '</div>' +
                   '<div style="flex:1;min-width:0;">' +
                   '<div class="notif-title">' + escHtml(n.title) + '</div>' +
                   '<div class="notif-message">' + escHtml(n.message) + '</div>' +
                   '<div class="notif-time">' + n.time_ago + '</div>' +
                   '</div>' +
                   (unread ? '<div class="notif-unread-dot"></div>' : '') +
                   '</a>';
        }).join('');
    }

    // ── GET ICON BASED ON TITLE ───────────────────────
    function getNotifIcon(title) {
        title = (title || '').toLowerCase();
        if (title.includes('new') || title.includes('bago') || title.includes('report'))
            return { icon: 'bi-exclamation-triangle-fill', bg: '#fef3c7', color: '#d97706' };
        if (title.includes('assign'))
            return { icon: 'bi-person-check-fill', bg: '#dbeafe', color: '#1d4ed8' };
        if (title.includes('resolv') || title.includes('close'))
            return { icon: 'bi-check-circle-fill', bg: '#dcfce7', color: '#16a34a' };
        if (title.includes('escalat') || title.includes('urgent') || title.includes('breach'))
            return { icon: 'bi-exclamation-octagon-fill', bg: '#fee2e2', color: '#dc2626' };
        if (title.includes('status') || title.includes('update'))
            return { icon: 'bi-arrow-repeat', bg: '#ede9fe', color: '#7c3aed' };
        return { icon: 'bi-bell-fill', bg: '#f1f5f9', color: '#64748b' };
    }

    // ── GET INCIDENT LINK BASED ON ROLE ──────────────
    function getIncidentLink(incidentId) {
        // Determine role from current URL
        var url = window.location.pathname;
        if (url.includes('/portal/admin/'))
            return '/irms/portal/admin/view_incident.php?id=' + incidentId;
        if (url.includes('/portal/responder/'))
            return '/irms/portal/responder/view_incident.php?id=' + incidentId;
        return '/irms/citizen/view_report.php?id=' + incidentId;
    }

    // ── MARK SINGLE AS READ ───────────────────────────
    window.markRead = function(id, el, event) {
        fetch('/irms/ajax/mark_notification_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id
        })
        .then(function() { fetchNotifications(); })
        .catch(function() {});
        
    };

    // ── MARK ALL AS READ ──────────────────────────────
    window.markAllRead = function() {
        fetch('/irms/ajax/mark_notification_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=0' // 0 = mark all
        })
        .then(function() { fetchNotifications(); })
        .catch(function() {});
    };

    // ── ESCAPE HTML ───────────────────────────────────
    function escHtml(s) {
        return String(s || '')
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;');
    }

    // ── START POLLING — every 30 seconds ─────────────
    fetchNotifications(); // Initial fetch
    pollingInterval = setInterval(fetchNotifications, 30000);

})();
</script>