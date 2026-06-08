<?php
// annuler_reclamation.php
require_once 'Config/session.php';
require_once 'Auth/auth.php';
require_once 'Config/database.php';

Auth::exigerConnexion('./login.php');
$user  = Auth::getUtilisateur();
$pdo   = getConnexion();
$idRec = (int)($_GET['idRc'] ?? $_GET['id'] ?? 0);

if (!$idRec) {
    header('Location: ./citoyen.php');
    exit;
}

// FIX: original used 'en_attente' (underscore) but DB stores 'en attente' (space)
// Also FIX: original updated to 'annule' but DB uses 'annulé' (with accent)
$stmt = $pdo->prepare("
    SELECT idRec FROM reclamation
    WHERE idRec = ? AND idUtilisateur = ? AND statut = 'en attente'
");
$stmt->execute([$idRec, $user['idUtilisateur']]);

if ($stmt->fetch()) {
    $pdo->prepare("
        UPDATE reclamation SET statut = 'annulé', dateModification = NOW()
        WHERE idRec = ?
    ")->execute([$idRec]);
    $_SESSION['success'] = 'Réclamation annulée avec succès.';
} else {
    $_SESSION['error'] = 'Cette réclamation ne peut pas être annulée.';
}

header('Location: ./citoyen.php');
exit;