# Corim Vente : lignes du devis (refonte et évolutions fusionnées)

Devis de refonte existant fusionné avec les évolutions demandées par la cliente (mail du 30 octobre 2026, rendez-vous du 3 novembre 2026). Détail technique : `plan-action.md` et `ameliorations.md`.

## Lignes à coller

  - Refonte technique en Laravel : reconstruction complète de la plateforme sur Laravel 13 et PHP 8.5, versions actuelles et maintenues, en remplacement du framework Kohana (abandonné depuis 2012) et de PHP 5.6 (sans mises à jour de sécurité depuis 2018) ; mêmes fonctionnalités, mêmes écrans et mêmes adresses ; protections standard intégrées (requêtes sécurisées contre les injections SQL, protection des formulaires, sessions et cookies sécurisés, limitation des tentatives de connexion, pages d'erreur sans fuite d'informations, dépôts de fichiers contrôlés), tests automatisés de chaque parcours
  - Audit et cadrage : inventaire de toutes les fonctionnalités et de tous les rôles, cartographie de la base de données, captures de référence des parcours
  - Authentification, gestion des rôles et des droits d'accès
  - Reprise des données : programmes, lots, clients, prospects et historique, plus les fichiers (plans, documents, photos)
  - Sécurisation des mots de passe existants
  - Intégration graphique à l'identique
  - Programmes et lots : création, modification, plans, diaporama, grille de disponibilité, archivage ; mise en page des descriptions (nettoyage des textes existants, retours à la ligne conservés, liens cliquables, présentation aérée) ; import en masse depuis Excel (modèle téléchargeable, lecture des colonnes par leur nom, mise à jour de la grille de prix des lots existants par référence, aperçu avant import) ; rattachement automatique de chaque plan à son lot d'après le nom du fichier
  - Workflow commercial : dénonciations, options, réservations et ventes (demande, validation ou refus, passage en acte, annulation) ; suivi des refus (options et réservations refusées conservées avec date et message, pages dédiées, date de décision sur les dénonciations refusées)
  - Clients et prospects : fiches, recherche, export CSV
  - Promoteurs et utilisateurs : gestion des comptes, privilèges par programme et par lot
  - Communication : messagerie interne, emailing, flash infos, agenda, formulaire de contact ; messages types modifiables par l'admin pour chaque décision, pré-remplis et ajustables au cas par cas ; signature unique paramétrable ajoutée automatiquement aux mails ; retrait des modules inutilisés après validation (données conservées en archive)
  - Partie publique : recherche de programmes, fiches programmes, mentions légales, CGU
  - API d'accès aux données (passerelle vers les sites partenaires et les prescripteurs) : API sécurisée et documentée en consultation seule pour les programmes, lots, prix, disponibilités et plans, avec une clé d'accès par site et par prescripteur, un cache mis à jour toutes les heures, et la compatibilité des adresses actuelles pour que Cocoon et les autres sites continuent de fonctionner ; chaque prescripteur ne reçoit que les programmes et lots que ses droits lui ouvrent sur la plateforme, ainsi que ses propres dénonciations ; lien personnel sans connexion (page lisible, fichier Excel actualisable, JSON) pour les prescripteurs sans webmaster
  - Emails transactionnels : modèles repris à l'identique, mail au prescripteur corrigé (police, taille, retours à la ligne), envoi via un service SMTP dédié, configuration SPF, DKIM et DMARC, historique des envois
  - Performance : allègement des pages les plus lourdes (dénonciations) et mesure de la rapidité sur téléphone
  - Recette et corrections : tests de chaque parcours comparés à l'existant, correction des anomalies relevées (dont le double envoi lors d'un double clic sur « Valider »)

## Jours à ajouter aux lignes existantes

| Ligne | Ajout |
|---|---|
| Programmes et lots (description, import Excel, plans) | +2 |
| Workflow commercial (suivi des refus) | +1 |
| Communication (messages types, signature, retrait des modules) | +2,5 |
| API d'accès aux données (prescripteurs, dénonciations, lien) | +2 |
| Emails transactionnels (mail prescripteur corrigé) | +0,5 |
| Performance (nouvelle ligne) | 0,5 |
| Refonte technique en Laravel (nouvelle ligne) | à chiffrer, ou à présenter comme le titre du devis sans montant propre |
| **Total des évolutions** | **8,5** |

La ligne « Messagerie » (2 jours) est répartie entre Communication (1,5 jour) et Emails transactionnels (0,5 jour). La correction du double clic entre dans « Recette et corrections » sans supplément.
