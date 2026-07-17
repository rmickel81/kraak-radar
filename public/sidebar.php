<?php
$currentFile = basename($_SERVER['PHP_SELF']);
$projectCount = DB::fetchOne("SELECT COUNT(*) as c FROM projects WHERE user_id = ?", [$user['id']])['c'] ?? 0;

// Iconos SVG inline
function s($name) {
    $icons = [
        'dashboard' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
        'prompts' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'competitors' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5C7 4 6 9 6 9z"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5C17 4 18 9 18 9z"/><path d="M4 22h16"/><path d="M10 22V8h4v14"/><path d="M8 22V2h8v20"/></svg>',
        'sources' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
        'costs' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'settings' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
        'export' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
        'logo' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#58a6ff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/><line x1="12" y1="12" x2="19" y2="5"/></svg>',
    ];
    return $icons[$name] ?? '';
}
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="sidebar-logo"><?= s('logo') ?></span>
        <span class="sidebar-title">Kraak Radar</span>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="sidebar-link <?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">
            <span class="s-icon"><?= s('dashboard') ?></span>
            <span>Dashboard</span>
        </a>
        <a href="prompts.php" class="sidebar-link <?= $currentFile === 'prompts.php' ? 'active' : '' ?>">
            <span class="s-icon"><?= s('prompts') ?></span>
            <span>Prompts</span>
        </a>
        <a href="competitors.php" class="sidebar-link <?= $currentFile === 'competitors.php' ? 'active' : '' ?>">
            <span class="s-icon"><?= s('competitors') ?></span>
            <span>Competidores</span>
        </a>
        <a href="sources.php" class="sidebar-link <?= $currentFile === 'sources.php' ? 'active' : '' ?>">
            <span class="s-icon"><?= s('sources') ?></span>
            <span>Fuentes</span>
        </a>
        <a href="costs.php" class="sidebar-link <?= $currentFile === 'costs.php' ? 'active' : '' ?>">
            <span class="s-icon"><?= s('costs') ?></span>
            <span>Costes</span>
        </a>
        <a href="settings.php" class="sidebar-link <?= $currentFile === 'settings.php' ? 'active' : '' ?>">
            <span class="s-icon"><?= s('settings') ?></span>
            <span>APIs</span>
        </a>
        <a href="export.php" class="sidebar-link <?= $currentFile === 'export.php' ? 'active' : '' ?>">
            <span class="s-icon"><?= s('export') ?></span>
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
        <a href="logout.php" class="sidebar-logout">Cerrar sesion</a>
    </div>
</aside>
