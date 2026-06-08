<?php
require_once 'Config/database.php';
require_once 'Classes_ServiceManager.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$pdo = getConnexion();
$serviceManager = new ServiceManager($pdo);

$idService = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($idService === 0) {
    header('Location: reclamations_publiques.php');
    exit;
}

$service = $serviceManager->obtenir($idService);

if (!$service) {
    header('Location: reclamations_publiques.php');
    exit;
}

$agents = $serviceManager->obtenirAgents($idService);

// Header config
$headerConfig = [
    'title' => htmlspecialchars($service['nomService']),
    'subtitle' => 'Détails du service',
    'icon' => '🏢',
    'bgGradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<div style="max-width:1000px;margin:0 auto;padding:20px">
    
    <div class="card" style="margin-bottom:20px">
        <a href="./reclamations_publiques.php" style="color:#667eea;text-decoration:none;font-weight:600">← Retour</a>
    </div>

    <div class="card">
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:30px;align-items:start">
            
            <!-- Détails du service -->
            <div>
                <h2 style="margin-top:0"><?= htmlspecialchars($service['nomService']) ?></h2>
                
                <div style="background:#f9f9f9;padding:20px;border-radius:6px;margin-bottom:20px">
                    <p style="line-height:1.8">
                        <?= nl2br(htmlspecialchars($service['descriptionService'])) ?>
                    </p>
                </div>
                
                <div style="background:#f0f7ff;padding:15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #667eea">
                    <h3 style="margin-top:0;color:#667eea">📋 Informations</h3>
                    
                    <div style="display:flex;flex-direction:column;gap:15px">
                        <?php if ($service['adresse']): ?>
                            <div>
                                <strong>📍 Adresse:</strong><br>
                                <?= htmlspecialchars($service['adresse']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($service['telephone']): ?>
                            <div>
                                <strong>📞 Téléphone:</strong><br>
                                <a href="tel:<?= htmlspecialchars($service['telephone']) ?>" style="color:#667eea;text-decoration:none">
                                    <?= htmlspecialchars($service['telephone']) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($service['email']): ?>
                            <div>
                                <strong>📧 Email:</strong><br>
                                <a href="mailto:<?= htmlspecialchars($service['email']) ?>" style="color:#667eea;text-decoration:none">
                                    <?= htmlspecialchars($service['email']) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($service['horaire_debut'] && $service['horaire_fin']): ?>
                            <div>
                                <strong>⏰ Horaires:</strong><br>
                                Du <?= htmlspecialchars($service['jours_ouverture'] ?? 'Tous les jours') ?><br>
                                De <?= substr($service['horaire_debut'], 0, 5) ?> à <?= substr($service['horaire_fin'], 0, 5) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <strong>🏷️ Catégorie:</strong><br>
                            <?= htmlspecialchars($service['nomCateg']) ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($agents)): ?>
                    <div style="background:#fff3cd;padding:15px;border-radius:6px;border-left:4px solid #ffc107">
                        <h3 style="margin-top:0">👥 Agents Responsables</h3>
                        <?php foreach ($agents as $agent): ?>
                            <div style="padding:10px;background:white;border-radius:4px;margin-bottom:8px">
                                <strong><?= htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']) ?></strong><br>
                                <a href="mailto:<?= htmlspecialchars($agent['email']) ?>" style="color:#ffc107;text-decoration:none;font-size:13px">
                                    <?= htmlspecialchars($agent['email']) ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- QR Code -->
            <div style="background:#f9f9f9;padding:20px;border-radius:6px;text-align:center;position:sticky;top:20px">
                <h3 style="margin-top:0">🔗 Code QR</h3>
                
                <div id="qrcode" style="margin:20px 0"></div>
                
                <p style="color:#666;font-size:13px">
                    Scannez ce code pour accéder aux détails du service
                </p>
                
                <button onclick="window.print()" style="padding:10px 20px;background:#667eea;color:white;border:none;border-radius:6px;cursor:pointer;width:100%;font-weight:600;margin-top:15px">
                    🖨️ Imprimer
                </button>
            </div>
        </div>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const data = "SERVICE: <?= htmlspecialchars($service['nomService']) ?>\nADR: <?= htmlspecialchars($service['adresse']) ?>\nTEL: <?= htmlspecialchars($service['telephone']) ?>\nEMAIL: <?= htmlspecialchars($service['email']) ?>\nHEURES: <?= htmlspecialchars($service['horaire_debut'] . ' - ' . $service['horaire_fin']) ?>";
    new QRCode(document.getElementById("qrcode"), {
        text: data,
        width: 250,
        height: 250,
        colorDark: "#667eea",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });
});
</script>

<style>
.card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

@media print {
    body > *:not(.card) { display: none; }
    .card { box-shadow: none; border: 1px solid #ddd; }
}
</style>

<?php require_once 'includes/footer.php'; ?>