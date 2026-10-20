<?php
require_once 'pages/db.php';

try {
    $db = getDB();
    
    // Vérifier si la table messages existe
    $stmt = $db->query("SHOW TABLES LIKE 'messages'");
    $result = $stmt->fetch();
    
    if ($result) {
        echo "✅ Table messages existe<br>";
        
        // Compter les messages
        $stmt = $db->query("SELECT COUNT(*) as total FROM messages");
        $count = $stmt->fetch();
        echo "📊 Nombre de messages: " . $count['total'] . "<br>";
        
        // Voir quelques messages
        if ($count['total'] > 0) {
            $stmt = $db->query("SELECT * FROM messages LIMIT 5");
            $messages = $stmt->fetchAll();
            echo "<pre>";
            print_r($messages);
            echo "</pre>";
        }
    } else {
        echo "❌ Table messages n'existe pas<br>";
    }
    
    // Lister toutes les tables
    echo "<br>📋 Toutes les tables:<br>";
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll();
    foreach ($tables as $table) {
        echo "- " . array_values($table)[0] . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage();
}
