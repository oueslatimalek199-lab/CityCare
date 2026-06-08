<?php
/**
 * EmailNotifications.php
 * Centralized email notification system for CityCare platform
 * Handles: new complaint confirmation, cancellation link (2h TTL), status updates, service events
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailNotifications {

    private static string $smtpHost     = 'smtp.gmail.com';
    private static string $smtpUser     = 'amaltoumi535@gmail.com';
    private static string $smtpPassword = 'crmr mydm sqtn zdqn';
    private static int    $smtpPort     = 587;
    private static string $fromName     = 'Plateforme CityCare';
    private static string $baseUrl      = 'http://localhost/Sprint1_AGL';

    // =====================================================================
    // PUBLIC API
    // =====================================================================

    /**
     * BF8 — Notification envoyée au citoyen dès qu'une réclamation est reçue.
     * Inclut un lien d'annulation valide 2 heures.
     */
    public static function nouvelleReclamation(
        $pdo,
        string $citizenEmail,
        string $citizenName,
        int    $idRec,
        string $titre,
        string $description,
        string $adresse,
        array  $categories = []
    ): bool {
        // Generate a one-time cancellation token (2h TTL)
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 7200); // 2 hours

        // Persist token
        try {
            $stmt = $pdo->prepare("
                INSERT INTO reclamation_token (idRec, token, expires_at, used)
                VALUES (?, ?, ?, 0)
                ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), used = 0
            ");
            $stmt->execute([$idRec, $token, $expires]);
        } catch (\Throwable $e) {
            error_log("EmailNotifications - token insert error: " . $e->getMessage());
            // Don't block the email even if token fails
            $token = null;
        }

        $cancelLink = $token
            ? self::$baseUrl . "/annuler_via_email.php?token=" . urlencode($token)
            : null;

        $catList = !empty($categories) ? implode(', ', $categories) : 'Non spécifié';

        $cancelHtml = '';
        if ($cancelLink) {
            $cancelHtml = "
            <div style='margin:25px 0;padding:18px;background:#fff8e1;border-radius:8px;border:2px dashed #ffc107;text-align:center'>
                <p style='margin:0 0 8px 0;font-size:13px;color:#856404;font-weight:600'>⚠️ Vous souhaitez annuler cette réclamation ?</p>
                <p style='margin:0 0 14px 0;font-size:12px;color:#856404'>Ce lien est valable pendant <strong>2 heures</strong> uniquement.</p>
                <a href='" . htmlspecialchars($cancelLink) . "'
                   style='display:inline-block;padding:10px 24px;background:#dc3545;color:white;text-decoration:none;border-radius:6px;font-weight:700;font-size:13px;letter-spacing:0.3px'>
                    ❌ Annuler ma réclamation
                </a>
            </div>";
        }

        $subject = "✅ Réclamation reçue — #$idRec : " . mb_substr($titre, 0, 40);

        $body = self::wrapEmail(
            '✅ Réclamation bien reçue',
            'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
            "
            <p>Bonjour <strong>" . htmlspecialchars($citizenName) . "</strong>,</p>
            <p>Votre réclamation a bien été enregistrée et sera traitée dans les meilleurs délais.</p>

            " . self::detailsBlock([
                ['📌 Numéro',      '#' . $idRec],
                ['📝 Titre',       htmlspecialchars($titre)],
                ['📋 Description', htmlspecialchars(mb_substr($description, 0, 200)) . (mb_strlen($description) > 200 ? '...' : '')],
                ['📍 Adresse',     htmlspecialchars($adresse ?: 'Non spécifié')],
                ['🏷️ Catégories',  htmlspecialchars($catList)],
                ['📊 Statut',      self::badge('en attente')],
                ['📅 Date',        date('d/m/Y à H:i')],
            ]) . "

            $cancelHtml

            <p>Vous pouvez suivre l'avancement dans votre espace personnel.</p>
            " . self::ctaButton('Suivre ma réclamation', self::$baseUrl . '/citoyen.php')
        );

        return self::send($citizenEmail, $subject, $body);
    }

    /**
     * BF8 — Email de confirmation générique (ex: après assignation, après traitement).
     */
    public static function confirmationReclamation(
        string $citizenEmail,
        string $citizenName,
        int    $idRec,
        string $titre,
        string $statut,
        string $agentName  = '',
        string $commentaire = ''
    ): bool {
        $subject = "📋 Mise à jour réclamation #$idRec";
        $commentHtml = $commentaire
            ? "<div style='margin-top:12px;padding:14px;background:#f9f9f9;border-left:4px solid #667eea;border-radius:4px;font-style:italic;color:#444'>"
              . "💬 " . nl2br(htmlspecialchars($commentaire)) . "</div>"
            : '';

        $rows = [
            ['📌 Numéro',       '#' . $idRec],
            ['📝 Titre',        htmlspecialchars($titre)],
            ['📊 Nouveau statut', self::badge($statut)],
        ];
        if ($agentName) {
            $rows[] = ['👤 Agent en charge', htmlspecialchars($agentName)];
        }

        $body = self::wrapEmail(
            '📋 Mise à jour de votre réclamation',
            'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            "
            <p>Bonjour <strong>" . htmlspecialchars($citizenName) . "</strong>,</p>
            <p>Votre réclamation a été mise à jour.</p>
            " . self::detailsBlock($rows) . "
            $commentHtml
            <p style='margin-top:18px'>Vous pouvez consulter le détail dans votre espace.</p>
            " . self::ctaButton('Voir ma réclamation', self::$baseUrl . '/citoyen.php')
        );

        return self::send($citizenEmail, $subject, $body);
    }

    /**
     * BF8 — Email envoyé après annulation réussie d'une réclamation.
     */
    public static function annulationReclamation(
        string $citizenEmail,
        string $citizenName,
        int    $idRec,
        string $titre,
        string $motif = ''
    ): bool {
        $subject = "❌ Réclamation #$idRec annulée";
        $motifHtml = $motif
            ? "<p style='margin-top:12px'><strong>Motif :</strong> " . htmlspecialchars($motif) . "</p>"
            : '';

        $body = self::wrapEmail(
            '❌ Annulation confirmée',
            'linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%)',
            "
            <p>Bonjour <strong>" . htmlspecialchars($citizenName) . "</strong>,</p>
            <p>Votre réclamation a bien été <strong>annulée</strong>.</p>
            " . self::detailsBlock([
                ['📌 Numéro', '#' . $idRec],
                ['📝 Titre',  htmlspecialchars($titre)],
                ['📊 Statut', self::badge('annulé')],
                ['📅 Date annulation', date('d/m/Y à H:i')],
            ]) . "
            $motifHtml
            <p style='margin-top:18px'>Si cette annulation est une erreur, vous pouvez soumettre une nouvelle réclamation.</p>
            " . self::ctaButton('Soumettre une nouvelle réclamation', self::$baseUrl . '/soumettre.php')
        );

        return self::send($citizenEmail, $subject, $body);
    }

    /**
     * BF8 — Notification à l'agent lors d'une nouvelle assignation.
     */
    public static function assignationAgent(
        string $agentEmail,
        string $agentName,
        int    $idRec,
        string $titre,
        string $description,
        string $adresse,
        string $citizenName,
        string $dateCreation
    ): bool {
        $subject = "🔔 Nouvelle réclamation assignée — #$idRec";

        $body = self::wrapEmail(
            '🔔 Nouvelle Réclamation Assignée',
            'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            "
            <p>Bonjour <strong>" . htmlspecialchars($agentName) . "</strong>,</p>
            <p>Une nouvelle réclamation vient d'être assignée à votre compte.</p>
            " . self::detailsBlock([
                ['📌 ID',          '#' . $idRec],
                ['📝 Titre',       htmlspecialchars($titre)],
                ['📋 Description', htmlspecialchars(mb_substr($description, 0, 150)) . '...'],
                ['📍 Adresse',     htmlspecialchars($adresse)],
                ['👤 Citoyen',     htmlspecialchars($citizenName)],
                ['📅 Date',        htmlspecialchars($dateCreation)],
            ]) . "
            <p style='margin-top:18px'>Veuillez traiter cette réclamation dans les meilleurs délais.</p>
            " . self::ctaButton('Accéder à mon tableau de bord', self::$baseUrl . '/agent.php')
        );

        return self::send($agentEmail, $subject, $body);
    }

    /**
     * BF8 — Notification au citoyen quand sa réclamation est réassignée.
     */
    public static function reassignationCitoyen(
        string $citizenEmail,
        string $citizenName,
        int    $idRec,
        string $titre,
        string $newAgentName,
        string $newAgentEmail
    ): bool {
        $subject = "🔄 Réclamation #$idRec réassignée";

        $body = self::wrapEmail(
            '🔄 Réclamation Réassignée',
            'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
            "
            <p>Bonjour <strong>" . htmlspecialchars($citizenName) . "</strong>,</p>
            <p>Votre réclamation a été confiée à un nouvel agent municipal.</p>
            " . self::detailsBlock([
                ['📌 Numéro',        '#' . $idRec],
                ['📝 Titre',         htmlspecialchars($titre)],
                ['👤 Nouvel agent',  htmlspecialchars($newAgentName)],
                ['📧 Email agent',   htmlspecialchars($newAgentEmail)],
            ]) . "
            <p style='margin-top:18px'>L'agent prendra contact si nécessaire.</p>
            " . self::ctaButton('Consulter ma réclamation', self::$baseUrl . '/citoyen.php')
        );

        return self::send($citizenEmail, $subject, $body);
    }

    // =====================================================================
    // INTERNAL HELPERS
    // =====================================================================

    private static function wrapEmail(string $title, string $headerGradient, string $content): string {
        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
        <body style='margin:0;padding:0;background:#f0f2f5;font-family:\"Segoe UI\",Arial,sans-serif;color:#333'>
            <table width='100%' cellpadding='0' cellspacing='0' style='padding:30px 16px'>
                <tr><td align='center'>
                <table width='600' cellpadding='0' cellspacing='0' style='max-width:600px;width:100%'>

                    <!-- HEADER -->
                    <tr><td style='background:$headerGradient;border-radius:12px 12px 0 0;padding:28px 32px;text-align:center'>
                        <h1 style='margin:0;font-size:22px;font-weight:700;color:white;letter-spacing:-0.3px'>$title</h1>
                        <p style='margin:6px 0 0;font-size:13px;color:rgba(255,255,255,.8)'>Plateforme CityCare</p>
                    </td></tr>

                    <!-- BODY -->
                    <tr><td style='background:white;padding:32px;border-radius:0 0 12px 12px;box-shadow:0 4px 24px rgba(0,0,0,.08)'>
                        <div style='font-size:15px;line-height:1.7'>
                            $content
                        </div>
                    </td></tr>

                    <!-- FOOTER -->
                    <tr><td style='padding:20px 32px;text-align:center'>
                        <p style='margin:0;font-size:11px;color:#aaa;line-height:1.6'>
                            Cet email a été envoyé automatiquement — merci de ne pas y répondre.<br>
                            © " . date('Y') . " CityCare — Plateforme Municipale de Gestion des Réclamations
                        </p>
                    </td></tr>

                </table>
                </td></tr>
            </table>
        </body>
        </html>";
    }

    private static function detailsBlock(array $rows): string {
        $html = "<div style='background:#f8f9fa;border-radius:8px;padding:18px;margin:18px 0'>";
        foreach ($rows as [$label, $value]) {
            $html .= "
            <div style='display:flex;gap:12px;padding:8px 0;border-bottom:1px solid #eee;font-size:14px'>
                <span style='min-width:130px;font-weight:600;color:#555'>{$label}</span>
                <span style='color:#333'>{$value}</span>
            </div>";
        }
        $html .= "</div>";
        return $html;
    }

    private static function badge(string $statut): string {
        $map = [
            'en attente'    => ['bg' => '#fff3cd', 'color' => '#856404'],
            'en traitement' => ['bg' => '#d1ecf1', 'color' => '#0c5460'],
            'résolu'        => ['bg' => '#d4edda', 'color' => '#155724'],
            'annulé'        => ['bg' => '#f8d7da', 'color' => '#721c24'],
        ];
        $b = $map[$statut] ?? ['bg' => '#e9ecef', 'color' => '#495057'];
        return "<span style='display:inline-block;background:{$b['bg']};color:{$b['color']};padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700'>"
             . htmlspecialchars($statut) . "</span>";
    }

    private static function ctaButton(string $label, string $url): string {
        return "
        <div style='margin-top:24px;text-align:center'>
            <a href='" . htmlspecialchars($url) . "'
               style='display:inline-block;padding:13px 30px;background:linear-gradient(135deg,#667eea,#764ba2);
                      color:white;text-decoration:none;border-radius:8px;font-weight:700;font-size:14px;
                      letter-spacing:0.2px;box-shadow:0 4px 12px rgba(102,126,234,.35)'>
                $label →
            </a>
        </div>";
    }

    private static function send(string $to, string $subject, string $body): bool {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = self::$smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = self::$smtpUser;
            $mail->Password   = self::$smtpPassword;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = self::$smtpPort;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(self::$smtpUser, self::$fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log("EmailNotifications::send() error to $to: " . $mail->ErrorInfo);
            return false;
        }
    }
}