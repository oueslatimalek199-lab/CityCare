<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

$pdo = getConnexion();
$message = '';
$erreur  = '';

$user_id = $user['idUtilisateur'];

$stmt = $pdo->prepare("SELECT nom, prenom, email, telephone, mot_de_passe FROM utilisateur WHERE idUtilisateur = ?");
$stmt->execute([$user_id]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom                  = trim($_POST["nom"]                 ?? '');
    $prenom               = trim($_POST["prenom"]              ?? '');
    $email                = trim($_POST["email"]               ?? '');
    $telephone            = trim($_POST["telephone"]           ?? '');
    $mot_de_passe_actuel  = $_POST["mot_de_passe_actuel"]      ?? '';
    $nouveau_mot_de_passe = $_POST["nouveau_mot_de_passe"]     ?? '';
    $confirm_mot_de_passe = $_POST["confirm_mot_de_passe"]     ?? '';

    if (empty($nom) || empty($prenom) || empty($email)) {
        $erreur = "Les champs nom, prénom et email sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Email invalide.";
    } elseif (!empty($telephone) && !preg_match("/^[0-9]{8,15}$/", $telephone)) {
        $erreur = "Numéro de téléphone invalide (8 à 15 chiffres).";
    } else {
        $check = $pdo->prepare("SELECT idUtilisateur FROM utilisateur WHERE email=? AND idUtilisateur!=?");
        $check->execute([$email, $user_id]);

        if ($check->fetch()) {
            $erreur = "Email déjà utilisé.";
        } else {
            $sql    = "UPDATE utilisateur SET nom=?, prenom=?, email=?, telephone=?";
            $params = [$nom, $prenom, $email, $telephone];

            if (!empty($nouveau_mot_de_passe)) {
                if (empty($mot_de_passe_actuel)) {
                    $erreur = "Vous devez fournir votre ancien mot de passe.";
                } elseif (!password_verify($mot_de_passe_actuel, $userData["mot_de_passe"])) {
                    $erreur = "Ancien mot de passe incorrect.";
                } elseif ($nouveau_mot_de_passe !== $confirm_mot_de_passe) {
                    $erreur = "Les mots de passe ne correspondent pas.";
                } elseif (strlen($nouveau_mot_de_passe) < 6) {
                    $erreur = "Nouveau mot de passe trop court (min 6 caractères).";
                } else {
                    $hashed  = password_hash($nouveau_mot_de_passe, PASSWORD_DEFAULT);
                    $sql    .= ", mot_de_passe=?";
                    $params[] = $hashed;
                }
            }

            if (empty($erreur)) {
                $sql     .= " WHERE idUtilisateur=?";
                $params[] = $user_id;

                try {
                    $update = $pdo->prepare($sql);
                    $ok     = $update->execute($params);

                    if ($ok) {
                        $message = "Profil mis à jour avec succès ✓";
                        // Update session
                        $_SESSION['nom']    = $nom;
                        $_SESSION['prenom'] = $prenom;
                        $_SESSION['email']  = $email;

                        $userData["nom"]       = $nom;
                        $userData["prenom"]    = $prenom;
                        $userData["email"]     = $email;
                        $userData["telephone"] = $telephone;
                    } else {
                        $erreur = "Erreur lors de la mise à jour.";
                    }
                } catch (Exception $e) {
                    $erreur = "Erreur base de données : " . $e->getMessage();
                }
            }
        }
    }
}

// User initials for avatar
$firstNameInitial = strtoupper(mb_substr($userData['prenom'] ?? '', 0, 1));
$lastNameInitial  = strtoupper(mb_substr($userData['nom']    ?? '', 0, 1));
$initials         = $firstNameInitial . $lastNameInitial;

// FIX: profil.php was missing the header include — pages need HTML <head>/<body>
// dashboard_header.php provides the full page shell including <html><head><body>
$headerConfig = [
    'title'       => 'Mon Profil',
    'subtitle'    => 'Gérez vos informations personnelles',
    'icon'        => '👤',
    'role'        => ucfirst($user['role']),
    'profileLink' => './profil.php',
    'bgGradient'  => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<?php if (!empty($erreur)): ?>
    <div class="alert-error"><?= htmlspecialchars($erreur) ?></div>
<?php endif; ?>

<?php if (!empty($message)): ?>
    <div class="alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px">

    <!-- SECTION 1: AVATAR & INFORMATIONS PERSONNELLES -->
    <div class="card">
        <!-- AVATAR -->
        <div class="avatar-section">
            <div class="avatar-circle">
                <span class="avatar-initials"><?= htmlspecialchars($initials) ?></span>
            </div>
            <div class="user-info-badge">
                <p class="user-full-name"><?= htmlspecialchars($userData['prenom'] . ' ' . $userData['nom']) ?></p>
                <p class="user-email"><?= htmlspecialchars($userData['email']) ?></p>
                <p style="margin:4px 0 0;font-size:12px;opacity:.8;">
                    <?= ucfirst($user['role']) ?>
                </p>
            </div>
        </div>

        <h2 style="color:#2c7be5;margin-bottom:20px;margin-top:25px">Informations personnelles</h2>

        <form method="POST">
            <div class="form-group">
                <label for="nom">Nom *</label>
                <input type="text" id="nom" name="nom" required
                       value="<?= htmlspecialchars($userData['nom']) ?>">
            </div>
            <div class="form-group">
                <label for="prenom">Prénom *</label>
                <input type="text" id="prenom" name="prenom" required
                       value="<?= htmlspecialchars($userData['prenom']) ?>">
            </div>
            <div class="form-group">
                <label for="email">E-mail *</label>
                <input type="email" id="email" name="email" required
                       value="<?= htmlspecialchars($userData['email']) ?>">
            </div>
            <div class="form-group">
                <label for="telephone">Téléphone</label>
                <input type="text" id="telephone" name="telephone" maxlength="15"
                       value="<?= htmlspecialchars($userData['telephone'] ?? '') ?>"
                       placeholder="Ex: 21612345678">
                <small style="color:#999;">Format : 8 à 15 chiffres</small>
            </div>
            <button type="submit" class="btn-submit">💾 Enregistrer les modifications</button>
        </form>
    </div>

    <!-- SECTION 2: CHANGER LE MOT DE PASSE -->
    <div class="card">
        <h2 style="color:#2c7be5;margin-bottom:20px">🔒 Changer le mot de passe</h2>

        <form method="POST">
            <div class="form-group">
                <label for="mot_de_passe_actuel">Ancien mot de passe *</label>
                <input type="password" id="mot_de_passe_actuel" name="mot_de_passe_actuel">
                <small style="color:#999;">Obligatoire pour changer le mot de passe</small>
            </div>
            <div class="form-group">
                <label for="nouveau_mot_de_passe">Nouveau mot de passe *</label>
                <input type="password" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe">
                <small style="color:#999;">Min. 6 caractères</small>
            </div>
            <div class="form-group">
                <label for="confirm_mot_de_passe">Confirmer le mot de passe *</label>
                <input type="password" id="confirm_mot_de_passe" name="confirm_mot_de_passe">
            </div>
            <button type="submit" class="btn-submit" style="background:#28a745">
                🔒 Mettre à jour le mot de passe
            </button>
        </form>
    </div>

</div>

<!-- NAVIGATION -->
<div style="margin-top:25px;text-align:center">
    <a href="./dashboard.php" class="btn-back">← Retour au tableau de bord</a>
</div>

<style>
.card{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);margin-bottom:20px}

.avatar-section{display:flex;align-items:center;gap:20px;padding:20px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:8px;margin-bottom:20px}
.avatar-circle{flex-shrink:0;width:90px;height:90px;border-radius:50%;background:white;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,0.15)}
.avatar-initials{font-size:36px;font-weight:700;color:#667eea;line-height:1}
.user-info-badge{color:white}
.user-full-name{margin:0 0 5px 0;font-size:17px;font-weight:600}
.user-email{margin:0;font-size:13px;opacity:.9}

.form-group{margin-bottom:15px}
.form-group label{display:block;font-weight:600;margin-bottom:5px;color:#333;font-size:14px}
.form-group input{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;font-size:14px;box-sizing:border-box;transition:border-color .3s}
.form-group input:focus{border-color:#667eea;outline:none;box-shadow:0 0 0 3px rgba(102,126,234,.15)}
.form-group small{display:block;margin-top:4px;color:#999;font-size:12px}

.btn-submit{width:100%;padding:12px;margin-top:15px;background:#2c7be5;color:white;border:none;border-radius:4px;cursor:pointer;font-weight:600;font-size:14px;transition:background .3s}
.btn-submit:hover{background:#1e5db8}

.btn-back{display:inline-block;padding:10px 20px;background:#6c757d;color:white;text-decoration:none;border-radius:4px;cursor:pointer;transition:background .3s}
.btn-back:hover{background:#5a6268}

.alert-error{background:#fdecea;color:#b00020;padding:15px;border-radius:5px;margin-bottom:20px;border-left:4px solid #b00020;font-weight:500}
.alert-success{background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin-bottom:20px;border-left:4px solid #28a745;font-weight:500}

@media(max-width:768px){
    div[style*="grid-template-columns:1fr 1fr"]{display:block !important}
    .avatar-section{flex-direction:column;text-align:center}
}
</style>

<?php require_once 'includes/footer.php'; ?>