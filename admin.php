<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

if ($user['role'] !== 'admin') {
    Auth::redirectToDashboard();
}

$pdo = getConnexion();
$message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$erreur  = isset($_SESSION['error'])   ? $_SESSION['error']   : '';
unset($_SESSION['success'], $_SESSION['error']);

// ========== HANDLE FORM SUBMISSION ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'ajouter_agent') {
        $prenom  = trim($_POST['prenom']   ?? '');
        $nom     = trim($_POST['nom']      ?? '');
        $email   = trim($_POST['email']    ?? '');
        $password = $_POST['password']     ?? '';
        $idCateg = isset($_POST['idCateg']) ? (int)$_POST['idCateg'] : 0;

        if (empty($prenom) || empty($nom) || empty($email) || empty($password) || $idCateg === 0) {
            $_SESSION['error'] = 'Tous les champs sont requis.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        if (strlen($password) < 8) {
            $_SESSION['error'] = 'Le mot de passe doit contenir au moins 8 caractères.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Veuillez entrer une adresse email valide.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        $checkEmail = $pdo->prepare("SELECT idUtilisateur FROM utilisateur WHERE email = ?");
        $checkEmail->execute([$email]);
        if ($checkEmail->rowCount() > 0) {
            $_SESSION['error'] = 'Cet email est déjà utilisé.';
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        try {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                INSERT INTO utilisateur (prenom, nom, email, mot_de_passe, role, statut, idCateg)
                VALUES (?, ?, ?, ?, 'agent', 'actif', ?)
            ");
            $stmt->execute([$prenom, $nom, $email, $hashedPassword, $idCateg]);
            $_SESSION['success'] = "Agent '{$prenom} {$nom}' créé avec succès !";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors de la création de l'agent : " . $e->getMessage();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// ========== FILTER PARAMETERS ==========
$statut = isset($_GET['statut']) && !empty($_GET['statut']) ? $_GET['statut'] : 'tous';

// Statistiques globales réclamations
$stats = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'en attente'    THEN 1 ELSE 0 END) as en_attente,
        SUM(CASE WHEN statut = 'en traitement' THEN 1 ELSE 0 END) as en_traitement,
        SUM(CASE WHEN statut = 'résolu'        THEN 1 ELSE 0 END) as resolu,
        SUM(CASE WHEN statut = 'annulé'        THEN 1 ELSE 0 END) as annule
    FROM reclamation
")->fetch(PDO::FETCH_ASSOC);

// *** FIX: fetch service statistics (was missing — caused $serviceStats undefined) ***
$serviceStats = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'actif'   THEN 1 ELSE 0 END) as actif,
        SUM(CASE WHEN statut = 'inactif' THEN 1 ELSE 0 END) as inactif
    FROM service
")->fetch(PDO::FETCH_ASSOC);

// Catégories pour le formulaire
$categories = $pdo->query("
    SELECT idCateg, label FROM categorie ORDER BY label
")->fetchAll(PDO::FETCH_ASSOC);

// Réclamations en retard
$queryRetard = "
    SELECT r.idRec, r.titre, r.statut,
           DATEDIFF(NOW(), r.dateAssignation) AS jours,
           u.prenom, u.nom, u.email,
           a.prenom AS agent_prenom, a.nom AS agent_nom
    FROM reclamation r
    LEFT JOIN utilisateur u ON r.idUtilisateur = u.idUtilisateur
    LEFT JOIN utilisateur a ON r.idUtilisateurAssigne = a.idUtilisateur
    WHERE r.statut != 'résolu' AND DATEDIFF(NOW(), r.dateAssignation) > 15
";

if ($statut !== 'tous') {
    $queryRetard .= " AND r.statut = ?";
    $stmtRetard = $pdo->prepare($queryRetard . " ORDER BY jours DESC");
    $stmtRetard->execute([$statut]);
} else {
    $stmtRetard = $pdo->query($queryRetard . " ORDER BY jours DESC");
}
$retard = $stmtRetard->fetchAll(PDO::FETCH_ASSOC);

// Tous les agents
$allAgents = $pdo->query("
    SELECT idUtilisateur, nom, prenom, email, statut
    FROM utilisateur
    WHERE role = 'agent'
    ORDER BY nom, prenom
")->fetchAll(PDO::FETCH_ASSOC);

// Custom header config
$headerConfig = [
    'title'      => 'Panneau d\'Administration',
    'subtitle'   => 'Gérez l\'ensemble du système, les agents et les réclamations',
    'icon'       => '⚙️',
    'role'       => 'Administrateur',
    'profileLink'=> './profil.php',
    'bgGradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<?php if (!empty($erreur)): ?>
    <div class="alert-error"><?= htmlspecialchars($erreur) ?></div>
<?php endif; ?>

<?php if (!empty($message)): ?>
    <div class="alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- STATISTIQUES GLOBALES -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:30px">
    <div class="card stat-box">
        <h3><?= $stats['total'] ?? 0 ?></h3>
        <p>Total réclamations</p>
    </div>
    <div class="card stat-box" style="border-left:4px solid #ffc107">
        <h3><?= $stats['en_attente'] ?? 0 ?></h3>
        <p>En attente</p>
    </div>
    <div class="card stat-box" style="border-left:4px solid #17a2b8">
        <h3><?= $stats['en_traitement'] ?? 0 ?></h3>
        <p>En traitement</p>
    </div>
    <div class="card stat-box" style="border-left:4px solid #28a745">
        <h3><?= $stats['resolu'] ?? 0 ?></h3>
        <p>Résolues</p>
    </div>
    <div class="card stat-box" style="border-left:4px solid #dc3545">
        <h3><?= count($retard) ?></h3>
        <p>⚠️ En retard</p>
    </div>
    <div class="card stat-box" style="border-left:4px solid #6f42c1">
        <h3><?= count($allAgents) ?></h3>
        <p>👥 Agents</p>
    </div>
</div>

<!-- ========== ACTIONS PRINCIPALES ========== -->
<div class="card" style="background:linear-gradient(135deg,#f093fb 0%,#f5576c 100%);color:white;padding:25px;margin-bottom:30px">
    <h2 style="color:white;margin:0 0 15px 0">🎛️ Vos Actions Administratives</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px">

        <a href="./gestion_agents.php" class="action-card-link">
            <div class="action-card">
                <h3>👥 Gérer les Agents</h3>
                <p>Activer/désactiver les agents et voir leur performance</p>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
                    <span class="action-count"><?= count($allAgents) ?> agents</span>
                    <span style="font-size:20px">→</span>
                </div>
            </div>
        </a>

        <a href="./gestion_reclamations.php" class="action-card-link">
            <div class="action-card">
                <h3>📋 Consulter les Réclamations</h3>
                <p>Voir et gérer toutes les réclamations du système</p>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
                    <span class="action-count"><?= $stats['total'] ?? 0 ?> total</span>
                    <span style="font-size:20px">→</span>
                </div>
            </div>
        </a>

        <a href="./gestion_services.php" class="action-card-link">
            <div class="action-card">
                <h3>📡 Gérer les Services</h3>
                <p>Ajouter / Modifier / Supprimer les Services</p>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
                    <!-- FIX: was $serviceStats['total'] which was undefined -->
                    <span class="action-count"><?= $serviceStats['total'] ?? 0 ?> services</span>
                    <span style="font-size:20px">→</span>
                </div>
            </div>
        </a>

        <div class="action-card-link" style="cursor:pointer"
             onclick="document.getElementById('modalAjoutAgent').classList.add('open')">
            <div class="action-card">
                <h3>➕ Ajouter un Agent</h3>
                <p>Créer un nouveau compte agent dans le système</p>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
                    <span class="action-count">Nouveau</span>
                    <span style="font-size:20px">+</span>
                </div>
            </div>
        </div>

        <a href="./reassign_complaint.php" class="action-card-link">
            <div class="action-card">
                <h3>🔄 Réaffecter Réclamations</h3>
                <p>Réassigner les réclamations entre les agents</p>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
                    <span class="action-count"><?= count($retard) ?> en retard</span>
                    <span style="font-size:20px">→</span>
                </div>
            </div>
        </a>

        <a href="./admin_service_requests.php" class="action-card-link">
            <div class="action-card">
                <h3>🔧 Demandes de Services</h3>
                <p>Suivez et réassignez les demandes de services en retard</p>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
                    <span class="action-count">Demandes</span>
                    <span style="font-size:20px">→</span>
                </div>
            </div>
        </a>

        <a href="./gestion_commentaires.php" class="action-card-link">
            <div class="action-card">
                <h3>💬 Modérer Commentaires</h3>
                <p>Approuver ou supprimer les commentaires signalés</p>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px">
                    <span class="action-count">Modération</span>
                    <span style="font-size:20px">→</span>
                </div>
            </div>
        </a>

    </div>
</div>

<!-- ========== MODAL - AJOUTER AGENT ========== -->
<div id="modalAjoutAgent" class="modal-overlay"
     onclick="if(event.target===this)this.classList.remove('open')">
    <div class="modal-box">
        <h2>➕ Ajouter un Nouvel Agent</h2>
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <input type="hidden" name="action" value="ajouter_agent">

            <label for="prenom">Prénom *</label>
            <input type="text" id="prenom" name="prenom" required>

            <label for="nom">Nom *</label>
            <input type="text" id="nom" name="nom" required>

            <label for="email">Email *</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Mot de passe (min 8 caractères) *</label>
            <input type="password" id="password" name="password" minlength="8" required>

            <label for="idCateg">Catégorie *</label>
            <select id="idCateg" name="idCateg" required>
                <option value="">-- Sélectionnez une catégorie --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['idCateg'] ?>"><?= htmlspecialchars($cat['label']) ?></option>
                <?php endforeach; ?>
            </select>

            <div class="modal-buttons">
                <button type="button" class="modal-btn modal-btn-cancel"
                        onclick="document.getElementById('modalAjoutAgent').classList.remove('open')">
                    Annuler
                </button>
                <button type="submit" class="modal-btn modal-btn-submit">✅ Créer</button>
            </div>
        </form>
    </div>
</div>

<!-- ========== RÉCLAMATIONS EN RETARD ========== -->
<?php if (!empty($retard)): ?>
<div class="card">
    <h2>⚠️ Réclamations en Retard (> 15 jours) — Aperçu</h2>
    <p style="color:#dc3545;font-weight:bold;margin-bottom:15px">
        Action requise : ces réclamations doivent être traitées en priorité.
    </p>

    <?php $retardLimited = array_slice($retard, 0, 5); ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Titre</th>
                <th>Citoyen</th>
                <th>Agent</th>
                <th>Jours</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($retardLimited as $rec): ?>
        <tr style="background:#fff3cd;border-left:4px solid #dc3545">
            <td><strong>#<?= $rec['idRec'] ?></strong></td>
            <td><?= htmlspecialchars(substr($rec['titre'], 0, 25)) ?></td>
            <td><?= htmlspecialchars(($rec['prenom'] ?? '') . ' ' . ($rec['nom'] ?? '')) ?></td>
            <td><?= htmlspecialchars(($rec['agent_prenom'] ?? 'Non') . ' ' . ($rec['agent_nom'] ?? 'assignée')) ?></td>
            <td><strong style="color:#dc3545"><?= $rec['jours'] ?> j</strong></td>
            <td>
                <a href="./detail_reclamation.php?id=<?= $rec['idRec'] ?>&role=admin"
                   class="btn-sm btn-info">Voir</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (count($retard) > 5): ?>
        <div style="margin-top:15px;text-align:center">
            <a href="./gestion_reclamations.php"
               style="color:#dc3545;text-decoration:none;font-weight:bold">
                Voir tous les <?= count($retard) ?> retards →
            </a>
        </div>
    <?php endif; ?>
</div><!-- /.card  FIX: closing tag was missing in original -->
<?php endif; ?>

<!-- ========== STYLES ========== -->
<style>
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:12px;padding:30px;width:100%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.modal-box h2{margin:0 0 20px 0;color:#333}
.modal-box label{display:block;margin-bottom:4px;font-weight:600;font-size:.9rem;color:#444}
.modal-box input,.modal-box select{width:100%;padding:9px 12px;margin-bottom:14px;border:1px solid #ddd;border-radius:6px;font-size:.95rem;box-sizing:border-box}
.modal-box input:focus,.modal-box select:focus{outline:none;border-color:#f5576c;box-shadow:0 0 0 3px rgba(245,87,108,0.1)}
.modal-buttons{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}
.modal-btn{padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:.9rem}
.modal-btn-cancel{background:#f5f5f5;color:#333;border:1px solid #ddd}
.modal-btn-cancel:hover{background:#efefef}
.modal-btn-submit{background:linear-gradient(135deg,#f093fb,#f5576c);color:#fff}
.modal-btn-submit:hover{opacity:0.9}

.action-card-link{text-decoration:none;display:block;transition:all .3s ease;cursor:pointer}
.action-card-link:hover{transform:translateY(-8px)}
.action-card-link:hover .action-card{box-shadow:0 8px 20px rgba(0,0,0,0.2)}
.action-card{background:rgba(255,255,255,.1);padding:20px;border-radius:8px;border:2px solid rgba(255,255,255,.2);height:100%;transition:all .3s ease;cursor:pointer}
.action-card:hover{background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.4)}
.action-card h3{margin:0 0 8px 0;font-size:18px;color:white;font-weight:700}
.action-card p{margin:0 0 10px 0;font-size:13px;color:rgba(255,255,255,.85);line-height:1.4}
.action-count{display:inline-block;background:rgba(255,255,255,.25);padding:6px 12px;border-radius:6px;font-size:13px;font-weight:700;color:white}

.card{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);margin-bottom:20px}
.stat-box{text-align:center;padding:20px !important;border-left:4px solid #2c7be5}
.stat-box h3{font-size:32px;margin:0 0 10px 0;color:#2c7be5}
.stat-box p{margin:0;color:#666;font-size:14px}

.badge{display:inline-block;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600}
.btn-sm{padding:5px 10px;font-size:12px;display:inline-block;border:none;border-radius:4px;cursor:pointer;text-decoration:none;transition:all .3s}
.btn-info{background:#17a2b8;color:white}
.btn-info:hover{background:#138496}

table{width:100%;border-collapse:collapse;margin-top:10px}
table thead{background:#f5f5f5}
table th,table td{padding:12px;text-align:left;border-bottom:1px solid #ddd;font-size:14px}
table th{font-weight:600;color:#333}
table tbody tr:hover{background:#f9f9f9}

.alert-error{background:#fdecea;color:#b00020;padding:15px;border-radius:5px;margin-bottom:20px;border-left:4px solid #b00020}
.alert-success{background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin-bottom:20px;border-left:4px solid #28a745}
</style>

<?php require_once 'includes/footer.php'; ?>