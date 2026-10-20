<?php
session_start();
// ✅ Chemins corrigés — index.php est dans pages/, backend est un niveau au-dessus
require_once __DIR__ . '/../backend/php/db.php';
require_once __DIR__ . '/../backend/php/annonces.php';

$db = getDB();

// Annonces récentes avec photo principale
$annonces = $db->query('
    SELECT a.*, c.nom AS categorie, c.icone,
           CONCAT(u.prenom," ",u.nom) AS vendeur,
           (SELECT url FROM photos p WHERE p.annonce_id = a.id AND p.ordre = 0 LIMIT 1) AS photo
    FROM annonces a
    JOIN categories c ON a.categorie_id = c.id
    JOIN utilisateurs u ON a.utilisateur_id = u.id
    WHERE a.statut = "active"
    ORDER BY a.created_at DESC
    LIMIT 8
')->fetchAll();

// Catégories avec comptage
$categories = $db->query('
    SELECT c.*, COUNT(a.id) AS nb
    FROM categories c
    LEFT JOIN annonces a ON a.categorie_id = c.id AND a.statut = "active"
    GROUP BY c.id ORDER BY nb DESC
')->fetchAll();

// Stats
$stats = $db->query('
    SELECT
      (SELECT COUNT(*) FROM annonces WHERE statut="active") AS nb_annonces,
      (SELECT COUNT(*) FROM utilisateurs WHERE est_actif=1) AS nb_vendeurs,
      (SELECT COUNT(DISTINCT ville) FROM annonces WHERE statut="active") AS nb_villes
')->fetch();

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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LocalMarket — Achetez & Vendez Près de Chez Vous</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- ✅ Chemins CSS corrigés -->
  <link rel="stylesheet" href="../assets/css/main.css">
  <link rel="stylesheet" href="../assets/css/navbar.css">
  <style>
    /* HERO */
    .hero{background:var(--gris);padding:5rem 2rem;text-align:center;border-bottom:3px solid var(--jaune);}
    .hero h1{font-size:clamp(2.5rem,6vw,5rem);line-height:1;margin-bottom:1rem;}
    .hero h1 span{color:var(--jaune);}
    .hero>p{color:var(--text-muted);font-size:1.1rem;margin-bottom:2.5rem;}
    /* SEARCH */
    .sf{display:flex;max-width:680px;margin:0 auto 2rem;background:var(--gris-clair);border:2px solid var(--gris-clair);border-radius:12px;overflow:hidden;transition:border-color .2s;}
    .sf:focus-within{border-color:var(--jaune);}
    .sf select{width:auto;min-width:155px;background:rgba(255,255,255,.05);border:none;padding:.9rem 1rem;color:var(--blanc);font-family:'Outfit',sans-serif;font-size:.88rem;cursor:pointer;flex-shrink:0;outline:none;}
    .sf .sep{width:1px;background:rgba(255,255,255,.1);margin:.5rem 0;flex-shrink:0;}
    .sf input{flex:1;background:none;border:none;padding:.9rem 1.2rem;color:var(--blanc);font-family:'Outfit',sans-serif;font-size:.95rem;outline:none;min-width:0;border-radius:0;}
    .sf input::placeholder{color:var(--text-muted);}
    .sf button{background:var(--jaune);color:var(--noir);border:none;padding:0 1.5rem;font-weight:700;font-family:'Outfit',sans-serif;cursor:pointer;white-space:nowrap;transition:background .2s;font-size:.95rem;}
    .sf button:hover{background:var(--jaune-clair);}
    /* STATS */
    .stats{background:var(--jaune);padding:1.5rem 2rem;display:flex;justify-content:center;gap:4rem;flex-wrap:wrap;}
    .stat{text-align:center;}
    .stat-n{font-family:'Bebas Neue',sans-serif;font-size:2.2rem;color:var(--noir);display:block;letter-spacing:1px;}
    .stat-l{font-size:.8rem;font-weight:700;color:#333;text-transform:uppercase;letter-spacing:1px;}
    /* CATÉGORIES */
    .cat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:1rem;}
    .cat-card{background:var(--gris);border:2px solid var(--gris-clair);border-radius:12px;padding:1.2rem 1rem;text-align:center;text-decoration:none;color:var(--blanc);transition:border-color .2s,transform .2s;display:block;}
    .cat-card:hover{border-color:var(--jaune);transform:translateY(-3px);}
    .cat-card .ic{font-size:2rem;margin-bottom:.4rem;}
    .cat-card .nm{font-weight:600;font-size:.82rem;line-height:1.3;}
    .cat-card .ct{color:var(--text-muted);font-size:.73rem;margin-top:.2rem;}
    /* GRID ANNONCES */
    .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1.2rem;}
    .card{background:var(--gris);border:2px solid var(--gris-clair);border-radius:12px;overflow:hidden;display:block;color:var(--blanc);text-decoration:none;transition:border-color .2s,transform .2s;}
    .card:hover{border-color:var(--jaune);transform:translateY(-4px);}
    .card-img{width:100%;height:200px;background:var(--gris-clair);overflow:hidden;position:relative;}
    .card-img img{width:100%;height:100%;object-fit:cover;transition:transform .3s;}
    .card:hover .card-img img{transform:scale(1.04);}
    .card-img .no-img{display:flex;align-items:center;justify-content:center;height:100%;font-size:3.5rem;}
    .card-body{padding:1rem;}
    .card-badge{background:var(--jaune);color:var(--noir);font-size:.7rem;font-weight:700;padding:.2rem .6rem;border-radius:4px;display:inline-block;margin-bottom:.5rem;text-transform:uppercase;}
    .card-title{font-weight:600;font-size:.95rem;margin-bottom:.4rem;line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .card-price{font-family:'Bebas Neue',sans-serif;font-size:1.45rem;color:var(--jaune);letter-spacing:1px;}
    .card-price.free{color:#4CAF50;font-size:1.1rem;font-family:'Outfit',sans-serif;font-weight:700;}
    .card-footer{display:flex;justify-content:space-between;margin-top:.7rem;padding-top:.7rem;border-top:1px solid var(--gris-clair);font-size:.75rem;color:var(--text-muted);}
    /* CTA */
    .cta{text-align:center;padding:4rem 2rem;background:var(--gris);border-top:3px solid var(--jaune);margin-top:3rem;}
    .cta h2{font-family:'Bebas Neue',sans-serif;font-size:2.5rem;margin-bottom:1rem;}
    .cta h2 span{color:var(--jaune);}
    .cta p{color:var(--text-muted);margin-bottom:2rem;}
    /* FOOTER */
    footer{background:#080808;border-top:1px solid var(--gris-clair);padding:1.5rem 2rem;text-align:center;color:var(--text-muted);font-size:.82rem;}
    footer a{color:var(--jaune);text-decoration:none;}
    @media(max-width:600px){.stats{gap:2rem;}.grid{grid-template-columns:1fr 1fr;gap:.8rem;}.card-img{height:150px;}}
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <a href="index.php" class="navbar-logo">Local<span>Market</span></a>
  <button class="navbar-toggle" onclick="this.nextElementSibling.classList.toggle('open')">
    <span></span><span></span><span></span>
  </button>
  <ul class="navbar-menu">
    <!-- ✅ Liens corrigés — on est déjà dans pages/ -->
    <li><a href="annonces.php">Annonces</a></li>
    <li><a href="publier.php">+ Publier</a></li>
    <?php if ($connecte): ?>
      <li><a href="mon-compte.php">👤 <?= htmlspecialchars($user_nom) ?></a></li>
      <li><a href="deconnexion.php" class="btn btn-outline">Déconnexion</a></li>
    <?php else: ?>
      <li><a href="connexion.php" class="btn btn-outline">Connexion</a></li>
      <li><a href="inscription.php" class="btn">Inscription</a></li>
    <?php endif; ?>
  </ul>
</nav>

<!-- HERO -->
<section class="hero">
  <?php if (!empty($_GET['nouveau'])): ?>
    <div class="alert alert-success" style="max-width:500px;margin:0 auto 2rem;">
      🎉 Bienvenue <?= htmlspecialchars($user_nom) ?> ! Votre compte est créé.
    </div>
  <?php endif; ?>

  <h1>Achetez & Vendez<br><span>Près de Chez Vous</span></h1>
  <p>La plateforme simple pour trouver de bonnes affaires dans votre quartier.</p>

  <!-- ✅ Action corrigée -->
  <form class="sf" action="annonces.php" method="GET">
    <select name="cat">
      <option value="">Toutes catégories</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>"><?= $c['icone'] ?> <?= htmlspecialchars($c['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="sep"></div>
    <input type="text" name="q" placeholder="Que recherchez-vous ? Ex: iPhone, vélo, maison...">
    <button type="submit">🔍 Rechercher</button>
  </form>

  <a href="publier.php" class="btn btn-lg">+ Publier une annonce gratuite</a>
</section>

<!-- STATS -->
<div class="stats">
  <div class="stat">
    <span class="stat-n"><?= number_format($stats['nb_annonces'] ?: 0, 0, ',', ' ') ?></span>
    <span class="stat-l">Annonces actives</span>
  </div>
  <div class="stat">
    <span class="stat-n"><?= number_format($stats['nb_vendeurs'] ?: 0, 0, ',', ' ') ?></span>
    <span class="stat-l">Vendeurs inscrits</span>
  </div>
  <div class="stat">
    <span class="stat-n"><?= $stats['nb_villes'] ?: 0 ?></span>
    <span class="stat-l">Villes couvertes</span>
  </div>
  <div class="stat">
    <span class="stat-n">100%</span>
    <span class="stat-l">Gratuit</span>
  </div>
</div>

<!-- CATÉGORIES -->
<section class="container" style="padding-top:3rem;">
  <h2 class="section-title">Catégories</h2>
  <div class="cat-grid">
    <?php foreach ($categories as $c): ?>
      <!-- ✅ Lien corrigé -->
      <a href="annonces.php?cat=<?= $c['id'] ?>" class="cat-card">
        <div class="ic"><?= $c['icone'] ?></div>
        <div class="nm"><?= htmlspecialchars($c['nom']) ?></div>
        <div class="ct"><?= number_format($c['nb'], 0, ',', ' ') ?> annonces</div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- ANNONCES RÉCENTES -->
<section class="container" style="padding-top:2.5rem;padding-bottom:1rem;">
  <h2 class="section-title">Annonces Récentes</h2>

  <?php if (empty($annonces)): ?>
    <div class="alert alert-info">
      Aucune annonce pour le moment.
      <a href="publier.php" style="color:var(--jaune);">Publiez la première !</a>
    </div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($annonces as $a): ?>
        <!-- ✅ Lien corrigé -->
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
            <span class="card-badge"><?= htmlspecialchars($a['categorie']) ?></span>
            <div class="card-title"><?= htmlspecialchars($a['titre']) ?></div>
            <?php if ($a['est_gratuit']): ?>
              <div class="card-price free">GRATUIT</div>
            <?php else: ?>
              <div class="card-price"><?= number_format($a['prix'], 0, ',', ' ') ?> FCFA</div>
            <?php endif; ?>
            <div class="card-footer">
              <span>📍 <?= htmlspecialchars($a['ville']) ?></span>
              <span><?= timeAgo($a['created_at']) ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="text-center mt-3" style="padding-bottom:1rem;">
    <a href="annonces.php" class="btn btn-outline">Voir toutes les annonces →</a>
  </div>
</section>

<!-- CTA -->
<section class="cta">
  <h2>Prêt à <span>vendre</span> quelque chose ?</h2>
  <p>Publiez votre première annonce gratuitement en moins de 2 minutes.</p>
  <a href="publier.php" class="btn btn-lg">Publier maintenant — C'est gratuit</a>
</section>

<footer>
  <p style="margin-bottom:.5rem;">
    <strong style="color:var(--jaune);">LocalMarket</strong> — Achetez & Vendez localement
  </p>
  <p><a href="#">À propos</a> · <a href="#">Contact</a> · <a href="#">Conditions</a></p>
  <p style="margin-top:.5rem;">© 2026 LocalMarket. Tous droits réservés.</p>
</footer>

<!-- ✅ Chemin JS corrigé -->
<script src="../assets/js/main.js"></script>
</body>
</html>
