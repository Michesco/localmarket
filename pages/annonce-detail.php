<?php
/**
 * LocalMarket — annonce-detail.php
 * Fichier à placer dans : pages/annonce-detail.php
 */
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$db = getDB();

// Récupérer l'ID de l'annonce
$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: annonces.php');
    exit;
}

// ✅ Colonnes corrigées : u.created_at (pas date_inscription), a.nb_vues (pas vues)
$stmt = $db->prepare('
    SELECT a.*,
           c.nom  AS categorie, c.icone,
           CONCAT(u.prenom, " ", u.nom) AS vendeur_nom,
           u.telephone  AS vendeur_tel,
           u.id         AS vendeur_id,
           u.created_at AS vendeur_depuis,
           u.photo_profil AS vendeur_photo,
           u.note_moyenne, u.nb_avis
    FROM annonces a
    JOIN categories c ON a.categorie_id = c.id
    JOIN utilisateurs u ON a.utilisateur_id = u.id
    WHERE a.id = ? AND a.statut = "active"
');
$stmt->execute([$id]);
$annonce = $stmt->fetch();

if (!$annonce) {
    header('Location: annonces.php?erreur=introuvable');
    exit;
}

// Récupérer les photos
$stmtPhotos = $db->prepare('SELECT * FROM photos WHERE annonce_id = ? ORDER BY ordre ASC');
$stmtPhotos->execute([$id]);
$photos = $stmtPhotos->fetchAll();

// Autres annonces du même vendeur avec photos
$stmtAutres = $db->prepare('
    SELECT a.id, a.titre, a.prix, a.est_gratuit,
           (SELECT url FROM photos p WHERE p.annonce_id = a.id AND p.ordre = 0 LIMIT 1) AS photo
    FROM annonces a
    WHERE a.utilisateur_id = ? AND a.id != ? AND a.statut = "active"
    LIMIT 3
');
$stmtAutres->execute([$annonce['vendeur_id'], $id]);
$autres = $stmtAutres->fetchAll();

// ✅ Incrémenter nb_vues (pas vues)
$db->prepare('UPDATE annonces SET nb_vues = nb_vues + 1 WHERE id = ?')->execute([$id]);

$connecte = estConnecte();

function timeAgo($d) {
    $s = time() - strtotime($d);
    if ($s < 60)     return 'À l\'instant';
    if ($s < 3600)   return 'il y a ' . floor($s/60) . ' min';
    if ($s < 86400)  return 'il y a ' . floor($s/3600) . 'h';
    if ($s < 604800) return 'il y a ' . floor($s/86400) . ' j';
    return date('d/m/Y', strtotime($d));
}

// Libellés état
$etats = [
    'neuf'      => 'Neuf',
    'tres_bon'  => 'Très bon état',
    'bon'       => 'Bon état',
    'correct'   => 'État correct',
    'pieces'    => 'Pour pièces',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($annonce['titre']) ?> — LocalMarket</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/main.css">
  <link rel="stylesheet" href="../assets/css/navbar.css">
  <style>
    :root {
      --jaune:#FFD600;--jaune-clair:#FFF176;--noir:#0D0D0D;
      --gris:#1A1A1A;--gris-clair:#2A2A2A;--blanc:#FAFAFA;
      --text-muted:#888;--vert:#4CAF50;--rouge:#FF4D4D;
    }
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Outfit',sans-serif;background:var(--noir);color:var(--blanc);}
    nav{background:var(--noir);border-bottom:3px solid var(--jaune);padding:0 2rem;display:flex;align-items:center;justify-content:space-between;height:70px;position:sticky;top:0;z-index:100;}
    .logo{font-family:'Bebas Neue',sans-serif;font-size:2rem;color:var(--jaune);letter-spacing:2px;text-decoration:none;}
    .logo span{color:var(--blanc);}
    .breadcrumb{padding:1rem 2rem;font-size:.82rem;color:var(--text-muted);max-width:1100px;margin:0 auto;}
    .breadcrumb a{color:var(--text-muted);text-decoration:none;}
    .breadcrumb a:hover{color:var(--jaune);}
    .breadcrumb span{color:var(--jaune);margin:0 .4rem;}
    .detail-wrapper{max-width:1100px;margin:0 auto;padding:0 2rem 3rem;display:grid;grid-template-columns:1fr 340px;gap:2rem;align-items:start;}
    @media(max-width:750px){.detail-wrapper{grid-template-columns:1fr;}}
    .gallery-main{background:var(--gris);border:2px solid var(--gris-clair);border-radius:14px;height:350px;display:flex;align-items:center;justify-content:center;font-size:6rem;margin-bottom:.8rem;overflow:hidden;}
    .gallery-main img{width:100%;height:100%;object-fit:cover;}
    .gallery-thumbs{display:flex;gap:.7rem;margin-bottom:1.5rem;flex-wrap:wrap;}
    .thumb{width:70px;height:70px;background:var(--gris);border:2px solid var(--gris-clair);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;cursor:pointer;transition:border-color .2s;flex-shrink:0;overflow:hidden;}
    .thumb img{width:100%;height:100%;object-fit:cover;}
    .thumb.active,.thumb:hover{border-color:var(--jaune);}
    .info-card{background:var(--gris);border:2px solid var(--gris-clair);border-radius:14px;padding:1.5rem;margin-bottom:1.2rem;}
    .badge-row{display:flex;gap:.6rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap;}
    .badge{background:var(--jaune);color:var(--noir);font-size:.72rem;font-weight:700;padding:.2rem .7rem;border-radius:4px;text-transform:uppercase;letter-spacing:.5px;}
    .badge-green{background:var(--vert);color:#fff;}
    .badge-outline{background:none;border:1px solid var(--gris-clair);color:var(--text-muted);}
    .annonce-title-big{font-size:1.6rem;font-weight:700;margin-bottom:.5rem;line-height:1.3;}
    .price-big{font-family:'Bebas Neue',sans-serif;font-size:2.5rem;color:var(--jaune);letter-spacing:1px;margin-bottom:.8rem;}
    .price-big.free{font-family:'Outfit',sans-serif;font-size:1.5rem;color:var(--vert);font-weight:700;}
    .location-date{color:var(--text-muted);font-size:.88rem;display:flex;gap:1.5rem;flex-wrap:wrap;}
    .divider{border:none;border-top:1px solid var(--gris-clair);margin:1.2rem 0;}
    .description-text{color:#ccc;line-height:1.75;font-size:.95rem;}
    .action-row{display:flex;gap:.8rem;margin-top:.8rem;}
    .action-btn{flex:1;background:var(--gris-clair);border:2px solid var(--gris-clair);border-radius:8px;padding:.7rem;color:var(--blanc);font-family:'Outfit',sans-serif;font-size:.85rem;font-weight:500;cursor:pointer;text-align:center;transition:border-color .2s,color .2s;display:flex;align-items:center;justify-content:center;gap:.4rem;}
    .action-btn:hover{border-color:var(--jaune);color:var(--jaune);}
    .action-btn.liked{border-color:var(--rouge);color:var(--rouge);}
    .message-form{background:var(--gris);border:2px solid var(--gris-clair);border-radius:14px;padding:1.2rem;margin-bottom:1.2rem;}
    .message-form h3{font-size:1rem;margin-bottom:1rem;font-weight:600;}
    .message-form textarea{width:100%;background:var(--gris-clair);border:2px solid var(--gris-clair);border-radius:8px;padding:.8rem;color:var(--blanc);font-family:'Outfit',sans-serif;font-size:.9rem;resize:vertical;min-height:90px;outline:none;}
    .message-form textarea:focus{border-color:var(--jaune);}
    .quick-msgs{display:flex;gap:.5rem;flex-wrap:wrap;margin:.7rem 0;}
    .quick-msg{background:var(--gris-clair);border:1px solid var(--gris-clair);border-radius:20px;padding:.35rem .8rem;font-size:.78rem;cursor:pointer;color:var(--blanc);transition:border-color .2s;}
    .quick-msg:hover{border-color:var(--jaune);color:var(--jaune);}
    .seller-card{background:var(--gris);border:2px solid var(--gris-clair);border-radius:14px;overflow:hidden;margin-bottom:1.2rem;position:sticky;top:90px;}
    .seller-card-header{background:var(--jaune);padding:1rem 1.2rem;display:flex;align-items:center;gap:.8rem;}
    .seller-avatar{width:48px;height:48px;background:var(--noir);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;overflow:hidden;}
    .seller-avatar img{width:100%;height:100%;object-fit:cover;}
    .seller-name{font-weight:700;color:var(--noir);font-size:1rem;}
    .seller-since{font-size:.78rem;color:#333;}
    .seller-card-body{padding:1.2rem;}
    .other-annonce{display:flex;align-items:center;gap:.8rem;padding:.5rem 0;border-bottom:1px solid var(--gris-clair);}
    .other-annonce-thumb{width:50px;height:50px;border-radius:8px;background:var(--gris-clair);overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;}
    .other-annonce-thumb img{width:100%;height:100%;object-fit:cover;}
    .other-annonce-info{flex:1;min-width:0;}
    .other-annonce-title{font-size:.85rem;color:var(--blanc);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .other-annonce-price{font-size:.85rem;color:var(--jaune);font-weight:700;}
    .contact-btn{display:block;background:var(--jaune);color:var(--noir);text-align:center;padding:.9rem;border-radius:8px;font-weight:700;font-size:1rem;text-decoration:none;margin-bottom:.8rem;transition:background .2s;border:none;cursor:pointer;width:100%;font-family:'Outfit',sans-serif;}
    .contact-btn:hover{background:var(--jaune-clair);}
    .whatsapp-btn{display:block;background:#25D366;color:#fff;text-align:center;padding:.9rem;border-radius:8px;font-weight:700;font-size:1rem;text-decoration:none;margin-bottom:.8rem;border:none;cursor:pointer;width:100%;font-family:'Outfit',sans-serif;transition:background .2s;}
    .whatsapp-btn:hover{background:#20bc5a;}
    .call-btn{display:block;background:var(--gris-clair);color:var(--blanc);text-align:center;padding:.9rem;border-radius:8px;font-weight:600;font-size:1rem;text-decoration:none;border:2px solid var(--gris-clair);cursor:pointer;width:100%;font-family:'Outfit',sans-serif;transition:border-color .2s,color .2s;}
    .call-btn:hover{border-color:var(--jaune);color:var(--jaune);}
    .safety-notice{background:rgba(255,214,0,.06);border:1px solid rgba(255,214,0,.2);border-radius:8px;padding:.8rem;margin-top:1rem;font-size:.78rem;color:var(--text-muted);line-height:1.5;}
    .safety-notice strong{color:var(--jaune);}
    footer{background:#080808;border-top:1px solid var(--gris-clair);padding:1.2rem;text-align:center;color:var(--text-muted);font-size:.8rem;}
    footer a{color:var(--jaune);text-decoration:none;}
  </style>
</head>
<body>

<nav>
  <a href="index.php" class="logo">Local<span>Market</span></a>
  <span style="color:var(--text-muted);font-size:.9rem;"> <?= htmlspecialchars($annonce['ville']) ?></span>
</nav>

<div style="max-width:1100px;margin:0 auto;padding:0 2rem;">
  <div class="breadcrumb">
    <a href="index.php">Accueil</a><span>›</span>
    <a href="annonces.php">Annonces</a><span>›</span>
    <a href="annonces.php?cat=<?= $annonce['categorie_id'] ?>"><?= htmlspecialchars($annonce['categorie']) ?></a><span>›</span>
    <?= htmlspecialchars($annonce['titre']) ?>
  </div>
</div>

<div class="detail-wrapper">

  <!-- GAUCHE -->
  <div>

    <!-- GALERIE -->
    <div class="gallery-main" id="main-photo">
      <?php if (!empty($photos)): ?>
        <img src="<?= htmlspecialchars($photos[0]['url']) ?>" alt="<?= htmlspecialchars($annonce['titre']) ?>">
      <?php else: ?>
        <?= $annonce['icone'] ?? '📦' ?>
      <?php endif; ?>
    </div>

    <div class="gallery-thumbs">
      <?php if (!empty($photos)): ?>
        <?php foreach ($photos as $i => $p): ?>
          <div class="thumb <?= $i === 0 ? 'active' : '' ?>"
               onclick="selectThumb(this, '<?= htmlspecialchars($p['url']) ?>')">
            <img src="<?= htmlspecialchars($p['url']) ?>" alt="">
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="thumb active"><?= $annonce['icone'] ?? '📦' ?></div>
      <?php endif; ?>
    </div>

    <!-- INFO -->
    <div class="info-card">
      <div class="badge-row">
        <span class="badge"><?= $annonce['icone'] ?> <?= htmlspecialchars($annonce['categorie']) ?></span>
        <?php if (!empty($annonce['etat'])): ?>
          <span class="badge badge-green"><?= $etats[$annonce['etat']] ?? $annonce['etat'] ?></span>
        <?php endif; ?>
        <span class="badge badge-outline"><?= timeAgo($annonce['created_at']) ?></span>
      </div>

      <h1 class="annonce-title-big"><?= htmlspecialchars($annonce['titre']) ?></h1>

      <?php if ($annonce['est_gratuit']): ?>
        <div class="price-big free">GRATUIT</div>
      <?php else: ?>
        <div class="price-big"><?= number_format($annonce['prix'], 0, ',', ' ') ?> FCFA</div>
      <?php endif; ?>

      <div class="location-date">
        <span>📍 <?= htmlspecialchars($annonce['ville']) ?><?= !empty($annonce['quartier']) ? ', ' . htmlspecialchars($annonce['quartier']) : '' ?></span>
        <!-- ✅ nb_vues corrigé -->
        <span> <?= intval($annonce['nb_vues']) ?> vues</span>
        <span> <?= date('d/m/Y à H\hi', strtotime($annonce['created_at'])) ?></span>
      </div>

      <hr class="divider">

      <h3 style="margin-bottom:.8rem;font-size:1rem;">Description</h3>
      <p class="description-text"><?= nl2br(htmlspecialchars($annonce['description'])) ?></p>

      <hr class="divider">

      <div class="action-row">
        <button class="action-btn" id="like-btn" onclick="toggleLike()">❤️ Sauvegarder</button>
        <button class="action-btn" onclick="share()">📤 Partager</button>
        <button class="action-btn" onclick="report()">🚩 Signaler</button>
      </div>
    </div>

    <!-- MESSAGE -->
    <div class="message-form">
      <h3> Envoyer un message au vendeur</h3>
      <?php if ($connecte): ?>
        <form method="POST" action="envoyer-message.php">
          <input type="hidden" name="annonce_id" value="<?= $annonce['id'] ?>">
          <input type="hidden" name="destinataire_id" value="<?= $annonce['vendeur_id'] ?>">
          <textarea name="message" id="msg-textarea"
            placeholder="Bonjour, votre annonce m'intéresse. Est-il toujours disponible ?"></textarea>
          <div class="quick-msgs">
            <span class="quick-msg" onclick="setMsg('Bonjour, est-il toujours disponible ?')">Toujours dispo ?</span>
            <span class="quick-msg" onclick="setMsg('Quel est votre prix final ?')">Prix final ?</span>
            <span class="quick-msg" onclick="setMsg('Livraison possible ?')">Livraison ?</span>
            <span class="quick-msg" onclick="setMsg('Je suis intéressé(e), quand peut-on se rencontrer ?')">Se rencontrer ?</span>
          </div>
          <button type="submit" class="contact-btn" style="margin-bottom:0;">📨 Envoyer le message</button>
        </form>
      <?php else: ?>
        <p style="color:var(--text-muted);margin-bottom:1rem;">Connectez-vous pour envoyer un message au vendeur.</p>
        <a href="connexion.php?redirect=annonce-detail.php?id=<?= $annonce['id'] ?>" class="contact-btn">
           Se connecter pour contacter
        </a>
      <?php endif; ?>
    </div>

  </div>

  <!-- DROITE : VENDEUR -->
  <div>
    <div class="seller-card">
      <div class="seller-card-header">
        <div class="seller-avatar">
          <?php if (!empty($annonce['vendeur_photo'])): ?>
            <img src="<?= htmlspecialchars($annonce['vendeur_photo']) ?>" alt="">
          <?php else: ?>
            👤
          <?php endif; ?>
        </div>
        <div>
          <div class="seller-name"><?= htmlspecialchars($annonce['vendeur_nom']) ?></div>
          <!-- ✅ vendeur_depuis = u.created_at -->
          <div class="seller-since">
             <?= number_format($annonce['note_moyenne'], 1) ?> (<?= $annonce['nb_avis'] ?> avis)
          </div>
          <div class="seller-since">Membre depuis <?= date('M Y', strtotime($annonce['vendeur_depuis'])) ?></div>
        </div>
      </div>
      <div class="seller-card-body">

        <?php if (!empty($annonce['vendeur_tel'])): ?>
          <a href="https://wa.me/<?= preg_replace('/\D/', '', $annonce['vendeur_tel']) ?>"
             target="_blank" class="whatsapp-btn">
             WhatsApp
          </a>
          <a href="tel:<?= htmlspecialchars($annonce['vendeur_tel']) ?>" class="call-btn">
             Appeler le vendeur
          </a>
        <?php endif; ?>

        <div class="safety-notice">
          <strong> Conseils de sécurité :</strong> Ne payez jamais à l'avance. Rencontrez le vendeur dans un lieu public. Vérifiez le produit avant d'acheter.
        </div>

        <?php if (!empty($autres)): ?>
          <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--gris-clair);">
            <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:.5rem;font-weight:600;">
              Autres annonces de <?= htmlspecialchars(explode(' ', $annonce['vendeur_nom'])[0]) ?>
            </div>
            <?php foreach ($autres as $a): ?>
              <a href="annonce-detail.php?id=<?= $a['id'] ?>"
                 class="other-annonce" style="text-decoration:none;">
                <div class="other-annonce-thumb">
                  <?php if (!empty($a['photo'])): ?>
                    <img src="<?= htmlspecialchars($a['photo']) ?>" alt="">
                  <?php else: ?>
                    📦
                  <?php endif; ?>
                </div>
                <div class="other-annonce-info">
                  <div class="other-annonce-title"><?= htmlspecialchars($a['titre']) ?></div>
                  <div class="other-annonce-price">
                    <?= $a['est_gratuit'] ? 'Gratuit' : number_format($a['prix'], 0, ',', ' ') . ' FCFA' ?>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
            <a href="annonces.php?vendeur=<?= $annonce['vendeur_id'] ?>"
               style="color:var(--jaune);text-decoration:none;font-size:.82rem;display:block;margin-top:.5rem;">
              Voir toutes ses annonces →
            </a>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

</div>

<footer>
  © 2025 <a href="index.php">LocalMarket</a> · Tous droits réservés
</footer>

<script src="../assets/js/main.js"></script>
<script>
  function selectThumb(el, imgUrl) {
    document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    const main = document.getElementById('main-photo');
    main.innerHTML = imgUrl
      ? '<img src="' + imgUrl + '" alt="">'
      : main.innerHTML;
  }
  function toggleLike() {
    const btn = document.getElementById('like-btn');
    btn.classList.toggle('liked');
    btn.textContent = btn.classList.contains('liked') ? ' Sauvegardé !' : ' Sauvegarder';
  }
  function share() {
    if (navigator.share) {
      navigator.share({ title: document.title, url: window.location.href });
    } else {
      navigator.clipboard.writeText(window.location.href);
      alert('🔗 Lien copié !');
    }
  }
  function report() { alert(' Merci. Cette annonce a été signalée à notre équipe.'); }
  function setMsg(text) { document.getElementById('msg-textarea').value = text; }
</script>

</body>

<?php if (!empty($_GET['message_envoye'])): ?>
  <div style="background:rgba(76,175,80,0.15);border:1px solid #4CAF50;
              color:#80c883;padding:1rem;text-align:center;font-size:.95rem;">
     Message envoyé avec succès ! Le vendeur vous répondra bientôt.
  </div>
<?php endif; ?>
</html>