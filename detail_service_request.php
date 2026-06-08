<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';
require_once 'Classes/servicerequestmanager.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

$pdo = getConnexion();
$requestManager = new ServiceRequestManager($pdo);

$idRequest = (int)($_GET['id'] ?? 0);

if (!$idRequest) {
    header('Location: ./citoyen_services.php');
    exit;
}

$demande = $requestManager->obtenirDemande($idRequest);

if (!$demande) {
    $_SESSION['error'] = 'Demande introuvable.';
    header('Location: ./citoyen_services.php');
    exit;
}

if ($user['role'] === 'citoyen' && $demande['idCitoyen'] != $user['idUtilisateur'] && $demande['idUtilisateur'] != $user['idUtilisateur']) {
    header('Location: ./citoyen_services.php');
    exit;
}

if ($user['role'] === 'agent' && $demande['idAgent'] != $user['idUtilisateur']) {
    header('Location: ./agent_services.php');
    exit;
}

$statusColors = [
    'en_attente' => ['bg' => '#fff3cd', 'color' => '#856404', 'text' => 'En attente'],
    'en attente' => ['bg' => '#fff3cd', 'color' => '#856404', 'text' => 'En attente'],
    'assignée'   => ['bg' => '#d1ecf1', 'color' => '#0c5460', 'text' => 'Assignee'],
    'acceptée'   => ['bg' => '#d4edda', 'color' => '#155724', 'text' => 'Acceptee'],
    'refusée'    => ['bg' => '#f8d7da', 'color' => '#721c24', 'text' => 'Refusee'],
    'en_retard'  => ['bg' => '#fff3cd', 'color' => '#856404', 'text' => 'En retard'],
];
$status = $statusColors[$demande['statut']] ?? ['bg' => '#eee', 'color' => '#333', 'text' => $demande['statut']];

$headerConfig = [
    'title'       => 'Detail Demande de Service',
    'subtitle'    => 'Consultez les details de la demande',
    'icon'        => '📋',
    'role'        => ucfirst($user['role']),
    'profileLink' => './profil.php',
    'bgGradient'  => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<div class="container">
    <h2>Demande de Service #<?= $demande['idRequest'] ?></h2>

    <div class="card">
        <h3><?= htmlspecialchars($demande['nomService']) ?></h3>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
            <div>
                <p><strong>Service :</strong></p>
                <p style="margin-top:5px;color:#666;"><?= htmlspecialchars($demande['nomService']) ?></p>
            </div>
            <div>
                <p><strong>Statut :</strong></p>
                <p style="margin-top:5px;">
                    <span style="background:<?= $status['bg'] ?>;color:<?= $status['color'] ?>;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;">
                        <?= htmlspecialchars($status['text']) ?>
                    </span>
                </p>
            </div>
        </div>

        <div style="margin-top:20px;padding:15px;background:#f9f9f9;border-radius:6px;">
            <p><strong>Description :</strong></p>
            <p style="margin-top:10px;line-height:1.6;color:#555;">
                <?= nl2br(htmlspecialchars($demande['description'])) ?>
            </p>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
            <div>
                <p><strong>Citoyen :</strong></p>
                <p style="margin-top:5px;color:#666;"><?= htmlspecialchars($demande['citoyen_prenom'] . ' ' . $demande['citoyen_nom']) ?></p>
                <p style="margin-top:3px;color:#999;font-size:12px;"><?= htmlspecialchars($demande['citoyen_email']) ?></p>
            </div>
            <div>
                <p><strong>Agent assigne :</strong></p>
                <?php if (!empty($demande['agent_prenom'])): ?>
                    <p style="margin-top:5px;color:#666;"><?= htmlspecialchars($demande['agent_prenom'] . ' ' . $demande['agent_nom']) ?></p>
                    <p style="margin-top:3px;color:#999;font-size:12px;"><?= htmlspecialchars($demande['agent_email']) ?></p>
                <?php else: ?>
                    <p style="margin-top:5px;color:#999;">En attente d assignation</p>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
            <div>
                <p><strong>Date de creation :</strong></p>
                <p style="margin-top:5px;color:#666;"><?= date('d/m/Y H:i', strtotime($demande['dateCreation'])) ?></p>
            </div>
            <div>
                <p><strong>Derniere modification :</strong></p>
                <p style="margin-top:5px;color:#666;">
                    <?php if (!empty($demande['dateModification'])): ?>
                        <?= date('d/m/Y H:i', strtotime($demande['dateModification'])) ?>
                    <?php else: ?>
                        <span style="color:#999;">-</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (!empty($demande['motifRefus'])): ?>
            <div style="margin-top:20px;padding:15px;background:#f8d7da;border-radius:6px;border-left:4px solid #721c24;">
                <p><strong>Motif du refus :</strong></p>
                <p style="margin-top:10px;color:#721c24;">
                    <?= nl2br(htmlspecialchars($demande['motifRefus'])) ?>
                </p>
            </div>
        <?php endif; ?>

        <div style="margin-top:25px;display:flex;gap:10px;flex-wrap:wrap;">
            <?php if ($user['role'] === 'citoyen'): ?>
                <a href="./citoyen_services.php" style="background:#667eea;color:white;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;">
                    Mes demandes
                </a>
            <?php elseif ($user['role'] === 'agent'): ?>
                <a href="./agent_services.php" style="background:#667eea;color:white;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;">
                    Retour
                </a>
            <?php elseif ($user['role'] === 'admin'): ?>
                <a href="./admin_service_requests.php" style="background:#667eea;color:white;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;">
                    Retour
                </a>
            <?php endif; ?>

            <?php if (!empty($demande['idAgent']) && in_array($user['role'], ['citoyen', 'agent'], true)): ?>
                <a href="./nouvelle_conversation.php?idRequest=<?= (int) $demande['idRequest'] ?>" style="background:#28a745;color:white;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;">
                    Ouvrir la messagerie
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.container { max-width:1000px;margin:0 auto;padding:20px; }
.card { background:white;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);padding:30px; }
.card h3 { margin:0 0 10px 0;color:#333;font-size:22px; }
</style>

<?php require_once 'includes/footer.php'; ?>
