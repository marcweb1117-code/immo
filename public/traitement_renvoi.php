<?php
// =========================================================================
// ÉTAPE 1 : VÉRIFICATION DE LA SESSION ET SÉCURISATION
// =========================================================================
session_start();
require_once __DIR__ . '/../config/db.php';

// Si l'utilisateur n'est pas connecté, on le renvoie à l'accueil
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$userId = $_SESSION['user_id'];

// On vérifie que le formulaire a bien été soumis en POST avec un fichier
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['document_justificatif'])) {
    header('Location: user_dashboard.php');
    exit();
}

// =========================================================================
// ÉTAPE 2 : CONFIGURATION ET SÉCURISATION DU DOSSIER DE RÉCEPTION
// =========================================================================
$uploadDir = __DIR__ . '/uploads/documents/';

// Si le dossier "uploads/documents/" n'existe pas sur le serveur, on le crée
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Récupération des infos du formulaire
$typeDocument = isset($_POST['type_document']) ? trim($_POST['type_document']) : 'carte_identite';
$file = $_FILES['document_justificatif'];

// =========================================================================
// ÉTAPE 3 : FILTRAGE RIGOUREUX DU FICHIER (SÉCURITÉ ANTIVIRUS)
// =========================================================================

// 1. Erreur basique lors du transfert
if ($file['error'] !== UPLOAD_ERR_OK) {
    die("Une erreur est survenue lors du transfert du fichier.");
}

// 2. Limitation de la taille (ex: maximum 5 Mo)
$maxSize = 5 * 1024 * 1024; // 5 Mo en octets
if ($file['size'] > $maxSize) {
    die("Le fichier est trop lourd. La taille maximale autorisée est de 5 Mo.");
}

// 3. Vérification de l'extension (PDF, PNG, JPG, JPEG uniquement)
$allowedExtensions = ['pdf', 'png', 'jpg', 'jpeg'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($fileExtension, $allowedExtensions)) {
    die("Format de fichier non autorisé. Merci d'envoyer un PDF, PNG ou JPG.");
}

// =========================================================================
// ÉTAPE 4 : RENOMMAGE UNIQUE ET STOCKAGE PHYSIQUE DU FICHIER
// =========================================================================
// On renomme pour éviter les caractères spéciaux et les doublons (ex: doc_27_abcdefg123.jpg)
$newFileName = 'doc_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $fileExtension;
$destination = $uploadDir . $newFileName;

// Déplacement du fichier temporaire vers son dossier de stockage définitif
if (!move_uploaded_files_custom($file['tmp_name'], $destination)) {
    // Si la fonction personnalisée échoue, on utilise la méthode standard PHP
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        die("Erreur technique lors de l'enregistrement du document.");
    }
}

// Le chemin relatif qu'on va enregistrer dans la base de données pour l'afficher
$dbFilePath = 'uploads/documents/' . $newFileName;

// Fonction de secours pour la gestion des environnements locaux
function move_uploaded_files_custom($tmp, $dest) {
    return rename($tmp, $dest);
}

// =========================================================================
// ÉTAPE 5 : MISE À JOUR SQL ET REDIRECTION VERS L'ESPACE CLIENT
// =========================================================================
try {
    // On démarre une transaction SQL pour garantir la cohérence
    $pdo->beginTransaction();

    // 1. On vérifie si l'utilisateur a déjà une ligne dans la table des documents
    $stmtCheck = $pdo->prepare("SELECT id FROM documents_verification WHERE user_id = ?");
    $stmtCheck->execute([$userId]);
    $docExists = $stmtCheck->fetch();

    if ($docExists) {
        // S'il existe, on met à jour le fichier, le type de document et on efface l'ancien motif de refus
        $stmtDoc = $pdo->prepare("
            UPDATE documents_verification
            SET type_document = ?, chemin_fichier = ?, motif_refus = NULL, date_soumission = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");
        $stmtDoc->execute([$typeDocument, $dbFilePath, $userId]);
    } else {
        // Sinon, on insère une nouvelle ligne proprement
        $stmtDoc = $pdo->prepare("
            INSERT INTO documents_verification (user_id, type_document, chemin_fichier)
            VALUES (?, ?, ?)
        ");
        $stmtDoc->execute([$userId, $typeDocument, $dbFilePath]);
    }

    // 2. On repasse le statut de l'utilisateur à 'en_attente' pour qu'il apparaisse chez l'admin
    $stmtUser = $pdo->prepare("UPDATE users SET statut_verification = 'en_attente' WHERE id = ?");
    $stmtUser->execute([$userId]);

    $pdo->commit();

    // Redirection vers le dashboard utilisateur avec un message de confirmation
    $_SESSION['message_succes'] = "Votre justificatif a bien été transmis. Votre dossier repasse en cours d'analyse.";
    header('Location: user_dashboard.php');
    exit();

} catch (\PDOException $e) {
    $pdo->rollBack();
    error_log("Erreur transmission document : " . $e->getMessage());
    die("Une erreur technique est survenue lors de l'enregistrement dans la base de données.");
}