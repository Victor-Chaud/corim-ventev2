# Corim Vente : plan d'action après le rendez-vous du 3 novembre 2026

Ce plan vient des notes du rendez-vous client du 3 novembre 2026. Il complète `ameliorations.md`, qui donne le détail des demandes reçues le 30 octobre. Tout reste soumis à l'acceptation du devis et se fait dans `NEW/` uniquement, avant la mise en production. On ne touche pas à l'ancienne application.

## Notes du rendez-vous

> Connexion au flux pour avoir l'actualisation en direct donc !!
> Api en lecture simple pas plus pas moins
> Actualisation toutes les heures
> Enlever ce qui n'est pas utilisé
> Double click valider dénonciation qui envoie 2 fois l'info à l'api.
> Suivi perf tel
> Le prescripteur doit avoir que ses dénonciations et lots qui le concernent lui.
> Grille de prix qui s'incrémente, Ajouter en masse et plans => en excel selon les colonnes. A fix pour incrémenter CSV sur le 1er index du tableau de données
> Nom des plans comme le nom du fichier sur serveur. Pour le faire remonter.

## Vue d'ensemble

| Lot | Sujet | Estimation | Statut |
|---|---|---|---|
| 1 | Double clic sur « Valider » une dénonciation | 0,25 jour | prêt à faire |
| 2 | Import en masse depuis Excel (grille de prix) | 1 jour | modèle de fichier à valider |
| 3 | Nom des plans et rattachement aux lots | 0,5 jour | prêt à faire |
| 4 | API prescripteur en lecture seule, mise à jour toutes les heures, lien personnel sans connexion | 4,5 jours | prêt à faire |
| 5 | Retirer les fonctions inutilisées | 0,5 à 1 jour | liste à valider avec la cliente |
| 6 | Suivi des performances sur téléphone | 0,5 jour | sens à confirmer |
| 7 | Demandes du 30 octobre (description, messagerie, refus) | 3 à 3,5 jours | validées telles quelles |

Ordre : 1, 3 et 2 d'abord (corrections que les utilisateurs verront tout de suite), puis 4, puis 5 et 6 une fois les réponses reçues. Ensuite la phase 3 de la refonte (vérification, revue, répétition de migration, recette) couvre l'ensemble.

Total hors lot 7 : 7,25 à 7,75 jours.

---

## Lot 1. Double clic sur « Valider » une dénonciation

**Constat.** Dans l'ancienne application, un double clic sur « Valider » envoie deux requêtes `json/AcceptAddDenonciation`. La base MyISAM n'a ni verrou ni transaction : la dénonciation est copiée deux fois dans `prospects` et le mail part deux fois. La base de prod contient 13 doublons de ce type, répartis sur 4 dénonciations.

**État dans `NEW/`.** Côté serveur, c'est déjà réglé : la validation verrouille la ligne et la supprime de `d_prospects` dans une transaction, donc le second appel ne trouve plus rien et ne crée rien. L'écran affiche quand même « Une erreur interne est survenue » au second clic, ce qui inquiète l'utilisateur.

**À faire.**
- Dans `public/medias/js/global.js`, désactiver le bouton dès le premier clic et le réactiver seulement en cas d'erreur. À appliquer à tous les boutons de validation et de refus du workflow (dénonciations, options, réservations, annulations), qui ont le même défaut.
- Test Pest : deux appels successifs à `AcceptAddDenonciation` donnent une seule ligne dans `prospects` et un seul mail.
- Migration des données : signaler les 13 doublons existants à la cliente. On ne les supprime pas sans son accord.

## Lot 2. Import en masse depuis Excel (grille de prix)

**Constat.** « Ajouter lot en masse » lit un CSV séparé par des points-virgules, où chaque colonne est attendue à une position fixe (date en 1re colonne, type en 2e, etc.). Conséquences :
- la ligne d'en-têtes d'un fichier Excel est importée comme un lot (c'est le « 1er index du tableau de données » des notes) ; aujourd'hui, il faut la supprimer à la main avant l'import ;
- une colonne déplacée ou ajoutée dans Excel décale toutes les valeurs ;
- la colonne « Plan d'architecte » (14e) est lue puis ignorée ;
- l'import ajoute toujours des lots. Pour mettre à jour une grille de prix, il faut d'abord supprimer les lots du programme, puis tout réimporter.

**À faire.**
1. **Modèle Excel** téléchargeable depuis la page, avec une ligne d'en-têtes (Référence, Type, Catégorie, Prix, Prix m², Étage, RDC, Jardin, Terrasse, Superficie, Stationnement, Exposition, Description, Plan, Cellier, Accession, Date de livraison). Excel l'enregistre en « CSV (séparateur : point-virgule) », un format déjà accepté.
2. **Lecture par nom de colonne.** La première ligne est reconnue comme une ligne d'en-têtes et n'est pas importée. Chaque colonne est trouvée par son nom, quel que soit l'ordre. Un ancien fichier sans en-têtes reste lu comme aujourd'hui, par position.
3. **Grille de prix qui se met à jour** : un lot dont la référence existe déjà dans le programme est **mis à jour** (prix, prix m², surface…) au lieu d'être créé en double ; une nouvelle référence crée un lot. Les lots absents du fichier ne sont jamais supprimés. Les lots en option, réservés ou vendus gardent leur statut.
4. **Aperçu avant import** : le tableau existant indique pour chaque ligne « ajout » ou « mise à jour » et signale les cellules vides obligatoires. Le message final donne le nombre de lots ajoutés et mis à jour.
5. Tests Pest : en-tête ignoré, colonnes dans le désordre, mise à jour par référence, ancien format sans en-têtes.

**À valider avec la cliente.** Le modèle de colonnes. La référence (numéro de lot) comme clé de mise à jour. Le comportement attendu si le prix d'un lot déjà réservé change dans le fichier (proposition : on met à jour, le statut ne bouge pas).

Pas de lecture directe des fichiers `.xlsx` : cela demanderait une bibliothèque en plus, alors que l'enregistrement en CSV depuis Excel suffit. Si la cliente y tient : +0,5 jour.

## Lot 3. Nom des plans et rattachement aux lots

**Constat.** Le plan d'un lot n'apparaît sur la plateforme et dans l'API (sites COCOON et corim-promotion) que s'il s'appelle exactement `plan-{référence}.pdf` sur le serveur. L'envoi en masse (« Ajouter les plans ») garde le nom du fichier tel qu'il est sur l'ordinateur : un plan envoyé sous le nom `A12.pdf` ou `Plan A12.pdf` est bien stocké, mais il n'est rattaché à aucun lot et ne « remonte » nulle part. Sur 993 plans en prod, 2 sont dans ce cas (`Plan-B02.pdf`, `plan.A01.pdf`).

**À faire.**
- À l'envoi en masse, retrouver le lot à partir du nom du fichier (`A12.pdf`, `plan-A12.pdf`, `Plan A12.pdf`, `plan.A12.pdf` : on ignore le préfixe « plan », la casse, les espaces et la ponctuation), puis enregistrer le fichier sous `plan-{référence}.pdf`. Si la colonne « Plan » du fichier Excel (lot 2) donne un nom de fichier, ce nom sert aussi à faire la correspondance.
- Un fichier qui ne correspond à aucun lot est refusé avec un message clair (« A99.pdf : aucun lot A99 dans ce programme ») au lieu d'être stocké sans lien.
- La page affiche déjà pour chaque lot si son plan existe : rien à changer.
- À la migration, renommer les 2 plans existants mal nommés s'ils correspondent à un lot.
- Le chemin public `medias/programmes/{clé}/plans/plan-{référence}.pdf` ne change pas (contrat de l'API).
- Tests Pest : les différentes formes de nom, le fichier sans lot, l'écrasement d'un plan existant.

## Lot 4. API prescripteur en lecture seule, mise à jour toutes les heures

**Décision du rendez-vous.** Consultation seulement, « pas plus pas moins » : pas d'écriture, pas de notifications (webhooks), pas d'export vers un portail. Les options A à D de `ameliorations.md` sont abandonnées. Chaque prescripteur ne voit que ses lots et ses dénonciations. Le prescripteur (ou son prestataire) branche son site ou son logiciel sur le flux, qui se met à jour automatiquement : les données sont rafraîchies au plus toutes les heures.

**À faire.** Cela correspond à la base décrite dans `ameliorations.md` (3 jours) plus les dossiers du prescripteur (+1 jour), réduits aux dénonciations.
1. **Clé d'accès par prescripteur.** L'admin la génère et la révoque depuis la fiche du prescripteur. La clé est montrée une seule fois, puis stockée sous forme de hachage. Nouvelle table `api_keys` (identifiant prescripteur, hachage, dates de création, de dernier usage et de révocation). Deux façons de la transmettre, toutes deux acceptées (décision du 3 novembre 2026) : l'en-tête `Authorization: Bearer <clé>` pour un webmaster, ou le paramètre `?cle=<clé>` dans l'adresse pour les outils qui ne savent que coller un lien.
1 bis. **Lien personnel pour un prescripteur sans webmaster** (+0,5 jour). Le prescripteur consulte ses données sans se connecter à la plateforme, grâce à un lien personnel qui contient sa clé, à mettre en favori. La même adresse existe en trois formats :
   - `…/api/v1/lots?cle=…` (par défaut) : une page lisible dans le navigateur, tableau des lots puis de ses dénonciations, aux couleurs de la plateforme, sans menu ni action. Page en `noindex`, non mise en cache par le navigateur ;
   - `…&format=csv` : à ouvrir dans Excel, ou à brancher une fois dans Excel (Données, À partir du web) pour une actualisation automatique ;
   - `…&format=json` : pour un webmaster ou un logiciel.
   Le contenu suit les droits déjà attribués au prescripteur dans « Gestion des privilèges » (tables `acces` et `acces_lot`, lues par `App\Support\Access`, comme à l'écran) : rien de nouveau à paramétrer. Une modification de ses droits vide son cache et s'applique tout de suite. Un compte prescripteur supprimé rend son lien inutilisable. Aucune clé pour un compte admin.
   La fiche du prescripteur affiche ces liens prêts à copier au moment où la clé est générée. Ce lien sert de mot de passe : en cas de doute, l'admin génère une nouvelle clé, ce qui coupe l'ancien lien.
2. **`GET /api/v1/lots`** : uniquement les lots de programmes non archivés auxquels le prescripteur a accès (`acces` et `acces_lot`, les mêmes droits qu'à l'écran). Champs : programme (titre, ville, livraison, description, vignette), référence, type, étage, surface, prix (nombre), options, statut, URL du plan. Filtres : programme, type, ville, budget.
3. **`GET /api/v1/denonciations`** : les dénonciations du prescripteur (en attente, validées, refusées) avec dates et programme. Rien sur les autres prescripteurs.
4. **Mise à jour toutes les heures** : réponses mises en cache 1 heure par prescripteur, avec l'en-tête `Cache-Control: max-age=3600`. La documentation conseille d'interroger le flux une fois par heure.
5. **Protection** : HTTPS uniquement, limite de débit par clé (par exemple 60 appels par heure), 401 sans clé ou avec une clé révoquée, aucune donnée personnelle d'acquéreur au-delà de ce que le prescripteur a lui-même saisi.
6. **Documentation** : une page courte (exemples d'appels, liste des champs, codes d'erreur) à transmettre au prestataire.
7. **Tests Pest** : un prescripteur ne voit jamais les lots ni les dénonciations d'un autre, une clé révoquée est refusée, la limite de débit s'applique, le cache se vide au bout d'une heure.

L'API publique actuelle (`firstsearch`, `search`) ne change pas : les sites COCOON et corim-promotion continuent de l'utiliser telle quelle.

**À confirmer.** « Actualisation en direct » et « toutes les heures » : on comprend un flux automatique, rafraîchi au plus toutes les heures. Si un changement de statut doit apparaître immédiatement, il suffit de vider le cache du prescripteur à chaque changement de statut d'un lot (+0,25 jour) ; ce n'est pas prévu par défaut.

## Lot 5. Retirer les fonctions inutilisées

Liste établie le 29 octobre (`suivi.md`, section « Fonctionnalités inactives ») sur la base de prod. À faire valider ligne par ligne par la cliente avant toute suppression :

| Fonction | Dernière utilisation | Proposition |
|---|---|---|
| Fiches clients (ajout, import et export CSV) | 2012 | retirer (pages, menus, routes) ; garder la table en archive |
| Emailing aux prescripteurs | 2015 (2 envois) | retirer ; supprime aussi la file d'envoi et le besoin d'un worker en prod |
| Flash info | 2021 (3 actualités) | retirer, sauf si l'API `search?action=allnews` sert encore à un site (à vérifier dans les journaux d'accès) |
| Agenda | jamais | retirer |
| Question secrète (mot de passe oublié) | jamais | retirer le champ (le lien de réinitialisation par mail la remplace) |
| Table des villes | jamais | ne pas migrer |
| Messagerie entre personnes | 2021 | garder : elle porte les notifications automatiques des demandes ; retirer seulement le bouton « nouveau message » si la cliente le souhaite |
| Demandes de documentation par l'API (`firstsearch?action=email`) | 2022 | garder : l'API publique est figée par contrat ; à revoir après la mise en production, d'après les journaux d'accès |

**Statut (12 novembre 2026) : fait pour les fiches clients, l'agenda, l'emailing et le destinataire « Client » de `/message/envoyer`** (plus aucune file d'attente, donc pas de worker en prod ; réimplantable depuis le premier commit du dépôt `corim-ventev2`, `5339fd6`) (pages, routes, menus, JS ; test `tests/Feature/Evolutions/RemovedFeaturesTest.php`). Non retirés : flash info, `/emails` (demandes de documentation) et question secrète. La file d'envoi reste : `/message/envoyer` l'utilise pour les mails aux clients.

Les données ne sont pas effacées : les tables restent dans la base (ou dans un export archivé) en cas de besoin. Les tests Pest des fonctions retirées sont remplacés par un test qui vérifie que leurs anciennes adresses renvoient 404.

## Lot 6. Suivi des performances sur téléphone

**Sens à confirmer** : notre lecture de « suivi perf tel » est que la plateforme doit rester rapide sur téléphone, et qu'on mesure cette rapidité. Si la note parle d'autre chose (par exemple un suivi des appels ou des résultats par commercial), ce lot est à rechiffrer.

**À faire si c'est bien cela.**
- La page admin « Dénonciations » est la plus lente : 6 668 lignes, 4,8 Mo de HTML et 5 835 calendriers créés au chargement. On ne crée plus le calendrier qu'au clic dans le champ, et les pages sont compressées (gzip) sur le serveur.
- Mesurer les pages principales sur un téléphone (Lighthouse, réseau 4G simulé), avant et après, et consigner les résultats dans `suivi.md`. Cette mesure sera refaite à la recette.
- La pagination ou un filtre par période sur « Dénonciations » serait le vrai gain, mais c'est un changement visible : à décider avec la cliente.

## Lot 7. Demandes du 30 octobre non abordées dans les notes

Description des programmes (0,5 jour), messages automatiques et signature des mails (1,5 à 2 jours), suivi des refus (1 jour) : voir `ameliorations.md`. Confirmé le 3 novembre 2026 : validées telles que décrites dans `ameliorations.md`, sans ajout.

---

## Méthode

Même méthode que la refonte. Pour chaque lot :
1. le vérificateur écrit les tests Pest d'acceptation à partir de ce document ;
2. le constructeur code jusqu'à ce que ces tests passent ;
3. `composer verify` (Pint, PHPStan, Pest, `composer audit`) doit être vert avant de considérer le lot terminé ;
4. mise à jour de `suivi.md`.

## Questions à envoyer à la cliente

1. **Import Excel** : valider le modèle de colonnes ; la référence du lot sert-elle bien de clé pour mettre à jour les prix ? Si le prix d'un lot réservé change dans le fichier, faut-il le mettre à jour ?
2. **API** : un flux rafraîchi au plus toutes les heures convient-il, ou un changement de statut doit-il apparaître immédiatement ? Quels prescripteurs seront branchés en premier ?
3. **Fonctions inutilisées** : valider le tableau du lot 5, ligne par ligne.
4. **« Suivi perf tel »** : s'agit-il de la rapidité du site sur téléphone, ou d'autre chose ?
5. **Doublons** : 13 dénonciations en double dans la base actuelle, faut-il les supprimer à la migration ?

---

## Implémentation locale (lancée le 3 novembre 2026)

Décisions de l'utilisateur pour l'implémentation dans `NEW/` :

- **Lot 5 (retrait des fonctions inutilisées) : reporté**, puis fait le 12 novembre 2026 pour les fiches clients, l'agenda et l'emailing (voir lot 5).
- **Signature des mails** : valeur par défaut = la signature actuelle écrite en dur dans `resources/views/workflow/mail/prescriber.blade.php` (adresse « Le Cristal », téléphones), modifiable ensuite par l'admin.
- **Suivi des refus** : message libre seulement, pas de liste de motifs. Le prescripteur voit ses propres refus, l'admin voit tout.
- **Lot 6** : traité comme la rapidité du site sur téléphone.
- **Lot 7** : description des programmes, messages automatiques et signature, suivi des refus, tels que décrits dans `ameliorations.md` (sections 2, 3 et 4) avec les décisions ci-dessus.

Rôles : un agent constructeur code, un agent testeur écrit les tests d'acceptation dans `tests/Feature/Evolutions/`, un agent de non-régression vérifie que le reste du site n'a pas bougé (copie de référence prise avant les travaux). Les rôles ne se mélangent pas.
