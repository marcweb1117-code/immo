<?php
/**
* 🖼️ MODULE PHASH - DÉTECTION DE DOUBLONS D'IMAGES
* Emplacement : src/phash.php
*/

/**
* Calcule l'empreinte visuelle (Average Hash / pHash) d'une image
*/
function calculerPHashImage($cheminImage) {
    if (!file_exists($cheminImage) || !extension_loaded('gd')) {
        return null;
    }

    $info = getimagesize($cheminImage);
    if (!$info) return null;

    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg':
            $source = @imagecreatefromjpeg($cheminImage);
            break;
        case 'image/png':
            $source = @imagecreatefrompng($cheminImage);
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) {
                $source = @imagecreatefromwebp($cheminImage);
            } else {
                return null;
            }
            break;
        default:
            return null;
    }

    if (!$source) return null;

    // 1. Redimensionner en 8x8 pixels
    $redimensionnee = imagecreatetruecolor(8, 8);
    imagecopyresampled($redimensionnee, $source, 0, 0, 0, 0, 8, 8, imagesx($source), imagesy($source));

    // 2. Convertir en niveaux de gris et calculer la moyenne
    $gris = [];
    $totalLuminance = 0;

    for ($y = 0; $y < 8; $y++) {
        for ($x = 0; $x < 8; $x++) {
            $rgb = imagecolorat($redimensionnee, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $luminance = (int)(($r * 0.299) + ($g * 0.587) + ($b * 0.114));
            $gris[] = $luminance;
            $totalLuminance += $luminance;
        }
    }

    imagedestroy($source);
    imagedestroy($redimensionnee);

    $moyenne = $totalLuminance / 64;

    // 3. Générer le hash binaire sur 64 bits
    $hashBinaire = '';
    foreach ($gris as $valeur) {
        $hashBinaire .= ($valeur >= $moyenne) ? '1' : '0';
    }

    // Convertir la chaîne binaire de 64 bits en 16 caractères hexadécimaux
    $hashHex = '';
    for ($i = 0; $i < 64; $i += 4) {
        $hashHex .= dechex(bindec(substr($hashBinaire, $i, 4)));
    }

    return $hashHex;
}

/**
* Calcule la distance de Hamming entre deux phash hexadécimaux
*/
function calculerDistanceHamming($hash1, $hash2) {
    if (strlen($hash1) !== 16 || strlen($hash2) !== 16) {
        return 64;
    }

    $bin1 = str_pad(base_convert($hash1, 16, 2), 64, '0', STR_PAD_LEFT);
    $bin2 = str_pad(base_convert($hash2, 16, 2), 64, '0', STR_PAD_LEFT);

    $distance = 0;
    for ($i = 0; $i < 64; $i++) {
        if ($bin1[$i] !== $bin2[$i]) {
            $distance++;
        }
    }

    return $distance;
}

/**
* Vérifie si le phash d'une nouvelle photo existe déjà en BDD (Globalement)
* Seuil <= 5 : Images identiques ou quasi-identiques
*/
function verifierDoublonPHash(PDO $db, $nouveauHash, $seuil = 5) {
    if (empty($nouveauHash)) return false;

    $stmt = $db->prepare("SELECT phash FROM photos_annonces WHERE phash IS NOT NULL AND phash != ''");
    $stmt->execute();
    $photosExistantes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($photosExistantes as $phashExistant) {
        $distance = calculerDistanceHamming($nouveauHash, $phashExistant);
        if ($distance <= $seuil) {
            return true;
        }
    }

    return false;
}
