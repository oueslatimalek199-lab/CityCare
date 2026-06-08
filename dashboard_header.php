<?php
/**
 * includes/dashboard_header.php
 * Full HTML shell + coloured dashboard header bar.
 * Every dashboard page (citoyen, agent, admin, etc.) uses this.
 *
 * Usage:
 * $headerConfig = [
 *     'title'       => 'Page Title',
 *     'subtitle'    => 'Subtitle',
 *     'icon'        => '📊',
 *     'bgGradient'  => 'linear-gradient(...)',
 *     'role'        => 'Citoyen|Agent|Administrateur',
 *     'profileLink' => './profil.php'
 * ];
 * require_once 'includes/dashboard_header.php';
 */

require_once __DIR__ . '/../Auth/auth.php';
$currentUser = Auth::getUtilisateur();
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$showMessageBubble = false;
$unreadMessages = 0;

if ($currentUser && in_array($currentPage, ['citoyen.php', 'agent.php'], true) && in_array($currentUser['role'], ['citoyen', 'agent'], true)) {
    require_once __DIR__ . '/../Config/database.php';
    require_once __DIR__ . '/../Classes/MessageManager.php';

    try {
        $pdo = getConnexion();
        $messageManager = new MessageManager($pdo);
        $unreadMessages = $messageManager->compterNonLus($currentUser['idUtilisateur']);
        $showMessageBubble = true;
    } catch (Throwable $e) {
        $showMessageBubble = true;
        $unreadMessages = 0;
    }
}

$defaults = [
    'title'       => 'Tableau de bord',
    'subtitle'    => 'Bienvenue',
    'icon'        => '📊',
    'bgGradient'  => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    'profileLink' => './profil.php'
];
$config = array_merge($defaults, $headerConfig ?? []);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['title']) ?> — CityCare</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }
        .page-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        /* ===== DASHBOARD HEADER BAR ===== */
        .dashboard-header {
            color: white;
            padding: 28px 30px;
            border-radius: 10px;
            margin-bottom: 28px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 18px;
        }
        .header-title h1 { margin:0 0 6px 0;font-size:30px;color:white; }
        .header-subtitle  { margin:0;font-size:14px;color:rgba(255,255,255,.9); }
        .header-actions   { display:flex;gap:12px;align-items:center;flex-wrap:wrap; }
        .role-badge {
            background: rgba(255,255,255,.2);
            padding: 7px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .user-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            background: rgba(255,255,255,.12);
            padding: 7px 14px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,.2);
        }
        .user-name { font-size:13px;font-weight:600;white-space:nowrap; }
        .action-btn {
            padding: 5px 11px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            display: inline-block;
            transition: all .25s;
        }
        .profile-btn { background:rgba(255,255,255,.2);color:white;border:1px solid rgba(255,255,255,.3); }
        .profile-btn:hover { background:rgba(255,255,255,.3); }
        .logout-btn  { background:rgba(220,53,69,.55);color:white;border:1px solid rgba(220,53,69,.75); }
        .logout-btn:hover { background:rgba(220,53,69,.8); }

        /* ===== GLOBAL UTILITIES ===== */
        .btn { display:inline-block;padding:8px 16px;border:none;border-radius:4px;cursor:pointer;text-decoration:none;font-size:13px;font-weight:600;transition:all .3s; }
        .btn-primary  { background:#667eea;color:white; }
        .btn-primary:hover { background:#5568d3; }
        .btn-danger   { background:#dc3545;color:white; }
        .btn-danger:hover { background:#c82333; }
        .btn-sm { padding:5px 10px;font-size:12px; }

        .badge { display:inline-block;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600; }
        .badge-attente    { background:#fff3cd;color:#856404; }
        .badge-traitement { background:#d1ecf1;color:#0c5460; }
        .badge-resolu     { background:#d4edda;color:#155724; }
        .badge-annule     { background:#f8d7da;color:#721c24; }

        .alert-error   { background:#fdecea;color:#b00020;padding:14px;border-radius:6px;margin-bottom:20px;border-left:4px solid #b00020;font-weight:600; }
        .alert-success { background:#d4edda;color:#155724;padding:14px;border-radius:6px;margin-bottom:20px;border-left:4px solid #28a745;font-weight:600; }

        input[type="text"],input[type="email"],input[type="password"],input[type="tel"],
        input[type="time"],input[type="number"],textarea,select {
            width:100%; padding:9px 12px; border:1px solid #ddd; border-radius:6px;
            font-size:14px; font-family:inherit; outline:none; transition:border .2s;
            box-sizing:border-box; color:#333;
        }
        input:focus,textarea:focus,select:focus {
            border-color:#667eea; box-shadow:0 0 0 3px rgba(102,126,234,.1);
        }
        label { display:block;font-weight:600;margin-bottom:5px;color:#444;font-size:14px; }

        @media(max-width:768px){
            .dashboard-header { padding:18px; }
            .header-content   { flex-direction:column;text-align:center; }
            .header-title h1  { font-size:22px; }
            .header-actions   { justify-content:center;width:100%; }
            .user-actions     { flex-direction:column;gap:8px; }
        }

        .message-bubble-link {
            position: fixed;
            right: 24px;
            bottom: 24px;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            font-size: 28px;
            box-shadow: 0 12px 28px rgba(102,126,234,.35);
            z-index: 999;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .message-bubble-link:hover {
            transform: translateY(-2px) scale(1.03);
            box-shadow: 0 16px 32px rgba(102,126,234,.45);
        }
        .message-badge {
            position: absolute;
            top: -4px;
            right: -2px;
            min-width: 24px;
            height: 24px;
            padding: 0 6px;
            border-radius: 999px;
            background: #dc3545;
            color: white;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }
    </style>
</head>
<body>
<div class="page-wrapper">

    <div class="dashboard-header" style="background:<?= $config['bgGradient'] ?>">
        <div class="header-content">
            <div class="header-title">
                <h1><?= $config['icon'] ?> <?= htmlspecialchars($config['title']) ?></h1>
                <p class="header-subtitle"><?= htmlspecialchars($config['subtitle']) ?></p>
            </div>
            <div class="header-actions">

                <?php if ($currentUser): ?>
                <div class="user-actions">
                    <span class="user-name">
                        👤 <?= htmlspecialchars($currentUser['prenom'] . ' ' . $currentUser['nom']) ?>
                    </span>
                    <a href="<?= htmlspecialchars($config['profileLink']) ?>"
                       class="action-btn profile-btn" title="Mon profil">
                        Profil
                    </a>
                    <a href="./logout.php"
                       class="action-btn logout-btn" title="Se déconnecter">
                        Déconnexion
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($showMessageBubble): ?>
        <a href="./messages.php" class="message-bubble-link" title="Ouvrir la messagerie" aria-label="Ouvrir la messagerie">
            <span>💬</span>
            <?php if ($unreadMessages > 0): ?>
                <span class="message-badge"><?= $unreadMessages > 99 ? '99+' : $unreadMessages ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>

<?php /* The content of each page goes here. footer.php closes the page-wrapper div and body/html */ ?>
