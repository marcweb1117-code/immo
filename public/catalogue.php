<?php
/**
* 🛍️ CATALOGUE PERSONNALISÉ AVEC SMG
* Emplacement : public/catalogue.php
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/recommendation.php';
require __DIR__ . '/../src/historique.php';
require __DIR__ . '/../src/chrono.php';

$db = $GLOBALS['pdo'] ?? $pdo ?? $db ?? null;

if (!($db instanceof PDO)) {
    die("Erreur de connexion à la base de données.");
}

$acheteurId = $_SESSION['user_id'] ?? null;

// Récupération des annonces avec la photo principale
$stmt = $db->query("
    SELECT a.*,
       (SELECT chemin_fichier FROM photos_annonces WHERE annonce_id = a.id AND est_principale = 1 LIMIT 1) as photo
FROM annonces a
WHERE a.statut = 'disponible'
ORDER BY a.date_creation DESC

");
$annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Calcul du SMG pour chaque annonce si un acheteur est connecté
foreach ($annonces as &$annonce) {
    if ($acheteurId) {
        // 🔥 ICI : On appelle la fonction qui calcule le SMG
        $smgData = calculerSMGAnnonceAcheteur($db, $annonce['id'], $acheteurId);
       
        // ✅ On stocke les données dans l'annonce
        if ($smgData && !isset($smgData['erreur'])) {
            $annonce['smg_data'] = $smgData;
            $annonce['smg_score'] = $smgData['smg_total'] ?? 0;
        } else {
            $annonce['smg_data'] = null;
            $annonce['smg_score'] = 0;
        }
    } else {
        $annonce['smg_score'] = 0;
        $annonce['smg_data'] = null;
    }
}
unset($annonce);

// ✅ Tri des annonces du plus fort SMG au plus faible (si acheteur connecté)
if ($acheteurId) {
    usort($annonces, function($a, $b) {
        return $b['smg_score'] <=> $a['smg_score'];
    });
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue des Biens</title>
    <link rel="stylesheet" href="asset/css/style.css">
</head>
<body>
<style>
        /* ============================================
           STYLES DU CATALOGUE - VERSION INTÉGRÉE
           ============================================ */
       
        /* --- RESET --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
       
        body {
            background: #f4f6f9;
            padding: 20px;
        }
       
        /* --- CONTAINER --- */
        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
        }
       
        /* --- HEADER --- */
        .header-catalogue {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header-catalogue h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 12px;
        }
        .header-catalogue .user-info {
            color: #7f8c8d;
            font-size: 10px;
            /* margin: 20px; */
            /* padding: 20px; */
        }
        .header-catalogue .user-info strong {
            color: #2c3e50;
        }
       
        /* --- GRILLE --- */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }
       
        /* --- CARTE --- */
        .card {
            border: 1px solid #e8ecf1;
            border-radius: 12px;
            overflow: hidden;
            background: white;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
       
        .card-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }
       
        /* --- IMAGES --- */
        .card-img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            background: #ecf0f1;
        }
        .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 220px;
            background: #ecf0f1;
            color: #bdc3c7;
            font-size: 1.2em;
        }
       
        /* --- CORPS DE LA CARTE --- */
        .card-body {
            padding: 20px;
        }
        .card-body h3 {
            margin: 0 0 8px 0;
            font-size: 1.1em;
            color: #2c3e50;
        }
        .card-body .prix {
            font-size: 1.4em;
            font-weight: bold;
            color: #2c3e50;
            margin: 5px 0;
        }
        .card-body .infos {
            color: #7f8c8d;
            font-size: 0.9em;
            margin: 5px 0;
        }
        .card-body .ville {
            color: #95a5a6;
            font-size: 0.9em;
        }
       
        /* --- BADGES --- */
        .badge-smg {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: bold;
            font-size: 0.9em;
            z-index: 10;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            color: white;
        }
        .badge-smg.high { background: #27ae60; }
        .badge-smg.good { background: #f39c12; }
        .badge-smg.medium { background: #3498db; }
        .badge-smg.low { background: #95a5a6; }
       
        .badge-transaction {
            position: absolute;
            top: 15px;
            left: 15px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: bold;
            color: white;
            z-index: 10;
        }
        .badge-transaction.vente { background: #27ae60; }
        .badge-transaction.location { background: #e67e22; }
       
        /* --- DÉTAILS SMG --- */
        .smg-detail {
            margin-top: 12px;
            padding: 10px 12px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 0.8em;
            color: #555;
            border-left: 3px solid #3498db;
        }
        .smg-detail .smg-piliers {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 5px;
        }
        .smg-detail .smg-piliers span {
            background: white;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.8em;
        }
        .correction-note {
            margin-top: 8px;
            padding: 6px 10px;
            background: #fef9e7;
            border-radius: 5px;
            font-size: 0.8em;
            color: #7d6608;
            border-left: 3px solid #f39c12;
        }
       
        /* --- ÉTAT VIDE --- */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }
        .empty-state h2 {
            color: #2c3e50;
        }
        .empty-state p {
            color: #7f8c8d;
        }
        .empty-state a {
            display: inline-block;
            padding: 12px 30px;
            background: #27ae60;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 15px;
        }
        .empty-state a:hover {
            background: #219150;
        }
       
        /* --- RESPONSIVE --- */
        @media (max-width: 992px) {
            .grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
            .container {
                padding: 0 15px;
            }
        }
       
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .container {
                padding: 0 12px;
            }
            .header-catalogue {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .header-catalogue h1 {
                font-size: 18px;
                text-align: center;
            }
            .header-catalogue .user-info{
                justify-content: center;
                text-align: center;
            }
            .card-img, .no-image {
                height: 200px;
            }
            .card-body {
                padding: 15px;
            }
            .card-body h3 {
                font-size: 16px;
            }
            .card-body .prix {
                font-size: 1.2em;
            }
            .badge-smg {
                font-size: 0.75em;
                padding: 5px 10px;
                top: 10px;
                right: 10px;
            }
            .badge-transaction {
                font-size: 0.65em;
                padding: 4px 8px;
                top: 10px;
                left: 10px;
            }
        }
       
        @media (max-width: 480px) {
            .container {
                padding: 0 8px;
            }
            .card-img, .no-image {
                height: 180px;
            }
            .grid {
                gap: 12px;
            }
        }
    </style>

<div class="container">
   
    <div class="header-catalogue">
        <h1>🏠 Catalogue des biens</h1>
        <div class="user-info">
            <?php if ($acheteurId): ?>
                <strong>👤 Acheteur connecté</strong> 
                <!-- <span style="color:#27ae60;">✅ SMG activé</span> -->
                <a href="profil_acheteur.php" style="margin-left:15px;color:#3498db;text-decoration:none;">⚙️ Mes critères</a>
            <?php else: ?>
                <span style="color:#95a5a6;">🔒 Connectez-vous pour voir vos recommandations personnalisées</span>
                <a href="connexion.php" style="margin-left:15px;color:#3498db;text-decoration:none;">Se connecter</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($annonces) === 0): ?>
        <div class="empty-state">
            <h2>📭 Aucune annonce disponible</h2>
            <p>Soyez le premier à publier une annonce !</p>
            <a href="envoyer_annonce.php" style="display:inline-block;padding:12px 30px;background:#27ae60;color:white;text-decoration:none;border-radius:6px;margin-top:15px;">📝 Publier une annonce</a>
        </div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($annonces as $annonce): ?>
                <div class="card">
                    <a href="annonce_detail.php?id=<?= $annonce['id'] ?>&action=clic" class="card-link">
                       
                        <!-- ✅ BADGE SMG (s'affiche si acheteur connecté) -->
                        <?php if ($acheteurId && isset($annonce['smg_score']) && $annonce['smg_score'] > 0): ?>
                            <?php
                                $score = round($annonce['smg_score']);
                                $classe = 'low';
                                if ($score >= 90) $classe = 'high';
                                elseif ($score >= 70) $classe = 'good';
                                elseif ($score >= 50) $classe = 'medium';
                            ?>
                            <div class="badge-smg <?= $classe ?>">
                                🎯 Match <?= $score ?>%
                            </div>
                        <?php elseif ($acheteurId): ?>
                            <div class="badge-smg low">
                                🎯 En attente...
                            </div>
                        <?php endif; ?>
                       
                        <!-- Badge transaction -->
                        <div class="badge-transaction <?= $annonce['type_transaction'] ?>">
                            <?= $annonce['type_transaction'] === 'vente' ? '🏷️ Vente' : '🔑 Location' ?>
                        </div>
                       
                        <!-- Photo -->
                        <?php if (!empty($annonce['photo'])): ?>
                            <img src="<?= htmlspecialchars($annonce['photo']) ?>" alt="<?= htmlspecialchars($annonce['titre']) ?>" class="card-img">
                        <?php else: ?>
                            <div class="no-image">📷 Aucune photo</div>
                        <?php endif; ?>
                       
                        <div class="card-body">
                            <h3><?= htmlspecialchars($annonce['titre']) ?></h3>
                            <div class="prix"><?= number_format($annonce['prix'], 0, ',', ' ') ?> €</div>
                            <div class="infos">📐 <?= $annonce['surface'] ?> m² • 🛋️ <?= $annonce['pieces'] ?> pièce(s)</div>
                            <div class="ville">📍 <?= htmlspecialchars($annonce['ville']) ?></div>
                        <?php
                            $joursRestants = calculerJoursRestants($annonce['date_expiration']);
                            if ($joursRestants !== null && $joursRestants > 0):
                            ?>
                                <div style="margin-top:8px; font-size:0.85em;">
                                    <?= afficherCompteRebours($joursRestants) ?>
                                    <?= afficherBarreChrono($joursRestants) ?>
                                </div>
                        <?php endif; ?>                        
                           
                            <!-- ✅ DÉTAIL DES PILIERS SMG (si disponible) -->
                            <?php if ($acheteurId && isset($annonce['smg_data']['detail'])): ?>
                                <div class="smg-detail">
                                    <div style="font-weight:600;font-size:0.85em;color:#2c3e50;">
                                        📊 Détail du score
                                    </div>
                                    <div class="smg-piliers">
                                        <span>Filtre: <?= round($annonce['smg_data']['detail']['pilier_1_filtre_actif'] ?? 0) ?>%</span>
                                        <span>Profil: <?= round($annonce['smg_data']['detail']['pilier_2_profil'] ?? 0) ?>%</span>
                                        <span>Comportement: <?= round($annonce['smg_data']['detail']['pilier_3_comportemental'] ?? 0) ?>%</span>
                                        <span>📍 <?= round($annonce['smg_data']['detail']['score_spatial'] ?? 0) ?>%</span>
                                    </div>
                                    <?php if (!empty($annonce['smg_data']['detail']['note_correction'])): ?>
                                        <div class="correction-note">
                                            💡 <?= htmlspecialchars($annonce['smg_data']['detail']['note_correction']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                           
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
   
</div>

</body>
</html>