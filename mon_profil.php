<?php
require_once 'Config/database.php';
require_once 'Auth/auth.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

if ($user['role'] !== 'citoyen') {
    header('Location: ./login.php');
    exit;
}

require_once 'includes/header.php';

$pdo = getConnexion();
$message = '';
$erreur = '';

// Récupérer les données du citoyen
$stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE idUtilisateur = ?");
$stmt->execute([$user['idUtilisateur']]);
$citoyen = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom         = trim($_POST['nom'] ?? '');
    $prenom      = trim($_POST['prenom'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $telephone   = trim($_POST['telephone'] ?? '');
    $ancien_mdp  = $_POST['ancien_mdp'] ?? '';
    $nouveau_mdp = $_POST['nouveau_mdp'] ?? '';
    $confirm_mdp = $_POST['confirm_mdp'] ?? '';

    // Validation
    if (empty($nom) || empty($prenom) || empty($email)) {
        $erreur = 'Les champs nom, prénom et email sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'Adresse email invalide.';
    } elseif (!empty($telephone) && !preg_match('/^[0-9]{8,15}$/', $telephone)) {
        $erreur = 'Numéro de téléphone invalide.';
    } else {
        // Vérifier si l'email est déjà utilisé
        $checkEmail = $pdo->prepare("SELECT idUtilisateur FROM utilisateur WHERE email = ? AND idUtilisateur != ?");
        $checkEmail->execute([$email, $user['idUtilisateur']]);
        
        if ($checkEmail->fetch()) {
            $erreur = 'Cet email est déjà utilisé.';
        } else {
            $sql = "UPDATE utilisateur SET nom = ?, prenom = ?, email = ?, telephone = ?";
            $params = [$nom, $prenom, $email, $telephone];

            // Mettre à jour le mot de passe si fourni
            if (!empty($nouveau_mdp)) {
                if (empty($ancien_mdp)) {
                    $erreur = 'Vous devez fournir votre ancien mot de passe.';
                } elseif (!password_verify($ancien_mdp, $citoyen['mot_de_passe'])) {
                    $erreur = 'Ancien mot de passe incorrect.';
                } elseif (strlen($nouveau_mdp) < 6) {
                    $erreur = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
                } elseif ($nouveau_mdp !== $confirm_mdp) {
                    $erreur = 'Les mots de passe ne correspondent pas.';
                } else {
                    $sql .= ", mot_de_passe = ?";
                    $params[] = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
                }
            }

            // Effectuer la mise à jour si pas d'erreur
            if (empty($erreur)) {
                $sql .= " WHERE idUtilisateur = ?";
                $params[] = $user['idUtilisateur'];
                $update = $pdo->prepare($sql);
                $success = $update->execute($params);

                if ($success) {
                    $message = 'Profil mis à jour avec succès. ✓';
                    // Actualiser les infos de session
                    $_SESSION['nom'] = $nom;
                    $_SESSION['prenom'] = $prenom;
                    $_SESSION['email'] = $email;
                    // Rafraîchir les données affichées
                    $citoyen['nom'] = $nom;
                    $citoyen['prenom'] = $prenom;
                    $citoyen['email'] = $email;
                    $citoyen['telephone'] = $telephone;
                } else {
                    $erreur = 'Erreur lors de la mise à jour du profil.';
                }
            }
        }
    }
}
?>

<div class="card">
    <h1>👤 Mon Profil</h1>
    <p style="color:#666;margin-top:5px">Modifiez vos informations personnelles</p>
</div>

<?php if ($erreur): ?>
    <div class="alert-error"><?= htmlspecialchars($erreur) ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px">
    
    <!-- SECTION 1: INFORMATIONS PERSONNELLES -->
    <div class="card">
        <h2 style="color:#2c7be5;margin-bottom:20px">Informations personnelles</h2>
        
        <form method="POST" id="profileForm">
            <div class="form-group">
                <label for="nom">Nom *</label>
                <input type="text" name="nom" id="nom" required 
                       value="<?= htmlspecialchars($citoyen['nom']) ?>"
                       style="width:100%;padding:10px;border:1px solid #ccc;border-radius:4px">
            </div>

            <div class="form-group">
                <label for="prenom">Prénom *</label>
                <input type="text" name="prenom" id="prenom" required 
                       value="<?= htmlspecialchars($citoyen['prenom']) ?>"
                       style="width:100%;padding:10px;border:1px solid #ccc;border-radius:4px">
            </div>

            <div class="form-group">
                <label for="email">E-mail *</label>
                <input type="email" name="email" id="email" required 
                       value="<?= htmlspecialchars($citoyen['email']) ?>"
                       style="width:100%;padding:10px;border:1px solid #ccc;border-radius:4px">
            </div>

            <div class="form-group">
                <label for="telephone">Téléphone</label>
                <input type="text" name="telephone" id="telephone" maxlength="15"
                       value="<?= htmlspecialchars($citoyen['telephone'] ?? '') ?>"
                       placeholder="Ex: 21612345678"
                       style="width:100%;padding:10px;border:1px solid #ccc;border-radius:4px">
                <small style="color:#666">Format: 8 à 15 chiffres</small>
            </div>

            <button type="submit" class="btn-primary" style="width:100%;padding:12px;margin-top:15px;background:#2c7be5;color:white;border:none;border-radius:4px;cursor:pointer;font-weight:bold">
                💾 Enregistrer les modifications
            </button>
        </form>
    </div>

    <!-- SECTION 2: CHANGER LE MOT DE PASSE -->
    <div class="card">
        <h2 style="color:#2c7be5;margin-bottom:20px">Changer le mot de passe</h2>
        
        <form method="POST" id="passwordForm">
            <div class="form-group">
                <label for="ancien_mdp">Ancien mot de passe</label>
                <input type="password" name="ancien_mdp" id="ancien_mdp"
                       style="width:100%;padding:10px;border:1px solid #ccc;border-radius:4px">
                <small style="color:#666">Laissez vide si vous ne voulez pas changer</small>
            </div>

            <div class="form-group">
                <label for="nouveau_mdp">Nouveau mot de passe</label>
                <input type="password" name="nouveau_mdp" id="nouveau_mdp"
                       style="width:100%;padding:10px;border:1px solid #ccc;border-radius:4px">
                <small style="color:#666">Min. 6 caractères</small>
            </div>

            <div class="form-group">
                <label for="confirm_mdp">Confirmer le mot de passe</label>
                <input type="password" name="confirm_mdp" id="confirm_mdp"
                       style="width:100%;padding:10px;border:1px solid #ccc;border-radius:4px">
            </div>

            <button type="submit" class="btn-primary" style="width:100%;padding:12px;margin-top:15px;background:#28a745;color:white;border:none;border-radius:4px;cursor:pointer;font-weight:bold">
                🔒 Changer le mot de passe
            </button>
        </form>
    </div>

</div>

<!-- BOUTONS DE NAVIGATION -->
<div style="margin-top:30px;text-align:center">
    <a href="./citoyen.php" class="btn" style="display:inline-block;padding:10px 20px;background:#6c757d;color:white;border:none;border-radius:4px;text-decoration:none;cursor:pointer">
        ← Retour au tableau de bord
    </a>
</div>

<style>
.card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    color: #333;
    font-size: 14px;
}

.form-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-group input:focus {
    border-color: #2c7be5;
    outline: none;
    box-shadow: 0 0 5px rgba(44,123,229,0.3);
}

.alert-error {
    background: #fdecea;
    color: #b00020;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    border-left: 4px solid #b00020;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    border-left: 4px solid #28a745;
}

.btn {
    display: inline-block;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 4px;
    cursor: pointer;
    transition: 0.3s;
}

.btn:hover {
    opacity: 0.9;
}

@media (max-width: 768px) {
    .card {
        display: block !important;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>