<?php
/**
 * delete_inappropriate_comments.php
 * Cron-style script — run from project root, not from a subfolder.
 * Can be called via: php delete_inappropriate_comments.php
 * Or scheduled as a cron job.
 */

// Adjust paths: this file lives at root, not in a subfolder
require_once __DIR__ . '/Config/database.php';
require_once __DIR__ . '/Classes/CommentaireManager.php';

$pdo = getConnexion();
$commentManager = new CommentaireManager($pdo);

$deleted = $commentManager->supprimerInappropries();

// Log
$logDir  = __DIR__ . '/logs';
$logFile = $logDir . '/moderation.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$message = date('Y-m-d H:i:s') . " - $deleted commentaire(s) inapproprié(s) supprimé(s)\n";
file_put_contents($logFile, $message, FILE_APPEND);

echo "✅ $deleted commentaire(s) supprimé(s)";