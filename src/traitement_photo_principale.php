<?php
/**
* ⭐ CHANGER L'IMAGE PRINCIPALE D'UNE ANNONCE
* Emplacement : src/traitement_photo_principale.php
*/

session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/connexion.php');
    exit();
}

$userId = $_SESSION['user_id'];
$photoId = filter_input(INPUT_GET, 'photo_id', FILTER_VALIDATE_INT);
$annonceId = filter_input(INPUT_GET, 'annonce_id', FILTER_VALIDATE_INT);

if (!$photoId || !$annonceId) {
    $_SESSION['erreur'] = "Paramètres manquants.";
    header('Location: ../public/user_dashboard.php');
    exit();
}

// Vérifier que l'annonce appartient à l'utilisateur
$stmt = $pdo->prepare("SELECT user_id FROM annonces WHERE id = ?");
$stmt->execute([$annonceId]);
$annonce = $stmt->fetch();

if (!$annonce || $annonce['user_id'] != $userId) {
    $_SESSION['erreur'] = "Vous n'êtes pas autorisé.";
    header('Location: ../public/user_dashboard.php');
    exit();
}

try {
    // 1. Retirer le statut "principale" à toutes les photos de l'annonce
    $stmt = $pdo->prepare("UPDATE photos_annonces SET est_principale = 0 WHERE annonce_id = ?");
    $stmt->execute([$annonceId]);

    // 2. Définir la nouvelle photo comme principale
    $stmt = $pdo->prepare("UPDATE photos_annonces SET est_principale = 1 WHERE id = ? AND annonce_id = ?");
    $stmt->execute([$photoId, $annonceId]);

    $_SESSION['success'] = "⭐ L'image principale a été mise à jour !";
    header('Location: ../public/modifier_annonce.php?id=' . $annonceId);
    exit();

} catch (PDOException $e) {
    error_log("Erreur changement photo principale : " . $e->getMessage());
    $_SESSION['erreur'] = "Erreur lors du changement d'image principale.";
    header('Location: ../public/modifier_annonce.php?id=' . $annonceId);
    exit();
}