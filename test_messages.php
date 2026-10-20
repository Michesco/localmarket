<?php
require_once 'pages/db.php';

try {
    $db = getDB();
    
    // Vérifier si la table messages existe
    $stmt = $db->query("SHOW TABLES LIKE 'messages'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✅ La table messages existe\n";
        
        // Compter les messages
        $stmt = $db->query("SELECT COUNT(*) as total FROM messages");
        $result = $stmt->fetch();
        echo "📊 Nombre de messages: " . $result['total'] . "\n";
        
        // Voir la structure
        echo "\n📋 Structure de la table:\n";
        $stmt = $db->query("DESCRIBE messages");
        print_r($stmt->fetchAll());
        
    } else {
        echo "❌ La table messages n'existe pas\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
