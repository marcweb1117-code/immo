<?php
/**
* 📝 TRAITEMENT DES CRITÈRES DE RECHERCHE
* Emplacement : src/traitement_criteres.php
* Version corrigée avec tous les champs
*/

session_start();
require_once '../config/db.php';

// 1. Sécurité : Vérifier si l'utilisateur est bien connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// 2. Traitement uniquement si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   
    // Nettoyage et récupération des inputs
    $type_bien = isset($_POST['type_bien']) ? trim($_POST['type_bien']) : 'appartement';
    $budget_max = isset($_POST['budget_max']) ? (int)$_POST['budget_max'] : 0;
    $localisation = isset($_POST['localisation']) ? trim($_POST['localisation']) : '';
    $surface_min = isset($_POST['surface_min']) ? (int)$_POST['surface_min'] : 0;
    $alerte_email = isset($_POST['alerte_email']) ? 1 : 0;
   
    // ✅ NOUVEAU : Récupération des commodités
    $req_parking = isset($_POST['req_parking']) ? 1 : 0;
    $req_balcon = isset($_POST['req_balcon']) ? 1 : 0;
    $req_ascenseur = isset($_POST['req_ascenseur']) ? 1 : 0;
   
    // ✅ NOUVEAU : Récupération des coordonnées GPS
    $latitude_cible = isset($_POST['latitude_cible']) && !empty($_POST['latitude_cible'])
        ? (float)$_POST['latitude_cible']
        : null;
    $longitude_cible = isset($_POST['longitude_cible']) && !empty($_POST['longitude_cible'])
        ? (float)$_POST['longitude_cible']
        : null;

    // Validation rapide des enums pour éviter les injections de valeurs bizarres
    $types_autorises = ['maison', 'appartement', 'terrain', 'local_commercial'];
    if (!in_array($type_bien, $types_autorises)) {
        $_SESSION['error'] = "Le type de bien sélectionné est invalide.";
        header('Location: ../public/profil_acheteur.php');
        exit();
    }

    // Vérification des limites
    if ($budget_max < 0 || $surface_min < 0) {
        $_SESSION['error'] = "Le budget et la surface doivent être des nombres positifs.";
        header('Location: ../public/profil_acheteur.php');
        exit();
    }

    try {
        // ✅ REQUÊTE CORRIGÉE : Tous les champs inclus
        $sql = "INSERT INTO criteres_recherche (
                    user_id,
                    type_bien,
                    budget_max,
                    localisation,
                    surface_min,
                    alerte_email,
                    req_parking,
                    req_balcon,
                    req_ascenseur,
                    latitude_cible,
                    longitude_cible
                ) VALUES (
                    :user_id,
                    :type_bien,
                    :budget_max,
                    :localisation,
                    :surface_min,
                    :alerte_email,
                    :req_parking,
                    :req_balcon,
                    :req_ascenseur,
                    :latitude_cible,
                    :longitude_cible
                ) ON DUPLICATE KEY UPDATE
                    type_bien = VALUES(type_bien),
                    budget_max = VALUES(budget_max),
                    localisation = VALUES(localisation),
                    surface_min = VALUES(surface_min),
                    alerte_email = VALUES(alerte_email),
                    req_parking = VALUES(req_parking),
                    req_balcon = VALUES(req_balcon),
                    req_ascenseur = VALUES(req_ascenseur),
                    latitude_cible = VALUES(latitude_cible),
                    longitude_cible = VALUES(longitude_cible)";
       
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':type_bien' => $type_bien,
            ':budget_max' => $budget_max,
            ':localisation' => $localisation,
            ':surface_min' => $surface_min,
            ':alerte_email' => $alerte_email,
            ':req_parking' => $req_parking,
            ':req_balcon' => $req_balcon,
            ':req_ascenseur' => $req_ascenseur,
            ':latitude_cible' => $latitude_cible,
            ':longitude_cible' => $longitude_cible
        ]);

        $_SESSION['success'] = "🎯 Vos critères de recherche et alertes ont été mis à jour avec succès !";

    } catch (PDOException $e) {
        $_SESSION['error'] = "Une erreur est survenue lors de l'enregistrement : " . $e->getMessage();
    }

    // 4. Redirection vers son profil acheteur dédié
    header('Location: ../public/profil_acheteur.php');
    exit();
} else {
    // Si quelqu'un tente d'accéder au fichier en direct sans POST
    header('Location: ../public/profil_acheteur.php');
    exit();
}