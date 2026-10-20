<?php
// Script temporaire pour modifier admin.php
$file = 'admin.php';
$content = file_get_contents($file);

// Remplacement 1: Avatar utilisateur dans le dashboard (ligne 418)
$old1 = '<td><span class="user-avatar">👤</span><?= htmlspecialchars($u[\'prenom\'].' '.$u[\'nom\']) ?></td>';
$new1 = '<td>
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--gris-clair),var(--gris));display:flex;align-items:center;justify-content:center;font-size:1.2rem;overflow:hidden;border:1px solid var(--gris-clair);">
                  <?php if (!empty($u[\'photo_profil\'])): ?>
                    <img src="<?= htmlspecialchars($u[\'photo_profil\']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                  <?php else: ?>
                    👤
                  <?php endif; ?>
                </div>
                <?= htmlspecialchars($u[\'prenom\'].' '.$u[\'nom\']) ?>
              </td>';
$content = str_replace($old1, $new1, $content);

// Remplacement 2: Avatar utilisateur dans la liste complète (ligne 574)
$content = str_replace($old1, $new1, $content);

// Remplacement 3: Miniature annonce dans le dashboard (ligne 439-440)
$old2 = '<div class="annonce-mini-img">
                  <?php if (!empty($a[\'photo\'])): ?><img src="<?= htmlspecialchars($a[\'photo\']) ?>" alt=""><?php else: ?><?= $a[\'icone\'] ?><?php endif; ?>
                </div>';
$new2 = '<div class="annonce-mini-img" style="width:60px;height:60px;border-radius:8px;background:linear-gradient(135deg,var(--gris-clair),var(--gris));overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:1.5rem;border:1px solid var(--gris-clair);">
                  <?php if (!empty($a[\'photo\'])): ?>
                    <img src="<?= htmlspecialchars($a[\'photo\']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                  <?php else: ?>
                    <span style="opacity:0.6;"><?= $a[\'icone\'] ?? \'📦\' ?></span>
                  <?php endif; ?>
                </div>';
$content = str_replace($old2, $new2, $content);

// Remplacement 4: Miniature annonce dans la liste complète (ligne 624-625)
$content = str_replace($old2, $new2, $content);

file_put_contents($file, $content);
echo "✅ admin.php modifié avec succès";
?>
