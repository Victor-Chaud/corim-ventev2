# Contrat — promoteurs

Statut : brouillon (à valider par Victor)
Sources lues :
- `OLD/application/controllers/gestion_promoteurs.php:1-127` (contrôleur complet)
- `OLD/application/controllers/json.php:1-13`, `:840-887` (AddPromoteur), `:1027-1073` (DeletePromoteur, TrashPromoteur, ActivPromoteur), `:1075-1168` (UpdateUserPrivilege, GetUserPrivilege, GetUserPrivilegeLot, appelés par l'écran), `:1437-1503` (NewAccountSetting, pour comparaison des emails), `:1597-1605` (JsonMe)
- `OLD/application/controllers/__main.php:8-61` (isConnected, isAdmin), `OLD/application/controllers/__json.php:1-48`, `OLD/application/controllers/connexion.php:26-29`
- `OLD/application/models/model.php:3-23`, `:1264-1310`, `:1351-1366`, `:1492-1597`, `:1624-1637`, `:1694-1700`, `:1718-1725`, `:2165-2169`
- `OLD/application/views/V_Gestion_promoteurs.php:1-197`, `OLD/application/views/V_Modifier_promoteur.php:1-55`
- `OLD/application/views/template/template.php:12-30`, `:67-68`, `:92-101`, `OLD/application/views/template/json.php`
- `OLD/medias/js/global.js:1-18`, `:1096-1286`
- `OLD/application/config/config.php:8`, `:22`, `:127-130`, `:143` (constantes non secrètes uniquement), `OLD/.htaccess`, `OLD/index.php:34`, `OLD/system/core/Kohana.php:239`, `OLD/system/libraries/drivers/Database/Mysql.php:363-377`
- Base locale `sql_corim_vente_` : `SHOW CREATE TABLE` de `user`, `privileges`, `acces`, `acces_lot` ; comptages (voir §5).

## 1. Périmètre

Écran d'administration des **comptes utilisateurs** de la plateforme, appelé « Prescripteurs » dans l'interface (menu `template.php:100`, titre `V_Gestion_promoteurs.php:24`) et « promoteurs » dans le code.

**Un promoteur n'est pas une entité distincte** : c'est une ligne de la table `user` associée à une ligne de `privileges` (`model.php:1272`, `:1276`). Le **rôle** est `privileges.acces` (`1` = Administrateur, `0` = Commercial, `V_Gestion_promoteurs.php:82-83`, `:123-124`). `user.statut` n'est **pas** un rôle : c'est un drapeau actif (`1`) / inactif (`0`) (`model.php:1286`, `:1306`), et la connexion exige `statut=1` (`model.php:1634`). L'écran liste donc tous les comptes, administrateurs compris (`model.php:1493`). Les listes filtrées sur les seuls commerciaux (`getPrescripteurs`, `model.php:1568-1576`) servent à d'autres modules (`message.php:51`, `emailing.php:24`, `programme.php:41`).

Fonctions, pour l'administrateur :
- lister les comptes (tous, actifs, inactifs) et filtrer par programmes accessibles ;
- créer un compte (identifiant, nom, mot de passe, email, type) avec envoi d'un email d'ouverture contenant le mot de passe en clair ;
- modifier un compte (identifiant, nom, email, mot de passe facultatif, actif/inactif) avec envoi d'un email ;
- désactiver / réactiver un compte ;
- supprimer définitivement un compte (bouton « corbeille ») ;
- ouvrir la modale « Gestion des privilèges » (accès par programme et par lot), qui relève du module `auth-users-privileges` (voir §4.5).

## 2. Rôles et droits

| Rôle (valeur en base) | Peut faire | Contrôle dans l'ancien code (fichier:ligne) |
|---|---|---|
| Administrateur (`privileges.acces = 1`, session `SessionAcces === '1'`) | Tout le module | `gestion_promoteurs.php:9-12`, `:26-29`, `:54-57`, `:71-74` → `__main.php:46-53` (isConnected), `__main.php:55-61` (isAdmin) ; session posée à `connexion.php:26-29`. Détail : module `auth-users-privileges`. |
| Commercial (`privileges.acces = 0`) | Rien en théorie (menu absent, `template.php:92`). **En pratique** : la redirection de `isAdmin` n'est pas suivie d'un `exit` (`gestion_promoteurs.php:10-12`, etc.), la page est quand même calculée et envoyée, et le POST de `modifier` est exécuté. | Voir §9 |
| Anonyme (non connecté) | Rien en théorie. **En pratique** : les 4 actions JSON (et les 3 actions de privilèges) n'ont **aucun** contrôle d'authentification ni de rôle (`json.php:840-887`, `:1027-1073`, `:1075-1168`). Le `isConnected` de `__json.php:17-24` n'est jamais appelé et lit une autre clé de session (`VBSessionId`). | Voir §9 |
| Compte inactif (`user.statut = 0`) | Ne peut plus se connecter (`model.php:1634`). Une session déjà ouverte n'est pas invalidée (isConnected ne relit pas la base, `__main.php:46-53`). | Recoupement `auth-users-privileges` |

Décision proposée pour la refonte : toutes les URL et actions du module exigent un utilisateur connecté, actif, avec `acces = 1` (middleware + Policy), sinon redirection vers `/` (écrans) ou 403 (actions JSON).

## 3. Écrans et URL

Routage Kohana : `/{contrôleur}/{méthode}/{arg}`, accessible avec ou sans préfixe `index.php/` (`config.php:8`, `:22`, `OLD/.htaccess`). `ALLOW_PRODUCTION = FALSE` (`gestion_promoteurs.php:5`) est sans effet car `IN_PRODUCTION` vaut FALSE (`index.php:34`, `Kohana.php:239`). Gabarit : `views/template/template.php` (titre de page préfixé `Corim promotion - `, `__main.php:44`).

### 3.1 `GET|POST /gestion_promoteurs` (index)

- Rôle : administrateur. Contrôleur `gestion_promoteurs.php:24-50`. Vue `V_Gestion_promoteurs`.
- Titre : `Corim promotion - Gestion des promoteurs` (`:48`). En-tête `<h1>Gestion des prescripteurs  <small><a id="addPromoteur">Ajouter un compte</a></small></h1>` (`V_Gestion_promoteurs.php:24`, `$status = ''`).
- GET : liste de tous les comptes (`getUsers`, `model.php:1492-1496`), triés par `name ASC`.
- POST (formulaire de filtre, `V_Gestion_promoteurs.php:32-47`) :
  - `type` (int, `0` = « Contient au moins un programme sélectionné », `1` = « Contient tous les programmes sélectionnés »), obligatoire pour filtrer ;
  - `projects[]` (liste d'int `projet_id`, select multiple bootstrap-select) ; options = programmes non archivés (`GetProgrammes`, `model.php:1694-1700`, `archive=0 ORDER BY projet_id DESC`), libellé `strtoupper(projet_titre)`.
  - Si `projects` non vide et `type` présent → `getUsersWithAccess($projects, (int)$type)` (`gestion_promoteurs.php:36-37`, `model.php:1510-1533`) ; sinon liste complète.
  - Les valeurs postées sont réaffichées sélectionnées (`V_Gestion_promoteurs.php:35-36`, `:42`).
- Variables de vue : `clients`, `programmes`, `lots` (= tous les `appartements`, `model.php:2165-2169`), `status`, `post`.
- Tableau (`V_Gestion_promoteurs.php:54-93`, classe `tablesorter-green`) : colonnes `NOM`, `EMAIL` (lien `mailto:`), `IDENTIFIANT`, `STATUT` (`Administrateur` si `acces==='1'`, `Commercial` si `'0'`), `ACTIF` (label vert `OUI` si `statut==='1'`, rouge `NON` sinon), puis boutons :
  - si actif : bouton rouge `delPromoteur` (icône `ban-circle`) → désactivation ; sinon bouton vert `activPromoteur` (icône `ok-circle`) → activation (`:70-76`) ;
  - crayon → `/gestion_promoteurs/modifier/{id}` (`:86`) ;
  - bouton `push-privileges` (icône `option-vertical`) → modale privilèges (`:86`) ;
  - colonne suivante : bouton rouge `trashPromoteur` (icône `trash`) → suppression définitive (`:87`).
- Le tableau n'est pas affiché s'il n'y a aucun compte (`:52`) ; le filtre et la modale privilèges ne sont affichés que s'il existe des programmes (`:31`, `:144`).
- Modale cachée « Ajouter un compte » (`:99-132`), ouverte par colorbox 740×580 (`global.js:1116-1126`) : champs `f_add_promoteur_pseudo` (« Identifiant* »), `f_add_promoteur_name` (« NOM Prénom* »), `f_add_promoteur_pwd` (password, « Mot de passe* »), `f_add_promoteur_email` (« E-mail* »), `f_add_promoteur_statut` (select « Type de compte* » : `0` Commercial, `1` Administrateur), bouton « Enregistrer ». Soumission AJAX, voir §4.1.
- Modale bootstrap « Gestion des privilèges » (`:133-198`) : voir §4.5.

### 3.2 `GET /gestion_promoteurs/actifs` et `GET /gestion_promoteurs/inactifs`

- Rôle : administrateur. `gestion_promoteurs.php:7-22` et `:52-67`. Vue `V_Gestion_promoteurs`.
- Liste `getUsersActifs` (`statut = 1`, `model.php:1498-1502`) ou `getUsersInactifs` (`statut = 0`, `model.php:1504-1508`). `<h1>` = `Gestion des prescripteurs actifs` / `inactifs`.
- `programmes` et `lots` ne sont pas passés → pas de filtre ni de contenu de modale privilèges (le bouton `push-privileges` ouvre une modale vide).
- Les liens vers ces pages sont commentés dans la vue (`V_Gestion_promoteurs.php:28-30`) : URL encore joignables mais non exposées dans l'interface. Voir §11.

### 3.3 `GET|POST /gestion_promoteurs/modifier/{id}`

- Rôle : administrateur. `gestion_promoteurs.php:69-125`. Vue `V_Modifier_promoteur`. Titre `Corim promotion - Gestion des promoteurs - Modifier le compte` (`:123`).
- `{id}` vide → redirection `/` (`:76-79`). `{id}` inconnu → `<h1>Modifier le prescripteur </h1>` sans formulaire (`V_Modifier_promoteur.php:3`, `:16`).
- GET : formulaire pré-rempli (`V_Modifier_promoteur.php:19-52`) : `f_edit_promoteur_pseudo` (« Identifiant de connexion »), `f_edit_promoteur_name` (« Nom du prescripteur »), `f_edit_promoteur_email` (type email, « E-mail »), `f_edit_promoteur_pwd` (password vide, « Mot de passe », aide « Si le champs est laissé vide, le mot de passe actuel sera conservé. »), `f_edit_promoteur_statut` (select « Statut » : `1` Activer, `0` Désactiver), hidden `hidden_pwd` = **hash du mot de passe actuel** (`:51`), bouton « ENREGISTRER ». Le type de compte (`acces`) n'est **pas** modifiable ici.
- POST (`gestion_promoteurs.php:83-120`) :
  - pseudo, name ou email vide → alerte warning : `Attention, les champs Identifiant, Nom, E-mail et  Statut sont obligatoires` (double espace d'origine, `:91`) ;
  - email invalide (`FILTER_VALIDATE_EMAIL`) → `Attention, l\e-mail renseigné ne correspond pas à un e-mail valide` (antislash littéral affiché, `:93`) ;
  - sinon `UpdateUser` (`model.php:1535-1557`) ; si exactement 1 ligne modifiée (affected rows, `Mysql.php:377`) → envoi de l'email §6.2 puis alerte success `Le compte a été modifié` (`:117`). Si 0 ligne modifiée (aucun changement) : ni message ni email.
  - Pas de redirection après POST : la vue est réaffichée avec les données relues (`:122`).

## 4. Actions AJAX / JSON

Toutes : `POST /index.php/json/{Action}/` (`global.js:1100`, `:1139`, `:1167`, `:1195`), corps `application/x-www-form-urlencoded`. Réponse : `json_encode($callback)` brut, ou `{callback}({json});` si `?callback=` est passé en GET (`json.php:1597-1605`), sans en-tête `Content-Type` explicite (gabarit `template/json.php`). Sans POST : réponse vide. Le JS évalue la réponse avec `eval` (`global.js:1103`).

### 4.1 `AddPromoteur` (`json.php:840-887`)

- Paramètres : `f_add_promoteur_pseudo`, `f_add_promoteur_name`, `f_add_promoteur_pwd`, `f_add_promoteur_email` (tous obligatoires), `f_add_promoteur_statut` (`0`|`1`, non validé).
- Réponses :
  - un champ obligatoire vide → `{"errors":0,"message":"Merci de renseigner tous les champs obligatoires"}` (`:849-851`) ; JS affiche `<div class="error">Tous les champs sont obligatoires</div>` (`global.js:1106-1107`) ;
  - email invalide → `{"errors":2,"message":"Adresse email non valide"}` (`:852-854`) ; JS : `<div class="error">L'adresse E-mail saisie n'est pas une adresse valide</div>` (`global.js:1108-1109`) ;
  - succès (insertion du privilège = 1 ligne) → email §6.1 puis `{"errors":1,"message":"Création réussie"}` (`:881-882`) ; JS : `<div class="success">Le compte a bien été ajouté. Vous pouvez à présent affecter les autorisations d'accès aux différents projets en cliquant <a href="/gestion_privilege">ici</a>.</div>` (`global.js:1104-1105`) ; la liste n'est pas rechargée ;
  - échec d'insertion → `$callback` non défini → `null` ; le JS n'affiche rien.
- Effets : `INSERT INTO user` puis `INSERT INTO privileges` (§5). Aucun contrôle d'unicité de l'identifiant ni de l'email.

### 4.2 `DeletePromoteur` = **désactivation** (`json.php:1027-1041`)

- Paramètre : `key` (id utilisateur). Confirmation JS « Confirmation de désactivation » / « Vous êtes sur le point de désactiver ce promoteur<br />Continuer ? » boutons Oui/Non (`global.js:1128-1154`).
- Effet : `UPDATE user SET statut= 0 WHERE id=…` (`model.php:1282-1290`).
- Réponse : `{"errors":1}` si 1 ligne modifiée, sinon `{"errors":0}`. Le JS recharge la page dans tous les cas (`global.js:1141-1143`).

### 4.3 `ActivPromoteur` = **réactivation** (`json.php:1059-1073`)

- Paramètre : `key`. Confirmation « Confirmation d'activation » / « Vous êtes sur le point d'activer ce promoteur<br />Continuer ? » (`global.js:1184-1210`).
- Effet : `UPDATE user SET statut=1 WHERE id=…` (`model.php:1302-1310`). Réponse et rechargement identiques à 4.2.

### 4.4 `TrashPromoteur` = **suppression définitive** (`json.php:1043-1057`)

- Paramètre : `key`. Confirmation « Confirmation de suppression » / « Vous êtes sur le point de supprimer ce promoteur<br />Continuer ? » (`global.js:1156-1182`).
- Effet : `DELETE FROM user WHERE id=…` (`model.php:1292-1300`). **Aucune suppression** dans `privileges`, `acces`, `acces_lot`, ni traitement des options, réservations, ventes, prospects, messages qui référencent l'utilisateur (orphelins mesurés en §5). Pas de corbeille réelle malgré l'icône.
- Réponse et rechargement identiques à 4.2.

Les noms sont trompeurs (`Delete` = désactiver, `Trash` = supprimer) : la refonte doit garder les URL legacy mais nommer clairement les méthodes.

### 4.5 Privilèges appelés depuis l'écran (recoupement `auth-users-privileges`)

La modale `#privileges` de `V_Gestion_promoteurs.php:133-198` (programmes non archivés avec vignette, case `projet[]`, sous-tableau des lots `lot[]` avec `numero`/`type`, liens « TOUT COCHER » / « DÉCOCHER », champ caché `user`) appelle `GetUserPrivilege` et `GetUserPrivilegeLot` à l'ouverture (`global.js:1241-1281`) et `UpdateUserPrivilege` au clic « Valider » (`global.js:1212-1227`, message `Les privilèges de l'utilisateur ont bien été modifiés`). Code serveur : `json.php:1075-1168`, `model.php:1312-1366`. `UpdateUserPrivilege` envoie un email contenant `pwdshow` (`json.php:1099-1125`). À contractualiser dans `auth-users-privileges` ; la refonte de `promoteurs` doit réutiliser le même composant/endpoint et la même modale.

## 5. Données

### Tables (schéma local, toutes MyISAM, latin1)

- `user` : `id` int AI PK, `pseudo` varchar(100), `name` varchar(250), `password` varchar(250), `email` varchar(250), `question` varchar(250), `reponse` varchar(250), `statut` int défaut 1, `pwdshow` varchar(150), tous NOT NULL. Aucun index unique sur `pseudo` ni `email`.
- `privileges` : `iduser` int (UNIQUE), `acces` int. Relation 1-1 avec `user`, sans FK.
- `acces` : `iduser`, `idprojet` (lien compte ↔ programme `projet.projet_id`), sans clé.
- `acces_lot` : `iduser`, `idlot` (lien compte ↔ `appartements.id`), UNIQUE (`iduser`,`idlot`).

### Comptages (base locale, snapshot prod)

- 242 comptes : `statut=1` : 99, `statut=0` : 143. `privileges.acces` : `1` : 1, `0` : 264. Tous les comptes ont un privilège.
- Orphelins dus à `TrashPromoteur` : 23 lignes `privileges`, 30 `acces`, 956 `acces_lot` sans `user` ; références à des `iduser` supprimés : `options` 185, `prospects` 833, `r_prospects` 250, `reservations` 39, `ventes` 68, `mp.idemetteur` 1376 (+ 7 à 0).
- 13 identifiants (`pseudo`) et 3 emails en doublon. `pwdshow` vide pour 119 comptes, rempli pour 123. `question` vide pour tous. 1 compte contient des entités HTML dans `pseudo`/`name`.

### Requêtes d'origine

- Liste : `SELECT user.*, privileges.acces FROM user INNER JOIN privileges ON user.id = privileges.iduser ORDER BY name ASC` (`model.php:1493`) ; variantes `WHERE user.statut = 1` (`:1499`) / `= 0` (`:1505`) ; par id `WHERE user.id = $id` (`:1563`).
- Filtre programmes (`model.php:1510-1533`) : `… INNER JOIN acces ON user.id = acces.iduser WHERE acces.idprojet = A {AND|OR} acces.idprojet = B … ORDER BY name ASC` (ids castés en int, `AND` si `type === 1`, `OR` sinon).
- Création (`model.php:1264-1280`) : `INSERT INTO user VALUES (NULL, '$pseudo','$name','$password','$email','','',1,'$pwd')` puis `INSERT INTO privileges VALUES ($id,$type)`. Retour = nb de lignes de la 2ᵉ requête.
- Modification (`model.php:1535-1557`) : `UPDATE user SET pseudo = …, name = …, password = …, email = …, statut = …, pwdshow = … WHERE id=…`.
- Désactivation / activation / suppression : `model.php:1286`, `:1306`, `:1296` (cités en §4).

### Règles métier implicites

- Hash : `sha256(HASH . htmlspecialchars(pwd, ENT_QUOTES))` à la création (`model.php:1268`, `normalize` `:11-14`) et à la modification (`model.php:1541`, `:1546`, `escape` `:16-19`), identique au contrôle de connexion (`model.php:1627-1628`). Recoupement `auth-users-privileges` (migration vers `password_hash`).
- `pwdshow` : mot de passe en clair ; à la création valeur brute (`model.php:1272`), à la modification valeur `htmlspecialchars` ou **chaîne vide si le mot de passe n'est pas changé** (`model.php:1541`, `:1551-1553`). Non repris dans la refonte (CLAUDE.md).
- À la création : `statut = 1`, `question = ''`, `reponse = ''` (`model.php:1272`).
- Champs texte à la création stockés **encodés HTML** (`escape` = `htmlspecialchars`, `model.php:1266-1270`) ; à la modification stockés **bruts** (`escapeSQL`, `model.php:1538-1540`). Les données en base mélangent donc les deux formes : la migration doit décoder les entités (`html_entity_decode`) avant de passer à l'échappement Blade.
- Le type de compte (`acces`) n'est fixé qu'à la création ; aucune interface du module ne le change.
- Les comparaisons de vue se font sur des chaînes (`=== '1'`, `V_Gestion_promoteurs.php:70`, `:82`, `V_Modifier_promoteur.php:43-44`) car le driver `mysql` renvoie des chaînes ; en Laravel, caster `statut` et `acces` en int et comparer en int.
- Un utilisateur sans ligne `privileges` n'apparaît dans aucune liste (INNER JOIN).

## 6. Emails

Expéditeur commun : `EMAIL` = `contact@corim-promotion.com`, nom `Corim promotion` (`config.php:143`). Destinataire : l'email saisi. Format : HTML + alternative texte `strip_tags($body)`. `BASE` = `https://corim-vente.com/` (`config.php:127`). La variable `$sujet` déclarée dans les deux cas n'est pas utilisée : le sujet réel est celui de `setSubject`.

### 6.1 Ouverture de compte (AddPromoteur)

- Déclencheur : création réussie (`json.php:858-880`). Envoyé avant la réponse JSON ; un échec d'envoi n'est pas géré.
- Transport : **Swift_SmtpTransport** SSL (`EMAIL_SMTP`, `EMAIL_PORT`, `EMAIL_USER`, `EMAIL_PASS`, `json.php:862-864`), pas MailTransport (ligne commentée `:861`).
- Sujet exact : `[Corim promotion] - Votre accès à la plateforme` (`json.php:875`).
- Corps exact (`json.php:866-873`) :

```html
<table width="500"><tr><td align="left"><img src="https://corim-vente.com/medias/images/logo-corim.png" /></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td>Bonjour,<br /><br />Votre accès à la plateforme Corim Promotion a été enregistré.<br /><br />Voici votre identifiant et votre mot de passe vous permettant de vous connecter :<br /><br />Identifiant : {pseudo}<br />Mot de passe : {mot de passe en clair}<br /><br />Pour y accéder, vous pouvez cliquer <a href="https://corim-vente.com/">ici</a></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td align="left">Immeuble "Le Cristal"<br />1475 av.Albert Einstein<br />34000  MONTPELLIER<br />Tél : 04 99 58 86 86<br />Mob : 06 12 36 06 94</td></tr></table>
```

### 6.2 Modification de compte (gestion_promoteurs/modifier)

- Déclencheur : POST valide de `modifier` modifiant exactement 1 ligne (`gestion_promoteurs.php:96-116`).
- Transport : **Swift_MailTransport** (fonction `mail()` PHP, `gestion_promoteurs.php:99`).
- Sujet exact : `[Corim promotion] - Votre accès à la plateforme CORIM` (`gestion_promoteurs.php:110`).
- Corps exact (`gestion_promoteurs.php:101-108`) :

```html
<table width="500"><tr><td align="left"><img src="https://corim-vente.com/medias/images/logo-corim.png" /></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td>Bonjour,<br /><br />Votre accès à la plateforme Corim Promotion a été modifié.<br /><br />Voici votre identifiant et votre mot de passe vous permettant de vous connecter :<br /><br />Identifiant : {pseudo}<br />Mot de passe : {mot de passe saisi, vide si inchangé}<br /><br />Pour y accéder, vous pouvez cliquer <a href="https://corim-vente.com/">ici</a></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td align="left">Immeuble "Le Cristal"<br />1475 av.Albert Einstein<br />34000  MONTPELLIER<br />Tél : 04 99 58 86 86<br />Fax : 04 67 60 40 77</td></tr></table>
```

Différences avec 6.1 : « enregistré » → « modifié », sujet suffixé `CORIM`, pied `Fax : 04 67 60 40 77` au lieu de `Mob : 06 12 36 06 94`, transport `mail()` au lieu de SMTP. Les valeurs `{pseudo}` et mot de passe sont concaténées sans échappement HTML.

Emails voisins hors module (recoupement) : `UpdateUserPrivilege` (`json.php:1104-1125`, sujet `[Corim Promotion] - Votre accès à la plateforme CORIM`, envoie `pwdshow`) et `NewAccountSetting` (`json.php:1452-1472`, sujet `[Corim promotion] - Vos nouveaux paramètres d'accès`) → `auth-users-privileges`.

Refonte : Mailables Laravel avec le même HTML ; décision à prendre sur l'envoi du mot de passe en clair (§11). Dev : mailer `log`.

## 7. Fichiers

Aucun upload ni téléchargement. Lecture seule des vignettes de programme pour la modale privilèges : `list_dir($_SERVER["DOCUMENT_ROOT"].MEDIAS.'programmes/{projet_titre_key}', 0, 'thumb', key)` concatène les noms de **tous** les fichiers du dossier `thumb/` pour former un seul `src` (`V_Gestion_promoteurs.php:3-21`, `:150-153`). Refonte : prendre le premier fichier image de `medias/programmes/{key}/thumb/` (même rendu tant qu'il n'y a qu'un fichier, voir §11).

## 8. Design

- Vues : `OLD/application/views/V_Gestion_promoteurs.php`, `OLD/application/views/V_Modifier_promoteur.php`, gabarit `OLD/application/views/template/template.php` (menu admin `:92-101`).
- CSS/JS chargés par le gabarit : Bootstrap 3 (classes `page-header`, `btn-*`, `label-*`, `glyphicon`, `modal`), `medias/tablesorter/js/jquery.tablesorter.combined.js` + `medias/tablesorter/css/theme.green.css` (`template.php:27-28`), `medias/colorbox/` (`:29-30`), `medias/confirm/jquery.confirm.{css,js}` (`:67-68`), bootstrap-select 1.13.14 depuis jsDelivr (`:12`, `:22-23`), `medias/js/global.js`.
- Tri/filtre de tableau : tablesorter avec widgets `zebra` et `filter` (placeholder `Recherche...`, filtres par colonne, sauvegarde des filtres) (`global.js:6-18`).
- Remplacements prévus (CLAUDE.md) : colorbox (modale d'ajout) et `$.confirm` (3 confirmations) → `<dialog>` avec les mêmes titres, messages et boutons Oui/Non ; tablesorter → petit tri + filtre par colonne en JS natif, même thème vert ; `eval` des réponses → `JSON.parse`/`fetch`. La modale privilèges reste une modale Bootstrap. bootstrap-select : à servir en local (pas de CDN) ou à garder, sans changement visuel.

## 9. Bugs et failles constatés

| Problème | Fichier:ligne | Décision proposée (reproduire / corriger) |
|---|---|---|
| Actions JSON `AddPromoteur`, `DeletePromoteur`, `TrashPromoteur`, `ActivPromoteur` sans aucune authentification : un anonyme peut créer un compte **administrateur**, désactiver, réactiver ou supprimer n'importe quel compte | `json.php:840-887`, `:1027-1073` | Corriger : auth + admin + CSRF |
| `isAdmin` échoue → `header(Location)` sans `exit` : un commercial reçoit la liste complète (noms, emails, identifiants) et peut POSTer `modifier` (changer email/mot de passe de l'admin → élévation de privilèges) | `gestion_promoteurs.php:10-12`, `:27-29`, `:55-57`, `:72-74` | Corriger : abort/redirect bloquant |
| Injection SQL à la création : mot de passe brut dans `pwdshow`, `$type` non quoté (`htmlspecialchars` n'échappe pas `\` ni les virgules/parenthèses) ; combinée à l'absence d'auth = injection SQL anonyme | `model.php:1266-1276` | Corriger : query builder |
| Hash du mot de passe exposé dans le HTML (`hidden_pwd`) et réinjecté tel quel si champ vide : un client peut fixer n'importe quel hash | `V_Modifier_promoteur.php:51`, `model.php:1543-1549` | Corriger : ne jamais exposer, conserver le hash côté serveur |
| Mots de passe en clair : `pwdshow` stocké, envoyés par email | `model.php:1272`, `:1553`, `json.php:869`, `gestion_promoteurs.php:104` | Corriger (`pwdshow` supprimé, CLAUDE.md) ; contenu email : voir §11 |
| XSS stockée : champs écrits bruts par `modifier` puis affichés sans échappement (liste et formulaire) | `model.php:1538-1540`, `V_Gestion_promoteurs.php:79-81`, `V_Modifier_promoteur.php:3`, `:23`, `:27`, `:31` | Corriger : échappement Blade, données décodées à la migration |
| XSS réfléchie via `?callback=` (JSONP non filtré, servi en text/html) | `json.php:1600-1601` | Corriger : réponse JSON simple, `Content-Type: application/json`, paramètre ignoré |
| Pas de CSRF sur les formulaires et actions | `V_Gestion_promoteurs.php:32`, `:103`, `V_Modifier_promoteur.php:19`, `global.js:1096-1210` | Corriger |
| Suppression définitive sans nettoyage : orphelins dans `privileges`, `acces`, `acces_lot` et historique commercial (options, réservations, ventes, prospects, messages) | `model.php:1296`, §5 comptages | Corriger : FK + suppression des droits ; historique : voir §11 |
| Pas d'unicité de `pseudo` (13 doublons) ni d'email | `json.php:849-857`, schéma `user` | Corriger : validation `unique` (après traitement des doublons existants, §11) |
| `$type` non validé : un `acces` hors 0/1 fait afficher le type de la ligne précédente (variable `$type` non réinitialisée) ou une notice | `json.php:848`, `V_Gestion_promoteurs.php:82-84` | Corriger : `in:0,1` |
| Statut non validé à la modification (le message le dit obligatoire mais il n'est pas contrôlé) | `gestion_promoteurs.php:88-90` | Corriger : `required|in:0,1` |
| Filtre « tous les programmes » : `AND` sur la même ligne de jointure → aucun résultat dès 2 programmes ; « au moins un » : doublons (pas de DISTINCT) | `model.php:1514-1529` | Corriger : comportement voulu (`HAVING COUNT(DISTINCT idprojet) = n` / `DISTINCT`) |
| Modification sans changement → 0 ligne affectée → ni message ni email | `gestion_promoteurs.php:95-118`, `Mysql.php:377` | Corriger : message `Le compte a été modifié` affiché ; email : voir §11 |
| Mot de passe inchangé → `pwdshow` vidé et email « Mot de passe : » vide | `model.php:1551-1553`, `gestion_promoteurs.php:104` | Sans objet (pwdshow supprimé) ; email : voir §11 |
| Faute dans le message : `l\e-mail` (antislash affiché) | `gestion_promoteurs.php:93` | Corriger en `l'e-mail` (à valider) |
| Échec d'insertion → réponse `null`, aucun message | `json.php:857-885` | Corriger : message d'erreur générique |
| `list_dir` : appel `$this->list_dir` hors classe (fatal si branche atteinte), concatène tous les fichiers dans un seul `src`, warning si dossier absent | `V_Gestion_promoteurs.php:3-21` | Corriger (premier fichier image) |
| Deux éléments `id="call"` dans la même page | `V_Gestion_promoteurs.php:102`, `:143` | Corriger sans changement visuel |
| Désactivation/suppression n'invalident pas la session en cours | `__main.php:46-53` | Corriger (recoupement auth) |
| Session en cours inchangée si l'admin se désactive ou se supprime lui-même ; aucune protection du dernier administrateur | `json.php:1027-1057` | Corriger : interdire sur soi-même (à valider, §11) |
| Identifiants MySQL en dur dans le modèle | `model.php:8` | Corriger (recoupement, `.env`) |
| `isConnected` de `__json.php` lit `VBSessionId`, clé jamais posée | `__json.php:17-24`, `connexion.php:26` | Sans objet |

## 10. Critères d'acceptation

1. Anonyme : `GET /gestion_promoteurs`, `/actifs`, `/inactifs`, `/modifier/{id}` → redirection `/connexion`.
2. Commercial connecté : mêmes URL → redirection `/`, sans aucune donnée de compte dans le corps ; `POST /gestion_promoteurs/modifier/{id}` ne modifie rien.
3. Anonyme ou commercial : `POST /index.php/json/AddPromoteur/`, `DeletePromoteur`, `TrashPromoteur`, `ActivPromoteur` → refus (403 ou redirection), aucune écriture en base.
4. Admin : `GET /gestion_promoteurs` affiche tous les comptes ayant un privilège, triés par nom, avec les colonnes NOM, EMAIL, IDENTIFIANT, STATUT, ACTIF et le `<h1>` `Gestion des prescripteurs`.
5. STATUT affiche `Administrateur` pour `acces=1`, `Commercial` pour `acces=0` ; ACTIF affiche `OUI` (label-success) pour `statut=1` et `NON` (label-danger) sinon.
6. Compte actif → bouton de désactivation ; compte inactif → bouton d'activation ; chaque ligne a les boutons modifier, privilèges et suppression.
7. `/gestion_promoteurs/actifs` (resp. `inactifs`) ne liste que `statut=1` (resp. `0`), `<h1>` suffixé `actifs` (resp. `inactifs`), sans formulaire de filtre.
8. Filtre `type=0` + `projects[]=A,B` → comptes ayant accès à A ou B, chacun une seule fois.
9. Filtre `type=1` + `projects[]=A,B` → comptes ayant accès à A et à B.
10. Les programmes du filtre sont les programmes non archivés, libellés en majuscules, ordre `projet_id DESC` ; la sélection postée est réaffichée.
11. AddPromoteur avec un champ obligatoire vide → `{"errors":0,"message":"Merci de renseigner tous les champs obligatoires"}`, rien en base.
12. AddPromoteur avec email invalide → `{"errors":2,"message":"Adresse email non valide"}`, rien en base.
13. AddPromoteur valide → `{"errors":1,"message":"Création réussie"}`, une ligne `user` (`statut=1`, mot de passe hashé, pas de clair) et une ligne `privileges` avec `acces` = type saisi.
14. AddPromoteur valide → un email au destinataire saisi, expéditeur `contact@corim-promotion.com` / `Corim promotion`, sujet `[Corim promotion] - Votre accès à la plateforme`, corps conforme à §6.1.
15. AddPromoteur avec type hors `0|1` → refus de validation.
16. AddPromoteur avec identifiant déjà existant → refus de validation (si décision §11 validée).
17. Le compte créé peut se connecter avec le mot de passe saisi.
18. DeletePromoteur `key=id` → `statut=0`, réponse `{"errors":1}` ; le compte ne peut plus se connecter ; id inexistant → `{"errors":0}`.
19. ActivPromoteur `key=id` → `statut=1`, réponse `{"errors":1}`.
20. TrashPromoteur `key=id` → ligne `user` supprimée ainsi que ses lignes `privileges`, `acces`, `acces_lot`, réponse `{"errors":1}`.
21. Les trois confirmations (désactivation, activation, suppression) affichent exactement les titres et messages de §4.2-4.4 avec boutons Oui/Non ; Oui recharge la page.
22. GET `modifier/{id}` : formulaire pré-rempli (identifiant, nom, email, statut), champ mot de passe vide, **aucun hash dans le HTML**.
23. POST `modifier` avec identifiant, nom ou email vide → alerte `Attention, les champs Identifiant, Nom, E-mail et  Statut sont obligatoires`, rien en base.
24. POST `modifier` avec email invalide → alerte d'email invalide (texte selon décision §9), rien en base.
25. POST `modifier` valide sans mot de passe → hash inchangé, autres champs mis à jour, alerte `Le compte a été modifié`.
26. POST `modifier` valide avec mot de passe → nouveau hash, connexion possible avec le nouveau mot de passe.
27. POST `modifier` valide → email sujet `[Corim promotion] - Votre accès à la plateforme CORIM`, corps conforme à §6.2 (contenu du mot de passe selon décision §11).
28. Un nom contenant `<script>` saisi via `modifier` est affiché échappé dans la liste et dans le formulaire.
29. `?callback=x` sur une action JSON ne produit pas de JSONP ; réponse `application/json`.
30. Toute requête POST du module sans jeton CSRF valide est rejetée (419).
31. La modale privilèges s'ouvre depuis la liste et coche les programmes/lots de l'utilisateur (tests détaillés dans `auth-users-privileges`).
32. Rendu Blade de la liste et du formulaire identique au markup legacy (classes, libellés, icônes) pour le rôle administrateur.

## 11. Questions ouvertes

1. Emails : continuer à envoyer le mot de passe en clair (création, modification) ou le remplacer par un lien signé de définition du mot de passe ? Le clair est incompatible avec la suppression de `pwdshow` pour l'email de privilèges, mais reste possible à la création/modification (mot de passe saisi par l'admin).
2. Email de modification quand le mot de passe n'est pas changé : l'envoyer quand même (avec « Mot de passe : » vide, comme aujourd'hui) ou le supprimer / omettre la ligne ?
3. Pieds d'email différents (`Mob : 06 12 36 06 94` vs `Fax : 04 67 60 40 77`) : garder chacun tel quel ou harmoniser ? Ces coordonnées sont-elles toujours valides ?
4. Suppression définitive : que faire de l'historique (options, réservations, ventes, prospects, messages) qui référence l'utilisateur ? Proposition : interdire la suppression d'un compte ayant un historique (désactivation seulement) ou soft delete. Que faire des orphelins existants à la migration (FK impossibles sans traitement) ?
5. Doublons existants (13 identifiants, 3 emails) : comment les dédoublonner avant d'imposer l'unicité ? Lequel se connecte aujourd'hui (recoupement `auth-users-privileges`) ?
6. Les pages `/actifs` et `/inactifs` (liens commentés) sont-elles encore utilisées ? Les garder comme routes cachées ou les rétablir en boutons ?
7. Faut-il pouvoir changer le type de compte (Commercial ↔ Administrateur) après création ? Aujourd'hui impossible dans l'interface.
8. Autoriser un administrateur à se désactiver / se supprimer lui-même, et à supprimer le dernier administrateur (1 seul `acces=1` dans le snapshot) ?
9. Le filtre « tous les programmes » ne marche pas aujourd'hui au-delà d'un programme : confirmer que le comportement voulu est l'intersection.
10. Après création, le message renvoie vers `/gestion_privilege` alors que la modale privilèges est disponible sur la même page : garder ce lien ?
11. Vignettes : le dossier `thumb/` d'un programme peut-il contenir plusieurs fichiers ? (Le rendu actuel serait alors une image cassée.)
