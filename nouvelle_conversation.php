<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';
require_once 'Classes/MessageManager.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();
$pdo = getConnexion();
$messageManager = new MessageManager($pdo);

$idRec = (int) ($_GET['idRec'] ?? 0);
$idRequest = (int) ($_GET['idRequest'] ?? 0);

$error = '';
$destinataireId = 0;
$title = '';
$type = '';
$idService = null;

if ($idRequest > 0) {
    $requestStmt = $pdo->prepare("
        SELECT ds.idRequest, ds.idService, ds.idUtilisateur, ds.idAgent, s.nomService,
               c.prenom AS citoyen_prenom, c.nom AS citoyen_nom,
               a.prenom AS agent_prenom, a.nom AS agent_nom
        FROM demande_service ds
        JOIN service s ON s.idService = ds.idService
        JOIN utilisateur c ON c.idUtilisateur = ds.idUtilisateur
        LEFT JOIN utilisateur a ON a.idUtilisateur = ds.idAgent
        WHERE ds.idRequest = ?
    ");
    $requestStmt->execute([$idRequest]);
    $item = $requestStmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        $_SESSION['error'] = 'Demande de service introuvable.';
        header('Location: ./messages.php');
        exit;
    }

    $isCitizenOwner = ((int) $item['idUtilisateur'] === (int) $user['idUtilisateur']);
    $isAssignedAgent = !empty($item['idAgent']) && ((int) $item['idAgent'] === (int) $user['idUtilisateur']);

    if (!$isCitizenOwner && !$isAssignedAgent) {
        header('Location: ./messages.php');
        exit;
    }

    if (empty($item['idAgent'])) {
        $_SESSION['error'] = 'Aucun agent n est encore assigne a cette demande.';
        header('Location: ./detail_service_request.php?id=' . $idRequest);
        exit;
    }

    $destinataireId = $isCitizenOwner ? (int) $item['idAgent'] : (int) $item['idUtilisateur'];
    $title = 'Demande de service #' . $item['idRequest'] . ' - ' . $item['nomService'];
    $type = 'demande_service';
    $idService = (int) $item['idService'];
} elseif ($idRec > 0) {
    $recStmt = $pdo->prepare("
        SELECT r.idRec, r.titre, r.idUtilisateur, r.idUtilisateurAssigne
        FROM reclamation r
        WHERE r.idRec = ?
    ");
    $recStmt->execute([$idRec]);
    $item = $recStmt->fetch(PDO::FETCH_ASSOC);

    if (!$item || empty($item['idUtilisateurAssigne'])) {
        $_SESSION['error'] = 'Aucun agent assigne a cette reclamation.';
        header('Location: ./citoyen.php');
        exit;
    }

    $isCitizenOwner = ((int) $item['idUtilisateur'] === (int) $user['idUtilisateur']);
    $isAssignedAgent = ((int) $item['idUtilisateurAssigne'] === (int) $user['idUtilisateur']);

    if (!$isCitizenOwner && !$isAssignedAgent) {
        header('Location: ./messages.php');
        exit;
    }

    $destinataireId = $isCitizenOwner ? (int) $item['idUtilisateurAssigne'] : (int) $item['idUtilisateur'];
    $title = $item['titre'];
    $type = 'reclamation';
} else {
    header('Location: ./messages.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contenu = trim($_POST['contenu'] ?? '');

    if ($contenu === '') {
        $error = 'Le message ne peut pas etre vide.';
    } else {
        try {
            $messageManager->envoyer(
                $user['idUtilisateur'],
                $destinataireId,
                $contenu,
                $idRec,
                $idService,
                $idRequest
            );

            $_SESSION['success'] = 'Message envoye avec succes.';
            header('Location: ./messages.php');
            exit;
        } catch (Exception $e) {
            $error = 'Une erreur s est produite lors de l envoi du message.';
        }
    }
}
?>

<div class="new-message-container">
    <div class="message-compose">
        <h2>Nouveau message</h2>
        <p class="subject-line"><?= htmlspecialchars($title) ?></p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="contenu">Votre message</label>
                <textarea name="contenu" id="contenu" rows="6" maxlength="2000" placeholder="Tapez votre message..." required autofocus></textarea>
                <small id="char-count">0/2000 caracteres</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Envoyer le message</button>
                <?php if ($type === 'demande_service'): ?>
                    <a href="./detail_service_request.php?id=<?= $idRequest ?>" class="btn btn-secondary">Annuler</a>
                <?php else: ?>
                    <a href="./citoyen.php" class="btn btn-secondary">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<style>
.new-message-container { max-width: 700px; margin: 0 auto; padding: 20px; }
.message-compose { background:#fff; padding:30px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,.1); }
.subject-line { background:#f5f5f5; padding:12px; border-radius:4px; margin-bottom:20px; color:#666; }
.form-group { margin-bottom:20px; }
.form-group small { display:block; margin-top:5px; color:#999; }
.form-actions { display:flex; gap:10px; flex-wrap:wrap; }
.btn-secondary { background:#ddd; color:#333; }
.alert { padding:12px; border-radius:4px; margin-bottom:20px; }
.alert-danger { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
</style>

<script>
document.getElementById('contenu')?.addEventListener('input', function () {
    document.getElementById('char-count').textContent = this.value.length + '/2000 caracteres';
});
</script>

<?php require_once 'includes/footer.php'; ?>