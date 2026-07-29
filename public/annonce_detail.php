<?php
/**
* 📄 PAGE DE DÉTAILS D'UNE ANNONCE
* Emplacement : public/annonce_detail.php
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/recommendation.php';
require __DIR__ . '/../src/hash_images.php';
require __DIR__ . '/../src/historique.php';
require __DIR__ . '/../src/chrono.php';

$db = $GLOBALS['pdo'] ?? $pdo ?? $db ?? null;

if (!($db instanceof PDO)) {
    die("Erreur de connexion à la base de données.");
}

// Récupération de l'ID de l'annonce
$annonceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$annonceId) {
    $_SESSION['erreur'] = "Annonce non trouvée.";
    header('Location: catalogue.php');
    exit();
}

// Récupération des détails de l'annonce
$stmt = $db->prepare(" 
SELECT a.*,
       u.prenom, u.nom, u.email, u.telephone, u.type_compte,
       (SELECT chemin_fichier FROM photos_annonces WHERE annonce_id = a.id AND est_principale = 1 LIMIT 1) as photo_principale
FROM annonces a
JOIN users u ON a.user_id = u.id
WHERE a.id = :id AND a.statut = 'disponible'


");
$stmt->execute([':id' => $annonceId]);
$annonce = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$annonce) {
    $_SESSION['erreur'] = "Annonce non trouvée ou indisponible.";
    header('Location: catalogue.php');
    exit();
}

// Récupération de toutes les photos
$stmt = $db->prepare("  
SELECT id, chemin_fichier as url, est_principale, nom_original
FROM photos_annonces
WHERE annonce_id = :annonce_id
ORDER BY est_principale DESC, id ASC

");
$stmt->execute([':annonce_id' => $annonceId]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul du SMG si acheteur connecté
$acheteurId = $_SESSION['user_id'] ?? null;
$smgData = null;
if ($acheteurId) {
    $smgData = calculerSMGAnnonceAcheteur($db, $annonceId, $acheteurId);
   
    // ✅ AJOUT : Enregistrer la consultation dans l'historique
    $action = isset($_GET['action']) && $_GET['action'] === 'clic' ? 'clic' : 'clic';
    enregistrerActionAcheteur($db, $acheteurId, $annonceId, $action);
}

// Vérification si l'utilisateur est le propriétaire de l'annonce
$estProprietaire = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $annonce['user_id'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($annonce['titre']) ?> - Détails</title>

    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; }
      
        .btn-retour {
            display: inline-block;
            padding: 10px 20px;
            background: #34495e;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .btn-retour:hover { background: #2c3e50; }
      
        .detail-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
      
        .photo-principale {
            width: 100%;
            height: 450px;
            object-fit: cover;
            background: #ecf0f1;
        }
      
        .photo-principale-placeholder {
            width: 100%;
            height: 450px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ecf0f1;
            color: #999;
            font-size: 2em;
        }
      
        .galerie {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
        }
        .galerie img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
            cursor: pointer;
            transition: transform 0.2s;
            border: 2px solid transparent;
        }
        .galerie img:hover {
            transform: scale(1.05);
            border-color: #3498db;
        }
        .galerie img.active {
            border-color: #3498db;
            box-shadow: 0 0 10px rgba(52,152,219,0.3);
        }
      
        .info-principale {
            padding: 30px;
            border-bottom: 1px solid #ecf0f1;
        }
      
        .info-principale h1 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 1.8em;
        }
      
        .badges {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        .badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .badge-vente { background: #27ae60; color: white; }
        .badge-location { background: #e67e22; color: white; }
        .badge-type { background: #3498db; color: white; }
        .badge-pieces { background: #9b59b6; color: white; }
        .badge-surface { background: #1abc9c; color: white; }
        .badge-smg-detail {
            background: #f39c12;
            color: white;
            font-size: 1.1em;
            padding: 8px 20px;
        }
        .badge-smg-detail.high { background: #27ae60; }
        .badge-smg-detail.medium { background: #f39c12; }
        .badge-smg-detail.low { background: #e74c3c; }
      
        .prix {
            font-size: 2.2em;
            font-weight: bold;
            color: #2c3e50;
            margin: 15px 0;
        }
      
        .grid-infos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 30px;
            border-bottom: 1px solid #ecf0f1;
        }
        @media (max-width: 768px) {
            .grid-infos { grid-template-columns: 1fr; }
        }
      
        .section-titre {
            font-size: 1.1em;
            font-weight: bold;
            color: #34495e;
            margin-bottom: 15px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 8px;
        }
      
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-item .label { color: #7f8c8d; }
        .info-item .value { font-weight: 500; color: #2c3e50; }
      
        .commodites {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            padding: 30px;
            border-bottom: 1px solid #ecf0f1;
        }
        .commodite {
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
        }
        .commodite.present { background: #d5f5e3; color: #27ae60; }
        .commodite.absent { background: #fdedec; color: #e74c3c; }
      
        .description {
            padding: 30px;
            border-bottom: 1px solid #ecf0f1;
        }
        .description p {
            line-height: 1.8;
            color: #34495e;
            white-space: pre-wrap;
        }
      
        .vendeur-info {
            padding: 30px;
            background: #f8f9fa;
        }
        .vendeur-info h3 { margin-top: 0; color: #2c3e50; }
        .vendeur-detail {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 8px 20px;
        }
        .vendeur-detail .label { color: #7f8c8d; font-weight: 500; }
        .vendeur-detail .value { color: #2c3e50; }
      
        .btn-contact {
            display: inline-block;
            padding: 12px 30px;
            background: #27ae60;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 15px;
            border: none;
            cursor: pointer;
        }
        .btn-contact:hover { background: #219150; }
      
        .btn-modifier {
            display: inline-block;
            padding: 12px 30px;
            background: #f39c12;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 15px;
            margin-right: 10px;
        }
        .btn-modifier:hover { background: #d68910; }
      
        .smg-detail-box {
            padding: 30px;
            background: #ebf5fb;
            border-bottom: 1px solid #ecf0f1;
        }
        .smg-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 15px;
        }
        @media (max-width: 768px) {
            .smg-grid { grid-template-columns: 1fr; }
        }
        .smg-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .smg-item .score {
            font-size: 1.8em;
            font-weight: bold;
            color: #2c3e50;
        }
        .smg-item .label {
            color: #7f8c8d;
            font-size: 0.85em;
            margin-top: 5px;
        }
      
        .correction-note {
            background: #fef9e7;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #f39c12;
            margin-top: 15px;
        }
      
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 992px) {
            .container { max-width: 100%; padding: 0 10px; }
        }

        @media (max-width: 768px) {
            body { padding: 10px; }

            .photo-principale,
            .photo-principale-placeholder {
                height: 260px;
            }

            .info-principale,
            .commodites,
            .description,
            .vendeur-info,
            .smg-detail-box {
                padding: 20px;
            }

            .info-principale h1 { font-size: 1.4em; }

            .prix { font-size: 1.7em; }

            .badges { gap: 8px; }
            .badge { font-size: 0.75em; padding: 4px 10px; }
            .badge-smg-detail { font-size: 1em; padding: 6px 14px; }

            .galerie {
                grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
                padding: 10px;
                gap: 8px;
            }
            .galerie img { height: 70px; }

            .vendeur-detail {
                grid-template-columns: 1fr;
                gap: 4px 0;
            }
            .vendeur-detail .label {
                margin-top: 8px;
            }

            .btn-contact, .btn-modifier {
                display: block;
                text-align: center;
                width: 100%;
                margin-right: 0;
                box-sizing: border-box;
            }
        }

        @media (max-width: 480px) {
            body { padding: 6px; }

            .btn-retour {
                width: 100%;
                text-align: center;
                box-sizing: border-box;
                padding: 10px;
            }

            .photo-principale,
            .photo-principale-placeholder {
                height: 200px;
            }

            .detail-card { border-radius: 8px; }

            .info-principale,
            .commodites,
            .description,
            .vendeur-info,
            .smg-detail-box {
                padding: 15px;
            }

            .info-principale h1 { font-size: 1.2em; }
            .prix { font-size: 1.4em; }

            .commodites { gap: 8px; }
            .commodite { font-size: 0.8em; padding: 6px 12px; }

            .smg-item .score { font-size: 1.4em; }

            .info-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 2px;
            }
        }
    </style>
   
</head>
<body>

<div class="container">
   
    <a href="catalogue.php" class="btn-retour">← Retour au catalogue</a>
   
    <?php if (isset($_SESSION['succes'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['succes']); unset($_SESSION['succes']); ?></div>
    <?php endif; ?>
   
    <?php if (isset($_SESSION['erreur'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_SESSION['erreur']); unset($_SESSION['erreur']); ?></div>
    <?php endif; ?>
   
    <div class="detail-card">
       
        <!-- PHOTO PRINCIPALE -->
        <?php if (!empty($annonce['photo_principale'])): ?>
            <img src="<?= htmlspecialchars($annonce['photo_principale']) ?>" alt="<?= htmlspecialchars($annonce['titre']) ?>" class="photo-principale" id="photoPrincipale">
        <?php else: ?>
            <div class="photo-principale-placeholder">📷 Aucune photo principale</div>
        <?php endif; ?>

        <!-- GALERIE -->
<?php if (count($photos) > 1): ?>
    <div class="galerie">
        <?php foreach ($photos as $photo): ?>
            <img src="<?= htmlspecialchars($photo['url']) ?>"
                 alt="Photo"
                 onclick="changerPhoto('<?= htmlspecialchars($photo['url']) ?>')"
                 class="<?= $photo['est_principale'] ? 'active' : '' ?>"
                 style="cursor:pointer;">
        <?php endforeach; ?>
    </div>
<?php elseif (count($photos) == 1): ?>
    <!-- Une seule photo, pas de galerie -->
<?php endif; ?>

        <!-- INFOS PRINCIPALES -->
        <div class="info-principale">
            <div class="badges">
                <span class="badge <?= $annonce['type_transaction'] === 'vente' ? 'badge-vente' : 'badge-location' ?>">
                    <?= $annonce['type_transaction'] === 'vente' ? '🏷️ Vente' : '🔑 Location' ?>
                </span>
                <span class="badge badge-type">🏠 <?= ucfirst($annonce['type_bien']) ?></span>
                <span class="badge badge-pieces">🛋️ <?= $annonce['pieces'] ?> pièce(s)</span>
                <span class="badge badge-surface">📐 <?= $annonce['surface'] ?> m²</span>
               
                <?php if ($smgData && $smgData['smg_total'] >= 70): ?>
                    <span class="badge badge-smg-detail <?= $smgData['smg_total'] >= 90 ? 'high' : ($smgData['smg_total'] >= 70 ? 'medium' : 'low') ?>">
                        🎯 Match <?= round($smgData['smg_total']) ?>%
                    </span>
                <?php endif; ?>
            </div>
           
            <h1><?= htmlspecialchars($annonce['titre']) ?></h1>
           
            <div class="prix"><?= number_format($annonce['prix'], 0, ',', ' ') ?> €</div>
           
            <p>📍 <?= htmlspecialchars($annonce['adresse']) ?>, <?= htmlspecialchars($annonce['code_postal']) ?> <?= htmlspecialchars($annonce['ville']) ?></p>
            <p>📅 Publiée le <?= date('d/m/Y à H:i', strtotime($annonce['date_creation'])) ?></p>
        </div>
       
        <!-- GRILLE D'INFOS -->
        <div class="grid-infos">
            <div>
                <div class="section-titre">📋 Détails du bien</div>
                <div class="info-item"><span class="label">Type de bien</span><span class="value"><?= ucfirst($annonce['type_bien']) ?></span></div>
                <div class="info-item"><span class="label">Transaction</span><span class="value"><?= $annonce['type_transaction'] === 'vente' ? 'Vente' : 'Location' ?></span></div>
                <div class="info-item"><span class="label">Surface</span><span class="value"><?= $annonce['surface'] ?> m²</span></div>
                <div class="info-item"><span class="label">Pièces</span><span class="value"><?= $annonce['pieces'] ?></span></div>
                <div class="info-item"><span class="label">Prix</span><span class="value"><?= number_format($annonce['prix'], 0, ',', ' ') ?> €</span></div>
                <?php if ($annonce['type_transaction'] === 'location'): ?>
                    <div class="info-item"><span class="label">Loyer CC</span><span class="value"><?= number_format($annonce['prix'], 0, ',', ' ') ?> € / mois</span></div>
                <?php endif; ?>
            </div>
           
            <div>
                <div class="section-titre">📍 Localisation</div>
                <div class="info-item"><span class="label">Adresse</span><span class="value"><?= htmlspecialchars($annonce['adresse'] ?: 'Non renseignée') ?></span></div>
                <div class="info-item"><span class="label">Code Postal</span><span class="value"><?= htmlspecialchars($annonce['code_postal']) ?></span></div>
                <div class="info-item"><span class="label">Ville</span><span class="value"><?= htmlspecialchars($annonce['ville']) ?></span></div>
                <?php if ($annonce['latitude'] && $annonce['longitude']): ?>
                    <div class="info-item"><span class="label">Coordonnées GPS</span><span class="value"><?= $annonce['latitude'] ?>, <?= $annonce['longitude'] ?></span></div>
                    <div class="info-item">
                        <span class="label">Voir sur la carte</span>
                        <span class="value">
                            <a href="https://www.google.com/maps?q=<;?= $annonce['latitude'] ?>,<?= $annonce['longitude'] ?>" target="_blank">📍 Google Maps</a>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
       
        <!-- COMMODITÉS -->
        <div class="commodites">
            <div class="section-titre" style="width:100%;">🛠️ Commodités</div>
            <span class="commodite <?= $annonce['has_parking'] ? 'present' : 'absent' ?>">
                <?= $annonce['has_parking'] ? '✅' : '❌' ?> Parking / Garage
            </span>
            <span class="commodite <?= $annonce['has_balcon'] ? 'present' : 'absent' ?>">
                <?= $annonce['has_balcon'] ? '✅' : '❌' ?> Balcon / Terrasse
            </span>
            <span class="commodite <?= $annonce['has_ascenseur'] ? 'present' : 'absent' ?>">
                <?= $annonce['has_ascenseur'] ? '✅' : '❌' ?> Ascenseur
            </span>
        </div>
       
        <!-- DESCRIPTION -->
        <div class="description">
            <div class="section-titre">📝 Description</div>
            <p><?= nl2br(htmlspecialchars($annonce['description'])) ?></p>
        </div>
       
        <!-- SMG DETAIL (si acheteur connecté) -->
        <?php if ($smgData): ?>
        <div class="smg-detail-box">
            <div class="section-titre">🎯 Score de Matching Global (SMG)</div>
            <p>Ce score reflète la compatibilité entre cette annonce et vos critères de recherche.</p>
           
            <div class="smg-grid">
                <div class="smg-item">
                    <div class="score"><?= round($smgData['pilier_1_filtre_actif'] ?? 0) ?>%</div>
                    <div class="label">📊 Filtre Actif<br><small>Prix, Surface, Localisation</small></div>
                </div>
                <div class="smg-item">
                    <div class="score"><?= round($smgData['pilier_2_profil'] ?? 0) ?>%</div>
                    <div class="label">👤 Profil<br><small>Type bien, Commodités</small></div>
                </div>
                <div class="smg-item">
                    <div class="score"><?= round($smgData['pilier_3_comportemental'] ?? 0) ?>%</div>
                    <div class="label">📈 Comportemental<br><small>Historique de recherche</small></div>
                </div>
            </div>

            <?php if ($smgData['detail']['correction_trajectoire_appliquee'] ?? false): ?>
                <div class="correction-note">
                    💡 <strong>Correction appliquée :</strong> <?= htmlspecialchars($smgData['detail']['note_correction'] ?? '') ?>
                </div>
            <?php endif; ?>
           
            <?php if ($smgData['smg_total'] >= 90): ?>
                <div class="alert alert-success" style="margin-top:15px;">
                    🎉 <strong>Annonce hautement recommandée !</strong> Cette annonce correspond parfaitement à vos critères.
                </div>
            <?php elseif ($smgData['smg_total'] >= 70): ?>
                <div class="alert alert-info" style="margin-top:15px;">
                    👍 <strong>Bonne correspondance.</strong> Cette annonce est pertinente pour vous.
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
       
        <!-- INFOS VENDEUR -->
        <div class="vendeur-info">
            <h3>👤 Informations du vendeur</h3>
            <div class="vendeur-detail">
                <span class="label">Nom</span>
                <span class="value"><?= htmlspecialchars($annonce['prenom'] . ' ' . $annonce['nom']) ?></span>
               
                <span class="label">Email</span>
                <span class="value"><?= htmlspecialchars($annonce['email']) ?></span>
               
                <?php if (!empty($annonce['telephone'])): ?>
                    <span class="label">Téléphone</span>
                    <span class="value"><?= htmlspecialchars($annonce['telephone']) ?></span>
                <?php endif; ?>
               
                <span class="label">Type de compte</span>
                <span class="value"><?= $annonce['type_compte'] === 'vendeur' ? '🏢 Vendeur' : '👤 Particulier' ?></span>
            </div>
           
            <div>
                <?php if ($estProprietaire): ?>
                    <a href="modifier_annonce.php?id=<?= $annonceId ?>" class="btn-modifier">✏️ Modifier l'annonce</a>
                <?php else: ?>
                    <a href="mailto:<?= htmlspecialchars($annonce['email']) ?>?subject=À propos de l'annonce : <?= urlencode($annonce['titre']) ?>" class="btn-contact">
                        📧 Contacter le vendeur
                    </a>
                <?php endif; ?>
            </div>
        </div>
       
    </div>
</div>
<script>
// Fonction pour changer la photo principale au clic sur une miniature
function changerPhoto(url) {
    document.getElementById('photoPrincipale').src = url;
   
    // Mettre à jour la classe active
    document.querySelectorAll('.galerie img').forEach(img => {
        img.classList.remove('active');
        if (img.src === url || img.src.endsWith(url.split('/').pop())) {
            img.classList.add('active');
        }
    });
}

// Vérifier si la galerie existe avant d'ajouter l'événement
document.addEventListener('DOMContentLoaded', function() {
    const galerieImages = document.querySelectorAll('.galerie img');
    if (galerieImages.length > 0) {
        // La première image (principale) est déjà active
    }
});
</script>

</body>
</html>
