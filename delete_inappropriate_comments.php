<?php
// À exécuter régulièrement (toutes les heures par exemple)
require_once '../Config/database.php';
require_once '../Classes/CommentaireManager.php';

$pdo = getConnexion();
$commentManager = new CommentaireManager($pdo);

$deleted = $commentManager->supprimerInappropries();

// Log
$logFile = '../logs/moderation.log';
$message = date('Y-m-d H:i:s') . " - $deleted commentaire(s) inapproprié(s) supprimé(s)\n";
file_put_contents($logFile, $message, FILE_APPEND);

echo "✅ $deleted commentaire(s) supprimé(s)";
?>