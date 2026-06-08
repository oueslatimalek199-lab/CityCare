<?php
session_start();
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';
$pdo = getConnexion();

$idService = (int)($_GET['id'] ?? 0);

if ($idService == 0) {
    header('Location: ./services_publiques.php');
    exit;
}

// Get service details
$stmt = $pdo->prepare("
    SELECT s.*, c.label AS nomCateg
    FROM service s
    LEFT JOIN categorie c ON s.idCateg = c.idCateg
    WHERE s.idService = ? AND s.statut = 'actif'
");
$stmt->execute([$idService]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    $_SESSION['error'] = 'Service introuvable';
    header('Location: ./services_publiques.php');
    exit;
}

// Get agents
$agents = $pdo->query("
    SELECT u.idUtilisateur, u.nom, u.prenom, u.email, u.telephone
    FROM service_agent sa
    JOIN utilisateur u ON sa.idUtilisateur = u.idUtilisateur
    WHERE sa.idService = $idService
")->fetchAll(PDO::FETCH_ASSOC);

// Header config
$headerConfig = [
    'title' => htmlspecialchars($service['nomService']),
    'subtitle' => 'Détails du service',
    'icon' => '🏢',
    'bgGradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<div class="detail-container">
    
    <div class="service-detail-card">
        <div class="detail-header">
            <h1><?= htmlspecialchars($service['nomService']) ?></h1>
            <span class="category-badge-large"><?= htmlspecialchars($service['nomCateg'] ?? 'Non catégorisé') ?></span>
        </div>
        
        <div class="detail-body">
            <!-- Description -->
            <section class="detail-section">
                <h2>📝 Description</h2>
                <p><?= nl2br(htmlspecialchars($service['descriptionService'])) ?></p>
            </section>
            
            <!-- Information -->
            <section class="detail-section">
                <h2>ℹ️ Informations de Contact</h2>
                <div class="info-grid">
                    <?php if (!empty($service['adresse'])): ?>
                        <div class="info-item">
                            <strong>📍 Adresse:</strong>
                            <p><?= htmlspecialchars($service['adresse']) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($service['telephone'])): ?>
                        <div class="info-item">
                            <strong>📞 Téléphone:</strong>
                            <p><a href="tel:<?= htmlspecialchars($service['telephone']) ?>">
                                <?= htmlspecialchars($service['telephone']) ?>
                            </a></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($service['email'])): ?>
                        <div class="info-item">
                            <strong>📧 Email:</strong>
                            <p><a href="mailto:<?= htmlspecialchars($service['email']) ?>">
                                <?= htmlspecialchars($service['email']) ?>
                            </a></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($service['horaire_debut']) && !empty($service['horaire_fin'])): ?>
                        <div class="info-item">
                            <strong>⏰ Horaires:</strong>
                            <p><?= htmlspecialchars($service['horaire_debut']) ?> - <?= htmlspecialchars($service['horaire_fin']) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($service['jours_ouverture'])): ?>
                        <div class="info-item">
                            <strong>📅 Jours d'ouverture:</strong>
                            <p><?= htmlspecialchars($service['jours_ouverture']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- Agents -->
            <?php if (!empty($agents)): ?>
                <section class="detail-section">
                    <h2>👥 Agents Responsables</h2>
                    <div class="agents-list">
                        <?php foreach ($agents as $agent): ?>
                            <div class="agent-card">
                                <h3><?= htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']) ?></h3>
                                <p><strong>📧 Email:</strong> <a href="mailto:<?= htmlspecialchars($agent['email']) ?>">
                                    <?= htmlspecialchars($agent['email']) ?>
                                </a></p>
                                <?php if (!empty($agent['telephone'])): ?>
                                    <p><strong>📞 Téléphone:</strong> <a href="tel:<?= htmlspecialchars($agent['telephone']) ?>">
                                        <?= htmlspecialchars($agent['telephone']) ?>
                                    </a></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- QR Code -->
            <section class="detail-section">
                <h2>🔲 Code QR</h2>
                <div id="qrcode" style="text-align: center; padding: 20px;"></div>
            </section>
        </div>
        
        <div class="detail-actions">
            <a href="./demander_service.php?service=<?= $idService ?>" class="btn btn-primary btn-large">
                ➕ Demander ce Service
            </a>
            <a href="./services_publiques.php" class="btn btn-secondary btn-large">
                ← Retour
            </a>
        </div>
    </div>
</div>

<style>
.detail-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}

.service-detail-card {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,0.1);
}

.detail-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.detail-header h1 {
    margin: 0;
    font-size: 32px;
}

.category-badge-large {
    background: rgba(255, 255, 255, 0.3);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
}

.detail-body {
    padding: 30px;
}

.detail-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.detail-section:last-child {
    border-bottom: none;
}

.detail-section h2 {
    margin-top: 0;
    margin-bottom: 15px;
    color: #333;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-item {
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
    border-left: 4px solid #667eea;
}

.info-item strong {
    display: block;
    margin-bottom: 8px;
    color: #333;
}

.info-item p {
    margin: 0;
    color: #666;
}

.info-item a {
    color: #667eea;
    text-decoration: none;
}

.agents-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
}

.agent-card {
    padding: 15px;
    background: #f0f7ff;
    border: 1px solid #c5d9f1;
    border-radius: 4px;
}

.agent-card h3 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #667eea;
}

.agent-card p {
    margin: 8px 0;
    font-size: 13px;
    color: #555;
}

.detail-actions {
    padding: 20px 30px;
    background: #f9f9f9;
    border-top: 1px solid #eee;
    display: flex;
    gap: 10px;
    justify-content: center;
}

.btn-large {
    padding: 12px 24px;
    font-size: 16px;
    min-width: 200px;
}

@media (max-width: 768px) {
    .detail-header {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .detail-header h1 {
        font-size: 24px;
    }
    
    .detail-actions {
        flex-direction: column;
    }
    
    .btn-large {
        width: 100%;
    }
}
</style>

<!-- QR Code Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const serviceName = "<?= htmlspecialchars($service['nomService']) ?>";
    const address = "<?= htmlspecialchars($service['adresse']) ?>";
    const phone = "<?= htmlspecialchars($service['telephone']) ?>";
    const email = "<?= htmlspecialchars($service['email']) ?>";
    const hours = "<?= htmlspecialchars($service['horaire_debut'] . ' - ' . $service['horaire_fin']) ?>";
    
    const qrData = "SERVICE: " + serviceName + "\n" +
                   "ADR: " + address + "\n" +
                   "TEL: " + phone + "\n" +
                   "EMAIL: " + email + "\n" +
                   "HEURES: " + hours;
    
    new QRCode(document.getElementById("qrcode"), {
        text: qrData,
        width: 250,
        height: 250,
        colorDark: "#667eea",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });
});
</script>