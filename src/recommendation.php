<?php
/**
* 🧠 MOTEUR DE RECOMMANDATION - VERSION SÉCURISÉE & DEBUG
* Emplacement : src/recommendation.php
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function calculerDistanceHaversine($lat1, $lon1, $lat2, $lon2) {
    if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
        return null;
    }
    $earth_radius = 6371;
    $dLat = deg2rad((float)$lat2 - (float)$lat1);
    $dLon = deg2rad((float)$lon2 - (float)$lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad((float)$lat1)) * cos(deg2rad((float)$lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth_radius * $c;
}

function calculerScoreSpatial($distance) {
    if ($distance === null) return 50;
    if ($distance < 1) return 100;
    if ($distance <= 5) return 80;
    if ($distance <= 15) return 60;
    if ($distance <= 30) return 35;
    return 10;
}

function calculerSMGAnnonceAcheteur(PDO $db, $annonceId, $acheteurId) {
    // 1. Récupération Annonce
    $stmtA = $db->prepare("SELECT * FROM annonces WHERE id = :id");
    $stmtA->execute([':id' => $annonceId]);
    $annonce = $stmtA->fetch(PDO::FETCH_ASSOC);

    if (!$annonce) {
        return ["erreur" => "Annonce introuvable (ID: $annonceId)"];
    }

    // 2. Récupération Critères Acheteur
    $stmtC = $db->prepare("SELECT * FROM criteres_recherche WHERE user_id = :uid");
    $stmtC->execute([':uid' => $acheteurId]);
    $criteres = $stmtC->fetch(PDO::FETCH_ASSOC);

    // 3. Récupération Historique
    $stmtH = $db->prepare("
        SELECT a.type_bien, COUNT(*) as nb_interactions
        FROM historique_actions_acheteurs h
        JOIN annonces a ON h.annonce_id = a.id
        WHERE h.user_id = :uid
        GROUP BY a.type_bien
        ORDER BY nb_interactions DESC
    ");
    $stmtH->execute([':uid' => $acheteurId]);
    $historique = $stmtH->fetchAll(PDO::FETCH_ASSOC);

    $coldStart = empty($criteres) && empty($historique);

    // Valeurs par défaut sécurisées si pas de critères
    $budgetMax = (float)($criteres['budget_max'] ?? $annonce['prix']);
    $surfaceMin = (float)($criteres['surface_min'] ?? $annonce['surface']);
    $typeBienCible = $criteres['type_bien'] ?? $annonce['type_bien'];
    $latCible = $criteres['latitude_cible'] ?? null;
    $lonCible = $criteres['longitude_cible'] ?? null;

    // --- PILIER 1 : FILTRE ACTIF (40%) ---
    // A. Prix
    $scorePrix = 100;
    $prixAnnonce = (float)$annonce['prix'];
    if ($budgetMax > 0 && $prixAnnonce > $budgetMax) {
        $ratioPrix = ($prixAnnonce - $budgetMax) / $budgetMax;
        if ($ratioPrix <= 0.05) {
            $scorePrix = 100 - ($ratioPrix / 0.05) * 15;
        } elseif ($ratioPrix <= 0.15) {
            $scorePrix = 84 - (($ratioPrix - 0.05) / 0.10) * 44;
        } else {
            $scorePrix = max(0, 39 - ($ratioPrix - 0.15) * 100);
        }
    }

    // B. Surface
    $scoreSurface = 100;
    $surfaceAnnonce = (float)$annonce['surface'];
    if ($surfaceMin > 0 && $surfaceAnnonce < $surfaceMin) {
        $deficitSurface = ($surfaceMin - $surfaceAnnonce) / $surfaceMin;
        if ($deficitSurface <= 0.05) {
            $scoreSurface = 100 - ($deficitSurface / 0.05) * 15;
        } elseif ($deficitSurface <= 0.15) {
            $scoreSurface = 84 - (($deficitSurface - 0.05) / 0.10) * 44;
        } else {
            $scoreSurface = max(0, 39 - ($deficitSurface - 0.15) * 100);
        }
    }

    // C. Spatial
    $dist = calculerDistanceHaversine(
        $annonce['latitude'] ?? null, $annonce['longitude'] ?? null,
        $latCible, $lonCible
    );
    $scoreSpatial = calculerScoreSpatial($dist);

    $pilier1 = ($scorePrix * 0.4) + ($scoreSurface * 0.3) + ($scoreSpatial * 0.3);

    // --- PILIER 2 : PROFIL (30%) ---
    $scoreProfil = 0;
    if ($annonce['type_bien'] === $typeBienCible) {
        $scoreProfil += 15;
    }
   
    $reqParking = !empty($criteres['req_parking']);
    $reqBalcon = !empty($criteres['req_balcon']);
    $reqAscens = !empty($criteres['req_ascenseur']);

    $hasParking = !empty($annonce['has_parking']);
    $hasBalcon = !empty($annonce['has_balcon']);
    $hasAscens = !empty($annonce['has_ascenseur']);

    if ($reqParking && $hasParking) $scoreProfil += 5;
    if ($reqBalcon && $hasBalcon) $scoreProfil += 5;
    if ($reqAscens && $hasAscens) $scoreProfil += 5;

    if ($scoreSpatial >= 60) $scoreProfil += 15;

    $pilier2 = min(100, ($scoreProfil / 45) * 100);

    // --- PILIER 3 : COMPORTEMENT (30%) ---
    $pilier3 = 50;
    $correctionTrajectoire = false;
    $noteCorrection = null;

    if (!empty($historique)) {
        $typePlusConsulte = $historique[0]['type_bien'];
        $totalInteractions = array_sum(array_column($historique, 'nb_interactions'));
        $ratioDominant = $totalInteractions > 0 ? ($historique[0]['nb_interactions'] / $totalInteractions) : 0;

        if ($typePlusConsulte !== $typeBienCible && $ratioDominant >= 0.6) {
            $correctionTrajectoire = true;
            $noteCorrection = "Correction appliquée : recherche active axée sur les " . $typePlusConsulte . "s";
            $pilier3 = ($annonce['type_bien'] === $typePlusConsulte) ? 95 : 25;
        } else {
            if ($annonce['type_bien'] === $typePlusConsulte) {
                $pilier3 = 85;
            }
        }
    }

    // --- SCORE FINAL ---
    if ($coldStart) {
        $smgTotal = ($pilier1 * 0.3) + ($pilier2 * 0.7);
    } else {
        $smgTotal = ($pilier1 * 0.40) + ($pilier2 * 0.30) + ($pilier3 * 0.30);
    }

    return [
        "annonce_id" => (string)$annonceId,
        "acheteur_id" => (string)$acheteurId,
        "smg_total" => round(min(100, max(0, $smgTotal)), 2),
        "detail" => [
            "pilier_1_filtre_actif" => round($pilier1, 2),
            "pilier_2_profil" => round($pilier2, 2),
            "pilier_3_comportemental" => round($pilier3, 2),
            "score_spatial" => round($scoreSpatial, 2),
            "correction_trajectoire_appliquee" => $correctionTrajectoire,
            "note_correction" => $noteCorrection
        ],
        "cold_start" => $coldStart,
        "alerte_declenchee" => ($smgTotal >= 90)
    ];
}