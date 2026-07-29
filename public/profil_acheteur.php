<?php
// public/profil_acheteur.php
session_start();
require_once '../config/db.php';

// 1. Vérification de la session et du type de compte
if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Récupération des informations de base de l'utilisateur
    $stmt = $pdo->prepare("SELECT prenom, nom, email, telephone, type_compte, email_verifie, telephone_verifie FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // Sécurité : Si le compte n'existe pas ou s'il n'est pas un acheteur
    if (!$user || $user['type_compte'] !== 'acheteur') {
        header('Location: connexion.php');
        exit();
    }

    // 2. Récupération des critères de recherche de l'acheteur (AVEC TOUS LES CHAMPS)
    $stmt_criteres = $pdo->prepare("
        SELECT type_bien, budget_max, localisation, surface_min, alerte_email,
               req_parking, req_balcon, req_ascenseur, latitude_cible, longitude_cible
        FROM criteres_recherche
        WHERE user_id = ?
    ");
    $stmt_criteres->execute([$user_id]);
    $criteres = $stmt_criteres->fetch();

    // Si aucun critère n'est encore enregistré, on initialise des valeurs par défaut
    if (!$criteres) {
        $criteres = [
            'type_bien' => 'appartement',
            'budget_max' => 0,
            'localisation' => '',
            'surface_min' => 0,
            'alerte_email' => 1,
            'req_parking' => 0,
            'req_balcon' => 0,
            'req_ascenseur' => 0,
            'latitude_cible' => null,
            'longitude_cible' => null
        ];
    }

    // 3. Gestion de l'affichage de l'avatar en cercle parfait
    $avatar_url = 'assets/img/default-avatar.png';
    $extensions = ['jpg', 'jpeg', 'png'];
    foreach ($extensions as $ext) {
        $file_path = 'uploads/profils/avatar_' . $user_id . '.' . $ext;
        if (file_exists($file_path)) {
            $avatar_url = $file_path . '?v=' . filemtime($file_path);
            break;
        }
    }

} catch (PDOException $e) {
    die("Erreur lors du chargement du profil acheteur : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Espace Acheteur - ImmoAventure</title>
    <meta name="viewport" content="width=device-with, initiale-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        :root {
            --primary: #2563eb;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg-card: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8fafc;
            color: var(--text-main);
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .header-profil {
            display: flex;
            align-items: center;
            gap: 20px;
            background: var(--bg-card);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .avatar-container {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid white;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            background-color: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .avatar-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .user-meta h1 {
            margin: 0 0 5px 0;
            font-size: 24px;
        }

        .badge-role {
            display: inline-block;
            padding: 4px 12px;
            background-color: #eff6ff;
            color: var(--primary);
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            font-size: 11px;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 4px;
            margin-left: 8px;
        }
        .status-verified { background-color: #d1fae5; color: #065f46; }
        .status-unverified { background-color: #fee2e2; color: #991b1b; }

        .grid-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        @media (max-width: 768px) {
            .grid-sections { grid-template-columns: 1fr; }
        }

        .card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 18px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 10px;
            color: #0f172a;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 6px;
            color: #334155;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            border: none;
            width: 100%;
            text-align: center;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #b91c1c; }

        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        .switch-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .favoris-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .favoris-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
      
        .favoris-item:last-child { border-bottom: none; }
        .favoris-link { text-decoration: none; color: var(--primary); font-weight: 500; font-size: 14px; }
        .favoris-link:hover { text-decoration: underline; }

        .btn-flottant {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            padding: 12px 20px;
            background-color: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            transition: transform 0.2s, background-color 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-flottant:hover {
            background-color: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        /* ✅ NOUVEAU : Styles pour les commodités et GPS */
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 5px;
        }
        .checkbox-grid label {
            font-weight: normal;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .checkbox-grid label:hover {
            background: #eff6ff;
            border-color: var(--primary);
        }
        .checkbox-grid input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
        }
        .checkbox-grid label.checked {
            background: #eff6ff;
            border-color: var(--primary);
        }

        .geo-btn {
            background-color: #10b981;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            margin-top: 5px;
        }
        .geo-btn:hover {
            background-color: #059669;
        }

        .indice {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .current-criteres {
            background: #f0f9ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary);
        }
        .current-criteres summary {
            cursor: pointer;
            font-weight: bold;
            color: #0f172a;
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .current-criteres summary::-webkit-details-marker { display: none; }
        .current-criteres summary::after {
            content: '▾';
            color: var(--primary);
            transition: transform 0.2s;
        }
        .current-criteres[open] summary::after {
            transform: rotate(180deg);
        }
        .current-criteres summary + p {
            margin-top: 12px;
        }
        .current-criteres p {
            margin: 5px 0;
            font-size: 14px;
        }
        .current-criteres strong {
            color: #0f172a;
        }

        .gps-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        /* ===== Responsive ===== */

        /* Tablettes */
        @media (max-width: 768px) {
            body { padding: 12px; }

            .header-profil {
                padding: 18px;
                gap: 14px;
            }

            .card {
                padding: 18px;
            }

            .btn-flottant {
                bottom: 14px;
                right: 14px;
                padding: 10px 16px;
                font-size: 13px;
            }
        }

        /* Mobiles */
        @media (max-width: 480px) {
            body { padding: 8px; }

            .header-profil {
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 20px 15px;
            }

            .avatar-container {
                width: 80px;
                height: 80px;
            }

            .user-meta h1 {
                font-size: 20px;
            }

            .card {
                padding: 14px;
                border-radius: 10px;
                margin-bottom: 16px;
                box-shadow: none;
                border: 1px solid #e2e8f0;
            }

            .card h2 {
                font-size: 15px;
                margin-bottom: 14px;
                padding-bottom: 8px;
            }

            .form-group {
                margin-bottom: 14px;
            }

            .form-group label {
                font-size: 13px;
            }

            .form-control {
                padding: 8px 10px;
                font-size: 13px;
            }

            .checkbox-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .checkbox-grid label {
                padding: 8px 10px;
                font-size: 13px;
            }

            .gps-grid {
                grid-template-columns: 1fr;
            }

            .current-criteres {
                padding: 14px 16px;
                border-left-width: 3px;
                transition: padding 0.2s ease;
            }

            .current-criteres summary {
                font-size: 15px;
                padding: 4px 0;
            }

            /* Fermé : bandeau plus grand, agréable à toucher */
            .current-criteres:not([open]) summary {
                font-size: 16px;
                padding: 6px 0;
            }

            /* Ouvert : contenu resserré pour ne pas prendre trop de place */
            .current-criteres[open] {
                padding: 10px 12px;
            }

            .current-criteres[open] summary {
                font-size: 13px;
                margin-bottom: 4px;
            }

            .current-criteres p {
                font-size: 13px;
                margin: 4px 0;
            }

            .current-criteres[open] p {
                font-size: 12px;
                margin: 2px 0;
            }

            .switch-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                padding: 10px;
            }

            .favoris-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
                padding: 10px 0;
            }

            .btn-flottant {
                position: fixed;
                left: 12px;
                right: 12px;
                bottom: 12px;
                width: auto;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="header-profil">
        <div class="avatar-container">
            <img src="<?= $avatar_url; ?>" alt="Photo de profil" class="avatar-preview">
        </div>
        <div class="user-meta">
            <h1><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></h1>
            <span class="badge-role">Espace Acheteur</span>
        </div>
    </div>

    <div class="grid-sections">
    <details class="current-criteres">
    <summary>📋 Récapitulatif</summary>
    <p>🏠 Type : <strong><?= ucfirst($criteres['type_bien']) ?></strong></p>
        <div class="column-left">

            <div class="card">
                <h2>👤 Informations Personnelles</h2>
                <form action="../src/traitement_profil.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Changer ma photo de profil (Max 5 Mo)</label>
                        <input type="file" name="photo_profil" class="form-control" accept="image/png, image/jpeg, image/jpg">
                    </div>
                    <div class="form-group">
                        <label>Prénom</label>
                        <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($user['prenom']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nom</label>
                        <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($user['nom']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>
                            Adresse e-mail
                            <span class="badge-status <?= $user['email_verifie'] ? 'status-verified' : 'status-unverified'; ?>">
                                <?= $user['email_verifie'] ? '✓ Vérifié' : '✕ Non vérifié'; ?>
                            </span>
                        </label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>
                            Téléphone
                            <span class="badge-status <?= $user['telephone_verifie'] ? 'status-verified' : 'status-unverified'; ?>">
                                <?= $user['telephone_verifie'] ? '✓ Vérifié' : '✕ Non vérifié'; ?>
                            </span>
                        </label>
                        <input type="text" name="telephone" class="form-control" value="<?= htmlspecialchars($user['telephone']); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Mettre à jour mes informations</button>
                </form>
            </div>

            <div class="card">
                <h2>🛡️ Sécurité & Mot de passe</h2>
                <form action="../src/traitement_password.php" method="POST">
                    <div class="form-group">
                        <label>Mot de passe actuel</label>
                        <input type="password" name="old_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Nouveau mot de passe (8 caractères min.)</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Confirmation du nouveau mot de passe</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Changer mon mot de passe</button>
                </form>
            </div>

            <div class="card" style="border: 1px solid #fee2e2; background: #fff5f5;">
                <h2 style="color: var(--danger);">Zone sensible</h2>
                <p style="font-size: 13px; color: #7f1d1d; margin-bottom: 15px;">La suppression effacera l'ensemble de vos critères de recherche, alertes et données de profil de manière irréversible.</p>
                <form action="../src/supprimer_compte.php" method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer définitivement votre compte acheteur ?');">
                    <button type="submit" class="btn btn-danger">Supprimer définitivement mon compte</button>
                </form>
            </div>
            </details>
        </div>
        <div class="column-right">
        <details class="current-criteres">
    <summary>📋 Récapitulatif</summary>
    <p>🏠 Type : <strong><?= ucfirst($criteres['type_bien']) ?></strong></p>
            <div class="card">
                <h2>🎯 Mes Critères de Recherche</h2>
               
                <!-- ✅ Affichage des critères actuels -->
                <div class="current-criteres">
                    <p><strong>📋 Récapitulatif :</strong></p>
                    <p>🏠 Type : <strong><?= ucfirst($criteres['type_bien']) ?></strong></p>
                    <p>💰 Budget : <strong><?= number_format($criteres['budget_max'], 0, ',', ' ') ?> €</strong></p>
                    <p>📐 Surface min : <strong><?= $criteres['surface_min'] ?> m²</strong></p>
                    <p>📍 Localisation : <strong><?= htmlspecialchars($criteres['localisation'] ?: 'Non définie') ?></strong></p>
                    <p>🚗 Parking : <strong><?= $criteres['req_parking'] ? '✅ Oui' : '❌ Non' ?></strong></p>
                    <p>🌅 Balcon : <strong><?= $criteres['req_balcon'] ? '✅ Oui' : '❌ Non' ?></strong></p>
                    <p>🏢 Ascenseur : <strong><?= $criteres['req_ascenseur'] ? '✅ Oui' : '❌ Non' ?></strong></p>
                    <?php if ($criteres['latitude_cible'] && $criteres['longitude_cible']): ?>
                        <p>📍 GPS : <strong><?= $criteres['latitude_cible'] ?>, <?= $criteres['longitude_cible'] ?></strong></p>
                    <?php endif; ?>
                </div>

                <form action="../src/traitement_criteres.php" method="POST">
                    <div class="form-group">
                        <label>Type de bien recherché</label>
                        <select name="type_bien" class="form-control">
                            <option value="appartement" <?= $criteres['type_bien'] === 'appartement' ? 'selected' : ''; ?>>Appartement</option>
                            <option value="maison" <?= $criteres['type_bien'] === 'maison' ? 'selected' : ''; ?>>Maison</option>
                            <option value="terrain" <?= $criteres['type_bien'] === 'terrain' ? 'selected' : ''; ?>>Terrain</option>
                            <option value="local_commercial" <?= $criteres['type_bien'] === 'local_commercial' ? 'selected' : ''; ?>>Local commercial</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Budget maximum (€)</label>
                        <input type="number" name="budget_max" class="form-control" min="0" value="<?= htmlspecialchars($criteres['budget_max']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Localisation préférée (Villes / Départements)</label>
                        <input type="text" name="localisation" class="form-control" placeholder="Ex: Paris, Lyon, 69002" value="<?= htmlspecialchars($criteres['localisation']); ?>">
                        <div class="indice">Indiquez une ville, un code postal ou un département</div>
                    </div>
                    <div class="form-group">
                        <label>Surface minimale (en m²)</label>
                        <input type="number" name="surface_min" class="form-control" min="0" value="<?= htmlspecialchars($criteres['surface_min']); ?>" required>
                    </div>

                    <!-- ✅ NOUVEAU : Commodités recherchées -->
                    <div class="form-group">
                        <label>🛠️ Commodités recherchées</label>
                        <div class="checkbox-grid">
                            <label <?= $criteres['req_parking'] ? 'class="checked"' : '' ?>>
                                <input type="checkbox" name="req_parking" value="1" <?= $criteres['req_parking'] ? 'checked' : '' ?>>
                                🚗 Parking
                            </label>
                            <label <?= $criteres['req_balcon'] ? 'class="checked"' : '' ?>>
                                <input type="checkbox" name="req_balcon" value="1" <?= $criteres['req_balcon'] ? 'checked' : '' ?>>
                                🌅 Balcon
                            </label>
                            <label <?= $criteres['req_ascenseur'] ? 'class="checked"' : '' ?>>
                                <input type="checkbox" name="req_ascenseur" value="1" <?= $criteres['req_ascenseur'] ? 'checked' : '' ?>>
                                🏢 Ascenseur
                            </label>
                        </div>
                    </div>

                    <!-- ✅ NOUVEAU : Coordonnées GPS -->
                    <div class="form-group">
                        <label>📍 Position GPS (optionnel)</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div>
                                <input type="text" name="latitude_cible" class="form-control"
                                       placeholder="Latitude (ex: 48.856614)"
                                       value="<?= htmlspecialchars($criteres['latitude_cible'] ?? '') ?>">
                            </div>
                            <div>
                                <input type="text" name="longitude_cible" class="form-control"
                                       placeholder="Longitude (ex: 2.352221)"
                                       value="<?= htmlspecialchars($criteres['longitude_cible'] ?? '') ?>">
                            </div>
                        </div>
                        <button type="button" class="geo-btn" onclick="geolocaliserUtilisateur()">
                            📍 Utiliser ma position actuelle
                        </button>
                        <div class="indice">Permet de calculer la distance entre votre position et le bien</div>
                    </div>

                    <div class="form-group" style="margin-top: 25px;">
                        <div class="switch-container">
                            <div>
                                <label style="margin: 0; font-size: 14px;">🔔 Alertes e-mail activées</label>
                                <small style="color: var(--text-muted); display: block; margin-top: 2px;">M'avertir dès qu'un bien correspond à mes critères (SMG ≥ 90%).</small>
                            </div>
                            <input type="checkbox" name="alerte_email" value="1" style="width: 22px; height: 22px; cursor: pointer;" <?= $criteres['alerte_email'] ? 'checked' : ''; ?>>
                        </div>
                    </div>
<button type="submit" class="btn btn-primary" style="margin-top: 10px;">💾 Enregistrer mes préférences d'achat</button>
                </form>
            </div>
            </details>
            <div class="card">
                <h2>❤️ Mes Annonces Favorites</h2>
                <ul class="favoris-list">
                    <li class="favoris-item">
                        <a href="annonce.php?id=12" class="favoris-link">Appartement T2 Moderne - 45m² - Centre-ville</a>
                        <span style="font-size: 13px; font-weight: bold; color: #475569;">185 000 €</span>
                    </li>
                    <li class="favoris-item">
                        <a href="annonce.php?id=25" class="favoris-link">Maison Familiale avec Jardin - 120m²</a>
                        <span style="font-size: 13px; font-weight: bold; color: #475569;">340 000 €</span>
                    </li>
                </ul>
                <div style="margin-top: 20px; text-align: center;">
                    <a href="mes_favoris.php" class="favoris-link" style="font-size: 14px; font-weight: bold;">→ Accéder à tous mes favoris</a>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- ✅ Bouton flottant vers le catalogue -->
<!-- <a href="catalogue.php" class="btn-flottant">🏠 Voir les annonces</a> -->

<script>
// ✅ Fonction de géolocalisation
function geolocaliserUtilisateur() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                // Arrondir à 8 décimales pour plus de précision
                const lat = position.coords.latitude.toFixed(8);
                const lng = position.coords.longitude.toFixed(8);
               
                // Remplir les champs
                const inputs = document.querySelectorAll('input[name="latitude_cible"], input[name="longitude_cible"]');
                inputs[0].value = lat;
                inputs[1].value = lng;
               
                // Feedback visuel
                const btn = document.querySelector('.geo-btn');
                const originalText = btn.textContent;
                btn.textContent = '✅ Position enregistrée !';
                btn.style.backgroundColor = '#10b981';
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.style.backgroundColor = '#10b981';
                }, 3000);
               
                alert('✅ Coordonnées GPS mises à jour avec succès !\nLatitude : ' + lat + '\nLongitude : ' + lng);
            },
            function(error) {
                let message = "Impossible de récupérer la géolocalisation : ";
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        message += "Vous devez autoriser la géolocalisation.";
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message += "Position indisponible.";
                        break;
                    case error.TIMEOUT:
                        message += "Temps d'attente dépassé.";
                        break;
                    default:
                        message += error.message;
                }
                alert(message);
            }
        );
    } else {
        alert("La géolocalisation n'est pas supportée par votre navigateur.");
    }
}

// ✅ Mettre à jour le style des checkboxes quand on clique
document.querySelectorAll('.checkbox-grid input[type="checkbox"]').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
        const label = this.closest('label');
        if (this.checked) {
            label.classList.add('checked');
        } else {
            label.classList.remove('checked');
        }
    });
});
</script>

</body>
</html>