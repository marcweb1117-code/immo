Développement de site immobilier
<?php
// src/traitement_connexion.php
session_start();
require_once __DIR__ . '/../config/db.php';

// On vérifie que les données viennent bien du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   
    $email = trim($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';

    if (empty($email) || empty($mot_de_passe)) {
        $_SESSION['erreur_connexion'] = "Veuillez remplir tous les champs.";
        header('Location: ../public/connexion.php');
        exit();
    }

    try {
        // Recherche de l'utilisateur par son email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si l'utilisateur existe dans la base de données
        if ($user) {
           
            // 1. CAS DU BOSS SUPRÊME (Vérification simple en texte clair)
            if ($user['role'] === 'admin') {
                if ($mot_de_passe === $user['mot_de_passe']) {
                    // Connexion réussie pour l'admin
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_prenom'] = $user['prenom'];
                    $_SESSION['user_role'] = $user['role'];
                   
                    header('Location: ../public/admin_dashboard.php');
                    exit();
                } else {
                    $_SESSION['erreur_connexion'] = "Mot de passe Admin incorrect.";
                    header('Location: ../public/connexion.php');
                    exit();
                }
            }
           
            // 2. CAS DES UTILISATEURS NORMAUX (Vérification classique par mot de passe haché)
            if (password_verify($mot_de_passe, $user['mot_de_passe'])) {
               
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_prenom'] = $user['prenom'];
                $_SESSION['user_role'] = $user['role'];

                // Redirection selon le type de compte
                if ($user['type_compte'] === 'acheteur') {
                    header('Location: ../public/profil_acheteur.php');
                    exit();
                } else {
                    header('Location: ../public/user_dashboard.php');
                    exit();
                }
               
            } else {
                $_SESSION['erreur_connexion'] = "Identifiants ou email incorrects.";
                header('Location: ../public/connexion.php');
                exit();
            }

        } else {
            $_SESSION['erreur_connexion'] = "Identifiants ou email incorrects.";
            header('Location: ../public/connexion.php');
            exit();
        }

    } catch (\PDOException $e) {
        error_log("Erreur de connexion : " . $e->getMessage());
        $_SESSION['erreur_connexion'] = "Une erreur technique est survenue.";
        header('Location: ../public/connexion.php');
        exit();
    }
} else {
    // Si on essaie d'accéder au script sans passer par le formulaire
    header('Location: ../public/connexion.php');
    exit();
}