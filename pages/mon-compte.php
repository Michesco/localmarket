<?php
/**
 * LocalMarket — mon-compte.php
 * Fichier à placer dans : pages/mon-compte.php
 */
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Protection : doit être connecté
if (!estConnecte()) {
    header('Location: connexion.php?redirect=mon-compte.php');
    exit;
}

$db     = getDB();
$userId = $_SESSION['user_id'];

// ── Traitement des formulaires POST ──────────────────
$succes = '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Modifier informations personnelles ────────────
    if ($action === 'modifier_profil') {
        $prenom    = trim($_POST['prenom']    ?? '');
        $nom       = trim($_POST['nom']       ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $ville     = trim($_POST['ville']     ?? '');

        if (empty($prenom) || empty($nom) || empty($telephone) || empty($ville)) {
            $erreur = '⚠️ Veuillez remplir tous les champs.';
        } else {
            $stmt = $db->prepare('UPDATE utilisateurs SET prenom=?, nom=?, telephone=?, ville=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$prenom, $nom, $telephone, $ville, $userId]);
            $_SESSION['user_nom'] = $prenom;
            $succes = '✅ Profil mis à jour avec succès.';
        }
    }

    // ── Changer mot de passe ──────────────────────────
    if ($action === 'changer_mdp') {
        $ancien   = trim($_POST['ancien_mdp']      ?? '');
        $nouveau  = trim($_POST['nouveau_mdp']     ?? '');
        $confirm  = trim($_POST['confirmer_mdp']   ?? '');

        $stmt = $db->prepare('SELECT mot_de_passe FROM utilisateurs WHERE id=?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!password_verify($ancien, $user['mot_de_passe'])) {
            $erreur = ' Ancien mot de passe incorrect.';
        } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $nouveau)) {
            $erreur = ' Le nouveau mot de passe doit contenir au moins 8 caractères, une majuscule et un chiffre.';
        } elseif ($nouveau !== $confirm) {
            $erreur = ' Les mots de passe ne correspondent pas.';
        } else {
            $hash = password_hash($nouveau, PASSWORD_DEFAULT);
            $db->prepare('UPDATE utilisateurs SET mot_de_passe=? WHERE id=?')->execute([$hash, $userId]);
            $succes = ' Mot de passe modifié avec succès.';
        }
    }

    // ── Supprimer le compte ───────────────────────────
    if ($action === 'supprimer_compte') {
        $confirm = trim($_POST['confirmer_suppression'] ?? '');
        if ($confirm !== 'SUPPRIMER') {
            $erreur = ' Tapez exactement "SUPPRIMER" pour confirmer.';
        } else {
            $db->prepare('DELETE FROM utilisateurs WHERE id=?')->execute([$userId]);
            session_destroy();
            header('Location: inscription.php?compte_supprime=1');
            exit;
        }
    }
}

// ── Récupérer les données ─────────────────────────────
$stmt = $db->prepare('SELECT * FROM utilisateurs WHERE id=?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Mes annonces
$stmtAnnonces = $db->prepare('
    SELECT a.*, c.nom AS categorie, c.icone,
           (SELECT url FROM photos p WHERE p.annonce_id = a.id AND p.ordre = 0 LIMIT 1) AS photo
    FROM annonces a
    JOIN categories c ON a.categorie_id = c.id
    WHERE a.utilisateur_id = ?
    ORDER BY a.created_at DESC
');
$stmtAnnonces->execute([$userId]);
$annonces = $stmtAnnonces->fetchAll();

// Mes messages (conversations)
$messages = [];
try {
    $stmtMessages = $db->prepare('
        SELECT 
            m.id,
            m.annonce_id,
            m.expediteur_id,
            m.destinataire_id,
            m.contenu,
            m.lu,
            m.created_at,
            a.titre AS annonce_titre,
            CONCAT(u.prenom," ",u.nom) AS interlocuteur,
            u.id AS interlocuteur_id,
            u.photo_profil AS interlocuteur_photo
        FROM messages m
        JOIN annonces a ON m.annonce_id = a.id
        JOIN utilisateurs u ON u.id = CASE WHEN m.expediteur_id = ? THEN m.destinataire_id ELSE m.expediteur_id END
        WHERE (m.expediteur_id = ? OR m.destinataire_id = ?)
        ORDER BY m.created_at DESC
        LIMIT 20
    ');
    $stmtMessages->execute([$userId, $userId, $userId]);
    $messages = $stmtMessages->fetchAll();
} catch (Exception $e) {
    error_log("Erreur messages: " . $e->getMessage());
}

// Mes favoris
$stmtFavoris = $db->prepare('
    SELECT a.*, c.nom AS categorie, c.icone,
           (SELECT url FROM photos p WHERE p.annonce_id = a.id AND p.ordre = 0 LIMIT 1) AS photo
    FROM favoris f
    JOIN annonces a ON f.annonce_id = a.id
    JOIN categories c ON a.categorie_id = c.id
    WHERE f.utilisateur_id = ?
    ORDER BY f.created_at DESC
');
$stmtFavoris->execute([$userId]);
$favoris = $stmtFavoris->fetchAll();

// Onglet actif
$onglet = $_GET['onglet'] ?? 'infos';

function timeAgo($d) {
    $s = time() - strtotime($d);
    if ($s < 60)     return 'À l\'instant';
    if ($s < 3600)   return 'il y a ' . floor($s/60) . ' min';
    if ($s < 86400)  return 'il y a ' . floor($s/3600) . 'h';
    if ($s < 604800) return 'il y a ' . floor($s/86400) . ' j';
    return date('d/m/Y', strtotime($d));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mon Compte — LocalMarket</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/main.css">
  <link rel="stylesheet" href="../assets/css/navbar.css">
  <style>
    :root{--jaune:#FFD600;--jaune-clair:#FFF176;--noir:#0D0D0D;--gris:#1A1A1A;--gris-clair:#2A2A2A;--blanc:#FAFAFA;--text-muted:#888;--vert:#4CAF50;--rouge:#FF4D4D;}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Outfit',sans-serif;background:var(--noir);color:var(--blanc);min-height:100vh;display:flex;flex-direction:column;}
    nav{background:var(--noir);border-bottom:3px solid var(--jaune);padding:0 2rem;display:flex;align-items:center;justify-content:space-between;height:70px;}
    .logo{font-family:'Bebas Neue',sans-serif;font-size:2rem;color:var(--jaune);letter-spacing:2px;text-decoration:none;}
    .logo span{color:var(--blanc);}

    /* LAYOUT */
    .compte-wrapper{max-width:1100px;margin:0 auto;padding:2rem 1.5rem;flex:1;display:grid;grid-template-columns:260px 1fr;gap:2rem;align-items:start;}
    @media(max-width:750px){.compte-wrapper{grid-template-columns:1fr;}}

    /* SIDEBAR */
    .sidebar{background:var(--gris);border:2px solid var(--gris-clair);border-radius:16px;overflow:hidden;position:sticky;top:20px;}
    .sidebar-header{background:var(--jaune);padding:1.5rem;text-align:center;}
    .avatar{width:70px;height:70px;background:var(--noir);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto .8rem;overflow:hidden;}
    .avatar img{width:100%;height:100%;object-fit:cover;}
    .sidebar-name{font-weight:700;color:var(--noir);font-size:1.1rem;}
    .sidebar-ville{font-size:.82rem;color:#333;}
    .sidebar-menu{padding:.5rem 0;}
    .sidebar-menu a{display:flex;align-items:center;gap:.8rem;padding:.9rem 1.5rem;color:var(--blanc);text-decoration:none;font-size:.9rem;font-weight:500;transition:background .2s,color .2s;border-left:3px solid transparent;}
    .sidebar-menu a:hover{background:var(--gris-clair);color:var(--jaune);}
    .sidebar-menu a.active{background:var(--gris-clair);color:var(--jaune);border-left-color:var(--jaune);}
    .sidebar-menu .logout{color:var(--rouge);}
    .sidebar-menu .logout:hover{background:rgba(255,77,77,.1);color:var(--rouge);}
    .badge-count{background:var(--jaune);color:var(--noir);font-size:.7rem;font-weight:700;padding:.1rem .5rem;border-radius:10px;margin-left:auto;}

    /* CONTENU */
    .content-card{background:var(--gris);border:2px solid var(--gris-clair);border-radius:16px;padding:2rem;margin-bottom:1.5rem;}
    .content-card h2{font-family:'Bebas Neue',sans-serif;font-size:1.6rem;letter-spacing:1px;margin-bottom:1.5rem;color:var(--jaune);}

    /* ALERTES */
    .alert{padding:.9rem 1rem;border-radius:8px;margin-bottom:1.2rem;font-size:.9rem;}
    .alert-error{background:rgba(255,77,77,.15);border:1px solid var(--rouge);color:#ff8080;}
    .alert-success{background:rgba(76,175,80,.15);border:1px solid var(--vert);color:#80c883;}

    /* FORMULAIRES */
    .form-group{margin-bottom:1.2rem;}
    label{display:block;font-weight:600;font-size:.88rem;margin-bottom:.4rem;color:var(--blanc);}
    label span{color:var(--jaune);}
    input[type=text],input[type=email],input[type=password],input[type=tel],select{width:100%;background:var(--gris-clair);border:2px solid var(--gris-clair);border-radius:8px;padding:.8rem 1rem;color:var(--blanc);font-family:'Outfit',sans-serif;font-size:.9rem;outline:none;transition:border-color .2s;}
    input:focus,select:focus{border-color:var(--jaune);}
    input::placeholder{color:var(--text-muted);}
    input[readonly]{opacity:.6;cursor:not-allowed;}
    .input-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
    .btn{background:var(--jaune);color:var(--noir);padding:.8rem 1.5rem;border-radius:8px;font-weight:700;font-family:'Outfit',sans-serif;font-size:.95rem;border:none;cursor:pointer;transition:background .2s;}
    .btn:hover{background:var(--jaune-clair);}
    .btn-danger{background:var(--rouge);color:#fff;}
    .btn-danger:hover{background:#ff3333;}

    /* ANNONCES */
    .annonce-row{display:flex;gap:1rem;padding:1rem 0;border-bottom:1px solid var(--gris-clair);align-items:center;}
    .annonce-row:last-child{border-bottom:none;}
    .annonce-thumb{width:70px;height:70px;border-radius:8px;background:linear-gradient(135deg,var(--gris-clair),var(--gris));overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:2rem;border:1px solid var(--gris-clair);}
    .annonce-thumb img{width:100%;height:100%;object-fit:cover;}
    .annonce-info{flex:1;min-width:0;}
    .annonce-titre{font-weight:600;font-size:.92rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .annonce-prix{color:var(--jaune);font-family:'Bebas Neue',sans-serif;font-size:1.1rem;}
    .annonce-meta{color:var(--text-muted);font-size:.78rem;margin-top:.2rem;}
    .annonce-actions{display:flex;gap:.5rem;flex-shrink:0;}
    .btn-sm{padding:.4rem .8rem;font-size:.78rem;border-radius:6px;font-weight:600;font-family:'Outfit',sans-serif;border:none;cursor:pointer;transition:background .2s;}
    .btn-sm-edit{background:var(--gris-clair);color:var(--blanc);border:1px solid var(--gris-clair);}
    .btn-sm-edit:hover{border-color:var(--jaune);color:var(--jaune);}
    .btn-sm-del{background:rgba(255,77,77,.15);color:var(--rouge);border:1px solid transparent;}
    .btn-sm-del:hover{background:var(--rouge);color:#fff;}
    .statut-badge{display:inline-block;font-size:.7rem;font-weight:700;padding:.15rem .5rem;border-radius:4px;text-transform:uppercase;}
    .statut-active{background:rgba(76,175,80,.2);color:var(--vert);}
    .statut-vendue{background:rgba(255,214,0,.2);color:var(--jaune);}
    .statut-expiree{background:rgba(255,77,77,.2);color:var(--rouge);}

    /* MESSAGES */
    .msg-row{display:flex;gap:1rem;padding:1rem 0;border-bottom:1px solid var(--gris-clair);align-items:center;text-decoration:none;color:var(--blanc);transition:background .2s;border-radius:8px;padding:.8rem;}
    .msg-row:hover{background:var(--gris-clair);}
    .msg-avatar{width:45px;height:45px;border-radius:50%;background:linear-gradient(135deg,var(--gris-clair),var(--gris));display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;overflow:hidden;border:1px solid var(--gris-clair);}
    .msg-avatar img{width:100%;height:100%;object-fit:cover;}
    .msg-info{flex:1;min-width:0;}
    .msg-nom{font-weight:600;font-size:.9rem;}
    .msg-annonce{color:var(--text-muted);font-size:.78rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .msg-time{color:var(--text-muted);font-size:.75rem;flex-shrink:0;}
    .msg-unread .msg-nom{color:var(--jaune);}

    /* FAVORIS */
    .favoris-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;}
    .fav-card{background:var(--gris-clair);border:2px solid var(--gris-clair);border-radius:12px;overflow:hidden;text-decoration:none;color:var(--blanc);transition:border-color .2s;}
    .fav-card:hover{border-color:var(--jaune);}
    .fav-img{height:130px;background:linear-gradient(135deg,var(--gris),var(--gris-clair));display:flex;align-items:center;justify-content:center;font-size:3rem;overflow:hidden;border-bottom:1px solid var(--gris-clair);}
    .fav-img img{width:100%;height:100%;object-fit:cover;}
    .fav-body{padding:.8rem;}
    .fav-titre{font-size:.85rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .fav-prix{color:var(--jaune);font-family:'Bebas Neue',sans-serif;font-size:1.1rem;}

    /* ZONE DANGER */
    .danger-zone{border:2px solid var(--rouge);border-radius:12px;padding:1.5rem;background:rgba(255,77,77,.05);}
    .danger-zone h3{color:var(--rouge);margin-bottom:.8rem;font-size:1rem;}

    /* EMPTY STATE */
    .empty{text-align:center;padding:3rem 1rem;color:var(--text-muted);}
    .empty .icon{font-size:3rem;margin-bottom:1rem;}
    .empty a{color:var(--jaune);text-decoration:none;font-weight:600;}

    footer{background:#080808;border-top:1px solid var(--gris-clair);padding:1.2rem;text-align:center;color:var(--text-muted);font-size:.8rem;}
    footer a{color:var(--jaune);text-decoration:none;}
  </style>
</head>
<body>

<nav>
  <a href="index.php" class="logo">Local<span>Market</span></a>
  <span style="color:var(--text-muted);font-size:.9rem;">
    👤 <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
  </span>
</nav>

<div class="compte-wrapper">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="avatar">
        <?php if (!empty($user['photo_profil'])): ?>
          <img src="<?= htmlspecialchars($user['photo_profil']) ?>" alt="Avatar">
        <?php else: ?>
          👤
        <?php endif; ?>
      </div>
      <div class="sidebar-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
      <div class="sidebar-ville"> <?= htmlspecialchars($user['ville']) ?></div>
    </div>
    <nav class="sidebar-menu">
      <a href="?onglet=infos"      class="<?= $onglet==='infos'      ? 'active' : '' ?>">👤 Mes informations</a>
      <a href="?onglet=annonces"   class="<?= $onglet==='annonces'   ? 'active' : '' ?>">
         Mes annonces
        <?php if (count($annonces)): ?>
          <span class="badge-count"><?= count($annonces) ?></span>
        <?php endif; ?>
      </a>
      <a href="?onglet=messages"   class="<?= $onglet==='messages'   ? 'active' : '' ?>">
         Mes messages
        <?php
          $nonLus = array_filter($messages, fn($m) => !$m['lu'] && $m['destinataire_id'] == $userId);
          if (count($nonLus)):
        ?>
          <span class="badge-count"><?= count($nonLus) ?></span>
        <?php endif; ?>
      </a>
      <a href="?onglet=favoris"    class="<?= $onglet==='favoris'    ? 'active' : '' ?>">
         Mes favoris
        <?php if (count($favoris)): ?>
          <span class="badge-count"><?= count($favoris) ?></span>
        <?php endif; ?>
      </a>
      <a href="?onglet=securite"   class="<?= $onglet==='securite'   ? 'active' : '' ?>">🔒 Mot de passe</a>
      <a href="?onglet=supprimer"  class="<?= $onglet==='supprimer'  ? 'active' : '' ?>">🗑️ Supprimer le compte</a>
      <a href="deconnexion.php" class="logout"> Déconnexion</a>
    </nav>
  </aside>

  <!-- CONTENU -->
  <main>

    <?php if ($succes): ?>
      <div class="alert alert-success"><?= $succes ?></div>
    <?php endif; ?>
    <?php if ($erreur): ?>
      <div class="alert alert-error"><?= $erreur ?></div>
    <?php endif; ?>

    <!-- ── INFOS PERSONNELLES ─────────────────────── -->
    <?php if ($onglet === 'infos'): ?>
    <div class="content-card">
      <h2>👤 Mes Informations</h2>
      <form method="POST" action="?onglet=infos">
        <input type="hidden" name="action" value="modifier_profil">
        <div class="input-row">
          <div class="form-group">
            <label>Prénom <span>*</span></label>
            <input type="text" name="prenom" value="<?= htmlspecialchars($user['prenom']) ?>" required>
          </div>
          <div class="form-group">
            <label>Nom <span>*</span></label>
            <input type="text" name="nom" value="<?= htmlspecialchars($user['nom']) ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label>Adresse email</label>
          <input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly>
          <small style="color:var(--text-muted);font-size:.75rem;">L'email ne peut pas être modifié.</small>
        </div>
        <div class="form-group">
          <label>Téléphone <span>*</span></label>
          <input type="tel" name="telephone" value="<?= htmlspecialchars($user['telephone']) ?>" required>
        </div>
        <div class="form-group">
          <label>Ville <span>*</span></label>
          <select name="ville" required>
            <?php
            $villes = ['Abidjan','Dakar','Douala','Lomé','Cotonou','Bamako','Ouagadougou','Autre'];
            foreach ($villes as $v) {
                $sel = $user['ville'] === $v ? 'selected' : '';
                echo "<option value=\"$v\" $sel>$v</option>";
            }
            ?>
          </select>
        </div>
        <div class="form-group">
          <label>Membre depuis</label>
          <input type="text" value="<?= date('d/m/Y', strtotime($user['created_at'])) ?>" readonly>
        </div>
        <button type="submit" class="btn">💾 Enregistrer les modifications</button>
      </form>
    </div>

    <!-- ── MES ANNONCES ───────────────────────────── -->
    <?php elseif ($onglet === 'annonces'): ?>
    <div class="content-card">
      <h2>📋 Mes Annonces (<?= count($annonces) ?>)</h2>
      <div style="margin-bottom:1.2rem;">
        <a href="publier.php" class="btn">+ Publier une nouvelle annonce</a>
      </div>
      <?php if (empty($annonces)): ?>
        <div class="empty">
          <div class="icon">📭</div>
          <p>Vous n'avez pas encore d'annonces.</p>
          <p style="margin-top:.5rem;"><a href="publier.php">Publiez votre première annonce →</a></p>
        </div>
      <?php else: ?>
        <?php foreach ($annonces as $a): ?>
          <div class="annonce-row">
            <div class="annonce-thumb">
              <?php if (!empty($a['photo'])): ?>
                <img src="<?= htmlspecialchars($a['photo']) ?>" alt="">
              <?php else: ?>
                <span style="font-size:1.8rem;opacity:0.6;"><?= $a['icone'] ?? '📦' ?></span>
              <?php endif; ?>
            </div>
            <div class="annonce-info">
              <div class="annonce-titre"><?= htmlspecialchars($a['titre']) ?></div>
              <div class="annonce-prix">
                <?= $a['est_gratuit'] ? 'GRATUIT' : number_format($a['prix'], 0, ',', ' ') . ' FCFA' ?>
              </div>
              <div class="annonce-meta">
                <span class="statut-badge statut-<?= $a['statut'] ?>"><?= ucfirst($a['statut']) ?></span>
                · <?= $a['nb_vues'] ?> vues · <?= timeAgo($a['created_at']) ?>
              </div>
            </div>
            <div class="annonce-actions">
              <a href="annonce-detail.php?id=<?= $a['id'] ?>" class="btn-sm btn-sm-edit">👁 Voir</a>
              <a href="modifier-annonce.php?id=<?= $a['id'] ?>" class="btn-sm btn-sm-edit">✏️ Modifier</a>
              <a href="supprimer-annonce.php?id=<?= $a['id'] ?>"
                 onclick="return confirm('Supprimer cette annonce ?')"
                 class="btn-sm btn-sm-del">🗑️</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- ── MES MESSAGES ───────────────────────────── -->
    <?php elseif ($onglet === 'messages'): ?>
    <div class="content-card">
      <h2> Mes Messages</h2>
      <?php if (empty($messages)): ?>
        <div class="empty">
          <div class="icon">💬</div>
          <p>Aucun message pour le moment.</p>
        </div>
      <?php else: ?>
        <?php foreach ($messages as $m): ?>
          <a href="messages.php?annonce=<?= $m['annonce_id'] ?>&avec=<?= $m['interlocuteur_id'] ?>"
             class="msg-row <?= !$m['lu'] && $m['destinataire_id'] == $userId ? 'msg-unread' : '' ?>">
            <div class="msg-avatar">
              <?php if (!empty($m['interlocuteur_photo'])): ?>
                <img src="<?= htmlspecialchars($m['interlocuteur_photo']) ?>" alt="Avatar">
              <?php else: ?>
                👤
              <?php endif; ?>
            </div>
            <div class="msg-info">
              <div class="msg-nom"><?= htmlspecialchars($m['interlocuteur']) ?></div>
              <div class="msg-annonce"> <?= htmlspecialchars($m['annonce_titre']) ?></div>
            </div>
            <div class="msg-time"><?= timeAgo($m['created_at']) ?></div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- ── MES FAVORIS ────────────────────────────── -->
    <?php elseif ($onglet === 'favoris'): ?>
    <div class="content-card">
      <h2> Mes Favoris (<?= count($favoris) ?>)</h2>
      <?php if (empty($favoris)): ?>
        <div class="empty">
          <div class="icon">❤️</div>
          <p>Vous n'avez pas encore de favoris.</p>
          <p style="margin-top:.5rem;"><a href="annonces.php">Parcourir les annonces →</a></p>
        </div>
      <?php else: ?>
        <div class="favoris-grid">
          <?php foreach ($favoris as $f): ?>
            <a href="annonce-detail.php?id=<?= $f['id'] ?>" class="fav-card">
              <div class="fav-img">
                <?php if (!empty($f['photo'])): ?>
                  <img src="<?= htmlspecialchars($f['photo']) ?>" alt="">
                <?php else: ?>
                  <span style="font-size:2.5rem;opacity:0.6;"><?= $f['icone'] ?? '📦' ?></span>
                <?php endif; ?>
              </div>
              <div class="fav-body">
                <div class="fav-titre"><?= htmlspecialchars($f['titre']) ?></div>
                <div class="fav-prix">
                  <?= $f['est_gratuit'] ? 'GRATUIT' : number_format($f['prix'], 0, ',', ' ') . ' FCFA' ?>
                </div>
                <div style="color:var(--text-muted);font-size:.75rem;">📍 <?= htmlspecialchars($f['ville']) ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ── CHANGER MOT DE PASSE ───────────────────── -->
    <?php elseif ($onglet === 'securite'): ?>
    <div class="content-card">
      <h2> Changer le Mot de Passe</h2>
      <form method="POST" action="?onglet=securite">
        <input type="hidden" name="action" value="changer_mdp">
        <div class="form-group">
          <label>Ancien mot de passe <span>*</span></label>
          <input type="password" name="ancien_mdp" placeholder="••••••••" required>
        </div>
        <div class="form-group">
          <label>Nouveau mot de passe <span>*</span></label>
          <input type="password" name="nouveau_mdp" placeholder="••••••••" required>
          <small style="color:var(--text-muted);font-size:.75rem;">Minimum 8 caractères, une majuscule et un chiffre.</small>
        </div>
        <div class="form-group">
          <label>Confirmer le nouveau mot de passe <span>*</span></label>
          <input type="password" name="confirmer_mdp" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn">🔒 Changer le mot de passe</button>
      </form>
    </div>

    <!-- ── SUPPRIMER LE COMPTE ────────────────────── -->
    <?php elseif ($onglet === 'supprimer'): ?>
    <div class="content-card">
      <h2> Supprimer Mon Compte</h2>
      <div class="danger-zone">
        <h3>⚠️Action irréversible</h3>
        <p style="color:var(--text-muted);font-size:.9rem;margin-bottom:1.2rem;line-height:1.6;">
          La suppression de votre compte entraînera la perte définitive de toutes vos annonces,
          messages et favoris. Cette action est <strong style="color:var(--rouge);">irréversible</strong>.
        </p>
        <form method="POST" action="?onglet=supprimer"
              onsubmit="return confirm('Êtes-vous absolument sûr ? Cette action est irréversible.')">
          <input type="hidden" name="action" value="supprimer_compte">
          <div class="form-group">
            <label>Tapez <strong style="color:var(--rouge);">SUPPRIMER</strong> pour confirmer</label>
            <input type="text" name="confirmer_suppression" placeholder="SUPPRIMER" required>
          </div>
          <button type="submit" class="btn btn-danger">🗑️ Supprimer définitivement mon compte</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

<footer>
  © 2025 <a href="index.php">LocalMarket</a> · Tous droits réservés
</footer>

<script src="../assets/js/main.js"></script>
</body>
</html>
