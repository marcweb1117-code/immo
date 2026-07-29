<?php
// public/connexion.php
session_start();


// Génération d'un token CSRF pour la connexion
if (empty($_SESSION['csrf_token_login'])) {
    $_SESSION['csrf_token_login'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Plateforme Immobilier</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        /* =========================================================
   Plateforme Immobilier — style.css
   Thème "plan d'architecte" : fond quadrillé façon plan technique,
   repères d'angle façon feuille de plan, encre bleu nuit + laiton.
   ========================================================= */

@import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600&display=swap');

:root {
    --ink: #16283A;            /* bleu nuit — encre de plan */
    --ink-dark: #0D1B27;
    --brass: #B08A3E;          /* laiton — accent clé/poignée */
    --paper: #F5F3EE;          /* fond papier */
    --paper-line: rgba(22, 40, 58, 0.08); /* trait de quadrillage */
    --card-bg: #FFFFFF;
    --text: #1F2A33;
    --text-muted: #62727C;
    --border: #DDE2E1;
    --error-bg: #FDECEA;
    --error-text: #7A2E24;
    --success-bg: #E7F3EA;
    --success-text: #234B32;
    --radius: 4px;
    --shadow: 0 24px 60px -20px rgba(13, 27, 39, 0.35);
}

* {
    box-sizing: border-box;
}

html, body {
    height: 100%;
}

body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px 20px;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    color: var(--text);
    background-color: var(--paper);
    background-image:
        linear-gradient(var(--paper-line) 1px, transparent 1px),
        linear-gradient(90deg, var(--paper-line) 1px, transparent 1px);
    background-size: 28px 28px;
}

/* ---------- Carte de connexion ---------- */

.register-container {
    position: relative;
    width: 100%;
    max-width: 400px;
    background: var(--card-bg);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 48px 40px 40px;
}

/* Repères d'angle façon feuille de plan technique — le signature element */
.register-container::before,
.register-container::after {
    content: "";
    position: absolute;
    width: 18px;
    height: 18px;
    border: 2px solid var(--brass);
    top: -9px;
    left: -9px;
    border-right: none;
    border-bottom: none;
}
.register-container::after {
    left: auto;
    top: auto;
    right: -9px;
    bottom: -9px;
    border: 2px solid var(--brass);
    border-left: none;
    border-top: none;
}

.register-container h2 {
    margin: 0 0 6px;
    font-family: 'Fraunces', Georgia, serif;
    font-weight: 600;
    font-size: 1.9em;
    color: var(--ink);
    letter-spacing: -0.01em;
}

.subtitle {
    margin: 0 0 32px;
    color: var(--text-muted);
    font-size: 0.95em;
}

/* ---------- Formulaire ---------- */

.form-group {
    margin-bottom: 22px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 0.8em;
    font-weight: 600;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--text-muted);
}

.form-group input {
    width: 100%;
    padding: 12px 4px;
    font-size: 1em;
    font-family: inherit;
    color: var(--text);
    background: transparent;
    border: none;
    border-bottom: 1.5px solid var(--border);
    border-radius: 0;
    transition: border-color 0.2s ease;
}

.form-group input::placeholder {
    color: #B7C0C5;
}

.form-group input:hover {
    border-bottom-color: #B9C2C6;
}

.form-group input:focus {
    outline: none;
    border-bottom-color: var(--brass);
}

/* Focus clavier visible pour l'accessibilité */
.form-group input:focus-visible,
.btn-submit:focus-visible,
a:focus-visible {
    outline: 2px solid var(--brass);
    outline-offset: 3px;
}

/* ---------- Bouton ---------- */

.btn-submit {
    width: 100%;
    margin-top: 10px;
    padding: 14px 20px;
    font-family: inherit;
    font-size: 0.95em;
    font-weight: 600;
    letter-spacing: 0.02em;
    color: #FFFFFF;
    background: var(--ink);
    border: none;
    border-radius: var(--radius);
    cursor: pointer;
    transition: background-color 0.2s ease, transform 0.15s ease;
}

.btn-submit:hover {
    background: var(--ink-dark);
}

.btn-submit:active {
    transform: translateY(1px);
}

/* ---------- Liens & messages ---------- */

a {
    color: var(--brass);
}

.register-container > p a,
p a {
    font-weight: 600;
    text-decoration: none;
}

.register-container > p a:hover,
p a:hover {
    text-decoration: underline;
}

/* Recolore proprement les blocs d'alerte injectés en inline par le PHP */
.register-container div[style*="background-color: #c6f6d5"] {
    background-color: var(--success-bg) !important;
    color: var(--success-text) !important;
}
.register-container div[style*="background-color: #fed7d7"] {
    background-color: var(--error-bg) !important;
    color: var(--error-text) !important;
}

/* ---------- Mouvement réduit ---------- */

@media (prefers-reduced-motion: reduce) {
    .btn-submit {
        transition: none;
    }
}

/* =========================================================
   RESPONSIVE
   ========================================================= */

/* Tablette */
@media (max-width: 600px) {
    body {
        padding: 20px 16px;
        align-items: flex-start;
        padding-top: 48px;
    }

    .register-container {
        max-width: 100%;
        padding: 40px 28px 32px;
    }

    .register-container h2 {
        font-size: 1.6em;
    }
}

/* Petit mobile */
@media (max-width: 380px) {
    body {
        padding: 12px;
        padding-top: 32px;
        background-size: 20px 20px;
    }

    .register-container {
        padding: 32px 20px 24px;
        border-radius: 0;
    }

    .register-container::before,
    .register-container::after {
        width: 14px;
        height: 14px;
    }

    .register-container h2 {
        font-size: 1.4em;
    }

    .subtitle {
        font-size: 0.88em;
        margin-bottom: 24px;
    }

    .form-group {
        margin-bottom: 18px;
    }

    .btn-submit {
        padding: 13px 16px;
    }
}

/* Écrans très larges : on laisse la carte respirer un peu plus */
@media (min-width: 1400px) {
    .register-container {
        max-width: 430px;
        padding: 56px 48px 44px;
    }
}
    </style>
</head>
<body>

<div class="register-container"> <h2>Connexion</h2>
    <p class="subtitle">Accédez à votre espace immobilier</p>

    <?php if (isset($_SESSION['succes_inscription'])): ?>
        <div style="background-color: #c6f6d5; color: #22543d; padding: 10px; border-radius: 4px; margin-bottom: 20px; font-size: 14px;">
            <?php
                echo $_SESSION['succes_inscription'];
                unset($_SESSION['succes_inscription']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['erreur_connexion'])): ?>
        <div style="background-color: #fed7d7; color: #742a2a; padding: 10px; border-radius: 4px; margin-bottom: 20px; font-size: 14px;">
            <?php
                echo $_SESSION['erreur_connexion'];
                unset($_SESSION['erreur_connexion']);
            ?>
        </div>
    <?php endif; ?>

    <form action="../src/traitement_connexion.php" method="POST" novalidate>
       
        <input type="hidden" name="csrf_token_login" value="<?php echo $_SESSION['csrf_token_login']; ?>">

        <div class="form-group">
            <label for="email">Adresse Email</label>
            <input type="email" id="email" name="email" required autocomplete="email">
        </div>

        <div class="form-group">
            <label for="mot_de_passe">Mot de passe</label>
            <input type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn-submit">Se connecter</button>
    </form>

    <p style="text-align: center; margin-top: 20px; font-size: 14px;">
        Pas encore de compte ? <a href="inscription.php" style="color: var(--primary-color); text-decoration: none; font-weight: bold;">Inscrivez-vous ici</a>
    </p>
</div>

</body>
</html>