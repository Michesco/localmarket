<?php
require_once 'pages/db.php';

try {
    $db = getDB();
    
    // Créer la table messages si elle n'existe pas
    $sql = "CREATE TABLE IF NOT EXISTS messages (
      id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      annonce_id   INT UNSIGNED NOT NULL,
      expediteur_id INT UNSIGNED NOT NULL,
      destinataire_id INT UNSIGNED NOT NULL,
      contenu      TEXT         NOT NULL,
      lu           TINYINT(1)   DEFAULT 0,
      created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (annonce_id)        REFERENCES annonces(id)      ON DELETE CASCADE,
      FOREIGN KEY (expediteur_id)     REFERENCES utilisateurs(id)  ON DELETE CASCADE,
      FOREIGN KEY (destinataire_id)   REFERENCES utilisateurs(id)  ON DELETE CASCADE,
      INDEX idx_conversation (annonce_id, expediteur_id, destinataire_id)
    ) ENGINE=InnoDB";
    
    $db->exec($sql);
    echo "✅ Table messages créée ou existe déjà<br>";
    
    // Vérifier
    $stmt = $db->query("SELECT COUNT(*) as total FROM messages");
    $count = $stmt->fetch();
    echo "📊 Nombre de messages: " . $count['total'];
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage();
}
