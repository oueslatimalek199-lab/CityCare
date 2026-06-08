<?php
require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Config/session.php';

class Auth {

    public static function connecter(string $email, string $mot_de_passe): bool|string {
        $pdo = getConnexion();

        $stmt = $pdo->prepare("
            SELECT idUtilisateur,
                   nom,
                   prenom,
                   email,
                   mot_de_passe,
                   role,
                   statut
            FROM   utilisateur
            WHERE  email = :email
            LIMIT  1
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        if (!password_verify($mot_de_passe, $user['mot_de_passe'])) {
            return false;
        }

        if ($user['role'] === 'agent' && $user['statut'] === 'inactif') {
            return 'agent_desactive';
        }

        session_regenerate_id(true);

        $_SESSION['connecte']      = true;
        $_SESSION['idUtilisateur'] = $user['idUtilisateur'];
        $_SESSION['nom']           = $user['nom'];
        $_SESSION['prenom']        = $user['prenom'];
        $_SESSION['email']         = $user['email'];
        $_SESSION['role']          = $user['role'];

        return true;
    }

    public static function deconnecter(): void {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }

        session_destroy();
    }

    public static function estConnecte(): bool {
        return !empty($_SESSION['connecte']) && $_SESSION['connecte'] === true;
    }

    public static function exigerConnexion(string $redirect = 'login.php'): void {
        if (!self::estConnecte()) {
            header('Location: ' . $redirect);
            exit;
        }
    }

    public static function getUtilisateur(): ?array {
        if (!self::estConnecte()) return null;
        return [
            'idUtilisateur' => $_SESSION['idUtilisateur'],
            'nom'           => $_SESSION['nom'],
            'prenom'        => $_SESSION['prenom'],
            'email'         => $_SESSION['email'],
            'role'          => $_SESSION['role'],
        ];
    }

    public static function redirectToDashboard(): void {
        if (!self::estConnecte()) {
            header('Location: ./login.php');
            exit;
        }

        $role = $_SESSION['role'] ?? null;

        if ($role === 'citoyen') {
            header('Location: ./citoyen.php');
        } elseif ($role === 'agent') {
            header('Location: ./agent.php');
        } elseif ($role === 'admin') {
            header('Location: ./admin.php');
        } else {
            self::deconnecter();
            header('Location: ./login.php?erreur=role_inconnu');
        }
        exit;
    }
}