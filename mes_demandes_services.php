<?php
// ===================== DÉMARRAGE & DÉBOGAGE =====================
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';

// Vérifier que l'utilisateur est connecté
Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

$pdo = getConnexion();

// ===================== RÉCUPÉRATION DES DEMANDES =====================
$stmt = $pdo->prepare("
    SELECT 
        d.idRequest,
        d.note,
        d.statut,
        d.dateCreation,
        d.dateModification,
        s.nomService,
        s.adresse,
        c.label AS categorie
    FROM demande_service d
    JOIN service s ON d.idService = s.idService
    LEFT JOIN categorie c ON s.idCateg = c.idCateg
    WHERE d.idUtilisateur = ?
    ORDER BY d.dateCreation DESC
");
$stmt->execute([$user['idUtilisateur']]);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===================== CONFIGURATION DU HEADER =====================
$headerConfig = [
    'title'       => 'Mes Demandes',
    'subtitle'    => 'Historique de vos demandes de services municipaux',
    'icon'        => '📜',
    'role'        => ucfirst($user['role']),
    'profileLink' => './profil.php',
    'bgGradient'  => 'linear-gradient(135deg, #10b981 0%, #059669 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<style>
.container {
    max-width: 1100px;
    margin: 30px auto;
    padding: 0 20px 60px;
}

.demande-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}

.demande-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.demande-title {
    font-size: 18px;
    font-weight: 700;
    color: #1f2937;
}

.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: white;
}

.badge.en-attente { background: #f59e0b; }
.badge.en-cours { background: #3b82f6; }
.badge.terminee { background: #10b981; }
.badge.annulee { background: #ef4444; }

.demande-info {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 10px;
}

.demande-note {
    background: #f9fafb;
    padding: 12px;
    border-radius: 8px;
    margin-top: 10px;
}

.no-demandes {
    text-align: center;
    padding: 50px;
    color: #6b7280;
}
</style>

<div class="container">
    <h2>📜 Historique de mes demandes</h2>

    <?php if (empty($demandes)): ?>
        <div class="no-demandes">
            <p>📭 Vous n'avez encore effectué aucune demande de service.</p>
            <a href="./services_publiques.php" class="btn btn-primary">
                ➕ Demander un service
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($demandes as $demande): 
            // Classe CSS pour le statut
            $statutClass = strtolower(str_replace(' ', '-', $demande['statut']));
        ?>
            <div class="demande-card">
                <div class="demande-header">
                    <div class="demande-title">
                        <?= htmlspecialchars($demande['nomService']) ?>
                    </div>
                    <span class="badge <?= $statutClass ?>">
                        <?= htmlspecialchars($demande['statut']) ?>
                    </span>
                </div>

                <div class="demande-info">
                    📂 <strong>Catégorie :</strong> <?= htmlspecialchars($demande['categorie'] ?? 'Non spécifiée') ?><br>
                    📍 <strong>Adresse :</strong> <?= htmlspecialchars($demande['adresse'] ?? 'Non spécifiée') ?><br>
                    📅 <strong>Date de création :</strong> <?= date('d/m/Y H:i', strtotime($demande['dateCreation'])) ?>
                    <?php if (!empty($demande['dateModification'])): ?>
                        <br>🔄 <strong>Dernière modification :</strong> <?= date('d/m/Y H:i', strtotime($demande['dateModification'])) ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($demande['note'])): ?>
                    <div class="demande-note">
                        📝 <strong>Note :</strong><br>
                        <?= nl2br(htmlspecialchars($demande['note'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <!-- ADD THIS -->
    <div style="margin-top:30px;text-align:center;">
        <a href="javascript:history.back()" 
           style="display:inline-block;padding:11px 28px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;transition:opacity .2s;">
            ← Retour
        </a>
    </div>
</div>

<?php
require_once 'includes/footer.php';
ob_end_flush();
?>
