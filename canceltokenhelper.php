<?php
/**
 * cancel_token_helper.php
 * Helper for generating cancellation tokens for service requests.
 * Referenced by demander_service.php
 */

/**
 * Generate a cancellation link token and store it in the DB.
 *
 * @param PDO    $pdo      Database connection
 * @param string $type     'demande' or 'reclamation'
 * @param int    $id       ID of the demande or reclamation
 * @param string $baseUrl  Base URL of the site
 * @param int    $ttlHours Token validity in hours (default: 2)
 * @return string  Full cancellation URL
 */
function genererLienAnnulation(PDO $pdo, string $type, int $id, string $baseUrl, int $ttlHours = 2): string {
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + $ttlHours * 3600);

    if ($type === 'demande') {
        // Store in demande_annulation_token
        try {
            $stmt = $pdo->prepare("
                INSERT INTO demande_annulation_token (idDemande, token, expires_at)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)
            ");
            $stmt->execute([$id, $token, $expires]);
        } catch (PDOException $e) {
            error_log("genererLienAnnulation (demande) error: " . $e->getMessage());
        }
        return $baseUrl . '/annuler_demande.php?token=' . urlencode($token);
    }

    if ($type === 'reclamation') {
        // Store in reclamation_token
        try {
            $stmt = $pdo->prepare("
                INSERT INTO reclamation_token (idRec, token, expires_at, used)
                VALUES (?, ?, ?, 0)
                ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), used = 0
            ");
            $stmt->execute([$id, $token, $expires]);
        } catch (PDOException $e) {
            error_log("genererLienAnnulation (reclamation) error: " . $e->getMessage());
        }
        return $baseUrl . '/annuler_via_email.php?token=' . urlencode($token);
    }

    return $baseUrl;
}

/**
 * Helper used in email templates: generates an HTML cancellation block.
 */
function htmlBlocAnnulation(string $cancelUrl, int $ttlHours = 2): string {
    return "
    <div style='margin:25px 0;padding:18px;background:#fff8e1;border-radius:8px;border:2px dashed #ffc107;text-align:center'>
        <p style='margin:0 0 8px 0;font-size:13px;color:#856404;font-weight:600'>
            ⚠️ Vous souhaitez annuler cette demande ?
        </p>
        <p style='margin:0 0 14px 0;font-size:12px;color:#856404'>
            Ce lien est valable pendant <strong>$ttlHours heures</strong> uniquement.
        </p>
        <a href='" . htmlspecialchars($cancelUrl) . "'
           style='display:inline-block;padding:10px 24px;background:#dc3545;color:white;
                  text-decoration:none;border-radius:6px;font-weight:700;font-size:13px'>
            ❌ Annuler ma demande
        </a>
    </div>";
}