<?php
/**
* 📡 ENDPOINT API RECOMMANDATION (JSON STRICT)
* Emplacement : src/api_recommandation.php
*/

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../config/db.php';
require __DIR__ . '/recommendation.php';

$db = $GLOBALS['pdo'] ?? $pdo ?? $db ?? null;

$annonceId = filter_input(INPUT_GET, 'annonce_id', FILTER_VALIDATE_INT);
$acheteurId = filter_input(INPUT_GET, 'acheteur_id', FILTER_VALIDATE_INT);

if (!$annonceId || !$acheteurId) {
    http_response_code(400);
    echo json_encode(["erreur" => "Paramètres annonce_id et acheteur_id obligatoires."]);
    exit();
}

$resultat = calculerSMGAnnonceAcheteur($db, $annonceId, $acheteurId);

if (!$resultat) {
    http_response_code(404);
    echo json_encode(["erreur" => "Annonce ou Acheteur non trouvé."]);
    exit();
}

// Retourne le format JSON exactement tel que demandé dans le prompt système
echo json_encode($resultat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);