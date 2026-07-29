<?php
/**
* 📝 MODIFICATION D'UNE ANNONCE
* Emplacement : public/modifier_annonce.php
*/

session_start();
require_once __DIR__ . '/../config/db.php';

// ============================================
// 1. VÉRIFICATION DE L'UTILISATEUR
// ============================================

if (!isset($_SESSION['user_id'])) {
    $_SESSION['erreur'] = "Vous devez être connecté pour modifier une annonce.";
    header('Location: connexion.php');
    exit();
}

$userId = $_SESSION['user_id'];

// ============================================
// 2. RÉCUPÉRATION DE L'ANNONCE
// ============================================

$annonceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$annonceId) {
    $_SESSION['erreur'] = "Annonce non trouvée.";
    header('Location: user_dashboard.php');
    exit();
}

// Vérifier que l'annonce appartient bien à l'utilisateur
$stmt = $pdo->prepare("
    SELECT * FROM annonces
    WHERE id = :id AND user_id = :user_id
");
$stmt->execute([':id' => $annonceId, ':user_id' => $userId]);
$annonce = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$annonce) {
    $_SESSION['erreur'] = "Vous n'êtes pas autorisé à modifier cette annonce.";
    header('Location: user_dashboard.php');
    exit();
}

// ============================================
// 3. RÉCUPÉRATION DES PHOTOS
// ============================================

$stmtPhotos = $pdo->prepare("
    SELECT id, chemin_fichier as url, est_principale
FROM photos_annonces
WHERE annonce_id = ?
ORDER BY est_principale DESC
");
$stmtPhotos->execute([$annonceId]);
$photos = $stmtPhotos->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// 4. GESTION DES MESSAGES
// ============================================

$success = $_SESSION['success'] ?? null;
$erreur = $_SESSION['erreur'] ?? null;
unset($_SESSION['success'], $_SESSION['erreur']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier l'annonce - ImmoAventure</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: #f8fafc; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
      
        .btn-retour {
            display: inline-block;
            padding: 10px 20px;
            background: #64748b;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        .btn-retour:hover { background: #475569; }

        .card {
            background: white;
            border-radius: 16px;
            padding: 35px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        h1 { color: #0f172a; margin-top: 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; color: #334155; font-size: 14px; }
        input[type="text"], input[type="number"], select, textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: border 0.2s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        textarea { resize: vertical; min-height: 120px; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }

        .checkbox-group { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 10px; }
        .checkbox-group label { font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .checkbox-group input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }

        .photo-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; margin-top: 10px; }
        .photo-item { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; position: relative; }
        .photo-item img { width: 100%; height: 80px; object-fit: cover; }
        .photo-item .badge-principale {
            position: absolute;
            top: 4px;
            right: 4px;
            background: #2563eb;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .btn-submit {
            background: #2563eb;
            color: white;
            border: none;
            padding: 14px 28px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
        }
        .btn-submit:hover { background: #1d4ed8; }

        .btn-supprimer {
            background: #ef4444;
            color: white;
            border: none;
            padding: 14px 28px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
            margin-top: 10px;
        }
        .btn-supprimer:hover { background: #dc2626; }

        @media (max-width: 768px) {
            body { padding: 12px; }

            .card {
                padding: 22px;
                border-radius: 12px;
            }

            h1 { font-size: 1.4em; }

            .grid-2, .grid-3 { grid-template-columns: 1fr; }

            .checkbox-group {
                flex-direction: column;
                gap: 12px;
            }

            .photo-list {
                grid-template-columns: repeat(auto-fill, minmax(85px, 1fr));
            }
        }

        @media (max-width: 480px) {
            body { padding: 8px; }

            .card { padding: 16px; }

            .btn-retour {
                display: block;
                text-align: center;
                margin-bottom: 15px;
            }

            h1 { font-size: 1.25em; padding-bottom: 10px; }
            h3 { font-size: 1em; }

            input[type="text"], input[type="number"], select, textarea {
                padding: 10px 12px;
                font-size: 16px; /* évite le zoom automatique sur iOS */
            }

            .photo-list {
                grid-template-columns: repeat(auto-fill, minmax(72px, 1fr));
                gap: 8px;
            }
            .photo-item img { height: 65px; }

            .btn-submit, .btn-supprimer {
                font-size: 15px;
                padding: 12px 20px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="user_dashboard.php" class="btn-retour"><i class="fa-solid fa-arrow-left"></i> Retour au dashboard</a>

    <div class="card">
        <h1>✏️ Modifier l'annonce</h1>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($erreur): ?>
            <div class="alert alert-error"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <form action="../src/traitement_modification_annonce.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="annonce_id" value="<?= $annonce['id'] ?>">

            <div class="form-group">
                <label for="titre">Titre de l'annonce *</label>
                <input type="text" id="titre" name="titre" value="<?= htmlspecialchars($annonce['titre']) ?>" required maxlength="150">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="type_bien">Type de bien *</label>
                    <select id="type_bien" name="type_bien" required>
                        <option value="appartement" <?= $annonce['type_bien'] === 'appartement' ? 'selected' : '' ?>>Appartement</option>
                        <option value="maison" <?= $annonce['type_bien'] === 'maison' ? 'selected' : '' ?>>Maison</option>
                        <option value="terrain" <?= $annonce['type_bien'] === 'terrain' ? 'selected' : '' ?>>Terrain</option>
                        <option value="local_commercial" <?= $annonce['type_bien'] === 'local_commercial' ? 'selected' : '' ?>>Local commercial</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="type_transaction">Type de transaction *</label>
                    <select id="type_transaction" name="type_transaction" required>
                        <option value="vente" <?= $annonce['type_transaction'] === 'vente' ? 'selected' : '' ?>>Vente</option>
                        <option value="location" <?= $annonce['type_transaction'] === 'location' ? 'selected' : '' ?>>Location</option>
                    </select>
                </div>
            </div>

            <div class="grid-3">
                <div class="form-group">
                    <label for="prix">Prix (€) *</label>
                    <input type="number" id="prix" name="prix" step="0.01" min="0" value="<?= $annonce['prix'] ?>" required>
                </div>
                <div class="form-group">
                    <label for="surface">Surface (m²) *</label>
                    <input type="number" id="surface" name="surface" min="1" value="<?= $annonce['surface'] ?>" required>
                </div>
                <div class="form-group">
                    <label for="pieces">Nombre de pièces *</label>
                    <input type="number" id="pieces" name="pieces" min="1" value="<?= $annonce['pieces'] ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description détaillée *</label>
                <textarea id="description" name="description" required><?= htmlspecialchars($annonce['description']) ?></textarea>
            </div>

            <h3 style="margin-top:25px;">📍 Localisation</h3>
            <div class="grid-2">
                <div class="form-group">
                    <label for="ville">Ville *</label>
                    <input type="text" id="ville" name="ville" value="<?= htmlspecialchars($annonce['ville']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="code_postal">Code Postal *</label>
                    <input type="text" id="code_postal" name="code_postal" value="<?= htmlspecialchars($annonce['code_postal']) ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="adresse">Adresse exacte</label>
                <input type="text" id="adresse" name="adresse" value="<?= htmlspecialchars($annonce['adresse']) ?>">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="latitude">Latitude (GPS)</label>
                    <input type="text" id="latitude" name="latitude" value="<?= htmlspecialchars($annonce['latitude']) ?>">
                </div>
                <div class="form-group">
                    <label for="longitude">Longitude (GPS)</label>
                    <input type="text" id="longitude" name="longitude" value="<?= htmlspecialchars($annonce['longitude']) ?>">
                </div>
            </div>

            <h3 style="margin-top:25px;">🛠️ Commodités</h3>
            <div class="checkbox-group">
                <label><input type="checkbox" name="has_parking" value="1" <?= $annonce['has_parking'] ? 'checked' : '' ?>> Parking / Garage</label>
                <label><input type="checkbox" name="has_balcon" value="1" <?= $annonce['has_balcon'] ? 'checked' : '' ?>> Balcon / Terrasse</label>
                <label><input type="checkbox" name="has_ascenseur" value="1" <?= $annonce['has_ascenseur'] ? 'checked' : '' ?>> Ascenseur</label>
            </div>

            <h3 style="margin-top:25px;">📷 Statut de l'annonce</h3>
            <div class="form-group">
                <select name="statut" style="width:100%; padding:12px 14px; border-radius:8px; border:1px solid #cbd5e1; font-size:14px;">
                    <option value="disponible" <?= $annonce['statut'] === 'disponible' ? 'selected' : '' ?>>Disponible</option>
                    <option value="vendu" <?= $annonce['statut'] === 'vendu' ? 'selected' : '' ?>>Vendu</option>
                    <option value="loue" <?= $annonce['statut'] === 'loue' ? 'selected' : '' ?>>Loué</option>
                    <option value="masque" <?= $annonce['statut'] === 'masque' ? 'selected' : '' ?>>Masqué</option>
                </select>
            </div>

            <!-- ============================================ -->
<!-- PHOTOS ACTUELLES AVEC SUPPRESSION -->
<!-- ============================================ -->
<h3 style="margin-top:25px;">📷 Photos actuelles</h3>

<?php if (count($photos) > 0): ?>
    <div class="photo-list">
        <?php foreach ($photos as $photo): ?>
            <div class="photo-item" style="position:relative; border: <?= $photo['est_principale'] ? '3px solid #2563eb' : '1px solid #e2e8f0' ?>; border-radius: 8px; padding: 5px; background: <?= $photo['est_principale'] ? '#eff6ff' : 'white' ?>;">
               
                <img src="<?= htmlspecialchars($photo['url']) ?>" alt="Photo" style="width:100%; height:80px; object-fit:cover; border-radius:4px;">
              
                <!-- Badge "Principale" -->
                <?php if ($photo['est_principale']): ?>
                    <span class="badge-principale" style="position:absolute; top:4px; right:4px; background:#2563eb; color:white; font-size:10px; font-weight:700; padding:2px 8px; border-radius:4px;">
                        ⭐ Principale
                    </span>
                <?php endif; ?>
              
                <!-- Boutons sous la photo -->
                <div style="margin-top:5px; display:flex; gap:5px; justify-content:center; flex-wrap:wrap;">
                   
                    <!-- Bouton "Définir comme principale" (si pas déjà principale) -->
                    <?php if (!$photo['est_principale']): ?>
                        <a href="../src/traitement_photo_principale.php?photo_id=<?= $photo['id'] ?>&annonce_id=<?= $annonce['id'] ?>"
                           style="font-size:10px; color:#2563eb; text-decoration:none; font-weight:600;"
                           onclick="return confirm('Définir cette photo comme image principale ?')">
                            ⭐ Principale
                        </a>
                    <?php endif; ?>
                   
                    <!-- 🔥 NOUVEAU : Bouton Supprimer (sauf si c'est la seule photo ET qu'elle est principale) -->
                    <?php if (!($photo['est_principale'] && count($photos) == 1)): ?>
                        <a href="../src/traitement_supprimer_photo.php?photo_id=<?= $photo['id'] ?>&annonce_id=<?= $annonce['id'] ?>"
                           style="font-size:10px; color:#ef4444; text-decoration:none; font-weight:600;"
                           onclick="return confirm('⚠️ Voulez-vous vraiment supprimer cette photo ? Cette action est irréversible.')">
                            🗑️ Supprimer
                        </a>
                    <?php endif; ?>
                   
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="form-group" style="margin-top:10px;">
        <label>Ajouter de nouvelles photos</label>
        <input type="file" name="nouvelles_photos[]" multiple accept="image/*" style="padding:10px 0;">
        <small style="color:#64748b; display:block; margin-top:4px;">Les nouvelles photos seront ajoutées en tant que photos secondaires.</small>
    </div>
<?php else: ?>
    <div class="form-group">
        <label>Aucune photo. Ajoutez-en :</label>
        <input type="file" name="nouvelles_photos[]" multiple accept="image/*" style="padding:10px 0;">
        <small style="color:#64748b; display:block; margin-top:4px;">La première photo uploadée deviendra automatiquement l'image principale.</small>
    </div>
<?php endif; ?>

            <button type="submit" class="btn-submit"><i class="fa-solid fa-save"></i> Enregistrer les modifications</button>
        </form>

        <form action="../src/traitement_suppression_annonce.php" method="POST" onsubmit="return confirm('⚠️ Voulez-vous vraiment supprimer cette annonce ? Cette action est irréversible.');">
            <input type="hidden" name="annonce_id" value="<?= $annonce['id'] ?>">
            <button type="submit" class="btn-supprimer"><i class="fa-solid fa-trash"></i> Supprimer l'annonce</button>
        </form>
    </div>
</div>

</body>
</html>