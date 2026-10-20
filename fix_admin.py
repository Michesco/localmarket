#!/usr/bin/env python3
# -*- coding: utf-8 -*-

with open('c:/xamppp/htdocs/LocalMarket/pages/admin.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

# Modifications
for i, line in enumerate(lines):
    # Remplacement 1: Avatar utilisateur ligne 418 (dashboard)
    if '<td><span class="user-avatar">👤</span><?= htmlspecialchars($u[\'prenom\'].' '.$u[\'nom\']) ?></td>' in line and i > 400 and i < 430:
        lines[i] = '''            <td>
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--gris-clair),var(--gris));display:flex;align-items:center;justify-content:center;font-size:1.2rem;overflow:hidden;border:1px solid var(--gris-clair);">
                  <?php if (!empty($u['photo_profil'])): ?>
                    <img src="<?= htmlspecialchars($u['photo_profil']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                  <?php else: ?>
                    👤
                  <?php endif; ?>
                </div>
                <?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?>
              </td>\n'''
    
    # Remplacement 2: Avatar utilisateur ligne 574 (liste complète)
    elif '<td><span class="user-avatar">👤</span><?= htmlspecialchars($u[\'prenom\'].' '.$u[\'nom\']) ?></td>' in line and i > 550 and i < 600:
        lines[i] = '''            <td>
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--gris-clair),var(--gris));display:flex;align-items:center;justify-content:center;font-size:1.2rem;overflow:hidden;border:1px solid var(--gris-clair);">
                  <?php if (!empty($u['photo_profil'])): ?>
                    <img src="<?= htmlspecialchars($u['photo_profil']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                  <?php else: ?>
                    👤
                  <?php endif; ?>
                </div>
                <?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?>
              </td>\n'''
    
    # Remplacement 3: Miniature annonce ligne 439-440 (dashboard)
    elif '<div class="annonce-mini-img">' in line and i > 430 and i < 450:
        if i + 1 < len(lines) and '<?php if (!empty($a[\'photo\'])): ?><img src="<?= htmlspecialchars($a[\'photo\']) ?>" alt=""><?php else: ?><?= $a[\'icone\'] ?><?php endif; ?>' in lines[i + 1]:
            lines[i] = '''                <div class="annonce-mini-img" style="width:60px;height:60px;border-radius:8px;background:linear-gradient(135deg,var(--gris-clair),var(--gris));overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:1.5rem;border:1px solid var(--gris-clair);">
                  <?php if (!empty($a['photo'])): ?>
                    <img src="<?= htmlspecialchars($a['photo']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                  <?php else: ?>
                    <span style="opacity:0.6;"><?= $a['icone'] ?? '📦' ?></span>
                  <?php endif; ?>
                </div>\n'''
            lines[i + 1] = ''
    
    # Remplacement 4: Miniature annonce ligne 624-625 (liste complète)
    elif '<div class="annonce-mini-img">' in line and i > 615 and i < 635:
        if i + 1 < len(lines) and '<?php if (!empty($a[\'photo\'])): ?><img src="<?= htmlspecialchars($a[\'photo\']) ?>" alt=""><?php else: ?><?= $a[\'icone\'] ?><?php endif; ?>' in lines[i + 1]:
            lines[i] = '''                <div class="annonce-mini-img" style="width:60px;height:60px;border-radius:8px;background:linear-gradient(135deg,var(--gris-clair),var(--gris));overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:1.5rem;border:1px solid var(--gris-clair);">
                  <?php if (!empty($a['photo'])): ?>
                    <img src="<?= htmlspecialchars($a['photo']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                  <?php else: ?>
                    <span style="opacity:0.6;"><?= $a['icone'] ?? '📦' ?></span>
                  <?php endif; ?>
                </div>\n'''
            lines[i + 1] = ''

with open('c:/xamppp/htdocs/LocalMarket/pages/admin.php', 'w', encoding='utf-8') as f:
    f.writelines(lines)

print("✅ admin.php modifié avec succès")
