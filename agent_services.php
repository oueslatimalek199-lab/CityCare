<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';
require_once 'Classes/servicerequestmanager.php'; // Classes subfolder version

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

if ($user['role'] !== 'agent') {
    Auth::redirectToDashboard();
}

$pdo = getConnexion();
$requestManager = new ServiceRequestManager($pdo);

// Handle request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']    ?? '';
    $idRequest = (int)($_POST['idRequest'] ?? 0);

    if ($action === 'accepter') {
        $notes = trim($_POST['notes'] ?? '');
        try {
            $requestManager->accepterDemande($idRequest, $user['idUtilisateur'], $notes);
            $_SESSION['success'] = '✅ Demande acceptée et citoyen notifié';
        } catch (Exception $e) {
            $_SESSION['error'] = '❌ ' . $e->getMessage();
        }
    } elseif ($action === 'refuser') {
        $motif = trim($_POST['motif'] ?? '');
        if (empty($motif)) {
            $_SESSION['error'] = '❌ Motif de refus obligatoire';
        } else {
            try {
                $requestManager->refuserDemande($idRequest, $user['idUtilisateur'], $motif);
                $_SESSION['success'] = '✅ Demande refusée et citoyen notifié';
            } catch (Exception $e) {
                $_SESSION['error'] = '❌ ' . $e->getMessage();
            }
        }
    }

    header('Location: ./agent_services.php');
    exit;
}

// Get pending requests for this agent
$demandesEnAttente = $requestManager->obtenirDemandesEnAttente($user['idUtilisateur']);

$successMsg = $_SESSION['success'] ?? '';
$errorMsg   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$headerConfig = [
    'title'       => 'Demandes de Services',
    'subtitle'    => 'Traitez les demandes de services assignées',
    'icon'        => '🔧',
    'role'        => 'Agent Municipal',
    'profileLink' => './profil.php',
    'bgGradient'  => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<?php if (!empty($successMsg)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>

<div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
        <h2>📋 Demandes de Services en Attente</h2>
        <a href="./agent.php" style="padding:8px 16px;background:#6c757d;color:white;text-decoration:none;border-radius:6px;font-weight:600;">
            ← Retour au tableau de bord
        </a>
    </div>

    <?php if (empty($demandesEnAttente)): ?>
        <div class="card">
            <p style="text-align:center;padding:40px;color:#28a745;font-size:16px;font-weight:600">
                ✅ Aucune demande à traiter pour le moment
            </p>
        </div>
    <?php else: ?>
        <div class="card">
            <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f5f5f5;">
                        <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">#</th>
                        <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">Service</th>
                        <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">Citoyen</th>
                        <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">Temps écoulé</th>
                        <th style="padding:12px;text-align:left;border-bottom:2px solid #ddd;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($demandesEnAttente as $demande): ?>
                        <tr style="background:<?= $demande['heures_ecoulees'] >= 20 ? '#fff3cd' : 'white' ?>;border-bottom:1px solid #eee;">
                            <td style="padding:12px;font-weight:bold;">#<?= $demande['idRequest'] ?></td>
                            <td style="padding:12px;"><?= htmlspecialchars($demande['nomService']) ?></td>
                            <td style="padding:12px;"><?= htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']) ?></td>
                            <td style="padding:12px;">
                                <span style="font-weight:600;color:<?= $demande['heures_ecoulees'] >= 20 ? '#dc3545' : '#333' ?>">
                                    <?= $demande['heures_ecoulees'] ?>h
                                </span>
                                <?php if ($demande['heures_ecoulees'] >= 20): ?>
                                    <span style="color:#dc3545;font-size:12px;"> ⚠️ Proche du délai</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px;">
                                <button onclick="afficherFormulaire(<?= $demande['idRequest'] ?>, 'accepter')"
                                        style="background:#28a745;color:white;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;margin-right:5px;font-weight:600;">
                                    ✅ Accepter
                                </button>
                                <button onclick="afficherFormulaire(<?= $demande['idRequest'] ?>, 'refuser')"
                                        style="background:#dc3545;color:white;border:none;padding:6px 12px;border-radius:4px;cursor:pointer;font-weight:600;">
                                    ❌ Refuser
                                </button>
                            </td>
                        </tr>
                        <tr style="background:#f9f9f9;border-bottom:1px solid #eee;">
                            <td colspan="5" style="padding:10px 12px;font-size:13px;color:#666;">
                                <strong>📝 Description :</strong>
                                <?= htmlspecialchars(substr($demande['description'], 0, 120)) ?>
                                <?= strlen($demande['description']) > 120 ? '...' : '' ?>
                                &nbsp;|&nbsp;
                                <strong>📧 Email :</strong> <?= htmlspecialchars($demande['citoyen_email']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL ACCEPTER -->
<div id="modalAccepter" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="modal-box">
        <h3>✅ Accepter la demande</h3>
        <form method="POST" action="./agent_services.php">
            <input type="hidden" name="action" value="accepter">
            <input type="hidden" name="idRequest" id="acceptIdRequest">
            <label style="display:block;font-weight:600;margin-bottom:8px;">Notes (optionnel)</label>
            <textarea name="notes" placeholder="Ajouter une note pour le citoyen..."></textarea>
            <div class="modal-buttons">
                <button type="button" class="modal-btn modal-btn-cancel"
                        onclick="document.getElementById('modalAccepter').classList.remove('open')">
                    Annuler
                </button>
                <button type="submit" class="modal-btn modal-btn-submit">✅ Confirmer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL REFUSER -->
<div id="modalRefuser" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="modal-box">
        <h3>❌ Refuser la demande</h3>
        <form method="POST" action="./agent_services.php">
            <input type="hidden" name="action" value="refuser">
            <input type="hidden" name="idRequest" id="refuserIdRequest">
            <label style="display:block;font-weight:600;margin-bottom:8px;">Motif de refus *</label>
            <textarea name="motif" placeholder="Expliquez pourquoi vous refusez cette demande..." required></textarea>
            <div class="modal-buttons">
                <button type="button" class="modal-btn modal-btn-cancel"
                        onclick="document.getElementById('modalRefuser').classList.remove('open')">
                    Annuler
                </button>
                <button type="submit" class="modal-btn modal-btn-submit">❌ Confirmer</button>
            </div>
        </form>
    </div>
</div>

<style>
.container { max-width:1200px;margin:0 auto;padding:20px; }
.card { background:white;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);padding:20px; }
.alert { padding:15px;border-radius:6px;margin-bottom:20px;font-weight:600; }
.alert-success { background:#d4edda;color:#155724;border-left:4px solid #28a745; }
.alert-danger  { background:#fdecea;color:#b00020;border-left:4px solid #dc3545; }
.modal-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center; }
.modal-overlay.open { display:flex; }
.modal-box { background:white;border-radius:12px;padding:30px;width:100%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,.3); }
.modal-box h3 { margin:0 0 20px 0;color:#333; }
.modal-box textarea { width:100%;height:120px;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;font-family:Arial,sans-serif;box-sizing:border-box;margin-bottom:15px;resize:vertical; }
.modal-buttons { display:flex;gap:10px;justify-content:flex-end; }
.modal-btn { padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:14px; }
.modal-btn-cancel { background:#f5f5f5;color:#333; }
.modal-btn-cancel:hover { background:#efefef; }
.modal-btn-submit { background:#667eea;color:white; }
.modal-btn-submit:hover { background:#5568d3; }
</style>

<script>
function afficherFormulaire(idRequest, type) {
    if (type === 'accepter') {
        document.getElementById('acceptIdRequest').value = idRequest;
        document.getElementById('modalAccepter').classList.add('open');
    } else if (type === 'refuser') {
        document.getElementById('refuserIdRequest').value = idRequest;
        document.getElementById('modalRefuser').classList.add('open');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>