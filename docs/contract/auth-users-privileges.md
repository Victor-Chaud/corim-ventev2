# Contrat — auth-users-privileges

Statut : brouillon (à valider par Victor)
Sources lues (chemins relatifs à `OLD/`) :
- `application/controllers/connexion.php` (1-49), `__connexion.php` (1-47), `logout.php` (1-14), `__main.php` (1-116), `__json.php` (1-47), `__form.php` (1-15), `administration.php` (1-111), `gestion_privilege.php` (1-27), `gestion_promoteurs.php` (1-127, frontière), `welcome.php` (1-16), `programme.php` (1-44), `refused_ask.php` (6-16), en-têtes de contrôle de tous les contrôleurs de `application/controllers/`
- `application/controllers/json.php` : 1-12, 269-291, 1075-1168, 1379-1517, 1519-1605
- `application/models/model.php` : 1-25, 78-83, 405-416, 1264-1367, 1492-1760, 2115-2169
- `application/config/config.php` (8, 22, 71, 125-147), `application/config/session.php` (1-49), `application/i18n/fr_FR/form_errors.php` (1-13)
- `system/libraries/Input.php` (52, 99-123, 279-391, 400-449), `system/core/utf8.php` (80-83), `system/helpers/valid.php` (290-299), `system/config/cookie.php` (9-32), `system/libraries/Session.php` (63-76), `system/libraries/drivers/Session/Database.php` (96-105)
- `application/views/V_connexion.php` (1-164), `template/connexion.php` (1-30), `template/template.php` (1-137), `template/template_welcome.php` (26-68), `template/toolbar.php` (1-45), `template/header.php` (1-6), `template/json.php`, `V_Json.php`, `V_Gestion_privilege.php` (1-187), `V_Gestion_promoteurs.php` (82-86, 146)
- `medias/js/global.js` : 1-2, 1212-1331, 1612-1705, 1764-1788 ; `medias/css/connexion.css`
- `.htaccess` (1-26)
- Base locale `sql_corim_vente_` (snapshot prod du 2026-10-27) : `SHOW CREATE TABLE` et comptages sur `user`, `privileges`, `acces`, `acces_lot`, `acces_lot_backup`, `sessions`.

## 1. Périmètre

Le module couvre tout ce qui identifie l'utilisateur et décide de ce qu'il peut voir :

- **Connexion** par identifiant (`pseudo`) + mot de passe sur `/connexion`, ouverture d'une session Kohana stockée en base (table `sessions`).
- **Déconnexion** (`/logout` dans le menu, `/connexion/deconnection` dans la toolbar).
- **Mot de passe oublié** : modale sur la page de connexion. L'utilisateur saisit son e-mail ; s'il est reconnu, un nouveau mot de passe généré côté navigateur est enregistré puis envoyé par e-mail.
- **Question secrète** : actions `CheckQuestion` / `CheckAnswer` encore exposées, mais l'interface est commentée et aucune donnée n'existe en base (voir §5). Je n'ai trouvé aucun mécanisme de « première connexion » dans le code (aucun drapeau, aucune redirection forcée) : voir §11.
- **Rôles** : deux valeurs dans `privileges.acces` (`1` = Administrateur, `0` = Commercial/Prescripteur). Le compte est actif ou inactif via `user.statut`.
- **Privilèges** : un administrateur attribue à chaque utilisateur des programmes (`acces`) et des lots (`acces_lot`) depuis `/gestion_privilege` (et la même modale dans `/gestion_promoteurs`). Chaque enregistrement envoie un e-mail à l'utilisateur.
- **Toolbar** : barre d'outils optionnelle, activée ou masquée en session (`ShowToolbar` / `HideToolbar`). Son rendu est commenté dans les gabarits (voir §8).
- **Contrôles d'accès communs** (`isConnected`, `isAdmin`, filtrage par `SessionAcces`/`SessionId`), auxquels tous les autres modules se réfèrent (§2.2).

La création, la modification, la désactivation et la suppression des comptes (`/gestion_promoteurs`, `json/AddPromoteur`, `DeletePromoteur`, `TrashPromoteur`, `ActivPromoteur`) appartiennent au module `promoteurs`. Ce contrat n'en fixe que les règles partagées : hash du mot de passe, `pwdshow`, `privileges`. Voir §11 Q1.

## 2. Rôles et droits

### 2.1 Valeurs réelles en base (snapshot local du 2026-10-27)

| Donnée | Valeur | Nombre | Signification (code) |
|---|---|---|---|
| `privileges.acces` | `1` | 1 | « Administrateur » (`V_Gestion_privilege.php:44`, `V_Gestion_promoteurs.php:82`) |
| `privileges.acces` | `0` | 264 | « Commercial » dans les tableaux (`V_Gestion_privilege.php:45`), « Prescripteurs » dans le menu (`template/template.php:100`), filtre `privileges.acces = 0` de `getPrescripteurs` (`model.php:1572`) |
| `user.statut` | `1` | 99 | actif : seul autorisé à se connecter (`model.php:1634`) |
| `user.statut` | `0` | 143 | inactif (`DeletePromoteur`, `model.php:1286`) |
| couple statut/acces | 1/1 : 1 · 1/0 : 98 · 0/0 : 143 | | aucun admin inactif |

Aucune autre valeur n'existe. 242 lignes `user`, 265 lignes `privileges` : 23 lignes orphelines (utilisateurs supprimés). Tous les utilisateurs ont une ligne `privileges` (`UNIQUE iduser`).

| Rôle (valeur en base) | Peut faire | Contrôle dans l'ancien code (fichier:ligne) |
|---|---|---|
| Anonyme | `/connexion`, mot de passe oublié. En pratique, **toutes les actions `json/*`** et `/administration/*`, faute de contrôle (§9) | `connexion.php:9` (`isDeconnected`) ; aucun contrôle dans `json.php` ni `administration.php` |
| Commercial `acces = 0`, `statut = 1` | Pages « connecté » ; données filtrées sur son `SessionId` (ses options, réservations, dénonciations, prospects) ; programmes listés via `acces` ; lots visibles dans la grille via `acces_lot` | `__main.php:24-25`, `model.php:933,954,1104,1121,1139,1158,1172,1193,1204,1214,1224,1241,1251,1395,2125-2135,2146-2159` |
| Administrateur `acces = 1`, `statut = 1` | Tout, notamment les menus Programmes, Clients, Prospects, Prescripteurs, Privilèges, Emailing, Emails, Flash info | `__main.php:55-61` (`isAdmin`), `template/template.php:92-104` |
| Utilisateur `statut = 0` | Ne peut pas se connecter. Une session déjà ouverte n'est jamais revérifiée | `model.php:1634` |

### 2.2 Comment chaque contrôleur vérifie l'accès (référence pour les autres modules)

Mécanique commune (`__main.php`) :
- `isConnected()` (`__main.php:46-53`) : si `SessionId` est vide, `Location: /connexion` **puis `exit()`**. C'est le seul contrôle qui arrête vraiment l'exécution.
- `isAdmin()` (`__main.php:55-61`) : `SessionAcces === '1'`, comparaison **stricte sur la chaîne** `'1'`.
- Motif « admin » utilisé partout : `if(!$this->isAdmin()){ header("Location: ".SITE_URL); }` **sans `exit()`**. Le contrôleur continue, exécute l'action (lectures **et écritures**) et rend la page dans le corps de la réponse 302 (§9, faille F2).
- La page d'accueil `/` (ou `/welcome`) est protégée dès le constructeur (`__main.php:19-36`, `isConnected()` l.28) puis par `welcome.php:10`.
- `__connexion.php` et `__json.php` ont leurs propres `isConnected()` **sans `exit()`** (`__connexion.php:17-24`, `__json.php:17-24`). `__json.php` teste en plus une clé `VBSessionId` que rien n'écrit (6 occurrences, toutes dans `__json.php`) : un appel à cet `isConnected()` redirigerait toujours. Aucun contrôleur `json`, `app`, `firstsearch` ou `search` ne l'appelle.

| Contrôleur (base) | Contrôle effectif | fichier:ligne |
|---|---|---|
| `welcome` (Main) | connecté | `__main.php:28`, `welcome.php:10` |
| `ajouter_lot`, `ajouter_lots`, `ajouter_plan` (Main) | `index` : connecté puis redirection `/` ; action : connecté + admin **sans exit** | `ajouter_lot.php:8-9,13-16`, `ajouter_lots.php:10-11,16-18`, `ajouter_plan.php:10-11,16-18` |
| `ajouter_programme`, `gerer_lots`, `gestion_programmes`, `modifier_appartement`, `modifier_programme`, `emails`, `flash_info`, `gestion_prospects`, `report`, `gestion_privilege` | connecté + admin **sans exit** | `ajouter_programme.php:8-10,77-79`, `gerer_lots.php:10-12`, `gestion_programmes.php:10-12`, `modifier_appartement.php:10-12`, `modifier_programme.php:10-12`, `emails.php:10-12`, `flash_info.php:10-12`, `gestion_prospects.php:9-11`, `report.php:10-12`, `gestion_privilege.php:10-13` |
| `emailing` | connecté + admin sans exit, puis `SessionAcces!=='1'` sans exit | `emailing.php:10-12,19-20` |
| `gestion_clients` | connecté + (admin et `$id` non vide) sans exit | `gestion_clients.php:8-10,62-64,78-80,120-122,168-170,236-238` |
| `gestion_promoteurs` | connecté + admin sans exit (`modifier` : `exit` seulement si `$id` vide) | `gestion_promoteurs.php:9-11,26-28,54-56,71-79` |
| `message` | connecté ; `envoyer` (l.45) : `SessionAcces!=='1'` sans exit | `message.php:8-9,13,45-48` |
| `refused_ask` | connecté ; branche admin / non-admin (pas de redirection) | `refused_ask.php:7,10-14` |
| `programme` | connecté uniquement ; prescripteurs chargés si admin. **Aucune vérification que le programme figure dans `acces`** | `programme.php:10,17,40-43` |
| `ask_denonciations`, `ask_options`, `ask_reservations`, `denonciations`, `options`, `reservations`, `ventes`, `messagerie` | connecté ; données filtrées par le modèle selon `SessionAcces` | `ask_*.php:8,13`, `denonciations.php:9,13`, `options.php:8,12,20,24`, `reservations.php:8,12,20,24`, `ventes.php:8,13`, `messagerie.php:8,14` |
| `optionner`, `reserver`, `rechercher`, `agenda`, `contact`, `cgu`, `mentions_legales` | connecté uniquement | `optionner.php:8,15,22`, `reserver.php:8,15,22`, `rechercher.php:10,24,32`, `agenda.php:9,16,49,59`, `contact.php:10`, `cgu.php:9`, `mentions_legales.php:9` |
| `connexion`, `logout` (Connexion) | `isDeconnected` (redirige `/` si connecté, **sans exit**) | `connexion.php:9`, `logout.php:8` |
| `json`, `app`, `firstsearch`, `search` (Json) | **aucun** | `json.php` (aucun appel), `app.php`, `firstsearch.php`, `search.php` |
| `formulaire` (Form) | aucun (public) | `__form.php`, `formulaire.php:3,8` |
| `administration` (Main) | **aucun** | `administration.php:7-110` |

Règle de filtrage dans le modèle : le modèle reçoit `SessionAcces` et le compare **strictement** à `'0'` (`model.php:933,954,1104…1251`) ou à `'1'` (`model.php:1392,1414,2122,2144`). Conséquence : si la session est vide (`NULL`), les fonctions qui testent `=== '0'` retombent sur la branche « admin, sans filtre » (`Count*`, `AskDenonciations`, `AskOptions`, `AskResas`, `Ventes`), alors que celles qui testent `=== '1'` retombent sur la branche « commercial » (`SearchAllProspect`, `GetTotalCountApartments`, `GetTotalApartmentsInfos`).

### 2.3 Décision pour Laravel (proposée)

- Garde `auth` sur toutes les routes non publiques. Un seul `Gate`/middleware `admin` (`role === admin`) qui **interrompt** la requête (redirection `/` sans corps, comme le comportement visible dans le navigateur).
- Énumération `Role` : `Admin = 1`, `Commercial = 0` (on garde les valeurs en base). Le compte est actif si `statut = 1`.
- Policies `ProgrammePolicy::view` (la ligne `acces` existe, ou admin) et `LotPolicy::view` (la ligne `acces_lot` existe, ou admin). Les autres modules les appellent au lieu de réécrire le filtrage.
- Les modules ne lisent jamais la session directement : `auth()->user()->role`, `auth()->id()`.

## 3. Écrans et URL

Kohana : `index_page = 'index.php'` (`config.php:22`), `site_domain = '/'` (`config.php:8`, `SITE_URL`). `.htaccess:20` réécrit `/xxx` en `index.php?kohana_uri=xxx`. Les deux formes `/connexion` et `/index.php/connexion` fonctionnent ; le JS utilise `/index.php/json/...` (`global.js:1` `SITE="/"`).

### 3.1 `GET|POST /connexion` — `Connexion_Controller::index` (`connexion.php:6-44`)

- Gabarit `template/connexion` (`__connexion.php:12`), vue `V_connexion`. Titre exact : `Corim Promotion - Connexion à votre espace` (`connexion.php:8`).
- Si l'utilisateur est déjà connecté : `Location: /` (`__connexion.php:26-33`), sans arrêt du script (§9).
- POST attendu : `action=connexion` (champ caché, `V_connexion.php:26`), `identifiant` (texte, requis, règle `standard_text` = `/^[\pL\pN\pZ\p{Pc}\p{Pd}\p{Po}]++$/u`, `valid.php:298`), `password` (requis) (`connexion.php:12-20`). Les champs ont l'attribut HTML `required` (`V_connexion.php:20,24`).
- Succès (`connexion.php:21-30`) : `GetConnexion` (§5.2) doit renvoyer **exactement une** ligne. La session reçoit alors `SessionId` = `user.id`, `SessionPseudo`, `SessionName` = `user.name`, `SessionAcces` = `privileges.acces` (chaîne `'0'`/`'1'`), puis `Location: /`. Aucune régénération d'identifiant de session ; `updateSessionId` est sans effet (§5.2).
- Échec d'authentification : la page est réaffichée avec, dans `<div class="alert alert-warning"><ul><li>`, le texte exact `Identifiant et/ou mot de passe non reconnu` (`connexion.php:32`, `V_connexion.php:5-17`).
- Échec de validation : messages de `i18n/fr_FR/form_errors.php` : `L'identifiant ne peut rester vide.` (l.4), `L'identifiant ne doit pas contenir de caractères spéciaux.` (l.5), `Le mot de passe ne peut rester vide.` (l.10). Pour tout autre cas : `Champs invalid.` (l.7).
- Un POST sans `action=connexion` (par exemple `action=forgottenpassword` du formulaire de la modale, `V_connexion.php:43-49`) n'a aucun effet : la page est réaffichée.
- Comptes à 3 pseudos actifs en double (§5.4) : si deux comptes actifs partagent pseudo **et** mot de passe, `sizeof == 2` et la connexion est refusée.

### 3.2 `GET /connexion/deconnection` — `Connexion_Controller::deconnection` (`connexion.php:46-48`)

`session->destroy()` puis `Location: /connexion` (`__connexion.php:44-47`). Lien de la toolbar (`template/toolbar.php:41`).

### 3.3 `GET /logout` — `Logout_Controller::index` (`logout.php:7-12`)

`isDeconnected()` (si connecté, pose `Location: /` sans exit), puis `shutdown()` détruit la session et remplace l'en-tête par `Location: /connexion`. Réponse finale : 302 vers `/connexion`, connecté ou non. Lien « Déconnexion » du menu (`template/template.php:117`, `template_welcome.php:68`).

### 3.4 `GET /gestion_privilege` — `Gestion_privilege_Controller::index` (`gestion_privilege.php:16-25`)

- Rôle : admin (`gestion_privilege.php:10-13`, sans exit : voir F2).
- Données : `getUsers()` (tous les utilisateurs joints à `privileges`, triés par `name`, `model.php:1492-1496`), `GetProgrammes()` (projets non archivés, tri `projet_id DESC`, `model.php:1694-1700`), `GetApartments()` (tous les lots, tri `id`, `model.php:2165-2169`).
- Titre : `Corim promotion - Gestion des privilèges` (`__main.php:43` + `gestion_privilege.php:23`).
- Vue `V_Gestion_privilege` : titre `<h1>Gestion des privilèges</h1>`. Tableau `tablesorter-green` (NOM, EMAIL, IDENTIFIANT, STATUT = Administrateur/Commercial, bouton `Privilèges` `.push-privileges` `rel=id`) (`V_Gestion_privilege.php:25-55`). Modale Bootstrap `#privileges` (`l.122-187`) : bandeau `CLIQUEZ SUR L'IMAGE POUR ACCÉDER AUX LOTS`, une ligne par programme (vignette = premier fichier de `medias/programmes/{key}/thumb/` via `list_dir`, `l.6-24,139-142`), case `projet[]`, liens `TOUT COCHER` / `DÉCOCHER`, sous-tableau caché des lots du programme (`lot[]`, RÉFÉRENCE = `numero`, TYPE), champ caché `user`, boutons `Fermer` / `Valider` (`#submitPrivileges`).
- JS (`global.js:1241-1281`) : à l'ouverture, décoche tout puis appelle `GetUserPrivilege` et `GetUserPrivilegeLot` (§4) pour cocher `#projectCheck{idprojet}` / `#lotCheck{idlot}`. Un clic sur la vignette replie ou déplie les lots (`l.1283-1286`). `TOUT COCHER` coche le programme et tous ses lots (`l.1229-1239`). `Valider` envoie `UpdateUserPrivilege` puis affiche `Les privilèges de l'utilisateur ont bien été modifiés` si `errors==1` (`l.1212-1227`).
- La même modale et le même JS existent sur `/gestion_promoteurs` (`V_Gestion_promoteurs.php:86,146`).

### 3.5 `/administration` — `Administration_Controller` (`administration.php`)

- `GET /administration` : vue `admin/welcome`, une page HTML vide (« Document sans nom ») (`administration.php:7-10`).
- `GET|POST /administration/Ajouter_Appartement` : formulaire Kohana généré ; un POST valide appelle `Add_Appartement($_POST)` et `Add_Ville` (`administration.php:12-103`). **Aucune authentification** (F3).
- Toute autre méthode : `__call` affiche `This text is generated by __call. If you expected the index page, you need to use: welcome/index/…` (`administration.php:106-110`).
- Aucun lien vers ces pages dans les gabarits. Code mort probable : voir Q5.

## 4. Actions AJAX / JSON

Commun à toutes les actions de `json.php` :
- URL `/index.php/json/<Action>/`, gabarit `template/json` qui ne fait qu'`echo $content` (`__json.php:12`). Corps = sortie de `JsonMe` (`json.php:1597-1605`) : `json_encode` (PHP 5.6 : `/` échappé en `\/`, accents en `\uXXXX`). Si `?callback=` est présent, la réponse est `callback(json);` (valeur réfléchie sans filtre : F7). Content-Type par défaut de Kohana : `text/html` (aucun `header()` dans ces actions).
- Le client lit les réponses avec `eval('(' + callback + ')')` (`global.js:1219,1256,1271,1624,1640,1670,1694,1768,1781`).
- **Aucune authentification ni aucun CSRF** sur ces actions (F1).
- Le filtre global XSS de Kohana modifie toutes les entrées POST (`config.php:71`, `Input.php:112-118,438-441`).

| Action | Méthode / paramètres | Réponse | Effets en base | fichier:ligne |
|---|---|---|---|---|
| `UpdateUserPrivilege` | POST `user` (id), `projet[]` (ids projet, optionnel), `lot[]` (ids lot, optionnel) | `{"errors":1}` si `projet[]` absent **ou** si tous les `INSERT acces` ont réussi ; `{"errors":0}` si au moins un a échoué. Aucune sortie sans POST | `DELETE acces` puis `DELETE acces_lot` de l'utilisateur ; `INSERT acces` par projet ; si tous OK, `INSERT acces_lot` par lot (résultat ignoré) puis e-mail E1. Sans `projet[]` : tout est supprimé, ni lot ni e-mail | `json.php:1075-1136`, `model.php:1312-1349,1718-1725` |
| `GetUserPrivilege` | POST `key` (id user) | `{"errors":1,"call":[{"idprojet":"12"},…]}` ou `{"errors":2}` | lecture `acces` | `json.php:1138-1152`, `model.php:1351-1358` |
| `GetUserPrivilegeLot` | POST `key` | `{"errors":1,"call":[{"idlot":"345"},…]}` ou `{"errors":2}` | lecture `acces_lot` | `json.php:1154-1168`, `model.php:1360-1367` |
| `CheckIfExist` | POST `action` (valeur libre, doit être présent), `email` | si **exactement une** ligne `user.email` correspond : `{"errors":0,"email":…,"id":"…","response":"Merci de confirmer votre demande de réinitialisation."}` ; sinon `{"errors":1,"response":"Votre email n'est pas reconnu."}`. Comptes inactifs inclus | lecture `user` | `json.php:1379-1397`, `model.php:1737-1743` |
| `CheckQuestion` | POST `action`, `email` | `{"errors":0,"response":<question>,"email":…}` ou `{"errors":1,"response":"Votre email n'est pas reconnu."}` | lecture `user.question` | `json.php:1399-1416`, `model.php:1745-1751` |
| `CheckAnswer` | POST `your_response`, `your_email` | `{"errors":0,"id":…,"pwd":<hash sha256 stocké>}` ou `{"errors":1,"response":"La réponse à votre question secrète est incorrect."}` | lecture `user` | `json.php:1418-1435`, `model.php:1753-1759` |
| `NewAccountSetting` | POST `idClient`, `pwdClient` (mot de passe en clair, **choisi par l'appelant**) | `{"errors":0,"response":"Vos nouveaux paramètres d'accès ont été envoyé à l'adresse <email>"}` ; échecs : `{"errors":1,"response":"Un problème est survenu lors du changement de vos accès, veuillez contacter l\administrateur du site."}` (le `\a` est littéral : apostrophe perdue, `json.php:1478,1482`) ou `…lors du contrôle de votre compte, veuillez contacter l'administrateur du site.` (`json.php:1486`) | `UPDATE user SET password=sha256(HASH.htmlspecialchars(pwd)), pwdshow=htmlspecialchars(pwd) WHERE id=…` puis e-mail E2 | `json.php:1437-1491`, `model.php:1615-1621,1707-1716` |
| `GenerateNewAccountSetting/{id}` | GET, segment ignoré | corps vide (la méthode renvoie une chaîne sans l'afficher) ; aucun appelant | aucun | `json.php:1493-1503` |
| `ShowToolbar` | GET (appel AJAX sans données) | `{"errors":0}` | session `SessionToolbar='1'` | `json.php:1505-1510`, `global.js:1764-1775` |
| `HideToolbar` | GET | `{"errors":0}` | supprime `SessionToolbar` | `json.php:1512-1517`, `global.js:1777-1788` |

Flux « mot de passe oublié » réellement utilisé (`global.js:1612-1653`, modale `V_connexion.php:35-57`) :
1. `Valider` → `CheckIfExist` avec `email` et `action=forgottenpassword` (champs de la modale).
2. `errors==1` → `<div class="alert alert-warning">Votre email n'est pas reconnu.</div>`.
3. `errors==0` → `<div class="alert alert-info">` avec le message et le bouton `CONFIRMER LA RÉINITIALISATION DE VOTRE MOT DE PASSE` (`rel` = id utilisateur).
4. Le clic génère `Math.random().toString(36).slice(-8)` (8 caractères base 36) **dans le navigateur** et l'envoie à `NewAccountSetting` avec `idClient`. Le corps de la modale est remplacé par `response` ; si `errors==0`, les boutons du pied de modale sont masqués.

Flux « question secrète » (`CheckQuestion` → `CheckAnswer` → `NewAccountSetting(id, hash)`, `global.js:1657-1705`) : le HTML est commenté (`V_connexion.php:59-164`), la fonction JS `CheckQuestion` n'existe pas et `question`/`reponse` sont vides pour les 242 comptes. Si on l'appelait, le mot de passe deviendrait le hash lui-même, transmis en clair.

## 5. Données

### 5.1 Schéma (base locale, tout en MyISAM latin1)

| Table | Colonnes | Clés | Lignes |
|---|---|---|---|
| `user` | `id` int AI, `pseudo` varchar(100), `name` varchar(250), `password` varchar(250), `email` varchar(250), `question` varchar(250), `reponse` varchar(250), `statut` int défaut 1, `pwdshow` varchar(150) | PK `id` (AUTO_INCREMENT=266) | 242 |
| `privileges` | `iduser` int, `acces` int | UNIQUE `iduser` | 265 (23 orphelines) |
| `acces` | `iduser` int, `idprojet` int | aucune | 829 (30 orphelines côté user, 0 côté projet, 0 doublon) |
| `acces_lot` | `iduser` int, `idlot` int | UNIQUE (`iduser`,`idlot`) | 18 470 (956 orphelines côté user, 144 côté lot ; 1 782 lots dont le programme n'est pas dans `acces` pour cet utilisateur) |
| `acces_lot_backup` | `iduser`, `idlot` | aucune | 7 353, jamais lue par le code |
| `sessions` | `session_id` varchar(127), `last_activity` int unsigned, `data` text (base64 de la session sérialisée, `Session/Database.php:96,105`) | PK `session_id` | 890 |

Les inserts sont positionnels (`INSERT INTO user VALUES (NULL, pseudo, name, password, email, '', '', 1, pwd)`, `model.php:1272`) : l'ordre des colonnes compte.

### 5.2 Requêtes d'origine

- Connexion `GetConnexion` (`model.php:1624-1637`) :
  `SELECT user.id,user.pseudo,user.name, privileges.acces FROM user INNER JOIN privileges ON user.id = privileges.iduser WHERE pseudo = <escape(identifiant)> AND password = <escape(hash)> AND statut=1`
  Comparaison MySQL sur `pseudo` (collation latin1_swedish_ci) : **insensible à la casse et aux espaces finaux**.
- `updateSessionId` (`model.php:1727-1735`) : `UPDATE sessions SET session_id=<id session> WHERE data=<user.id>`. `data` contient du base64 : la condition ne correspond jamais et l'appel n'a aucun effet.
- Privilèges : `DELETE FROM acces WHERE iduser=$iduser` (`model.php:1336`), `DELETE FROM acces_lot WHERE iduser=$iduser` (`1346`), `INSERT INTO acces VALUES ($iduser, $entry)` (`1317`), `INSERT INTO acces_lot VALUES ($iduser, $entry)` (`1327`), `SELECT acces.idprojet FROM acces WHERE iduser=$iduser` (`1355`), `SELECT acces_lot.idlot FROM acces_lot WHERE iduser=$iduser` (`1364`). Les inserts utilisent `escape()` = `htmlspecialchars`, **sans échappement SQL ni guillemets** (F4).
- `GetIdentity` (`model.php:1718-1725`) : `SELECT pseudo, email, pwdshow FROM user WHERE id = $iduser` (non échappé).
- Mot de passe oublié : `Get_If_Exist` `SELECT id FROM user WHERE email=?` (`1737-1743`), `Get_Question` (`1745-1751`), `Get_Answer` `WHERE email=? AND reponse=?` (`1753-1759`), `Control_This_Account_By_Id` (`1615-1621`), `UpdateThisAccount` (`1707-1716`). Ces fonctions passent par le query builder de Kohana, qui échappe les valeurs.
- Listes d'utilisateurs : `getUsers` / `getUsersActifs` / `getUsersInactifs` / `getUserById` / `getPrescripteurs` / `GetPrescripteursByProject` / `getUsersWithAccess` (`model.php:1492-1597`), `getEmailUser` (`78-83`), `GetEmailByAccess` (`405-416`). Toutes font `INNER JOIN privileges` sauf `getEmailUser`/`GetEmailByAccess`.
- Effet des privilèges hors de ce module : programmes visibles par un commercial = `acces` JOIN `projet` (`GetProgrammesByAccess`, `model.php:1652-1663`, utilisé dans `__main.php:25`) ; lots visibles dans la grille = `acces_lot` (`GetTotalCountApartments`/`GetTotalApartmentsInfos`, `model.php:2115-2163`). Les deux listes sont indépendantes : avoir le programme sans aucun lot affiche une grille vide (ou une erreur SQL `IN ()`, voir F9).

### 5.3 Algorithme de hash des mots de passe (référence pour toute la refonte)

Constante : `HASH = hash("SHA1", <littéral>)` (`config.php:134`), donc une chaîne hexadécimale minuscule de 40 caractères. Ne pas recopier le littéral : il passe dans `.env` (`LEGACY_PASSWORD_SALT`, valeur = les 40 caractères hexadécimaux).

Transformation complète du mot de passe saisi, dans l'ordre :
1. `utf8::clean($_POST)` : suppression des caractères de contrôle ASCII et de l'UTF-8 invalide (`system/core/utf8.php:81`).
2. `Input::clean_input_data` (`Input.php:419-449`), filtre XSS global activé (`config.php:71`) : `xss_clean` intégré (`Input.php:358-386`), qui remplace `&amp;`, `&lt;` et `&gt;` puis applique `html_entity_decode`, retire les attributs `on*`, `javascript:` et les balises dangereuses. Ensuite `\r\n`/`\r` deviennent `\n`. Les chaînes vides ou faites d'espaces ne sont pas filtrées (`Input.php:298`).
3. `normalize()`/`escape()` (`model.php:11-19`) : `htmlspecialchars($pwd, ENT_QUOTES)` (UTF-8 par défaut en PHP 5.6). `'` → `&#039;`, `"` → `&quot;`, `&` → `&amp;`, `<` `>` → `&lt;` `&gt;`.
4. `password = hash('sha256', HASH . résultat)`, soit 64 caractères hexadécimaux minuscules (`model.php:1268, 1546, 1628, 1711`).

Formule : **`sha256( HASH . htmlspecialchars( xss_clean(utf8_clean(pwd)), ENT_QUOTES ) )`**. Elle est identique dans les quatre chemins d'écriture et de vérification : connexion `GetConnexion:1627-1628`, création `AddPromoteur:1268`, modification `UpdateUser:1541,1546`, réinitialisation `UpdateThisAccount:1710-1711`.

Vérification sur les données : les 242 hash font 64 caractères hexadécimaux. Pour les 123 comptes où `pwdshow` est renseigné, `SHA2(CONCAT(HASH, pwdshow),256) = password` dans 123 cas sur 123 (`pwdshow` déjà passé par `htmlspecialchars` dans `UpdateUser`/`UpdateThisAccount` ; aucun ne contient `&`). Aucun compte n'a de mot de passe vide.

Implémentation Laravel attendue : un `LegacyHasher` (ou une vérification dans le `UserProvider`) qui applique les étapes 3-4 à l'entrée brute. Les étapes 1-2 sont l'identité pour tout mot de passe sans `&`, `<`, entité HTML, caractère de contrôle ni retour chariot ; un test Pest couvre ces cas limites. Au premier succès, `Hash::make` (bcrypt/argon2) remplace le hash et une colonne/drapeau `legacy_hash` passe à faux. `pwdshow` n'est pas migré.

`UpdateUser` en modification sans nouveau mot de passe réécrit `password = hidden_pwd` venu du formulaire (`model.php:1547-1549`) : le hash circule dans un champ caché.

### 5.4 Règles implicites

- `statut` : `1` actif, `0` inactif. Seuls les comptes actifs se connectent.
- `SessionAcces` est la chaîne `'0'`/`'1'` (résultat MySQL), comparée strictement partout.
- Pseudos : 13 valeurs en double, dont 3 parmi les comptes actifs. E-mails : 3 doublons (`CheckIfExist` répond alors « non reconnu »). Aucun e-mail vide.
- Session : driver `database`, cookie `kohanasession`, durée `50000` s (≈ 13 h 53), validation du `user_agent`, pas de chiffrement, pas de régénération (`session.php:7,12-15,21,26,33,39,45,50`). Cookie `secure=FALSE`, `httponly=FALSE`, `path=/`, `expire=0` (`system/config/cookie.php:9-32`). `gc_probability = 2` % (`session.php:50`).

## 6. Emails

Transport : SwiftMailer `swift` (`application/libraries/swift/lib/swift_required.php`). Expéditeur `EMAIL` / nom `Corim promotion` (`config.php:142-146`, valeurs non recopiées). Tous les corps sont un `<table width="500">` HTML avec le logo `BASE.'medias/images/logo-corim.png'` (`BASE = https://corim-vente.com/`, `config.php:127`) et une version texte obtenue par `strip_tags`.

| # | Déclencheur | Destinataire | Sujet exact | Corps | Transport |
|---|---|---|---|---|---|
| E1 | `UpdateUserPrivilege` quand tous les `INSERT acces` ont réussi | `user.email` de l'utilisateur modifié | `[Corim Promotion] - Votre accès à la plateforme CORIM` | « Bonjour, Nous vous informons avoir actualisé les informations et fichiers sur le(s) programme(s) en ligne. Voici votre identifiant et votre mot de passe vous permettant de vous connecter : Identifiant : {pseudo} Mot de passe : **{pwdshow}** Pour y accéder, vous pouvez cliquer ici » + adresse « Immeuble "Le Cristal" 1475 av.Albert Einstein 34000 MONTPELLIER Tél … Mob … » | Swift SMTP SSL (`EMAIL_SMTP`, `EMAIL_PORT`) — `json.php:1104-1125` |
| E2 | `NewAccountSetting` après mise à jour réussie | `user.email` du compte `idClient` | `[Corim promotion] - Vos nouveaux paramètres d'accès` | « Bonjour, Votre accès à la plateforme Corim Promotion a été modifié. Voici votre identifiant et votre mot de passe … Identifiant : {pseudo} Mot de passe : **{pwdClient}** Pour y accéder, vous pouvez cliquer ici » + même adresse (Tél/Mob) | Swift SMTP SSL — `json.php:1450-1472` |
| (E3, module promoteurs) | `gestion_promoteurs/modifier` réussi | e-mail saisi | `[Corim promotion] - Votre accès à la plateforme CORIM` | comme E2, avec mot de passe saisi et « Fax » au lieu de « Mob » | `Swift_MailTransport` (`mail()`) — `gestion_promoteurs.php:97-116` |

Pour E1, 83 des 99 comptes actifs ont un `pwdshow` vide : la ligne « Mot de passe : » part vide. La variable `$sujet` est déclarée mais non utilisée (`json.php:1105`). Le résultat de `send` est ignoré pour E1 et conditionne la réponse pour E2 (`json.php:1473-1479`).

Refonte : on garde les déclencheurs, les sujets et la mise en page. Le mot de passe en clair disparaît des deux e-mails (impossible sans `pwdshow`), remplacé par l'identifiant et un lien signé de définition du mot de passe. Voir Q3/Q4.

## 7. Fichiers

Aucun upload. `V_Gestion_privilege.php:6-24,139-140` lit `$_SERVER['DOCUMENT_ROOT'].'/medias/programmes/{projet_titre_key}/thumb/'` et concatène **tous** les noms de fichiers dans l'attribut `src`. L'image n'est correcte que si le dossier contient un seul fichier (sinon les chemins sont collés). Comportement visuel à conserver : première vignette du dossier `thumb` (Q8).

## 8. Design

- Connexion : gabarit `application/views/template/connexion.php` (Bootstrap 3.3.6 CDN, jQuery 1.11.3 + migrate 1.2.1, jQuery UI 1.11.3, tablesorter, print-preview, `medias/css/connexion.css`, `medias/css/global.css`, police Questrial). Vue `application/views/V_connexion.php` : `#login.animated.fadeInDown`, `#logoff` (lien `/`), modale Bootstrap `#lost-password`. Les blocs HTML commentés (`l.59-164`) ne sont pas repris.
- Menu et déconnexion : `application/views/template/template.php:77-121` et `template_welcome.php:26-68` (menu admin l.92-104, menu commun l.110-117, liens « Déverrouiller les outils » / « Verrouiller les outils » visibles seulement si `SessionToolbar=1`, l.113-116).
- Toolbar : `application/views/template/toolbar.php` (menus Accueil, Actualiser, Imprimer, Dénonciations, Contact, lien `connexion/deconnection` avec `medias/images/18px-logout.svg.png`, « Espace de {SessionName} ») et `template/header.php`. **Non rendues** : `echo $toolbar` et `echo $header` sont commentés (`template.php:74-75`, `template_welcome.php:26`). Les liens du menu qui pilotent la toolbar n'apparaissent que si elle est déjà active, ce qui ne peut arriver qu'en appelant l'URL à la main.
- Privilèges : `application/views/V_Gestion_privilege.php` (tablesorter `tablesorter-green`, modale Bootstrap), JS `medias/js/global.js:1212-1331`. Le bloc `colorbox` `#gestionAcces` (`V_Gestion_privilege.php:57-121`, `global.js:1288-1331`) est commenté ou mort : on ne le reprend pas.
- Plugins : `tablesorter` → petit tri vanilla JS (CLAUDE.md), sans changement visuel (filtre « Recherche... », thème vert). `colorbox` et `confirm` ne servent pas ici. Bootstrap 3 et jQuery restent pour les modales (ou `<dialog>` stylé à l'identique). `eval()` est remplacé par `JSON.parse` ou `fetch().json()`.

## 9. Bugs et failles constatés

| # | Problème | Fichier:ligne | Décision proposée |
|---|---|---|---|
| F1 | **Aucune authentification ni CSRF sur `json/*`** : n'importe qui peut lire et modifier les privilèges, lister les clients (`SearchClients`), etc. | `json.php` (aucun `isConnected`), `__json.php:17-24` (clé `VBSessionId` jamais écrite) | Corriger : `auth` + rôle par action, CSRF |
| F2 | **Contrôle admin sans `exit()`** : un commercial connecté exécute les actions admin (écritures comprises) et reçoit la page complète dans le corps de la 302 (par exemple `/gestion_privilege` divulgue noms, e-mails et pseudos de tous les utilisateurs) | `gestion_privilege.php:11-13` et toutes les lignes « sans exit » du §2.2 | Corriger : middleware qui interrompt |
| F3 | `/administration/Ajouter_Appartement` accessible sans connexion (écrit dans `appartements` et `villes`) | `administration.php:12-39`, `__main.php:37-39` | Corriger : supprimer (Q5) |
| F4 | **Prise de contrôle de n'importe quel compte** : `NewAccountSetting` accepte un `idClient` arbitraire et un mot de passe choisi par l'appelant, sans jeton. `CheckIfExist` renvoie l'id à partir d'un e-mail (énumération) | `json.php:1437-1448,1388`, `global.js:1633-1637` | Corriger : reset Laravel par lien signé à usage unique, réponse identique que l'e-mail existe ou non, rate limiting |
| F5 | `CheckAnswer` renvoie le hash du mot de passe à un anonyme ; la réponse secrète est comparée en clair | `json.php:1427`, `model.php:1758` | Corriger : ne pas reprendre la question secrète (Q2) |
| F6 | Injection SQL : `InsertPrivilege`/`InsertPrivilegeLot` (`htmlspecialchars` sans guillemets), `GetIdentity` (non échappé), `AddPromoteur` (`pwdshow` brut). Identifiants MySQL en dur dans le constructeur du modèle | `model.php:1314-1317,1324-1327,1720-1722,1272,8` | Corriger : query builder et paramètres ; secrets dans `.env` |
| F7 | XSS réfléchie via `?callback=` de `JsonMe`, réponse en `text/html` | `json.php:1600-1601` | Corriger : pas de JSONP sur `json/*` (le JSONP public relève de `public-api`) |
| F8 | Mots de passe en clair : `pwdshow` stocké et envoyé par e-mail (E1, E2) ; hash sha256 salé à sel fixe, sans étirement ; hash envoyé dans un champ caché | `model.php:1272,1553,1713`, `json.php:1102,1114,1461`, `model.php:1547-1549` | Corriger : `Hash::make`, rehash à la connexion, `pwdshow` supprimé |
| F9 | Commercial sans aucun `acces_lot` → `IN ()` → erreur SQL affichée. Avec une recherche (`qtype`/`query`), le filtre `acces_lot` saute et le commercial voit **tous** les lots du programme | `model.php:2130-2136,2151-2157` | Corriger : liste vide → grille vide ; filtre toujours appliqué (à reporter dans `programmes-lots`) |
| F10 | `programme/show/{key}` ne vérifie pas que le programme figure dans `acces` (IDOR : documents, plans, visuels de tout programme) | `programme.php:16-36` | Corriger : `ProgrammePolicy::view` |
| F11 | Fixation de session : pas de régénération à la connexion, `updateSessionId` sans effet ; cookie sans `HttpOnly` ni `Secure` | `connexion.php:25-29`, `model.php:1727-1735`, `session.php:39`, `cookie.php:26,32` | Corriger : `session()->regenerate()`, cookies secure/HttpOnly/SameSite=Lax |
| F12 | Un compte désactivé (`statut=0`) ou dont le rôle change garde sa session (jusqu'à 50 000 s) | `__main.php:46-61` (session seule) | Corriger : recharger l'utilisateur à chaque requête (Laravel le fait) et refuser s'il est inactif |
| F13 | Pas de limite de tentatives de connexion ni de reset | `connexion.php:11-38` | Corriger : `RateLimiter` |
| F14 | Session vide (`NULL`) : les fonctions `=== '0'` renvoient les données « admin » non filtrées | `model.php:1104,1121,1139,1158,1172,1193,1204,1214,1241` | Corriger : rôle toujours défini derrière `auth` |
| F15 | `UpdateUserPrivilege` : `errors=1` sert à la fois de succès et de « aucun projet » ; `INSERT acces_lot` non vérifié ; e-mail envoyé à chaque enregistrement | `json.php:1088-1133` | Reproduire le comportement visible (message de succès, e-mail) ; transaction en base |
| F16 | Message `veuillez contacter l\administrateur` (apostrophe perdue) ; « envoyé » sans accord | `json.php:1475,1478,1482` | Reproduire le texte à l'identique sauf décision contraire (Q9) |
| F17 | Pseudos actifs en double (3) : connexion impossible s'ils partagent le mot de passe ; e-mails en double (3) : reset impossible | `connexion.php:24`, `json.php:1385` ; données | Nettoyer les données avant d'ajouter `UNIQUE(pseudo)` (Q6) |
| F18 | Orphelins : 23 `privileges`, 30 `acces`, 956 + 144 `acces_lot` ; `TrashPromoteur` supprime le `user` sans ses privilèges | `model.php:1292-1300` | Corriger : FK `ON DELETE CASCADE` et purge des orphelins à la migration |
| F19 | `isDeconnected`/`isConnected` de `__connexion` et `__json` sans `exit()` | `__connexion.php:17-33`, `__json.php:17-33` | Corriger (aucun effet visible) |
| F20 | `xss_clean` global modifie les mots de passe contenant des entités ou balises | `config.php:71`, `Input.php:358-386` | Reproduire **uniquement** dans la vérification du hash hérité ; plus de filtre global ensuite |

## 10. Critères d'acceptation

1. `GET /connexion` (et `/index.php/connexion`) non connecté → 200, titre `Corim Promotion - Connexion à votre espace`, formulaire `identifiant`/`password`/`action=connexion` et modale `#lost-password` conformes au balisage de `V_connexion.php:1-57`.
2. `GET /connexion` connecté → 302 vers `/`.
3. POST d'identifiants valides (compte actif, hash hérité) → 302 vers `/`, session authentifiée, identifiant de session régénéré.
4. Après le 3, `user.password` est un hash `Hash::make` et le marqueur « hérité » est levé ; une 2e connexion avec le même mot de passe réussit.
5. Le hash hérité est vérifié par `sha256(LEGACY_PASSWORD_SALT . htmlspecialchars(pwd, ENT_QUOTES))` ; un test couvre un mot de passe contenant `'`, `"`, `&`, `<`.
6. Mauvais mot de passe → 200, `<li>Identifiant et/ou mot de passe non reconnu</li>` dans `.alert.alert-warning`.
7. Compte `statut=0` avec le bon mot de passe → même message qu'au 6.
8. Identifiant vide → `L'identifiant ne peut rester vide.` ; mot de passe vide → `Le mot de passe ne peut rester vide.`
9. Pseudo comparé sans tenir compte de la casse (comme la collation latin1_swedish_ci).
10. La 6e tentative échouée en une minute pour un même identifiant et une même IP → refusée (429 ou message), sans vérification du mot de passe.
11. `GET /logout` → session invalidée, 302 vers `/connexion`, connecté ou non. Idem pour `GET /connexion/deconnection`.
12. Toute page « connecté » du §2.2 sans session → 302 vers `/connexion`, sans corps applicatif.
13. Toute page admin du §2.2 avec un commercial → 302 vers `/`, **sans aucun effet en base et sans contenu de page** dans la réponse.
14. Toute action `json/*` sans session → 401/302, sans effet ; avec une session sans jeton CSRF → 419.
15. `GET /gestion_privilege` admin → tableau NOM/EMAIL/IDENTIFIANT/STATUT avec `Administrateur`/`Commercial`, un bouton `Privilèges` par utilisateur, modale listant les programmes non archivés (`projet_id DESC`) et leurs lots.
16. `POST json/GetUserPrivilege` `key=<id>` admin → `{"errors":1,"call":[{"idprojet":"…"}]}` (ids en chaînes) ou `{"errors":2}` ; idem `GetUserPrivilegeLot` avec `idlot`.
17. `POST json/UpdateUserPrivilege` admin avec `user`, `projet[]`, `lot[]` → `acces` et `acces_lot` de l'utilisateur remplacés exactement, `{"errors":1}`, e-mail E1 envoyé (mailer `log`) avec le sujet exact.
18. `UpdateUserPrivilege` sans `projet[]` → toutes les lignes `acces`/`acces_lot` de l'utilisateur supprimées, `{"errors":1}`, aucun e-mail.
19. `UpdateUserPrivilege` par un commercial → refusé, aucune modification.
20. Mot de passe oublié avec un e-mail connu → message `Merci de confirmer votre demande de réinitialisation.` (ou message neutre retenu en Q3), puis e-mail E2 ; le mot de passe n'est **jamais** fixé par le client et l'id utilisateur n'est jamais exposé.
21. Mot de passe oublié avec un e-mail inconnu → même réponse visible que pour un e-mail connu (anti-énumération) si Q3 est validée, sinon `Votre email n'est pas reconnu.`
22. Le lien de réinitialisation est signé, expire, ne sert qu'une fois et met à jour le hash avec `Hash::make`.
23. `json/CheckQuestion`, `json/CheckAnswer`, `json/GenerateNewAccountSetting` → 404 (si Q2 est validée).
24. Commercial : la liste des programmes (accueil) ne contient que les projets de `acces` ; la grille de lots ne contient que les lots de `acces_lot`, recherche comprise ; sans aucun lot → grille vide, sans erreur.
25. Commercial sur `programme/show/{key}` d'un programme absent de `acces` → 403 ou redirection `/` (Q7).
26. Un utilisateur passé à `statut=0` pendant sa session est déconnecté à la requête suivante.
27. Cookie de session `Secure`, `HttpOnly`, `SameSite=Lax` ; aucune réponse JSON n'accepte `?callback=`.
28. Migration : `pwdshow` absent du nouveau schéma ; FK `privileges/acces/acces_lot → user` et `acces → projet`, `acces_lot → appartements` ; orphelins purgés (comptages du §5.1 consignés dans le rapport de migration).
29. Les menus de `template.php:92-117` sont rendus à l'identique selon le rôle (admin : 8 entrées supplémentaires).

## 11. Questions ouvertes

1. **Frontière avec `promoteurs`** : la gestion des comptes (`gestion_promoteurs`, `AddPromoteur`, `DeletePromoteur`, `TrashPromoteur`, `ActivPromoteur`, `UpdateUser`) relève-t-elle de `promoteurs` (proposition) ou de ce module ? La modale des privilèges existe sur les deux écrans : un seul composant partagé, porté par ce module ?
2. **Question secrète** : `question`/`reponse` sont vides pour les 242 comptes et l'interface est commentée. On supprime les colonnes et les actions `CheckQuestion`/`CheckAnswer` ?
3. **Mot de passe oublié** : on passe au lien signé (checklist sécurité), en gardant la modale, les textes et l'e-mail E2 réécrit sans mot de passe ? Et la réponse neutre pour les e-mails inconnus, qui change le texte `Votre email n'est pas reconnu.` ?
4. **E-mail E1** (à chaque enregistrement de privilèges) : sans `pwdshow`, quel contenu ? Proposition : identifiant + lien « définir mon mot de passe ». Faut-il l'envoyer à chaque enregistrement comme aujourd'hui ?
5. `/administration` (sans lien, sans authentification, page vide et ancien formulaire d'ajout de lot) : on le supprime ? Les journaux d'accès prod montrent-ils un usage ?
6. **Doublons** : 3 pseudos actifs et 3 e-mails en double. Qui arbitre le nettoyage avant `UNIQUE(pseudo)` (et `UNIQUE(email)` ?) ?
7. **Accès programme** : faut-il bloquer `programme/show/{key}` pour un commercial sans ligne `acces` (correction de F10) ? Aujourd'hui il y accède, avec une grille limitée à ses lots.
8. Vignettes de la modale des privilèges quand `thumb/` contient plusieurs fichiers : prendre la première (par ordre alphabétique ?) ?
9. Faut-il corriger les fautes des messages (`l\administrateur`, `ont été envoyé`, `est incorrect`) ou les garder à l'identique ?
10. **Toolbar** (non rendue, inaccessible depuis l'interface) : on la supprime avec `ShowToolbar`/`HideToolbar`, ou on garde deux routes sans effet ?
11. « Première connexion » : je n'ai trouvé aucun mécanisme dans le code (ni drapeau, ni redirection, ni changement de mot de passe forcé). Ce besoin existe-t-il ailleurs (procédure manuelle) ? Faut-il forcer un changement de mot de passe à la première connexion après la migration, puisque les mots de passe connus de Corim (`pwdshow`) disparaissent ?
12. `acces_lot_backup` (7 353 lignes, jamais lue) : on la supprime à la migration ?
13. Durée de session : on garde 50 000 s (≈ 14 h) ou on prend la valeur Laravel (120 min d'inactivité) ?
14. Les sessions actives (890) ne sont pas migrées, donc tout le monde devra se reconnecter à la bascule. Acceptable ?
