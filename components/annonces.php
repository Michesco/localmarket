<?php
/**
 * LocalMarket — Gestion des annonces (CRUD)
 */

require_once __DIR__ . '/db.php';

// ─────────────────────────────────────────────
// LISTER les annonces avec filtres
// ─────────────────────────────────────────────
function getAnnonces(array $filtres = [], int $page = 1, int $limit = 12): array {
    $db     = getDB();
    $where  = ['a.statut = "active"'];
    $params = [];

    if (!empty($filtres['categorie_id'])) {
        $where[]  = 'a.categorie_id = ?';
        $params[] = $filtres['categorie_id'];
    }
    if (!empty($filtres['ville'])) {
        $where[]  = 'a.ville = ?';
        $params[] = $filtres['ville'];
    }
    if (!empty($filtres['prix_min'])) {
        $where[]  = 'a.prix >= ?';
        $params[] = $filtres['prix_min'];
    }
    if (!empty($filtres['prix_max'])) {
        $where[]  = 'a.prix <= ?';
        $params[] = $filtres['prix_max'];
    }
    if (!empty($filtres['recherche'])) {
        $where[]  = 'MATCH(a.titre, a.description) AGAINST (? IN BOOLEAN MODE)';
        $params[] = $filtres['recherche'] . '*';
    }

    $tri = match($filtres['tri'] ?? 'recent') {
        'prix_asc'  => 'a.prix ASC',
        'prix_desc' => 'a.prix DESC',
        'vues'      => 'a.nb_vues DESC',
        default     => 'a.created_at DESC',
    };

    $offset = ($page - 1) * $limit;
    $sql = '
        SELECT a.*, c.nom AS categorie, c.icone,
               CONCAT(u.prenom," ",u.nom) AS vendeur,
               (SELECT url FROM photos p WHERE p.annonce_id=a.id AND p.ordre=0 LIMIT 1) AS photo
        FROM annonces a
        JOIN categories c ON a.categorie_id = c.id
        JOIN utilisateurs u ON a.utilisateur_id = u.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY ' . $tri . '
        LIMIT ? OFFSET ?
    ';
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ─────────────────────────────────────────────
// DÉTAIL d'une annonce
// ─────────────────────────────────────────────
function getAnnonce(int $id): ?array {
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM v_annonces WHERE id = ?');
    $stmt->execute([$id]);
    $annonce = $stmt->fetch();
    if (!$annonce) return null;

    // Incrémenter les vues
    $db->prepare('UPDATE annonces SET nb_vues = nb_vues + 1 WHERE id = ?')->execute([$id]);

    // Récupérer toutes les photos
    $photos = $db->prepare('SELECT url FROM photos WHERE annonce_id = ? ORDER BY ordre');
    $photos->execute([$id]);
    $annonce['photos'] = $photos->fetchAll(PDO::FETCH_COLUMN);

    return $annonce;
}

// ─────────────────────────────────────────────
// CRÉER une annonce
// ─────────────────────────────────────────────
function creerAnnonce(array $data, int $user_id, array $photos = []): array {
    $db = getDB();

    $stmt = $db->prepare('
        INSERT INTO annonces
          (utilisateur_id, categorie_id, titre, description, prix, est_gratuit, etat, ville, quartier, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 DAY))
    ');
    $stmt->execute([
        $user_id,
        $data['categorie_id'],
        htmlspecialchars($data['titre']),
        htmlspecialchars($data['description']),
        $data['est_gratuit'] ? null : ($data['prix'] ?? null),
        $data['est_gratuit'] ? 1 : 0,
        $data['etat'] ?? 'bon',
        htmlspecialchars($data['ville']),
        htmlspecialchars($data['quartier'] ?? ''),
    ]);

    $annonce_id = $db->lastInsertId();

    // Enregistrer les photos
    foreach ($photos as $i => $url) {
        $db->prepare('INSERT INTO photos (annonce_id, url, ordre) VALUES (?, ?, ?)')
           ->execute([$annonce_id, $url, $i]);
    }

    return ['success' => true, 'annonce_id' => $annonce_id];
}

// ─────────────────────────────────────────────
// MODIFIER une annonce
// ─────────────────────────────────────────────
function modifierAnnonce(int $id, int $user_id, array $data): array {
    $db = getDB();

    // Vérifier que l'annonce appartient à l'utilisateur
    $check = $db->prepare('SELECT id FROM annonces WHERE id = ? AND utilisateur_id = ?');
    $check->execute([$id, $user_id]);
    if (!$check->fetch()) {
        return ['success' => false, 'message' => 'Action non autorisée.'];
    }

    $db->prepare('
        UPDATE annonces
        SET titre=?, description=?, prix=?, est_gratuit=?, etat=?, ville=?, quartier=?
        WHERE id=?
    ')->execute([
        htmlspecialchars($data['titre']),
        htmlspecialchars($data['description']),
        $data['est_gratuit'] ? null : $data['prix'],
        $data['est_gratuit'] ? 1 : 0,
        $data['etat'],
        htmlspecialchars($data['ville']),
        htmlspecialchars($data['quartier'] ?? ''),
        $id
    ]);

    return ['success' => true];
}

// ─────────────────────────────────────────────
// SUPPRIMER une annonce
// ─────────────────────────────────────────────
function supprimerAnnonce(int $id, int $user_id): array {
    $db = getDB();

    $check = $db->prepare('SELECT id FROM annonces WHERE id = ? AND utilisateur_id = ?');
    $check->execute([$id, $user_id]);
    if (!$check->fetch()) {
        return ['success' => false, 'message' => 'Action non autorisée.'];
    }

    $db->prepare('DELETE FROM annonces WHERE id = ?')->execute([$id]);
    return ['success' => true];
}

// ─────────────────────────────────────────────
// MES ANNONCES (tableau de bord)
// ─────────────────────────────────────────────
function getMesAnnonces(int $user_id): array {
    $db   = getDB();
    $stmt = $db->prepare('
        SELECT a.*, c.nom AS categorie,
               (SELECT url FROM photos p WHERE p.annonce_id=a.id AND p.ordre=0 LIMIT 1) AS photo
        FROM annonces a
        JOIN categories c ON a.categorie_id = c.id
        WHERE a.utilisateur_id = ?
        ORDER BY a.created_at DESC
    ');
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
