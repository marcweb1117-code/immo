<?php
/**
* 🗑️ SUPPRESSION D'UNE PHOTO D'UNE ANNONCE
* Emplacement : src/traitement_supprimer_photo.php
*/

session_start();
require_once __DIR__ . '/../config/db.php';

// ============================================
// VÉRIFICATION DE SÉCURITÉ
// ============================================

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

// ============================================
// VÉRIFIER QUE L'ANNONCE APPARTIENT À L'UTILISATEUR
// ============================================

$stmt = $pdo->prepare("SELECT user_id FROM annonces WHERE id = ?");
$stmt->execute([$annonceId]);
$annonce = $stmt->fetch();

if (!$annonce || $annonce['user_id'] != $userId) {
    $_SESSION['erreur'] = "Vous n'êtes pas autorisé à modifier cette annonce.";
    header('Location: ../public/user_dashboard.php');
    exit();
}

// ============================================
// RÉCUPÉRER LES INFOS DE LA PHOTO
// ============================================

$stmt = $pdo->prepare("SELECT id, chemin_fichier, est_principale FROM photos_annonces WHERE id = ? AND annonce_id = ?");
$stmt->execute([$photoId, $annonceId]);
$photo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$photo) {
    $_SESSION['erreur'] = "Photo non trouvée.";
    header('Location: ../public/modifier_annonce.php?id=' . $annonceId);
    exit();
}

// ============================================
// VÉRIFIER QU'ON NE SUPPRIME PAS LA SEULE PHOTO
// ============================================

$stmt = $pdo->prepare("SELECT COUNT(*) FROM photos_annonces WHERE annonce_id = ?");
$stmt->execute([$annonceId]);
$totalPhotos = $stmt->fetchColumn();

if ($totalPhotos <= 1) {
    $_SESSION['erreur'] = "❌ Impossible de supprimer la seule photo de l'annonce. Ajoutez-en une autre d'abord.";
    header('Location: ../public/modifier_annonce.php?id=' . $annonceId);
    exit();
}

// ============================================
// SUPPRESSION DU FICHIER PHYSIQUE
// ============================================

$filePath = __DIR__ . '/../public/' . $photo['chemin_fichier'];

if (file_exists($filePath)) {
    unlink($filePath);
}

// ============================================
// SUPPRESSION EN BDD
// ============================================

$stmt = $pdo->prepare("DELETE FROM photos_annonces WHERE id = ? AND annonce_id = ?");
$stmt->execute([$photoId, $annonceId]);

// ============================================
// SI LA PHOTO SUPPRIMÉE ÉTAIT PRINCIPALE → EN DÉFINIR UNE NOUVELLE
// ============================================

if ($photo['est_principale']) {
    // Récupérer la première photo restante
    $stmt = $pdo->prepare("SELECT id FROM photos_annonces WHERE annonce_id = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$annonceId]);
    $newPrincipal = $stmt->fetch(PDO::FETCH_ASSOC);
   
    if ($newPrincipal) {
        $stmt = $pdo->prepare("UPDATE photos_annonces SET est_principale = 1 WHERE id = ?");
        $stmt->execute([$newPrincipal['id']]);
    }
}

// ============================================
// REDIRECTION
// ============================================

$_SESSION['success'] = "✅ La photo a été supprimée avec succès !";
header('Location: ../public/modifier_annonce.php?id=' . $annonceId);
exit();