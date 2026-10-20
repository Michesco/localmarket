<?php
require_once 'pages/db.php';

try {
    $db = getDB();
    
    // Créer la table messages
    $sql = "CREATE TABLE IF NOT EXISTS messages (
      id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      annonce_id   INT UNSIGNED NOT NULL,
      expediteur_id INT UNSIGNED NOT NULL,
      destinataire_id INT UNSIGNED NOT NULL,
      contenu      TEXT         NOT NULL,
      lu           TINYINT(1)   DEFAULT 0,
      created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_conversation (annonce_id, expediteur_id, destinataire_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "Table messages créée avec succès";
    
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage();
}
