<?php
/**
 * LocalMarket — publier.php
 * Formulaire de publication d'annonce avec enregistrement BDD + upload photo
 */
session_start();
require_once '../backend/php/db.php';
require_once '../backend/php/annonces.php';

// ── Authentification requise ──────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: connexion.php?redirect=publier.php');
    exit;
}

$user_id  = $_SESSION['user_id'];
$user_nom = $_SESSION['user_nom'] ?? '';
$erreur   = '';
$succes   = '';
$champs   = [];

// ── Récupérer les catégories ──────────────────────────
$db         = getDB();
$categories = $db->query('SELECT * FROM categories ORDER BY nom')->fetchAll();

// ── Traitement du formulaire POST ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $champs = [
        'categorie_id' => (int)($_POST['categorie_id'] ?? 0),
        'titre'        => trim($_POST['titre']        ?? ''),
        'description'  => trim($_POST['description']  ?? ''),
        'etat'         => $_POST['etat']              ?? 'bon',
        'est_gratuit'  => isset($_POST['est_gratuit']) ? 1 : 0,
        'prix'         => trim($_POST['prix']         ?? ''),
        'ville'        => trim($_POST['ville']        ?? ''),
        'quartier'     => trim($_POST['quartier']     ?? ''),
        'telephone'    => trim($_POST['telephone']    ?? ''),
    ];

    // ── Validations ──────────────────────────────────
    if (!$champs['categorie_id']) {
        $erreur = '⚠️ Veuillez choisir une catégorie.';
    } elseif (strlen($champs['titre']) < 5) {
        $erreur = '⚠️ Le titre doit contenir au moins 5 caractères.';
    } elseif (strlen($champs['description']) < 20) {
        $erreur = '⚠️ La description doit contenir au moins 20 caractères.';
    } elseif (!$champs['est_gratuit'] && (!is_numeric($champs['prix']) || $champs['prix'] < 0)) {
        $erreur = '⚠️ Veuillez saisir un prix valide ou cocher "Gratuit".';
    } elseif (empty($champs['ville'])) {
        $erreur = '⚠️ Veuillez indiquer votre ville.';
    } elseif (empty($champs['telephone'])) {
        $erreur = '⚠️ Le numéro de téléphone est requis.';
    } else {

        // ── Upload des photos ─────────────────────────
        $photos_urls = [];
        $upload_dir  = '../assets/images/uploads/';

        // Créer le dossier si inexistant
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (!empty($_FILES['photos']['name'][0])) {
            $extensions_ok = ['jpg', 'jpeg', 'png', 'webp'];
            $max_size      = 5 * 1024 * 1024; // 5 Mo
            $nb_photos     = count($_FILES['photos']['name']);
            $nb_photos     = min($nb_photos, 8); // max 8 photos

            for ($i = 0; $i < $nb_photos; $i++) {
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

                // Vérification que c'est bien une image
                $info = getimagesize($file['tmp_name']);
                if (!$info) continue;

                // Nom unique pour éviter les conflits
                $nouveau_nom = 'img_' . uniqid() . '.' . $ext;
                $destination = $upload_dir . $nouveau_nom;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                  $photos_urls[] = '/LocalMarket/assets/images/uploads/' . $nouveau_nom;
                }
            }
        }

        // ── Insertion en base de données ──────────────
        $stmt = $db->prepare('
            INSERT INTO annonces
              (utilisateur_id, categorie_id, titre, description,
               prix, est_gratuit, etat, ville, quartier,
               expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?,
                    DATE_ADD(NOW(), INTERVAL 60 DAY))
        ');
        $stmt->execute([
            $user_id,
            $champs['categorie_id'],
            htmlspecialchars($champs['titre']),
            htmlspecialchars($champs['description']),
            $champs['est_gratuit'] ? null : $champs['prix'],
            $champs['est_gratuit'],
            $champs['etat'],
            htmlspecialchars($champs['ville']),
            htmlspecialchars($champs['quartier']),
        ]);

        $annonce_id = $db->lastInsertId();

        // Enregistrer les photos
        foreach ($photos_urls as $ordre => $url) {
            $db->prepare('INSERT INTO photos (annonce_id, url, ordre) VALUES (?, ?, ?)')
               ->execute([$annonce_id, $url, $ordre]);
        }

        // Mettre à jour le téléphone si différent
        $db->prepare('UPDATE utilisateurs SET telephone = ? WHERE id = ?')
           ->execute([$champs['telephone'], $user_id]);

        // ✅ Rediriger vers la page de l'annonce créée
        header('Location: annonce-detail.php?id=' . $annonce_id . '&publie=1');
        exit;
    }
}

// Villes
$villes = ['Abidjan','Dakar','Douala','Lomé','Cotonou','Bamako','Ouagadougou','Autre'];
// États
$etats = [
    'neuf'      => 'Neuf — jamais utilisé',
    'tres_bon'  => 'Très bon état — légèrement utilisé',
    'bon'       => 'Bon état — quelques traces d\'usure',
    'correct'   => 'État correct — défauts visibles',
    'pieces'    => 'Pour pièces — ne fonctionne pas',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Publier une annonce — LocalMarket</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/main.css">
  <link rel="stylesheet" href="../assets/css/navbar.css">
  <style>
    .publish-wrapper { max-width:850px; margin:2.5rem auto; padding:0 1.5rem; display:grid; grid-template-columns:1fr 270px; gap:2rem; align-items:start; }
    @media(max-width:700px){ .publish-wrapper{grid-template-columns:1fr;} .preview-col{display:none;} }
    .form-card { background:var(--gris); border:2px solid var(--gris-clair); border-radius:14px; overflow:hidden; margin-bottom:1.5rem; }
    .form-card-header { background:var(--jaune); padding:1.2rem 1.5rem; display:flex; align-items:center; gap:.8rem; }
    .form-card-header h2 { font-family:'Bebas Neue',sans-serif; font-size:1.4rem; color:var(--noir); letter-spacing:1px; }
    .form-card-body { padding:1.8rem 1.5rem; }
    .form-group { margin-bottom:1.4rem; }
    .input-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .char-count { text-align:right; font-size:.78rem; color:var(--text-muted); margin-top:.3rem; }
    .photo-upload-area { border:2px dashed var(--gris-clair); border-radius:10px; padding:2rem; text-align:center; cursor:pointer; transition:border-color .2s; }
    .photo-upload-area:hover { border-color:var(--jaune); }
    .upload-icon { font-size:2.5rem; margin-bottom:.8rem; }
    .photo-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:.7rem; margin-top:1rem; }
    .photo-thumb { aspect-ratio:1; background:var(--gris-clair); border-radius:8px; border:2px solid var(--gris-clair); display:flex; align-items:center; justify-content:center; font-size:1.5rem; overflow:hidden; }
    .photo-thumb img { width:100%; height:100%; object-fit:cover; }
    .price-row { display:grid; grid-template-columns:1fr auto; gap:.8rem; align-items:start; }
    .gratuit-toggle { display:flex; align-items:center; gap:.6rem; background:var(--gris-clair); padding:.85rem 1rem; border-radius:8px; cursor:pointer; border:2px solid var(--gris-clair); white-space:nowrap; transition:border-color .2s; }
    .gratuit-toggle:hover { border-color:var(--jaune); }
    .gratuit-toggle input { accent-color:var(--jaune); width:16px; height:16px; }
    .btn-submit { background:var(--jaune); color:var(--noir); padding:1rem; border-radius:8px; font-weight:700; font-family:'Outfit',sans-serif; font-size:1rem; border:none; cursor:pointer; width:100%; transition:background .2s; }
    .btn-submit:hover { background:var(--jaune-clair); }
    /* Preview */
    .preview-box { background:var(--gris); border:2px solid var(--gris-clair); border-radius:14px; overflow:hidden; position:sticky; top:90px; }
    .preview-header { background:var(--gris-clair); padding:.8rem 1rem; font-size:.8rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:1px; }
    .preview-body { padding:1rem; }
    .preview-img { width:100%; height:150px; background:var(--gris-clair); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:3rem; margin-bottom:1rem; overflow:hidden; }
    .preview-img img { width:100%; height:100%; object-fit:cover; }
    .preview-price { font-family:'Bebas Neue',sans-serif; font-size:1.5rem; color:var(--jaune); letter-spacing:1px; }
    .tips-box { background:var(--gris); border:2px solid var(--gris-clair); border-radius:14px; padding:1.2rem; margin-top:1.2rem; }
    .tips-box h4 { font-size:.85rem; color:var(--jaune); margin-bottom:.6rem; font-weight:700; }
    .tips-box li { font-size:.8rem; color:var(--text-muted); margin-bottom:.4rem; padding-left:1rem; position:relative; }
    .tips-box li::before { content:'✓'; color:var(--jaune); position:absolute; left:0; font-weight:700; }
    footer { background:#080808; border-top:1px solid var(--gris-clair); padding:1.2rem; text-align:center; color:var(--text-muted); font-size:.8rem; }
    footer a { color:var(--jaune); text-decoration:none; }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <a href="../index.php" class="navbar-logo">Local<span>Market</span></a>
  <button class="navbar-toggle" onclick="this.nextElementSibling.classList.toggle('open')">
    <span></span><span></span><span></span>
  </button>
  <ul class="navbar-menu">
    <li><a href="annonces.php">Annonces</a></li>
    <li style="color:var(--jaune);font-weight:600;">+ Publier</li>
    <li><a href="mon-compte.php">👤 <?= htmlspecialchars($user_nom) ?></a></li>
    <li><a href="deconnexion.php" class="btn btn-outline">Déconnexion</a></li>
  </ul>
</nav>

<!-- FORMULAIRE -->
<form method="POST" action="publier.php"
      enctype="multipart/form-data" id="publish-form">
<div class="publish-wrapper">

  <!-- COLONNE GAUCHE : FORMULAIRE -->
  <div>

    <?php if ($erreur): ?>
      <div class="alert alert-error" style="margin-bottom:1.5rem;"><?= $erreur ?></div>
    <?php endif; ?>

    <!-- BLOC 1 : Annonce -->
    <div class="form-card">
      <div class="form-card-header">
        <span>📝</span><h2>Votre annonce</h2>
      </div>
      <div class="form-card-body">

        <div class="form-group">
          <label for="categorie_id">Catégorie <span style="color:var(--jaune)">*</span></label>
          <select id="categorie_id" name="categorie_id" required>
            <option value="">-- Choisissez une catégorie --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"
                <?= (($champs['categorie_id'] ?? 0) == $cat['id']) ? 'selected' : '' ?>>
                <?= $cat['icone'] ?> <?= htmlspecialchars($cat['nom']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="titre">
            Titre de l'annonce <span style="color:var(--jaune)">*</span>
            <span style="font-weight:400;color:var(--text-muted);font-size:.8rem;">— Soyez précis</span>
          </label>
          <input type="text" id="titre" name="titre" maxlength="80"
                 value="<?= htmlspecialchars($champs['titre'] ?? '') ?>"
                 placeholder="Ex: iPhone 13 Pro 256 Go — Bleu sierra"
                 oninput="updatePreview(); updateCount('titre','titre-count')"
                 required>
          <div class="char-count"><span id="titre-count">0</span>/80</div>
        </div>

        <div class="form-group">
          <label for="description">Description <span style="color:var(--jaune)">*</span></label>
          <textarea id="description" name="description" maxlength="1500" rows="6"
                    oninput="updateCount('description','desc-count')"
                    placeholder="Décrivez votre produit : état, caractéristiques, accessoires inclus..."
                    required><?= htmlspecialchars($champs['description'] ?? '') ?></textarea>
          <div class="char-count"><span id="desc-count">0</span>/1500</div>
        </div>

        <div class="form-group">
          <label for="etat">État du produit</label>
          <select id="etat" name="etat">
            <?php foreach ($etats as $val => $label): ?>
              <option value="<?= $val ?>"
                <?= (($champs['etat'] ?? 'bon') === $val) ? 'selected' : '' ?>>
                <?= $label ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

      </div>
    </div>

    <!-- BLOC 2 : Photos -->
    <div class="form-card">
      <div class="form-card-header">
        <span>📷</span><h2>Photos du produit</h2>
      </div>
      <div class="form-card-body">
        <p style="color:var(--text-muted);font-size:.88rem;margin-bottom:1rem;">
          Les annonces avec photos reçoivent 5× plus de réponses. Max 8 photos, 5 Mo chacune.
        </p>
        <label class="photo-upload-area" for="photos">
          <input type="file" id="photos" name="photos[]"
                 accept="image/*" multiple
                 onchange="previewPhotos(event)" style="display:none">
          <div class="upload-icon">📁</div>
          <div style="font-weight:600;margin-bottom:.3rem;">Cliquez pour ajouter des photos</div>
          <div style="font-size:.82rem;color:var(--text-muted);">JPEG, PNG, WEBP · Max 5 Mo par photo</div>
        </label>
        <div class="photo-grid" id="photo-grid">
          <div class="photo-thumb">📷</div>
          <div class="photo-thumb">📷</div>
          <div class="photo-thumb">📷</div>
          <div class="photo-thumb">📷</div>
        </div>
      </div>
    </div>

    <!-- BLOC 3 : Prix & Localisation -->
    <div class="form-card">
      <div class="form-card-header">
        <span>💰</span><h2>Prix & Localisation</h2>
      </div>
      <div class="form-card-body">

        <div class="form-group">
          <label>Prix <span style="color:var(--jaune)">*</span></label>
          <div class="price-row">
            <input type="number" id="prix" name="prix" min="0"
                   value="<?= htmlspecialchars($champs['prix'] ?? '') ?>"
                   placeholder="Ex: 150000"
                   oninput="updatePreview()">
            <label class="gratuit-toggle">
              <input type="checkbox" name="est_gratuit" id="est-gratuit"
                     <?= !empty($champs['est_gratuit']) ? 'checked' : '' ?>
                     onchange="toggleGratuit()">
              Gratuit / Don
            </label>
          </div>
        </div>

        <div class="input-row">
          <div class="form-group">
            <label for="ville">Ville <span style="color:var(--jaune)">*</span></label>
            <select id="ville" name="ville" required>
              <option value="">Choisir...</option>
              <?php foreach ($villes as $v): ?>
                <option value="<?= $v ?>"
                  <?= (($champs['ville'] ?? '') === $v) ? 'selected' : '' ?>>
                  <?= $v ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="quartier">Quartier / Zone</label>
            <input type="text" id="quartier" name="quartier"
                   value="<?= htmlspecialchars($champs['quartier'] ?? '') ?>"
                   placeholder="Ex: Hédjranawé, Assiganmin...">
          </div>
        </div>

      </div>
    </div>

    <!-- BLOC 4 : Contact -->
    <div class="form-card">
      <div class="form-card-header">
        <span>📞</span><h2>Contact</h2>
      </div>
      <div class="form-card-body">

        <div class="form-group">
          <label for="telephone">Téléphone affiché <span style="color:var(--jaune)">*</span></label>
          <input type="tel" id="telephone" name="telephone"
                 value="<?= htmlspecialchars($champs['telephone'] ?? '') ?>"
                 placeholder="+228 00 00 00 00" required>
        </div>

        <button type="submit" class="btn-submit">🚀 Publier l'annonce</button>

        <p style="text-align:center;color:var(--text-muted);font-size:.8rem;margin-top:1rem;">
          En publiant, vous acceptez les
          <a href="#" style="color:var(--jaune);">conditions d'utilisation</a>.
        </p>
      </div>
    </div>

  </div>

  <!-- COLONNE DROITE : APERÇU -->
  <div class="preview-col">
    <div class="preview-box">
      <div class="preview-header">👁 Aperçu</div>
      <div class="preview-body">
        <div class="preview-img" id="preview-img">📦</div>
        <span class="badge badge-yellow" id="preview-cat">Catégorie</span>
        <div style="font-weight:600;font-size:.95rem;margin:.5rem 0;" id="preview-titre">
          Titre de votre annonce
        </div>
        <div class="preview-price" id="preview-prix">— FCFA</div>
        <div style="color:var(--text-muted);font-size:.8rem;margin-top:.6rem;">
          📍 Votre ville · Aujourd'hui
        </div>
      </div>
    </div>

    <div class="tips-box">
      <h4>💡 Conseils pour mieux vendre</h4>
      <ul>
        <li>Titre clair avec marque et modèle</li>
        <li>Minimum 3 photos de qualité</li>
        <li>Prix juste selon le marché</li>
        <li>Répondez rapidement aux messages</li>
        <li>Précisez l'état et les défauts</li>
      </ul>
    </div>
  </div>

</div>
</form>

<footer>
  © 2025 <a href="../index.php">LocalMarket</a> · Tous droits réservés
</footer>

<script src="../assets/js/main.js"></script>
<script>
// Mise à jour de l'aperçu en temps réel
function updatePreview() {
  const titre = document.getElementById('titre').value;
  const prix  = document.getElementById('prix').value;
  const gratuit = document.getElementById('est-gratuit').checked;

  document.getElementById('preview-titre').textContent = titre || 'Titre de votre annonce';

  const prixEl = document.getElementById('preview-prix');
  if (gratuit) {
    prixEl.textContent = 'GRATUIT';
    prixEl.style.color = 'var(--vert)';
  } else if (prix) {
    prixEl.textContent = Number(prix).toLocaleString('fr') + ' FCFA';
    prixEl.style.color = 'var(--jaune)';
  } else {
    prixEl.textContent = '— FCFA';
    prixEl.style.color = 'var(--jaune)';
  }
}

// Compteur de caractères
function updateCount(inputId, countId) {
  const val = document.getElementById(inputId).value.length;
  document.getElementById(countId).textContent = val;
}

// Activer/désactiver le champ prix
function toggleGratuit() {
  const check  = document.getElementById('est-gratuit').checked;
  const prixIn = document.getElementById('prix');
  prixIn.disabled = check;
  prixIn.style.opacity = check ? '.4' : '1';
  updatePreview();
}

// Catégorie sélectionnée → aperçu
document.getElementById('categorie_id').addEventListener('change', function() {
  const text = this.options[this.selectedIndex].text;
  document.getElementById('preview-cat').textContent = text || 'Catégorie';
});

// Prévisualisation des photos
function previewPhotos(event) {
  const files = Array.from(event.target.files).slice(0, 8);
  const grid  = document.getElementById('photo-grid');
  grid.innerHTML = '';

  files.forEach((file, i) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      const div = document.createElement('div');
      div.className = 'photo-thumb';
      div.innerHTML = `<img src="${e.target.result}" alt="photo ${i+1}">`;
      grid.appendChild(div);

      // Première photo → aperçu
      if (i === 0) {
        const prev = document.getElementById('preview-img');
        prev.innerHTML = `<img src="${e.target.result}" alt="aperçu">`;
      }
    };
    reader.readAsDataURL(file);
  });

  // Slots vides
  for (let j = files.length; j < 4; j++) {
    const div = document.createElement('div');
    div.className = 'photo-thumb';
    div.textContent = '📷';
    grid.appendChild(div);
  }
}

// Init compteurs
updateCount('titre', 'titre-count');
updateCount('description', 'desc-count');
</script>
</body>
</html>
