<?php
/**
* 🖼️ MODULE DE DÉTECTION DE DOUBLONS - VERSION SIMPLIFIÉE (MD5 + Taille + Dimensions)
* Emplacement : src/hash_images.php
* Version : 1.0
* Auteur : Système Anti-Doublon
*/

/**
* Calcule tous les hashs et caractéristiques d'une image
*
* @param string $cheminImage Chemin absolu vers l'image
* @return array|null Retourne un tableau avec les infos ou null en cas d'erreur
*/
function calculerHashsImage($cheminImage) {
    // Vérification que le fichier existe
    if (!file_exists($cheminImage)) {
        error_log("❌ calculerHashsImage() - Fichier inexistant : " . $cheminImage);
        return null;
    }
   
    // Vérification que le fichier est lisible
    if (!is_readable($cheminImage)) {
        error_log("❌ calculerHashsImage() - Fichier non lisible : " . $cheminImage);
        return null;
    }
   
    // 1. Hash MD5 du fichier (le plus fiable pour des fichiers identiques)
    $hashMd5 = md5_file($cheminImage);
   
    // 2. Hash SHA1 du fichier (pour redondance)
    $hashSha1 = sha1_file($cheminImage);
   
    // 3. Taille du fichier en octets
    $taille = filesize($cheminImage);
   
    // 4. Dimensions de l'image
    $info = getimagesize($cheminImage);
    $largeur = 0;
    $hauteur = 0;
   
    if ($info !== false) {
        $largeur = $info[0];
        $hauteur = $info[1];
    } else {
        error_log("⚠️ calculerHashsImage() - Impossible de lire les dimensions : " . $cheminImage);
    }
   
    // 5. Nom original du fichier
    $nomOriginal = basename($cheminImage);
   
    // 6. Extension du fichier
    $extension = strtolower(pathinfo($cheminImage, PATHINFO_EXTENSION));
   
    $resultat = [
        'hash_md5' => $hashMd5,
        'hash_sha1' => $hashSha1,
        'taille' => $taille,
        'largeur' => $largeur,
        'hauteur' => $hauteur,
        'nom_original' => $nomOriginal,
        'extension' => $extension,
        'taille_ko' => round($taille / 1024, 2), // Taille en Ko (pour info)
    ];
   
    error_log("✅ calculerHashsImage() - Hash MD5 : " . $hashMd5 . " - " . $taille . " octets - " . $largeur . "x" . $hauteur);
   
    return $resultat;
}

/**
* Vérifie si une image est un doublon en plusieurs niveaux
*
* @param PDO $db Connexion à la base de données
* @param string $hashMd5 Hash MD5 du fichier
* @param int $taille Taille du fichier en octets
* @param int $largeur Largeur de l'image
* @param int $hauteur Hauteur de l'image
* @param int $seuilTaille Pourcentage de tolérance pour la taille (ex: 5 = 5%)
* @return array Résultat de la vérification
*/
function verifierDoublonImage(PDO $db, $hashMd5, $taille, $largeur, $hauteur, $seuilTaille = 5) {
    error_log("=== verifierDoublonImage() appelée ===");
    error_log("Hash MD5 : " . $hashMd5);
    error_log("Taille : " . $taille . " octets");
    error_log("Dimensions : " . $largeur . "x" . $hauteur);
   
    // === NIVEAU 1 : Vérification par Hash MD5 exact ===
    // C'est le plus fiable : si le MD5 est identique, c'est EXACTEMENT le même fichier
    $stmt = $db->prepare("SELECT id, annonce_id, nom_original FROM photos_annonces WHERE hash_md5 = :hash_md5 LIMIT 1");
    $stmt->execute([':hash_md5' => $hashMd5]);
    $existant = $stmt->fetch(PDO::FETCH_ASSOC);
   
    if ($existant) {
        error_log("🚨 DOUBLON NIVEAU 1 - Hash MD5 identique !");
        return [
            'doublon' => true,
            'niveau' => 'Hash MD5',
            'message' => 'Ce fichier est strictement identique à une image déjà présente (ID: ' . $existant['id'] . ')',
            'details' => $existant
        ];
    }
   
    // === NIVEAU 2 : Vérification par Taille + Dimensions exactes ===
    // Une image avec exactement la même taille et les mêmes dimensions a 99% de chances d'être la même
    $stmt = $db->prepare("
        SELECT id, annonce_id, nom_original, taille_fichier, largeur, hauteur
        FROM photos_annonces
        WHERE taille_fichier = :taille
        AND largeur = :largeur
        AND hauteur = :hauteur
        LIMIT 1
    ");
    $stmt->execute([
        ':taille' => $taille,
        ':largeur' => $largeur,
        ':hauteur' => $hauteur
    ]);
    $existant = $stmt->fetch(PDO::FETCH_ASSOC);
   
    if ($existant) {
        error_log("🚨 DOUBLON NIVEAU 2 - Taille + Dimensions identiques !");
        return [
            'doublon' => true,
            'niveau' => 'Taille + Dimensions',
            'message' => 'Une image avec exactement les mêmes caractéristiques existe déjà (ID: ' . $existant['id'] . ')',
            'details' => $existant
        ];
    }
   
    // === NIVEAU 3 : Vérification par Taille approximative + Dimensions ===
    // Permet de détecter les images légèrement modifiées (compression, métadonnées)
    if ($largeur > 0 && $hauteur > 0) {
        // Calcul de la marge de tolérance
        $marge = ($taille * $seuilTaille) / 100;
        $tailleMin = $taille - $marge;
        $tailleMax = $taille + $marge;
       
        $stmt = $db->prepare("
            SELECT id, annonce_id, nom_original, taille_fichier, largeur, hauteur
            FROM photos_annonces
            WHERE largeur = :largeur
            AND hauteur = :hauteur
            AND taille_fichier BETWEEN :taille_min AND :taille_max
            LIMIT 1
        ");
        $stmt->execute([
            ':largeur' => $largeur,
            ':hauteur' => $hauteur,
            ':taille_min' => $tailleMin,
            ':taille_max' => $tailleMax
        ]);
        $existant = $stmt->fetch(PDO::FETCH_ASSOC);
       
        if ($existant) {
            $pourcentage = round(($existant['taille_fichier'] / $taille) * 100, 2);
            error_log("🚨 DOUBLON NIVEAU 3 - Dimensions identiques, taille proche (" . $pourcentage . "%) !");
            return [
                'doublon' => true,
                'niveau' => 'Dimensions + Taille proche',
                'message' => 'Une image avec les mêmes dimensions et une taille similaire (' . $pourcentage . '% de correspondance) existe déjà',
                'details' => $existant
            ];
        }
    }
   
    // === PAS DE DOUBLON ===
    error_log("✅ Aucun doublon détecté - Image unique");
    return [
        'doublon' => false,
        'niveau' => 'Aucun',
        'message' => 'Image unique - Aucun doublon détecté',
        'details' => null
    ];
}

/**
* Récupère toutes les photos d'une annonce avec leurs hashs
*
* @param PDO $db Connexion à la base de données
* @param int $annonceId ID de l'annonce
* @return array Liste des photos
*/
function getPhotosAnnonce($db, $annonceId) {
    $stmt = $db->prepare("
        SELECT id, url, hash_md5, hash_sha1, taille_fichier, largeur, hauteur, nom_original, est_principale
        FROM photos_annonces
        WHERE annonce_id = :annonce_id
        ORDER BY est_principale DESC, id ASC
    ");
    $stmt->execute([':annonce_id' => $annonceId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
* Vérifie si une annonce a une photo principale
*
* @param PDO $db Connexion à la base de données
* @param int $annonceId ID de l'annonce
* @return bool
*/
function hasPhotoPrincipale($db, $annonceId) {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM photos_annonces
        WHERE annonce_id = :annonce_id AND est_principale = 1
    ");
    $stmt->execute([':annonce_id' => $annonceId]);
    return $stmt->fetchColumn() > 0;
}