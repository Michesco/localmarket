enregistre le message en base de données, puis redirige automatiquement vers l'annonce avec un message de confirmationenregistre le message en base de données, puis redirige automatiquement vers l'annonce avec un message de confirmation<?php
/**
 * LocalMarket — envoyer-message.php
 * Fichier à placer dans : pages/envoyer-message.php
 */
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Doit être connecté
if (!estConnecte()) {
    header('Location: connexion.php');
    exit;
}

$db     = getDB();
$userId = $_SESSION['user_id'];

// ── Traitement envoi POST ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $annonce_id      = intval($_POST['annonce_id']      ?? 0);
    $destinataire_id = intval($_POST['destinataire_id'] ?? 0);
    $message         = trim($_POST['message']           ?? '');

    if (!$annonce_id || !$destinataire_id || empty($message)) {
        header('Location: annonce-detail.php?id=' . $annonce_id . '&erreur=message_vide');
        exit;
    }

    // Vérifier que l'annonce existe
    $stmt = $db->prepare('SELECT id FROM annonces WHERE id = ? AND statut = "active"');
    $stmt->execute([$annonce_id]);
    if (!$stmt->fetch()) {
        header('Location: annonces.php');
        exit;
    }

    // Ne pas s'envoyer un message à soi-même
    if ($destinataire_id === $userId) {
        header('Location: annonce-detail.php?id=' . $annonce_id . '&erreur=proprio');
        exit;
    }

    // Insérer le message
    $stmt = $db->prepare('
        INSERT INTO messages (annonce_id, expediteur_id, destinataire_id, contenu, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ');
    $stmt->execute([$annonce_id, $userId, $destinataire_id, $message]);

    // Rediriger vers l'annonce avec succès
    header('Location: annonce-detail.php?id=' . $annonce_id . '&message_envoye=1');
    exit;
}

// Accès direct GET → rediriger
header('Location: annonces.php');
exit;