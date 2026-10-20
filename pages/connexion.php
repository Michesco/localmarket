<?php
/**
 * LocalMarket — connexion.php
 * Gère l'affichage du formulaire ET le traitement POST
 */
session_start();
require_once '../backend/php/db.php';

// ── Si déjà connecté → rediriger ─────────────────────
if (isset($_SESSION['user_id'])) {
    header('Location: ../pages/index.php');
    exit;
}

$erreur  = '';
$succes  = '';
$email_prefill = '';

// ── Traitement du formulaire POST ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email       = trim($_POST['email']      ?? '');
    $mot_de_passe = trim($_POST['mot_de_passe'] ?? '');
    $email_prefill = htmlspecialchars($email);

    // Validation basique
    if (empty($email) || empty($mot_de_passe)) {
        $erreur = '⚠️ Veuillez remplir tous les champs.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = '⚠️ Adresse email invalide.';

    } else {
        // Vérification en base de données
        $db   = getDB();
        $stmt = $db->prepare('
            SELECT id, prenom, nom, email, mot_de_passe
            FROM utilisateurs
            WHERE email = ? AND est_actif = 1
        ');
        $stmt->execute([strtolower($email)]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($mot_de_passe, $user['mot_de_passe'])) {
            $erreur = '❌ Email ou mot de passe incorrect.';
        } else {
            // ✅ Connexion réussie — créer la session
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_nom']   = $user['prenom'];
            $_SESSION['user_email'] = $user['email'];

            // Rediriger vers la page demandée ou l'accueil
            $redirect = $_GET['redirect'] ?? '../index.php';
            header('Location: ' . $redirect);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion — LocalMarket</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/main.css">
  <link rel="stylesheet" href="../assets/css/navbar.css">
  <style>
    body { display:flex; flex-direction:column; min-height:100vh; }
    .auth-wrapper { flex:1; display:flex; align-items:center; justify-content:center; padding:3rem 1rem; }
    .auth-box { background:var(--gris); border:2px solid var(--gris-clair); border-radius:16px; width:100%; max-width:420px; overflow:hidden; }
    .auth-header { background:var(--jaune); padding:2rem; text-align:center; }
    .auth-header h1 { font-family:'Bebas Neue',sans-serif; font-size:2rem; color:var(--noir); letter-spacing:2px; }
    .auth-header p { color:#555; font-size:.9rem; margin-top:.3rem; }
    .auth-body { padding:2rem; }
    .form-group { margin-bottom:1.3rem; }
    .divider { display:flex; align-items:center; gap:1rem; margin:1.5rem 0; color:var(--text-muted); font-size:.85rem; }
    .divider::before,.divider::after { content:''; flex:1; height:1px; background:var(--gris-clair); }
    .forgot { text-align:right; font-size:.82rem; margin-top:.4rem; }
    .forgot a { color:var(--jaune); text-decoration:none; }
    .btn-submit { background:var(--jaune); color:var(--noir); padding:1rem; border-radius:8px; font-weight:700; font-family:'Outfit',sans-serif; font-size:1rem; border:none; cursor:pointer; width:100%; transition:background .2s; }
    .btn-submit:hover { background:var(--jaune-clair); }
    .auth-footer { text-align:center; margin-top:1.5rem; padding-top:1.5rem; border-top:1px solid var(--gris-clair); color:var(--text-muted); font-size:.9rem; }
    .auth-footer a { color:var(--jaune); text-decoration:none; font-weight:600; }
    footer { background:#080808; border-top:1px solid var(--gris-clair); padding:1.2rem; text-align:center; color:var(--text-muted); font-size:.8rem; }
    footer a { color:var(--jaune); text-decoration:none; }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <a href="../index.php" class="navbar-logo">Local<span>Market</span></a>
  <span style="color:var(--text-muted);font-size:.9rem;">
    Pas encore membre ?
    <a href="inscription.php" style="color:var(--jaune);font-weight:600;">Créer un compte</a>
  </span>
</nav>

<!-- FORMULAIRE -->
<div class="auth-wrapper">
  <div class="auth-box">

    <div class="auth-header">
      <h1>Connexion</h1>
      <p>Bon retour sur LocalMarket 👋</p>
    </div>

    <div class="auth-body">

      <?php if ($erreur): ?>
        <div class="alert alert-error"><?= $erreur ?></div>
      <?php endif; ?>

      <?php if (!empty($_GET['inscrit'])): ?>
        <div class="alert alert-success">
          ✅ Compte créé avec succès ! Vous pouvez maintenant vous connecter.
        </div>
      <?php endif; ?>

      <form method="POST" action="connexion.php">

        <div class="form-group">
          <label for="email">Adresse email</label>
          <input type="email" id="email" name="email"
                 value="<?= $email_prefill ?>"
                 placeholder="jean@exemple.com"
                 required autofocus>
        </div>

        <div class="form-group">
          <label for="mot_de_passe">Mot de passe</label>
          <input type="password" id="mot_de_passe" name="mot_de_passe"
                 placeholder="••••••••" required>
          <div class="forgot">
            <a href="#">Mot de passe oublié ?</a>
          </div>
        </div>

        <button type="submit" class="btn-submit">Se connecter</button>

      </form>

      <div class="auth-footer">
        Pas encore de compte ?
        <a href="inscription.php">S'inscrire gratuitement</a>
      </div>

    </div>
  </div>
</div>

<footer>
  © 2025 <a href="../index.php">LocalMarket</a> · Tous droits réservés
</footer>

</body>
</html>
