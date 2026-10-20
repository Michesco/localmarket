<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Protection : doit être connecté
if (!estConnecte()) {
    header('Location: connexion.php');
    exit;
}

$db     = getDB();
$userId = $_SESSION['user_id'];
$id     = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: mon-compte.php?onglet=annonces');
    exit;
}

// Vérifier que l'annonce appartient bien à cet utilisateur
$stmt = $db->prepare('SELECT id FROM annonces WHERE id = ? AND utilisateur_id = ?');
$stmt->execute([$id, $userId]);
$annonce = $stmt->fetch();

if (!$annonce) {
    header('Location: mon-compte.php?onglet=annonces');
    exit;
}

// Supprimer les photos liées
$db->prepare('DELETE FROM photos WHERE annonce_id = ?')->execute([$id]);

// Supprimer les favoris liés
$db->prepare('DELETE FROM favoris WHERE annonce_id = ?')->execute([$id]);

// Supprimer les messages liés
$db->prepare('DELETE FROM messages WHERE annonce_id = ?')->execute([$id]);

// Supprimer l'annonce
$db->prepare('DELETE FROM annonces WHERE id = ? AND utilisateur_id = ?')->execute([$id, $userId]);

// Rediriger vers mes annonces
header('Location: mon-compte.php?onglet=annonces&supprime=1');
exit;