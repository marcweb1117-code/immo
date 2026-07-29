<?php
/**
* 🗑️ SUPPRESSION D'UNE ANNONCE
* Emplacement : src/traitement_suppression_annonce.php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/user_dashboard.php');
    exit();
}

$annonceId = filter_input(INPUT_POST, 'annonce_id', FILTER_VALIDATE_INT);

if (!$annonceId) {
    $_SESSION['erreur'] = "Annonce non trouvée.";
    header('Location: ../public/user_dashboard.php');
    exit();
}

// Vérifier que l'annonce appartient bien à l'utilisateur
$stmt = $pdo->prepare("SELECT id FROM annonces WHERE id = :id AND user_id = :user_id");
$stmt->execute([':id' => $annonceId, ':user_id' => $userId]);
$annonce = $stmt->fetch();

if (!$annonce) {
    $_SESSION['erreur'] = "Vous n'êtes pas autorisé à supprimer cette annonce.";
    header('Location: ../public/user_dashboard.php');
    exit();
}

// ============================================
// SUPPRESSION
// ============================================

try {
    // Supprimer les photos physiquement
    $stmtPhotos = $pdo->prepare("SELECT chemin_fichier FROM photos_annonces WHERE annonce_id = ?");
    $stmtPhotos->execute([$annonceId]);
    $photos = $stmtPhotos->fetchAll();

    foreach ($photos as $photo) {
        $filePath = __DIR__ . '/../public/' . $photo['chemin_fichier'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // Supprimer les enregistrements des photos
    $stmt = $pdo->prepare("DELETE FROM photos_annonces WHERE annonce_id = ?");
    $stmt->execute([$annonceId]);

    // Supprimer l'annonce
    $stmt = $pdo->prepare("DELETE FROM annonces WHERE id = ? AND user_id = ?");
    $stmt->execute([$annonceId, $userId]);

    $_SESSION['success'] = "🗑️ L'annonce a été supprimée avec succès.";
    header('Location: ../public/user_dashboard.php');
    exit();

} catch (PDOException $e) {
    error_log("Erreur suppression annonce : " . $e->getMessage());
    $_SESSION['erreur'] = "Erreur lors de la suppression : " . $e->getMessage();
    header('Location: ../public/user_dashboard.php');
    exit();
}