<?php
session_start();
// Génération d'un token CSRF robuste si absent
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Plateforme Immobilier</title>
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
    max-width: 460px;
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

.form-group select {
    width: 100%;
    padding: 12px 4px;
    font-size: 1em;
    font-family: inherit;
    color: var(--text);
    background: transparent;
    border: none;
    border-bottom: 1.5px solid var(--border);
    border-radius: 0;
    cursor: pointer;
    transition: border-color 0.2s ease;
}
.form-group select:hover { border-bottom-color: #B9C2C6; }
.form-group select:focus { outline: none; border-bottom-color: var(--brass); }

/* Deux champs côte à côte (ex: prénom / nom) */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 20px;
}

/* Champs conditionnels (ex: infos pro) */
.conditional-fields {
    border-left: 2px solid var(--paper-line);
    padding-left: 16px;
    margin-bottom: 4px;
}
.conditional-fields.hidden {
    display: none;
}

/* Messages d'erreur inline sous chaque champ */
.error-message {
    display: block;
    min-height: 16px;
    margin-top: 5px;
    font-size: 0.78em;
    color: var(--error-text);
}
.error-message:empty {
    margin-top: 0;
    min-height: 0;
}

.hint-message {
    display: block;
    margin-top: 6px;
    font-size: 0.78em;
    color: var(--text-muted);
}

/* Jauge de robustesse du mot de passe */
.password-strength-meter {
    height: 4px;
    margin-top: 10px;
    background: var(--border);
    border-radius: 2px;
    overflow: hidden;
}
#strength-bar {
    height: 100%;
    width: 0%;
    background: var(--error-text);
    border-radius: 2px;
    transition: width 0.25s ease, background-color 0.25s ease;
}
#strength-bar.weak   { width: 33%;  background: #C0392B; }
#strength-bar.medium { width: 66%;  background: var(--brass); }
#strength-bar.strong { width: 100%; background: #2E7D4F; }

/* Cases à cocher (CGU, RGPD) */
.checkbox-group {
    position: relative;
    display: flex;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 0 10px;
    margin-bottom: 18px;
    font-size: 0.85em;
}
.checkbox-group input[type="checkbox"] {
    width: 16px;
    height: 16px;
    margin-top: 3px;
    flex-shrink: 0;
    accent-color: var(--brass);
    cursor: pointer;
}
.checkbox-group label {
    flex: 1;
    color: var(--text);
    line-height: 1.4;
    cursor: pointer;
}
.checkbox-group .error-message {
    flex-basis: 100%;
    margin-left: 26px;
}

/* Focus clavier visible pour l'accessibilité */
.form-group input:focus-visible,
.form-group select:focus-visible,
.checkbox-group input:focus-visible,
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

    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
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

    .form-group input,
    .form-group select {
        font-size: 16px; /* évite le zoom automatique sur iOS */
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

    .conditional-fields {
        padding-left: 12px;
    }

    .checkbox-group {
        font-size: 0.82em;
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

<div class="register-container">
    <h2>Créer un compte</h2>
    <p class="subtitle">Rejoignez notre communauté immobilière</p>
    
<?php if (isset($_SESSION['erreurs_inscription']) && !empty($_SESSION['erreurs_inscription'])): ?>
    <div style="background-color: #fed7d7; color: #742a2a; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
        <p style="margin: 0 0 8px 0; font-weight: bold;">Le formulaire contient des erreurs :</p>
        <ul style="margin: 0; padding-left: 20px;">
            <?php foreach ($_SESSION['erreurs_inscription'] as $erreur): ?>
                <li><?php echo htmlspecialchars($erreur); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
        // Une fois affichées, on vide les erreurs pour les prochains essais
        unset($_SESSION['erreurs_inscription']);
    ?>
<?php endif; ?>

    <form id="registerForm" action="../src/traitement_inscription.php" method="POST" novalidate>
       
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <div class="form-row">
            <div class="form-group">
                <label for="prenom">Prénom</label>
                <input type="text" id="prenom" name="prenom" required autocomplete="given-name">
                <span class="error-message" id="error-prenom"></span>
            </div>
           
            <div class="form-group">
                <label for="nom">Nom</label>
                <input type="text" id="nom" name="nom" required autocomplete="family-name">
                <span class="error-message" id="error-nom"></span>
            </div>
        </div>

        <div class="form-group">
            <label for="email">Adresse Email</label>
            <input type="email" id="email" name="email" required autocomplete="email">
            <span class="error-message" id="error-email"></span>
        </div>

        <div class="form-group">
            <label for="telephone">Téléphone</label>
            <input type="tel" id="telephone" name="telephone" placeholder="0612345678" required autocomplete="tel">
            <span class="error-message" id="error-telephone"></span>
        </div>

        <div class="form-group">
            <label for="type_compte">Type de compte</label>
            <select id="type_compte" name="type_compte" required>
                <option value="acheteur">Acheteur</option>
                <option value="particulier_vendeur">Particulier Vendeur</option>
                <option value="professionnel">Professionnel de l'immobilier</option>
            </select>
        </div>

        <div id="pro-fields" class="conditional-fields hidden">
            <div class="form-group">
                <label for="nom_agence">Nom de l'agence</label>
                <input type="text" id="nom_agence" name="nom_agence">
                <span class="error-message" id="error-nom_agence"></span>
            </div>
            <div class="form-group">
                <label for="siren">Numéro SIREN</label>
                <input type="text" id="siren" name="siren" maxlength="9" placeholder="123456789">
                <span class="error-message" id="error-siren"></span>
            </div>
        </div>

        <div class="form-group">
            <label for="mot_de_passe">Mot de passe</label>
            <input type="password" id="mot_de_passe" name="mot_de_passe" required autocomplete="new-password">
            <div class="password-strength-meter">
                <div id="strength-bar"></div>
            </div>
            <span class="hint-message">Minimum 12 caractères, une majuscule, un chiffre et un caractère spécial.</span>
            <span class="error-message" id="error-mot_de_passe"></span>
        </div>

        <div class="checkbox-group">
            <input type="checkbox" id="accepte_cgu" name="accepte_cgu" required>
            <label for="accepte_cgu">J'accepte les <a href="#" target="_blank">Conditions Générales d'Utilisation</a></label>
            <span class="error-message" id="error-cgu"></span>
        </div>

        <div class="checkbox-group">
            <input type="checkbox" id="accepte_rgpd" name="accepte_rgpd" required>
            <label for="accepte_rgpd">J'accepte la politique de confidentialité des données (RGPD)</label>
            <span class="error-message" id="error-rgpd"></span>
        </div>

        <button type="submit" class="btn-submit">S'inscrire</button>
    </form>
</div>

<script src="js/inscription.js"></script>
</body>
</html>