<?php
// src/supprimer_compte.php
session_start();

// 1. Connexion à la BDD et vérification de la session
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Sécurité : Uniquement accessible via une requête POST (venant de notre bouton de profil)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   
    try {
        // 2. Supprimer la photo de profil du serveur si elle existe
        $upload_dir = '../public/uploads/profils/';
        $allowed_extensions = ['jpg', 'jpeg', 'png'];
       
        foreach ($allowed_extensions as $ext) {
            $avatar_file = $upload_dir . "avatar_" . $user_id . "." . $ext;
            if (file_exists($avatar_file)) {
                unlink($avatar_file); // Supprime le fichier image
            }
        }

        // 3. Supprimer l'utilisateur de la base de données
        // (Le ON DELETE CASCADE de ta BDD supprimera automatiquement ses documents et codes de vérification)
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);

        // 4. Nettoyage complet de la session
        $_SESSION = array(); // Vide toutes les variables de session

        // Si un cookie de session est utilisé, on le détruit
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy(); // Détruit la session sur le serveur

        // 5. Redirection finale avec un message de confirmation (via cookie temporaire ou paramètre d'URL)
        // Comme la session est détruite, on passe par un paramètre d'URL pour informer l'utilisateur
        header('Location: ../public/register.php?account=deleted');
        exit();

    } catch (PDOException $e) {
        // En cas d'erreur, on remet une session pour avertir l'utilisateur
        $_SESSION['error'] = "Erreur système lors de la suppression du compte : " . $e->getMessage();
        header('Location: ../public/profil.php');
        exit();
    }

} else {
    // Redirection si tentative d'accès direct par URL (GET)
    header('Location: ../public/profil.php');
    exit();
}