<?php
// config/db.php

// Configuration des identifiants (À adapter selon ton environnement local/production)
$host     = 'localhost';
$db       = 'immobilier';
$user     = 'root';
$password = '';
$charset  = 'utf8mb4';

// Définition de la chaîne de connexion (DSN)
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Options de configuration PDO pour maximiser la sécurité et la robustesse
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Active les exceptions en cas d'erreur SQL
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retourne les résultats sous forme de tableau associatif
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Désactive l'émulation pour utiliser de vraies requêtes préparées
];

try {
    // Initialisation de la connexion globale
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (\PDOException $e) {
    // En production, il ne faut jamais afficher $e->getMessage() directement (fuite d'informations sensibles)
    // On journalise l'erreur en interne et on affiche un message générique propre
    error_log($e->getMessage());
    die("Une erreur technique est survenue. Veuillez réessayer plus tard.");
}