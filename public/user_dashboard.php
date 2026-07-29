<?php
// =========================================================================
// PARTIE 1 : LOGIQUE APPLICATIVE, SÉCURITÉ ET DONNÉES UTILISATEUR (PHP)
// =========================================================================
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ .'/../src/chrono.php';

// Sécurité : On vérifie si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$userId = $_SESSION['user_id'];

try {
    // Récupération des données utilisateur + motif de refus s'il existe
    $stmtUser = $pdo->prepare("
        SELECT u.prenom, u.nom, u.type_compte, u.nom_agence, u.siren, u.statut_verification,
               d.motif_refus
        FROM users u
        LEFT JOIN documents_verification d ON u.id = d.user_id
        WHERE u.id = ?
    ");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header('Location: index.php');
        exit();
    }

    // ✅ RÉCUPÉRATION DES ANNONCES DU VENDEUR (3 dernières)
    $stmtAnnonces = $pdo->prepare(" 
            SELECT id, titre, ville, prix, type_bien, type_transaction, statut, date_creation, date_expiration,
       (SELECT chemin_fichier FROM photos_annonces WHERE annonce_id = annonces.id AND est_principale = 1 LIMIT 1) as photo
FROM annonces
WHERE user_id = ?
ORDER BY date_creation DESC
LIMIT 3    
    ");
    $stmtAnnonces->execute([$userId]);
    $dernieresAnnonces = $stmtAnnonces->fetchAll(PDO::FETCH_ASSOC);

    // ✅ RÉCUPÉRATION DE TOUTES LES ANNONCES (pour la liste complète)
    $stmtToutesAnnonces = $pdo->prepare("
    SELECT id, titre, ville, prix, type_bien, type_transaction, statut, date_creation, date_expiration,
       (SELECT chemin_fichier FROM photos_annonces WHERE annonce_id = annonces.id AND est_principale = 1 LIMIT 1) as photo
FROM annonces
WHERE user_id = ?
ORDER BY date_creation DESC
    ");
    $stmtToutesAnnonces->execute([$userId]);
    $toutesAnnonces = $stmtToutesAnnonces->fetchAll(PDO::FETCH_ASSOC);

    // ✅ STATISTIQUES RÉELLES
    $nbAnnonces = count($toutesAnnonces);
   
    // Nombre de vues (si tu as une table de vues, sinon on met 0)
    $nbVues = 0;
    $stmtVues = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM historique_actions_acheteurs
        WHERE annonce_id IN (SELECT id FROM annonces WHERE user_id = ?)
        AND type_action = 'clic'
    ");
    $stmtVues->execute([$userId]);
    $vues = $stmtVues->fetch(PDO::FETCH_ASSOC);
    $nbVues = $vues['total'] ?? 0;

    // Nombre de messages (si tu as une table de messages)
    $nbMessages = 0;

} catch (\PDOException $e) {
    error_log("Erreur User Dashboard : " . $e->getMessage());
    die("Une erreur technique est survenue lors du chargement de votre espace.");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace Client - ImmoAventure</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-main: #f8fafc;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --primary: #2563eb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --card-bg: #ffffff;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-main); color: var(--text-dark); display: flex; min-height: 100vh; }

        .sidebar { width: 260px; background-color: var(--sidebar-bg); color: #fff; padding: 24px; flex-shrink: 0; display: flex; flex-direction: column; min-height: 100vh; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
        .sidebar-brand { font-size: 20px; font-weight: 700; letter-spacing: -0.5px; margin-bottom: 40px; display: flex; align-items: center; gap: 10px; color: #fff; text-decoration: none; }
        .sidebar-brand i { color: var(--primary); }
        .sidebar-menu { list-style: none; display: flex; flex-direction: column; gap: 8px; flex-grow: 1; }
        .sidebar-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #94a3b8; text-decoration: none; font-weight: 500; border-radius: 8px; transition: all 0.2s ease; }
        .sidebar-link:hover, .sidebar-link.active { background-color: var(--sidebar-hover); color: #fff; }
        .sidebar-link.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }

        .sidebar-footer { margin-top: auto; border-top: 1px solid #1e293b; padding-top: 20px; }
        .btn-logout { color: #f1f5f9; background: none; border: none; font-size: 15px; cursor: pointer; width: 100%; text-align: left; display: flex; align-items: center; gap: 12px; padding: 12px 16px; }
        .btn-logout:hover { color: var(--danger); }

        .main-content { flex-grow: 1; padding: 40px; max-width: 1400px; width: 100%; margin: 0 auto; overflow-y: auto; }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 15px; }
        .welcome-title { font-size: 26px; font-weight: 700; color: #0f172a; }
        .badge-account { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; font-size: 13px; font-weight: 600; border-radius: 9999px; background-color: #e2e8f0; color: #334155; }
        .badge-account.pro { background-color: #dbeafe; color: #1e40af; }

        .alert-status { padding: 20px; border-radius: 12px; display: flex; align-items: flex-start; gap: 16px; margin-bottom: 32px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .alert-status.warning { background-color: #fef3c7; border-left: 5px solid var(--warning); color: #92400e; }
        .alert-status.warning i { color: var(--warning); font-size: 22px; }
        .alert-status.danger { background-color: #fee2e2; border-left: 5px solid var(--danger); color: #991b1b; }
        .alert-status.danger i { color: var(--danger); font-size: 22px; }

        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 40px; }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } }
        .stat-card { background-color: var(--card-bg); padding: 24px; border-radius: 16px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .stat-info h3 { font-size: 14px; color: var(--text-muted); font-weight: 500; text-transform: uppercase; margin-bottom: 8px; }
        .stat-info p { font-size: 28px; font-weight: 700; color: #0f172a; }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .stat-icon.blue { background-color: #eff6ff; color: var(--primary); }
        .stat-icon.green { background-color: #ecfdf5; color: var(--success); }
        .stat-icon.orange { background-color: #fff7ed; color: var(--warning); }

        .section-title { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .section-title a { font-size: 14px; font-weight: 600; color: var(--primary); text-decoration: none; }
        .section-title a:hover { text-decoration: underline; }

        .table-container { background-color: var(--card-bg); border-radius: 16px; border: 1px solid #e2e8f0; overflow-x: auto; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background-color: #f8fafc; padding: 16px 24px; font-size: 13px; font-weight: 600; color: var(--text-muted); border-bottom: 1px solid #e2e8f0; }
        td { padding: 18px 24px; font-size: 14px; border-bottom: 1px solid #e2e8f0; color: #334155; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #f8fafc; }

        .annonce-item { display: flex; align-items: center; gap: 14px; font-weight: 600; color: #0f172a; }
        .annonce-img { width: 50px; height: 40px; border-radius: 6px; object-fit: cover; background-color: #e2e8f0; }
        .annonce-img-placeholder { width: 50px; height: 40px; background-color: #e2e8f0; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 12px; }

        .badge-statut { padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 6px; display: inline-block; }
        .badge-disponible { background-color: #ecfdf5; color: #047857; }
        .badge-vendu { background-color: #fee2e2; color: #991b1b; }
        .badge-loue { background-color: #fef3c7; color: #92400e; }
        .badge-masque { background-color: #f1f5f9; color: #475569; }

        .btn-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-edit { padding: 6px 14px; font-size: 12px; font-weight: 600; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; background-color: #dbeafe; color: #1e40af; }
        .btn-edit:hover { background-color: #bfdbfe; }
        .btn-view { padding: 6px 14px; font-size: 12px; font-weight: 600; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; background-color: #e2e8f0; color: #334155; }
        .btn-view:hover { background-color: #cbd5e1; }

        .empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
        .empty-state i { font-size: 48px; color: #cbd5e1; margin-bottom: 15px; display: block; }

        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; min-height: auto; height: auto; position: relative; padding: 16px; }
            .sidebar-menu { flex-direction: row; flex-wrap: wrap; gap: 4px; }
            .sidebar-link { padding: 8px 12px; font-size: 13px; }
            .sidebar-brand { margin-bottom: 20px; }
            .sidebar-footer { margin-top: 10px; border-top: none; padding-top: 10px; }
            .main-content { padding: 20px; }
            .header { flex-direction: column; align-items: flex-start; }
            .welcome-title { font-size: 22px; }
            .stats-grid { grid-template-columns: 1fr; }
            .stat-card { padding: 18px; }
            .section-title { font-size: 16px; }
            table { font-size: 13px; min-width: 600px; }
            th, td { padding: 12px 16px; }
            .btn-actions { flex-direction: column; align-items: stretch; }
            .btn-edit, .btn-view { justify-content: center; }
            .alert-status { flex-direction: column; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 14px; }
            .sidebar { padding: 12px; }
            .sidebar-brand { font-size: 17px; }
            .welcome-title { font-size: 19px; }
            .badge-account { font-size: 12px; padding: 5px 10px; }
            .stat-info p { font-size: 22px; }
            .stat-icon { width: 40px; height: 40px; font-size: 16px; }
            table { min-width: 560px; }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <a href="#" class="sidebar-brand">
            <i class="fa-solid fa-house-chimney-user"></i> ImmoAventure
        </a>
        <ul class="sidebar-menu">
            <li><a href="user_dashboard.php" class="sidebar-link active"><i class="fa-solid fa-chart-pie"></i> Mon Tableau de bord</a></li>
            <li>
                <a href="envoyer_annonce.php" class="sidebar-link <?= ($user['statut_verification'] !== 'valide') ? 'disabled' : '' ?>">
                    <i class="fa-solid fa-circle-plus"></i> Publier une Annonce
                </a>
            </li>
            <li><a href="user_dashboard.php#mes-annonces" class="sidebar-link"><i class="fa-solid fa-layer-group"></i> Mes Annonces</a></li>
            <li>
                <a href="profil.php" class="sidebar-link <?= ($user['statut_verification'] !== 'valide') ? 'disabled' : '' ?>">
                    <i class="fa-solid fa-user"></i> Mon Profil
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <form action="deconnexion.php" method="POST">
                <button type="submit" class="btn-logout">
                    <i class="fa-solid fa-right-from-bracket"></i> Déconnexion
                </button>
            </form>
        </div>
    </aside>

    <main class="main-content">
      
        <header class="header">
            <div>
                <h1 class="welcome-title">Bonjour, <?= htmlspecialchars($user['prenom']) ?> ! 👋</h1>
            </div>
            <div>
                <?php if ($user['type_compte'] === 'professionnel'): ?>
                    <span class="badge-account pro"><i class="fa-solid fa-briefcase"></i> Agence : <?= htmlspecialchars($user['nom_agence']) ?> (SIREN : <?= htmlspecialchars($user['siren']) ?>)</span>
                <?php else: ?>
                    <span class="badge-account"><i class="fa-solid fa-user"></i> Particulier Vendeur</span>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($user['statut_verification'] === 'en_attente'): ?>
            <div class="alert-status warning">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <div>
                    <strong style="display:block; font-size:16px; margin-bottom:4px;">Dossier en cours d'examen</strong>
                    Votre pièce justificative est en cours d'analyse par nos modérateurs. La publication de vos annonces immobilières sera débloquée dès validation finale.
                </div>
            </div>
        <?php elseif ($user['statut_verification'] === 'refuse'): ?>
            <div class="alert-status danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong style="display:block; font-size:16px; margin-bottom:4px;">Dossier refusé par la modération</strong>
                    Votre document n'a pas pu être validé pour le motif suivant : <span style="font-weight:700; text-decoration: underline; color: var(--danger);"><?= htmlspecialchars($user['motif_refus'] ?? 'Document non conforme ou illisible.') ?></span>.
                </div>
            </div>
        <?php endif; ?>

        <?php if ($user['statut_verification'] === 'refuse' || $user['statut_verification'] === 'non_requis'): ?>
            <?php if ($user['statut_verification'] !== 'valide'): ?>
                <div class="alert-status warning" style="border-left-color: var(--primary); background-color: #eff6ff;">
                    <i class="fa-solid fa-upload" style="color: var(--primary);"></i>
                    <div style="width:100%;">
                        <strong style="display:block; font-size:16px; margin-bottom:8px;">Envoyer un justificatif d'identité</strong>
                        <form action="traitement_renvoi.php" method="POST" enctype="multipart/form-data">
                            <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                                <div style="flex:1; min-width:180px;">
                                    <label style="font-size:12px; font-weight:600; color: var(--text-muted); display:block; margin-bottom:4px;">Type de pièce :</label>
                                    <select name="type_document" required style="padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px; outline: none; background: #fff; width:100%;">
                                        <option value="carte_identite">Carte Nationale d'Identité</option>
                                        <option value="passeport">Passeport</option>
                                        <?php if ($user['type_compte'] === 'professionnel'): ?>
                                            <option value="kbis">Extrait KBIS (-3 mois)</option>
                                            <option value="carte_pro">Carte Professionnelle</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div style="flex:1; min-width:200px;">
                                    <label style="font-size:12px; font-weight:600; color: var(--text-muted); display:block; margin-bottom:4px;">Document (PDF, JPG, PNG) :</label>
                                    <input type="file" name="document_justificatif" required style="font-size:14px; color: var(--text-dark); padding: 8px 0; width:100%;">
                                </div>
                                <div>
                                    <button type="submit" style="background-color: var(--primary); color: white; border: none; padding: 12px 24px; font-size: 14px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: background 0.2s; display: inline-flex; align-items: center; gap: 8px;">
                                        <i class="fa-solid fa-paper-plane"></i> Transmettre
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- STATISTIQUES -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Mes Biens en ligne</h3>
                    <p><?= ($user['statut_verification'] === 'valide') ? $nbAnnonces : 0 ?></p>
                </div>
                <div class="stat-icon blue"><i class="fa-solid fa-building"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Vues de mes annonces</h3>
                    <p><?= ($user['statut_verification'] === 'valide') ? $nbVues : 0 ?></p>
                </div>
                <div class="stat-icon green"><i class="fa-solid fa-eye"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Messages Reçus</h3>
                    <p><?= ($user['statut_verification'] === 'valide') ? $nbMessages : 0 ?></p>
                </div>
                <div class="stat-icon orange"><i class="fa-solid fa-comments"></i></div>
            </div>
        </section>

        <!-- DERNIÈRES ANNONCES -->
<h2 class="section-title" id="mes-annonces">
    Mes dernières annonces
    <a href="#toutes-annonces">Voir toutes mes annonces →</a>
</h2>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Bien Immobilier</th>
                <th>Ville</th>
                <th>Prix</th>
                <th>Statut</th>
                <th>Expiration</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
    <?php if ($user['statut_verification'] === 'valide' && count($dernieresAnnonces) > 0): ?>
        <?php foreach ($dernieresAnnonces as $annonce): ?>
            <?php
                $dateExpiration = $annonce['date_expiration'] ?? null;
                $joursRestants = calculerJoursRestants($dateExpiration);
            ?>
            <tr>
                <td>
                    <div class="annonce-item">
                        <?php if (!empty($annonce['photo'])): ?>
                            <img src="<?= htmlspecialchars($annonce['photo']) ?>" alt="" class="annonce-img">
                        <?php else: ?>
                            <div class="annonce-img-placeholder"><i class="fa-regular fa-image"></i></div>
                        <?php endif; ?>
                        <?= htmlspecialchars($annonce['titre']) ?>
                    </div>
                </td>
                <td><?= htmlspecialchars($annonce['ville']) ?></td>
                <td style="font-weight: 600;"><?= number_format($annonce['prix'], 0, ',', ' ') ?> €</td>
                <td>
                    <span class="badge-statut badge-<?= $annonce['statut'] ?>">
                        <?= ucfirst($annonce['statut']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($joursRestants === null): ?>
                        <span style="color:#95a5a6;">Non définie</span>
                    <?php elseif ($joursRestants <= 0): ?>
                        <span style="color:#e74c3c; font-weight:bold;">Expirée</span>
                    <?php else: ?>
                        <span style="color:<?= $joursRestants > 7 ? '#27ae60' : ($joursRestants > 3 ? '#f39c12' : '#e74c3c') ?>; font-weight:bold;">
                            <?= $joursRestants ?> jour<?= $joursRestants > 1 ? 's' : '' ?>
                        </span>
                        <?php if ($joursRestants <= 7): ?>
                            <br><small style="color:#e74c3c;">⚠️ Renouvelez !</small>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="btn-actions">
                        <!-- ✅ Bouton Renouveler (s'affiche si <= 7 jours restants) -->
                        <?php if ($joursRestants !== null && $joursRestants > 0 && $joursRestants <= 7): ?>
                            <a href="../src/traitement_renouveler_annonce.php?id=<?= $annonce['id'] ?>"
                               class="btn-edit"
                               style="background:#fef3c7; color:#92400e;"
                               onclick="return confirm('🔄 Renouveler cette annonce pour 3 semaines supplémentaires ?')">
                                🔄 Renouveler
                            </a>
                        <?php endif; ?>
                       
                        <a href="modifier_annonce.php?id=<?= $annonce['id'] ?>" class="btn-edit">
                            <i class="fa-solid fa-pen"></i> Modifier
                        </a>
                        <a href="annonce_detail.php?id=<?= $annonce['id'] ?>" target="_blank" class="btn-view">
                            <i class="fa-solid fa-eye"></i> Voir
                        </a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php elseif ($user['statut_verification'] === 'valide'): ?>
        <tr>
            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                <i class="fa-regular fa-circle-plus" style="font-size:32px; display:block; margin-bottom:10px;"></i>
                Vous n'avez pas encore publié d'annonces.
                <br><a href="envoyer_annonce.php" style="color: var(--primary); font-weight:600; text-decoration:none;">Publier ma première annonce →</a>
            </td>
        </tr>
    <?php else: ?>
        <tr>
            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                <i class="fa-solid fa-lock" style="font-size:32px; display:block; margin-bottom:10px;"></i>
                Votre compte doit être validé pour commencer à publier.
            </td>
        </tr>
    <?php endif; ?>
</tbody>
    </table>
</div>

        <!-- TOUTES LES ANNONCES -->
        <h2 class="section-title" id="toutes-annonces" style="margin-top:40px;">
            Toutes mes annonces
            <span style="font-size:14px; font-weight:400; color: var(--text-muted);">(<?= count($toutesAnnonces) ?> annonces)</span>
        </h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Bien Immobilier</th>
                        <th>Ville</th>
                        <th>Prix</th>
                        <th>Statut</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($user['statut_verification'] === 'valide' && count($toutesAnnonces) > 0): ?>
                        <?php foreach ($toutesAnnonces as $annonce): ?>
                            <tr>
                                <td>
                                    <div class="annonce-item">
                                        <?php if (!empty($annonce['photo'])): ?>
                                            <img src="<?= htmlspecialchars($annonce['photo']) ?>" alt="Photo" class="annonce-img">
                                        <?php else: ?>
                                            <div class="annonce-img-placeholder"><i class="fa-regular fa-image"></i></div>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($annonce['titre']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($annonce['ville']) ?></td>
                                <td style="font-weight: 600;"><?= number_format($annonce['prix'], 0, ',', ' ') ?> €</td>
                                <td>
                                    <span class="badge-statut badge-<?= $annonce['statut'] ?>">
                                        <?= ucfirst($annonce['statut']) ?>
                                    </span>
                                </td>
                                <td style="font-size:13px; color: var(--text-muted);"><?= date('d/m/Y', strtotime($annonce['date_creation'])) ?></td>
                                <td>
                                    <div class="btn-actions">
                                        <a href="modifier_annonce.php?id=<?= $annonce['id'] ?>" class="btn-edit">
                                            <i class="fa-solid fa-pen"></i> Modifier
                                        </a>
                                        <a href="annonce_detail.php?id=<?= $annonce['id'] ?>" target="_blank" class="btn-view">
                                            <i class="fa-solid fa-eye"></i> Voir
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php elseif ($user['statut_verification'] === 'valide'): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                Aucune annonce publiée.
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                Compte non validé.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</body>
</html>

