<?php
// src/traitement_profil.php
session_start();

// 1. Connexion à la BDD et vérification de la session
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Par défaut, si la requête n'est pas un POST ou en cas de problème initial
$redirect_page = '../public/profil_acheteur.php';

try {
    // On récupère le type_compte directement en BDD pour savoir précisément où rediriger
    $stmt_type = $pdo->prepare("SELECT type_compte FROM users WHERE id = ?");
    $stmt_type->execute([$user_id]);
    $user_data = $stmt_type->fetch();
   
    if ($user_data) {
        // Si c'est un acheteur -> profil_acheteur.php, sinon -> profil_vendeur.php
        $redirect_page = ($user_data['type_compte'] === 'acheteur') ? '../public/profil_acheteur.php' : '../public/profil.php';
    }
} catch (PDOException $e) {
    // En cas de panne BDD précoce, on laisse la valeur par défaut
}

// Vérifier si le formulaire a bien été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   
    // 2. Nettoyage et récupération des données textuelles
    $prenom = trim(htmlspecialchars($_POST['prenom']));
    $nom = trim(htmlspecialchars($_POST['nom']));
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $telephone = trim(htmlspecialchars($_POST['telephone']));

    // Validation de sécurité de base
    if (empty($prenom) || empty($nom) || !$email || empty($telephone)) {
        $_SESSION['error'] = "Tous les champs textuels sont obligatoires et l'email doit être valide.";
        header("Location: $redirect_page");
        exit();
    }

    try {
        // 3. Récupérer les anciennes données pour voir s'il y a des changements (Email / Tel)
        $stmt_check = $pdo->prepare("SELECT prenom, nom, email, telephone, email_verifie, telephone_verifie FROM users WHERE id = ?");
        $stmt_check->execute([$user_id]);
        $current_user = $stmt_check->fetch();

        if (!$current_user) {
            $_SESSION['error'] = "Utilisateur introuvable.";
            header('Location: ../public/login.php');
            exit();
        }

        // 4. Détection des changements réels
        $un_changement = false;

        if ($prenom !== $current_user['prenom'] ||
            $nom !== $current_user['nom'] ||
            $email !== $current_user['email'] ||
            $telephone !== $current_user['telephone']) {
            $un_changement = true;
        }

        // On vérifie aussi si une nouvelle photo de profil est envoyée
        if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] !== UPLOAD_ERR_NO_FILE) {
            $un_changement = true;
        }

        // Si aucun changement n'est détecté, on stoppe et on met un message d'info neutre ou erreur discrète
        if (!$un_changement) {
            $_SESSION['error'] = "ℹ️ Aucune modification n'a été détectée.";
            header("Location: $redirect_page");
            exit();
        }

        // Vérifier si l'email a changé et s'il est déjà utilisé par un AUTRE utilisateur
        $email_verifie = $current_user['email_verifie'];
        if ($email !== $current_user['email']) {
            $stmt_unique = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt_unique->execute([$email, $user_id]);
            if ($stmt_unique->fetch()) {
                $_SESSION['error'] = "Cette adresse e-mail est déjà utilisée par un autre compte.";
                header("Location: $redirect_page");
                exit();
            }
            // L'email a changé -> Il n'est plus vérifié !
            $email_verifie = 0;
        }

        // Vérifier si le téléphone a changé
        $telephone_verifie = $current_user['telephone_verifie'];
        if ($telephone !== $current_user['telephone']) {
            // Le téléphone a changé -> Il n'est plus vérifié !
            $telephone_verifie = 0;
        }

        // 5. Gestion de l'upload de la Photo de Profil
        if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['photo_profil'];

            // Limiter la taille à 5 Mo
            $max_size = 5 * 1024 * 1024;
            // Extensions et types MIME autorisés
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            $allowed_mime_types = ['image/jpeg', 'image/jpg', 'image/png'];

            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $file_mime = mime_content_type($file['tmp_name']);

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['error'] = "Une erreur est survenue lors du téléchargement de la photo.";
                header("Location: $redirect_page");
                exit();
            }

            if ($file['size'] > $max_size) {
                $_SESSION['error'] = "La photo est trop lourde. Limite maximale : 5 Mo.";
                header("Location: $redirect_page");
                exit();
            }

            if (!in_array($file_ext, $allowed_extensions) || !in_array($file_mime, $allowed_mime_types)) {
                $_SESSION['error'] = "Format invalide. Uniquement des images .png, .jpg ou .jpeg.";
                header("Location: $redirect_page");
                exit();
            }

            // Dossier de destination
            $upload_dir = '../public/uploads/profils/';
           
            // Créer le dossier s'il n'existe pas encore
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Pour éviter d'accumuler des fichiers inutiles, on supprime les anciens avatars de cet ID
            foreach ($allowed_extensions as $ext) {
                $old_file = $upload_dir . "avatar_" . $user_id . "." . $ext;
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }

            // Nom final unique basé sur la convention : avatar_ID.extension
            $new_file_name = "avatar_" . $user_id . "." . $file_ext;
            $destination = $upload_dir . $new_file_name;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $_SESSION['error'] = "Impossible d'enregistrer l'image sur le serveur.";
                header("Location: $redirect_page");
                exit();
            }
        }

        // 6. Mise à jour des informations en Base de Données
        $sql = "UPDATE users
                SET prenom = ?, nom = ?, email = ?, telephone = ?, email_verifie = ?, telephone_verifie = ?
                WHERE id = ?";
        $stmt_update = $pdo->prepare($sql);
        $stmt_update->execute([
            $prenom,
            $nom,
            $email,
            $telephone,
            $email_verifie,
            $telephone_verifie,
            $user_id
        ]);

        $_SESSION['success'] = "Vos informations personnelles ont été mises à jour avec succès !";
        header("Location: $redirect_page");
        exit();

    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur système lors de la mise à jour : " . $e->getMessage();
        header("Location: $redirect_page");
        exit();
    }

} else {
    // Redirection si tentative d'accès direct au fichier
    header("Location: $redirect_page");
    exit();
}