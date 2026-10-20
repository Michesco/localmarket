<?php
/**
 * LocalMarket — modifier-annonce.php
 * Fichier à placer dans : pages/modifier-annonce.php
 */
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (!estConnecte()) {
    header('Location: connexion.php?redirect=mon-compte.php?onglet=annonces');
    exit;
}

$db     = getDB();
$userId = $_SESSION['user_id'];
$id     = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: mon-compte.php?onglet=annonces');
    exit;
}

// Vérifier que l'annonce appartient à l'utilisateur
$stmt = $db->prepare('SELECT * FROM annonces WHERE id = ? AND utilisateur_id = ?');
$stmt->execute([$id, $userId]);
$annonce = $stmt->fetch();

if (!$annonce) {
    header('Location: mon-compte.php?onglet=annonces');
    exit;
}

// Charger les catégories
$categories = $db->query('SELECT * FROM categories ORDER BY nom')->fetchAll();

// Charger les photos existantes
$stmtPhotos = $db->prepare('SELECT * FROM photos WHERE annonce_id = ? ORDER BY ordre');
$stmtPhotos->execute([$id]);
$photos = $stmtPhotos->fetchAll();

$succes = '';
$erreur = '';

// ── Traitement du formulaire ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre       = trim($_POST['titre']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $categorie   = (int)($_POST['categorie_id'] ?? 0);
    $prix        = (float)($_POST['prix']      ?? 0);
    $est_gratuit = isset($_POST['est_gratuit']) ? 1 : 0;
    $etat        = $_POST['etat']   ?? 'bon';
    $ville       = trim($_POST['ville']  ?? '');
    $quartier    = trim($_POST['quartier'] ?? '');
    $statut      = $_POST['statut'] ?? 'active';

    if (empty($titre) || empty($description) || empty($categorie) || empty($ville)) {
        $erreur = '⚠️ Veuillez remplir tous les champs obligatoires.';
    } else {
        // Mettre à jour l'annonce
        $stmt = $db->prepare('
            UPDATE annonces
            SET titre=?, description=?, categorie_id=?, prix=?, est_gratuit=?,
                etat=?, ville=?, quartier=?, statut=?, updated_at=NOW()
            WHERE id=? AND utilisateur_id=?
        ');
        $stmt->execute([
            $titre, $description, $categorie, $est_gratuit ? 0 : $prix,
            $est_gratuit, $etat, $ville, $quartier, $statut, $id, $userId
        ]);

        // ── Upload de nouvelles photos ────────────────
        if (!empty($_FILES['photos']['name'][0])) {
            $upload_dir    = '../assets/images/uploads/';
            $extensions_ok = ['jpg', 'jpeg', 'png', 'webp'];
            $max_size      = 5 * 1024 * 1024;

            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            // Compter les photos existantes
            $nbExistantes = count($photos);
            $nbAjouter    = min(count($_FILES['photos']['name']), 8 - $nbExistantes);

            for ($i = 0; $i < $nbAjouter; $i++) {
                $file = [
                    'name'     => $_FILES['photos']['name'][$i],
                    'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                    'size'     => $_FILES['photos']['size'][$i],
                    'error'    => $_FILES['photos']['error'][$i],
                ];
                if ($file['error'] !== UPLOAD_ERR_OK) continue;
                if ($file['size'] > $max_size) continue;
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $extensions_ok)) continue;
                if (!getimagesize($file['tmp_name'])) continue;

                $nouveau_nom = 'img_' . uniqid() . '.' . $ext;
                $destination = $upload_dir . $nouveau_nom;
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $ordre = $nbExistantes + $i;
                    $db->prepare('INSERT INTO photos (annonce_id, url, ordre) VALUES (?,?,?)')
                       ->execute([$id, '/LocalMarket/assets/images/uploads/' . $nouveau_nom, $ordre]);
                }
            }
        }

        // ── Supprimer photos cochées ──────────────────
        if (!empty($_POST['supprimer_photos'])) {
            foreach ($_POST['supprimer_photos'] as $photoId) {
                $photoId = (int)$photoId;
                // Récupérer le chemin pour supprimer le fichier
                $p = $db->prepare('SELECT url FROM photos WHERE id = ? AND annonce_id = ?');
                $p->execute([$photoId, $id]);
                $photo = $p->fetch();
                if ($photo) {
                    $fichier = __DIR__ . '/../' . ltrim($photo['url'], '/LocalMarket/');
                    if (file_exists($fichier)) unlink($fichier);
                    $db->prepare('DELETE FROM photos WHERE id = ?')->execute([$photoId]);
                }
            }
        }

        $succes = '✅ Annonce modifiée avec succès.';

        // Recharger les photos
        $stmtPhotos->execute([$id]);
        $photos = $stmtPhotos->fetchAll();
    }
}

$villes = ['Abidjan','Dakar','Douala','Lomé','Cotonou','Bamako','Ouagadougou','Autre'];
$etats  = ['neuf' => 'Neuf', 'tres_bon' => 'Très bon état', 'bon' => 'Bon état', 'correct' => 'État correct', 'mauvais' => 'Mauvais état'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Modifier l'annonce — LocalMarket</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root{--jaune:#FFD600;--jaune-clair:#FFF176;--noir:#0D0D0D;--gris:#1A1A1A;--gris-clair:#2A2A2A;--blanc:#FAFAFA;--text-muted:#888;--vert:#4CAF50;--rouge:#FF4D4D;}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Outfit',sans-serif;background:var(--noir);color:var(--blanc);min-height:100vh;}
    nav{background:var(--noir);border-bottom:3px solid var(--jaune);padding:0 2rem;display:flex;align-items:center;justify-content:space-between;height:70px;}
    .logo{font-family:'Bebas Neue',sans-serif;font-size:2rem;color:var(--jaune);letter-spacing:2px;text-decoration:none;}
    .logo span{color:var(--blanc);}
    .nav-link{color:var(--text-muted);text-decoration:none;font-size:.9rem;}
    .nav-link:hover{color:var(--jaune);}

    .wrapper{max-width:800px;margin:0 auto;padding:2rem 1.5rem;}
    .page-title{font-family:'Bebas Neue',sans-serif;font-size:2.2rem;letter-spacing:2px;color:var(--jaune);margin-bottom:1.5rem;}

    .alert{padding:.9rem 1rem;border-radius:8px;margin-bottom:1.2rem;font-size:.9rem;}
    .alert-error{background:rgba(255,77,77,.15);border:1px solid var(--rouge);color:#ff8080;}
    .alert-success{background:rgba(76,175,80,.15);border:1px solid var(--vert);color:#80c883;}

    .form-card{background:var(--gris);border:2px solid var(--gris-clair);border-radius:16px;padding:1.8rem;margin-bottom:1.5rem;}
    .form-card h2{font-family:'Bebas Neue',sans-serif;font-size:1.4rem;letter-spacing:1px;color:var(--jaune);margin-bottom:1.2rem;display:flex;align-items:center;gap:.5rem;}

    .form-group{margin-bottom:1.2rem;}
    label{display:block;font-weight:600;font-size:.88rem;margin-bottom:.4rem;color:var(--blanc);}
    label span{color:var(--jaune);}
    input[type=text],input[type=number],textarea,select{width:100%;background:var(--gris-clair);border:2px solid var(--gris-clair);border-radius:8px;padding:.8rem 1rem;color:var(--blanc);font-family:'Outfit',sans-serif;font-size:.9rem;outline:none;transition:border-color .2s;}
    input:focus,textarea:focus,select:focus{border-color:var(--jaune);}
    input::placeholder,textarea::placeholder{color:var(--text-muted);}
    textarea{resize:vertical;min-height:120px;}
    .input-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
    @media(max-width:600px){.input-row{grid-template-columns:1fr;}}

    .checkbox-label{display:flex;align-items:center;gap:.6rem;cursor:pointer;font-size:.9rem;}
    .checkbox-label input{width:auto;}

    /* PHOTOS EXISTANTES */
    .photos-existantes{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:1rem;margin-bottom:1rem;}
    .photo-item{position:relative;border-radius:10px;overflow:hidden;border:2px solid var(--gris-clair);}
    .photo-item img{width:100%;height:110px;object-fit:cover;display:block;}
    .photo-item .del-checkbox{position:absolute;top:6px;right:6px;background:rgba(0,0,0,.7);border-radius:6px;padding:4px 6px;display:flex;align-items:center;gap:4px;font-size:.72rem;color:#fff;cursor:pointer;}
    .photo-item .del-checkbox input{width:auto;margin:0;}
    .photo-principale{position:absolute;bottom:0;left:0;right:0;background:rgba(255,214,0,.85);color:var(--noir);font-size:.68rem;font-weight:700;text-align:center;padding:2px;}

    /* UPLOAD */
    .photo-upload-area{display:block;border:2px dashed var(--gris-clair);border-radius:10px;padding:1.5rem;text-align:center;cursor:pointer;transition:border-color .2s;}
    .photo-upload-area:hover{border-color:var(--jaune);}

    /* STATUT */
    .statut-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem;}
    .statut-opt{border:2px solid var(--gris-clair);border-radius:10px;padding:.8rem;text-align:center;cursor:pointer;transition:all .2s;font-size:.85rem;font-weight:600;}
    .statut-opt:hover{border-color:var(--jaune);}
    .statut-opt.selected{border-color:var(--jaune);background:rgba(255,214,0,.1);color:var(--jaune);}

    .btn{background:var(--jaune);color:var(--noir);padding:.9rem 1.8rem;border-radius:8px;font-weight:700;font-family:'Outfit',sans-serif;font-size:.95rem;border:none;cursor:pointer;transition:background .2s;}
    .btn:hover{background:var(--jaune-clair);}
    .btn-outline{background:transparent;border:2px solid var(--gris-clair);color:var(--blanc);}
    .btn-outline:hover{border-color:var(--jaune);color:var(--jaune);}
    .actions-bar{display:flex;gap:1rem;justify-content:flex-end;flex-wrap:wrap;}

    footer{background:#080808;border-top:1px solid var(--gris-clair);padding:1.2rem;text-align:center;color:var(--text-muted);font-size:.8rem;margin-top:2rem;}
    footer a{color:var(--jaune);text-decoration:none;}
  </style>
</head>
<body>

<nav>
  <a href="../index.php" class="logo">Local<span>Market</span></a>
  <a href="mon-compte.php?onglet=annonces" class="nav-link">← Retour à mes annonces</a>
</nav>

<div class="wrapper">
  <div class="page-title">✏️ Modifier l'annonce</div>

  <?php if ($succes): ?>
    <div class="alert alert-success"><?= $succes ?></div>
  <?php endif; ?>
  <?php if ($erreur): ?>
    <div class="alert alert-error"><?= $erreur ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">

    <!-- BLOC 1 : Informations générales -->
    <div class="form-card">
      <h2>📋 Informations générales</h2>

      <div class="form-group">
        <label>Titre de l'annonce <span>*</span></label>
        <input type="text" name="titre"
               value="<?= htmlspecialchars($annonce['titre']) ?>"
               placeholder="Ex: iPhone 14 Pro — 256 Go" required>
      </div>

      <div class="form-group">
        <label>Catégorie <span>*</span></label>
        <select name="categorie_id" required>
          <option value="">-- Choisir une catégorie --</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"
              <?= $annonce['categorie_id'] == $c['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['icone'] . ' ' . $c['nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Description <span>*</span></label>
        <textarea name="description" placeholder="Décrivez votre produit..." required><?= htmlspecialchars($annonce['description']) ?></textarea>
      </div>

      <div class="form-group">
        <label>État du produit <span>*</span></label>
        <select name="etat" required>
          <?php foreach ($etats as $val => $label): ?>
            <option value="<?= $val ?>" <?= $annonce['etat'] === $val ? 'selected' : '' ?>>
              <?= $label ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- BLOC 2 : Prix -->
    <div class="form-card">
      <h2>💰 Prix</h2>

      <div class="form-group">
        <label class="checkbox-label">
          <input type="checkbox" name="est_gratuit" id="est_gratuit"
                 <?= $annonce['est_gratuit'] ? 'checked' : '' ?>
                 onchange="togglePrix(this)">
          Donner gratuitement
        </label>
      </div>

      <div class="form-group" id="prix-group" style="<?= $annonce['est_gratuit'] ? 'display:none' : '' ?>">
        <label>Prix (FCFA) <span>*</span></label>
        <input type="number" name="prix" id="prix"
               value="<?= htmlspecialchars($annonce['prix']) ?>"
               placeholder="Ex: 150000" min="0">
      </div>
    </div>

    <!-- BLOC 3 : Localisation -->
    <div class="form-card">
      <h2>📍 Localisation</h2>
      <div class="input-row">
        <div class="form-group">
          <label>Ville <span>*</span></label>
          <select name="ville" required>
            <?php foreach ($villes as $v): ?>
              <option value="<?= $v ?>" <?= $annonce['ville'] === $v ? 'selected' : '' ?>>
                <?= $v ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Quartier</label>
          <input type="text" name="quartier"
                 value="<?= htmlspecialchars($annonce['quartier'] ?? '') ?>"
                 placeholder="Ex: Cocody, Plateau...">
        </div>
      </div>
    </div>

    <!-- BLOC 4 : Photos -->
    <div class="form-card">
      <h2>📷 Photos</h2>

      <?php if (!empty($photos)): ?>
        <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:.8rem;">
          Cochez les photos à supprimer :
        </p>
        <div class="photos-existantes">
          <?php foreach ($photos as $i => $p): ?>
            <div class="photo-item">
              <img src="<?= htmlspecialchars($p['url']) ?>" alt="Photo <?= $i+1 ?>">
              <?php if ($i === 0): ?>
                <div class="photo-principale">⭐ Principale</div>
              <?php endif; ?>
              <label class="del-checkbox">
                <input type="checkbox" name="supprimer_photos[]" value="<?= $p['id'] ?>">
                🗑️
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (count($photos) < 8): ?>
        <label class="photo-upload-area" for="photos">
          <input type="file" id="photos" name="photos[]"
                 accept="image/*" multiple style="display:none"
                 onchange="previewPhotos(event)">
          <div style="font-size:2rem;margin-bottom:.5rem;">📁</div>
          <div style="font-weight:600;margin-bottom:.3rem;">
            Ajouter des photos (<?= count($photos) ?>/8)
          </div>
          <div style="font-size:.82rem;color:var(--text-muted);">
            JPEG, PNG, WEBP · Max 5 Mo par photo
          </div>
        </label>
        <div id="preview-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:1rem;margin-top:1rem;"></div>
      <?php else: ?>
        <p style="color:var(--text-muted);font-size:.85rem;">
          Nombre maximum de photos atteint (8/8). Supprimez une photo pour en ajouter.
        </p>
      <?php endif; ?>
    </div>

    <!-- BLOC 5 : Statut -->
    <div class="form-card">
      <h2>📌 Statut de l'annonce</h2>
      <div class="statut-grid">
        <?php
        $statuts = ['active' => '✅ Active', 'vendue' => '🏷️ Vendue', 'expiree' => '⏸️ Suspendue'];
        foreach ($statuts as $val => $label):
        ?>
          <div class="statut-opt <?= $annonce['statut'] === $val ? 'selected' : '' ?>"
               onclick="selectStatut('<?= $val ?>', this)">
            <?= $label ?>
          </div>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="statut" id="statut-input" value="<?= htmlspecialchars($annonce['statut']) ?>">
    </div>

    <!-- ACTIONS -->
    <div class="actions-bar">
      <a href="mon-compte.php?onglet=annonces" class="btn btn-outline">Annuler</a>
      <button type="submit" class="btn">💾 Enregistrer les modifications</button>
    </div>

  </form>
</div>

<footer>
  © 2025 <a href="../index.php">LocalMarket</a> · Tous droits réservés
</footer>

<script>
function togglePrix(cb) {
  document.getElementById('prix-group').style.display = cb.checked ? 'none' : '';
  document.getElementById('prix').required = !cb.checked;
}

function selectStatut(val, el) {
  document.querySelectorAll('.statut-opt').forEach(o => o.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('statut-input').value = val;
}

function previewPhotos(event) {
  const grid = document.getElementById('preview-grid');
  grid.innerHTML = '';
  const files = Array.from(event.target.files);
  files.slice(0, <?= 8 - count($photos) ?>).forEach(file => {
    const reader = new FileReader();
    reader.onload = e => {
      const div = document.createElement('div');
      div.style.cssText = 'border-radius:10px;overflow:hidden;border:2px solid #2A2A2A;';
      div.innerHTML = `<img src="${e.target.result}" style="width:100%;height:110px;object-fit:cover;display:block;">`;
      grid.appendChild(div);
    };
    reader.readAsDataURL(file);
  });
}
</script>
</body>
</html>