<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';
require_once 'Classes/servicerequestmanager.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

if ($user['role'] !== 'citoyen') {
    Auth::redirectToDashboard();
}

$pdo = getConnexion();
$requestManager = new ServiceRequestManager($pdo);
$mesDemandes = $requestManager->obtenirDemandesCitoyen($user['idUtilisateur']);

$headerConfig = [
    'title' => 'Mes Demandes de Services',
    'subtitle' => 'Suivez vos demandes de services municipaux',
    'icon' => '📋',
    'role' => 'Citoyen',
    'profileLink' => './profil.php',
    'bgGradient' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
];
require_once 'includes/dashboard_header.php';
?>

<div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
        <h2>Mes demandes de services</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="./services_publiques.php" style="padding:8px 16px;background:linear-gradient(135deg,#4facfe,#00f2fe);color:white;text-decoration:none;border-radius:6px;font-weight:600;">
                Demander un service
            </a>
            <a href="./messages.php" style="padding:8px 16px;background:#28a745;color:white;text-decoration:none;border-radius:6px;font-weight:600;">
                Messagerie
            </a>
            <a href="./citoyen.php" style="padding:8px 16px;background:#6c757d;color:white;text-decoration:none;border-radius:6px;font-weight:600;">
                Retour
            </a>
        </div>
    </div>

    <?php if (empty($mesDemandes)): ?>
        <div class="card" style="text-align:center;padding:50px;">
            <div style="font-size:48px;margin-bottom:15px;">📭</div>
            <h3 style="color:#999;">Vous n avez pas encore soumis de demande de service</h3>
            <a href="./services_publiques.php" style="display:inline-block;margin-top:20px;background:linear-gradient(135deg,#4facfe,#00f2fe);color:white;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;">
                Decouvrir les services disponibles
            </a>
        </div>
    <?php else: ?>
        <div class="card">
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f5f5f5;">
                            <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">#</th>
                            <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">Service</th>
                            <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">Statut</th>
                            <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">Agent assigne</th>
                            <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">Date</th>
                            <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mesDemandes as $demande): ?>
                            <?php
                            $statusColors = [
                                'en_attente' => ['bg' => '#fff3cd', 'color' => '#856404', 'text' => 'En attente'],
                                'en attente' => ['bg' => '#fff3cd', 'color' => '#856404', 'text' => 'En attente'],
                                'assignée' => ['bg' => '#d1ecf1', 'color' => '#0c5460', 'text' => 'Assignee'],
                                'assignéee' => ['bg' => '#d1ecf1', 'color' => '#0c5460', 'text' => 'Assignee'],
                                'acceptée' => ['bg' => '#d4edda', 'color' => '#155724', 'text' => 'Acceptee'],
                                'acceptéee' => ['bg' => '#d4edda', 'color' => '#155724', 'text' => 'Acceptee'],
                                'refusée' => ['bg' => '#f8d7da', 'color' => '#721c24', 'text' => 'Refusee'],
                                'en_retard' => ['bg' => '#fff3cd', 'color' => '#856404', 'text' => 'En retard'],
                            ];
                            $status = $statusColors[$demande['statut']] ?? ['bg' => '#eee', 'color' => '#333', 'text' => $demande['statut']];
                            ?>
                            <tr style="background:white;border-bottom:1px solid #eee;">
                                <td style="padding:12px;font-weight:bold;">#<?= (int) $demande['idRequest'] ?></td>
                                <td style="padding:12px;"><?= htmlspecialchars($demande['nomService']) ?></td>
                                <td style="padding:12px;">
                                    <span style="background:<?= $status['bg'] ?>;color:<?= $status['color'] ?>;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;">
                                        <?= htmlspecialchars($status['text']) ?>
                                    </span>
                                </td>
                                <td style="padding:12px;">
                                    <?php if (!empty($demande['agent_prenom'])): ?>
                                        <?= htmlspecialchars($demande['agent_prenom'] . ' ' . $demande['agent_nom']) ?>
                                    <?php else: ?>
                                        <span style="color:#999;">En attente d assignation</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px;font-size:13px;"><?= date('d/m/Y H:i', strtotime($demande['dateCreation'])) ?></td>
                                <td style="padding:12px;">
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <a href="./detail_service_request.php?id=<?= (int) $demande['idRequest'] ?>" style="background:#667eea;color:white;padding:6px 12px;border-radius:5px;text-decoration:none;font-size:12px;font-weight:600;">
                                            Voir
                                        </a>
                                        <?php if (!empty($demande['idAgent'])): ?>
                                            <a href="./nouvelle_conversation.php?idRequest=<?= (int) $demande['idRequest'] ?>" style="background:#28a745;color:white;padding:6px 12px;border-radius:5px;text-decoration:none;font-size:12px;font-weight:600;">
                                                Message
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php if (!empty($demande['motifRefus'])): ?>
                                <tr style="background:#fff5f5;border-bottom:1px solid #eee;">
                                    <td colspan="6" style="padding:10px 12px;font-size:13px;color:#721c24;">
                                        <strong>Motif du refus :</strong> <?= htmlspecialchars($demande['motifRefus']) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.container { max-width:1200px; margin:0 auto; padding:20px; }
.card { background:white; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,.1); padding:20px; }
</style>

<?php require_once 'includes/footer.php'; ?>
