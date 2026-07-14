<?php
/**
 * Sidebar de navegación profesional
 * 
 * Se espera que $user esté definido (de requireLogin).
 * Se espera $currentPage para marcar la página activa.
 */
$currentFile = basename($_SERVER['PHP_SELF']);
$projectCount = DB::fetchOne("SELECT COUNT(*) as c FROM projects WHERE user_id = ?", [$user['id']])['c'] ?? 0;
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="sidebar-logo">◈</span>
        <span class="sidebar-title">Kraak Radar</span>
    </div>
    
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="sidebar-link <?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">
            <span class="s-icon">📊</span>
            <span>Dashboard</span>
        </a>
        <a href="prompts.php" class="sidebar-link <?= $currentFile === 'prompts.php' ? 'active' : '' ?>">
            <span class="s-icon">🎯</span>
            <span>Prompts</span>
        </a>
        <a href="competitors.php" class="sidebar-link <?= $currentFile === 'competitors.php' ? 'active' : '' ?>">
            <span class="s-icon">⚔️</span>
            <span>Competidores</span>
        </a>
        <a href="sources.php" class="sidebar-link <?= $currentFile === 'sources.php' ? 'active' : '' ?>">
            <span class="s-icon">🔗</span>
            <span>Fuentes</span>
        </a>
        <a href="costs.php" class="sidebar-link <?= $currentFile === 'costs.php' ? 'active' : '' ?>">
            <span class="s-icon">💰</span>
            <span>Costes</span>
        </a>
        <a href="settings.php" class="sidebar-link <?= $currentFile === 'settings.php' ? 'active' : '' ?>">
            <span class="s-icon">⚙️</span>
            <span>APIs</span>
        </a>
        <a href="export.php" class="sidebar-link <?= $currentFile === 'export.php' ? 'active' : '' ?>">
            <span class="s-icon">📥</span>
            <span>Exportar</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <span class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                <span class="user-plan">Plan <?= $user['plan'] ?></span>
            </div>
        </div>
        <div class="sidebar-projects">
            <span><?= $projectCount ?> proyecto<?= $projectCount !== 1 ? 's' : '' ?></span>
        </div>
        <a href="logout.php" class="sidebar-logout">Cerrar sesión</a>
    </div>
</aside>
