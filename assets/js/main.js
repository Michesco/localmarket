/**
 * LocalMarket — main.js
 * Fonctions globales réutilisables sur toutes les pages
 */

/* =========================================
   NAVBAR — Menu mobile hamburger
   ========================================= */
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.navbar-toggle');
  const menu   = document.querySelector('.navbar-menu');
  if (toggle && menu) {
    toggle.addEventListener('click', () => menu.classList.toggle('open'));
  }
});

/* =========================================
   ALERTES
   ========================================= */
/**
 * Affiche un message d'alerte temporaire
 * @param {string} message  - Texte à afficher
 * @param {'success'|'error'|'info'} type - Type d'alerte
 * @param {string} containerId - ID de l'élément parent (optionnel)
 */
function showAlert(message, type = 'info', containerId = null) {
  const existing = document.querySelector('.lm-alert');
  if (existing) existing.remove();

  const div = document.createElement('div');
  div.className = `alert alert-${type} lm-alert`;
  div.textContent = message;

  const container = containerId
    ? document.getElementById(containerId)
    : document.querySelector('.auth-body') || document.querySelector('.form-card-body') || document.body;

  container.prepend(div);
  setTimeout(() => div.remove(), 4000);
}

/* =========================================
   FORMULAIRES — Validation simple
   ========================================= */
/**
 * Vérifie que tous les champs requis sont remplis
 * @param {HTMLFormElement|string} formOrId
 * @returns {boolean}
 */
function validateRequired(formOrId) {
  const form = typeof formOrId === 'string'
    ? document.getElementById(formOrId)
    : formOrId;
  if (!form) return false;

  let valid = true;
  form.querySelectorAll('[required]').forEach(input => {
    if (!input.value.trim()) {
      input.style.borderColor = 'var(--rouge)';
      valid = false;
    } else {
      input.style.borderColor = '';
    }
  });
  return valid;
}

/**
 * Valide le format d'un email
 */
function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/* =========================================
   COMPTEUR DE CARACTÈRES
   ========================================= */
/**
 * Lie un champ texte à un compteur de caractères
 * @param {string} inputId  - ID du textarea / input
 * @param {string} counterId - ID du <span> compteur
 * @param {number} max      - Limite max
 */
function bindCharCount(inputId, counterId, max) {
  const input   = document.getElementById(inputId);
  const counter = document.getElementById(counterId);
  if (!input || !counter) return;

  input.addEventListener('input', () => {
    const len = input.value.length;
    counter.textContent = len;
    counter.style.color = len >= max * 0.9 ? 'var(--rouge)' : 'var(--text-muted)';
  });
}

/* =========================================
   UPLOAD PHOTOS — Prévisualisation
   ========================================= */
/**
 * Initialise la prévisualisation de photos
 * @param {string} inputId   - ID de l'input file
 * @param {string} gridId    - ID de la grille de prévisualisation
 * @param {string} previewId - ID de l'image principale (optionnel)
 * @param {number} maxPhotos - Nombre maximum de photos
 */
function initPhotoUpload(inputId, gridId, previewId = null, maxPhotos = 8) {
  const input = document.getElementById(inputId);
  const grid  = document.getElementById(gridId);
  if (!input || !grid) return;

  input.addEventListener('change', (e) => {
    const files = Array.from(e.target.files).slice(0, maxPhotos);
    grid.innerHTML = '';

    files.forEach((file, i) => {
      const reader = new FileReader();
      reader.onload = (ev) => {
        const div = document.createElement('div');
        div.className = 'photo-thumb has-image';
        div.innerHTML = `<img src="${ev.target.result}" alt="Photo ${i+1}" style="width:100%;height:100%;object-fit:cover;border-radius:6px;">`;
        grid.appendChild(div);

        if (i === 0 && previewId) {
          const prev = document.getElementById(previewId);
          if (prev) prev.innerHTML = `<img src="${ev.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
        }
      };
      reader.readAsDataURL(file);
    });

    // Ajouter les slots vides restants
    for (let j = files.length; j < 4; j++) {
      const div = document.createElement('div');
      div.className = 'photo-thumb';
      div.textContent = '📷';
      grid.appendChild(div);
    }
  });
}

/* =========================================
   FAVORIS — Sauvegarde locale
   ========================================= */
const Favorites = {
  key: 'lm_favorites',

  getAll() {
    try { return JSON.parse(localStorage.getItem(this.key)) || []; }
    catch { return []; }
  },

  add(id) {
    const favs = this.getAll();
    if (!favs.includes(id)) { favs.push(id); localStorage.setItem(this.key, JSON.stringify(favs)); }
  },

  remove(id) {
    const favs = this.getAll().filter(f => f !== id);
    localStorage.setItem(this.key, JSON.stringify(favs));
  },

  toggle(id) {
    this.has(id) ? this.remove(id) : this.add(id);
    return this.has(id);
  },

  has(id) { return this.getAll().includes(id); }
};

/* =========================================
   PARTAGE
   ========================================= */
function shareAnnonce(title = document.title) {
  if (navigator.share) {
    navigator.share({ title, url: window.location.href });
  } else {
    navigator.clipboard.writeText(window.location.href)
      .then(() => showAlert('🔗 Lien copié dans le presse-papier !', 'success'))
      .catch(() => showAlert('Impossible de copier le lien.', 'error'));
  }
}

/* =========================================
   FORMAT PRIX
   ========================================= */
/**
 * Formate un nombre en prix avec séparateur de milliers
 * @param {number} amount
 * @param {string} currency
 */
function formatPrice(amount, currency = 'FCFA') {
  if (!amount || isNaN(amount)) return '—';
  return Number(amount).toLocaleString('fr-FR') + ' ' + currency;
}

/* =========================================
   TEMPS RELATIF
   ========================================= */
function timeAgo(dateStr) {
  const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
  if (diff < 60)     return 'À l\'instant';
  if (diff < 3600)   return `il y a ${Math.floor(diff/60)} min`;
  if (diff < 86400)  return `il y a ${Math.floor(diff/3600)}h`;
  if (diff < 604800) return `il y a ${Math.floor(diff/86400)} jours`;
  return new Date(dateStr).toLocaleDateString('fr-FR');
}
