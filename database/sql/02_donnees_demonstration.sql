-- =====================================================================
--  Fichier 2/2 : DONNEES DE DEMONSTRATION (personnages fictifs du dossier)
--  A importer APRES le fichier 01, dans la meme base.
--  Ces donnees servent a tester les relations, les vues et les contraintes.
--  Elles ne doivent pas etre utilisees en production.
-- =====================================================================

SET NAMES utf8mb4;

-- Agents (l'ordre compte : un responsable doit exister avant ses agents).
-- 1 Chef de Region -> 2 SG -> 3 Directeur DAF -> 4 Cheffe Comptabilite -> 5 Hery
-- 2 SG -> 8 Directeur DAG -> 7 Cheffe RH -> 6 Tahina (RH gestionnaire)
INSERT INTO agents (id, matricule, nom, prenoms, sexe, telephone, email, fonction_id, structure_id, responsable_id, rattachement, date_entree)
VALUES
 (1, 'CR-0001',  'RAKOTONIRINA', 'Faly',   'M', '0340000001', NULL,                      (SELECT id FROM fonctions WHERE libelle='Chef de Region'),      1,  NULL, 'region', '2015-01-05'),
 (2, 'SG-0001',  'RAZAFY',       'Rivo',   'M', '0340000002', NULL,                      (SELECT id FROM fonctions WHERE libelle='Secretaire General'),  4,  1,    'sg',     '2012-02-01'),
 (3, 'DIR-0011', 'RANDRIA',      'Jean',   'M', '0340000003', 'jean.randria@exemple.mg', (SELECT id FROM fonctions WHERE libelle='Directeur'),          11, 2,    'sg',     '2010-06-01'),
 (4, 'AGT-0104', 'ANDRIAMASY',   'Lalao',  'F', '0340000004', 'lalao.andriamasy@exemple.mg', (SELECT id FROM fonctions WHERE libelle='Chef de service'), 12, 3,    'sg',     '2013-09-15'),
 (5, 'AGT-0231', 'RAKOTOARISOA', 'Hery',   'M', '0340000005', NULL,                      (SELECT id FROM fonctions WHERE libelle='Attache administratif'), 12, 4,   'sg',     '2019-03-01'),
 (8, 'DIR-0005', 'RASOLO',       'Mamy',   'M', '0340000008', NULL,                      (SELECT id FROM fonctions WHERE libelle='Directeur'),          5,  2,    'sg',     '2011-04-01'),
 (7, 'AGT-0050', 'RAVELO',       'Soa',    'F', '0340000007', 'soa.ravelo@exemple.mg',   (SELECT id FROM fonctions WHERE libelle='Chef de service'),    6,  8,    'sg',     '2014-01-10'),
 (6, 'AGT-0188', 'RABE',         'Tahina', 'M', '0340000006', 'tahina.rabe@exemple.mg',  (SELECT id FROM fonctions WHERE libelle='Assistant'),          6,  7,    'sg',     '2018-07-02');

-- Habilitations : tout le monde est Agent ; ces lignes ajoutent un role.
-- (attribuee_par NULL = premier compte cree par script ; ensuite par Soa RAVELO, RH responsable)
INSERT INTO habilitations (agent_id, role_id, structure_id, date_debut, acte_reference, attribuee_par) VALUES
 (7, 5, NULL, '2026-01-01', 'Note de service n 12', NULL),
 (7, 1, 6,    '2026-01-01', 'Note de service n 12', NULL),
 (6, 4, NULL, '2026-01-01', 'Note de service n 12', 7),
 (4, 1, 12,   '2026-01-01', 'Note de service n 12', 7),
 (3, 1, 11,   '2026-01-01', 'Note de service n 12', 7),
 (8, 1, 5,    '2026-01-01', 'Note de service n 12', 7),
 (2, 2, 4,    '2026-01-01', 'Note de service n 12', 7),
 (1, 3, 1,    '2026-01-01', 'Note de service n 12', 7);

-- Comptes en attente d'activation (aucun mot de passe tant que l'agent n'a pas active).
INSERT INTO comptes (agent_id, statut, code_activation_hash, code_activation_expire_le)
VALUES (5, 'en_attente_activation', 'HASH_DEMO_A_REMPLACER', '2026-11-30 23:59:59'),
       (6, 'en_attente_activation', 'HASH_DEMO_A_REMPLACER', '2026-11-30 23:59:59');

-- Delegation du Directeur DAF pendant son absence (exemple).
INSERT INTO delegations (id, delegant_id, delegataire_id, date_debut, date_fin, statut, motif)
VALUES (1, 3, 4, '2026-12-21', '2026-12-31', 'planifiee', 'Conge de fin d annee');

-- Propositions de solde (controlees par une AUTRE personne que celle qui propose)
INSERT INTO soldes_propositions (id, agent_id, annee, nature, jours, motif, propose_par, statut, controle_par, controle_le, commentaire_controle) VALUES
 (1, 5, 2026, 'report',     5.00, 'Report 2025 : decision de report jointe', 6, 'valide', 7, '2026-01-03 09:00:00', 'Conforme a la decision'),
 (2, 5, 2026, 'correction', -3.00, '3 jours pris en 2025 non enregistres',   6, 'valide', 7, '2026-09-03 10:00:00', 'Piece verifiee');

-- Registre 2026 de Hery RAKOTOARISOA (exemple du dossier, partie 8.7).
-- Resultat attendu : disponible 27, reserve 0, consomme 5.
INSERT INTO soldes_mouvements (agent_id, annee, type_mouvement, delta_disponible, delta_reserve, delta_consomme, proposition_id, motif, auteur_id, created_at) VALUES
 (5, 2026, 'droit_annuel',  30, 0,  0, NULL, 'Droit annuel 2026',                       NULL, '2026-01-01 00:00:00'),
 (5, 2026, 'report',         5, 0,  0, 1,    'Report 2025',                             6,    '2026-01-03 09:00:00'),
 (5, 2026, 'reservation',   -5, 5,  0, NULL, 'Demande DM-2026-0045 (23 au 27 mars)',    NULL, '2026-03-10 08:30:00'),
 (5, 2026, 'consommation',   0, -5, 5, NULL, 'Conge pris (5 jours)',                    NULL, '2026-03-27 18:00:00'),
 (5, 2026, 'reservation',  -10, 10, 0, NULL, 'Demande DM-2026-0210 (10 jours)',         NULL, '2026-06-15 11:00:00'),
 (5, 2026, 'liberation',    10, -10, 0, NULL, 'Annulation approuvee DM-2026-0210',      NULL, '2026-06-20 14:00:00'),
 (5, 2026, 'correction',    -3, 0,  0, 2,    '3 jours pris en 2025 non enregistres',    6,    '2026-09-03 10:15:00');

-- Parcours du dossier (partie 10.1) : avis AV-2026-0087 puis demande DM-2026-0310.
INSERT INTO avis_prealables (id, reference, agent_id, type_demande_id, responsable_id, avis_donne_par, date_debut, date_fin, duree_jours,
                             motif, continuite_service, statut, sens_avis, envoye_le, avis_rendu_le,
                             autorisation_emise_le, autorisation_expire_le, autorisation_utilisee_le)
VALUES (1, 'AV-2026-0087', 5, (SELECT id FROM types_demande WHERE code='conge_annuel'), 4, 4, '2026-12-07', '2026-12-18', 10.0,
        'Repos annuel', 'Une collegue assure les ecritures', 'rendu', 'favorable',
        '2026-11-02 09:15:00', '2026-11-04 10:30:00', '2026-11-04 10:30:00', '2026-11-11 10:30:00', '2026-11-05 08:45:00');

INSERT INTO demandes (id, reference, avis_id, agent_id, type_demande_id, date_debut, date_fin, duree_jours, motif,
                      contact_pendant_absence, circuit, statut, soumise_le)
VALUES (1, 'DM-2026-0310', 1, 5, (SELECT id FROM types_demande WHERE code='conge_annuel'), '2026-12-07', '2026-12-18', 10.0,
        'Repos annuel', '0340000005', 'sg', 'en_controle_rh', '2026-11-05 09:00:00');

-- Etapes figees du circuit : avis (fait), controle RH (en attente), decision du SG (en attente).
INSERT INTO demande_etapes (demande_id, ordre, role_etape, acteur_id, statut, resultat, commentaire, echeance_le, accomplie_le) VALUES
 (1, 1, 'avis_responsable', 4, 'accomplie', 'favorable', NULL,                 '2026-11-04 17:00:00', '2026-11-04 10:30:00'),
 (1, 2, 'controle_rh',      6, 'en_attente', NULL,        NULL,                '2026-11-09 17:00:00', NULL),
 (1, 3, 'decision',         2, 'en_attente', NULL,        NULL,                '2026-11-12 17:00:00', NULL);

INSERT INTO demande_historique (demande_id, ancien_statut, nouveau_statut, auteur_id, commentaire, created_at) VALUES
 (1, NULL,        'brouillon',      5,    'Demande creee a partir de l avis AV-2026-0087', '2026-11-05 08:45:00'),
 (1, 'brouillon', 'soumise',        5,    'Soumission, circuit SG calcule',                '2026-11-05 09:00:00'),
 (1, 'soumise',   'en_controle_rh', NULL, 'Transmise a la RH pour controle',               '2026-11-05 09:00:00');

INSERT INTO compteurs_references (prefixe, annee, dernier_numero) VALUES ('AV', 2026, 87), ('DM', 2026, 310);

-- Notification exemple : l'agent est informe de l'avis favorable (interne + SMS).
INSERT INTO notifications (id, destinataire_id, type_evenement, titre, message, importance, avis_id, created_at)
VALUES (1, 5, 'avis_rendu', 'Avis favorable', 'Votre avis AV-2026-0087 est favorable. Autorisation valable jusqu au 11/11/2026.', 'importante', 1, '2026-11-04 10:30:00');
INSERT INTO envois_notifications (notification_id, canal, adresse, statut, envoye_le) VALUES
 (1, 'interne', NULL,         'envoye', '2026-11-04 10:30:00'),
 (1, 'sms',     '0340000005', 'envoye', '2026-11-04 10:31:00');

-- Premiere ligne du journal d'audit.
INSERT INTO audit_logs (auteur_id, action, objet_type, objet_id, valeur_avant, valeur_apres)
VALUES (5, 'demande.soumise', 'demandes', 1, NULL, '{"statut":"soumise","circuit":"sg"}');

-- FIN DU FICHIER 2/2
