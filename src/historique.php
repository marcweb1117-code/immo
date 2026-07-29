<?php
/**
* 📊 GESTION DE L'HISTORIQUE DES ACTIONS DES ACHETEURS
* Emplacement : src/historique.php
*/

/**
* Enregistre une action d'un acheteur dans l'historique
*
* @param PDO $db Connexion à la base de données
* @param int $acheteurId ID de l'acheteur connecté
* @param int $annonceId ID de l'annonce consultée
* @param string $typeAction Type d'action ('clic', 'favoris', 'vue_longue')
* @return bool True si l'enregistrement a réussi, False sinon
*/
function enregistrerActionAcheteur(PDO $db, $acheteurId, $annonceId, $typeAction = 'clic') {
    // Vérifications de sécurité
    if (!$acheteurId || !$annonceId) {
        return false;
    }
   
    // Types d'actions autorisés
    $typesAutorises = ['clic', 'favoris', 'vue_longue'];
    if (!in_array($typeAction, $typesAutorises)) {
        return false;
    }
   
    try {
        $stmt = $db->prepare("
            INSERT INTO historique_actions_acheteurs (user_id, annonce_id, type_action, date_action)
            VALUES (:user_id, :annonce_id, :type_action, NOW())
        ");
       
        $stmt->execute([
            ':user_id' => $acheteurId,
            ':annonce_id' => $annonceId,
            ':type_action' => $typeAction
        ]);
       
        return true;
       
    } catch (PDOException $e) {
        // On logue l'erreur mais on ne bloque pas l'affichage
        error_log("Erreur enregistrement historique : " . $e->getMessage());
        return false;
    }
}