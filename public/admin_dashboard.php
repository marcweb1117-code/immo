<?php
// =========================================================================
// PARTIE 1 : LOGIQUE APPLICATIVE ET SÉCURITÉ (PHP)
// =========================================================================
session_start();
require_once __DIR__ . '/../config/db.php';

// Sécurité : Vérification stricte des droits d'accès de l'Admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Sécurité : Génération du jeton CSRF contre les failles de soumission
if (empty($_SESSION['csrf_token_admin'])) {
    $_SESSION['csrf_token_admin'] = bin2hex(random_bytes(32));
}

try {
    // Récupération des compteurs dynamiques pour les blocs de statistiques
    $stmtAttente = $pdo->query("SELECT COUNT(*) FROM users WHERE statut_verification = 'en_attente'");
    $nbAttente = $stmtAttente->fetchColumn();

    $stmtValide = $pdo->query("SELECT COUNT(*) FROM users WHERE statut_verification = 'valide'");
    $nbValide = $stmtValide->fetchColumn();

    $stmtRefuse = $pdo->query("SELECT COUNT(*) FROM users WHERE statut_verification = 'refuse'");
    $nbRefuse = $stmtRefuse->fetchColumn();

        // Requête principale pour l'affichage du tableau des demandes de modération (CORRIGÉE)
        $sqlList = "SELECT u.id, u.prenom, u.nom, u.email, u.type_compte, u.nom_agence, u.siren, u.date_inscription,
        d.type_document, d.chemin_fichier AS fichier_path
 FROM users u
 LEFT JOIN documents_verification d ON u.id = d.user_id
 WHERE u.statut_verification = 'en_attente'
 ORDER BY u.date_inscription ASC";
   
    $stmtList = $pdo->query($sqlList);
    $utilisateursEnAttente = $stmtList->fetchAll(PDO::FETCH_ASSOC);

} catch (\PDOException $e) {
    error_log("Erreur Dashboard Admin : " . $e->getMessage());
    die("Une erreur technique est survenue lirsdu chargement du tableau de bord.");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Immo 2026</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
/* ============================================
   CSS DASHBOARD ADMIN - RESPONSIVE (FIXED)
   ============================================ */

:root {
    --bg-main: #f8fafc;
    --sidebar-width: 260px;
    --primary: #4f46e5;
    --text-dark: #0f172a;
    --text-light: #64748b;
    --border-color: #e2e8f0;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
}

body {
    background-color: var(--bg-main);
    color: var(--text-dark);
    min-height: 100vh;
}

/* --- DASHBOARD WRAPPER --- */
.dashboard-wrapper {
    display: flex;
    width: 100%;
    min-height: 100vh;
}

/* ============================================
   SIDEBAR — DESKTOP (sticky colonne gauche)
   ============================================ */
.sidebar {
    width: var(--sidebar-width);
    background-color: #1e293b;
    color: white;
    padding: 24px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: fixed;   /* FIX : fixed plutôt que sticky pour isoler du flux */
    top: 0;
    left: 0;
    height: 100vh;
    overflow-y: auto;
    z-index: 100;
    transition: transform 0.3s ease;
}

.sidebar-brand {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 40px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
    flex-grow: 1;
}

.sidebar-item {
    margin-bottom: 8px;
}

.sidebar-link {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #94a3b8;
    text-decoration: none;
    padding: 12px;
    border-radius: 8px;
    transition: all 0.2s;
    font-size: 15px;
    font-weight: 500;
}

.sidebar-link:hover,
.sidebar-link.active {
    background-color: #334155;
    color: white;
}

.sidebar-footer {
    padding-top: 20px;
    border-top: 1px solid #334155;
    font-size: 13px;
    color: #64748b;
}

/* ============================================
   MAIN CONTENT
   ============================================ */
.main-content {
    margin-left: var(--sidebar-width); /* FIX : compensé avec fixed sidebar */
    flex-grow: 1;
    padding: 40px;
    min-width: 0;
    box-sizing: border-box;
    width: 100%;
}

/* --- HEADER --- */
.main-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
    flex-wrap: wrap;
    gap: 15px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

/* Bouton burger — caché sur desktop */
.menu-toggle {
    display: none;
    background: none;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 8px 10px;
    cursor: pointer;
    color: var(--text-dark);
    font-size: 18px;
    line-height: 1;
    transition: background 0.2s;
}
.menu-toggle:hover { background: var(--border-color); }

.header-title h1 {
    font-size: 26px;
    margin: 0;
    color: var(--text-dark);
    font-weight: 700;
}
.header-title p {
    margin: 4px 0 0;
    color: var(--text-light);
    font-size: 14px;
}

/* --- STATS --- */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 24px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    border: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
}
.stat-label {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--text-light);
    letter-spacing: 0.5px;
}
.stat-number {
    font-size: 32px;
    font-weight: 700;
    color: var(--text-dark);
    margin-top: 8px;
}
.stat-card.waiting  { border-top: 4px solid var(--warning); }
.stat-card.approved { border-top: 4px solid var(--success); }
.stat-card.rejected { border-top: 4px solid var(--danger); }

/* --- TABLE --- */
.table-section-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 16px;
    color: var(--text-dark);
}

.table-card {
    background: white;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    overflow: hidden;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch; /* FIX : scroll fluide iOS */
}

table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    min-width: 700px;
}
th {
    background-color: #f8fafc;
    color: var(--text-light);
    padding: 16px 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    border-bottom: 1px solid var(--border-color);
    white-space: nowrap; /* FIX : headers ne cassent pas */
}
td {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-dark);
    font-size: 14px;
    vertical-align: middle;
}
tr:last-child td { border-bottom: none; }
tr:hover { background-color: #fafafa; }

/* --- BADGES --- */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}
.badge-pro     { background-color: #fef3c7; color: #d97706; }
.badge-vendeur { background-color: #f1f5f9; color: #475569; }

/* --- ACTIONS --- */
.action-container {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}
.btn {
    padding: 8px 14px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
    text-decoration: none;
    text-align: center;
    white-space: nowrap;
}
.btn-success { background-color: var(--success); color: white; }
.btn-success:hover { background-color: #059669; }
.btn-danger  { background-color: var(--danger);  color: white; }
.btn-danger:hover  { background-color: #dc2626; }

.input-reason {
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 13px;
    width: 150px;
    outline: none;
}
.input-reason:focus { border-color: var(--primary); }

/* --- ALERTS --- */
.alert {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 24px;
    font-weight: 500;
    font-size: 14px;
}
.alert-success {
    background-color: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}
.alert-error {
    background-color: #fef2f2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.empty-state {
    padding: 40px;
    text-align: center;
    color: var(--text-light);
    font-weight: 600;
}

/* Overlay pour fermer le menu en cliquant ailleurs */
.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    z-index: 99;
}

/* ============================================
   RESPONSIVE — TABLETTE (≤ 992px)
   ============================================ */
@media (max-width: 992px) {
    :root { --sidebar-width: 220px; }

    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    .main-content {
        padding: 28px;
    }
}

/* ============================================
   RESPONSIVE — MOBILE (≤ 768px)
   FIX PRINCIPAL : sidebar devient un drawer
   ============================================ */
@media (max-width: 768px) {

    /* Sidebar glisse hors écran par défaut */
    .sidebar {
        transform: translateX(-100%);
        width: 240px;
    }

    /* Quand le body a la classe open, la sidebar est visible */
    body.sidebar-open .sidebar {
        transform: translateX(0);
    }
    body.sidebar-open .sidebar-overlay {
        display: block;
    }

    /* FIX : plus de margin-left sur mobile */
    .main-content {
        margin-left: 0;
        padding: 20px;
        width: 100%;
    }

    /* Burger visible */
    .menu-toggle { display: flex; align-items: center; }

    .main-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .header-title h1 { font-size: 22px; }

    /* FIX : 2 colonnes sur tablette, pas 3 */
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 28px;
    }
    .stat-card  { padding: 18px; }
    .stat-number { font-size: 26px; }
    .stat-label  { font-size: 11px; }

    table       { min-width: 600px; font-size: 13px; }
    th, td      { padding: 12px 14px; }

    .action-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }
    .input-reason { width: 120px; font-size: 12px; }
    .btn          { font-size: 12px; padding: 6px 12px; }
}

/* ============================================
   RESPONSIVE — PETIT MOBILE (≤ 480px)
   ============================================ */
@media (max-width: 480px) {
    .main-content { padding: 14px; }

    /* 1 colonne sur très petit écran */
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    .header-title h1 { font-size: 19px; }
    .stat-number      { font-size: 22px; }

    table  { min-width: 500px; font-size: 12px; }
    th, td { padding: 10px 12px; }

    .input-reason { width: 100px; font-size: 11px; padding: 6px 8px; }
    .btn          { font-size: 11px; padding: 5px 10px; }
    .empty-state  { padding: 25px; font-size: 14px; }
}
</style>

</head>
<body>

    <div class="dashboard-wrapper">

    <div class="main-header">
                        <div class="header-left">
                            <!-- Burger burger burger 🍔 -->
                            <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Menu">☰</button>
                            </div>
                            </div>
       
        <aside class="sidebar">
        <div class="main-header">
                        <div class="header-left">
                            <!-- Burger burger burger 🍔 -->
                            <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Menu">☰</button>
                            </div>
                            </div>
                        </li>
            <div>
                <div class="sidebar-brand">
                    <span>👑 Boss Suprême</span>
                </div>
                <ul class="sidebar-menu">
                    <li class="sidebar-item">
                        <a href="admin_dashboard.php" class="sidebar-link active">
                            <span>📂 Vérification Dossiers</span>
                        </a>
                    </li>

                    <li class="sidebar-item">
                        <a href="annonces.php" class="sidebar-link">
                            <span>👁️ Voir les Annonces</span>
                        </a>

                    <li class="sidebar-item">
                        <a href="annonces.php" class="sidebar-link">
                            <span>👁️ Voir les Annonces</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div>
                <a href="../src/deconnexion.php" class="sidebar-link" style="color: #f87171;">
                    <span>Déconnexion 🚪</span>
                </a>
            </div>
        </aside>

        <main class="main-content">
           
            <header class="main-header">
                <div class="header-title">
                    <h1>Panneau d'Administration</h1>
                    <p>Gestion et modération réglementaire des pièces d'identité</p>
                </div>
            </header>

            <?php if (isset($_SESSION['message_moderation'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['message_moderation']); unset($_SESSION['message_moderation']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['erreur_moderation'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['erreur_moderation']); unset($_SESSION['erreur_moderation']); ?></div>
            <?php endif; ?>

            <section class="stats-grid">
                <div class="stat-card waiting">
                    <span class="stat-label">En attente</span>
                    <span class="stat-number"><?php echo $nbAttente; ?></span>
                </div>
                <div class="stat-card approved">
                    <span class="stat-label">Comptes Validés</span>
                    <span class="stat-number"><?php echo $nbValide; ?></span>
                </div>
                <div class="stat-card rejected">
                    <span class="stat-label">Dossiers Refusés</span>
                    <span class="stat-number"><?php echo $nbRefuse; ?></span>
                </div>
            </section>

            <h2 class="table-section-title">Demandes en attente d'examen</h2>
           
            <div class="table-card">
                <?php if (empty($utilisateursEnAttente)): ?>
                    <div style="padding: 40px; text-align: center; color: var(--text-light); font-weight: 600;">
                        🎉 Tout est à jour ! Aucun document en attente de vérification.
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Structure / Profil</th>
                                <th>Justificatif</th>
                                <th>Date d'envoi</th>
                                <th>Décision réglementaire</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($utilisateursEnAttente as $user): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></div>
                                        <div style="font-size: 13px; color: var(--text-light); margin-top: 2px;"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </td>
                                   
                                    <td>
                                        <?php if ($user['type_compte'] === 'professionnel'): ?>
                                            <span class="badge badge-pro">Professionnel</span>
                                            <div style="font-size: 12px; color: var(--text-light); margin-top: 4px;">
                                                <strong><?php echo htmlspecialchars($user['nom_agence'] ?? 'Agence non spécifiée'); ?></strong><br>
                                                SIREN : <?php echo htmlspecialchars($user['siren'] ?? 'N/A'); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-vendeur">Particulier Vendeur</span>
                                        <?php endif; ?>
                                    </td>
                                   
                                    <td>
                                        <?php if (!empty($user['fichier_path'])): ?>
                                            <div style="font-size: 13px; font-weight: 600; text-transform: uppercase;">
                                                📄 <?php echo str_replace('_', ' ', $user['type_document'] ?? 'Document'); ?>
                                            </div>
                                            <a href="<?php echo htmlspecialchars($user['fichier_path']); ?>" target="_blank" style="color: var(--primary); font-size: 12px; font-weight: 700; text-decoration: none; display: inline-block; margin-top: 4px;">
                                                👁️ Ouvrir le document
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--danger); font-size: 13px; font-weight: 600;">Fichier manquant</span>
                                        <?php endif; ?>
                                    </td>
                                   
                                    <td style="color: var(--text-light); font-size: 13px;">
                                        <?php echo date('d/m/Y à H:i', strtotime($user['date_inscription'])); ?>
                                    </td>
                                   
                                    <td>
                                        <div class="action-container">
                                            <form action="../src/traitement_moderation.php" method="POST" style="margin: 0;">
                                                <input type="hidden" name="csrf_token_admin" value="<?php echo $_SESSION['csrf_token_admin']; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="valider">
                                                <button type="submit" class="btn btn-success">Valider</button>
                                            </form>

                                            <form action="../src/traitement_moderation.php" method="POST" style="margin: 0; display: flex; gap: 6px; align-items: center;">
                                                <input type="hidden" name="csrf_token_admin" value="<?php echo $_SESSION['csrf_token_admin']; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="refuser">
                                                <input type="text" name="motif_refus" class="input-reason" placeholder="Motif du refus..." required>
                                                <button type="submit" class="btn btn-danger">Refuser</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        </main>
    </div>
    <script>
  function toggleSidebar() {
    document.body.classList.toggle('sidebar-open');
  }

  // Ferme la sidebar si on redimensionne vers desktop
  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
      document.body.classList.remove('sidebar-open');
    }
  });
</script>

</body>
</html>