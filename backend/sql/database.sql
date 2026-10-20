-- ============================================
-- LocalMarket — Schéma + Données de démo
-- Images : Unsplash (URLs directes, gratuites)
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
  mot_de_passe  VARCHAR(255) NOT NULL,
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

INSERT INTO categories (nom, slug, icone) VALUES
  ('Téléphones & Accessoires', 'telephones',    '📱'),
  ('Informatique',             'informatique',  '💻'),
  ('Immobilier',               'immobilier',    '🏠'),
  ('Motos & Scooters',         'motos',         '🏍️'),
  ('Voitures',                 'voitures',      '🚗'),
  ('Emploi & Services',        'emploi',        '👔'),
  ('Meubles & Décoration',     'meubles',       '🛋️'),
  ('Vêtements & Mode',         'vetements',     '👗'),
  ('Jeux & Loisirs',           'jeux',          '🎮'),
  ('Électroménager',           'electromenager','🍳'),
  ('Autres',                   'autres',        '📦');

-- ─────────────────────────────────────────────
-- TABLE : annonces
-- ─────────────────────────────────────────────
CREATE TABLE annonces (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id  INT UNSIGNED NOT NULL,
  categorie_id    INT UNSIGNED NOT NULL,
  titre           VARCHAR(100) NOT NULL,
  description     TEXT         NOT NULL,
  prix            DECIMAL(12,0) DEFAULT NULL,
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
  INDEX idx_statut    (statut),
  INDEX idx_categorie (categorie_id),
  INDEX idx_ville     (ville),
  FULLTEXT INDEX ft_search (titre, description)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- TABLE : photos
-- ─────────────────────────────────────────────
CREATE TABLE photos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  annonce_id  INT UNSIGNED NOT NULL,
  url         VARCHAR(500) NOT NULL,
  ordre       TINYINT UNSIGNED DEFAULT 0,
  FOREIGN KEY (annonce_id) REFERENCES annonces(id) ON DELETE CASCADE,
  INDEX idx_annonce (annonce_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- TABLE : messages
-- ─────────────────────────────────────────────
CREATE TABLE messages (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  annonce_id      INT UNSIGNED NOT NULL,
  expediteur_id   INT UNSIGNED NOT NULL,
  destinataire_id INT UNSIGNED NOT NULL,
  contenu         TEXT         NOT NULL,
  lu              TINYINT(1)   DEFAULT 0,
  created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (annonce_id)      REFERENCES annonces(id)     ON DELETE CASCADE,
  FOREIGN KEY (expediteur_id)   REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (destinataire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
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
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  auteur_id   INT UNSIGNED NOT NULL,
  vendeur_id  INT UNSIGNED NOT NULL,
  annonce_id  INT UNSIGNED NOT NULL,
  note        TINYINT UNSIGNED NOT NULL CHECK (note BETWEEN 1 AND 5),
  commentaire TEXT DEFAULT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_avis (auteur_id, annonce_id),
  FOREIGN KEY (auteur_id)  REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (vendeur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
  FOREIGN KEY (annonce_id) REFERENCES annonces(id)     ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────
-- VUE : annonces complètes
-- ─────────────────────────────────────────────
CREATE VIEW v_annonces AS
SELECT
  a.id, a.titre, a.description, a.prix, a.est_gratuit,
  a.etat, a.ville, a.quartier, a.nb_vues, a.statut, a.created_at,
  c.nom   AS categorie_nom,
  c.icone AS categorie_icone,
  c.slug  AS categorie_slug,
  u.id    AS vendeur_id,
  CONCAT(u.prenom,' ',u.nom) AS vendeur_nom,
  u.telephone  AS vendeur_telephone,
  u.note_moyenne AS vendeur_note,
  (SELECT url FROM photos p WHERE p.annonce_id = a.id AND p.ordre = 0 LIMIT 1) AS photo_principale
FROM annonces a
JOIN categories c ON a.categorie_id = c.id
JOIN utilisateurs u ON a.utilisateur_id = u.id
WHERE a.statut = 'active';

-- ═════════════════════════════════════════════
-- DONNÉES DE DÉMO
-- Mot de passe pour tous : "password123"
-- Hash bcrypt de "password123"
-- ═════════════════════════════════════════════

INSERT INTO utilisateurs (prenom, nom, email, telephone, mot_de_passe, ville, note_moyenne, nb_avis) VALUES
  ('Moussa',   'Diallo',   'moussa@demo.com',   '+221 77 123 45 67', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dakar',    4.9, 23),
  ('Aminata',  'Koné',     'aminata@demo.com',  '+225 07 456 78 90', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Abidjan',  4.7, 15),
  ('Kwame',    'Mensah',   'kwame@demo.com',    '+233 24 789 01 23', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Douala',   4.5, 8),
  ('Fatou',    'Ba',       'fatou@demo.com',    '+221 76 234 56 78', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dakar',    5.0, 31),
  ('Ibrahim',  'Traoré',   'ibrahim@demo.com',  '+226 70 345 67 89', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lomé',     4.3, 6),
  ('Chantal',  'Mbeki',    'chantal@demo.com',  '+237 69 456 78 90', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cotonou',  4.8, 19);

-- ─────────────────────────────────────────────
-- ANNONCES DÉMO avec vraies images Unsplash
-- ─────────────────────────────────────────────

-- 📱 TÉLÉPHONES (cat 1)
INSERT INTO annonces (utilisateur_id, categorie_id, titre, description, prix, etat, ville, quartier, nb_vues, created_at, expires_at) VALUES
(1, 1, 'iPhone 14 Pro — 256 Go, Noir Spatial', 'iPhone 14 Pro en excellent état, utilisé 8 mois. Écran sans rayure, batterie à 94%. Vendu avec boîte, câble Lightning et chargeur 20W. Débloqué tous opérateurs. Raison de vente : passage au 15 Pro.', 480000, 'tres_bon', 'Dakar', 'Plateau', 342, DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_ADD(NOW(), INTERVAL 60 DAY)),
(2, 1, 'Samsung Galaxy S23 Ultra — 512 Go', 'Samsung S23 Ultra comme neuf, acheté il y a 4 mois. Stylet S Pen inclus. Housse de protection offerte. Couleur Crème. Toutes fonctionnalités parfaites. Facture disponible.', 520000, 'tres_bon', 'Abidjan', 'Cocody', 187, DATE_SUB(NOW(), INTERVAL 5 HOUR), DATE_ADD(NOW(), INTERVAL 60 DAY)),
(3, 1, 'Xiaomi Redmi Note 12 — 128 Go', 'Redmi Note 12 en bon état, quelques micro-rayures sur la coque. Écran parfait, batterie autonomie excellente. Vendu sans accessoires. Prix négociable.', 85000, 'bon', 'Douala', 'Bonamoussadi', 95, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 60 DAY));

INSERT INTO photos (annonce_id, url, ordre) VALUES
(1, 'https://images.unsplash.com/photo-1603921326210-6edd2d60ca68?w=600&q=80', 0),
(1, 'https://images.unsplash.com/photo-1512054502232-10a0a035d672?w=600&q=80', 1),
(2, 'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=600&q=80', 0),
(2, 'https://images.unsplash.com/photo-1565849904461-04a58ad377e0?w=600&q=80', 1),
(3, 'https://images.unsplash.com/photo-1546054454-aa26e2b734c7?w=600&q=80', 0);

-- 💻 INFORMATIQUE (cat 2)
INSERT INTO annonces (utilisateur_id, categorie_id, titre, description, prix, etat, ville, quartier, nb_vues, created_at, expires_at) VALUES
(4, 2, 'MacBook Air M2 — 8 Go RAM, 256 Go SSD', 'MacBook Air M2 2022 en parfait état. Couleur Minuit. Autonomie exceptionnelle, très rapide. Vendu avec chargeur MagSafe. Aucune rayure. Parfait pour travail et études.', 750000, 'tres_bon', 'Dakar', 'Almadies', 428, DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_ADD(NOW(), INTERVAL 60 DAY)),
(5, 2, 'Dell XPS 15 — Core i7, 16 Go, RTX 3050', 'Dell XPS 15 2021, processeur i7-11800H, 16 Go RAM, SSD 512 Go, écran 4K OLED. Excellente machine pour développeurs et graphistes. Chargeur inclus.', 680000, 'bon', 'Lomé', 'Adidogomé', 213, DATE_SUB(NOW(), INTERVAL 8 HOUR), DATE_ADD(NOW(), INTERVAL 60 DAY)),
(6, 2, 'iPad Pro 11" M1 — Wi-Fi, 128 Go', 'iPad Pro 11 pouces M1, Wi-Fi, 128 Go, couleur Gris sidéral. Avec Apple Pencil 2e génération. Étui Smart Folio inclus. Parfait état, utilisé pour prise de notes.', 350000, 'tres_bon', 'Cotonou', 'Cadjèhoun', 156, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_ADD(NOW(), INTERVAL 60 DAY));

INSERT INTO photos (annonce_id, url, ordre) VALUES
(4, 'https://images.unsplash.com/photo-1611186871525-9c19e9e6e36d?w=600&q=80', 0),
(4, 'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?w=600&q=80', 1),
(5, 'https://images.unsplash.com/photo-1593642632559-0c6d3fc62b89?w=600&q=80', 0),
(6, 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=600&q=80', 0),
(6, 'https://images.unsplash.com/photo-1622533261163-e4716b325c60?w=600&q=80', 1);

-- 🏠 IMMOBILIER (cat 3)
INSERT INTO annonces (utilisateur_id, categorie_id, titre, description, prix, etat, ville, quartier, nb_vues, created_at, expires_at) VALUES
(1, 3, 'Appartement F3 meublé — Vue mer, Plateau', 'Magnifique appartement F3 entièrement meublé, situé au 4e étage avec vue sur la mer. Séjour lumineux, 2 chambres, cuisine équipée, 2 SDB. Gardiennage 24h/24. Idéal expatriés.', 350000, 'neuf', 'Dakar', 'Plateau', 589, DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_ADD(NOW(), INTERVAL 60 DAY)),
(2, 3, 'Villa 4 chambres à vendre — Cocody Angré', 'Belle villa de plain-pied, 4 chambres, 3 salles de bain, salon spacieux, cuisine américaine, garage 2 voitures. Jardin aménagé, piscine. Quartier résidentiel et sécurisé.', 45000000, 'neuf', 'Abidjan', 'Cocody Angré', 721, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 60 DAY)),
(3, 3, 'Studio meublé à louer — Centre-ville', 'Studio tout équipé, 35 m², au 2e étage. Climatisé, Wi-Fi inclus, eau et électricité comprises. Idéal pour étudiant ou jeune professionnel. Disponible immédiatement.', 80000, 'tres_bon', 'Douala', 'Akwa', 298, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_ADD(NOW(), INTERVAL 60 DAY));

INSERT INTO photos (annonce_id, url, ordre) VALUES
(7, 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=600&q=80', 0),
(7, 'https://images.unsplash.com/photo-1484154218962-a197022b5858?w=600&q=80', 1),
(7, 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=600&q=80', 2),
(8, 'https://images.unsplash.com/photo-1570129477492-45c003edd2be?w=600&q=80', 0),
(8, 'https://images.unsplash.com/photo-1583608205776-bfd35f0d9f83?w=600&q=80', 1),
(9, 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=600&q=80', 0);

-- 🏍️ MOTOS (cat 4)
INSERT INTO annonces (utilisateur_id, categorie_id, titre, description, prix, etat, ville, quartier, nb_vues, created_at, expires_at) VALUES
(4, 4, 'Yamaha MT-07 2021 — 12 000 km', 'Yamaha MT-07 2021, 12 000 km au compteur, parfait état. Révision faite récemment, pneus neufs. Couleur Noir/Gris. Carnet d entretien complet. Idéale pour ville et route.', 2800000, 'tres_bon', 'Dakar', 'Sacré-Cœur', 445, DATE_SUB(NOW(), INTERVAL 4 HOUR), DATE_ADD(NOW(), INTERVAL 60 DAY)),
(5, 4, 'Honda CB 150 — 2022, comme neuf', 'Honda CB150 2022, moins de 5000 km. Premier propriétaire, toujours garée sous abri. Aucune chute. Tous papiers à jour. Idéale pour débutant ou usage quotidien.', 950000, 'tres_bon', 'Lomé', 'Nyékonakpoè', 189, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_ADD(NOW(), INTERVAL 60 DAY));

INSERT INTO photos (annonce_id, url, ordre) VALUES
(10, 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=600&q=80', 0),
(10, 'https://images.unsplash.com/photo-1609630875171-b1321377ee65?w=600&q=80', 1),
(11, 'https://images.unsplash.com/photo-1449426468159-d96dbf08f19f?w=600&q=80', 0);

-- 🚗 VOITURES (cat 5)
INSERT INTO annonces (utilisateur_id, categorie_id, titre, description, prix, etat, ville, quartier, nb_vues, created_at, expires_at) VALUES
(6, 5, 'Toyota Corolla Cross 2022 — Full options', 'Toyota Corolla Cross 2022, 18 000 km, full options. Caméra de recul, GPS, toit ouvrant, sièges chauffants. Première main, entretien Toyota. Couleur Blanc nacré.', 14500000, 'tres_bon', 'Cotonou', 'Cadjèhoun', 632, DATE_SUB(NOW(), INTERVAL 5 HOUR), DATE_ADD(NOW(), INTERVAL 60 DAY)),
(1, 5, 'Renault Duster 4x4 — 2020, Diesel', 'Renault Duster 4x4 2020, moteur diesel 1.5 dCi. Parfait pour tous terrains. 45 000 km, entretien régulier. Jantes aluminium, climatisation automatique. Couleur Gris.', 9200000, 'bon', 'Dakar', 'Liberté 6', 387, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 60 DAY));

INSERT INTO photos (annonce_id, url, ordre) VALUES
(12, 'https://images.unsplash.com/photo-1617469767908-de77b4b38bd4?w=600&q=80', 0),
(12, 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?w=600&q=80', 1),
(13, 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=600&q=80', 0),
(13, 'https://images.unsplash.com/photo-1489824904134-891ab64532f1?w=600&q=80', 1);

-- 🛋️ MEUBLES (cat 7)
INSERT INTO annonces (utilisateur_id, categorie_id, titre, description, prix, etat, ville, quartier, nb_vues, created_at, expires_at) VALUES
(2, 7, 'Canapé d angle en cuir — Noir, 6 places', 'Canapé d angle 6 places en cuir véritable, coloris noir. Très confortable, en bon état, légères traces d usure sur les accoudoirs. Largeur 280 cm. Cause déménagement.', 180000, 'bon', 'Abidjan', 'Marcory', 134, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_ADD(NOW(), INTERVAL 60 DAY)),
(3, 7, 'Lit King Size 180x200 + matelas', 'Ensemble lit King Size 180x200 cm en bois massif, coloris noyer. Matelas mousse haute densité 20 cm inclus. Excellent état, utilisé 1 an. Livraison possible.', 220000, 'tres_bon', 'Douala', 'Makepe', 98, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_ADD(NOW(), INTERVAL 60 DAY));

INSERT INTO photos (annonce_id, url, ordre) VALUES
(14, 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=600&q=80', 0),
(14, 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=600&q=80', 1),
(15, 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=600&q=80', 0);

-- 👗 VÊTEMENTS (cat 8)
INSERT INTO annonces (utilisateur_id, categorie_id, titre, description, prix, etat, ville, quartier, nb_vues, created_at, expires_at) VALUES
(4, 8, 'Nike Air Jordan 1 Retro High OG — 42', 'Air Jordan 1 Retro High OG coloris Chicago, pointure 42. Achetées il y a 6 mois, portées 3-4 fois seulement. Boîte originale, lacets de rechange inclus. Authentique.', 95000, 'tres_bon', 'Dakar', 'Mermoz', 267, DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_ADD(NOW(), INTERVAL 60 DAY)),
(5, 8, 'Costume 3 pièces sur mesure — Bleu marine', 'Costume 3 pièces tailleur, tissu laine italienne, coloris bleu marine. Taille 50 (L). Porté 2 fois pour des cérémonies. Parfait état. Idéal pour mariage ou événement.', 75000, 'tres_bon', 'Lomé', 'Bè', 89, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_ADD(NOW(), INTERVAL 60 DAY));

INSERT INTO photos (annonce_id, url, ordre) VALUES
(16, 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&q=80', 0),
(16, 'https://images.unsplash.com/photo-1607522370275-f14206abe5d3?w=600&q=80', 1),
(17, 'https://images.unsplash.com/photo-1594938298603-c8148c4b4c8e?w=600&q=80', 0);

-- 🎮 JEUX (cat 9)
INSERT INTO annonces (utilisateur_id, categorie_id, titre, description, prix, etat, ville, quartier, nb_vues, created_at, expires_at) VALUES
(6, 9, 'PlayStation 5 — Édition Standard + 3 jeux', 'PS5 édition standard, très bon état. Vendue avec 3 jeux : FIFA 24, God of War Ragnarök, Spider-Man 2. 2 manettes DualSense. Câbles et boîte d origine inclus.', 550000, 'tres_bon', 'Cotonou', 'Fidjrossè', 512, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 60 DAY)),
(1, 9, 'Nintendo Switch OLED — Blanc', 'Nintendo Switch OLED blanc, parfait état. Vendue avec dock, Joy-Con, câbles. Jeux Mario Kart 8 et Zelda inclus sur carte. Utilisée uniquement en mode portable.', 280000, 'tres_bon', 'Dakar', 'Point E', 198, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_ADD(NOW(), INTERVAL 60 DAY));

INSERT INTO photos (annonce_id, url, ordre) VALUES
(18, 'https://images.unsplash.com/photo-1607853202273-797f1c22a38e?w=600&q=80', 0),
(18, 'https://images.unsplash.com/photo-1625805866449-3ece1b31d74e?w=600&q=80', 1),
(19, 'https://images.unsplash.com/photo-1578303512597-81e6cc155b3e?w=600&q=80', 0);

-- 🍳 ÉLECTROMÉNAGER (cat 10)
INSERT INTO annonces (utilisateur_id, categorie_id, titre, description, prix, etat, ville, quartier, nb_vues, created_at, expires_at) VALUES
(2, 10, 'Réfrigérateur Samsung No Frost — 350L', 'Réfrigérateur Samsung No Frost 350L, 2 portes, inox. Acheté en 2022, parfait fonctionnement. Économique en énergie (A+). Cause : déménagement dans logement meublé.', 195000, 'tres_bon', 'Abidjan', 'Yopougon', 145, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_ADD(NOW(), INTERVAL 60 DAY)),
(3, 10, 'Machine à laver LG — 7 kg, Frontale', 'LG F4WV709S1E 7 kg, frontale, Wi-Fi intégré. 2 ans d utilisation, excellent état. Programmes variés, très silencieuse. Manuels inclus. Cause déménagement.', 160000, 'bon', 'Douala', 'Logbaba', 87, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_ADD(NOW(), INTERVAL 60 DAY));

INSERT INTO photos (annonce_id, url, ordre) VALUES
(20, 'https://images.unsplash.com/photo-1571175443880-49e1d25b2bc5?w=600&q=80', 0),
(21, 'https://images.unsplash.com/photo-1626806787461-102c1bfaaea1?w=600&q=80', 0);

-- 👔 EMPLOI (cat 6) — annonce gratuite
INSERT INTO annonces (utilisateur_id, categorie_id, titre, description, prix, est_gratuit, etat, ville, quartier, nb_vues, created_at, expires_at) VALUES
(4, 6, 'Recherche Développeur Web — CDI, Dakar', 'Startup fintech basée à Dakar recrute un développeur web full-stack. Compétences requises : PHP/Laravel ou Node.js, React, MySQL. 2 ans d expérience minimum. Salaire attractif + tickets resto.', NULL, 1, 'neuf', 'Dakar', 'Almadies', 334, DATE_SUB(NOW(), INTERVAL 12 HOUR), DATE_ADD(NOW(), INTERVAL 60 DAY));

INSERT INTO photos (annonce_id, url, ordre) VALUES
(22, 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80', 0);

-- Mettre à jour quelques compteurs de vues réalistes
UPDATE annonces SET nb_vues = FLOOR(50 + RAND() * 500);
