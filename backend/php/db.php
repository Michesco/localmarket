<?php
/**
 * LocalMarket — Connexion à la base de données
 * Utilise PDO pour une meilleure sécurité
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'localmarket');
define('DB_USER', 'root');        // À changer en production
define('DB_PASS', '');            // À changer en production
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Connexion à la base de données impossible.']));
        }
    }
    return $pdo;
}
