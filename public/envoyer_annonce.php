<?php
/**
* 📄 FORMULAIRE DE PUBLICATION D'ANNONCE
* Emplacement : public/envoyer_annonce.php
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de sécurité : l'utilisateur doit être connecté et avoir un rôle vendeur/pro/admin
if (!isset($_SESSION['user_id'])) {
    $_SESSION['erreur'] = "Vous devez être connecté pour accéder à cette page.";
    header('Location: connexion.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publier une annonce - Immobilier</title>
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f4f6f9; margin: 0; padding: 20px; }
        .form-container { max-width: 800px; background: #ffffff; margin: 20px auto; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; font-size: 1.6em; }
        .alert { padding: 12px 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
      
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; color: #34495e; }
        input[type="text"], input[type="number"], select, textarea {
            width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px;
        }
        textarea { resize: vertical; min-height: 120px; }
      
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }

        .checkbox-group { display: flex; gap: 20px; margin-top: 10px; }
        .checkbox-group label { font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .checkbox-group input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }

        .file-upload { border: 2px dashed #3498db; padding: 20px; text-align: center; border-radius: 6px; background-color: #f8fbfe; cursor: pointer; }
      
        .btn-submit {
            background-color: #27ae60; color: white; border: none; padding: 14px 28px;
            font-size: 16px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%;
            transition: background 0.3s ease;
        }
        .btn-submit:hover { background-color: #219150; }
        .geo-btn { background-color: #3498db; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; margin-top: 5px; font-size: 0.85em; }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            body { padding: 12px; }

            .form-container {
                margin: 10px auto;
                padding: 20px;
                border-radius: 8px;
            }

            h1 { font-size: 1.35em; }

            .grid-2,
            .grid-3 {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .checkbox-group {
                flex-direction: column;
                gap: 12px;
            }
        }

        @media (max-width: 480px) {
            body { padding: 8px; }

            .form-container {
                padding: 15px;
                border-radius: 6px;
            }

            h1 { font-size: 1.2em; padding-bottom: 8px; }
            h3 { font-size: 1.05em; }

            input[type="text"], input[type="number"], select, textarea {
                padding: 10px;
                font-size: 16px; /* évite le zoom automatique sur iOS */
            }

            .file-upload { padding: 14px; }

            .btn-submit { padding: 12px 20px; font-size: 15px; }

            .geo-btn {
                width: 100%;
                white-space: normal;
            }
        }
    </style>
    
</head>
<body>
<a href="user_dashboard.php"><button>DASHBOARD</button></a>
<div class="form-container">
    <h1> Publier une nouvelle annonce</h1>

    <?php if (isset($_SESSION['erreur'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_SESSION['erreur']); unset($_SESSION['erreur']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['succes'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['succes']); unset($_SESSION['succes']); ?>
        </div>
    <?php endif; ?>

    <form action="../src/traitement_annonce.php" method="POST" enctype="multipart/form-data">
       
        <div class="form-group">
            <label for="titre">Titre de l'annonce *</label>
            <input type="text" id="titre" name="titre" placeholder="Ex: Bel appartement lumineux 3 pièces avec balcon" required maxlength="150">
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label for="type_bien">Type de bien *</label>
                <select id="type_bien" name="type_bien" required>
                    <option value="appartement">Appartement</option>
                    <option value="maison">Maison</option>
                    <option value="terrain">Terrain</option>
                    <option value="local_commercial">Local commercial</option>
                </select>
            </div>
            <div class="form-group">
                <label for="type_transaction">Type de transaction *</label>
                <select id="type_transaction" name="type_transaction" required>
                    <option value="vente">Vente</option>
                    <option value="location">Location</option>
                </select>
            </div>
        </div>

        <div class="grid-3">
            <div class="form-group">
                <label for="prix">Prix (€) *</label>
                <input type="number" id="prix" name="prix" step="0.01" min="0" placeholder="250000" required>
            </div>
            <div class="form-group">
                <label for="surface">Surface (m²) *</label>
                <input type="number" id="surface" name="surface" min="1" placeholder="65" required>
            </div>
            <div class="form-group">
                <label for="pieces">Nombre de pièces *</label>
                <input type="number" id="pieces" name="pieces" min="1" value="1" required>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description détaillée *</label>
            <textarea id="description" name="description" placeholder="Décrivez le bien en détail (état, orientation, travaux récents...)" required></textarea>
        </div>

        <h3> Localisation</h3>
        <div class="grid-2">
            <div class="form-group">
                <label for="ville">Ville *</label>
                <input type="text" id="ville" name="ville" placeholder="Ex: Paris" required maxlength="100">
            </div>
            <div class="form-group">
                <label for="code_postal">Code Postal *</label>
                <input type="text" id="code_postal" name="code_postal" placeholder="Ex: 75015" required maxlength="10">
            </div>
        </div>

        <div class="form-group">
            <label for="adresse">Adresse exacte</label>
            <input type="text" id="adresse" name="adresse" placeholder="Ex: 12 Rue des Fleurs">
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label for="latitude">Latitude (GPS)</label>
                <input type="text" id="latitude" name="latitude" placeholder="Ex: 48.856614">
            </div>
            <div class="form-group">
                <label for="longitude">Longitude (GPS)</label>
                <input type="text" id="longitude" name="longitude" placeholder="Ex: 2.352221">
            </div>
        </div>
        <button type="button" class="geo-btn" onclick="geolocaliserUtilisateur()">📍 Remplir automatiquement mes coordonnées GPS actuelles</button>

        <h3 style="margin-top:25px;"> Commodités</h3>
        <div class="checkbox-group">
            <label><input type="checkbox" name="has_parking" value="1"> Parking / Garage</label>
            <label><input type="checkbox" name="has_balcon" value="1"> Balcon / Terrasse</label>
            <label><input type="checkbox" name="has_ascenseur" value="1"> Ascenseur</label>
        </div>

        <h3 style="margin-top:25px;">📷 Photos du bien</h3>
        <div class="form-group">
            <div class="file-upload">
                <label for="photos">Sélectionnez les images (JPG, PNG, WEBP)</label>
                <input type="file" id="photos" name="photos[]" multiple accept="image/*" style="margin-top:10px;">
                <!-- Dans la partie upload, remplace le paragraphe explicatif -->
<p style="font-size:0.85em; color:#7f8c8d; margin-top:5px;">
    La première photo sera utilisée comme couverture principale.
    <strong style="color:#27ae60;">🔒 Système anti-doublon actif</strong> :
    détection par MD5, taille et dimensions.
</p>
            </div>
        </div>

        <button type="submit" class="btn-submit">🚀 Publier l'annonce maintenant</button>
    </form>
</div>

<script>
// Optionnel : Récupère la position géographique du navigateur pour simplifier la saisie GPS
function geolocaliserUtilisateur() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            document.getElementById('latitude').value = position.coords.latitude.toFixed(8);
            document.getElementById('longitude').value = position.coords.longitude.toFixed(8);
            alert("Coordonnées GPS mises à jour avec succès !");
        }, function(error) {
            alert("Impossible de récupérer la géolocalisation automatique : " + error.message);
        });
    } else {
        alert("La géolocalisation n'est pas supportée par votre navigateur.");
    }
}
</script>

</body>
</html>