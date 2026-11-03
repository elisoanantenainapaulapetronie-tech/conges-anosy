-- =====================================================================
--  APPLICATION DE GESTION DES CONGES, ABSENCES ET MISSIONS - REGION ANOSY
--  Fichier 1/2 : STRUCTURE de la base + DONNEES DE REFERENCE
--  Moteur cible : MariaDB 10.4 ou plus (XAMPP), moteur InnoDB, utf8mb4
--
--  MODE D'EMPLOI
--   1. Creer d'abord une base VIDE (voir le guide), par exemple :
--        CREATE DATABASE conges_anosy_sql CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   2. Selectionner cette base, puis importer ce fichier en entier.
--   3. Ne pas l'importer deux fois dans la meme base (les tables existent deja).
--
--  CONVENTIONS
--   - Tous les identifiants sont des INT UNSIGNED (ou BIGINT UNSIGNED pour les journaux).
--   - Les agents ne sont JAMAIS supprimes : ils passent a la situation "sorti".
--     Les cles etrangeres vers agents sont donc en RESTRICT.
--   - Les durees sont en jours ouvres (DECIMAL(5,1) pour permettre les demi-journees).
--   - Les regles qui ne peuvent pas etre ecrites en SQL simple (calcul du circuit,
--     delegations, controle des droits a chaque requete) sont appliquees par
--     l'application, cote serveur.
-- =====================================================================

SET NAMES utf8mb4;

-- =====================================================================
-- 1. ORGANISATION
-- =====================================================================

CREATE TABLE structures (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom           VARCHAR(190) NOT NULL,
  code          VARCHAR(30)  NULL,
  type          ENUM('cabinet','direction','service','service_rattache','autre') NOT NULL,
  parent_id     INT UNSIGNED NULL,
  rattachement  ENUM('sg','region') NOT NULL COMMENT 'Circuit : Secretaire General ou Chef de Region',
  actif         TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_structures_code (code),
  KEY ix_structures_parent (parent_id),
  CONSTRAINT fk_structures_parent FOREIGN KEY (parent_id) REFERENCES structures (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fonctions (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  libelle     VARCHAR(120) NOT NULL COMMENT 'Libelle seulement : la fonction n est pas un droit',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fonctions_libelle (libelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE agents (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  matricule        VARCHAR(30)  NOT NULL,
  nom              VARCHAR(120) NOT NULL,
  prenoms          VARCHAR(160) NULL,
  sexe             ENUM('M','F') NOT NULL COMMENT 'Utile pour maternite et paternite',
  date_naissance   DATE         NULL,
  telephone        VARCHAR(30)  NULL,
  email            VARCHAR(190) NULL,
  fonction_id      INT UNSIGNED NULL,
  grade_categorie  VARCHAR(60)  NULL,
  structure_id     INT UNSIGNED NOT NULL,
  responsable_id   INT UNSIGNED NULL COMMENT 'Responsable hierarchique direct (une personne precise)',
  rattachement     ENUM('sg','region') NOT NULL COMMENT 'Renseigne par la RH, l agent ne peut pas le changer',
  date_entree      DATE         NOT NULL,
  date_sortie      DATE         NULL,
  situation        ENUM('actif','suspendu','sorti') NOT NULL DEFAULT 'actif',
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_agents_matricule (matricule),
  KEY ix_agents_structure (structure_id, situation),
  KEY ix_agents_responsable (responsable_id),
  KEY ix_agents_nom (nom, prenoms),
  CONSTRAINT fk_agents_fonction    FOREIGN KEY (fonction_id)    REFERENCES fonctions (id)  ON DELETE SET NULL,
  CONSTRAINT fk_agents_structure   FOREIGN KEY (structure_id)   REFERENCES structures (id) ON DELETE RESTRICT,
  CONSTRAINT fk_agents_responsable FOREIGN KEY (responsable_id) REFERENCES agents (id)     ON DELETE RESTRICT,
  CONSTRAINT ck_agents_sortie CHECK (date_sortie IS NULL OR date_sortie >= date_entree)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE affectations_historique (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id        INT UNSIGNED NOT NULL,
  structure_id    INT UNSIGNED NOT NULL,
  responsable_id  INT UNSIGNED NULL,
  rattachement    ENUM('sg','region') NOT NULL,
  date_debut      DATE         NOT NULL,
  date_fin        DATE         NULL,
  motif           VARCHAR(255) NULL,
  modifie_par     INT UNSIGNED NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_affect_agent (agent_id, date_debut),
  CONSTRAINT fk_affect_agent       FOREIGN KEY (agent_id)       REFERENCES agents (id)     ON DELETE RESTRICT,
  CONSTRAINT fk_affect_structure   FOREIGN KEY (structure_id)   REFERENCES structures (id) ON DELETE RESTRICT,
  CONSTRAINT fk_affect_responsable FOREIGN KEY (responsable_id) REFERENCES agents (id)     ON DELETE RESTRICT,
  CONSTRAINT fk_affect_modifie_par FOREIGN KEY (modifie_par)    REFERENCES agents (id)     ON DELETE RESTRICT,
  CONSTRAINT ck_affect_dates CHECK (date_fin IS NULL OR date_fin >= date_debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 2. COMPTES, ROLES ET HABILITATIONS
-- =====================================================================

CREATE TABLE roles (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code         VARCHAR(40)  NOT NULL,
  libelle      VARCHAR(120) NOT NULL,
  description  VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un compte est cree APRES la fiche (la fiche existe sans compte).
-- Le mot de passe est choisi par l'agent a l'activation : il est vide avant.
CREATE TABLE comptes (
  id                          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id                    INT UNSIGNED NOT NULL,
  mot_de_passe_hash           VARCHAR(255) NULL,
  statut                      ENUM('en_attente_activation','actif','desactive') NOT NULL DEFAULT 'en_attente_activation',
  code_activation_hash        VARCHAR(255) NULL,
  code_activation_expire_le   DATETIME NULL,
  active_le                   DATETIME NULL,
  telephone_verifie_le        DATETIME NULL,
  double_auth_active          TINYINT(1) NOT NULL DEFAULT 0,
  double_auth_secret          VARCHAR(255) NULL,
  echecs_connexion            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloque_jusqua               DATETIME NULL,
  derniere_connexion_le       DATETIME NULL,
  langue                      ENUM('fr','mg') NOT NULL DEFAULT 'fr',
  created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_comptes_agent (agent_id),
  CONSTRAINT fk_comptes_agent FOREIGN KEY (agent_id) REFERENCES agents (id) ON DELETE RESTRICT,
  CONSTRAINT ck_comptes_actif CHECK (statut <> 'actif' OR mot_de_passe_hash IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liens de reinitialisation a usage unique (administrateur technique).
CREATE TABLE reinitialisations_mot_de_passe (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  compte_id   INT UNSIGNED NOT NULL,
  jeton_hash  CHAR(64)     NOT NULL,
  demande_par INT UNSIGNED NULL,
  expire_le   DATETIME     NOT NULL,
  utilise_le  DATETIME     NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reinit_jeton (jeton_hash),
  CONSTRAINT fk_reinit_compte FOREIGN KEY (compte_id)   REFERENCES comptes (id) ON DELETE CASCADE,
  CONSTRAINT fk_reinit_par    FOREIGN KEY (demande_par) REFERENCES agents (id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une habilitation relie une personne, un role, un perimetre et des dates.
-- Le role "Agent" n'est pas stocke : tout le monde est Agent.
CREATE TABLE habilitations (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id        INT UNSIGNED NOT NULL,
  role_id         INT UNSIGNED NOT NULL,
  structure_id    INT UNSIGNED NULL COMMENT 'Perimetre (service ou direction)',
  date_debut      DATE         NOT NULL,
  date_fin        DATE         NULL,
  acte_reference  VARCHAR(190) NULL COMMENT 'Ex. Note de service n 12',
  attribuee_par   INT UNSIGNED NULL COMMENT 'NULL seulement pour le premier compte cree par script',
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_habil_agent (agent_id, role_id),
  KEY ix_habil_structure (structure_id),
  CONSTRAINT fk_habil_agent     FOREIGN KEY (agent_id)      REFERENCES agents (id)     ON DELETE RESTRICT,
  CONSTRAINT fk_habil_role      FOREIGN KEY (role_id)       REFERENCES roles (id)      ON DELETE RESTRICT,
  CONSTRAINT fk_habil_structure FOREIGN KEY (structure_id)  REFERENCES structures (id) ON DELETE RESTRICT,
  CONSTRAINT fk_habil_attribuee FOREIGN KEY (attribuee_par) REFERENCES agents (id)     ON DELETE RESTRICT,
  CONSTRAINT ck_habil_dates CHECK (date_fin IS NULL OR date_fin >= date_debut),
  CONSTRAINT ck_habil_pas_soi_meme CHECK (attribuee_par IS NULL OR attribuee_par <> agent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. PARAMETRAGE
-- =====================================================================

CREATE TABLE types_demande (
  id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code                     VARCHAR(40)  NOT NULL,
  libelle                  VARCHAR(160) NOT NULL,
  famille                  ENUM('conge','absence','mission','declaration') NOT NULL,
  avis_prealable           TINYINT(1)   NOT NULL DEFAULT 0,
  controle_rh_obligatoire  TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'Modifiable par la RH responsable',
  decompte_solde           ENUM('oui','non','sur_decision') NOT NULL DEFAULT 'non',
  circuit                  ENUM('decision','avis_responsable_seul','information_responsable','selon_duree') NOT NULL,
  plafond_jours            DECIMAL(5,1) NULL,
  pieces_requises          JSON NULL,
  actif                    TINYINT(1)   NOT NULL DEFAULT 1,
  created_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_types_code (code),
  CONSTRAINT ck_types_plafond CHECK (plafond_jours IS NULL OR plafond_jours > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jours_feries (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  date_ferie  DATE         NOT NULL,
  libelle     VARCHAR(160) NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_feries_date (date_ferie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE parametres (
  cle          VARCHAR(60)  NOT NULL,
  valeur       VARCHAR(255) NULL,
  description  VARCHAR(255) NULL,
  modifie_par  INT UNSIGNED NULL,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (cle),
  CONSTRAINT fk_param_modifie_par FOREIGN KEY (modifie_par) REFERENCES agents (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Numerotation : AV-2026-0087, DM-2026-0310, DEC-2026-0001, OM-2026-0001...
CREATE TABLE compteurs_references (
  prefixe        VARCHAR(10)       NOT NULL,
  annee          SMALLINT UNSIGNED NOT NULL,
  dernier_numero INT UNSIGNED      NOT NULL DEFAULT 0,
  PRIMARY KEY (prefixe, annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 4. AVIS PREALABLE, DELEGATIONS, DEMANDES
-- =====================================================================

-- Avis prealable du responsable direct. L'avis defavorable est consultatif
-- et son motif est obligatoire. L'autorisation de soumettre est emise dans
-- les deux cas et expire apres 7 jours calendaires.
CREATE TABLE avis_prealables (
  id                        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference                 VARCHAR(30)  NOT NULL,
  agent_id                  INT UNSIGNED NOT NULL,
  type_demande_id           INT UNSIGNED NOT NULL,
  responsable_id            INT UNSIGNED NULL COMMENT 'Personne designee pour donner l avis',
  avis_donne_par            INT UNSIGNED NULL COMMENT 'Personne qui a reellement repondu (delegataire possible)',
  date_debut                DATE         NOT NULL,
  date_fin                  DATE         NOT NULL,
  duree_jours               DECIMAL(5,1) NOT NULL,
  motif                     TEXT         NULL,
  continuite_service        TEXT         NULL,
  statut                    ENUM('brouillon','en_attente','rendu','retire','expire') NOT NULL DEFAULT 'brouillon',
  sens_avis                 ENUM('favorable','defavorable') NULL,
  motif_avis                TEXT         NULL,
  autres_dates_proposees    VARCHAR(255) NULL,
  envoye_le                 DATETIME     NULL,
  avis_rendu_le             DATETIME     NULL,
  autorisation_emise_le     DATETIME     NULL,
  autorisation_expire_le    DATETIME     NULL,
  autorisation_utilisee_le  DATETIME     NULL,
  created_at                DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_avis_reference (reference),
  KEY ix_avis_agent (agent_id, statut),
  KEY ix_avis_responsable (responsable_id, statut),
  KEY ix_avis_expiration (autorisation_expire_le),
  CONSTRAINT fk_avis_agent       FOREIGN KEY (agent_id)        REFERENCES agents (id)        ON DELETE RESTRICT,
  CONSTRAINT fk_avis_type        FOREIGN KEY (type_demande_id) REFERENCES types_demande (id) ON DELETE RESTRICT,
  CONSTRAINT fk_avis_responsable FOREIGN KEY (responsable_id)  REFERENCES agents (id)        ON DELETE RESTRICT,
  CONSTRAINT fk_avis_donne_par   FOREIGN KEY (avis_donne_par)  REFERENCES agents (id)        ON DELETE RESTRICT,
  CONSTRAINT ck_avis_dates   CHECK (date_fin >= date_debut),
  CONSTRAINT ck_avis_duree   CHECK (duree_jours > 0),
  CONSTRAINT ck_avis_motif   CHECK (sens_avis IS NULL OR sens_avis = 'favorable' OR (motif_avis IS NOT NULL AND motif_avis <> '')),
  CONSTRAINT ck_avis_pas_soi CHECK (responsable_id IS NULL OR responsable_id <> agent_id),
  CONSTRAINT ck_avis_pas_soi2 CHECK (avis_donne_par IS NULL OR avis_donne_par <> agent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Delegation obligatoire pour le Directeur, le SG et le Chef de Region.
CREATE TABLE delegations (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  delegant_id    INT UNSIGNED NOT NULL,
  delegataire_id INT UNSIGNED NOT NULL,
  date_debut     DATE         NOT NULL,
  date_fin       DATE         NOT NULL,
  statut         ENUM('planifiee','active','terminee','annulee') NOT NULL DEFAULT 'planifiee',
  motif          VARCHAR(255) NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_deleg_delegant (delegant_id, statut),
  KEY ix_deleg_delegataire (delegataire_id, statut),
  CONSTRAINT fk_deleg_delegant    FOREIGN KEY (delegant_id)    REFERENCES agents (id) ON DELETE RESTRICT,
  CONSTRAINT fk_deleg_delegataire FOREIGN KEY (delegataire_id) REFERENCES agents (id) ON DELETE RESTRICT,
  CONSTRAINT ck_deleg_dates CHECK (date_fin >= date_debut),
  CONSTRAINT ck_deleg_distincts CHECK (delegant_id <> delegataire_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demande officielle : conge, autorisation d'absence, mission ou declaration
-- (maladie, maternite, paternite). Le circuit est calcule et fige a la soumission.
CREATE TABLE demandes (
  id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference                VARCHAR(30)  NOT NULL,
  avis_id                  INT UNSIGNED NULL,
  agent_id                 INT UNSIGNED NOT NULL,
  type_demande_id          INT UNSIGNED NOT NULL,
  chef_projet_id           INT UNSIGNED NULL COMMENT 'Conge lie a un projet personnel : avis du chef de projet',
  date_debut               DATE         NOT NULL,
  date_fin                 DATE         NOT NULL,
  heure_debut              TIME         NULL,
  heure_fin                TIME         NULL,
  duree_jours              DECIMAL(5,1) NOT NULL,
  motif                    TEXT         NULL,
  contact_pendant_absence  VARCHAR(190) NULL,
  circuit                  ENUM('sg','region','responsable','information') NULL,
  statut                   ENUM('brouillon','soumise','en_controle_rh','a_completer','en_attente_decision',
                                'validee','enregistree','refusee','irrecevable','annulee','caduque',
                                'planifiee','en_cours','terminee','interrompue') NOT NULL DEFAULT 'brouillon',
  urgence                  TINYINT(1)   NOT NULL DEFAULT 0,
  regularisation           TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Demande faite apres l evenement',
  motif_regularisation     VARCHAR(255) NULL,
  derogation               TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Plus de 20 jours ou 3e demande',
  conformite_rh            ENUM('conforme','non_conforme') NULL,
  conformite_commentaire   TEXT         NULL,
  version                  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  soumise_le               DATETIME     NULL,
  decidee_le               DATETIME     NULL,
  enregistree_le           DATETIME     NULL,
  reponse_attendue_avant   DATETIME     NULL COMMENT '5 jours pour completer, sinon caduque',
  annulation_demandee_le   DATETIME     NULL,
  motif_annulation         VARCHAR(255) NULL,
  created_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_demandes_reference (reference),
  UNIQUE KEY uq_demandes_avis (avis_id),
  KEY ix_demandes_agent (agent_id, statut),
  KEY ix_demandes_statut (statut, circuit),
  KEY ix_demandes_periode (date_debut, date_fin),
  CONSTRAINT fk_demandes_avis   FOREIGN KEY (avis_id)         REFERENCES avis_prealables (id) ON DELETE SET NULL,
  CONSTRAINT fk_demandes_agent  FOREIGN KEY (agent_id)        REFERENCES agents (id)          ON DELETE RESTRICT,
  CONSTRAINT fk_demandes_type   FOREIGN KEY (type_demande_id) REFERENCES types_demande (id)   ON DELETE RESTRICT,
  CONSTRAINT fk_demandes_projet FOREIGN KEY (chef_projet_id)  REFERENCES agents (id)          ON DELETE RESTRICT,
  CONSTRAINT ck_demandes_dates  CHECK (date_fin >= date_debut),
  CONSTRAINT ck_demandes_duree  CHECK (duree_jours > 0),
  CONSTRAINT ck_demandes_heures CHECK (heure_debut IS NULL OR heure_fin IS NULL OR heure_fin > heure_debut),
  CONSTRAINT ck_demandes_regul  CHECK (regularisation = 0 OR (motif_regularisation IS NOT NULL AND motif_regularisation <> '')),
  CONSTRAINT ck_demandes_projet CHECK (chef_projet_id IS NULL OR chef_projet_id <> agent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE missions (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  demande_id         INT UNSIGNED NOT NULL,
  objet              VARCHAR(255) NOT NULL,
  destination        VARCHAR(255) NOT NULL,
  objectifs          TEXT         NULL,
  moyen_transport    VARCHAR(100) NULL,
  frais_estimes      DECIMAL(12,2) NULL,
  nature             ENUM('institutionnelle','projet') NOT NULL DEFAULT 'institutionnelle',
  numero_ordre       VARCHAR(30)  NULL,
  statut_cloture     ENUM('en_cours','a_cloturer','cloturee') NOT NULL DEFAULT 'en_cours',
  rapport_depose_le  DATETIME     NULL,
  rapport_chemin     VARCHAR(255) NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_missions_demande (demande_id),
  UNIQUE KEY uq_missions_ordre (numero_ordre),
  CONSTRAINT fk_missions_demande FOREIGN KEY (demande_id) REFERENCES demandes (id) ON DELETE CASCADE,
  CONSTRAINT ck_missions_frais CHECK (frais_estimes IS NULL OR frais_estimes >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Etapes figees du circuit : qui doit faire quoi, avant quand.
-- agent_effectif_id + delegation_id : "par X par delegation de Y".
CREATE TABLE demande_etapes (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  demande_id         INT UNSIGNED NOT NULL,
  ordre              TINYINT UNSIGNED NOT NULL,
  role_etape         ENUM('avis_responsable','avis_chef_projet','approbation_responsable',
                          'controle_rh','decision','information') NOT NULL,
  acteur_id          INT UNSIGNED NULL,
  agent_effectif_id  INT UNSIGNED NULL,
  delegation_id      INT UNSIGNED NULL,
  statut             ENUM('en_attente','accomplie','ignoree','escaladee') NOT NULL DEFAULT 'en_attente',
  resultat           ENUM('favorable','defavorable','recevable','a_completer','irrecevable',
                          'approuve','refuse','pris_connaissance') NULL,
  commentaire        TEXT         NULL,
  echeance_le        DATETIME     NULL,
  rappel_envoye_le   DATETIME     NULL,
  accomplie_le       DATETIME     NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_etapes_ordre (demande_id, ordre),
  KEY ix_etapes_acteur (acteur_id, statut),
  KEY ix_etapes_echeance (statut, echeance_le),
  CONSTRAINT fk_etapes_demande    FOREIGN KEY (demande_id)        REFERENCES demandes (id)    ON DELETE CASCADE,
  CONSTRAINT fk_etapes_acteur     FOREIGN KEY (acteur_id)         REFERENCES agents (id)      ON DELETE RESTRICT,
  CONSTRAINT fk_etapes_effectif   FOREIGN KEY (agent_effectif_id) REFERENCES agents (id)      ON DELETE RESTRICT,
  CONSTRAINT fk_etapes_delegation FOREIGN KEY (delegation_id)     REFERENCES delegations (id) ON DELETE SET NULL,
  CONSTRAINT ck_etapes_motif CHECK (resultat IS NULL OR resultat NOT IN ('defavorable','refuse') OR (commentaire IS NOT NULL AND commentaire <> ''))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique : chaque changement d'etat est horodate et conserve (non modifiable).
CREATE TABLE demande_historique (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  demande_id      INT UNSIGNED NOT NULL,
  ancien_statut   VARCHAR(30)  NULL,
  nouveau_statut  VARCHAR(30)  NOT NULL,
  auteur_id       INT UNSIGNED NULL COMMENT 'NULL = action automatique du systeme',
  commentaire     TEXT         NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_hist_demande (demande_id, created_at),
  CONSTRAINT fk_hist_demande FOREIGN KEY (demande_id) REFERENCES demandes (id) ON DELETE RESTRICT,
  CONSTRAINT fk_hist_auteur  FOREIGN KEY (auteur_id)  REFERENCES agents (id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Le Chef de Region et le SG ne deposent pas de demande : ils declarent
-- leurs dates d'indisponibilite et designent un delegataire.
CREATE TABLE indisponibilites (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id       INT UNSIGNED NOT NULL,
  nature         ENUM('conge','mission','autre') NOT NULL,
  date_debut     DATE         NOT NULL,
  date_fin       DATE         NOT NULL,
  motif          VARCHAR(255) NULL,
  delegation_id  INT UNSIGNED NOT NULL,
  duree_jours    DECIMAL(5,1) NULL COMMENT 'Sert a ajuster le solde si c est un conge',
  notifiee_le    DATETIME     NULL,
  annulee_le     DATETIME     NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_indispo_agent (agent_id, date_debut, date_fin),
  CONSTRAINT fk_indispo_agent      FOREIGN KEY (agent_id)      REFERENCES agents (id)      ON DELETE RESTRICT,
  CONSTRAINT fk_indispo_delegation FOREIGN KEY (delegation_id) REFERENCES delegations (id) ON DELETE RESTRICT,
  CONSTRAINT ck_indispo_dates CHECK (date_fin >= date_debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 5. SOLDES : PROPOSITIONS PUIS REGISTRE DES MOUVEMENTS
-- =====================================================================

-- Toute saisie manuelle (solde initial, correction, report, indemnisation, perte)
-- est d'abord une PROPOSITION, controlee par une autre personne.
-- Personne n'ecrit ni ne controle son propre solde.
CREATE TABLE soldes_propositions (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id              INT UNSIGNED NOT NULL COMMENT 'Personne concernee',
  annee                 SMALLINT UNSIGNED NOT NULL,
  nature                ENUM('solde_initial','correction','report','indemnisation','perte') NOT NULL,
  jours                 DECIMAL(6,2) NOT NULL COMMENT 'Positif ou negatif',
  motif                 VARCHAR(500) NOT NULL,
  propose_par           INT UNSIGNED NOT NULL,
  statut                ENUM('en_attente','valide','rejete') NOT NULL DEFAULT 'en_attente',
  controle_par          INT UNSIGNED NULL,
  controle_le           DATETIME     NULL,
  commentaire_controle  TEXT         NULL,
  created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_prop_agent (agent_id, statut),
  CONSTRAINT fk_prop_agent    FOREIGN KEY (agent_id)     REFERENCES agents (id) ON DELETE RESTRICT,
  CONSTRAINT fk_prop_propose  FOREIGN KEY (propose_par)  REFERENCES agents (id) ON DELETE RESTRICT,
  CONSTRAINT fk_prop_controle FOREIGN KEY (controle_par) REFERENCES agents (id) ON DELETE RESTRICT,
  CONSTRAINT ck_prop_jours    CHECK (jours <> 0),
  CONSTRAINT ck_prop_pas_soi  CHECK (propose_par <> agent_id),
  CONSTRAINT ck_prop_controle CHECK (controle_par IS NULL OR (controle_par <> propose_par AND controle_par <> agent_id)),
  CONSTRAINT ck_prop_statut   CHECK (statut = 'en_attente' OR (controle_par IS NOT NULL AND controle_le IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pieces jointes : stockees hors du dossier public ; "medicale" = lecture reservee a la RH habilitee.
CREATE TABLE pieces_jointes (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  demande_id      INT UNSIGNED NULL,
  avis_id         INT UNSIGNED NULL,
  proposition_id  INT UNSIGNED NULL,
  nom_original    VARCHAR(255) NOT NULL,
  chemin          VARCHAR(255) NOT NULL,
  mime            VARCHAR(100) NOT NULL,
  taille_octets   INT UNSIGNED NOT NULL,
  medicale        TINYINT(1)   NOT NULL DEFAULT 0,
  depose_par      INT UNSIGNED NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pieces_demande (demande_id),
  KEY ix_pieces_avis (avis_id),
  KEY ix_pieces_proposition (proposition_id),
  CONSTRAINT fk_pieces_demande     FOREIGN KEY (demande_id)     REFERENCES demandes (id)           ON DELETE RESTRICT,
  CONSTRAINT fk_pieces_avis        FOREIGN KEY (avis_id)        REFERENCES avis_prealables (id)    ON DELETE RESTRICT,
  CONSTRAINT fk_pieces_proposition FOREIGN KEY (proposition_id) REFERENCES soldes_propositions (id) ON DELETE RESTRICT,
  CONSTRAINT fk_pieces_depose_par  FOREIGN KEY (depose_par)     REFERENCES agents (id)             ON DELETE SET NULL,
  CONSTRAINT ck_pieces_rattachement CHECK (demande_id IS NOT NULL OR avis_id IS NOT NULL OR proposition_id IS NOT NULL),
  CONSTRAINT ck_pieces_taille CHECK (taille_octets > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chaque consultation d'une piece medicale est journalisee (non modifiable).
CREATE TABLE consultations_pieces (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  piece_id     INT UNSIGNED NOT NULL,
  agent_id     INT UNSIGNED NOT NULL,
  consulte_le  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  adresse_ip   VARCHAR(45)  NULL,
  PRIMARY KEY (id),
  KEY ix_consult_piece (piece_id, consulte_le),
  CONSTRAINT fk_consult_piece FOREIGN KEY (piece_id) REFERENCES pieces_jointes (id) ON DELETE RESTRICT,
  CONSTRAINT fk_consult_agent FOREIGN KEY (agent_id) REFERENCES agents (id)         ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registre des mouvements, comme un carnet de banque.
--   solde disponible = SUM(delta_disponible)   jours reserves = SUM(delta_reserve)
--   jours consommes  = SUM(delta_consomme)
-- On n'ecrit jamais "le solde est 17". Une erreur se corrige par une NOUVELLE ligne :
-- les declencheurs en bas du fichier interdisent toute modification ou suppression.
CREATE TABLE soldes_mouvements (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  agent_id          INT UNSIGNED NOT NULL,
  annee             SMALLINT UNSIGNED NOT NULL,
  type_mouvement    ENUM('solde_initial','droit_annuel','report','reservation','liberation',
                         'consommation','correction','indemnisation','perte') NOT NULL,
  delta_disponible  DECIMAL(6,2) NOT NULL DEFAULT 0,
  delta_reserve     DECIMAL(6,2) NOT NULL DEFAULT 0,
  delta_consomme    DECIMAL(6,2) NOT NULL DEFAULT 0,
  demande_id        INT UNSIGNED NULL,
  proposition_id    INT UNSIGNED NULL,
  motif             VARCHAR(255) NULL,
  auteur_id         INT UNSIGNED NULL COMMENT 'NULL = mouvement automatique du systeme',
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_mouv_agent (agent_id, annee),
  KEY ix_mouv_demande (demande_id),
  CONSTRAINT fk_mouv_agent       FOREIGN KEY (agent_id)       REFERENCES agents (id)              ON DELETE RESTRICT,
  CONSTRAINT fk_mouv_demande     FOREIGN KEY (demande_id)     REFERENCES demandes (id)            ON DELETE RESTRICT,
  CONSTRAINT fk_mouv_proposition FOREIGN KEY (proposition_id) REFERENCES soldes_propositions (id) ON DELETE RESTRICT,
  CONSTRAINT fk_mouv_auteur      FOREIGN KEY (auteur_id)      REFERENCES agents (id)              ON DELETE RESTRICT,
  CONSTRAINT ck_mouv_non_nul CHECK (delta_disponible <> 0 OR delta_reserve <> 0 OR delta_consomme <> 0),
  CONSTRAINT ck_mouv_manuel  CHECK (type_mouvement NOT IN ('solde_initial','correction','report','indemnisation','perte')
                                    OR proposition_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 6. DOCUMENTS, NOTIFICATIONS, AUDIT
-- =====================================================================

-- Decision de conge, ordre de mission, attestation : PDF avec numero et QR de verification.
CREATE TABLE documents (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type_document      ENUM('decision_conge','ordre_mission','attestation_absence') NOT NULL,
  numero             VARCHAR(40)  NOT NULL,
  code_verification  CHAR(40)     NOT NULL COMMENT 'Contenu du QR code',
  demande_id         INT UNSIGNED NOT NULL,
  chemin             VARCHAR(255) NOT NULL,
  genere_le          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  genere_par         INT UNSIGNED NULL COMMENT 'NULL = genere automatiquement',
  PRIMARY KEY (id),
  UNIQUE KEY uq_documents_numero (numero),
  UNIQUE KEY uq_documents_code (code_verification),
  KEY ix_documents_demande (demande_id),
  CONSTRAINT fk_documents_demande FOREIGN KEY (demande_id) REFERENCES demandes (id) ON DELETE RESTRICT,
  CONSTRAINT fk_documents_par     FOREIGN KEY (genere_par) REFERENCES agents (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification interne (obligatoire pour tout compte) ; les e-mails et SMS
-- sont traces dans envois_notifications.
CREATE TABLE notifications (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  destinataire_id  INT UNSIGNED NOT NULL,
  type_evenement   VARCHAR(60)  NOT NULL COMMENT 'Ex. avis_demande, avis_rendu, a_completer, validee',
  titre            VARCHAR(190) NOT NULL,
  message          TEXT         NULL,
  importance       ENUM('normale','importante') NOT NULL DEFAULT 'normale',
  demande_id       INT UNSIGNED NULL,
  avis_id          INT UNSIGNED NULL,
  lue_le           DATETIME     NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_notif_destinataire (destinataire_id, lue_le),
  CONSTRAINT fk_notif_destinataire FOREIGN KEY (destinataire_id) REFERENCES agents (id)           ON DELETE RESTRICT,
  CONSTRAINT fk_notif_demande      FOREIGN KEY (demande_id)      REFERENCES demandes (id)         ON DELETE SET NULL,
  CONSTRAINT fk_notif_avis         FOREIGN KEY (avis_id)         REFERENCES avis_prealables (id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE envois_notifications (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  notification_id  BIGINT UNSIGNED NOT NULL,
  canal            ENUM('interne','email','sms','main_propre') NOT NULL,
  adresse          VARCHAR(190) NULL,
  statut           ENUM('en_attente','envoye','echec') NOT NULL DEFAULT 'en_attente',
  envoye_le        DATETIME     NULL,
  erreur           VARCHAR(255) NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_envois_notification (notification_id),
  KEY ix_envois_statut (statut, canal),
  CONSTRAINT fk_envois_notification FOREIGN KEY (notification_id) REFERENCES notifications (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal d'audit : ni modifiable ni supprimable.
CREATE TABLE audit_logs (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  auteur_id     INT UNSIGNED NULL,
  action        VARCHAR(80)  NOT NULL,
  objet_type    VARCHAR(80)  NULL,
  objet_id      BIGINT UNSIGNED NULL,
  valeur_avant  JSON NULL,
  valeur_apres  JSON NULL,
  refuse        TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = action refusee par le serveur',
  adresse_ip    VARCHAR(45)  NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_audit_objet (objet_type, objet_id),
  KEY ix_audit_auteur (auteur_id, created_at),
  CONSTRAINT fk_audit_auteur FOREIGN KEY (auteur_id) REFERENCES agents (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 7. DECLENCHEURS : tables en ecriture seule (ajout oui, modification non)
-- =====================================================================

CREATE TRIGGER tr_soldes_mouvements_bu BEFORE UPDATE ON soldes_mouvements
  FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Mouvement de solde non modifiable : creer un mouvement de correction';
CREATE TRIGGER tr_soldes_mouvements_bd BEFORE DELETE ON soldes_mouvements
  FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Mouvement de solde non supprimable';

CREATE TRIGGER tr_audit_logs_bu BEFORE UPDATE ON audit_logs
  FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal d audit non modifiable';
CREATE TRIGGER tr_audit_logs_bd BEFORE DELETE ON audit_logs
  FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal d audit non supprimable';

CREATE TRIGGER tr_demande_historique_bu BEFORE UPDATE ON demande_historique
  FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historique de demande non modifiable';
CREATE TRIGGER tr_demande_historique_bd BEFORE DELETE ON demande_historique
  FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historique de demande non supprimable';

CREATE TRIGGER tr_consultations_pieces_bu BEFORE UPDATE ON consultations_pieces
  FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal de consultation non modifiable';
CREATE TRIGGER tr_consultations_pieces_bd BEFORE DELETE ON consultations_pieces
  FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Journal de consultation non supprimable';

-- =====================================================================
-- 8. VUES : soldes, planning, retards
-- =====================================================================

-- Solde disponible = droits + report - consommes - reserves.
-- Solde previsionnel = solde disponible - jours en attente de decision.
CREATE VIEW v_soldes_agents AS
SELECT a.id AS agent_id,
       a.matricule,
       a.nom,
       a.prenoms,
       s.annee,
       s.solde_disponible,
       s.jours_reserves,
       s.jours_consommes,
       COALESCE(w.jours_en_attente, 0) AS jours_en_attente,
       s.solde_disponible - COALESCE(w.jours_en_attente, 0) AS solde_previsionnel
FROM (
       SELECT agent_id, annee,
              SUM(delta_disponible) AS solde_disponible,
              SUM(delta_reserve)    AS jours_reserves,
              SUM(delta_consomme)   AS jours_consommes
       FROM soldes_mouvements
       GROUP BY agent_id, annee
     ) s
JOIN agents a ON a.id = s.agent_id
LEFT JOIN (
       SELECT d.agent_id, YEAR(d.date_debut) AS annee, SUM(d.duree_jours) AS jours_en_attente
       FROM demandes d
       JOIN types_demande t ON t.id = d.type_demande_id
       WHERE d.statut IN ('soumise','en_controle_rh','a_completer','en_attente_decision')
         AND t.decompte_solde = 'oui'
       GROUP BY d.agent_id, YEAR(d.date_debut)
     ) w ON w.agent_id = s.agent_id AND w.annee = s.annee;

-- Planning global : conges, absences, missions confirmes + indisponibilites declarees.
CREATE VIEW v_planning_global AS
SELECT 'demande' AS source, d.id AS objet_id, d.agent_id, a.structure_id,
       t.libelle AS nature, d.date_debut, d.date_fin, d.statut
FROM demandes d
JOIN agents a ON a.id = d.agent_id
JOIN types_demande t ON t.id = d.type_demande_id
WHERE d.statut IN ('enregistree','planifiee','en_cours','terminee')
UNION ALL
SELECT 'indisponibilite', i.id, i.agent_id, a.structure_id,
       CONCAT('Indisponibilite (', i.nature, ')'), i.date_debut, i.date_fin, 'declaree'
FROM indisponibilites i
JOIN agents a ON a.id = i.agent_id
WHERE i.annulee_le IS NULL;

-- Etapes en retard : sert aux rappels, alertes RH et escalades.
CREATE VIEW v_etapes_en_retard AS
SELECT e.id AS etape_id, e.demande_id, d.reference, e.role_etape, e.acteur_id,
       e.echeance_le, e.rappel_envoye_le
FROM demande_etapes e
JOIN demandes d ON d.id = e.demande_id
WHERE e.statut = 'en_attente'
  AND e.echeance_le IS NOT NULL
  AND e.echeance_le < NOW();

-- =====================================================================
-- 9. DONNEES DE REFERENCE
-- =====================================================================

INSERT INTO roles (id, code, libelle, description) VALUES
 (1, 'responsable_hierarchique', 'Responsable hierarchique', 'Avis prealable, approbation des absences courtes, information'),
 (2, 'secretaire_general',       'Secretaire General',       'Decision du circuit SG ; avis sur les demandes des Directeurs'),
 (3, 'chef_region',              'Chef de Region',           'Decision du circuit Region'),
 (4, 'rh_gestionnaire',          'RH gestionnaire',          'Fiches, controle des demandes, propositions de solde'),
 (5, 'rh_responsable',           'RH responsable',           'Contre-validation, parametres, habilitations, journal d audit'),
 (6, 'admin_technique',          'Administrateur technique', 'Comptes techniques, sauvegardes ; sans acces aux pieces medicales');

INSERT INTO structures (id, nom, code, type, parent_id, rattachement) VALUES
 (1,  'Cabinet du Chef de Region',                    'CAB',    'cabinet',          NULL, 'region'),
 (2,  'Service de la communication',                  'COM',    'service_rattache', NULL, 'region'),
 (3,  'Service des marches publics',                  'MP',     'service_rattache', NULL, 'region'),
 (4,  'Secretariat General',                          'SG',     'autre',            NULL, 'sg'),
 (5,  'Direction des Affaires generales',             'DAG',    'direction',        4,    'sg'),
 (6,  'Service Gestion des ressources humaines',      'SRH',    'service',          5,    'sg'),
 (7,  'Service Programmation et suivi-evaluation',    'SPSE',   'service',          5,    'sg'),
 (8,  'Service des Affaires juridiques',              'SAJ',    'service',          5,    'sg'),
 (9,  'Service Systeme d information',                'SSI',    'service',          5,    'sg'),
 (10, 'Direction du Developpement economique',        'DDE',    'direction',        4,    'sg'),
 (11, 'Direction des Affaires financieres',           'DAF',    'direction',        4,    'sg'),
 (12, 'Service Comptabilite',                         'COMPTA', 'service',          11,   'sg'),
 (13, 'Direction des Affaires sociales',              'DAS',    'direction',        4,    'sg');

INSERT INTO fonctions (libelle) VALUES
 ('Chef de Region'), ('Secretaire General'), ('Directeur de cabinet'), ('Directeur'),
 ('Chef de service'), ('Attache administratif'), ('Assistant'), ('Consultant'), ('Expert'),
 ('Conseiller'), ('Charge de mission'), ('Inspecteur'), ('Secretaire'),
 ('Chauffeur'), ('Gardien'), ('Technicien'), ('Magasinier');

INSERT INTO types_demande (code, libelle, famille, avis_prealable, controle_rh_obligatoire, decompte_solde, circuit, plafond_jours, pieces_requises) VALUES
 ('conge_annuel',           'Conge annuel',                    'conge',       1, 1, 'oui',          'decision',                20,   '[]'),
 ('maladie',                'Maladie',                         'declaration', 0, 1, 'non',          'information_responsable', NULL, '["Certificat medical"]'),
 ('maternite',              'Maternite',                       'declaration', 0, 1, 'non',          'information_responsable', NULL, '["Certificat medical"]'),
 ('paternite',              'Paternite',                       'declaration', 0, 1, 'non',          'information_responsable', NULL, '["Acte de naissance"]'),
 ('evenement_familial',     'Evenement familial ou deces',     'conge',       0, 1, 'non',          'avis_responsable_seul',   NULL, '["Justificatif (acte)"]'),
 ('conge_formation',        'Conge de formation',              'conge',       1, 1, 'sur_decision', 'decision',                NULL, '["Convocation ou programme"]'),
 ('conge_projet_personnel', 'Conge lie a un projet personnel', 'conge',       1, 1, 'sur_decision', 'decision',                NULL, '[]'),
 ('autorisation_absence',   'Autorisation d absence',          'absence',     1, 1, 'non',          'selon_duree',             NULL, '[]'),
 ('mission',                'Mission',                         'mission',     1, 1, 'non',          'decision',                NULL, '["Objet et destination"]');

INSERT INTO parametres (cle, valeur, description) VALUES
 ('droit_annuel_jours',            '30', 'Droit annuel en jours [A definir avec la RH]'),
 ('plafond_jours_par_demande',     '20', 'Au-dela : derogation avec avis de conformite de la RH'),
 ('max_demandes_conge_annuel',     '2',  'Par an ; les demandes annulees ne comptent pas'),
 ('seuil_absence_courte_jours',    '1',  'Jusqu a ce seuil : approbation du responsable direct'),
 ('delai_avis_jours_ouvres',       '2',  'Delai de l avis du responsable'),
 ('delai_controle_rh_jours_ouvres','2',  'Delai du controle RH'),
 ('delai_decision_jours_ouvres',   '3',  'Delai de decision du SG ou du Chef'),
 ('delai_complement_jours',        '5',  'Delai de reponse de l agent, sinon demande caduque'),
 ('validite_autorisation_jours',   '7',  'Autorisation de soumettre, en jours calendaires'),
 ('alerte_correction_solde_jours', '5',  'Alerte sur les corrections de solde superieures a ce seuil'),
 ('duree_max_delegation_jours',    NULL, 'A fixer par l administration');

-- Jours feries a date fixe (2026 et 2027). Les fetes mobiles (lundi de Paques,
-- Ascension, lundi de Pentecote) sont a ajouter par la RH.
INSERT INTO jours_feries (date_ferie, libelle) VALUES
 ('2026-01-01', 'Jour de l An'),        ('2026-03-29', 'Commemoration de 1947'),
 ('2026-05-01', 'Fete du Travail'),     ('2026-06-26', 'Fete de l Independance'),
 ('2026-08-15', 'Assomption'),          ('2026-11-01', 'Toussaint'),
 ('2026-12-25', 'Noel'),
 ('2027-01-01', 'Jour de l An'),        ('2027-03-29', 'Commemoration de 1947'),
 ('2027-05-01', 'Fete du Travail'),     ('2027-06-26', 'Fete de l Independance'),
 ('2027-08-15', 'Assomption'),          ('2027-11-01', 'Toussaint'),
 ('2027-12-25', 'Noel');

-- FIN DU FICHIER 1/2
