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

if ($idConv <= 0) {
    echo json_encode([]);
    exit;
}

// Récupérer la conversation
$convStmt = $pdo->prepare("SELECT * FROM conversation WHERE idConversation = ?");
$convStmt->execute([$idConv]);
$conv = $convStmt->fetch(PDO::FETCH_ASSOC);

if (!$conv) {
    echo json_encode([]);
    exit;
}

// Charger les messages du thread
$messages = $messageManager->obtenirThread(
    $conv['idRec'] ?? null,
    $conv['idService'] ?? null,
    $conv['idRequest'] ?? null,
    $conv['idUtilisateur1'],
    $conv['idUtilisateur2'],
    100,
    0
);

header('Content-Type: application/json');
echo json_encode($messages);
