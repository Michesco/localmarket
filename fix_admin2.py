#!/usr/bin/env python3
# -*- coding: utf-8 -*-

with open('c:/xamppp/htdocs/LocalMarket/pages/admin.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Remplacement simple des avatars utilisateurs
old_avatar = '<td><span class="user-avatar">👤</span><?= htmlspecialchars($u[\'prenom\'].' '.$u[\'nom\']) ?></td>'
new_avatar = '''<td>
                <div class="user-avatar" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--gris-clair),var(--gris));display:flex;align-items:center;justify-content:center;font-size:1.2rem;overflow:hidden;border:1px solid var(--gris-clair);">
                  <?php if (!empty($u['photo_profil'])): ?>
                    <img src="<?= htmlspecialchars($u['photo_profil']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                  <?php else: ?>
                    👤
                  <?php endif; ?>
                </div>
                <?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?>
              </td>'''

content = content.replace(old_avatar, new_avatar)

# Remplacement simple des miniatures annonces
old_thumb = '''<div class="annonce-mini-img">
                  <?php if (!empty($a['photo'])): ?><img src="<?= htmlspecialchars($a['photo']) ?>" alt=""><?php else: ?><?= $a['icone'] ?><?php endif; ?>
                </div>'''
new_thumb = '''<div class="annonce-mini-img" style="width:60px;height:60px;border-radius:8px;background:linear-gradient(135deg,var(--gris-clair),var(--gris));overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:1.5rem;border:1px solid var(--gris-clair);">
                  <?php if (!empty($a['photo'])): ?>
                    <img src="<?= htmlspecialchars($a['photo']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                  <?php else: ?>
                    <span style="opacity:0.6;"><?= $a['icone'] ?? '📦' ?></span>
                  <?php endif; ?>
                </div>'''

content = content.replace(old_thumb, new_thumb)

with open('c:/xamppp/htdocs/LocalMarket/pages/admin.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("✅ admin.php modifié avec succès")
