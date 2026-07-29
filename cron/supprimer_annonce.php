<?php
/**
* 🗑️ SUPPRESSION AUTOMATIQUE DES ANNONCES EXPIRÉES
* À exécuter tous les jours via CRON
* Emplacement : cron/supprimer_annonces.php
*/

require_once __DIR__ . '/../config/db.php';

// Passer les annonces expirées en statut 'expiree'
$stmt = $pdo->prepare("
    UPDATE annonces
    SET statut = 'expiree'
    WHERE date_expiration < NOW()
    AND statut != 'expiree'
");
$stmt->execute();

$count = $stmt->rowCount();
error_log("✅ $count annonces expirées ont été masquées.");

// Optionnel : Supprimer définitivement les annonces expirées depuis plus de 30 jours
$stmt = $pdo->prepare("
    DELETE FROM annonces
    WHERE statut = 'expiree'
    AND date_expiration < DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute();

$deleted = $stmt->rowCount();
error_log("🗑️ $deleted annonces ont été définitivement supprimées.");

echo "✅ Cron exécuté : $count annonces masquées, $deleted annonces supprimées.";