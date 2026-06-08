<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';
require_once 'Classes_ServiceManager.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';
if ($user['role'] !== 'admin') {
    Auth::redirectToDashboard();
}

$pdo = getConnexion();
$serviceManager = new ServiceManager($pdo);

$message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$erreur = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assigner_agent') {
    $action = $_POST['action'];
    $idService = (int)($_POST['idService'] ?? 0);
    $idUtilisateur = (int)($_POST['idUtilisateur'] ?? 0);

    if ($idService === 0 || $idUtilisateur === 0) {
        $_SESSION['error'] = 'Données invalides.';
    } else {
        try {
            // 1) Assigner
            $serviceManager->assignerAgent($idService, $idUtilisateur);

            // 2) Récupérer infos
            $agent = $serviceManager->getAgentById($idUtilisateur);
            $service = $serviceManager->obtenir($idService); // 👈 Utilise obtenir() existant

            // 3) Email avec PHPMailer + DESIGN
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'amaltoumi535@gmail.com';
            $mail->Password   = 'crmr mydm sqtn zdqn';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('amaltoumi535@gmail.com', 'Plateforme CityCare');
            $mail->addAddress($agent['email'], $agent['prenom'] . ' ' . $agent['nom']);

            $mail->isHTML(true);
            $mail->Subject = "🔔 Nouveau service assigné - " . htmlspecialchars($service['nomService']);

            // 👉 VOICI LE DESIGN QUI VA MARCHER
            $mail->Body = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 8px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
                    .header h2 { margin: 0; font-size: 20px; }
                    .content { background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea; }
                    .details { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 15px 0; }
                    .detail-row { margin: 10px 0; }
                    .label { font-weight: bold; color: #667eea; }
                    .button { display: inline-block; background: #667eea; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin-top: 15px; font-weight: 600; }
                    .footer { margin-top: 20px; font-size: 12px; color: #999; text-align: center; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h2>🔔 Nouveau Service Assigné</h2>
                    </div>
                    <div class="content">
                        <p>Bonjour <strong>' . htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']) . '</strong>,</p>
                        <p>Vous avez été assigné au service suivant :</p>

                        <div class="details">
                            <div class="detail-row">
                                <span class="label">📌 Service :</span> ' . htmlspecialchars($service['nomService']) . '
                            </div>
                            <div class="detail-row">
                                <span class="label">📋 Description :</span> ' . htmlspecialchars(mb_substr($service['descriptionService'], 0, 150)) . (strlen($service['descriptionService']) > 150 ? '...' : '') . '
                            </div>
                            <div class="detail-row">
                                <span class="label">📍 Adresse :</span> ' . htmlspecialchars($service['adresse']) . '
                            </div>
                            <div class="detail-row">
                                <span class="label">📞 Téléphone :</span> ' . htmlspecialchars($service['telephone']) . '
                            </div>
                            <div class="detail-row">
                                <span class="label">📧 Email :</span> ' . htmlspecialchars($service['email']) . '
                            </div>
                            <div class="detail-row">
                                <span class="label">⏰ Horaires :</span> ' . htmlspecialchars($service['horaire_debut']) . ' - ' . htmlspecialchars($service['horaire_fin']) . '
                            </div>
                        </div>

                        <p>Veuillez consulter votre tableau de bord pour connaître les détails complets et commencer le suivi du service.</p>
                        <a href="http://localhost/AGL/agent.php" class="button">Accéder à mon tableau de bord</a>

                        <div class="footer">
                            <p>Cet email a été envoyé automatiquement. Veuillez ne pas répondre.</p>
                            <p>Plateforme CityCare — Gestion des Services Municipaux</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>';

            $mail->send();

            $_SESSION['success'] = "Agent assigné et email envoyé avec succès! ✓";

        } catch (Exception $e) {
            error_log("Email error (gestion_services.php): " . $e->getMessage());
            $_SESSION['error'] = "Erreur : " . $e->getMessage();
        }
    }

    header('Location: gestion_services.php');
    exit;
}

// Traiter les actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // ===== AJOUTER SERVICE =====
        if ($_POST['action'] === 'ajouter_service') {
            $nomService = trim($_POST['nomService'] ?? '');
            $descriptionService = trim($_POST['descriptionService'] ?? '');
            $idCateg = (int)($_POST['idCateg'] ?? 0);
            $adresse = trim($_POST['adresse'] ?? '');
            $telephone = trim($_POST['telephone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $horaire_debut = $_POST['horaire_debut'] ?? '';
            $horaire_fin = $_POST['horaire_fin'] ?? '';
            $jours_ouverture = trim($_POST['jours_ouverture'] ?? '');
            
            if (empty($nomService) || $idCateg === 0) {
                $_SESSION['error'] = 'Tous les champs requis doivent être remplis.';
            } else {
                try {
                    $serviceManager->creer($nomService, $descriptionService, $idCateg, $adresse, $telephone, $email, $horaire_debut, $horaire_fin, $jours_ouverture);
                    $_SESSION['success'] = "Service '$nomService' créé avec succès!";
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                }
            }
            header('Location: gestion_services.php');
            exit;
        }
        
        // ===== MODIFIER SERVICE =====
        elseif ($_POST['action'] === 'modifier_service') {
            $idService = (int)($_POST['idService'] ?? 0);
            $nomService = trim($_POST['nomService'] ?? '');
            $descriptionService = trim($_POST['descriptionService'] ?? '');
            $idCateg = (int)($_POST['idCateg'] ?? 0);
            $adresse = trim($_POST['adresse'] ?? '');
            $telephone = trim($_POST['telephone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $horaire_debut = $_POST['horaire_debut'] ?? '';
            $horaire_fin = $_POST['horaire_fin'] ?? '';
            $jours_ouverture = trim($_POST['jours_ouverture'] ?? '');
            
            if ($idService === 0 || empty($nomService)) {
                $_SESSION['error'] = 'Données invalides.';
            } else {
                try {
                    $serviceManager->modifier($idService, $nomService, $descriptionService, $idCateg, $adresse, $telephone, $email, $horaire_debut, $horaire_fin, $jours_ouverture);
                    $_SESSION['success'] = "Service modifié avec succès!";
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                }
            }
            header('Location: gestion_services.php');
            exit;
        }
        
        // ===== SUPPRIMER SERVICE =====
        elseif ($_POST['action'] === 'supprimer_service') {
            $idService = (int)($_POST['idService'] ?? 0);
            
            if ($idService === 0) {
                $_SESSION['error'] = 'ID de service invalide.';
            } else {
                try {
                    $serviceManager->supprimer($idService);
                    $_SESSION['success'] = "Service supprimé avec succès!";
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                }
            }
            header('Location: gestion_services.php');
            exit;
        }
        
        // ===== CHANGER STATUT =====
        elseif ($_POST['action'] === 'changer_statut') {
            $idService = (int)($_POST['idService'] ?? 0);
            $statut = $_POST['statut'] ?? '';
            
            if ($idService === 0 || !in_array($statut, ['actif', 'inactif'])) {
                $_SESSION['error'] = 'Données invalides.';
            } else {
                try {
                    $serviceManager->changerStatut($idService, $statut);
                    $_SESSION['success'] = "Statut changé avec succès!";
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                }
            }
            header('Location: gestion_services.php');
            exit;
        }
        
        // ===== ASSIGNER AGENT =====
        elseif ($_POST['action'] === 'assigner_agent') {
    $idService = (int)($_POST['idService'] ?? 0);
    $idUtilisateur = (int)($_POST['idUtilisateur'] ?? 0);

    if ($idService === 0 || $idUtilisateur === 0) {
        $_SESSION['error'] = 'Données invalides.';
    } else {
        try {
            // 1) Assigner
            $serviceManager->assignerAgent($idService, $idUtilisateur);

            // 2) Récupérer infos
            $agent = $serviceManager->getAgentById($idUtilisateur);
            $service = $serviceManager->obtenir($idService);

            // 3) Email avec PHPMailer
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'amaltoumi535@gmail.com';
            $mail->Password   = 'crmr mydm sqtn zdqn';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('amaltoumi535@gmail.com', 'Gestion Services Municipaux');
            $mail->addAddress($agent['email'], $agent['nom']);

            $mail->isHTML(true);
            $mail->Subject = 'Nouveau service assigné';
            $mail->Body = "
                Bonjour <b>{$agent['nom']}</b>,<br><br>
                Vous avez été assigné au service : <b>{$service['nomService']}</b>.<br>
                Connectez-vous à la plateforme pour consulter les détails.<br><br>
                Cordialement.
            ";

            $mail->send();

            $_SESSION['success'] = "Agent assigné et email envoyé avec succès!";

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
    }

    header('Location: gestion_services.php');
    exit;
}
        // ===== RETIRER AGENT =====
        elseif ($_POST['action'] === 'retirer_agent') {
            $idService = (int)($_POST['idService'] ?? 0);
            $idUtilisateur = (int)($_POST['idUtilisateur'] ?? 0);
            
            if ($idService === 0 || $idUtilisateur === 0) {
                $_SESSION['error'] = 'Données invalides.';
            } else {
                try {
                    $serviceManager->retirerAgent($idService, $idUtilisateur);
                    $_SESSION['success'] = "Agent retiré avec succès!";
                } catch (Exception $e) {
                    $_SESSION['error'] = $e->getMessage();
                }
            }
            header('Location: gestion_services.php');
            exit;
        }
    }
}

// Récupérer les services
$services = $serviceManager->obtenirTous();

// Récupérer les catégories
$categories = $pdo->query("SELECT idCateg, label FROM categorie ORDER BY label")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les agents
$agents = $pdo->query("
    SELECT idUtilisateur, prenom, nom, email
    FROM utilisateur
    WHERE role = 'agent' AND statut = 'actif'
    ORDER BY prenom, nom
")->fetchAll(PDO::FETCH_ASSOC);

// Header config
$headerConfig = [
    'title' => 'Gestion des Services',
    'subtitle' => 'Gérez les services municipaux et leurs agents',
    'icon' => '🏢',
    'role' => 'Administrateur',
    'profileLink' => './profil.php',
    'bgGradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<?php if ($message): ?>
    <div class="alert-success" style="max-width:1200px;margin:20px auto">✅ <?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($erreur): ?>
    <div class="alert-error" style="max-width:1200px;margin:20px auto">❌ <?= htmlspecialchars($erreur) ?></div>
<?php endif; ?>

<div style="max-width:1200px;margin:0 auto;padding:20px">
    
    <!-- ===== STATISTIQUES ===== -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:30px">
        <div class="card stat-box">
            <h3 style="color:#667eea"><?= count($services) ?></h3>
            <p>Total services</p>
        </div>
        <div class="card stat-box">
            <h3 style="color:#1cc88a"><?= count(array_filter($services, fn($s) => $s['statut'] === 'actif')) ?></h3>
            <p>Services actifs</p>
        </div>
        <div class="card stat-box">
            <h3 style="color:#e74a3b"><?= count(array_filter($services, fn($s) => $s['statut'] === 'inactif')) ?></h3>
            <p>Services inactifs</p>
        </div>
    </div>

    <!-- ===== BOUTON AJOUTER SERVICE ===== -->
    <div class="card" style="margin-bottom:20px">
        <button onclick="document.getElementById('modalAjouterService').classList.add('open')" 
                style="padding:12px 24px;background:linear-gradient(135deg, #f093fb 0%, #f5576c 100%);color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:14px">
            ➕ Ajouter un Service
        </button>
    </div>

    <!-- ===== TABLEAU SERVICES ===== -->
    <div class="card">
        <h2>📋 Tous les Services</h2>
        
        <?php if (empty($services)): ?>
            <p style="text-align:center;color:#999;padding:20px">Aucun service créé pour le moment</p>
        <?php else: ?>
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead style="background:#f5f5f5">
                        <tr>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd">Nom</th>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd">Catégorie</th>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd">Téléphone</th>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd">Horaires</th>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd">Statut</th>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr style="border-bottom:1px solid #eee">
                                <td style="padding:12px">
                                    <strong><?= htmlspecialchars($service['nomService']) ?></strong><br>
                                    <small style="color:#666"><?= htmlspecialchars(substr($service['descriptionService'], 0, 40)) ?>...</small>
                                </td>
                                <td style="padding:12px;font-size:13px"><?= htmlspecialchars($service['nomCateg']) ?></td>
                                <td style="padding:12px;font-size:13px"><?= htmlspecialchars($service['telephone'] ?? '—') ?></td>
                                <td style="padding:12px;font-size:13px">
                                    <?php if ($service['horaire_debut'] && $service['horaire_fin']): ?>
                                        <?= substr($service['horaire_debut'], 0, 5) ?> - <?= substr($service['horaire_fin'], 0, 5) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td style="padding:12px">
                                    <span style="background:<?= $service['statut'] === 'actif' ? '#d4edda' : '#f8d7da' ?>;color:<?= $service['statut'] === 'actif' ? '#155724' : '#721c24' ?>;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600">
                                        <?= ucfirst($service['statut']) ?>
                                    </span>
                                </td>
                                <td style="padding:12px;text-align:center">
                                    <button onclick="document.getElementById('modalModifier<?= $service['idService'] ?>').classList.add('open')" 
                                            style="padding:5px 10px;background:#17a2b8;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;margin-right:5px">
                                        ✏️ Modifier
                                    </button>
                                    <button onclick="document.getElementById('modalAgents<?= $service['idService'] ?>').classList.add('open')" 
                                            style="padding:5px 10px;background:#667eea;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;margin-right:5px">
                                        👥 Agents
                                    </button>
                                    <button onclick="document.getElementById('modalQR<?= $service['idService'] ?>').classList.add('open')" 
                                            style="padding:5px 10px;background:#1cc88a;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;margin-right:5px">
                                        🔗 QR
                                    </button>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="supprimer_service">
                                        <input type="hidden" name="idService" value="<?= $service['idService'] ?>">
                                        <button type="submit" onclick="return confirm('Êtes-vous sûr?')" 
                                                style="padding:5px 10px;background:#dc3545;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px">
                                            🗑️ Supprimer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- ===== MODAL AJOUTER SERVICE ===== -->
<div id="modalAjouterService" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="modal-box" style="max-width:600px">
        <h2>➕ Ajouter un Service</h2>
        <form method="POST">
            <input type="hidden" name="action" value="ajouter_service">
            
            <label>Nom du service *</label>
            <input type="text" name="nomService" required placeholder="Ex: Service d'eau">
            
            <label>Description *</label>
            <textarea name="descriptionService" placeholder="Description du service" style="height:80px"></textarea>
            
            <label>Catégorie *</label>
            <select name="idCateg" required>
                <option value="">-- Sélectionnez --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['idCateg'] ?>"><?= htmlspecialchars($cat['label']) ?></option>
                <?php endforeach; ?>
            </select>
            
            <label>Adresse</label>
            <input type="text" name="adresse" placeholder="Adresse">
            
            <label>Téléphone</label>
            <input type="tel" name="telephone" placeholder="+216 XX XXX XXX">
            
            <label>Email</label>
            <input type="email" name="email" placeholder="email@example.com">
            
            <label>Horaire ouverture</label>
            <input type="time" name="horaire_debut">
            
            <label>Horaire fermeture</label>
            <input type="time" name="horaire_fin">
            
            <label>Jours d'ouverture</label>
            <input type="text" name="jours_ouverture" placeholder="Ex: Lun-Ven">
            
            <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end">
                <button type="button" onclick="document.getElementById('modalAjouterService').classList.remove('open')" 
                        style="padding:10px 20px;background:#f5f5f5;color:#333;border:none;border-radius:6px;cursor:pointer">
                    Annuler
                </button>
                <button type="submit" style="padding:10px 20px;background:linear-gradient(135deg, #f093fb 0%, #f5576c 100%);color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600">
                    ✅ Créer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===== MODALS MODIFIER SERVICE ===== -->
<?php foreach ($services as $service): ?>
    <div id="modalModifier<?= $service['idService'] ?>" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
        <div class="modal-box" style="max-width:600px">
            <h2>✏️ Modifier: <?= htmlspecialchars($service['nomService']) ?></h2>
            <form method="POST">
                <input type="hidden" name="action" value="modifier_service">
                <input type="hidden" name="idService" value="<?= $service['idService'] ?>">
                
                <label>Nom du service *</label>
                <input type="text" name="nomService" required value="<?= htmlspecialchars($service['nomService']) ?>">
                
                <label>Description *</label>
                <textarea name="descriptionService" style="height:80px"><?= htmlspecialchars($service['descriptionService']) ?></textarea>
                
                <label>Catégorie *</label>
                <select name="idCateg" required>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['idCateg'] ?>" <?= $cat['idCateg'] == $service['idCateg'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label>Adresse</label>
                <input type="text" name="adresse" value="<?= htmlspecialchars($service['adresse'] ?? '') ?>">
                
                <label>Téléphone</label>
                <input type="tel" name="telephone" value="<?= htmlspecialchars($service['telephone'] ?? '') ?>">
                
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($service['email'] ?? '') ?>">
                
                <label>Horaire ouverture</label>
                <input type="time" name="horaire_debut" value="<?= htmlspecialchars($service['horaire_debut'] ?? '') ?>">
                
                <label>Horaire fermeture</label>
                <input type="time" name="horaire_fin" value="<?= htmlspecialchars($service['horaire_fin'] ?? '') ?>">
                
                <label>Jours d'ouverture</label>
                <input type="text" name="jours_ouverture" value="<?= htmlspecialchars($service['jours_ouverture'] ?? '') ?>">
                
                <label>Statut</label>
                <select name="statut">
                    <option value="actif" <?= $service['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="inactif" <?= $service['statut'] === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                </select>
                
                <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end">
                    <button type="button" onclick="document.getElementById('modalModifier<?= $service['idService'] ?>').classList.remove('open')" 
                            style="padding:10px 20px;background:#f5f5f5;color:#333;border:none;border-radius:6px;cursor:pointer">
                        Annuler
                    </button>
                    <button type="submit" style="padding:10px 20px;background:linear-gradient(135deg, #f093fb 0%, #f5576c 100%);color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600">
                        ✅ Modifier
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL AGENTS -->
    <div id="modalAgents<?= $service['idService'] ?>" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
        <div class="modal-box" style="max-width:600px">
            <h2>👥 Agents du Service: <?= htmlspecialchars($service['nomService']) ?></h2>
            
            <?php $agentsService = $serviceManager->obtenirAgents($service['idService']); ?>
            
            <!-- Agents assignés -->
            <?php if (!empty($agentsService)): ?>
                <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-bottom:20px">
                    <h3 style="margin-top:0">Agents assignés:</h3>
                    <?php foreach ($agentsService as $agent): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:white;border-radius:4px;margin-bottom:8px">
                            <div>
                                <strong><?= htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']) ?></strong><br>
                                <small style="color:#666"><?= htmlspecialchars($agent['email']) ?></small>
                            </div>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="retirer_agent">
                                <input type="hidden" name="idService" value="<?= $service['idService'] ?>">
                                <input type="hidden" name="idUtilisateur" value="<?= $agent['idUtilisateur'] ?>">
                                <button type="submit" onclick="return confirm('Retirer cet agent?')" 
                                        style="padding:5px 10px;background:#dc3545;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px">
                                    ❌ Retirer
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php else: ?>
                <p style="color:#999;text-align:center;padding:20px">Aucun agent assigné</p>
            <?php endif; ?>
            
            <!-- Ajouter agent -->
            <form method="POST" style="border-top:1px solid #ddd;padding-top:15px">
                <input type="hidden" name="action" value="assigner_agent">
                <input type="hidden" name="idService" value="<?= $service['idService'] ?>">
                
                <label>Ajouter un agent</label>
                <select name="idUtilisateur" required>
                    <option value="">-- Sélectionnez un agent --</option>
                    <?php foreach ($agents as $agent): ?>
                        <?php if (!in_array($agent['idUtilisateur'], array_column($agentsService, 'idUtilisateur'))): ?>
                            <option value="<?= $agent['idUtilisateur'] ?>">
                                <?= htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                
                <div style="display:flex;gap:10px;margin-top:15px;justify-content:flex-end">
                    <button type="button" onclick="document.getElementById('modalAgents<?= $service['idService'] ?>').classList.remove('open')" 
                            style="padding:10px 20px;background:#f5f5f5;color:#333;border:none;border-radius:6px;cursor:pointer">
                        Fermer
                    </button>
                    <button type="submit" style="padding:10px 20px;background:#667eea;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600">
                        ✅ Assigner
                    </button>
                </div>
            </form>
        </div>
    </div>
    

    <!-- MODAL QR CODE -->
    <div id="modalQR<?= $service['idService'] ?>" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
        <div class="modal-box" style="max-width:500px;text-align:center">
            <h2>🔗 QR Code du Service</h2>

<div style="text-align: center; padding: 20px 0;">
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?php echo urlencode('http://localhost/Sprint1_AGL/service.php?id=' . $service['idService']); ?>" 
         alt="QR Code" 
         style="border: 2px solid #ddd; border-radius: 8px;">
</div>

<div style="text-align: center; margin-top: 15px;">
    <a href="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?php echo urlencode('http://localhost/Sprint1_AGL/service.php?id=' . $service['idService']); ?>" 
       download="service_qr.png" 
       style="display: inline-block; padding: 10px 20px; background: #4facfe; color: white; text-decoration: none; border-radius: 5px;">
        🖨️ Imprimer
    </a>
</div>
                
                <script>
                    // Générer QR code avec qrserver.com
                    document.addEventListener('DOMContentLoaded', function() {
                        const data = "SERVICE: <?= htmlspecialchars($service['nomService']) ?>\nADR: <?= htmlspecialchars($service['adresse']) ?>\nTEL: <?= htmlspecialchars($service['telephone']) ?>";
                        const qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" + encodeURIComponent(data);
                        document.getElementById('qrcode<?= $service['idService'] ?>').innerHTML = '<img src="' + qrUrl + '" alt="QR Code">';
                    });
                </script>
                
            
            <button type="button" onclick="document.getElementById('modalQR<?= $service['idService'] ?>').classList.remove('open')" 
                    style="padding:10px 20px;background:#f5f5f5;color:#333;border:none;border-radius:6px;cursor:pointer">
                Fermer
            </button>
        </div>
    </div>

<?php endforeach; ?>

<style>
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal-overlay.open {
    display: flex;
}
.modal-box {
    background: #fff;
    border-radius: 12px;
    padding: 30px;
    width: 100%;
    max-width: 480px;
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
    max-height: 90vh;
    overflow-y: auto;
}
.modal-box h2 {
    margin: 0 0 20px 0;
    color: #333;
}
.modal-box label {
    display: block;
    margin-bottom: 4px;
    font-weight: 600;
    font-size: .9rem;
    color: #444;
}
.modal-box input, .modal-box textarea, .modal-box select {
    width: 100%;
    padding: 9px 12px;
    margin-bottom: 14px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: .95rem;
    box-sizing: border-box;
}
.modal-box input:focus, .modal-box textarea:focus, .modal-box select:focus {
    outline: none;
    border-color: #f5576c;
    box-shadow: 0 0 0 3px rgba(245,87,108,0.1);
}

.card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stat-box {
    text-align: center;
    padding: 20px !important;
    border-left: 4px solid #2c7be5;
}

.stat-box h3 {
    font-size: 32px;
    margin: 0 0 10px 0;
}

.stat-box p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    border-radius: 5px;
    border-left: 4px solid #28a745;
    font-weight: 600;
}

.alert-error {
    background: #fdecea;
    color: #b00020;
    padding: 15px;
    border-radius: 5px;
    border-left: 4px solid #b00020;
    font-weight: 600;
}
.btn-back {
    display: inline-block;
    padding: 10px 20px;
    background: #6c757d;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.3s;
}
</style>
<div style="margin-top:30px">
    <a href="./admin.php" class="btn-back">← Retour au tableau de bord</a>
</div>

<?php require_once 'includes/footer.php'; ?>