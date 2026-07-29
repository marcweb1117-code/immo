<?php
/**
* 📝 TRAITEMENT DE PUBLICATION D'ANNONCE AVEC SÉCURITÉ ANTI-DOUBLON
* Version : Méthode combinée (MD5 + Taille + Dimensions)
* Emplacement : src/traitement_annonce.php
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../config/db.php';
require __DIR__ . '/recommendation.php';
require __DIR__ . '/hash_images.php';

$db = $GLOBALS['pdo'] ?? $pdo ?? $db ?? null;

if (!($db instanceof PDO)) {
    die("Erreur de connexion à la base de données.");
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['erreur'] = "Vous devez être connecté pour publier.";
    header('Location: ../public/envoyer_annonce.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- RÉCUPÉRATION DES DONNÉES ---
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

    $has_parking = isset($_POST['has_parking']) ? 1 : 0;
    $has_balcon = isset($_POST['has_balcon']) ? 1 : 0;
    $has_ascenseur = isset($_POST['has_ascenseur']) ? 1 : 0;

    // --- VALIDATION DES CHAMPS OBLIGATOIRES ---
    if (!$titre || !$prix || !$surface || !$ville) {
        $_SESSION['erreur'] = "Veuillez remplir tous les champs obligatoires.";
        header('Location: ../public/envoyer_annonce.php');
        exit();
    }

    try {
        // --- 🔍 VÉRIFICATION ANTI-DOUBLON DES PHOTOS ---
        $photosAEnregistrer = [];
        $erreurDoublon = false;

        if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
            $totalPhotos = count($_FILES['photos']['name']);
          
            error_log("=== TRAITEMENT DE " . $totalPhotos . " PHOTO(S) ===");

            for ($i = 0; $i < $totalPhotos; $i++) {
                error_log("--- Photo #" . ($i+1) . " : " . $_FILES['photos']['name'][$i] . " ---");
              
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpPath = $_FILES['photos']['tmp_name'][$i];
                  
                    // 1. Calcul des hashs et caractéristiques
                    $hashs = calculerHashsImage($tmpPath);
                  
                    if (!$hashs) {
                        $_SESSION['erreur'] = "Impossible de lire l'image \"" . htmlspecialchars($_FILES['photos']['name'][$i]) . "\". Vérifie que c'est une image valide.";
                        $erreurDoublon = true;
                        break;
                    }
                  
                    error_log("Hash MD5 : " . $hashs['hash_md5']);
                    error_log("Taille : " . $hashs['taille'] . " octets");
                    error_log("Dimensions : " . $hashs['largeur'] . "x" . $hashs['hauteur']);
                  
                    // 2. Vérification des doublons
                    $verification = verifierDoublonImage(
                        $db,
                        $hashs['hash_md5'],
                        $hashs['taille'],
                        $hashs['largeur'],
                        $hashs['hauteur']
                    );
                  
                    if ($verification['doublon']) {
                        $_SESSION['erreur'] = "Impossible de publier l'annonce : l'image \"" . htmlspecialchars($_FILES['photos']['name'][$i]) . "\" est un doublon !";
                        $_SESSION['erreur'] .= "📌 " . $verification['message'];
                        if ($verification['details']) {
                            $_SESSION['erreur'] .= "🔗 Image existante : " . htmlspecialchars($verification['details']['nom_original'] ?? 'Inconnu');
                        }
                        $erreurDoublon = true;
                        error_log("🚨 DOUBLON DÉTECTÉ - " . $verification['niveau']);
                        break;
                    }
                  
                    error_log("✅ Pas de doublon pour cette image");
                  
                    $photosAEnregistrer[] = [
                        'tmp_name' => $tmpPath,
                        'name' => $_FILES['photos']['name'][$i],
                        'hashs' => $hashs
                    ];
                  
                } else {
                    // Gestion des erreurs d'upload
                    $uploadErrors = [
                        UPLOAD_ERR_INI_SIZE => 'Fichier trop volumineux (limite serveur)',
                        UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux (limite formulaire)',
                        UPLOAD_ERR_PARTIAL => 'Fichier partiellement téléchargé',
                        UPLOAD_ERR_NO_FILE => 'Aucun fichier sélectionné',
                        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
                        UPLOAD_ERR_CANT_WRITE => 'Erreur d\'écriture sur le disque',
                        UPLOAD_ERR_EXTENSION => 'Extension PHP bloquée'
                    ];
                    $code = $_FILES['photos']['error'][$i];
                    $message = $uploadErrors[$code] ?? 'Erreur inconnue';
                    error_log("❌ Erreur d'upload : " . $message);
                    $_SESSION['erreur'] = "Erreur d'upload pour \"" . htmlspecialchars($_FILES['photos']['name'][$i]) . "\" : $message";
                    $erreurDoublon = true;
                    break;
                }
            }
        }

        if ($erreurDoublon) {
            header('Location: ../public/envoyer_annonce.php');
            exit();
        }

        // --- DÉBUT DE LA TRANSACTION ---
        $db->beginTransaction();

        // 🔥 AJOUT : Date d'expiration (AVANT la requête)
        $dateExpiration = date('Y-m-d H:i:s', strtotime('+21 days'));

        // --- INSERTION DE L'ANNONCE ---
        $stmt = $db->prepare("
            INSERT INTO annonces (
                user_id, titre, description, prix, surface, pieces, type_bien, type_transaction,
                adresse, code_postal, ville, latitude, longitude,
                has_parking, has_balcon, has_ascenseur, statut, date_creation,
                date_expiration, renouvellements
            ) VALUES (
                :user_id, :titre, :description, :prix, :surface, :pieces, :type_bien, :type_transaction,
                :adresse, :code_postal, :ville, :latitude, :longitude,
                :has_parking, :has_balcon, :has_ascenseur, 'disponible', NOW(),
                :date_expiration, 0
            )
        ");

        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
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
            ':date_expiration' => $dateExpiration  // 🔥 NOUVEAU
        ]);

        $annonceId = $db->lastInsertId();
        error_log("✅ Annonce créée avec ID : " . $annonceId . " - Expire le : " . $dateExpiration);

        // --- SAUVEGARDE DES PHOTOS ---
        $uploadDir = __DIR__ . '/../public/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
            error_log("📁 Dossier uploads créé");
        }

        foreach ($photosAEnregistrer as $index => $photo) {
            $extension = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
            $newFileName = uniqid('img_', true) . '.' . $extension;
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($photo['tmp_name'], $destination)) {
                $photoUrl = 'uploads/' . $newFileName;
                $isPrincipale = ($index === 0) ? 1 : 0;
              
                // ✅ INSERT avec chemin_fichier au lieu de url
                $stmtPhoto = $db->prepare("
                    INSERT INTO photos_annonces (
                        annonce_id, chemin_fichier, est_principale,
                        hash_md5, hash_sha1, taille_fichier, largeur, hauteur, nom_original
                    ) VALUES (
                        :annonce_id, :chemin_fichier, :est_principale,
                        :hash_md5, :hash_sha1, :taille, :largeur, :hauteur, :nom_original
                    )
                ");
                $stmtPhoto->execute([
                    ':annonce_id' => $annonceId,
                    ':chemin_fichier' => $photoUrl,
                    ':est_principale' => $isPrincipale,
                    ':hash_md5' => $photo['hashs']['hash_md5'],
                    ':hash_sha1' => $photo['hashs']['hash_sha1'],
                    ':taille' => $photo['hashs']['taille'],
                    ':largeur' => $photo['hashs']['largeur'],
                    ':hauteur' => $photo['hashs']['hauteur'],
                    ':nom_original' => $photo['hashs']['nom_original']
                ]);
              
                error_log("✅ Photo enregistrée : " . $newFileName . " - Principale : " . ($isPrincipale ? 'Oui' : 'Non'));
              
            } else {
                throw new Exception("Impossible de déplacer le fichier " . $photo['name']);
            }
        }

        // --- VALIDATION DE LA TRANSACTION ---
        $db->commit();
        error_log("✅ Transaction validée");

        // --- DÉCLENCHEMENT DU SYSTÈME D'ALERTE INTELLIGENTE ---
        try {
            $stmtAcheteurs = $db->prepare("SELECT id, email, prenom FROM users WHERE type_compte = 'acheteur'");
            $stmtAcheteurs->execute();
            $acheteurs = $stmtAcheteurs->fetchAll(PDO::FETCH_ASSOC);

            $acheteursANotifier = [];
            foreach ($acheteurs as $acheteur) {
                $smgResult = calculerSMGAnnonceAcheteur($db, $annonceId, $acheteur['id']);
                if ($smgResult && $smgResult['smg_total'] >= 90) {
                    $acheteursANotifier[] = [
                        'acheteur_id' => $acheteur['id'],
                        'prenom' => $acheteur['prenom'],
                        'smg' => $smgResult['smg_total']
                    ];
                }
            }
          
            $nbNotifies = count($acheteursANotifier);
            $_SESSION['succes'] = "Annonce publiée avec succès ! " . $nbNotifies . " acheteur(s) qualifié(s) ont été notifiés.";
            error_log("📧 " . $nbNotifies . " acheteurs notifiés");
          
        } catch (Exception $e) {
            error_log("⚠️ Erreur système d'alerte : " . $e->getMessage());
            $_SESSION['succes'] = "Annonce publiée avec succès (système d'alerte temporairement indisponible).";
        }

        header('Location: ../public/catalogue.php');
        exit();

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
            error_log("❌ Rollback effectué");
        }
        $_SESSION['erreur'] = "Erreur lors de la publication : " . $e->getMessage();
        error_log("❌ Erreur : " . $e->getMessage());
        header('Location: ../public/envoyer_annonce.php');
        exit();
    }
}