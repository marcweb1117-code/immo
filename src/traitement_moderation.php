<?php
// =========================================================================
// PARTIE 1 : SÉCURISATION DU SCRIPT (Vérification Rôle Admin & Jeton CSRF)
// =========================================================================
session_start();
require_once __DIR__ . '/../config/db.php';

// Sécurité 1 : On bloque immédiatement si la personne n'est pas Admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/index.php');
    exit();
}

// Sécurité 2 : On vérifie si la requête vient bien en POST et si le jeton CSRF correspond
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['erreur_moderation'] = "Requête invalide.";
    header('Location: ../public/admin_dashboard.php');
    exit();
}

if (!isset($_POST['csrf_token_admin']) || $_POST['csrf_token_admin'] !== $_SESSION['csrf_token_admin']) {
    $_SESSION['erreur_moderation'] = "Échec de la vérification de sécurité (CSRF).";
    header('Location: ../public/admin_dashboard.php');
    exit();
}

// =========================================================================
// PARTIE 2 : RÉCUPÉRATION ET FILTRAGE DES DONNÉES ENTRANTES
// =========================================================================
$userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$motifRefus = isset($_POST['motif_refus']) ? trim($_POST['motif_refus']) : '';

// Validation de base : il faut un ID utilisateur valide pour travailler
if ($userId <= 0) {
    $_SESSION['erreur_moderation'] = "Identifiant utilisateur introuvable.";
    header('Location: ../public/admin_dashboard.php');
    exit();
}

try {
    // On commence une transaction SQL pour sécuriser les modifications croisées
    $pdo->beginTransaction();

    // =========================================================================
    // PARTIE 3 : TRAITEMENT DE L'OPTION A - VALIDATION DU COMPTE (MIS À JOUR)
    // =========================================================================
    if ($action === 'valider') {
      
        // 1. On passe le statut de l'utilisateur à 'valide'
        $stmtUpdateUser = $pdo->prepare("UPDATE users SET statut_verification = 'valide' WHERE id = ?");
        $stmtUpdateUser->execute([$userId]);

        // 2. On passe aussi le statut du document à 'valide' et on nettoie l'éventuel ancien motif de refus
        $stmtUpdateDoc = $pdo->prepare("UPDATE documents_verification SET statut = 'valide', motif_refus = NULL WHERE user_id = ?");
        $stmtUpdateDoc->execute([$userId]);

        $_SESSION['message_moderation'] = "Le compte de l'utilisateur a été validé avec succès ! 🎉";
  
    // =========================================================================
    // PARTIE 4 : TRAITEMENT DE L'OPTION B - REFUS DU DOSSIER (MIS À JOUR)
    // =========================================================================
    } elseif ($action === 'refuser') {
      
        // Sécurité : On valide que le motif de refus n'est pas vide
        if (empty($motifRefus)) {
            $_SESSION['erreur_moderation'] = "Le motif du refus est obligatoire.";
            header('Location: ../public/admin_dashboard.php');
            exit();
        }

        // 1. On passe le statut de l'utilisateur à 'refuse'
        $stmtUpdateUser = $pdo->prepare("UPDATE users SET statut_verification = 'refuse' WHERE id = ?");
        $stmtUpdateUser->execute([$userId]);

        // 2. On met à jour le document : statut 'refuse' et stockage du motif
        $stmtUpdateDoc = $pdo->prepare("UPDATE documents_verification SET statut = 'refuse', motif_refus = ? WHERE user_id = ?");
        $stmtUpdateDoc->execute([$motifRefus, $userId]);

        $_SESSION['message_moderation'] = "Le dossier a été refusé et le motif a été enregistré. ❌";
      
    } else {
        $_SESSION['erreur_moderation'] = "Action de modération inconnue.";
    }

    // Si tout s'est bien passé, on valide définitivement les changements dans la base de données
    $pdo->commit();

} catch (\PDOException $e) {
    // En cas de gros bug SQL, on annule tout pour ne pas corrompre les données
    $pdo->rollBack();
    error_log("Erreur traitement modération : " . $e->getMessage());
    $_SESSION['erreur_moderation'] = "Une erreur technique est survenue durant la mise à jour.";
}

// =========================================================================
// PARTIE 5 : RETOUR AUTOMATIQUE ET REDIRECTION VERS LE DASHBOARD
// =========================================================================
header('Location: ../public/admin_dashboard.php');
exit();