<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';
require_once 'Classes/MessageManager.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();
$pdo = getConnexion();
$messageManager = new MessageManager($pdo);

$idConversation = (int) ($_GET['id_conv'] ?? 0);
if ($idConversation <= 0) {
    header('Location: ./messages.php');
    exit;
}

$convStmt = $pdo->prepare("
    SELECT c.*,
           r.titre AS rec_titre,
           s.nomService,
           ds.idRequest AS demande_id,
           u1.prenom AS prenom1, u1.nom AS nom1, u1.role AS role1,
           u2.prenom AS prenom2, u2.nom AS nom2, u2.role AS role2
    FROM conversation c
    LEFT JOIN reclamation r ON c.idRec = r.idRec
    LEFT JOIN service s ON c.idService = s.idService
    LEFT JOIN demande_service ds ON c.idRequest = ds.idRequest
    LEFT JOIN utilisateur u1 ON c.idUtilisateur1 = u1.idUtilisateur
    LEFT JOIN utilisateur u2 ON c.idUtilisateur2 = u2.idUtilisateur
    WHERE c.idConversation = ?
      AND (c.idUtilisateur1 = ? OR c.idUtilisateur2 = ?)
");
$convStmt->execute([$idConversation, $user['idUtilisateur'], $user['idUtilisateur']]);
$conversation = $convStmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    header('Location: ./messages.php');
    exit;
}

$otherUserId = ((int) $conversation['idUtilisateur1'] === (int) $user['idUtilisateur'])
    ? (int) $conversation['idUtilisateur2']
    : (int) $conversation['idUtilisateur1'];

$messageManager->marquerCommeLu(
    $user['idUtilisateur'],
    $otherUserId,
    $conversation['idRec'] ?: null,
    $conversation['idService'] ?: null,
    $conversation['idRequest'] ?: null
);

$messages = $messageManager->obtenirThread(
    $conversation['idRec'] ?: null,
    $conversation['idService'] ?: null,
    $conversation['idRequest'] ?: null,
    $user['idUtilisateur'],
    $otherUserId,
    100,
    0
);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contenu = trim($_POST['contenu'] ?? '');

    if ($contenu === '') {
        $error = 'Le message ne peut pas etre vide.';
    } else {
        try {
            $messageManager->envoyer(
                $user['idUtilisateur'],
                $otherUserId,
                $contenu,
                $conversation['idRec'] ?: null,
                $conversation['idService'] ?: null,
                $conversation['idRequest'] ?: null
            );

            $recipientStmt = $pdo->prepare("SELECT * FROM utilisateur WHERE idUtilisateur = ?");
            $recipientStmt->execute([$otherUserId]);
            $recipient = $recipientStmt->fetch(PDO::FETCH_ASSOC);

            if (!empty($conversation['demande_id'])) {
                $subject = 'Demande de service #' . $conversation['demande_id'] . ' - ' . $conversation['nomService'];
            } elseif (!empty($conversation['rec_titre'])) {
                $subject = $conversation['rec_titre'];
            } else {
                $subject = $conversation['nomService'] ?? 'Conversation';
            }

            if ($recipient) {
                $messageManager->envoyerNotification(
                    $recipient,
                    $user,
                    ['titre' => $subject],
                    ['contenu' => $contenu]
                );
            }
            header('Location: ./message_detail.php?id_conv=' . $idConversation);
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
