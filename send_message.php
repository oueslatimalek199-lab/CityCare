<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';
require_once 'Classes/MessageManager.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();
$pdo = getConnexion();
$messageManager = new MessageManager($pdo);

$idConv = (int) ($_GET['id_conv'] ?? 0);
$contenu = trim($_POST['contenu'] ?? '');

if ($idConv <= 0 || $contenu === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Conversation invalide ou message vide']);
    exit;
}

// Récupérer la conversation
$convStmt = $pdo->prepare("SELECT * FROM conversation WHERE idConversation = ?");
$convStmt->execute([$idConv]);
$conv = $convStmt->fetch(PDO::FETCH_ASSOC);

if (!$conv) {
    http_response_code(404);
    echo json_encode(['error' => 'Conversation introuvable']);
    exit;
}

// Déterminer le destinataire
$destinataireId = ($conv['idUtilisateur1'] == $user['idUtilisateur'])
    ? $conv['idUtilisateur2']
    : $conv['idUtilisateur1'];

// Envoyer le message
$messageId = $messageManager->envoyer(
    $user['idUtilisateur'],
    $destinataireId,
    $contenu,
    $conv['idRec'] ?? null,
    $conv['idService'] ?? null,
    $conv['idRequest'] ?? null
);

// Charger les infos du destinataire
$recipientStmt = $pdo->prepare("SELECT * FROM utilisateur WHERE idUtilisateur = ?");
$recipientStmt->execute([$destinataireId]);
$recipient = $recipientStmt->fetch(PDO::FETCH_ASSOC);

// Définir le sujet de la notification
if (!empty($conv['idRequest']) && !empty($conv['idService'])) {
    $subject = 'Demande de service #' . $conv['idRequest'];
} elseif (!empty($conv['idRec'])) {
    $subject = 'Réclamation #' . $conv['idRec'];
} else {
    $subject = 'Nouvelle conversation';
}

// Envoyer la notification email
if ($recipient) {
    $messageManager->envoyerNotification(
        $recipient,
        $user,
        ['titre' => $subject],
        ['contenu' => $contenu]
    );
}

echo json_encode(['success' => true, 'message_id' => $messageId]);
