
<?php
// src/traitement_inscription.php

// 1. Initialisation de la session et inclusion de la base de données
session_start();
require_once __DIR__ . '/../config/db.php';

// On force le script à ne traiter que les requêtes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/inscription.php');
    exit();
}

// 2. Vérification de la sécurité CSRF
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
}

// 3. Récupération et Nettoyage initial (Sanitization) des données reçues
$prenom        = isset($_POST['prenom']) ? trim(htmlspecialchars($_POST['prenom'])) : '';
$nom           = isset($_POST['nom']) ? trim(htmlspecialchars($_POST['nom'])) : '';
$email         = isset($_POST['email']) ? trim($_POST['email']) : '';
$telephone     = isset($_POST['telephone']) ? trim(htmlspecialchars($_POST['telephone'])) : '';
$type_compte   = isset($_POST['type_compte']) ? trim($_POST['type_compte']) : '';
$mot_de_passe  = isset($_POST['mot_de_passe']) ? $_POST['mot_de_passe'] : '';
$nom_agence    = isset($_POST['nom_agence']) ? trim(htmlspecialchars($_POST['nom_agence'])) : null;
$siren         = isset($_POST['siren']) ? trim(htmlspecialchars($_POST['siren'])) : null;
$accepte_cgu   = isset($_POST['accepte_cgu']) ? true : false;
$accepte_rgpd  = isset($_POST['accepte_rgpd']) ? true : false;

// Tableau pour stocker les erreurs potentielles
$erreurs = [];

// 4. Validations strictes côté Serveur
if (empty($prenom) || empty($nom)) {
    $erreurs[] = "Le nom et le prénom sont obligatoires.";
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erreurs[] = "L'adresse email n'est pas valide.";
}

// Validation basique du téléphone (ex: format français ou international générique)
if (empty($telephone) || !preg_match('/^[0-9\+\s]{9,20}$/', $telephone)) {
    $erreurs[] = "Le numéro de téléphone n'est pas valide.";
}

// Validation de l'ENUM du type de compte
$types_autorises = ['acheteur', 'particulier_vendeur', 'professionnel'];
if (!in_array($type_compte, $types_autorises)) {
    $erreurs[] = "Le type de compte sélectionné est invalide.";
}

// Validation conditionnelle pour les professionnels
if ($type_compte === 'professionnel') {
    if (empty($nom_agence)) {
        $erreurs[] = "Le nom de l'agence est obligatoire pour les professionnels.";
}
    if (empty($siren) || !preg_match('/^[0-9]{9}$/', $siren)) {
        $erreurs[] = "Le numéro SIREN doit être composé exactement de 9 chiffres.";
    }
} else {
    // Si ce n'est pas un pro, on force ces valeurs à NULL pour la BDD
    $nom_agence = null;
    $siren = null;
}

// Force du mot de passe (Minimum 12 caractères, 1 majuscule, 1 chiffre, 1 caractère spécial)
if (strlen($mot_de_passe) < 12 ||
    !preg_match('/[A-Z]/', $mot_de_passe) ||
    !preg_match('/[0-9]/', $mot_de_passe) ||
    !preg_match('/[^A-Za-z0-9]/', $mot_de_passe)) {
    $erreurs[] = "Le mot de passe ne respecte pas les critères de sécurité exigés.";
}

// Vérification des consentements juridiques
if (!$accepte_cgu || !$accepte_rgpd) {
    $erreurs[] = "Vous devez accepter les CGU et la politique RGPD pour vous inscrire.";
}

// S'il y a déjà des erreurs à ce stade, on stoppe et on l'indique
if (!empty($erreurs)) {
    $_SESSION['erreurs_inscription'] = $erreurs;
    header('Location: ../public/inscription.php');
    exit();
}
// Vérification si l'email existe déjà dans la base de données
$stmtCheckEmail = $pdo->prepare("SELECT id FROM users WHERE email = :email");
$stmtCheckEmail->execute(['email' => $email]);

if ($stmtCheckEmail->fetch()) {
    // Si on trouve un résultat, l'email est déjà pris !
    $erreurs[] = "Cette adresse email est déjà associée à un compte.";
}

// Ensuite, le script continue sur ta vérification habituelle :
if (!empty($erreurs)) {
    $_SESSION['erreurs_inscription'] = $erreurs;
    header('Location: ../public/inscription.php');
    exit();
}

try {
    // 5. Vérification de l'unicité de l'email via une requête préparée
    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmtCheck->execute(['email' => $email]);
    if ($stmtCheck->fetch()) {
        $_SESSION['erreurs_inscription'] = ["Cette adresse email est déjà associée à un compte."];
        header('Location: ../public/inscription.php');
        exit();
    }

    // 6. Hachage hautement sécurisé du mot de passe (Argon2id ou Bcrypt par défaut si non dispo)
    $algoHachage = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    $mot_de_passe_hache = password_hash($mot_de_passe, $algoHachage);

    // 7. Capture de l'adresse IP de l'utilisateur
    $ip_inscription = $_SERVER['REMOTE_ADDR'] ?? null;

    // 8. Insertion du nouvel utilisateur en Base de Données
    $sql = "INSERT INTO users (
                prenom, nom, email, telephone, mot_de_passe, type_compte,
                nom_agence, siren, accepte_cgu, accepte_rgpd, ip_inscription
            ) VALUES (
                :prenom, :nom, :email, :telephone, :mot_de_passe, :type_compte,
                :nom_agence, :siren, :accepte_cgu, :accepte_rgpd, :ip_inscription
            )";

    $stmtInsert = $pdo->prepare($sql);
    $stmtInsert->execute([
        'prenom'          => $prenom,
        'nom'             => $nom,
        'email'           => $email,
        'telephone'       => $telephone,
        'mot_de_passe'    => $mot_de_passe_hache,
        'type_compte'     => $type_compte,
        'nom_agence'      => $nom_agence,
        'siren'           => $siren,
        'accepte_cgu'     => (int)$accepte_cgu,
        'accepte_rgpd'    => (int)$accepte_rgpd,
        'ip_inscription'  => $ip_inscription
    ]);

    // L'inscription a réussi !
    // On nettoie le token CSRF pour la suite
    unset($_SESSION['csrf_token']);

    $_SESSION['succes_inscription'] = "Votre compte a bien été créé ! Vous pouvez maintenant vous connecter.";
    header('Location: ../public/connexion.php'); // Redirection vers notre future page de connexion
    exit();

} catch (\PDOException $e) {
    // Log interne et message d'erreur utilisateur propre
    error_log("Erreur lors de l'inscription : " . $e->getMessage());
    $_SESSION['erreurs_inscription'] = ["Une erreur technique est survenue. Veuillez réessayer plus tard."];
    header('Location: ../public/inscription.php');
    exit();
}