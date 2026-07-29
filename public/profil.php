<?php
// public/profil.php
session_start();

// 1. Connexion à la base de données
require_once '../config/db.php';

// 2. Sécurité : Vérifier si l'utilisateur est bien connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirige vers la page de connexion si pas connecté
    exit();
}

$user_id = $_SESSION['user_id'];

// 3. Récupération des données de l'utilisateur
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        // Si l'utilisateur n'existe plus dans la BDD (ex: supprimé)
        session_destroy();
        header('Location: login.php');
        exit();
    }
} catch (PDOException $e) {
    die("Erreur de chargement du profil.");
}

// 4. Déterminer le chemin de la photo de profil (avatar)
// Convention : On cherche s'il existe un fichier avatar_ID.jpg ou .png
$avatar_url = "uploads/profils/default-avatar.png"; // Image par défaut
$extensions = ['jpg', 'jpeg', 'png'];
foreach ($extensions as $ext) {
    if (file_exists("uploads/profils/avatar_" . $user_id . "." . $ext)) {
        $avatar_url = "uploads/profils/avatar_" . $user_id . "." . $ext . "?t=" . time(); // Le ?t= évite le cache du navigateur
        break;
    }
}

// 5. Formatage de la date d'inscription (ex: "Membre depuis le 12/03/2025")
$date_inscription = new DateTime($user['date_inscription']);
$date_formatee = $date_inscription->format('d/m/Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Immo</title>
    <style>
        :root {
            --primary: #2563eb;
            --success: #16a34a;
            --warning: #ca8a04;
            --danger: #dc2626;
            --gray-100: #f3f4f6;
            --gray-700: #374151;
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
      
        /* Alertes */
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-danger { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        /* Header Profil & Badges */
        .profile-header { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 25px; margin-bottom: 25px; }
        /* Conteneur du cercle */
.avatar-container {
    width: 100px;         /* Largeur fixe du cercle */
    height: 100px;        /* Hauteur fixe du cercle (identique pour faire un carré parfait) */
    border-radius: 50%;   /* Rend le conteneur parfaitement rond */
    overflow: hidden;     /* Coupe tout ce qui dépasse du cercle */
    border: 3px solid white;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    background-color: #f1f5f9; /* Fond gris clair si l'image met du temps à charger */
    display: flex;
    align-items: center;
    justify-content: center;
}

/* L'image à l'intérieur */
.avatar-preview {
    width: 100%;
    height: 100%;
    object-fit: cover;    /* 👑 LA MAGIE EST ICI : Centre et recadre l'image automatiquement sans la déformer */
    object-position: center; /* Reste centré sur le milieu de la photo */
}
        .profile-info h2 { margin: 0 0 8px 0; font-size: 24px; }
        .member-since { font-size: 14px; color: #64748b; margin-bottom: 10px; }
        .badges-wrapper { display: flex; gap: 10px; flex-wrap: wrap; }
        .badge { padding: 6px 12px; border-radius: 50px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .badge-acheteur { background-color: #e0f2fe; color: #0369a1; }
        .badge-vendeur { background-color: #faf5ff; color: #6b21a8; }
        .badge-valide { background-color: #dcfce7; color: #16a34a; }
        .badge-en-attente { background-color: #fef9c3; color: #ca8a04; }
        .badge-refuse { background-color: #fee2e2; color: #dc2626; }
        .badge-non-requis { background-color: #f1f5f9; color: #475569; }

        /* Sections Formulaires */
        .profile-section { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .profile-section h3 { margin-top: 0; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; color: #0f172a; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
        .input-wrapper { display: flex; align-items: center; gap: 10px; position: relative; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 15px; transition: border 0.2s; }
        .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
      
        /* Indicateurs de vérification */
        .verification-indicator { font-size: 12px; font-weight: 600; padding: 4px 8px; border-radius: 4px; white-space: nowrap; }
        .verified { background-color: #dcfce7; color: #15803d; }
        .not-verified { background-color: #fee2e2; color: #b91c1c; }

        /* Boutons */
        .btn { padding: 10px 20px; border: none; border-radius: 6px; font-size: 15px; font-weight: 500; cursor: pointer; transition: background 0.2s; }
        .btn-primary { background-color: var(--primary); color: white; }
        .btn-primary:hover { background-color: #1d4ed8; }
        .btn-danger-discret { background-color: transparent; color: #94a3b8; border: 1px solid #cbd5e1; font-size: 13px; padding: 6px 12px; }
        .btn-danger-discret:hover { background-color: #fee2e2; color: var(--danger); border-color: #fca5a5; }
                /* Bouton flottant en bas à droite */
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

/* ============ RESPONSIVE ============ */
@media (max-width: 768px) {
    body { padding: 12px; }

    .profile-header {
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 20px;
        gap: 15px;
    }

    .profile-info h2 { font-size: 20px; }

    .badges-wrapper { justify-content: center; }

    .profile-section { padding: 20px; }

    .form-grid-2 {
        grid-template-columns: 1fr !important;
    }

    .input-wrapper {
        flex-wrap: wrap;
    }

    .btn { width: 100%; }

    .profile-section[style*="justify-content: space-between"] {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 15px;
    }

    .profile-section[style*="justify-content: space-between"] form {
        display: flex;
        flex-direction: column;
        gap: 10px;
        width: 100%;
    }

    .btn-flottant {
        position: static;
        display: inline-block;
        text-align: center;
        width: 100%;
        box-shadow: none;
    }
}

@media (max-width: 480px) {
    .avatar-container {
        width: 80px;
        height: 80px;
    }

    .profile-info h2 { font-size: 18px; }

    .badge { font-size: 11px; padding: 5px 10px; }
}
    </style>
</head>
<body>

<div class="container">

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="profile-header">
        <div class="avatar-container">
            <img src="<?= $avatar_url ?>" alt="Photo de profil" class="avatar-preview">
        </div>
        <div class="profile-info">
            <h2><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></h2>
            <div class="member-since">Membre depuis le <?= $date_formatee ?></div>
           
            <div class="badges-wrapper">
                <?php if ($user['type_compte'] === 'acheteur'): ?>
                    <span class="badge badge-acheteur">👤 Acheteur</span>
                <?php else: ?>
                    <span class="badge badge-vendeur">🏠 Particulier Vendeur</span>
                <?php endif; ?>

                <?php if ($user['statut_verification'] === 'valide'): ?>
                    <span class="badge badge-valide">✅ Profil Vérifié</span>
                <?php elseif ($user['statut_verification'] === 'en_attente'): ?>
                    <span class="badge badge-en-attente">⏳ En cours d'examen</span>
                <?php elseif ($user['statut_verification'] === 'refuse'): ?>
                    <span class="badge badge-refuse">❌ Vérification Refusée</span>
                <?php else: ?>
                    <span class="badge badge-non-requis">➖ Non requis</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="profile-section">
        <h3>Informations Personnelles</h3>
        <form action="../src/traitement_profil.php" method="POST" enctype="multipart/form-data">
           
            <div class="form-group">
                <label for="photo_profil">Changer la photo de profil (.png, .jpg, .jpeg)</label>
                <input type="file" name="photo_profil" id="photo_profil" accept="image/png, image/jpeg, image/jpg">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="prenom">Prénom</label>
                    <input type="text" name="prenom" id="prenom" class="form-control" value="<?= htmlspecialchars($user['prenom']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="nom">Nom</label>
                    <input type="text" name="nom" id="nom" class="form-control" value="<?= htmlspecialchars($user['nom']) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Adresse e-mail</label>
                <div class="input-wrapper">
                    <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    <?php if ($user['email_verifie']): ?>
                        <span class="verification-indicator verified">Vérifié</span>
                    <?php else: ?>
                        <span class="verification-indicator not-verified">Non vérifié</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="telephone">Téléphone</label>
                <div class="input-wrapper">
                    <input type="tel" name="telephone" id="telephone" class="form-control" value="<?= htmlspecialchars($user['telephone']) ?>" required>
                    <?php if ($user['telephone_verifie']): ?>
                        <span class="verification-indicator verified">Vérifié</span>
                    <?php else: ?>
                        <span class="verification-indicator not-verified">Non vérifié</span>
                    <?php endif; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
        </form>
    </div>

    <div class="profile-section">
        <h3>Sécurité & Identifiants</h3>
        <form action="../src/traitement_password.php" method="POST">
            <div class="form-group">
                <label for="old_password">Mot de passe actuel</label>
                <input type="password" name="old_password" id="old_password" class="form-control" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmation du nouveau mot de passe</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Mettre à jour le mot de passe</button>
        </form>
    </div>

    <div class="profile-section" style="border-top: 1px solid #fee2e2; display: flex; justify-content: space-between; align-items: center; background-color: #fffafb;">
        <div>
            <h4 style="margin: 0 0 5px 0; color: #9f1239;">Zone dangereuse</h4>
            <p style="margin: 0; font-size: 13px; color: #64748b;">Cette action supprimera définitivement votre compte et toutes vos données.</p>
        </div>
        <form action="../src/supprimer_compte.php" method="POST" onsubmit="return confirm('Êtes-vous absolument sûr de vouloir supprimer définitivement votre compte ? Cette action est irréversible.');">
            <button type="submit" class="btn btn-danger-discret">Supprimer mon compte</button>
            <a href="user_dashboard.php" class="btn-flottant">Dashboard</a>
        </form>
    </div>

</div>

</body>
</html>