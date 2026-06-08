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

// Get service info if coming from service detail
$idService = (int)($_GET['service'] ?? 0);
$service   = null;

if ($idService) {
    $svc = $pdo->prepare("
        SELECT s.*, c.label AS nomCateg
        FROM service s
        LEFT JOIN categorie c ON s.idCateg = c.idCateg
        WHERE s.idService = ? AND s.statut = 'actif'
    ");
    $svc->execute([$idService]);
    $service = $svc->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        $_SESSION['error'] = 'Service introuvable';
        header('Location: ./services_publiques.php');
        exit;
    }
}

$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idService   = (int)($_POST['idService']   ?? 0);
    $description = trim($_POST['description']  ?? '');

    if (!$idService) {
        $errorMsg = 'Veuillez sélectionner un service';
    } elseif (empty($description)) {
        $errorMsg = 'La description est obligatoire';
    } elseif (strlen($description) < 10) {
        $errorMsg = 'La description doit contenir au moins 10 caractères';
    } else {
        try {
            // creerDemande is an alias of soumettre() in Classes/ServiceRequestManager.php
            $idRequest = $requestManager->creerDemande($idService, $user['idUtilisateur'], $description);
            $_SESSION['success'] = '✅ Demande de service créée avec succès ! Référence : #' . $idRequest;
            header('Location: ./citoyen_services.php');
            exit;
        } catch (Exception $e) {
            $errorMsg = '❌ ' . $e->getMessage();
        }
    }
}

// Get all active services
$services = $pdo->query("
    SELECT s.*, c.label AS nomCateg
    FROM service s
    LEFT JOIN categorie c ON s.idCateg = c.idCateg
    WHERE s.statut = 'actif'
    ORDER BY s.nomService
")->fetchAll(PDO::FETCH_ASSOC);

$headerConfig = [
    'title'       => 'Demander un Service',
    'subtitle'    => 'Soumettez une demande de service municipal',
    'icon'        => '🔧',
    'role'        => 'Citoyen',
    'profileLink' => './profil.php',
    'bgGradient'  => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<div class="container">
    <h2>🔧 Demander un Service</h2>

    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <div style="margin-bottom:20px;">
                <label style="display:block;font-weight:600;margin-bottom:8px;color:#333;">Service *</label>
                <select name="idService" required
                        style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;">
                    <option value="">-- Sélectionnez un service --</option>
                    <?php foreach ($services as $svc): ?>
                        <option value="<?= $svc['idService'] ?>"
                            <?= ($service && $svc['idService'] == $service['idService']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($svc['nomService']) ?>
                            (<?= htmlspecialchars($svc['nomCateg'] ?? '') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block;font-weight:600;margin-bottom:8px;color:#333;">Description *</label>
                <textarea name="description" required
                          placeholder="Décrivez votre demande en détail..."
                          style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;height:150px;font-family:Arial,sans-serif;box-sizing:border-box;resize:vertical;"></textarea>
                <small style="color:#999;">Minimum 10 caractères</small>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="submit"
                        style="background:#28a745;color:white;padding:12px 25px;border:none;border-radius:6px;cursor:pointer;font-weight:600;">
                    ✅ Soumettre la demande
                </button>
                <a href="./citoyen_services.php"
                   style="background:#6c757d;color:white;padding:12px 25px;border-radius:6px;text-decoration:none;font-weight:600;">
                    ✕ Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<style>
.container { max-width:800px;margin:0 auto;padding:20px; }
.card { background:white;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);padding:30px; }
.alert { padding:15px;border-radius:6px;margin-bottom:20px;font-weight:600; }
.alert-danger { background:#fdecea;color:#b00020;border-left:4px solid #dc3545; }
</style>

<?php require_once 'includes/footer.php'; ?>