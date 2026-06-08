<?php
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class MessageManager {
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function envoyer($idExpediteur, $idDestinataire, $contenu, $idRec = null, $idService = null, $idRequest = null)
    {
        $contenu = trim((string) $contenu);

        if ($contenu === '' || mb_strlen($contenu) > 2000) {
            throw new Exception('Le message doit contenir entre 1 et 2000 caracteres.');
        }

        if (!$idRec && !$idService && !$idRequest) {
            throw new Exception('Un message doit etre lie a une reclamation ou a une demande de service.');
        }

        $checkUsers = $this->pdo->prepare("
            SELECT idUtilisateur
            FROM utilisateur
            WHERE idUtilisateur IN (?, ?)
        ");
        $checkUsers->execute([$idExpediteur, $idDestinataire]);

        if ($checkUsers->rowCount() !== 2) {
            throw new Exception('Utilisateur invalide.');
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO message (idRec, idService, idRequest, idExpediteaur, idDestinataire, contenu, typeMessage)
                VALUES (?, ?, ?, ?, ?, ?, 'text')
            ");
            $stmt->execute([$idRec, $idService, $idRequest, $idExpediteur, $idDestinataire, $contenu]);

            $messageId = (int) $this->pdo->lastInsertId();
            $this->updateConversation($idRec, $idService, $idRequest, $idExpediteur, $idDestinataire, $messageId);

            return $messageId;
        } catch (PDOException $e) {
            throw new Exception('Erreur lors de l envoi du message: ' . $e->getMessage());
        }
    }

    private function updateConversation($idRec, $idService, $idRequest, $user1, $user2, $messageId): void
    {
        [$userA, $userB] = $this->normalizeParticipants($user1, $user2);

        $existing = $this->obtenirConversationParContexte($userA, $userB, $idRec, $idService, $idRequest);

        if ($existing) {
            $stmt = $this->pdo->prepare("
                UPDATE conversation
                SET dernier_message_id = ?, dateUpdate = NOW()
                WHERE idConversation = ?
            ");
            $stmt->execute([$messageId, $existing['idConversation']]);
            return;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO conversation (idRec, idService, idRequest, idUtilisateur1, idUtilisateur2, dernier_message_id, dateUpdate)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$idRec, $idService, $idRequest, $userA, $userB, $messageId]);
    }

    public function compterNonLus($idUtilisateur): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total
            FROM message
            WHERE idDestinataire = ? AND lu = FALSE
        ");
        $stmt->execute([$idUtilisateur]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['total'] ?? 0);
    }

    public function compterNonLusParConversation($idUtilisateur): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                CASE
                    WHEN c.idUtilisateur1 = ? THEN c.idUtilisateur2
                    ELSE c.idUtilisateur1
                END AS idAutre,
                c.idRec,
                c.idService,
                c.idRequest,
                COUNT(m.idMessage) AS nb_non_lu
            FROM conversation c
            LEFT JOIN message m ON (
                ((m.idRec = c.idRec) OR (m.idRec IS NULL AND c.idRec IS NULL))
                AND ((m.idService = c.idService) OR (m.idService IS NULL AND c.idService IS NULL))
                AND ((m.idRequest = c.idRequest) OR (m.idRequest IS NULL AND c.idRequest IS NULL))
                AND m.idDestinataire = ?
                AND m.lu = FALSE
            )
            WHERE c.idUtilisateur1 = ? OR c.idUtilisateur2 = ?
            GROUP BY c.idConversation, idAutre, c.idRec, c.idService, c.idRequest
        ");

        $stmt->execute([$idUtilisateur, $idUtilisateur, $idUtilisateur, $idUtilisateur]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenirConversationParContexte($user1, $user2, $idRec = null, $idService = null, $idRequest = null)
    {
        [$userA, $userB] = $this->normalizeParticipants($user1, $user2);

        $query = "
            SELECT idConversation
            FROM conversation
            WHERE idUtilisateur1 = ?
              AND idUtilisateur2 = ?
        ";
        $params = [$userA, $userB];

        if ($idRec) {
            $query .= " AND idRec = ?";
            $params[] = $idRec;
        } else {
            $query .= " AND idRec IS NULL";
        }

        if ($idService) {
            $query .= " AND idService = ?";
            $params[] = $idService;
        } else {
            $query .= " AND idService IS NULL";
        }

        if ($idRequest) {
            $query .= " AND idRequest = ?";
            $params[] = $idRequest;
        } else {
            $query .= " AND idRequest IS NULL";
        }

        $query .= " LIMIT 1";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function normalizeParticipants($user1, $user2): array
    {
        $participants = [(int) $user1, (int) $user2];
        sort($participants, SORT_NUMERIC);
        return $participants;
    }

    public function envoyerNotification($destinataire, $expediteur, $complaint, $message): void
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'amaltoumi535@gmail.com';
            $mail->Password = 'crmr mydm sqtn zdqn';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom('amaltoumi535@gmail.com', 'CityCare - Plateforme Reclamations');
            $mail->addAddress($destinataire['email']);

            $mail->isHTML(true);
            $mail->Subject = 'Nouveau message de ' . $expediteur['prenom'] . ' ' . $expediteur['nom'];

            $preview = mb_substr(strip_tags((string) $message['contenu']), 0, 100) . '...';

            $mail->Body = "
            <html>
                <body style='font-family: Arial, sans-serif;'>
                    <div style='max-width: 600px; margin: 0 auto;'>
                        <h2>Vous avez recu un nouveau message</h2>
                        <p>Bonjour <strong>" . htmlspecialchars($destinataire['prenom']) . "</strong>,</p>
                        <p><strong>" . htmlspecialchars($expediteur['prenom'] . ' ' . $expediteur['nom']) . "</strong> vient de vous envoyer un message concernant :</p>
                        <div style='background: #f5f5f5; padding: 15px; border-left: 4px solid #667eea; margin: 15px 0;'>
                            <p><strong>Sujet :</strong> " . htmlspecialchars($complaint['titre']) . "</p>
                            <p><strong>Message :</strong></p>
                            <p>" . nl2br(htmlspecialchars($preview)) . "</p>
                        </div>
                        <p><a href='http://localhost/AGL/messages.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Voir le message</a></p>
                        <hr>
                        <p style='font-size: 12px; color: #666;'>Cet email a ete envoye automatiquement. Veuillez ne pas repondre.</p>
                    </div>
                </body>
            </html>
            ";

            $mail->send();
        } catch (Exception $e) {
            error_log('Erreur lors de l envoi de notification email: ' . $mail->ErrorInfo);
        }
    }


    public function marquerCommeLu($idUtilisateur, $idAutre, $idRec = null, $idService = null, $idRequest = null): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE message
            SET lu = TRUE
            WHERE idDestinataire = ?
            AND idExpediteaur = ?
            AND ((idRec = ?) OR (idRec IS NULL AND ? IS NULL))
            AND ((idService = ?) OR (idService IS NULL AND ? IS NULL))
            AND ((idRequest = ?) OR (idRequest IS NULL AND ? IS NULL))
        ");
        $stmt->execute([
            $idUtilisateur, $idAutre,
            $idRec, $idRec,
            $idService, $idService,
            $idRequest, $idRequest
        ]);
    }

    public function obtenirThread($idRec = null, $idService = null, $idRequest = null, $user1, $user2, $limit = 50, $offset = 0): array
    {
        [$userA, $userB] = $this->normalizeParticipants($user1, $user2);

        $stmt = $this->pdo->prepare("
            SELECT m.*, u.prenom, u.nom
            FROM message m
            JOIN utilisateur u ON u.idUtilisateur = m.idExpediteaur
            WHERE ((m.idRec = ?) OR (m.idRec IS NULL AND ? IS NULL))
            AND ((m.idService = ?) OR (m.idService IS NULL AND ? IS NULL))
            AND ((m.idRequest = ?) OR (m.idRequest IS NULL AND ? IS NULL))
            AND ((m.idExpediteaur = ? AND m.idDestinataire = ?) OR (m.idExpediteaur = ? AND m.idDestinataire = ?))
            ORDER BY m.dateMessage ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([
            $idRec, $idRec,
            $idService, $idService,
            $idRequest, $idRequest,
            $userA, $userB,
            $userB, $userA,
            $limit, $offset
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
