<?php
/**
 * LocalMarket — Authentification
 */

require_once __DIR__ . '/db.php';
session_start();

// ─────────────────────────────────────────────
// INSCRIPTION
// ─────────────────────────────────────────────
function inscrireUtilisateur(array $data): array {
    $db = getDB();

    // Vérifier si l'email existe déjà
    $stmt = $db->prepare('SELECT id FROM utilisateurs WHERE email = ?');
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Cet email est déjà utilisé.'];
    }

    $hash = password_hash($data['mot_de_passe'], PASSWORD_BCRYPT);

    $stmt = $db->prepare('
        INSERT INTO utilisateurs (prenom, nom, email, telephone, mot_de_passe, ville)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        htmlspecialchars($data['prenom']),
        htmlspecialchars($data['nom']),
        strtolower(trim($data['email'])),
        $data['telephone'],
        $hash,
        $data['ville']
    ]);

    $id = $db->lastInsertId();
    $_SESSION['user_id']  = $id;
    $_SESSION['user_nom'] = $data['prenom'];

    return ['success' => true, 'user_id' => $id];
}

// ─────────────────────────────────────────────
// CONNEXION
// ─────────────────────────────────────────────
function connecterUtilisateur(string $email, string $mot_de_passe): array {
    $db = getDB();

    $stmt = $db->prepare('SELECT * FROM utilisateurs WHERE email = ? AND est_actif = 1');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($mot_de_passe, $user['mot_de_passe'])) {
        return ['success' => false, 'message' => 'Email ou mot de passe incorrect.'];
    }

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_nom']   = $user['prenom'];
    $_SESSION['user_email'] = $user['email'];

    return ['success' => true, 'user' => [
        'id'     => $user['id'],
        'prenom' => $user['prenom'],
        'nom'    => $user['nom'],
        'email'  => $user['email'],
        'ville'  => $user['ville'],
    ]];
}

// ─────────────────────────────────────────────
// DÉCONNEXION
// ─────────────────────────────────────────────
function deconnecter(): void {
    session_destroy();
    header('Location: ../pages/connexion.html');
    exit;
}

// ─────────────────────────────────────────────
// VÉRIFICATION SESSION
// ─────────────────────────────────────────────
function estConnecte(): bool {
    return isset($_SESSION['user_id']);
}

function requireAuth(): void {
    if (!estConnecte()) {
        header('Location: ../pages/connexion.html');
        exit;
    }
}
