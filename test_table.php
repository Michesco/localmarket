<?php
require_once 'pages/db.php';
$db = getDB();
try {
    $result = $db->query("SELECT 1 FROM messages LIMIT 1");
    echo "Table messages existe";
} catch (Exception $e) {
    echo "Table messages n'existe pas: " . $e->getMessage();
}
