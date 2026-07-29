<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$annonce_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($annonce_id <= 0) {
    die("Annonce introuvable.");
}

// 1. Incrémenter automatiquement le nombre de vues à chaque chargement de page
$stmt_vue = $db->prepare("UPDATE annonces SET nb_vues = nb_vues + 1 WHERE id = :id");
$stmt_vue->execute([':id' => $annonce_id]);

// 2. CORRECTION : Récupérer UNE SEULE annonce précise grâce à son ID (:id)
$stmt = $db->prepare("
    SELECT a.*, u.prenom, u.nom, u.telephone, u.type_compte, u.nom_agence
    FROM annonces a
    JOIN users u ON a.user_id = u.id
    WHERE a.id = :id
");
$stmt->execute([':id' => $annonce_id]);
$annonce = $stmt->fetch(PDO::FETCH_ASSOC); // Utilisation de fetch() au lieu de fetchAll()

if (!$annonce) {
    die("Cette annonce n'existe pas ou a été supprimée.");
}

// 3. Récupérer TOUTES les photos associées à l'annonce pour le slider (Correction : On enlève le filtre strict du pHash temporairement pour tout afficher)
$stmt_photos = $db->prepare("SELECT chemin_fichier FROM photos_annonces WHERE annonce_id = :id");
$stmt_photos->execute([':id' => $annonce_id]);
$photos = $stmt_photos->fetchAll(PDO::FETCH_ASSOC);

// 4. Récupérer les commentaires liés uniquement à cette annonce (Ajusté selon ta structure)
$stmt_comments = $db->prepare("
    SELECT h.*, u.prenom, u.nom
    FROM historique_actions_acheteurs h
    JOIN users u ON h.user_id = u.id
    WHERE h.annonce_id = :id
    ORDER BY h.date_action DESC
");
$stmt_comments->execute([':id' => $annonce_id]);
$commentaires = $stmt_comments->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($annonce['titre']); ?> - MatchImmo</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f5f7fa; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
      
        /* Structure Slider */
        .slider-container { position: relative; max-width: 100%; height: 450px; overflow: hidden; border-radius: 8px; background-color: #000; }
        .slides { display: none; width: 100%; height: 100%; object-fit: cover; }
        .slides.active { display: block; }
        .prev, .next { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.6); color: white; padding: 15px; cursor: pointer; font-size: 20px; border: none; border-radius: 0 5px 5px 0; }
        .next { right: 0; border-radius: 5px 0 0 5px; }

        /* Stats bar */
        .stats-bar { display: flex; gap: 20px; margin: 15px 0; font-size: 14px; color: #7f8c8d; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .stat-item { display: flex; align-items: center; gap: 5px; }

        /* Vendeur & Actions */
        .vendeur-card { background: #f9fbfd; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-top: 20px; }
        .btn-action { display: inline-block; padding: 10px 20px; color: white; background: #2ecc71; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 10px; cursor: pointer; border: none;}
        .btn-message { background: #3498db; margin-left: 10px; }
      
        /* Commentaires */
        .comment-section { margin-top: 30px; }
        .comment-box { border-left: 3px solid #3498db; padding-left: 10px; margin-bottom: 15px; background: #fafafa; padding: 10px; }
        .comment-meta { font-size: 12px; color: #95a5a6; }
    </style>
</head>
<body>

<div class="container">
    <div class="slider-container">
        <?php if (!empty($photos)): ?>
            <?php foreach ($photos as $index => $photo): ?>
                <img src="uploads/<?php echo htmlspecialchars($photo['chemin_fichier']); ?>" class="slides <?php echo $index === 0 ? 'active' : ''; ?>" alt="Photo du bien">
            <?php endforeach; ?>
            <button class="prev" onclick="changeSlide(-1)">&#10094;</button>
            <button class="next" onclick="changeSlide(1)">&#10095;</button>
        <?php else: ?>
            <div style="color:white; text-align:center; padding-top:200px;">Aucune photo disponible pour ce bien</div>
        <?php endif; ?>
    </div>

    <div class="stats-bar">
        <div class="stat-item">👁️ <strong><?php echo $annonce['nb_vues']; ?></strong> vues</div>
        <div class="stat-item" style="cursor:pointer;" onclick="likerAnnonce(<?php echo $annonce_id; ?>)">❤️ <strong id="like-count"><?php echo $annonce['nb_likes']; ?></strong> j'aime</div>
        <div class="stat-item">💬 <strong><?php echo count($commentaires); ?></strong> commentaires</div>
    </div>

    <h1><?php echo htmlspecialchars($annonce['titre']); ?> - <?php echo number_format($annonce['prix'], 0, ',', ' '); ?> €</h1>
    <p><strong>Localisation :</strong> <?php echo htmlspecialchars($annonce['ville']); ?> (<?php echo htmlspecialchars($annonce['code_postal']); ?>)</p>
    <p><strong>Description :</strong><br><?php echo nl2br(htmlspecialchars($annonce['description'])); ?></p>

    <div class="vendeur-card">
        <h3>Annonce publiée par :
            <a href="profil_public.php?id=<?php echo $annonce['user_id']; ?>" style="color: #3498db; text-decoration: none;">
                <?php echo htmlspecialchars($annonce['prenom'] . ' ' . $annonce['nom']); ?>
                <?php echo ($annonce['type_compte'] === 'professionnel') ? '💼 (Agence : '.htmlspecialchars($annonce['nom_agence']).')' : '👤 (Particulier)'; ?>
            </a>
        </h3>
        <p>Cliquez sur le nom du vendeur pour voir toutes ses autres annonces en ligne.</p>
      
        <button class="btn-action" onclick="revelerTelephone(this, '<?php echo htmlspecialchars($annonce['telephone']); ?>')">📞 Afficher le numéro</button>
        <a href="messagerie.php?dest_id=<?php echo $annonce['user_id']; ?>&annonce_id=<?php echo $annonce_id; ?>" class="btn-action btn-message">✉️ Envoyer un message</a>
    </div>

    <div class="comment-section">
        <h3>Section des discussions (<?php echo count($commentaires); ?>)</h3>
      
        <?php if (isset($_SESSION['user_id'])): ?>
            <form action="../src/ajouter_commentaire.php" method="POST" style="margin-bottom: 20px;">
                <input type="hidden" name="annonce_id" value="<?php echo $annonce_id; ?>">
                <textarea name="commentaire" placeholder="Posez une question sur ce bien..." rows="3" style="width:100%; padding:10px; border-radius:5px; border:1px solid #ccc;" required></textarea>
                <button type="submit" class="btn-action" style="background:#34495e;">Publier le commentaire</button>
            </form>
        <?php else: ?>
            <p><a href="connexion.php">Connectez-vous</a> pour poser une question ou laisser un commentaire.</p>
        <?php endif; ?>

        <div id="liste-commentaires">
            <?php foreach ($commentaires as $com): ?>
                <div class="comment-box">
                    <p style="margin:0 0 5px 0;"><strong><?php echo htmlspecialchars($com['prenom'] . ' ' . $com['nom']); ?> :</strong> <?php echo htmlspecialchars($com['commentaire']); ?></p>
                    <div class="comment-meta">Le <?php echo date('d/m/Y à H:i', strtotime($com['date_creation'])); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    let currentSlideIndex = 0;
    const slides = document.querySelectorAll('.slides');

    function changeSlide(direction) {
        if (slides.length === 0) return;
        slides[currentSlideIndex].classList.remove('active');
        currentSlideIndex += direction;
        if (currentSlideIndex >= slides.length) currentSlideIndex = 0;
        if (currentSlideIndex < 0) currentSlideIndex = slides.length - 1;
        slides[currentSlideIndex].classList.add('active');
    }

    function revelerTelephone(bouton, numero) {
        bouton.innerText = "📞 " + numero;
        bouton.style.background = "#27ae60";
        bouton.onclick = null;
    }

    function likerAnnonce(idAnnonce) {
        fetch('../src/liker_annonce.php?id=' + idAnnonce)
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                document.getElementById('like-count').innerText = data.nouveaux_likes;
            } else {
                alert(data.message || "Erreur lors de l'ajout aux mentions J'aime.");
            }
        });
    }
</script>
</body>
</html>