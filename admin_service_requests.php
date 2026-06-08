<?php
// admin_service_requests.php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';
require_once 'Classes_ServiceRequestManager.php'; // root-level version

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

if ($user['role'] !== 'admin') {
    Auth::redirectToDashboard();
}

$pdo = getConnexion();
$requestManager = new ServiceRequestManager($pdo);

// Handle reassignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reassign'])) {
    $idRequest      = (int)($_POST['idRequest']      ?? 0);
    $idNouveauAgent = (int)($_POST['idNouveauAgent'] ?? 0);

    if ($idRequest && $idNouveauAgent) {
        try {
            $requestManager->reassignerDemande($idRequest, $idNouveauAgent);
            $_SESSION['success'] = '✅ Demande réassignée avec succès';
        } catch (Exception $e) {
            $_SESSION['error'] = '❌ ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = '❌ Veuillez sélectionner un agent valide.';
    }

    header('Location: ./admin_service_requests.php');
    exit;
}

// Auto-mark overdue requests
$requestManager->marquerEnRetard();

// Get overdue requests
$demandesEnRetard = $requestManager->obtenirDemandesEnRetard();

// Get all active agents
$agents = $pdo->query("
    SELECT idUtilisateur, CONCAT(prenom, ' ', nom) as fullname
    FROM utilisateur
    WHERE role = 'agent' AND statut = 'actif'
    ORDER BY prenom, nom
")->fetchAll(PDO::FETCH_ASSOC);

// Get success/error messages
$successMsg = $_SESSION['success'] ?? '';
$errorMsg   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$headerConfig = [
    'title'       => 'Suivi des Demandes de Services',
    'subtitle'    => 'Gérez et réassignez les demandes de services en retard',
    'icon'        => '📊',
    'role'        => 'Administrateur',
    'profileLink' => './profil.php',
    'bgGradient'  => 'linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<div class="container">
    <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <h2>⚠️ Demandes en Retard (> 24h)</h2>

    <?php if (empty($demandesEnRetard)): ?>
        <div class="card">
            <p style="text-align:center;padding:30px;color:#28a745;font-size:16px;font-weight:600">
                ✅ Aucune demande en retard pour le moment !
            </p>
        </div>
    <?php else: ?>
        <div class="card">
            <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background-color:#f5f5f5;">
                        <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">#</th>
                        <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">Service</th>
                        <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">Citoyen</th>
                        <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">Agent actuel</th>
                        <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">Heures écoulées</th>
                        <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">Réassigner à</th>
                        <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($demandesEnRetard as $demande): ?>
                        <tr style="background-color:#fff5f5;border-bottom:1px solid #eee;border-left:4px solid #dc3545;">
                            <td style="padding:12px;font-weight:bold;">#<?= $demande['idRequest'] ?></td>
                            <td style="padding:12px;"><?= htmlspecialchars($demande['nomService']) ?></td>
                            <td style="padding:12px;"><?= htmlspecialchars($demande['citoyen_prenom'] . ' ' . $demande['citoyen_nom']) ?></td>
                            <td style="padding:12px;"><?= htmlspecialchars($demande['agent_prenom'] . ' ' . $demande['agent_nom']) ?></td>
                            <td style="padding:12px;color:red;font-weight:bold;"><?= $demande['heures_ecoulees'] ?>h</td>
                            <td style="padding:12px;">
                                <form method="POST" style="display:flex;gap:8px;align-items:center">
                                    <input type="hidden" name="idRequest" value="<?= $demande['idRequest'] ?>">
                                    <select name="idNouveauAgent" required style="padding:6px;border-radius:4px;border:1px solid #ccc;font-size:13px;">
                                        <option value="">-- Choisir --</option>
                                        <?php foreach ($agents as $agent): ?>
                                            <option value="<?= $agent['idUtilisateur'] ?>"
                                                <?= $agent['idUtilisateur'] == $demande['idAgent'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($agent['fullname']) ?>
                                                <?php if ($agent['idUtilisateur'] == $demande['idAgent']): ?> (Actuel)<?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="reassign" value="1"
                                        style="background:#667eea;color:white;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;font-weight:600;font-size:13px;">
                                        🔄 Réassigner
                                    </button>
                                </form>
                            </td>
                            <td style="padding:12px;">
                                <a href="./detail_service_request.php?id=<?= $demande['idRequest'] ?>"
                                    style="background:#17a2b8;color:white;padding:6px 12px;border-radius:4px;text-decoration:none;font-size:12px;font-weight:600;">
                                    👁️ Voir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>

    <div style="margin-top:25px;">
        <a href="./admin.php" style="display:inline-block;padding:10px 20px;background:#6c757d;color:white;text-decoration:none;border-radius:4px;font-weight:600;">
            ← Retour au tableau de bord
        </a>
    </div>
</div>

<style>
.container { max-width:1400px;margin:0 auto;padding:20px; }
.card { background:white;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);padding:20px;margin-bottom:20px; }
.alert { padding:15px;border-radius:6px;margin-bottom:20px;font-weight:600; }
.alert-success { background:#d4edda;color:#155724;border-left:4px solid #28a745; }
.alert-danger  { background:#fdecea;color:#b00020;border-left:4px solid #dc3545; }
</style>

<?php require_once 'includes/footer.php'; ?>