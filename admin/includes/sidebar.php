<!-- ========================================
     SIDEBAR
     ======================================== -->
<button class="menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-shield-alt"></i>
            <span>ABSA Admin</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="users.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>Utilisateurs</span>
        </a>
        <a href="conversations.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'conversations.php' ? 'active' : '' ?>">
            <i class="fas fa-comments"></i>
            <span>Conversations</span>
        </a>
        <a href="emotions.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'emotions.php' ? 'active' : '' ?>">
            <i class="fas fa-heart"></i>
            <span>Analyse Émotions</span>
        </a>
        <a href="admin-users.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'admin-users.php' ? 'active' : '' ?>">
            <i class="fas fa-user-shield"></i>
            <span>Administrateurs</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-info">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                <i class="fas fa-user-circle" style="font-size: 32px; color: #4b3795;"></i>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 600; color: #e5e7eb; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?= htmlspecialchars($adminName ?? 'Admin') ?>
                    </div>
                    <?php
                    $role = $adminRole ?? 'viewer';
                    $roleLabel = $role === 'admin' ? 'Administrateur' : 'Observateur';
                    $roleColor = $role === 'admin' ? '#4b3795' : '#51c6e1';
                    ?>
                    <div style="display: inline-block; background: <?= $roleColor ?>20; color: <?= $roleColor ?>; border: 1px solid <?= $roleColor ?>; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-top: 4px;">
                        <?= $roleLabel ?>
                    </div>
                </div>
            </div>
        </div>
        <button class="btn-logout" onclick="logout()">
            <i class="fas fa-sign-out-alt"></i>
            <span>Déconnexion</span>
        </button>
    </div>
</aside>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('active');
}

// Fermer le sidebar quand on clique en dehors sur mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.querySelector('.menu-toggle');

    if (window.innerWidth <= 768 &&
        !sidebar.contains(event.target) &&
        !menuToggle.contains(event.target) &&
        sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
    }
});
</script>
