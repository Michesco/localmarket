<?php
/**
 * LocalMarket — admin.php
 * Fichier à placer dans : pages/admin.php
 */
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// ── Protection admin ──────────────────────────────
if (!estConnecte()) {
    header('Location: connexion.php?redirect=admin.php');
    exit;
}

$db     = getDB();
$userId = $_SESSION['user_id'];

// Vérifier que l'utilisateur est admin (email ou colonne is_admin)
$adminUser = $db->query('SELECT * FROM utilisateurs WHERE id = ' . intval($userId))->fetch();

if (!$adminUser || empty($adminUser['is_admin'])) {
    http_response_code(403);
    die('<h1 style="font-family:sans-serif;text-align:center;margin-top:5rem;color:#FF4D4D;">⛔ Accès refusé — Réservé aux administrateurs</h1>');
}


$onglet  = $_GET['onglet'] ?? 'dashboard';
$succes  = '';
$erreur  = '';

// ── Traitements POST ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Supprimer utilisateur
    if ($action === 'supprimer_user') {
        $id = intval($_POST['user_id']);
        if ($id !== $userId) {
            $db->prepare('DELETE FROM utilisateurs WHERE id=?')->execute([$id]);
            $succes = '✅ Utilisateur supprimé.';
        } else {
            $erreur = '❌ Vous ne pouvez pas supprimer votre propre compte.';
        }
    }

    // Activer/désactiver utilisateur
    if ($action === 'toggle_user') {
        $id  = intval($_POST['user_id']);
        $val = intval($_POST['est_actif']) === 1 ? 0 : 1;
        $db->prepare('UPDATE utilisateurs SET est_actif=? WHERE id=?')->execute([$val, $id]);
        $succes = '✅ Statut utilisateur mis à jour.';
    }

    // Supprimer annonce
    if ($action === 'supprimer_annonce') {
        $id = intval($_POST['annonce_id']);
        $db->prepare('DELETE FROM annonces WHERE id=?')->execute([$id]);
        $succes = '✅ Annonce supprimée.';
    }

    // Changer statut annonce
    if ($action === 'statut_annonce') {
        $id     = intval($_POST['annonce_id']);
        $statut = $_POST['statut'];
        $allowed = ['active','vendue','expiree','suspendue'];
        if (in_array($statut, $allowed)) {
            $db->prepare('UPDATE annonces SET statut=? WHERE id=?')->execute([$statut, $id]);
            $succes = '✅ Statut annonce mis à jour.';
        }
    }

    // Ajouter catégorie
    if ($action === 'ajouter_cat') {
        $nom   = trim($_POST['cat_nom']   ?? '');
        $slug  = trim($_POST['cat_slug']  ?? '');
        $icone = trim($_POST['cat_icone'] ?? '📦');
        if ($nom && $slug) {
            $db->prepare('INSERT INTO categories (nom, slug, icone) VALUES (?,?,?)')->execute([$nom, $slug, $icone]);
            $succes = '✅ Catégorie ajoutée.';
        } else {
            $erreur = '⚠️ Nom et slug requis.';
        }
    }

    // Supprimer catégorie
    if ($action === 'supprimer_cat') {
        $id = intval($_POST['cat_id']);
        $db->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
        $succes = '✅ Catégorie supprimée.';
    }

    // Supprimer message signalé
    if ($action === 'supprimer_message') {
        $id = intval($_POST['message_id']);
        $db->prepare('DELETE FROM messages WHERE id=?')->execute([$id]);
        $succes = '✅ Message supprimé.';
    }
}

// ── Données Dashboard ─────────────────────────────
$stats = $db->query('
    SELECT
      (SELECT COUNT(*) FROM utilisateurs)                        AS total_users,
      (SELECT COUNT(*) FROM utilisateurs WHERE est_actif=1)      AS users_actifs,
      (SELECT COUNT(*) FROM annonces)                            AS total_annonces,
      (SELECT COUNT(*) FROM annonces WHERE statut="active")      AS annonces_actives,
      (SELECT COUNT(*) FROM annonces WHERE statut="suspendue")   AS annonces_suspendues,
      (SELECT COUNT(*) FROM messages)                            AS total_messages,
      (SELECT COUNT(*) FROM categories)                          AS total_categories,
      (SELECT COUNT(*) FROM favoris)                             AS total_favoris
')->fetch();

// Annonces par catégorie (pour graphique)
$statsCat = $db->query('
    SELECT c.nom, c.icone, COUNT(a.id) AS nb
    FROM categories c
    LEFT JOIN annonces a ON a.categorie_id = c.id AND a.statut="active"
    GROUP BY c.id ORDER BY nb DESC
')->fetchAll();

// Inscriptions par mois (6 derniers mois)
$statsInscriptions = $db->query('
    SELECT DATE_FORMAT(created_at, "%b %Y") AS mois,
           COUNT(*) AS nb
    FROM utilisateurs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, "%Y-%m")
    ORDER BY created_at ASC
')->fetchAll();

// Annonces par mois
$statsAnnoncesParMois = $db->query('
    SELECT DATE_FORMAT(created_at, "%b %Y") AS mois,
           COUNT(*) AS nb
    FROM annonces
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, "%Y-%m")
    ORDER BY created_at ASC
')->fetchAll();

// ── Données Utilisateurs ──────────────────────────
$utilisateurs = $db->query('
    SELECT u.*,
           COUNT(a.id) AS nb_annonces
    FROM utilisateurs u
    LEFT JOIN annonces a ON a.utilisateur_id = u.id
    GROUP BY u.id
    ORDER BY u.created_at DESC
')->fetchAll();

// ── Données Annonces ──────────────────────────────
$annonces = $db->query('
    SELECT a.*, c.nom AS categorie, c.icone,
           CONCAT(u.prenom," ",u.nom) AS vendeur,
           (SELECT url FROM photos p WHERE p.annonce_id=a.id AND p.ordre=0 LIMIT 1) AS photo
    FROM annonces a
    JOIN categories c ON a.categorie_id=c.id
    JOIN utilisateurs u ON a.utilisateur_id=u.id
    ORDER BY a.created_at DESC
')->fetchAll();

// ── Données Catégories ────────────────────────────
$categories = $db->query('
    SELECT c.*, COUNT(a.id) AS nb_annonces
    FROM categories c
    LEFT JOIN annonces a ON a.categorie_id=c.id
    GROUP BY c.id ORDER BY c.id ASC
')->fetchAll();

// ── Messages (tous) ───────────────────────────────
$messages = $db->query('
    SELECT m.*,
           CONCAT(e.prenom," ",e.nom) AS expediteur,
           CONCAT(d.prenom," ",d.nom) AS destinataire,
           a.titre AS annonce_titre
    FROM messages m
    JOIN utilisateurs e ON m.expediteur_id=e.id
    JOIN utilisateurs d ON m.destinataire_id=d.id
    JOIN annonces a ON m.annonce_id=a.id
    ORDER BY m.created_at DESC
    LIMIT 50
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administration — LocalMarket</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <style>
    :root{--jaune:#FFD600;--jaune-clair:#FFF176;--noir:#0D0D0D;--gris:#1A1A1A;--gris-clair:#2A2A2A;--blanc:#FAFAFA;--text-muted:#888;--vert:#4CAF50;--rouge:#FF4D4D;--bleu:#4A90D9;}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Outfit',sans-serif;background:var(--noir);color:var(--blanc);min-height:100vh;display:flex;flex-direction:column;}

    /* TOPBAR */
    .topbar{background:#080808;border-bottom:3px solid var(--jaune);padding:0 2rem;display:flex;align-items:center;justify-content:space-between;height:65px;position:sticky;top:0;z-index:100;}
    .topbar-logo{font-family:'Bebas Neue',sans-serif;font-size:1.6rem;color:var(--jaune);letter-spacing:2px;text-decoration:none;}
    .topbar-logo span{color:var(--blanc);}
    .topbar-right{display:flex;align-items:center;gap:1rem;font-size:.88rem;color:var(--text-muted);}
    .topbar-right a{color:var(--jaune);text-decoration:none;font-weight:600;}

    /* LAYOUT */
    .admin-layout{display:flex;flex:1;}

    /* SIDEBAR */
    .sidebar{width:240px;background:#080808;border-right:1px solid var(--gris-clair);padding:1.5rem 0;position:sticky;top:65px;height:calc(100vh - 65px);overflow-y:auto;flex-shrink:0;}
    .sidebar-section{padding:.5rem 1.5rem;font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-top:1rem;}
    .sidebar a{display:flex;align-items:center;gap:.8rem;padding:.8rem 1.5rem;color:var(--blanc);text-decoration:none;font-size:.88rem;font-weight:500;transition:background .2s,color .2s;border-left:3px solid transparent;}
    .sidebar a:hover{background:var(--gris);color:var(--jaune);}
    .sidebar a.active{background:var(--gris);color:var(--jaune);border-left-color:var(--jaune);}
    .sidebar .badge{background:var(--rouge);color:#fff;font-size:.65rem;font-weight:700;padding:.1rem .4rem;border-radius:10px;margin-left:auto;}

    /* MAIN */
    .admin-main{flex:1;padding:2rem;overflow-x:hidden;}

    /* ALERTES */
    .alert{padding:.9rem 1rem;border-radius:8px;margin-bottom:1.5rem;font-size:.9rem;}
    .alert-error{background:rgba(255,77,77,.15);border:1px solid var(--rouge);color:#ff8080;}
    .alert-success{background:rgba(76,175,80,.15);border:1px solid var(--vert);color:#80c883;}

    /* PAGE TITLE */
    .page-title{font-family:'Bebas Neue',sans-serif;font-size:2rem;letter-spacing:2px;color:var(--jaune);margin-bottom:1.5rem;}

    /* STAT CARDS */
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;margin-bottom:2rem;}
    .stat-card{background:var(--gris);border:2px solid var(--gris-clair);border-radius:12px;padding:1.2rem 1.5rem;display:flex;align-items:center;gap:1rem;transition:border-color .2s;}
    .stat-card:hover{border-color:var(--jaune);}
    .stat-icon{font-size:2.2rem;flex-shrink:0;}
    .stat-num{font-family:'Bebas Neue',sans-serif;font-size:2rem;color:var(--jaune);line-height:1;}
    .stat-label{font-size:.78rem;color:var(--text-muted);margin-top:.2rem;}

    /* CHARTS */
    .charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:2rem;}
    @media(max-width:900px){.charts-grid{grid-template-columns:1fr;}}
    .chart-card{background:var(--gris);border:2px solid var(--gris-clair);border-radius:12px;padding:1.5rem;}
    .chart-card h3{font-size:1rem;font-weight:600;margin-bottom:1rem;color:var(--blanc);}

    /* TABLES */
    .table-card{background:var(--gris);border:2px solid var(--gris-clair);border-radius:12px;overflow:hidden;margin-bottom:1.5rem;}
    .table-header{padding:1rem 1.5rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--gris-clair);}
    .table-header h3{font-size:1rem;font-weight:700;}
    .table-search{background:var(--gris-clair);border:1px solid var(--gris-clair);border-radius:8px;padding:.5rem .9rem;color:var(--blanc);font-family:'Outfit',sans-serif;font-size:.85rem;outline:none;width:220px;}
    .table-search:focus{border-color:var(--jaune);}
    table{width:100%;border-collapse:collapse;}
    th{background:var(--gris-clair);padding:.7rem 1rem;text-align:left;font-size:.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;}
    td{padding:.8rem 1rem;font-size:.85rem;border-bottom:1px solid var(--gris-clair);vertical-align:middle;}
    tr:last-child td{border-bottom:none;}
    tr:hover td{background:rgba(255,255,255,.02);}
    .td-truncate{max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

    /* BADGES */
    .badge-status{display:inline-block;font-size:.7rem;font-weight:700;padding:.2rem .6rem;border-radius:4px;text-transform:uppercase;}
    .badge-active{background:rgba(76,175,80,.2);color:var(--vert);}
    .badge-inactive{background:rgba(255,77,77,.2);color:var(--rouge);}
    .badge-vendue{background:rgba(255,214,0,.2);color:var(--jaune);}
    .badge-suspendue{background:rgba(255,77,77,.2);color:var(--rouge);}
    .badge-expiree{background:rgba(136,136,136,.2);color:var(--text-muted);}

    /* BOUTONS */
    .btn{display:inline-block;background:var(--jaune);color:var(--noir);padding:.5rem 1rem;border-radius:6px;font-weight:700;font-family:'Outfit',sans-serif;font-size:.82rem;border:none;cursor:pointer;text-decoration:none;transition:background .2s;}
    .btn:hover{background:var(--jaune-clair);}
    .btn-sm{padding:.3rem .7rem;font-size:.75rem;}
    .btn-danger{background:rgba(255,77,77,.15);color:var(--rouge);border:1px solid transparent;}
    .btn-danger:hover{background:var(--rouge);color:#fff;}
    .btn-outline{background:none;border:1px solid var(--gris-clair);color:var(--blanc);}
    .btn-outline:hover{border-color:var(--jaune);color:var(--jaune);}
    .btn-success{background:rgba(76,175,80,.15);color:var(--vert);border:1px solid transparent;}
    .btn-success:hover{background:var(--vert);color:#fff;}

    /* AVATAR */
    .user-avatar{width:36px;height:36px;border-radius:50%;background:var(--gris-clair);display:inline-flex;align-items:center;justify-content:center;font-size:1.1rem;margin-right:.5rem;vertical-align:middle;}

    /* FORM ADD CAT */
    .add-form{background:var(--gris-clair);border-radius:10px;padding:1.2rem;margin-bottom:1.2rem;display:grid;grid-template-columns:1fr 1fr 80px auto;gap:.8rem;align-items:end;}
    .add-form input{background:var(--gris);border:2px solid var(--gris);border-radius:8px;padding:.6rem .9rem;color:var(--blanc);font-family:'Outfit',sans-serif;font-size:.88rem;outline:none;}
    .add-form input:focus{border-color:var(--jaune);}
    .add-form label{display:block;font-size:.75rem;color:var(--text-muted);margin-bottom:.3rem;font-weight:600;}
    @media(max-width:700px){.add-form{grid-template-columns:1fr 1fr;}}

    /* MSG CARD */
    .msg-card{background:var(--gris-clair);border-radius:10px;padding:1rem;margin-bottom:.8rem;display:flex;gap:1rem;align-items:flex-start;}
    .msg-meta{font-size:.78rem;color:var(--text-muted);margin-bottom:.4rem;}
    .msg-content{font-size:.88rem;color:var(--blanc);line-height:1.5;flex:1;}

    /* SELECT */
    select.inline{background:var(--gris-clair);border:1px solid var(--gris-clair);border-radius:6px;padding:.3rem .6rem;color:var(--blanc);font-family:'Outfit',sans-serif;font-size:.78rem;outline:none;cursor:pointer;}
    select.inline:focus{border-color:var(--jaune);}

    /* ANNONCE MINI */
    .annonce-mini{display:flex;align-items:center;gap:.7rem;}
    .annonce-mini-img{width:40px;height:40px;border-radius:6px;background:var(--gris-clair);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.2rem;}
    .annonce-mini-img img{width:100%;height:100%;object-fit:cover;}

    footer{background:#080808;border-top:1px solid var(--gris-clair);padding:1rem;text-align:center;color:var(--text-muted);font-size:.78rem;}
    footer a{color:var(--jaune);text-decoration:none;}
  </style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
  <a href="index.php" class="topbar-logo">Local<span>Market</span> <span style="font-size:1rem;color:var(--rouge);margin-left:.5rem;">ADMIN</span></a>
  <div class="topbar-right">
    <span>👤 <?= htmlspecialchars($adminUser['prenom']) ?></span>
    <a href="mon-compte.php">Mon compte</a>
    <a href="deconnexion.php" style="color:var(--rouge);">Déconnexion</a>
  </div>
</header>

<div class="admin-layout">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-section">Principal</div>
    <a href="?onglet=dashboard"    class="<?= $onglet==='dashboard'    ? 'active':'' ?>">📊 Tableau de bord</a>
    <a href="?onglet=statistiques" class="<?= $onglet==='statistiques' ? 'active':'' ?>">📈 Statistiques</a>

    <div class="sidebar-section">Contenu</div>
    <a href="?onglet=annonces"     class="<?= $onglet==='annonces'     ? 'active':'' ?>">
      📋 Annonces
      <?php if ($stats['annonces_suspendues'] > 0): ?>
        <span class="badge"><?= $stats['annonces_suspendues'] ?></span>
      <?php endif; ?>
    </a>
    <a href="?onglet=categories"   class="<?= $onglet==='categories'   ? 'active':'' ?>">🗂️ Catégories</a>
    <a href="?onglet=messages"     class="<?= $onglet==='messages'     ? 'active':'' ?>">💬 Messages</a>

    <div class="sidebar-section">Membres</div>
    <a href="?onglet=utilisateurs" class="<?= $onglet==='utilisateurs' ? 'active':'' ?>">👥 Utilisateurs</a>

    <div class="sidebar-section">Site</div>
    <a href="index.php">🌐 Voir le site</a>
  </aside>

  <!-- MAIN -->
  <main class="admin-main">

    <?php if ($succes): ?><div class="alert alert-success"><?= $succes ?></div><?php endif; ?>
    <?php if ($erreur): ?><div class="alert alert-error"><?= $erreur ?></div><?php endif; ?>

    <!-- ══════════════════════════════════════════ -->
    <!-- DASHBOARD                                  -->
    <!-- ══════════════════════════════════════════ -->
    <?php if ($onglet === 'dashboard'): ?>

    <div class="page-title">📊 Tableau de Bord</div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div>
          <div class="stat-num"><?= $stats['total_users'] ?></div>
          <div class="stat-label">Utilisateurs total</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div>
          <div class="stat-num"><?= $stats['users_actifs'] ?></div>
          <div class="stat-label">Comptes actifs</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📋</div>
        <div>
          <div class="stat-num"><?= $stats['total_annonces'] ?></div>
          <div class="stat-label">Annonces total</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">🟢</div>
        <div>
          <div class="stat-num"><?= $stats['annonces_actives'] ?></div>
          <div class="stat-label">Annonces actives</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">🚫</div>
        <div>
          <div class="stat-num" style="color:var(--rouge)"><?= $stats['annonces_suspendues'] ?></div>
          <div class="stat-label">Annonces suspendues</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">💬</div>
        <div>
          <div class="stat-num"><?= $stats['total_messages'] ?></div>
          <div class="stat-label">Messages échangés</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">🗂️</div>
        <div>
          <div class="stat-num"><?= $stats['total_categories'] ?></div>
          <div class="stat-label">Catégories</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">❤️</div>
        <div>
          <div class="stat-num"><?= $stats['total_favoris'] ?></div>
          <div class="stat-label">Favoris ajoutés</div>
        </div>
      </div>
    </div>

    <!-- Derniers utilisateurs -->
    <div class="table-card">
      <div class="table-header"><h3>👥 Derniers inscrits</h3><a href="?onglet=utilisateurs" class="btn btn-sm">Voir tous</a></div>
      <table>
        <thead><tr><th>Utilisateur</th><th>Email</th><th>Ville</th><th>Inscrit le</th><th>Statut</th></tr></thead>
        <tbody>
          <?php foreach (array_slice($utilisateurs, 0, 5) as $u): ?>
          <tr>
            <td><span class="user-avatar">👤</span><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></td>
            <td style="color:var(--text-muted)"><?= htmlspecialchars($u['email']) ?></td>
            <td>📍 <?= htmlspecialchars($u['ville']) ?></td>
            <td style="color:var(--text-muted)"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
            <td><span class="badge-status <?= $u['est_actif'] ? 'badge-active':'badge-inactive' ?>"><?= $u['est_actif'] ? 'Actif':'Inactif' ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Dernières annonces -->
    <div class="table-card">
      <div class="table-header"><h3>📋 Dernières annonces</h3><a href="?onglet=annonces" class="btn btn-sm">Voir toutes</a></div>
      <table>
        <thead><tr><th>Annonce</th><th>Vendeur</th><th>Prix</th><th>Statut</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach (array_slice($annonces, 0, 5) as $a): ?>
          <tr>
            <td>
              <div class="annonce-mini">
                <div class="annonce-mini-img">
                  <?php if (!empty($a['photo'])): ?><img src="<?= htmlspecialchars($a['photo']) ?>" alt=""><?php else: ?><?= $a['icone'] ?><?php endif; ?>
                </div>
                <span class="td-truncate"><?= htmlspecialchars($a['titre']) ?></span>
              </div>
            </td>
            <td><?= htmlspecialchars($a['vendeur']) ?></td>
            <td style="color:var(--jaune);font-weight:700"><?= $a['est_gratuit'] ? 'Gratuit' : number_format($a['prix'],0,',',' ').' FCFA' ?></td>
            <td><span class="badge-status badge-<?= $a['statut'] ?>"><?= ucfirst($a['statut']) ?></span></td>
            <td style="color:var(--text-muted)"><?= date('d/m/Y', strtotime($a['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- ══════════════════════════════════════════ -->
    <!-- STATISTIQUES                               -->
    <!-- ══════════════════════════════════════════ -->
    <?php elseif ($onglet === 'statistiques'): ?>

    <div class="page-title">📈 Statistiques</div>

    <div class="charts-grid">
      <!-- Inscriptions par mois -->
      <div class="chart-card">
        <h3>👥 Inscriptions (6 derniers mois)</h3>
        <canvas id="chartInscriptions" height="200"></canvas>
      </div>
      <!-- Annonces par mois -->
      <div class="chart-card">
        <h3>📋 Annonces publiées (6 derniers mois)</h3>
        <canvas id="chartAnnonces" height="200"></canvas>
      </div>
      <!-- Annonces par catégorie -->
      <div class="chart-card">
        <h3>🗂️ Annonces par catégorie</h3>
        <canvas id="chartCategories" height="200"></canvas>
      </div>
      <!-- Répartition statuts -->
      <div class="chart-card">
        <h3>📊 Répartition des statuts</h3>
        <canvas id="chartStatuts" height="200"></canvas>
      </div>
    </div>

    <script>
    Chart.defaults.color = '#888';
    Chart.defaults.borderColor = '#2A2A2A';

    // Inscriptions
    new Chart(document.getElementById('chartInscriptions'), {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_column($statsInscriptions, 'mois')) ?>,
        datasets: [{
          label: 'Inscriptions',
          data: <?= json_encode(array_column($statsInscriptions, 'nb')) ?>,
          backgroundColor: 'rgba(255,214,0,0.7)',
          borderColor: '#FFD600',
          borderWidth: 2, borderRadius: 6
        }]
      },
      options: { responsive: true, plugins: { legend: { display: false } } }
    });

    // Annonces par mois
    new Chart(document.getElementById('chartAnnonces'), {
      type: 'line',
      data: {
        labels: <?= json_encode(array_column($statsAnnoncesParMois, 'mois')) ?>,
        datasets: [{
          label: 'Annonces',
          data: <?= json_encode(array_column($statsAnnoncesParMois, 'nb')) ?>,
          borderColor: '#FFD600', backgroundColor: 'rgba(255,214,0,0.1)',
          borderWidth: 2, fill: true, tension: 0.4,
          pointBackgroundColor: '#FFD600'
        }]
      },
      options: { responsive: true, plugins: { legend: { display: false } } }
    });

    // Par catégorie
    new Chart(document.getElementById('chartCategories'), {
      type: 'doughnut',
      data: {
        labels: <?= json_encode(array_column($statsCat, 'nom')) ?>,
        datasets: [{
          data: <?= json_encode(array_column($statsCat, 'nb')) ?>,
          backgroundColor: ['#FFD600','#4CAF50','#4A90D9','#FF4D4D','#FF9800','#9C27B0','#00BCD4','#795548','#607D8B','#E91E63','#FF5722'],
          borderWidth: 2, borderColor: '#1A1A1A'
        }]
      },
      options: { responsive: true, plugins: { legend: { position: 'right', labels: { font: { size: 11 } } } } }
    });

    // Statuts
    new Chart(document.getElementById('chartStatuts'), {
      type: 'pie',
      data: {
        labels: ['Actives','Vendues','Expirées','Suspendues'],
        datasets: [{
          data: [
            <?= $db->query('SELECT COUNT(*) FROM annonces WHERE statut="active"')->fetchColumn() ?>,
            <?= $db->query('SELECT COUNT(*) FROM annonces WHERE statut="vendue"')->fetchColumn() ?>,
            <?= $db->query('SELECT COUNT(*) FROM annonces WHERE statut="expiree"')->fetchColumn() ?>,
            <?= $db->query('SELECT COUNT(*) FROM annonces WHERE statut="suspendue"')->fetchColumn() ?>
          ],
          backgroundColor: ['#4CAF50','#FFD600','#888','#FF4D4D'],
          borderWidth: 2, borderColor: '#1A1A1A'
        }]
      },
      options: { responsive: true, plugins: { legend: { position: 'right' } } }
    });
    </script>

    <!-- ══════════════════════════════════════════ -->
    <!-- UTILISATEURS                               -->
    <!-- ══════════════════════════════════════════ -->
    <?php elseif ($onglet === 'utilisateurs'): ?>

    <div class="page-title">👥 Gestion des Utilisateurs</div>

    <div class="table-card">
      <div class="table-header">
        <h3>👥 <?= count($utilisateurs) ?> utilisateurs</h3>
        <input type="text" class="table-search" placeholder="🔍 Rechercher..." oninput="filtrerTable(this,'table-users')">
      </div>
      <table id="table-users">
        <thead>
          <tr><th>Utilisateur</th><th>Email</th><th>Ville</th><th>Annonces</th><th>Inscrit le</th><th>Statut</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($utilisateurs as $u): ?>
          <tr>
            <td><span class="user-avatar">👤</span><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></td>
            <td style="color:var(--text-muted);font-size:.82rem"><?= htmlspecialchars($u['email']) ?></td>
            <td>📍 <?= htmlspecialchars($u['ville']) ?></td>
            <td style="text-align:center"><strong><?= $u['nb_annonces'] ?></strong></td>
            <td style="color:var(--text-muted)"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
            <td><span class="badge-status <?= $u['est_actif'] ? 'badge-active':'badge-inactive' ?>"><?= $u['est_actif'] ? 'Actif':'Inactif' ?></span></td>
            <td>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="toggle_user">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <input type="hidden" name="est_actif" value="<?= $u['est_actif'] ?>">
                <button class="btn btn-sm <?= $u['est_actif'] ? 'btn-outline':'btn-success' ?>" title="<?= $u['est_actif'] ? 'Désactiver':'Activer' ?>">
                  <?= $u['est_actif'] ? '🚫':'✅' ?>
                </button>
              </form>
              <?php if ($u['id'] !== $userId): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cet utilisateur ?')">
                <input type="hidden" name="action" value="supprimer_user">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn btn-sm btn-danger">🗑️</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- ══════════════════════════════════════════ -->
    <!-- ANNONCES                                   -->
    <!-- ══════════════════════════════════════════ -->
    <?php elseif ($onglet === 'annonces'): ?>

    <div class="page-title">📋 Gestion des Annonces</div>

    <div class="table-card">
      <div class="table-header">
        <h3>📋 <?= count($annonces) ?> annonces</h3>
        <input type="text" class="table-search" placeholder="🔍 Rechercher..." oninput="filtrerTable(this,'table-annonces')">
      </div>
      <table id="table-annonces">
        <thead>
          <tr><th>Annonce</th><th>Vendeur</th><th>Catégorie</th><th>Prix</th><th>Vues</th><th>Statut</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($annonces as $a): ?>
          <tr>
            <td>
              <div class="annonce-mini">
                <div class="annonce-mini-img">
                  <?php if (!empty($a['photo'])): ?><img src="<?= htmlspecialchars($a['photo']) ?>" alt=""><?php else: ?><?= $a['icone'] ?><?php endif; ?>
                </div>
                <span class="td-truncate" style="max-width:160px"><?= htmlspecialchars($a['titre']) ?></span>
              </div>
            </td>
            <td style="font-size:.82rem"><?= htmlspecialchars($a['vendeur']) ?></td>
            <td><?= $a['icone'] ?> <?= htmlspecialchars($a['categorie']) ?></td>
            <td style="color:var(--jaune);font-weight:700"><?= $a['est_gratuit'] ? 'Gratuit' : number_format($a['prix'],0,',',' ').' F' ?></td>
            <td style="color:var(--text-muted)"><?= $a['nb_vues'] ?></td>
            <td>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="statut_annonce">
                <input type="hidden" name="annonce_id" value="<?= $a['id'] ?>">
                <select name="statut" class="inline" onchange="this.form.submit()">
                  <?php foreach (['active','vendue','expiree','suspendue'] as $s): ?>
                    <option value="<?= $s ?>" <?= $a['statut']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td>
              <a href="annonce-detail.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline" target="_blank">👁</a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette annonce ?')">
                <input type="hidden" name="action" value="supprimer_annonce">
                <input type="hidden" name="annonce_id" value="<?= $a['id'] ?>">
                <button class="btn btn-sm btn-danger">🗑️</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- ══════════════════════════════════════════ -->
    <!-- CATÉGORIES                                 -->
    <!-- ══════════════════════════════════════════ -->
    <?php elseif ($onglet === 'categories'): ?>

    <div class="page-title">🗂️ Gestion des Catégories</div>

    <!-- Formulaire ajout -->
    <form method="POST" action="?onglet=categories">
      <input type="hidden" name="action" value="ajouter_cat">
      <div class="add-form">
        <div>
          <label>Nom <span style="color:var(--jaune)">*</span></label>
          <input type="text" name="cat_nom" placeholder="Ex: Téléphones" required>
        </div>
        <div>
          <label>Slug <span style="color:var(--jaune)">*</span></label>
          <input type="text" name="cat_slug" placeholder="Ex: telephones" required>
        </div>
        <div>
          <label>Icône</label>
          <input type="text" name="cat_icone" placeholder="📱" value="📦">
        </div>
        <div>
          <label>&nbsp;</label>
          <button type="submit" class="btn">+ Ajouter</button>
        </div>
      </div>
    </form>

    <div class="table-card">
      <div class="table-header"><h3>🗂️ <?= count($categories) ?> catégories</h3></div>
      <table>
        <thead><tr><th>Icône</th><th>Nom</th><th>Slug</th><th>Annonces actives</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($categories as $c): ?>
          <tr>
            <td style="font-size:1.5rem;text-align:center"><?= $c['icone'] ?></td>
            <td><strong><?= htmlspecialchars($c['nom']) ?></strong></td>
            <td style="color:var(--text-muted);font-family:monospace"><?= htmlspecialchars($c['slug']) ?></td>
            <td style="text-align:center"><strong><?= $c['nb_annonces'] ?></strong></td>
            <td>
              <?php if ($c['nb_annonces'] == 0): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cette catégorie ?')">
                <input type="hidden" name="action" value="supprimer_cat">
                <input type="hidden" name="cat_id" value="<?= $c['id'] ?>">
                <button class="btn btn-sm btn-danger">🗑️ Supprimer</button>
              </form>
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:.78rem;">Non supprimable (annonces liées)</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- ══════════════════════════════════════════ -->
    <!-- MESSAGES                                   -->
    <!-- ══════════════════════════════════════════ -->
    <?php elseif ($onglet === 'messages'): ?>

    <div class="page-title">💬 Gestion des Messages</div>

    <?php if (empty($messages)): ?>
      <div style="text-align:center;padding:3rem;color:var(--text-muted);">
        <div style="font-size:3rem;margin-bottom:1rem;">💬</div>
        <p>Aucun message pour le moment.</p>
      </div>
    <?php else: ?>
      <div class="table-card">
        <div class="table-header">
          <h3>💬 <?= count($messages) ?> messages</h3>
          <input type="text" class="table-search" placeholder="🔍 Rechercher..." oninput="filtrerTable(this,'table-messages')">
        </div>
        <table id="table-messages">
          <thead><tr><th>De</th><th>À</th><th>Annonce</th><th>Message</th><th>Date</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($messages as $m): ?>
            <tr>
              <td><strong><?= htmlspecialchars($m['expediteur']) ?></strong></td>
              <td><?= htmlspecialchars($m['destinataire']) ?></td>
              <td class="td-truncate" style="max-width:140px;color:var(--text-muted)"><?= htmlspecialchars($m['annonce_titre']) ?></td>
              <td class="td-truncate" style="max-width:200px"><?= htmlspecialchars($m['contenu']) ?></td>
              <td style="color:var(--text-muted);font-size:.78rem"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
              <td>
                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce message ?')">
                  <input type="hidden" name="action" value="supprimer_message">
                  <input type="hidden" name="message_id" value="<?= $m['id'] ?>">
                  <button class="btn btn-sm btn-danger">🗑️</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php endif; ?>

  </main>
</div>

<footer>
  © 2025 <a href="index.php">LocalMarket</a> · Panel Administration
</footer>

<script>
// Filtre tableau en temps réel
function filtrerTable(input, tableId) {
  const filtre = input.value.toLowerCase();
  const lignes = document.getElementById(tableId).querySelectorAll('tbody tr');
  lignes.forEach(ligne => {
    ligne.style.display = ligne.textContent.toLowerCase().includes(filtre) ? '' : 'none';
  });
}
</script>

</body>
</html>