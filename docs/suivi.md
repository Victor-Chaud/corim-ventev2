# Suivi de la refonte : tests d'acceptation

- Date : 2026-10-29
- Commande : `cd NEW && flock storage/framework/pest.lock php8.5 vendor/bin/pest`
- Base : `corim_new_test` (RefreshDatabase), données prod lues en lecture seule depuis la connexion `legacy` (`loadLegacy()` dans `tests/Pest.php`)
- Total : **272 tests**, **272 ✅**, 0 ❌, 8 264 assertions, 17 s

Légende : ✅ passe · ❌ échoue (cause entre parenthèses) · ⏳ non testable en Pest

Helpers (`tests/Pest.php`, `tests/Helpers/*.php`) : `loadLegacy()`, `legacyFixture()`, `assertSameModuloTies()`, `jsonpUnwrap()`, `makeAdmin()`, `makeCommercial()`, `corimAdmin()` (id 1), `makeProjet()`, `makeLot()`, `makeClient()`, `makeMessage()`, `makeNews()`, `workflowWorld()`, `dashboardCounters()`, `sentMails()` / `mailRecipients()` (transport `array`), `expectLegacyMail()`, `expectBulkMail()`, `emailingBody()`, `contactMailBody()`, `pageText()`, `decoded()`, `tablesState()`, `withoutTestingCsrfBypass()`. Données personnelles fictives (`@exemple.test`). Aucun e-mail réel, aucun appel externe : mailer `array`, `Queue::fake()` pour la file.

Les tests négatifs (refus, absence d'effet) vérifient d'abord que la route répond (`expectRouteExists`), pour ne pas passer à vide.

## Synthèse par module

| Vague | Module | Critères du contrat | Tests | Statut | Fichiers |
|---|---|---|---|---|---|
| A | public-api | 38/38 | 38 | ✅ | `tests/Feature/PublicApi/` |
| A | auth-users-privileges | 29/29 | 29 | ✅ | `tests/Feature/Auth/` |
| A | programmes-lots | 32/32 + C16bis | 33 | ✅ | `tests/Feature/ProgramsLots/` |
| B | workflow | 34/34 (C29 en 2 tests) | 35 | ✅ | `tests/Feature/Workflow/` |
| B | clients-prospects | 22/22 + C21bis | 23 | ✅ | `tests/Feature/ClientsProspects/` |
| B | promoteurs | 32/32 | 32 | ✅ | `tests/Feature/Prescribers/` |
| C | communication | 30/30 | 30 | ✅ | `tests/Feature/Communication/` |
| C | public | 30/31 (C31 ⏳) | 30 | ✅ | `tests/Feature/Public/` |

Les 7 échecs précédents (Auth C11, C12, C13, C24, C26, C29, Workflow C30) attendaient les pages de la vague C (`/`, `/contact`, `/emails`, …) : ils passent maintenant.

## Ajouts de cette passe

- **clients-prospects C21bis** (`ClientsProspects/EncodingTest.php`) : `ajouter_client`, `edition_client` et `AddProspect` stockent `htmlspecialchars(ENT_QUOTES)` une seule fois (jamais `&amp;#039;`), un POST de texte déjà encodé ne double pas, et les pages affichent le texte décodé.
- **programmes-lots C16bis** (`ProgramsLots/AdminPagesTest.php`, déjà présent et vérifié) : `ajouter_lot` et l'import CSV encodent `'` et `&` comme `AddAppartement` (`model.php:2228`), `ville` = `projet_lieu` encodé une fois, `gerer_lots/modifier` enregistre le texte tel que saisi (`UpdateAppartement`, `model.php:2258`), formulaire sans `&amp;amp;`.
- **communication** : `AccessTest` (C1, C2, C29, C30), `MessagingTest` (C3-C11), `SendTest` (C12-C13), `EmailsFlashInfoTest` (C20-C25), `ContactAgendaTest` (C26, C28). C14-C19 (emailing) et C27 (agenda) supprimés le 2026-11-12 (lot 5).
- **public** : `DashboardTest` (C1-C9, C30), `LayoutTest` (C10-C15, C19), `PagesTest` (C16-C18, C20-C29).
- `dashboardCounters()` déplacé de `Workflow/PagesTest.php` vers `tests/Helpers/public.php` (partagé avec Public).

## Choix des tests sur les questions ouvertes (règle « même projet »)

- auth C20-C23 : reset par lien signé, `NewAccountSetting` sans effet ; C23 suppose la question secrète retirée.
- auth C12 : `/optionner` et `/reserver` retirés de la liste des pages connectées (code mort jamais porté, contrat workflow §3.12 : 404).
- json/* internes (hors public-api) : `text/html` via `LegacyJson`, `callback` ignoré, car `global.js` fait `eval()` de la réponse (décision du lead). Communication C17 attend donc `text/html`, et Promoteurs accepte `text/html`.
- communication C7 : message d'un tiers → 403 ou 404 pour un non-admin ; l'admin n'est pas restreint (comportement legacy).
- communication C11 / C24 : suppression en POST + CSRF ; le GET ne supprime rien (réponse libre : 405 ou redirection).
- communication C13 / C15 : nom d'expéditeur comparé sans la casse (`Corim Promotion` dans le legacy `mail()`, `Corim promotion` dans le socle), adresse et Reply-To `contact@corim-promotion.com` exacts, UTF-8. Le contact (C26) garde l'expéditeur du socle et le pied « Fax : 04 67 60 40 77 » de `contact.php`.
- communication C15 : prescripteurs « tous » = tous les `acces=0`, inactifs compris ; programme = tout utilisateur ayant un `acces` (admin compris), comme `GetEmailByAccess`.
- communication C16 : `Queue::fake()` ; un job `ShouldQueue` par destinataire, avec réessais (`tries > 1`, `backoff` ou `retryUntil`) et un middleware `RateLimited`/`ThrottlesExceptions` ; l'historique `emailing` existe avant tout envoi. Limiteur `emailing` = `config('mail.emailing_per_minute')` (30 par défaut).
- communication C18 : chaque colonne cherchée est testée avec un seul mot-clé ; la combinaison de plusieurs mots-clés (Q6, `AND` sans parenthèses) n'est pas figée.
- communication C27 : `/agenda/*` → `/` (connecté) ou `/connexion` (invité), sans écriture ; `json/DeleteEvent` → 404 (connecté), 302/401/404 (invité).
- communication C28 : le test accepte les deux décisions, soit 404, soit un formulaire qui envoie `demande infos : site CORIM` avec Reply-To validé (en-tête injecté refusé).
- public C5 : les programmes archivés restent sur l'accueil du prescripteur (Q4, reproduit à l'identique).
- public C17 : texte de `V_Mentions.php` lu comme texte (variables substituées), sans exécuter l'ancien code.
- public C23 / C25 : rendu de `404.html` et `maintenance.html` (vues `errors::404` / `errors::503`, testées via `abort(503)` pour ne pas mettre l'application de dev en maintenance).
- public C27 / C28 : méthodes internes Kohana et `/rechercher*` → 404 pour un connecté (un invité peut être redirigé vers `/connexion`).

## Fonctionnalités inactives ou en panne dans l'ancienne version

Relevé du 2026-10-29 sur la copie locale de la base de prod (dump du 2026-10-27) et dans les contrats. La refonte les porte toutes à l'identique, sauf mention contraire. À discuter avec la cliente : garder, relancer ou supprimer.

### Plus utilisées depuis longtemps (elles marchent, personne ne s'en sert)

| Fonctionnalité | Dernière utilisation | Volume total | Table |
|---|---|---|---|
| Fiches clients (ajout, import et export CSV) | 2012-10-15 | 212 fiches, toutes de 2012 | `clients` |
| Emailing aux prescripteurs | 2015-12-03 | 2 envois (2013 et 2015) | `emailing` |
| Messagerie entre personnes | 2021-12-07 (2020-05-20 pour un message d'un prescripteur) | 281 messages humains ; depuis, seulement des notifications automatiques | `mp` |
| Flash info | 2021-11-08 | 3 actualités | `news` |
| Demandes de documentation via l'API (`firstsearch?action=email`) | 2022-05-08 | 2 135 demandes | `docs` |
| Agenda | jamais | 0 événement | `agenda` |
| Question secrète (mot de passe oublié) | jamais | 0 compte avec une question ; interface commentée dans la vue de connexion | `user.question` |
| Table des villes | jamais | 0 ligne (seul `/administration/Ajouter_Appartement` y écrivait) | `villes` |

### En panne en production (erreur ou sans effet)

| Fonctionnalité | Ce qui se passe en prod | Décision refonte |
|---|---|---|
| API `firstsearch` `light`, `withref` et appel sans paramètre | HTTP 500 (MySQL refuse `ORDER BY` + `DISTINCT`, `$action` indéfini) | réparée : renvoie le résultat prévu |
| API `firstsearch?action=app` | `var_dump` puis arrêt | réparée |
| API `search/news` sans `action=allnews` | HTTP 500 (`$callback` indéfini) | réparée |
| `/rechercher`, `/rechercher/par_residence`, `/rechercher/par_lot` | HTTP 500 (vue inexistante) | 404 pour un utilisateur connecté (contrat public §10.28) |
| `/optionner`, `/reserver` (anciennes pages de demande) | erreur Kohana avant toute écriture ; plus aucun lien | non portées (404) |
| `json/Acter` | toujours 500 ; bouton absent de l'interface | non porté |
| `/formulaire` | vues inexistantes | non porté (404) |
| Upload du diaporama d'un programme | code mort, aucun formulaire ne poste vers lui ; dossiers `diapo/` vides | non porté |
| `/administration`, `/administration/Ajouter_Appartement` | accessibles sans connexion, écrivaient dans `appartements` et `villes` | supprimées (404, faille F3) |
| `json/GenerateNewAccountSetting` | ne fait rien, aucun appelant | réponse vide conservée |
| Refus d'une dénonciation (`RejetAddDenonciation`) | échoue au premier appel quand l'id n'existe pas encore dans `r_prospects` | réparé |
| `PasserEnActer`, `UpdateDate` avec un type inconnu | HTTP 500 | renvoie 0 |
| Gestion clients : message « Aucun fichier CSV… », bouton « Supprimer le fichier CSV du serveur » | message jamais affiché, bouton sans effet | réparés |
| Création d'un flash info avec un champ vide | échec de l'insertion | réparée |
| Barre d'outils (Verrouiller / Déverrouiller, aperçu avant impression) | jamais affichée ; une fois masquée, impossible à réactiver | conservée telle quelle |
| Paramètre `callback` du JSONP | ignoré (variable non définie) : noms de callback fixes | conservé (les partenaires en dépendent) |

## Non testable en Pest

- public C31 : comparaison visuelle (captures 1280 px et 375 px) avec l'ancienne application : à faire au recettage.
- public C29 / public-api C35 : redirection http→https (vhost Apache). Pest vérifie seulement que `/application`, `/system`, `/modules`, `*.csv`, `.env` et les logs ne sont pas servis.
- Absence d'exécution PHP par Apache dans le dossier d'upload : configuration du vhost, à contrôler au déploiement.
- communication C16 : sans objet depuis le 2026-11-12 (plus d'envoi en file : emailing et destinataire « Client » de `/message/envoyer` retirés).

## Contestations

- **programmes-lots C25** : ajusté par le lead (`ajouter_plan/show`, messages OK / manquant / sans lot).
- **auth PasswordReset C20 / C22** : ajustés par le lead. Le nouveau mot de passe est généré côté serveur et envoyé par e-mail, et le `pwdClient` envoyé par l'appelant est ignoré (correctif F4).
- **promoteurs** : le builder a signalé 5 tests à corriger. Corrections faites par l'agent de suivi et acceptées.
- **workflow** : `AddOption` / `AddReservation` rendent `name` et `cp` obligatoires. À vérifier en revue au regard de la règle « rien ajouté ».
- **public DashboardTest C2** : l'échec venait du test (la vérification invité tournait encore avec la session connectée). Test corrigé, `/welcome` et `/` sont identiques.

## Évolutions (novembre 2026)

Tests d'acceptation dans `tests/Feature/Evolutions/`, écrits d'après `plan-action.md` et `ameliorations.md` (sections 2 à 4). Dernier passage (3 novembre 2026) : **84/84**.

Lot 5 : fiches clients, agenda et emailing retirés le 2026-11-12, données gardées en base (tables `clients`, `agenda`, `emailing`) ; leurs tests sont remplacés par `RemovedFeaturesTest.php` (404 sur les anciennes URL). `composer verify` : Pest 406/406.

| Lot | Fichier | Tests | Résultat |
|---|---|---|---|
| 1. Double clic sur Valider | `Lot1DoubleClickTest.php` | 7 | 7/7 |
| 2. Import Excel (grille de prix) | `Lot2ImportExcelTest.php` | 8 | 8/8 |
| 3. Nom des plans | `Lot3PlansTest.php` | 13 | 13/13 |
| 4. API prescripteur | `Lot4PrescriberApiTest.php` | 33 | 33/33 |
| 6. Rapidité sur téléphone (`/denonciations`) | `Lot6ReferralsTest.php` | 2 | 2/2 |
| 7a. Description des programmes | `Lot7aDescriptionTest.php` | 5 | 5/5 |
| 7b. Messages automatiques et signature | `Lot7bMessagesTest.php` | 9 | 9/9 |
| 7c. Suivi des refus | `Lot7cRejectionsTest.php` | 7 | 7/7 |

Non testable en Pest, à vérifier à la main ou à la recette :
- lot 1 : bouton désactivé dès le premier clic et réactivé en cas d'erreur (JS de `global.js`), sur tous les boutons de validation, de refus et d'annulation ;
- lot 6 : vitesse mesurée sur téléphone (Lighthouse, 4G simulée), calendrier créé seulement au clic, compression gzip (vhost) ;
- rendu visuel : aspect de la fiche programme aérée, de la page HTML de l'API, de l'écran « Messages automatiques » et du mail au prescripteur dans plusieurs messageries ;
- lot 4 : HTTPS uniquement (vhost).

Tests existants ajustés pour les changements voulus (identifiants et noms conservés) :
- **workflow EmailsTest C31 E4 à E14** : le corps attendu (`mailPrescripteur` dans `tests/Helpers/legacy.php`) porte la police en ligne (Arial 14 px), le message de l'admin échappé avec retours à la ligne (`nl2br`) et la signature lue dans les paramètres (par défaut l'adresse « Le Cristal », en texte échappé avec `<br>`). Sujet, expéditeur, destinataire, phrase, programme, référence ou client et partie texte restent vérifiés comme avant (lot 7b).
- **workflow ValidationsTest C16** : la ligne « Client : M PROSPECT Paul » est attendue dans une cellule avec la police en ligne (lot 7b).
- **programmes-lots AdminPagesTest C10** : le lot du programme a la référence `T1`, pour que l'envoi de `plan-T1.pdf` corresponde à un lot (lot 3 : un plan sans lot est refusé).
- **programmes-lots AdminPagesTest C26** : `plan-A2.pdf`, sans lot A2, est désormais refusé avec « aucun lot » et n'est pas stocké ; `plan-A1.pdf` reste enregistré, le `.txt` reste refusé (lot 3).

- **`loadLegacy()` dans `tests/Pest.php`** : ne copie plus que les colonnes présentes à la fois dans la nouvelle base et dans `legacy` (même intersection que `legacy:import`), puisque `r_prospects` a maintenant `date_refus` (lot 7c).
- **clients-prospects MigrationTest C22** : la comparaison avec le snapshot porte sur les colonnes legacy seulement, `date_refus` étant une colonne nouvelle (lot 7c).

Correctifs après vérification de non-régression (lot 4), tests ajoutés : génération de clé en Post/Redirect/Get (la fiche montre la clé et les trois liens une seule fois, un nouvel affichage ne la montre plus et ne régénère rien ; compte admin → message d'erreur sur la fiche), neutralisation des formules dans le CSV (`=`, `+`, `-`, `@`, `|`, tabulation, retour chariot, y compris après des espaces ou une espace insécable ; nombres comme `-1`, `-12.5`, `185000` inchangés), limite par adresse (`throttle:600,1`) avant la vérification de la clé. Le helper de génération de clé suit désormais la redirection.

Suite complète : 356/356.
