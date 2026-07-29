<?php
// src/traitement_password.php
session_start();

// 1. Connexion à la BDD et vérification de la session
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Vérifier si le formulaire a bien été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   
    // 2. Récupération des données du formulaire
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation de sécurité de base : aucun champ vide
    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = "Tous les champs de mot de passe sont obligatoires.";
        header('Location: ../public/profil.php');
        exit();
    }

    // Sécurité supplémentaire : Longueur du nouveau mot de passe
    if (strlen($new_password) < 8) {
        $_SESSION['error'] = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
        header('Location: ../public/profil.php');
        exit();
    }

    // Vérification de la correspondance entre le nouveau mot de passe et sa confirmation
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "La confirmation ne correspond pas au nouveau mot de passe.";
        header('Location: ../public/profil.php');
        exit();
    }

    try {
        // 3. Récupérer le mot de passe actuel stocké en BDD
        $stmt = $pdo->prepare("SELECT mot_de_passe FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            $_SESSION['error'] = "Utilisateur introuvable.";
            header('Location: ../public/login.php');
            exit();
        }

        // 4. Vérifier si le mot de passe actuel saisi est le bon
        if (!password_verify($old_password, $user['mot_de_passe'])) {
            $_SESSION['error'] = "Le mot de passe actuel est incorrect.";
            header('Location: ../public/profil.php');
            exit();
        }

        // 5. Tout est OK -> On hash le nouveau mot de passe
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

        // Update en base de données
        $stmt_update = $pdo->prepare("UPDATE users SET mot_de_passe = ? WHERE id = ?");
        $stmt_update->execute([$new_password_hash, $user_id]);

        $_SESSION['success'] = "Votre mot de passe a été modifié avec succès !";
        header('Location: ../public/profil.php');
        exit();

    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur système lors du changement de mot de passe : " . $e->getMessage();
        header('Location: ../public/profil.php');
        exit();
    }

} else {
    // Redirection si tentative d'accès direct au fichier
    header('Location: ../public/profil.php');
    exit();
}