<?php
/**
* 📝 TRAITEMENT DE MODIFICATION D'UNE ANNONCE
* Emplacement : src/traitement_modification_annonce.php
*/

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/phash.php';

// Sécurité : Vérifier si l'utilisateur est connecté
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
    $_SESSION['erreur'] = "Vous n'êtes pas autorisé à modifier cette annonce.";
    header('Location: ../public/user_dashboard.php');
    exit();
}

// Récupération des données du formulaire
$titre = filter_input(INPUT_POST, 'titre', FILTER_SANITIZE_SPECIAL_CHARS);
$description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_SPECIAL_CHARS);
$prix = filter_input(INPUT_POST, 'prix', FILTER_VALIDATE_FLOAT);
$surface = filter_input(INPUT_POST, 'surface', FILTER_VALIDATE_INT);
$pieces = filter_input(INPUT_POST, 'pieces', FILTER_VALIDATE_INT) ?: 1;
$type_bien = $_POST['type_bien'] ?? 'appartement';
$type_transaction = $_POST['type_transaction'] ?? 'vente';
$adresse = filter_input(INPUT_POST, 'adresse', FILTER_SANITIZE_SPECIAL_CHARS);
$code_postal = filter_input(INPUT_POST, 'code_postal', FILTER_SANITIZE_SPECIAL_CHARS);
$ville = filter_input(INPUT_POST, 'ville', FILTER_SANITIZE_SPECIAL_CHARS);
$latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT) ?: null;
$longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT) ?: null;
$statut = $_POST['statut'] ?? 'disponible';

$has_parking = isset($_POST['has_parking']) ? 1 : 0;
$has_balcon = isset($_POST['has_balcon']) ? 1 : 0;
$has_ascenseur = isset($_POST['has_ascenseur']) ? 1 : 0;

// Validation
if (!$titre || !$prix || !$surface || !$ville) {
    $_SESSION['erreur'] = "Veuillez remplir tous les champs obligatoires.";
    header('Location: ../public/modifier_annonce.php?id=' . $annonceId);
    exit();
}

try {
    // Mise à jour de l'annonce
    $stmt = $pdo->prepare("
        UPDATE annonces SET
            titre = :titre,
            description = :description,
            prix = :prix,
            surface = :surface,
            pieces = :pieces,
            type_bien = :type_bien,
            type_transaction = :type_transaction,
            adresse = :adresse,
            code_postal = :code_postal,
            ville = :ville,
            latitude = :latitude,
            longitude = :longitude,
            has_parking = :has_parking,
            has_balcon = :has_balcon,
            has_ascenseur = :has_ascenseur,
            statut = :statut,
            date_modification = NOW()
        WHERE id = :id AND user_id = :user_id
    ");

    $stmt->execute([
        ':titre' => $titre,
        ':description' => $description,
        ':prix' => $prix,
        ':surface' => $surface,
        ':pieces' => $pieces,
        ':type_bien' => $type_bien,
        ':type_transaction' => $type_transaction,
        ':adresse' => $adresse,
        ':code_postal' => $code_postal,
        ':ville' => $ville,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':has_parking' => $has_parking,
        ':has_balcon' => $has_balcon,
        ':has_ascenseur' => $has_ascenseur,
        ':statut' => $statut,
        ':id' => $annonceId,
        ':user_id' => $userId
    ]);
// Gestion des nouvelles photos
// ============================================
// GESTION DES NOUVELLES PHOTOS (AVEC ANTI-DOUBLON)
// ============================================

if (isset($_FILES['nouvelles_photos']) && !empty($_FILES['nouvelles_photos']['name'][0])) {
    $uploadDir = __DIR__ . '/../public/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Récupérer les photos existantes pour savoir si l'annonce a déjà une photo principale
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM photos_annonces WHERE annonce_id = ? AND est_principale = 1");
    $stmtCheck->execute([$annonceId]);
    $hasPrincipal = $stmtCheck->fetchColumn() > 0;

    $totalPhotos = count($_FILES['nouvelles_photos']['name']);
    for ($i = 0; $i < $totalPhotos; $i++) {
        if ($_FILES['nouvelles_photos']['error'][$i] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['nouvelles_photos']['tmp_name'][$i];
           
            // ✅ 1. CALCUL DU PHASH
            $pHash = calculerPHashImage($tmpPath);
           
            // ✅ 2. VÉRIFICATION ANTI-DOUBLON (GLOBALE)
            if ($pHash && verifierDoublonPHash($pdo, $pHash, 5)) {
                $_SESSION['erreur'] = "❌ L'image \"" . htmlspecialchars($_FILES['nouvelles_photos']['name'][$i]) . "\" est déjà présente sur le site dans une autre annonce.";
                header('Location: ../public/modifier_annonce.php?id=' . $annonceId);
                exit();
            }
           
            // ✅ 3. SAUVEGARDE DU FICHIER
            $extension = strtolower(pathinfo($_FILES['nouvelles_photos']['name'][$i], PATHINFO_EXTENSION));
            $newFileName = uniqid('img_', true) . '.' . $extension;
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($tmpPath, $destination)) {
                $photoUrl = 'uploads/' . $newFileName;
               
                // ✅ 4. DÉTERMINER SI C'EST LA PHOTO PRINCIPALE
                // Si l'annonce n'a pas encore de photo principale ET que c'est la première photo uploadée
                $estPrincipale = (!$hasPrincipal && $i === 0) ? 1 : 0;
               
                $stmtPhoto = $pdo->prepare("
                    INSERT INTO photos_annonces (annonce_id, chemin_fichier, image_hash, est_principale)
                    VALUES (:annonce_id, :chemin_fichier, :image_hash, :est_principale)
                ");
                $stmtPhoto->execute([
                    ':annonce_id' => $annonceId,
                    ':chemin_fichier' => $photoUrl,
                    ':image_hash' => $pHash,
                    ':est_principale' => $estPrincipale
                ]);
            }
        }
    }
}
    $_SESSION['success'] = "✅ L'annonce a été modifiée avec succès !";
    header('Location: ../public/modifier_annonce.php?id=' . $annonceId);
    exit();

} catch (PDOException $e) {
    error_log("Erreur modification annonce : " . $e->getMessage());
    $_SESSION['erreur'] = "Erreur lors de la modification : " . $e->getMessage();
    header('Location: ../public/modifier_annonce.php?id=' . $annonceId);
    exit();
}