<?php
/**
 * cron_check_reclamations.php
 * Run every few hours via cron.
 */
require_once __DIR__ . '/Config/database.php';

$pdo   = getConnexion();
$delai = 7; // days

$stmt = $pdo->prepare("
    SELECT idRec, titre, dateCreation, statut
    FROM reclamation
    WHERE statut IN ('en attente', 'en traitement')
      AND dateCreation < DATE_SUB(NOW(), INTERVAL :delai DAY)
");
$stmt->execute(['delai' => $delai]);
$reclamations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($reclamations)) {
    echo "Aucune réclamation hors délai détectée.\n";
    exit;
}

$logDir  = __DIR__ . '/logs';
$logFile = $logDir . '/reclamations_retard.log';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

foreach ($reclamations as $rec) {
    // We do NOT change status to hors_delai because the ENUM doesn't include it.
    // Instead we just log. To add a hors_delai status, add it to the ENUM in the DB.
    $msg = date('Y-m-d H:i:s') . " — Réclamation #{$rec['idRec']} '{$rec['titre']}' dépasse {$delai} jours sans résolution.\n";
    file_put_contents($logFile, $msg, FILE_APPEND);
    echo $msg;
}