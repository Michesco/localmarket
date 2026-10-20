<?php
session_start();
require_once '../backend/php/db.php';

$db = getDB();

// ── Paramètres GET ─────────────────────────────────────
$q         = trim($_GET['q']      ?? '');
$cat_id    = (int)($_GET['cat']   ?? 0);
$ville     = trim($_GET['ville']  ?? '');
$prix_min  = (int)($_GET['pmin']  ?? 0);
$prix_max  = (int)($_GET['pmax']  ?? 0);
$etat      = trim($_GET['etat']   ?? '');
$tri       = $_GET['tri']         ?? 'recent';
$page      = max(1, (int)($_GET['page'] ?? 1));
$par_page  = 12;

// ── Construction de la requête ─────────────────────────
$where  = ['a.statut = "active"'];
$params = [];

if ($q) {
    $where[]  = 'MATCH(a.titre, a.description) AGAINST (? IN BOOLEAN MODE)';
    $params[] = $q . '*';
}
if ($cat_id) {
    $where[]  = 'a.categorie_id = ?';
    $params[] = $cat_id;
}
if ($ville) {
    $where[]  = 'a.ville = ?';
    $params[] = $ville;
}
if ($prix_min) {
    $where[]  = 'a.prix >= ?';
    $params[] = $prix_min;
}
if ($prix_max) {
    $where[]  = 'a.prix <= ?';
    $params[] = $prix_max;
}
if ($etat) {
    $where[]  = 'a.etat = ?';
    $params[] = $etat;
}

$order = match($tri) {
    'prix_asc'  => 'a.prix ASC',
    'prix_desc' => 'a.prix DESC',
    'vues'      => 'a.nb_vues DESC',
    default     => 'a.created_at DESC',
};

$where_sql = implode(' AND ', $where);

// Compter le total
$count_stmt = $db->prepare("SELECT COUNT(*) FROM annonces a WHERE $where_sql");
$count_stmt->execute($params);
$total      = (int)$count_stmt->fetchColumn();
$nb_pages   = max(1, ceil($total / $par_page));
$offset     = ($page - 1) * $par_page;

// Récupérer les annonces avec photos de profil des vendeurs
$sql = "
    SELECT a.*, c.nom AS categorie, c.icone,
           CONCAT(u.prenom,' ',u.nom) AS vendeur,
           u.photo_profil AS vendeur_photo,
           (SELECT url FROM photos p WHERE p.annonce_id = a.id AND p.ordre = 0 LIMIT 1) AS photo
    FROM annonces a
    JOIN categories c ON a.categorie_id = c.id
    JOIN utilisateurs u ON a.utilisateur_id = u.id
    WHERE $where_sql
    ORDER BY $order
    LIMIT ? OFFSET ?
";
$stmt = $db->prepare($sql);
$stmt->execute(array_merge($params, [$par_page, $offset]));
$annonces = $stmt->fetchAll();

// Catégories pour le filtre
$categories = $db->query('
    SELECT c.*, COUNT(a.id) AS nb
    FROM categories c
    LEFT JOIN annonces a ON a.categorie_id = c.id AND a.statut = "active"
    GROUP BY c.id ORDER BY nb DESC
')->fetchAll();

// Villes disponibles
$villes_dispo = $db->query('
    SELECT ville, COUNT(*) AS nb FROM annonces
    WHERE statut = "active" GROUP BY ville ORDER BY nb DESC
')->fetchAll();

$connecte = isset($_SESSION['user_id']);
$user_nom = $_SESSION['user_nom'] ?? '';

function timeAgo($d) {
    $s = time() - strtotime($d);
    if ($s < 60)     return 'À l\'instant';
    if ($s < 3600)   return 'il y a '.floor($s/60).' min';
    if ($s < 86400)  return 'il y a '.floor($s/3600).'h';
    if ($s < 604800) return 'il y a '.floor($s/86400).' j';
    return date('d/m/Y', strtotime($d));
}

// URL helper pour conserver les filtres en changeant juste un paramètre
function buildUrl(array $overrides = []): string {
    $params = array_filter(array_merge([
        'q'    => $_GET['q']    ?? '',
        'cat'  => $_GET['cat']  ?? '',
        'ville'=> $_GET['ville']?? '',
        'pmin' => $_GET['pmin'] ?? '',
        'pmax' => $_GET['pmax'] ?? '',
        'etat' => $_GET['etat'] ?? '',
        'tri'  => $_GET['tri']  ?? '',
        'page' => $_GET['page'] ?? '',
    ], $overrides));
    return 'annonces.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $q ? "\"$q\" — " : ($cat_id ? htmlspecialchars($categories[0]['nom'] ?? '').' — ' : '') ?>Annonces — LocalMarket</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/main.css">
  <link rel="stylesheet" href="../assets/css/navbar.css">
  <style>
    .page-header{background:var(--gris);border-bottom:3px solid var(--jaune);padding:1.8rem 2rem;}
    .page-header h1{font-size:2rem;margin-bottom:.8rem;}
    .page-header h1 span{color:var(--jaune);}
    .sf{display:flex;max-width:600px;background:var(--gris-clair);border:2px solid var(--gris-clair);border-radius:10px;overflow:hidden;transition:border-color .2s;}
    .sf:focus-within{border-color:var(--jaune);}
    .sf input{flex:1;background:none;border:none;padding:.85rem 1.2rem;color:var(--blanc);font-family:'Outfit',sans-serif;font-size:.95rem;outline:none;}
    .sf input::placeholder{color:var(--text-muted);}
    .sf button{background:var(--jaune);color:var(--noir);border:none;padding:0 1.3rem;font-weight:700;font-family:'Outfit',sans-serif;cursor:pointer;font-size:.95rem;transition:background .2s;}
    .sf button:hover{background:var(--jaune-clair);}
    /* LAYOUT */
    .layout{display:grid;grid-template-columns:240px 1fr;gap:2rem;max-width:1100px;margin:2rem auto;padding:0 2rem;align-items:start;}
    @media(max-width:700px){.layout{grid-template-columns:1fr;padding:0 1rem;}.filters{display:none;}}
    /* FILTERS */
    .filters{background:var(--gris);border:2px solid var(--gris-clair);border-radius:12px;padding:1.2rem;position:sticky;top:90px;}
    .filter-ttl{font-family:'Bebas Neue',sans-serif;font-size:1.1rem;letter-spacing:1px;color:var(--jaune);margin-bottom:1rem;}
    .filter-grp{margin-bottom:1.4rem;}
    .filter-grp>label{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);display:block;margin-bottom:.6rem;}
    .filter-opt{display:flex;align-items:center;gap:.6rem;padding:.35rem 0;cursor:pointer;font-size:.88rem;}
    .filter-opt input{accent-color:var(--jaune);width:16px;height:16px;flex-shrink:0;}
    .filter-opt .ct{color:var(--text-muted);font-size:.76rem;margin-left:auto;}
    .price-row{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-top:.5rem;}
    .price-row input{padding:.5rem .7rem;font-size:.85rem;}
    .btn-apply{width:100%;margin-top:.5rem;}
    .btn-reset{width:100%;margin-top:.5rem;background:none;border:1px solid var(--gris-clair);color:var(--text-muted);border-radius:8px;padding:.5rem;font-family:'Outfit',sans-serif;font-size:.82rem;cursor:pointer;}
    .btn-reset:hover{border-color:var(--rouge);color:var(--rouge);}
    /* RÉSULTATS */
    .results-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.2rem;flex-wrap:wrap;gap:.8rem;}
    .results-count{font-size:.9rem;color:var(--text-muted);}
    .results-count strong{color:var(--blanc);}
    .sort-sel{background:var(--gris-clair);border:2px solid var(--gris-clair);border-radius:8px;padding:.5rem .8rem;color:var(--blanc);font-family:'Outfit',sans-serif;font-size:.85rem;width:auto;cursor:pointer;outline:none;}
    .sort-sel:focus{border-color:var(--jaune);}
    /* GRILLE */
    .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:1.2rem;}
    .card{background:var(--gris);border:2px solid var(--gris-clair);border-radius:12px;overflow:hidden;display:block;color:var(--blanc);text-decoration:none;transition:border-color .2s,transform .2s;}
    .card:hover{border-color:var(--jaune);transform:translateY(-4px);}
    .card-img{width:100%;height:180px;background:var(--gris-clair);overflow:hidden;}
    .card-img img{width:100%;height:100%;object-fit:cover;transition:transform .3s;}
    .card:hover .card-img img{transform:scale(1.04);}
    .card-img .no-img{display:flex;align-items:center;justify-content:center;height:100%;font-size:3rem;}
    .card-body{padding:1rem;}
    .c-badge{background:var(--jaune);color:var(--noir);font-size:.68rem;font-weight:700;padding:.2rem .6rem;border-radius:4px;display:inline-block;margin-bottom:.5rem;text-transform:uppercase;}
    .c-title{font-weight:600;font-size:.9rem;margin-bottom:.3rem;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
    .c-price{font-family:'Bebas Neue',sans-serif;font-size:1.35rem;color:var(--jaune);letter-spacing:1px;}
    .c-price.free{color:#4CAF50;font-size:1rem;font-family:'Outfit',sans-serif;font-weight:700;}
    .c-footer{display:flex;justify-content:space-between;margin-top:.6rem;padding-top:.6rem;border-top:1px solid var(--gris-clair);font-size:.73rem;color:var(--text-muted;}
    .c-vendor{display:flex;align-items:center;gap:.5rem;margin-top:.5rem;font-size:.75rem;color:var(--text-muted);}
    .vendor-avatar{width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,var(--gris-clair),var(--gris));display:flex;align-items:center;justify-content:center;font-size:.8rem;overflow:hidden;border:1px solid var(--gris-clair);flex-shrink:0;}
    .vendor-avatar img{width:100%;height:100%;object-fit:cover;}
    .user-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--gris-clair),var(--gris));display:flex;align-items:center;justify-content:center;font-size:.9rem;overflow:hidden;border:1px solid var(--gris-clair);}
    .user-avatar img{width:100%;height:100%;object-fit:cover;}
    /* VIDE */
    .empty{text-align:center;padding:4rem 2rem;}
    .empty-ic{font-size:4rem;margin-bottom:1rem;}
    .empty h3{font-family:'Bebas Neue',sans-serif;font-size:1.5rem;margin-bottom:.5rem;}
    /* PAGINATION */
    .pagination{display:flex;justify-content:center;gap:.5rem;margin:2.5rem 0;flex-wrap:wrap;}
    .pgb{min-width:38px;height:38px;background:var(--gris);border:2px solid var(--gris-clair);border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.88rem;font-family:'Outfit',sans-serif;color:var(--blanc);text-decoration:none;transition:all .2s;padding:0 .5rem;}
    .pgb:hover{border-color:var(--jaune);color:var(--jaune);}
    .pgb.active{background:var(--jaune);color:var(--noir);border-color:var(--jaune);font-weight:700;}
    .pgb.disabled{opacity:.4;pointer-events:none;}
    footer{background:#080808;border-top:1px solid var(--gris-clair);padding:1.2rem;text-align:center;color:var(--text-muted);font-size:.8rem;}
    footer a{color:var(--jaune);text-decoration:none;}
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <ul class="navbar-menu">
    <li><a href="annonces.php" style="color:var(--jaune);">Annonces</a></li>
    <li><a href="publier.php">+ Publier</a></li>
    <?php if ($connecte): ?>
      <li>
        <a href="mon-compte.php" style="display:flex;align-items:center;gap:.5rem;">
          <div class="user-avatar">
            <?php
            $stmt = $db->prepare('SELECT photo_profil FROM utilisateurs WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user_photo = $stmt->fetch();
            ?>
            <?php if (!empty($user_photo['photo_profil'])): ?>
              <img src="<?= htmlspecialchars($user_photo['photo_profil']) ?>" alt="">
            <?php else: ?>
              👤
            <?php endif; ?>
          </div>
          <?= htmlspecialchars($user_nom) ?>
        </a>
      </li>
      <li><a href="deconnexion.php" class="btn btn-outline">Déconnexion</a></li>
    <?php else: ?>
      <li><a href="connexion.php" class="btn btn-outline">Connexion</a></li>
      <li><a href="inscription.php" class="btn">Inscription</a></li>
    <?php endif; ?>
  </ul>

  <a href="../index.php" class="navbar-logo">Local<span>Market</span></a>

  <button class="navbar-toggle" onclick="this.previousElementSibling.previousElementSibling.classList.toggle('open')">
    <span></span><span></span><span></span>
  </button>
</nav>

<!-- EN-TÊTE -->
<div class="page-header">
  <div class="container">
    <h1>
      <?php if ($q): ?>
        Résultats pour <span>"<?= htmlspecialchars($q) ?>"</span>
      <?php elseif ($cat_id): ?>
        <?php $cat_active = array_filter($categories, fn($c) => $c['id'] == $cat_id); ?>
        <?php $cat_active = reset($cat_active); ?>
        <?= $cat_active['icone'] ?? '' ?> <span><?= htmlspecialchars($cat_active['nom'] ?? 'Catégorie') ?></span>
      <?php else: ?>
        Toutes les <span>annonces</span>
      <?php endif; ?>
    </h1>
    <form class="sf" action="annonces.php" method="GET">
      <?php if ($cat_id): ?>
        <input type="hidden" name="cat" value="<?= $cat_id ?>">
      <?php endif; ?>
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
             placeholder="Rechercher dans les annonces...">
      <button type="submit">🔍</button>
    </form>
  </div>
</div>

<!-- LAYOUT PRINCIPAL -->
<div class="layout">

  <!-- FILTRES -->
  <aside class="filters">
    <div class="filter-ttl">🎛 Filtres</div>
    <form method="GET" action="annonces.php">
      <?php if ($q): ?>
        <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
      <?php endif; ?>

      <!-- Catégories -->
      <div class="filter-grp">
        <label>Catégorie</label>
        <label class="filter-opt">
          <input type="radio" name="cat" value=""
                 <?= !$cat_id ? 'checked' : '' ?>> Toutes
          <span class="ct"><?= $total ?></span>
        </label>
        <?php foreach ($categories as $c): ?>
          <label class="filter-opt">
            <input type="radio" name="cat" value="<?= $c['id'] ?>"
                   <?= $cat_id == $c['id'] ? 'checked' : '' ?>>
            <?= $c['icone'] ?> <?= htmlspecialchars($c['nom']) ?>
            <span class="ct"><?= $c['nb'] ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <!-- Prix -->
      <div class="filter-grp">
        <label>Prix (FCFA)</label>
        <div class="price-row">
          <input type="number" name="pmin" placeholder="Min"
                 value="<?= $prix_min ?: '' ?>">
          <input type="number" name="pmax" placeholder="Max"
                 value="<?= $prix_max ?: '' ?>">
        </div>
      </div>

      <!-- État -->
      <div class="filter-grp">
        <label>État</label>
        <?php foreach ([''=>'Tous', 'neuf'=>'Neuf', 'tres_bon'=>'Très bon état', 'bon'=>'Bon état', 'correct'=>'Correct'] as $val => $lbl): ?>
          <label class="filter-opt">
            <input type="radio" name="etat" value="<?= $val ?>"
                   <?= $etat === $val ? 'checked' : '' ?>>
            <?= $lbl ?>
          </label>
        <?php endforeach; ?>
      </div>

      <!-- Ville -->
      <div class="filter-grp">
        <label>Ville</label>
        <select name="ville" style="padding:.5rem .7rem;font-size:.85rem;">
          <option value="">Toutes les villes</option>
          <?php foreach ($villes_dispo as $v): ?>
            <option value="<?= htmlspecialchars($v['ville']) ?>"
              <?= $ville === $v['ville'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($v['ville']) ?> (<?= $v['nb'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="btn btn-apply">Appliquer</button>
      <a href="annonces.php" class="btn-reset">✕ Réinitialiser</a>
    </form>
  </aside>

  <!-- ANNONCES -->
  <div>
    <div class="results-top">
      <div class="results-count">
        <strong><?= number_format($total, 0, ',', ' ') ?></strong>
        annonce<?= $total > 1 ? 's' : '' ?> trouvée<?= $total > 1 ? 's' : '' ?>
      </div>
      <form method="GET" action="annonces.php" id="sort-form">
        <?php foreach ($_GET as $k => $v): if ($k !== 'tri'): ?>
          <input type="hidden" name="<?= htmlspecialchars($k) ?>"
                 value="<?= htmlspecialchars($v) ?>">
        <?php endif; endforeach; ?>
        <select class="sort-sel" name="tri"
                onchange="document.getElementById('sort-form').submit()">
          <option value="recent"    <?= $tri==='recent'    ? 'selected':'' ?>>📅 Plus récentes</option>
          <option value="prix_asc"  <?= $tri==='prix_asc'  ? 'selected':'' ?>>💰 Prix croissant</option>
          <option value="prix_desc" <?= $tri==='prix_desc' ? 'selected':'' ?>>💰 Prix décroissant</option>
          <option value="vues"      <?= $tri==='vues'      ? 'selected':'' ?>>👁 Plus vues</option>
        </select>
      </form>
    </div>

    <?php if (empty($annonces)): ?>
      <div class="empty">
        <div class="empty-ic">🔍</div>
        <h3>Aucune annonce trouvée</h3>
        <p>Essayez d'autres mots-clés ou élargissez vos filtres.</p>
        <a href="annonces.php" class="btn mt-2">Voir toutes les annonces</a>
      </div>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($annonces as $a): ?>
          <a href="annonce-detail.php?id=<?= $a['id'] ?>" class="card">
            <div class="card-img" style="background:linear-gradient(135deg,var(--gris-clair),var(--gris));">
              <?php if (!empty($a['photo'])): ?>
                <img src="<?= htmlspecialchars($a['photo']) ?>"
                     alt="<?= htmlspecialchars($a['titre']) ?>"
                     loading="lazy">
              <?php else: ?>
                <div class="no-img" style="opacity:0.6;"><?= $a['icone'] ?? '📦' ?></div>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <span class="c-badge"><?= htmlspecialchars($a['categorie']) ?></span>
              <div class="c-title"><?= htmlspecialchars($a['titre']) ?></div>
              <?php if ($a['est_gratuit']): ?>
                <div class="c-price free">GRATUIT</div>
              <?php else: ?>
                <div class="c-price"><?= number_format($a['prix'], 0, ',', ' ') ?> FCFA</div>
              <?php endif; ?>
              <div class="c-vendor">
                <div class="vendor-avatar">
                  <?php if (!empty($a['vendeur_photo'])): ?>
                    <img src="<?= htmlspecialchars($a['vendeur_photo']) ?>" alt="">
                  <?php else: ?>
                    👤
                  <?php endif; ?>
                </div>
                <?= htmlspecialchars($a['vendeur']) ?>
              </div>
              <div class="c-footer">
                <span> <?= htmlspecialchars($a['ville']) ?></span>
                <span><?= timeAgo($a['created_at']) ?></span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- PAGINATION -->
      <?php if ($nb_pages > 1): ?>
        <div class="pagination">
          <a href="<?= buildUrl(['page' => $page - 1]) ?>"
             class="pgb <?= $page <= 1 ? 'disabled' : '' ?>">←</a>

          <?php for ($p = max(1, $page - 2); $p <= min($nb_pages, $page + 2); $p++): ?>
            <a href="<?= buildUrl(['page' => $p]) ?>"
               class="pgb <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
          <?php endfor; ?>

          <a href="<?= buildUrl(['page' => $page + 1]) ?>"
             class="pgb <?= $page >= $nb_pages ? 'disabled' : '' ?>">→</a>
        </div>
        <p class="text-center" style="color:var(--text-muted);font-size:.82rem;margin-bottom:1rem;">
          Page <?= $page ?> sur <?= $nb_pages ?>
        </p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</div>

<footer>
  © 2025 <a href="../index.php">LocalMarket</a> · Tous droits réservés
</footer>

<script src="../assets/js/main.js"></script>
</body>
</html>
