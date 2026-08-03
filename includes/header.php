<?php
/**
 * Verwacht optioneel: $actief (string) om het actieve navigatie-item te markeren
 * Verwacht optioneel: $paginatitel (string)
 */
$actief = $actief ?? '';
$paginatitel = $paginatitel ?? EVENT_NAAM;
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($paginatitel) ?> — Meldkamer</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="topbar">
    <div class="topbar-inner">
        <a href="/index.php" class="brand">
            <span class="brand-mark">MK</span>
            <span>
                <span class="brand-name">Meldkamer</span><br>
                <span class="brand-event"><?= e(EVENT_NAAM) ?></span>
            </span>
        </a>
        <nav class="mainnav">
            <?php if (is_ingelogd()): ?>
                <a href="/index.php" class="<?= $actief === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="/melding_nieuw.php" class="<?= $actief === 'nieuw' ? 'active' : '' ?>">Nieuwe melding</a>
                <a href="/archief.php" class="<?= $actief === 'archief' ? 'active' : '' ?>">Archief</a>
                <?php if (is_beheerder()): ?>
                    <a href="/admin/index.php" class="<?= $actief === 'admin' ? 'active' : '' ?>">Beheer</a>
                <?php endif; ?>
                <span class="user-chip">
                    <?= e(huidige_gebruiker_naam()) ?>
                    <span class="rol-badge rol-<?= e(huidige_gebruiker_rol()) ?>"><?= e(rol_label(huidige_gebruiker_rol())) ?></span>
                </span>
                <a href="/admin/logout.php">Uitloggen</a>
            <?php else: ?>
                <a href="/admin/login.php" class="<?= $actief === 'login' ? 'active' : '' ?>">Inloggen</a>
            <?php endif; ?>
        </nav>
    </div>
</div>
<div class="wrap">
