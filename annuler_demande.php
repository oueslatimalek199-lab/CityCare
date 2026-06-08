<?php
require_once 'Config/database.php';
require_once 'Config/session.php';

$pdo = getConnexion();

$token = $_GET['token'] ?? '';
$message = '';
$success = false;

if (!empty($token)) {
    // Vérifier le token
    $stmt = $pdo->prepare("
        SELECT dat.idDemande, dat.expires_at, ds.statut
        FROM demande_annulation_token dat
        JOIN demande_service ds ON dat.idDemande = ds.idRequest
        WHERE dat.token = ?
    ");
    $stmt->execute([$token]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        if (strtotime($data['expires_at']) >= time()) {
            if ($data['statut'] !== 'annulée') {
                // Mettre à jour le statut de la demande
                $update = $pdo->prepare("
                    UPDATE demande_service
                    SET statut = 'annulée', dateModification = NOW()
                    WHERE idRequest = ?
                ");
                $update->execute([$data['idDemande']]);

                // Supprimer le token après utilisation
                $delete = $pdo->prepare("
                    DELETE FROM demande_annulation_token
                    WHERE token = ?
                ");
                $delete->execute([$token]);

                $message = "Votre demande a été annulée avec succès.";
                $success = true;
            } else {
                $message = "Cette demande est déjà annulée.";
            }
        } else {
            $message = "Le lien d'annulation a expiré.";
        }
    } else {
        $message = "Lien d'annulation invalide.";
    }
} else {
    $message = "Token manquant.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Annulation de la demande</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2 class="<?= $success ? 'success' : 'error' ?>">
            <?= htmlspecialchars($message) ?>
        </h2>
        <a href="./services_publiques.php">Retour aux services</a>
    </div>
</body>
</html>