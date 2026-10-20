<?php
/**
 * LocalMarket — inscription.php
 * Fichier à placer dans : pages/inscription.php
 */

// ✅ db.php et auth.php sont dans le MÊME dossier pages/
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

session_start();

// Si déjà connecté → rediriger
if (estConnecte()) {
    header('Location: index.php');
    exit;
}

$erreur = '';

// ── Traitement du formulaire POST ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $prenom       = trim($_POST['prenom']       ?? '');
    $nom          = trim($_POST['nom']          ?? '');
    $email        = trim($_POST['email']        ?? '');
    $telephone    = trim($_POST['telephone']    ?? '');
    $ville        = trim($_POST['ville']        ?? '');
    $mot_de_passe = trim($_POST['mot_de_passe'] ?? '');
    $confirmation = trim($_POST['confirmation'] ?? '');
    $cgu          = isset($_POST['cgu']);

    // ── Validations ──────────────────────────────────
    if (empty($prenom) || empty($nom) || empty($email) || empty($telephone) || empty($ville) || empty($mot_de_passe) || empty($confirmation)) {
        $erreur = '⚠️ Veuillez remplir tous les champs obligatoires.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = '⚠️ Adresse email invalide.';

    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $mot_de_passe)) {
        $erreur = '⚠️ Le mot de passe doit contenir au moins 8 caractères, une majuscule et un chiffre.';

    } elseif ($mot_de_passe !== $confirmation) {
        $erreur = '⚠️ Les mots de passe ne correspondent pas.';

    } elseif (!$cgu) {
        $erreur = "⚠️ Vous devez accepter les conditions d'utilisation.";

    } else {
        // ✅ Appel de la fonction inscrireUtilisateur() depuis auth.php
        $resultat = inscrireUtilisateur([
            'prenom'       => $prenom,
            'nom'          => $nom,
            'email'        => $email,
            'telephone'    => $telephone,
            'ville'        => $ville,
            'mot_de_passe' => $mot_de_passe,
        ]);

        if ($resultat['success']) {
            // ✅ Inscription réussie → redirection vers connexion
            header('Location: connexion.php?inscrit=1');
            exit;
        } else {
            $erreur = '❌ ' . $resultat['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inscription — LocalMarket</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --jaune: #FFD600;
      --jaune-clair: #FFF176;
      --noir: #0D0D0D;
      --gris: #1A1A1A;
      --gris-clair: #2A2A2A;
      --blanc: #FAFAFA;
      --text-muted: #888;
      --rouge: #FF4D4D;
      --vert: #4CAF50;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Outfit', sans-serif; background: var(--noir); color: var(--blanc); min-height: 100vh; display: flex; flex-direction: column; }

    nav {
      background: var(--noir);
      border-bottom: 3px solid var(--jaune);
      padding: 0 2rem;
      display: flex; align-items: center; justify-content: space-between;
      height: 70px;
    }
    .logo { font-family: 'Bebas Neue', sans-serif; font-size: 2rem; color: var(--jaune); letter-spacing: 2px; text-decoration: none; }
    .logo span { color: var(--blanc); }

    .auth-wrapper { flex: 1; display: flex; align-items: center; justify-content: center; padding: 3rem 1rem; }

    .auth-box {
      background: var(--gris);
      border: 2px solid var(--gris-clair);
      border-radius: 16px;
      width: 100%;
      max-width: 480px;
      overflow: hidden;
    }

    .auth-header { background: var(--jaune); padding: 2rem; text-align: center; }
    .auth-header h1 { font-family: 'Bebas Neue', sans-serif; font-size: 2rem; color: var(--noir); letter-spacing: 2px; }
    .auth-header p { color: var(--gris); font-size: 0.9rem; margin-top: 0.3rem; }

    .auth-body { padding: 2rem; }

    .alert { padding: 0.9rem 1rem; border-radius: 8px; margin-bottom: 1.2rem; font-size: 0.9rem; }
    .alert-error   { background: rgba(255,77,77,0.15); border: 1px solid var(--rouge); color: #ff8080; }
    .alert-success { background: rgba(76,175,80,0.15); border: 1px solid var(--vert);  color: #80c883; }

    .form-group { margin-bottom: 1.3rem; }
    label { display: block; font-weight: 600; font-size: 0.9rem; margin-bottom: 0.5rem; color: var(--blanc); }
    label span { color: var(--jaune); }

    input[type="text"], input[type="email"], input[type="password"], input[type="tel"], select {
      width: 100%; background: var(--gris-clair); border: 2px solid var(--gris-clair);
      border-radius: 8px; padding: 0.85rem 1rem; color: var(--blanc);
      font-family: 'Outfit', sans-serif; font-size: 0.95rem; outline: none; transition: border-color 0.2s;
    }
    input:focus, select:focus { border-color: var(--jaune); }
    input::placeholder { color: var(--text-muted); }

    .input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .password-hint { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.4rem; }

    .checkbox-group { display: flex; align-items: flex-start; gap: 0.7rem; margin-bottom: 1.5rem; }
    .checkbox-group input[type="checkbox"] { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--jaune); flex-shrink: 0; }
    .checkbox-group label { font-weight: 400; font-size: 0.88rem; color: var(--text-muted); margin: 0; }
    .checkbox-group label a { color: var(--jaune); text-decoration: none; }

    .btn {
      background: var(--jaune); color: var(--noir); padding: 0.9rem; border-radius: 8px;
      font-weight: 700; font-family: 'Outfit', sans-serif; font-size: 1rem; border: none;
      cursor: pointer; transition: background 0.2s, transform 0.1s; width: 100%; letter-spacing: 0.5px;
    }
    .btn:hover { background: var(--jaune-clair); transform: translateY(-1px); }

    .auth-footer { text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--gris-clair); color: var(--text-muted); font-size: 0.9rem; }
    .auth-footer a { color: var(--jaune); text-decoration: none; font-weight: 600; }

    .divider { display: flex; align-items: center; gap: 1rem; margin: 1.5rem 0; color: var(--text-muted); font-size: 0.85rem; }
    .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--gris-clair); }

    .social-btn {
      background: var(--gris-clair); color: var(--blanc); border: 2px solid var(--gris-clair);
      border-radius: 8px; padding: 0.75rem; display: flex; align-items: center; justify-content: center;
      gap: 0.7rem; font-family: 'Outfit', sans-serif; font-size: 0.9rem; font-weight: 500;
      cursor: pointer; transition: border-color 0.2s; width: 100%; margin-bottom: 0.8rem;
    }
    .social-btn:hover { border-color: var(--jaune); }
    select option { background: var(--gris-clair); }

    footer { background: #080808; border-top: 1px solid var(--gris-clair); padding: 1.2rem; text-align: center; color: var(--text-muted); font-size: 0.8rem; }
    footer a { color: var(--jaune); text-decoration: none; }
  </style>
</head>
<body>

<nav>
  <a href="index.php" class="logo">Local<span>Market</span></a>
  <span style="color: var(--text-muted); font-size: 0.9rem;">
    Déjà membre ? <a href="connexion.php" style="color: var(--jaune); text-decoration: none; font-weight: 600;">Connexion</a>
  </span>
</nav>

<div class="auth-wrapper">
  <div class="auth-box">

    <div class="auth-header">
      <h1>Créer un compte</h1>
      <p>Rejoignez LocalMarket — c'est gratuit !</p>
    </div>

    <div class="auth-body">

      <?php if ($erreur): ?>
        <div class="alert alert-error"><?= $erreur ?></div>
      <?php endif; ?>

    

      <div class="divider">ou avec votre email</div>

      <form method="POST" action="inscription.php">

        <div class="input-row">
          <div class="form-group">
            <label>Prénom <span>*</span></label>
            <input type="text" name="prenom" placeholder="Afivi"
                   value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Nom <span>*</span></label>
            <input type="text" name="nom" placeholder="AGANON"
                   value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label>Adresse email <span>*</span></label>
          <input type="email" name="email" placeholder="Afivi@exemple.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label>Numéro de téléphone <span>*</span></label>
          <input type="tel" name="telephone" placeholder="+228  00 00 00 00"
                 value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label>Ville <span>*</span></label>
          <select name="ville" required>
            <option value="">Sélectionnez votre ville</option>
            <?php
            $villes = ['Abidjan','Dakar','Douala','Lomé','Cotonou','Bamako','Ouagadougou','Autre'];
            foreach ($villes as $v) {
                $selected = (($_POST['ville'] ?? '') === $v) ? 'selected' : '';
                echo "<option value=\"$v\" $selected>$v</option>";
            }
            ?>
          </select>
        </div>

        <div class="form-group">
          <label>Mot de passe <span>*</span></label>
          <input type="password" name="mot_de_passe" placeholder="••••••••" required>
          <p class="password-hint">Minimum 8 caractères, avec une majuscule et un chiffre.</p>
        </div>

        <div class="form-group">
          <label>Confirmer le mot de passe <span>*</span></label>
          <input type="password" name="confirmation" placeholder="••••••••" required>
        </div>

        <div class="checkbox-group">
          <input type="checkbox" id="cgu" name="cgu">
          <label for="cgu">
            J'accepte les <a href="#">Conditions d'utilisation</a>
            et la <a href="#">Politique de confidentialité</a> de LocalMarket.
          </label>
        </div>

        <button type="submit" class="btn">Créer mon compte gratuitement</button>

      </form>

      <div class="auth-footer">
        Déjà inscrit ? <a href="connexion.php">Se connecter</a>
      </div>

    </div>
  </div>
</div>

<footer>
  © 2025 <a href="index.php">LocalMarket</a> · Tous droits réservés
</footer>

</body>
</html>
