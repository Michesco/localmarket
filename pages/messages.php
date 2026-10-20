<?php
/**
 * LocalMarket — messages.php
 * Affichage de la conversation de messages
 */
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Protection : doit être connecté
if (!estConnecte()) {
    header('Location: connexion.php?redirect=messages.php');
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];

$annonceId = intval($_GET['annonce'] ?? 0);
$avecId = intval($_GET['avec'] ?? 0);

if (!$annonceId || !$avecId) {
    header('Location: mon-compte.php?onglet=messages');
    exit;
}

// Vérifier que l'annonce existe et récupérer sa photo
$stmt = $db->prepare('
    SELECT a.id, a.titre,
           (SELECT url FROM photos p WHERE p.annonce_id = a.id AND p.ordre = 0 LIMIT 1) AS photo
    FROM annonces a
    WHERE a.id = ? AND a.statut = "active"
');
$stmt->execute([$annonceId]);
$annonce = $stmt->fetch();

if (!$annonce) {
    header('Location: mon-compte.php?onglet=messages');
    exit;
}

// Vérifier que l'interlocuteur existe
$stmt = $db->prepare('SELECT id, prenom, nom, photo_profil FROM utilisateurs WHERE id = ?');
$stmt->execute([$avecId]);
$interlocuteur = $stmt->fetch();

if (!$interlocuteur) {
    header('Location: mon-compte.php?onglet=messages');
    exit;
}

// Marquer les messages comme lus
$db->prepare('UPDATE messages SET lu = 1 WHERE annonce_id = ? AND destinataire_id = ? AND expediteur_id = ?')
   ->execute([$annonceId, $userId, $avecId]);

// Récupérer les messages de la conversation avec photos de profil
$stmtMessages = $db->prepare('
    SELECT m.*, 
           CONCAT(u.prenom, " ", u.nom) AS expediteur_nom,
           u.photo_profil
    FROM messages m
    JOIN utilisateurs u ON m.expediteur_id = u.id
    WHERE m.annonce_id = ? 
      AND ((m.expediteur_id = ? AND m.destinataire_id = ?)
           OR (m.expediteur_id = ? AND m.destinataire_id = ?))
    ORDER BY m.created_at ASC
');
$stmtMessages->execute([$annonceId, $userId, $avecId, $avecId, $userId]);
$messages = $stmtMessages->fetchAll();

// Traitement envoi de message
$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contenu = trim($_POST['message'] ?? '');
    
    if (empty($contenu)) {
        $erreur = '⚠️ Le message ne peut pas être vide.';
    } else {
        $stmt = $db->prepare('
            INSERT INTO messages (annonce_id, expediteur_id, destinataire_id, contenu, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ');
        $stmt->execute([$annonceId, $userId, $avecId, $contenu]);
        
        // Rediriger pour éviter renvoi
        header('Location: messages.php?annonce=' . $annonceId . '&avec=' . $avecId);
        exit;
    }
}

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
  <title>Messages — LocalMarket</title>
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
    
    .container{max-width:900px;margin:0 auto;padding:2rem 1.5rem;flex:1;}
    
    .header-card{background:var(--gris);border:2px solid var(--gris-clair);border-radius:16px;padding:1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;}
    .back-link{color:var(--text-muted);text-decoration:none;font-size:.9rem;display:flex;align-items:center;gap:.4rem;}
    .back-link:hover{color:var(--jaune);}
    .annonce-info{flex:1;}
    .annonce-title{font-weight:700;font-size:1rem;color:var(--jaune);}
    .interlocutor-info{font-size:.85rem;color:var(--text-muted);}
    
    .messages-container{background:var(--gris);border:2px solid var(--gris-clair);border-radius:16px;padding:1.5rem;margin-bottom:1.5rem;max-height:500px;overflow-y:auto;}
    .message-row{display:flex;gap:.8rem;margin-bottom:1rem;align-items:flex-start;}
    .message-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--gris-clair),var(--gris));display:flex;align-items:center;justify-content:center;font-size:1.2rem;overflow:hidden;border:1px solid var(--gris-clair);flex-shrink:0;}
    .message-avatar img{width:100%;height:100%;object-fit:cover;}
    .message-bubble{padding:.9rem 1.1rem;border-radius:12px;max-width:80%;line-height:1.5;font-size:.95rem;}
    .message-sent{background:var(--jaune);color:var(--noir);margin-left:auto;}
    .message-received{background:var(--gris-clair);color:var(--blanc);}
    .message-time{font-size:.7rem;color:var(--text-muted);margin-top:.4rem;}
    .message-sent .message-time{color:#333;}
    .annonce-thumb{width:50px;height:50px;border-radius:8px;background:var(--gris-clair);overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:1.5rem;border:1px solid var(--gris-clair);flex-shrink:0;}
    .annonce-thumb img{width:100%;height:100%;object-fit:cover;}
    
    .form-card{background:var(--gris);border:2px solid var(--gris-clair);border-radius:16px;padding:1.5rem;}
    .form-card h3{font-size:1rem;margin-bottom:1rem;font-weight:600;color:var(--jaune);}
    textarea{width:100%;background:var(--gris-clair);border:2px solid var(--gris-clair);border-radius:8px;padding:.9rem;color:var(--blanc);font-family:'Outfit',sans-serif;font-size:.95rem;resize:vertical;min-height:100px;outline:none;margin-bottom:1rem;}
    textarea:focus{border-color:var(--jaune);}
    .btn{background:var(--jaune);color:var(--noir);padding:.8rem 1.5rem;border-radius:8px;font-weight:700;font-family:'Outfit',sans-serif;font-size:.95rem;border:none;cursor:pointer;transition:background .2s;}
    .btn:hover{background:var(--jaune-clair);}
    
    .alert{padding:.9rem 1rem;border-radius:8px;margin-bottom:1.2rem;font-size:.9rem;}
    .alert-error{background:rgba(255,77,77,.15);border:1px solid var(--rouge);color:#ff8080;}
    
    .empty{text-align:center;padding:3rem 1rem;color:var(--text-muted);}
    
    footer{background:#080808;border-top:1px solid var(--gris-clair);padding:1.2rem;text-align:center;color:var(--text-muted);font-size:.8rem;}
    footer a{color:var(--jaune);text-decoration:none;}
  </style>
</head>
<body>

<nav>
  <a href="index.php" class="logo">Local<span>Market</span></a>
  <span style="color:var(--text-muted);font-size:.9rem;">💬 Messages</span>
</nav>

<div class="container">
  
  <div class="header-card">
    <a href="mon-compte.php?onglet=messages" class="back-link">← Retour</a>
    <div class="annonce-thumb">
      <?php if (!empty($annonce['photo'])): ?>
        <img src="<?= htmlspecialchars($annonce['photo']) ?>" alt="<?= htmlspecialchars($annonce['titre']) ?>">
      <?php else: ?>
        📋
      <?php endif; ?>
    </div>
    <div class="annonce-info">
      <div class="annonce-title"><?= htmlspecialchars($annonce['titre']) ?></div>
      <div class="interlocutor-info" style="display:flex;align-items:center;gap:.5rem;">
        <div style="width:35px;height:35px;border-radius:50%;background:linear-gradient(135deg,var(--gris-clair),var(--gris));display:flex;align-items:center;justify-content:center;font-size:1.1rem;overflow:hidden;border:1px solid var(--gris-clair);">
          <?php if (!empty($interlocuteur['photo_profil'])): ?>
            <img src="<?= htmlspecialchars($interlocuteur['photo_profil']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
          <?php else: ?>
            👤
          <?php endif; ?>
        </div>
        Conversation avec <?= htmlspecialchars($interlocuteur['prenom'] . ' ' . $interlocuteur['nom']) ?>
      </div>
    </div>
  </div>

  <?php if ($erreur): ?>
    <div class="alert alert-error"><?= $erreur ?></div>
  <?php endif; ?>

  <div class="messages-container">
    <?php if (empty($messages)): ?>
      <div class="empty">
        <div style="font-size:3rem;margin-bottom:1rem;">💬</div>
        <p>Aucun message dans cette conversation.</p>
        <p style="margin-top:.5rem;">Envoyez le premier message !</p>
      </div>
    <?php else: ?>
      <?php foreach ($messages as $m): ?>
        <div class="message-row">
          <div class="message-avatar">
            <?php if (!empty($m['photo_profil'])): ?>
              <img src="<?= htmlspecialchars($m['photo_profil']) ?>" alt="">
            <?php else: ?>
              👤
            <?php endif; ?>
          </div>
          <div class="message-bubble <?= $m['expediteur_id'] == $userId ? 'message-sent' : 'message-received' ?>">
            <?= nl2br(htmlspecialchars($m['contenu'])) ?>
            <div class="message-time"><?= timeAgo($m['created_at']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="form-card">
    <h3>💬 Envoyer un message</h3>
    <form method="POST">
      <textarea name="message" placeholder="Écrivez votre message..." required></textarea>
      <button type="submit" class="btn">📨 Envoyer</button>
    </form>
  </div>

</div>

<footer>
  © 2025 <a href="index.php">LocalMarket</a> · Tous droits réservés
</footer>

<script src="../assets/js/main.js"></script>
</body>
</html>
 