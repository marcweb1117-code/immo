
<?php
/**
* ⏱️ GESTION DU COMPTE À REBOURS DES ANNONCES
* Emplacement : src/chrono.php
*/

function calculerJoursRestants($dateExpiration) {
    if (empty($dateExpiration)) {
        return null;
    }
   
    $now = new DateTime();
    $expiration = new DateTime($dateExpiration);
    $diff = $now->diff($expiration);
   
    // Si la date est passée
    if ($diff->invert == 1) {
        return 0;
    }
   
    return $diff->days;
}

function afficherCompteRebours($joursRestants) {
    if ($joursRestants === null) {
        return '<span style="color: #95a5a6;">⏳ Non définie</span>';
    }
   
    if ($joursRestants <= 0) {
        return '<span style="color: #e74c3c; font-weight: bold;">❌ Expirée</span>';
    }
   
    if ($joursRestants == 1) {
        return '<span style="color: #e74c3c; font-weight: bold;">⚠️ Expire demain !</span>';
    }
   
    if ($joursRestants <= 3) {
        return '<span style="color: #e74c3c; font-weight: bold;">⚠️ Expire dans ' . $joursRestants . ' jours</span>';
    }
   
    if ($joursRestants <= 7) {
        return '<span style="color: #f39c12;">⏳ Expire dans ' . $joursRestants . ' jours</span>';
    }
   
    return '<span style="color: #27ae60;">✅ ' . $joursRestants . ' jours restants</span>';
}

function afficherBarreChrono($joursRestants) {
    if ($joursRestants === null || $joursRestants <= 0) {
        return '';
    }
   
    // Barre de progression (21 jours = 100%)
    $pourcentage = min(100, ($joursRestants / 21) * 100);
    $couleur = $pourcentage > 60 ? '#27ae60' : ($pourcentage > 30 ? '#f39c12' : '#e74c3c');
   
    return '
        <div style="margin-top:5px; background:#ecf0f1; border-radius:4px; height:6px; overflow:hidden;">
            <div style="width:' . $pourcentage . '%; height:100%; background:' . $couleur . '; border-radius:4px; transition:width 0.5s;"></div>
        </div>
    ';
}