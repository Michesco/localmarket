-- ============================================
-- LocalMarket — Schéma Base de Données MySQL
-- ============================================

CREATE DATABASE IF NOT EXISTS localmarket
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE localmarket;

-- ─────────────────────────────────────────────
-- TABLE : utilisateurs
-- ─────────────────────────────────────────────
CREATE TABLE utilisateurs (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prenom        VARCHAR(60)  NOT NULL,
  nom           VARCHAR(60)  NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  telephone     VARCHAR(25)  NOT NULL,
  mot_de_passe  VARCHAR(255) NOT NULL,          -- hash bcrypt
  ville         VARCHAR(80)  NOT NULL,
  photo_profil  VARCHAR(255) DEFAULT NULL,
  note_moyenne  DECIMAL(2,1) DEFAULT 0.0,
  nb_avis       INT UNSIGNED DEFAULT 0,
  est_actif     TINYINT(1)   DEFAULT 1,
  created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- TABLE : categories
-- ─────────────────────────────────────────────
CREATE TABLE categories (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom       VARCHAR(80)  NOT NULL,
  slug      VARCHAR(80)  NOT NULL UNIQUE,
  icone     VARCHAR(10)  DEFAULT '📦',
  parent_id INT UNSIGNED DEFAULT NULL,
  FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-

-- ─────────────────────────────────────────────
-- TABLE : annonces
-- ─────────────────────────────────────────────
CREATE TABLE annonces (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id  INT UNSIGNED NOT NULL,
  categorie_id    INT UNSIGNED NOT NULL,
  titre           VARCHAR(100) NOT NULL,
  description     TEXT         NOT NULL,
  prix            DECIMAL(12,0) DEFAULT NULL,   -- NULL = gratuit
  est_gratuit     TINYINT(1)   DEFAULT 0,
  etat            ENUM('neuf','tres_bon','bon','correct','pieces') DEFAULT 'bon',
  ville           VARCHAR(80)  NOT NULL,
  quartier        VARCHAR(80)  DEFAULT NULL,
  statut          ENUM('active','vendue','expiree','suspendue') DEFAULT 'active',
  nb_vues         INT UNSIGNED DEFAULT 0,
  created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  expires_at      DATETIME     DEFAULT NULL,
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (categorie_id)   REFERENCES categories(id)   ON DELETE RESTRICT,
  INDEX idx_statut   (statut),
  INDEX idx_categorie(categorie_id),
  INDEX idx_ville    (ville),
  FULLTEXT INDEX ft_search (titre, description)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- TABLE : photos
-- ─────────────────────────────────────────────
CREATE TABLE photos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  annonce_id  INT UNSIGNED NOT NULL,
  url         VARCHAR(300) NOT NULL,
  ordre       TINYINT UNSIGNED DEFAULT 0,        -- 0 = photo principale
  FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE CASCADE,
  INDEX idx_annonce (annonce_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- TABLE : messages
-- ─────────────────────────────────────────────
CREATE TABLE messages (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  annonce_id   INT UNSIGNED NOT NULL,
  expediteur_id INT UNSIGNED NOT NULL,
  destinataire_id INT UNSIGNED NOT NULL,
  contenu      TEXT         NOT NULL,
  lu           TINYINT(1)   DEFAULT 0,
  created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (annonce_id)        REFERENCES annonces(id)      ON DELETE CASCADE,
  FOREIGN KEY (expediteur_id)     REFERENCES utilisateurs(id)  ON DELETE CASCADE,
  FOREIGN KEY (destinataire_id)   REFERENCES utilisateurs(id)  ON DELETE CASCADE,
  INDEX idx_conversation (annonce_id, expediteur_id, destinataire_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- TABLE : favoris
-- ─────────────────────────────────────────────
CREATE TABLE favoris (
  utilisateur_id INT UNSIGNED NOT NULL,
  annonce_id     INT UNSIGNED NOT NULL,
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (utilisateur_id, annonce_id),
  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (annonce_id)     REFERENCES annonces(id)     ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- TABLE : avis
-- ─────────────────────────────────────────────
CREATE TABLE avis (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  auteur_id      INT UNSIGNED NOT NULL,
  vendeur_id     INT UNSIGNED NOT NULL,
  annonce_id     INT UNSIGNED NOT NULL,
  note           TINYINT UNSIGNED NOT NULL CHECK (note BETWEEN 1 AND 5),
  commentaire    TEXT DEFAULT NULL,
  created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_avis (auteur_id, annonce_id),
  FOREIGN KEY (auteur_id)   REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (vendeur_id)  REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (annonce_id)  REFERENCES annonces(id)     ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- VUE : annonces_completes
-- ─────────────────────────────────────────────
CREATE VIEW v_annonces AS
SELECT
  a.id,
  a.titre,
  a.description,
  a.prix,
  a.est_gratuit,
  a.etat,
  a.ville,
  a.quartier,
  a.nb_vues,
  a.statut,
  a.created_at,
  c.nom       AS categorie_nom,
  c.icone     AS categorie_icone,
  c.slug      AS categorie_slug,
  u.id        AS vendeur_id,
  CONCAT(u.prenom, ' ', u.nom) AS vendeur_nom,
  u.telephone AS vendeur_telephone,
  u.note_moyenne AS vendeur_note,
  (SELECT url FROM photos p WHERE p.annonce_id = a.id AND p.ordre = 0 LIMIT 1) AS photo_principale
FROM annonces a
JOIN categories c ON a.categorie_id = c.id
JOIN utilisateurs u ON a.utilisateur_id = u.id
WHERE a.statut = 'active';

- Données de départ
INSERT INTO categories (nom, slug, icone) VALUES
  ('Téléphones & Accessoires', 'telephones',    '📱'),
  ('Informatique',             'informatique',  '💻'),
  ('Immobilier — Location',    'location',      '🏠'),
  ('Immobilier — Vente',       'vente-immo',    '🏡'),
  ('Motos & Scooters',         'motos',         '🏍️'),
  ('Voitures',                 'voitures',      '🚗'),
  ('Emploi & Services',        'emploi',        '👔'),
  ('Meubles & Décoration',     'meubles',       '🛋️'),
  ('Vêtements & Mode',         'vetements',     '👗'),
  ('Jeux & Loisirs',           'jeux',          '🎮'),
  ('Électroménager',           'electromenager','🍳'),
  ('Autres',                   'autres',        '📦');
