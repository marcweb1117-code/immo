<?php
/**
* 🔄 RENOUVELLEMENT D'UNE ANNONCE
* Emplacement : src/traitement_renouveler_annonce.php
*/

session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/connexion.php');
    exit();
}

$userId = $_SESSION['user_id'];
$annonceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$annonceId) {
    $_SESSION['erreur'] = "Annonce non trouvée.";
    header('Location: ../public/user_dashboard.php');
    exit();
}

// Vérifier que l'annonce appartient à l'utilisateur
$stmt = $pdo->prepare("SELECT id, renouvellements FROM annonces WHERE id = ? AND user_id = ?");
$stmt->execute([$annonceId, $userId]);
$annonce = $stmt->fetch();

if (!$annonce) {
    $_SESSION['erreur'] = "Vous n'êtes pas autorisé.";
    header('Location: ../public/user_dashboard.php');
    exit();
}

// Limiter à 5 renouvellements par annonce (optionnel)
if ($annonce['renouvellements'] >= 5) {
    $_SESSION['erreur'] = "❌ Cette annonce a déjà été renouvelée 5 fois. Vous ne pouvez plus la renouveler.";
    header('Location: ../public/user_dashboard.php');
    exit();
}

// Renouveler pour 21 jours
$newExpiration = date('Y-m-d H:i:s', strtotime('+21 days'));

$stmt = $pdo->prepare("
    UPDATE annonces
    SET date_expiration = :date_expiration,
        renouvellements = renouvellements + 1,
        statut = 'disponible'
    WHERE id = :id AND user_id = :user_id
");
$stmt->execute([
    ':date_expiration' => $newExpiration,
    ':id' => $annonceId,
    ':user_id' => $userId
]);

$_SESSION['success'] = "✅ Annonce renouvelée avec succès ! Elle est maintenant disponible pour 3 semaines.";
header('Location: ../public/user_dashboard.php');
exit();