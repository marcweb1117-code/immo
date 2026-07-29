<?php
/**
* 🐛 DIAGNOSTIC COMPLET DU SMG
* Emplacement : public/debug_smg.php
* À SUPPRIMER APRÈS RÉSOLUTION
*/

session_start();
require_once '../config/db.php';

$db = $GLOBALS['pdo'] ?? $pdo ?? $db ?? null;

if (!($db instanceof PDO)) {
    die("❌ Erreur de connexion à la BDD");
}

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'] ?? null;

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <title>🔍 Diagnostic SMG</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; padding: 30px; background: #1a1a2e; color: #eee; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: #16213e; border-radius: 12px; padding: 25px; margin-bottom: 25px; border-left: 5px solid #e94560; }
        .card.green { border-left-color: #10b981; }
        .card.orange { border-left-color: #f59e0b; }
        .card.blue { border-left-color: #3b82f6; }
        h1 { color: #e94560; border-bottom: 2px solid #e94560; padding-bottom: 15px; }
        h2 { color: #3b82f6; margin-top: 0; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #2a2a4a; }
        th { background: #1a1a3e; color: #3b82f6; }
        code { background: #0f0f2a; padding: 2px 8px; border-radius: 4px; color: #f59e0b; }
        .highlight { background: #2a2a4a; padding: 15px; border-radius: 8px; }
        .btn { display: inline-block; padding: 10px 20px; background: #e94560; color: white; text-decoration: none; border-radius: 6px; margin-top: 10px; }
        .btn:hover { background: #c73e54; }
        pre { background: #0f0f2a; padding: 15px; border-radius: 8px; overflow-x: auto; white-space: pre-wrap; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .badge-ok { background: #10b981; color: white; }
        .badge-ko { background: #ef4444; color: white; }
        .badge-warning { background: #f59e0b; color: #1a1a2e; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 DIAGNOSTIC COMPLET DU SMG</h1>";

// ============================================
// 1. INFOS UTILISATEUR
// ============================================
echo "<div class='card blue'>
<h2>👤 1. UTILISATEUR CONNECTÉ</h2>";

if (!$user_id) {
    echo "<p class='error'>❌ AUCUN UTILISATEUR CONNECTÉ !</p>";
    echo "<p>→ Connecte-toi d'abord.</p>";
} else {
    $stmt = $db->prepare("SELECT id, email, prenom, nom, type_compte FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
   
    if ($user) {
        echo "<table>";
        echo "<tr><th>Champ</th><th>Valeur</th><th>Statut</th></tr>";
        echo "<tr><td>ID</td><td><code>" . $user['id'] . "</code></td><td>✅</td></tr>";
        echo "<tr><td>Email</td><td><code>" . htmlspecialchars($user['email']) . "</code></td><td>✅</td></tr>";
        echo "<tr><td>Nom complet</td><td><code>" . htmlspecialchars($user['prenom'] . ' ' . $user['nom']) . "</code></td><td>✅</td></tr>";
       
        $typeOk = $user['type_compte'] === 'acheteur';
        echo "<tr><td>Type de compte</td><td><code>" . $user['type_compte'] . "</code></td>";
        echo "<td>" . ($typeOk ? "<span class='success'>✅ ACHETEUR - OK</span>" : "<span class='error'>❌ " . strtoupper($user['type_compte']) . " - PAS ACHETEUR !</span>") . "</td></tr>";
        echo "</table>";
       
        if (!$typeOk) {
            echo "<p class='error' style='font-size:16px;'>⚠️ Le SMG est CALCULÉ UNIQUEMENT pour les comptes de type 'acheteur'.</p>";
            echo "<p>→ Exécute cette requête : <code>UPDATE users SET type_compte = 'acheteur' WHERE id = " . $user['id'] . ";</code></p>";
        }
    } else {
        echo "<p class='error'>❌ Utilisateur introuvable en BDD.</p>";
    }
}
echo "</div>";

// ============================================
// 2. STRUCTURE DE LA TABLE criteres_recherche
// ============================================
echo "<div class='card blue'>
<h2>📊 2. STRUCTURE DE LA TABLE <code>criteres_recherche</code></h2>";

$stmt = $db->query("SHOW COLUMNS FROM criteres_recherche");
$colonnes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$colonnesAttendues = ['user_id', 'type_bien', 'budget_max', 'localisation', 'surface_min', 'alerte_email', 'req_parking', 'req_balcon', 'req_ascenseur', 'latitude_cible', 'longitude_cible'];
$colonnesExistantes = array_column($colonnes, 'Field');

echo "<table>";
echo "<tr><th>Colonne</th><th>Présente ?</th><th>Type</th></tr>";
foreach ($colonnesAttendues as $col) {
    $existe = in_array($col, $colonnesExistantes);
    if ($existe) {
        $info = array_filter($colonnes, function($c) use ($col) { return $c['Field'] === $col; });
        $type = reset($info)['Type'] ?? '?';
        echo "<tr><td><code>$col</code></td><td><span class='success'>✅ Oui</span></td><td><code>$type</code></td></tr>";
    } else {
        echo "<tr><td><code>$col</code></td><td><span class='error'>❌ NON</span></td><td>-</td></tr>";
    }
}
echo "</table>";

$manquantes = array_diff($colonnesAttendues, $colonnesExistantes);
if (count($manquantes) > 0) {
    echo "<p class='error' style='font-size:16px;'>⚠️ Des colonnes sont MANQUANTES !</p>";
    echo "<p>→ Exécute ce script SQL :</p>";
    echo "<pre>
ALTER TABLE criteres_recherche
ADD COLUMN req_parking BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN req_balcon BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN req_ascenseur BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN latitude_cible DECIMAL(10, 8) DEFAULT NULL,
ADD COLUMN longitude_cible DECIMAL(11, 8) DEFAULT NULL;
</pre>";
}
echo "</div>";

// ============================================
// 3. CRITÈRES DE L'UTILISATEUR
// ============================================
if ($user_id) {
    echo "<div class='card green'>
    <h2>🎯 3. CRITÈRES DE L'ACHETEUR (ID: $user_id)</h2>";
   
    $stmt = $db->prepare("SELECT * FROM criteres_recherche WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $criteres = $stmt->fetch(PDO::FETCH_ASSOC);
   
    if (!$criteres) {
        echo "<p class='error'>❌ AUCUN CRITÈRE ENREGISTRÉ !</p>";
        echo "<p>→ Va sur <code>profil_acheteur.php</code> et enregistre tes critères.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>Champ</th><th>Valeur</th><th>Statut</th></tr>";
       
        // Type bien
        $typeBien = $criteres['type_bien'] ?? 'N/A';
        echo "<tr><td>Type de bien</td><td><code>" . htmlspecialchars($typeBien) . "</code></td>";
        echo "<td>" . (!empty($typeBien) ? "<span class='success'>✅</span>" : "<span class='warning'>⚠️</span>") . "</td></tr>";
       
        // Budget
        $budget = $criteres['budget_max'] ?? 0;
        echo "<tr><td>Budget max</td><td><code>" . number_format($budget, 0, ',', ' ') . " €</code></td>";
        echo "<td>" . ($budget > 0 ? "<span class='success'>✅</span>" : "<span class='warning'>⚠️</span>") . "</td></tr>";
       
        // Surface
        $surface = $criteres['surface_min'] ?? 0;
        echo "<tr><td>Surface min</td><td><code>" . $surface . " m²</code></td>";
        echo "<td>" . ($surface > 0 ? "<span class='success'>✅</span>" : "<span class='warning'>⚠️</span>") . "</td></tr>";
       
        // Localisation
        $localisation = $criteres['localisation'] ?? '';
        echo "<tr><td>Localisation</td><td><code>" . htmlspecialchars($localisation ?: 'Non définie') . "</code></td>";
        echo "<td>" . (!empty($localisation) ? "<span class='success'>✅</span>" : "<span class='warning'>⚠️</span>") . "</td></tr>";
       
        // Parking
        $parking = $criteres['req_parking'] ?? 0;
        echo "<tr><td>Parking</td><td><code>" . ($parking ? '✅ Oui' : '❌ Non') . "</code></td>";
        echo "<td><span class='success'>✅</span></td></tr>";
       
        // Balcon
        $balcon = $criteres['req_balcon'] ?? 0;
        echo "<tr><td>Balcon</td><td><code>" . ($balcon ? '✅ Oui' : '❌ Non') . "</code></td>";
        echo "<td><span class='success'>✅</span></td></tr>";
       
        // Ascenseur
        $ascenseur = $criteres['req_ascenseur'] ?? 0;
        echo "<tr><td>Ascenseur</td><td><code>" . ($ascenseur ? '✅ Oui' : '❌ Non') . "</code></td>";
        echo "<td><span class='success'>✅</span></td></tr>";
       
        // GPS
        $lat = $criteres['latitude_cible'] ?? null;
        $lng = $criteres['longitude_cible'] ?? null;
        $hasGps = $lat && $lng;
        echo "<tr><td>GPS</td><td><code>" . ($hasGps ? "$lat, $lng" : 'Non défini') . "</code></td>";
        echo "<td>" . ($hasGps ? "<span class='success'>✅</span>" : "<span class='warning'>⚠️ Optionnel</span>") . "</td></tr>";
       
        echo "</table>";
       
        // Évaluation globale
        $problemes = [];
        if ($budget == 0) $problemes[] = "Budget à 0";
        if ($surface == 0) $problemes[] = "Surface à 0";
        if (empty($localisation)) $problemes[] = "Localisation vide";
       
        if (count($problemes) > 0) {
            echo "<p class='warning'>⚠️ Problèmes détectés : " . implode(', ', $problemes) . "</p>";
            echo "<p>→ Corrige-les dans ton profil acheteur.</p>";
        } else {
            echo "<p class='success'>✅ Tous les critères essentiels sont correctement renseignés !</p>";
        }
    }
    echo "</div>";
}

// ============================================
// 4. ANNONCES DISPONIBLES
// ============================================
echo "<div class='card blue'>
<h2>🏠 4. ANNONCES DISPONIBLES</h2>";

$stmt = $db->query("SELECT id, titre, prix, surface, type_bien, ville, latitude, longitude FROM annonces WHERE statut = 'disponible' LIMIT 10");
$annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($annonces) == 0) {
    echo "<p class='warning'>⚠️ Aucune annonce disponible.</p>";
    echo "<p>→ Publie d'abord des annonces.</p>";
} else {
    echo "<table>";
    echo "<tr><th>ID</th><th>Titre</th><th>Prix</th><th>Surface</th><th>Type</th><th>Ville</th><th>GPS</th></tr>";
    foreach ($annonces as $a) {
        $hasGps = $a['latitude'] && $a['longitude'];
        echo "<tr>";
        echo "<td><code>" . $a['id'] . "</code></td>";
        echo "<td>" . htmlspecialchars(substr($a['titre'], 0, 30)) . "...</td>";
        echo "<td>" . number_format($a['prix'], 0, ',', ' ') . " €</td>";
        echo "<td>" . $a['surface'] . " m²</td>";
        echo "<td>" . $a['type_bien'] . "</td>";
        echo "<td>" . htmlspecialchars($a['ville']) . "</td>";
        echo "<td>" . ($hasGps ? "✅" : "<span class='warning'>⚠️</span>") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><span class='warning'>⚠️</span> = Pas de coordonnées GPS → le score spatial sera à 50%</p>";
}
echo "</div>";

// ============================================
// 5. TEST SMG EN DIRECT
// ============================================
if ($user_id) {
    echo "<div class='card green'>
    <h2>🧪 5. TEST SMG EN DIRECT</h2>";
   
    // Récupérer une annonce
    $stmt = $db->query("SELECT id FROM annonces WHERE statut = 'disponible' LIMIT 1");
    $annonceTest = $stmt->fetch(PDO::FETCH_ASSOC);
   
    if ($annonceTest) {
        require_once __DIR__ . '/../src/recommendation.php';
       
        $annonceId = $annonceTest['id'];
        echo "<p>Test avec l'annonce ID: <code>$annonceId</code> et l'acheteur ID: <code>$user_id</code></p>";
       
        $resultat = calculerSMGAnnonceAcheteur($db, $annonceId, $user_id);
       
        echo "<div class='highlight'>";
        echo "<pre>";
        print_r($resultat);
        echo "</pre>";
        echo "</div>";
       
        if (isset($resultat['erreur'])) {
            echo "<p class='error'>❌ Erreur : " . $resultat['erreur'] . "</p>";
        } else {
            echo "<p>📊 SMG Total : <strong style='font-size:24px;color:#10b981;'>" . $resultat['smg_total'] . "%</strong></p>";
           
            // Détail des piliers
            if (isset($resultat['detail'])) {
                echo "<table>";
                echo "<tr><th>Pilier</th><th>Score</th><th>Statut</th></tr>";
                echo "<tr><td>Pilier 1 - Filtre actif</td><td>" . $resultat['detail']['pilier_1_filtre_actif'] . "%</td>";
                echo "<td>" . ($resultat['detail']['pilier_1_filtre_actif'] > 0 ? "<span class='success'>✅</span>" : "<span class='warning'>⚠️</span>") . "</td></tr>";
                echo "<tr><td>Pilier 2 - Profil</td><td>" . $resultat['detail']['pilier_2_profil'] . "%</td>";
                echo "<td>" . ($resultat['detail']['pilier_2_profil'] > 0 ? "<span class='success'>✅</span>" : "<span class='warning'>⚠️</span>") . "</td></tr>";
                echo "<tr><td>Pilier 3 - Comportement</td><td>" . $resultat['detail']['pilier_3_comportemental'] . "%</td>";
                echo "<td>" . ($resultat['detail']['pilier_3_comportemental'] > 0 ? "<span class='success'>✅</span>" : "<span class='warning'>⚠️</span>") . "</td></tr>";
                echo "<tr><td>Score Spatial</td><td>" . $resultat['detail']['score_spatial'] . "%</td>";
                echo "<td>" . ($resultat['detail']['score_spatial'] > 0 ? "<span class='success'>✅</span>" : "<span class='warning'>⚠️</span>") . "</td></tr>";
                echo "</table>";
               
                if ($resultat['cold_start']) {
                    echo "<p class='warning'>⚠️ Mode COLD START actif → Piliers 1 (30%) + 2 (70%)</p>";
                } else {
                    echo "<p class='success'>✅ Mode NORMAL actif → Piliers 1 (40%) + 2 (30%) + 3 (30%)</p>";
                }
            }
        }
    } else {
        echo "<p class='warning'>⚠️ Aucune annonce disponible pour le test.</p>";
    }
    echo "</div>";
}

// ============================================
// 6. HISTORIQUE COMPORTEMENTAL
// ============================================
if ($user_id) {
    echo "<div class='card blue'>
    <h2>📈 6. HISTORIQUE COMPORTEMENTAL (Pilier 3)</h2>";
   
    $stmt = $db->prepare("
        SELECT a.type_bien, COUNT(*) as nb_interactions
        FROM historique_actions_acheteurs h
        JOIN annonces a ON h.annonce_id = a.id
        WHERE h.user_id = ?
        GROUP BY a.type_bien
        ORDER BY nb_interactions DESC
    ");
    $stmt->execute([$user_id]);
    $historique = $stmt->fetchAll(PDO::FETCH_ASSOC);
   
    if (count($historique) == 0) {
        echo "<p class='warning'>⚠️ Aucun historique de navigation.</p>";
        echo "<p>→ Consulte quelques annonces pour créer de l'historique.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>Type de bien</th><th>Nombre d'interactions</th></tr>";
        foreach ($historique as $h) {
            echo "<tr><td>" . $h['type_bien'] . "</td><td>" . $h['nb_interactions'] . "</td></tr>";
        }
        echo "</table>";
        echo "<p class='success'>✅ Historique présent - Pilier 3 calculable.</p>";
    }
    echo "</div>";
}

// ============================================
// 7. RECOMMANDATIONS FINALES
// ============================================
echo "<div class='card' style='border-left-color: #8b5cf6;'>
<h2>💡 7. RECOMMANDATIONS</h2>";

$recos = [];

if (!$user_id) {
    $recos[] = "🔴 Connecte-toi en tant qu'acheteur.";
} else {
    $stmt = $db->prepare("SELECT type_compte FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u && $u['type_compte'] !== 'acheteur') {
        $recos[] = "🔴 Change ton type de compte en 'acheteur' avec : <code>UPDATE users SET type_compte = 'acheteur' WHERE id = $user_id;</code>";
    }
}

// Vérifier les colonnes
$stmt = $db->query("SHOW COLUMNS FROM criteres_recherche");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
$manquantes = array_diff($colonnesAttendues, $cols);
if (count($manquantes) > 0) {
    $recos[] = "🔴 Ajoute les colonnes manquantes dans criteres_recherche.";
}

// Vérifier les critères
if ($user_id) {
    $stmt = $db->prepare("SELECT budget_max, surface_min FROM criteres_recherche WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$c) {
        $recos[] = "🔴 Enregistre tes critères dans profil_acheteur.php";
    } elseif ($c['budget_max'] == 0 || $c['surface_min'] == 0) {
        $recos[] = "🟡 Augmente ton budget ou ta surface min (actuellement à 0).";
    }
}

// Vérifier les annonces avec GPS
$stmt = $db->query("SELECT COUNT(*) FROM annonces WHERE statut = 'disponible' AND latitude IS NOT NULL AND longitude IS NOT NULL");
$nbGps = $stmt->fetchColumn();
if ($nbGps == 0) {
    $recos[] = "🟡 Aucune annonce n'a de coordonnées GPS → le score spatial restera à 50%.";
}

if (count($recos) == 0) {
    echo "<p class='success'>✅ Tout semble OK ! Le SMG devrait fonctionner.</p>";
} else {
    echo "<ul>";
    foreach ($recos as $r) {
        echo "<li style='margin:10px 0;'>$r</li>";
    }
    echo "</ul>";
}

echo "</div>";

// ============================================
// 8. BOUTONS D'ACTION
// ============================================
echo "<div style='text-align:center; margin-top:30px;'>
    <a href='catalogue.php' class='btn' style='margin-right:10px;'>🏠 Voir le catalogue</a>
    <a href='profil_acheteur.php' class='btn' style='background:#3b82f6;'>👤 Modifier mes critères</a>
</div>";

echo "</div></body></html>";