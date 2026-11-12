# Contrat — public

Statut : brouillon (à valider par Victor)
Sources lues :
- `OLD/application/controllers/__main.php:1-116`, `__json.php:1-48`, `welcome.php:1-15`, `rechercher.php:1-37`, `search.php:1-145` (pas de sortie HTML, voir 3.9), `app.php:1-11`, `cgu.php:1-16`, `mentions_legales.php:1-16`, `administration.php:1-111`, `connexion.php:6-45` (valeurs de session seulement), `json.php:1505-1517` (ShowToolbar/HideToolbar), `firstsearch.php:89-110` (branche `app`)
- `OLD/application/views/template/template.php:1-137`, `template_welcome.php:1-96`, `header.php:1-6`, `toolbar.php:1-45`, `module_programmes.php:1-36`, `module_dashboard.php:1-94`, `json.php:1-2`, `connexion.php:1-30` (pour l'inventaire des assets)
- `OLD/application/views/welcome_content.php`, `V_app.php:1-117`, `V_Cgu.php:1-2`, `V_Mentions.php:1-89`, `V_Json.php:1`, `admin/welcome.php`, `admin/V_Ajouter_Appartement.php`, `admin/V_Ajouter_Projet.php`
- `OLD/application/models/model.php:11-23, 1153-1260, 1624-1636, 1652-1700, 1848-1910, 1912-1955`
- `OLD/application/config/config.php:8-128` (sans les identifiants), `session.php:7-50`, `OLD/system/config/routes.php:7`, `OLD/system/core/Kohana.php:152, 225-300, 839`, `OLD/system/controllers/template.php:17-54`, `OLD/system/libraries/Controller.php:45-49`
- `OLD/.htaccess`, `OLD/index.php:2-3, 34, 112, 124`, `OLD/404.html`, `OLD/maintenance.html`, `OLD/robots.txt`
- `OLD/medias/js/global.js` (lignes citées), `OLD/medias/js/app.js:1-89`, `OLD/medias/css/*.css`, en-têtes de version de toutes les librairies de `OLD/medias`, `OLD/bootstrap`, `OLD/font-awesome-4.5.0`
- `OLD/application/logs/2023-09-04.log.php` (erreurs « vue rechercher »)
- Base locale (SELECT/DESCRIBE seulement) : `user`, `privileges`, `projet`, `acces`, `mp`, `villes`, `appartements`, `news`, `emailing`, `sessions`

## 1. Périmètre

Le module couvre :
- **Le gabarit commun** (layout HTML, barre de navigation Bootstrap avec menu par rôle et badge de messages non lus, pied de page), utilisé par toutes les pages des contrôleurs qui étendent `__Main_Controller` (36 contrôleurs). Le gabarit est choisi selon le premier segment d'URL (`__main.php:19-39`) : `template/template_welcome` pour `/` et `/welcome`, `template/template` partout ailleurs. Les contrôleurs `__Json_Controller` (`app`, `firstsearch`, `search`, `json`) utilisent `template/json` (`__json.php:12`), qui n'affiche que le contenu.
- **La page d'accueil / tableau de bord** (`/`) : réservée aux utilisateurs connectés. Colonne gauche : vignettes des programmes accessibles. Colonne droite : compteurs (lots actés, demandes d'options, options, demandes de réservations, réservations, demandes de dénonciations, dénonciations, lien Rapport pour l'admin). Le contenu propre (`welcome_content.php`) est vide.
- **Les pages CGU et mentions légales** (texte statique, connexion requise).
- **La recherche « par résidence / par lot »** (`/rechercher...`) : cassée en production (la vue n'existe pas), lien retiré du menu.
- **La page `/app`** (« DISPONIBILITES ») : formulaire public de recherche de lots par typologie et budget qui appelle `firstsearch?action=app` en JSONP ; non fonctionnelle aujourd'hui.
- **Les pages d'erreur** : 404 (page d'erreur Kohana, `404.html` statique non branché) et maintenance (`maintenance.html`, activée à la main dans `index.php`).
- **Le contrôleur `administration`** (vues `admin/`) : code mort accessible sans authentification.
- **L'inventaire complet des assets front** du site (section 8).

Hors périmètre : connexion/déconnexion et mot de passe oublié (`auth-users-privileges`), JSONP de `search` et `firstsearch` (`public-api`), pages vers lesquelles mènent le menu et le tableau de bord (autres modules).

## 2. Rôles et droits

Le rôle vient de `privileges.acces` (int) lu à la connexion (`model.php:1630-1634`) et stocké en session `SessionAcces` (`connexion.php:29`). Le driver MySQL de Kohana renvoie des chaînes : les comparaisons strictes `==='1'` / `==='0'` fonctionnent sur la chaîne. Base locale : `acces=1` → 1 utilisateur, `acces=0` → 264 utilisateurs ; seuls les `user.statut=1` peuvent se connecter (`model.php:1634`).

| Rôle (valeur en base) | Peut faire | Contrôle dans l'ancien code (fichier:ligne) |
|---|---|---|
| Visiteur non connecté | `/app`, `/administration/*` (aucun contrôle), pages d'erreur, maintenance | aucun contrôle : `app.php:7-9`, `administration.php:7-103` |
| Visiteur non connecté | sur `/`, `/welcome`, `/cgu`, `/mentions_legales`, `/rechercher*` : redirigé vers `/connexion` | `__main.php:46-53` (`isConnected`, `header Location` + `exit`) appelé par `__main.php:28`, `welcome.php:10`, `cgu.php:9`, `mentions_legales.php:9`, `rechercher.php:10,24,32` |
| Connecté (`SessionId` non vide) | accueil, CGU, mentions | `__main.php:46-53` |
| Administrateur (`privileges.acces = 1`) | menu complet (Programmes, Clients, Prospects, Prescripteurs, Privilèges, Emailing, Emails, Flash info), vignettes de tous les programmes non archivés, compteurs globaux, lien RAPPORT | `template.php:92`, `template_welcome.php:43`, `__main.php:22-23`, `module_dashboard.php:89`, `isAdmin` `__main.php:55-61` |
| Prescripteur / commercial (`privileges.acces = 0`) | menu réduit, vignettes des programmes liés dans `acces`, compteurs filtrés sur son id | `template.php:105-108`, `__main.php:24-25`, filtres `WHERE iduser=` dans `model.php:1153-1260` |
| Session sans `SessionAcces` `'0'` ni `'1'` | branche « non admin » du menu ; aucune vignette (variable `programmes` non définie) | `template.php:92,105`, `__main.php:22-26` |

## 3. Écrans et URL

Routage Kohana 2.3 : `/<contrôleur>/<méthode>/<args>` ; route par défaut `welcome` (`system/config/routes.php:7`) ; toute URL non fichier est réécrite en `index.php?kohana_uri=...` (`.htaccess`), et `/index.php/<uri>` fonctionne aussi (`config.php:22`). Les liens internes sont construits avec `SITE_URL` = `/` (`config.php:8,128`). HTTP est redirigé en HTTPS par `.htaccess` (`RewriteCond %{HTTPS} off`, code 302 par défaut). Les dossiers `application|modules|system` répondent 403 (`.htaccess`). Les fichiers `*.csv` sont interdits (`.htaccess`, `<Files *.csv>`). Le listing de dossiers est désactivé (`Options -Indexes`).

Tout contrôleur Kohana expose ses méthodes publiques en URL, sauf celles qui commencent par `_` (`system/core/Kohana.php:258-270`). Voir 3.10.

### 3.1 Accueil / tableau de bord — `GET /`, `GET /welcome`, `GET /welcome/index`
- Paramètres : aucun.
- Rôle : connecté. Non connecté → `302 Location: /connexion` (`__main.php:28` via `isConnected`, avant même `welcome.php:10`).
- Contrôleur : `welcome.php:8-14` ; gabarit `template/template_welcome` (`__main.php:19-20`).
- Titre : `Corim promotion - Tableau de bord` (`__main.php:43` + `welcome.php:12`).
- Vue de contenu : `welcome_content.php` (vide, seulement la garde `SYSPATH`).
- Blocs : `module_programmes` (colonne `col-xs-6 col-sm-5 col-md-3`) et `module_dashboard` (colonne `col-xs-6 col-sm-7 col-md-9`) affichés si `Connected()` (`template_welcome.php:77-82`).
- `module_programmes.php:23-34` : pour chaque programme, `<div class="item_programme">` avec vignette `<a href="/programme/show/{projet_titre_key}" class="vignette"><img src="..." alt="vignette" width="120" class="img-thumbnail" /></a>` puis bouton `<a class="btn btn-info btn-xs btn-block" href="/programme/show/{key}">{projet_titre}<span>{projet_lieu}</span></a>`. L'URL de l'image est la concaténation de tous les fichiers du dossier `medias/programmes/{key}/thumb/` (`module_programmes.php:4-22`), soit en pratique le seul fichier présent (31 dossiers locaux, 1 fichier chacun). `projet_titre` et `projet_lieu` sont affichés sans échappement.
- `module_dashboard.php:60-94` : `div.list-group` de 7 liens `a.list-group-item` avec `span.badge` = compteur et libellés exacts `LOTS ACT&Eacute;`, `DEMANDES D'OPTIONS`, `OPTIONS`, `DEMANDES DE R&Eacute;SERVATIONS`, `R&Eacute;SERVATIONS`, `DEMANDES DE D&Eacute;NONCIATIONS`, `D&Eacute;NONCIATIONS`, puis `RAPPORT` (admin seulement, lien `/report`). Lien = page cible si compteur > 0, sinon `javascript:void(0)` (`module_dashboard.php:3-58`). Les trois « demandes » ajoutent la classe `list-group-item-warning` et l'icône `glyphicon glyphicon-exclamation-sign` si compteur > 0 (`module_dashboard.php:65-67, 73-75, 81-83`). Cibles : `/ventes`, `/ask_options`, `/options`, `/ask_reservations`, `/reservations`, `/ask_denonciations`, `/denonciations`.

### 3.2 CGU — `GET /cgu`
- Rôle : connecté (`cgu.php:9`). Gabarit `template/template`.
- Titre : `Conditions générales de vente` (`cgu.php:11`, remplace le préfixe).
- Contenu exact (`V_Cgu.php:2`) : `<h3>.Conditions générales d'utilisation</h3><br />` (point initial inclus, aucun texte).

### 3.3 Mentions légales — `GET /mentions_legales`
- Rôle : connecté (`mentions_legales.php:9`). Gabarit `template/template`.
- Titre : chaîne vide (`mentions_legales.php:11`) → `<title></title>`.
- Contenu : texte statique `V_Mentions.php:2-89`, variables PHP en dur `V_Mentions.php:5-13` (nom, adresse, CP, ville, capital `XXXXXX`, RCS, téléphone, directeur de publication, site), hébergeur « Bodéva Communication » (`V_Mentions.php:22-25`), sections 1 à 5 en `<h2>`/`<h3>` avec `<br />` en dur. À recopier tel quel (voir questions).

### 3.4 Recherche — `GET /rechercher`, `GET /rechercher/par_residence`, `GET /rechercher/par_lot`
- Rôle : connecté (`rechercher.php:10,24,32`).
- `index` charge `Get_AllDept()` et `Get_AllType()` (`rechercher.php:15-19`), puis toutes les actions instancient `new View('rechercher')` (`rechercher.php:11,25,33`). **Ce fichier de vue n'existe pas** dans `OLD/application/views/` : Kohana lève « La ressource vue, rechercher, n'a pas été trouvée » (constaté dans `application/logs/2023-09-04.log.php`), page d'erreur 500.
- CSS spécifique prévu : `recherche.css` (`template.php:38-40`).
- Le lien « Rechercher » de la barre d'outils est commenté (`toolbar.php:20-23`).
- Les fonctions JS associées `Recherche()` / `Recherche_Ville()` (`global.js:1707-1762`) postent vers `json/Recherche` et `json/Recherche_Ville` sur un `#form-recherche` qui n'existe dans aucune vue.

### 3.5 Réécritures `rechercher-par-residence` / `rechercher-par-lot`
- `.htaccess` (après la règle générale) : `RewriteRule ^(.+)rechercher-par-residence$ index.php/rechercher/par_residence [L]` et idem `par-lot`.
- Ces règles sont placées après la règle générale `^(.+)$ → index.php?kohana_uri=$1 [L]`, qui les capte toujours en premier : elles ne s'appliquent jamais. Une URL `/x/rechercher-par-residence` aboutit à une 404 Kohana. Elles exigent en plus au moins un caractère avant le motif (`(.+)`).

### 3.6 App « DISPONIBILITES » — `GET /app`
- Rôle : aucun (`app.php:3-9`, `__Json_Controller` sans appel à `isConnected`).
- Gabarit `template/json` (affiche seulement `$content`) ; la vue `V_app.php` est une page HTML complète (`<title>DISPONIBILITES</title>`, `V_app.php:8`).
- Formulaire `#form-special` (`V_app.php:27-96`) : cases `typologie[]` T1 à T6 (`awesome-bootstrap-checkbox`), select `budget` (0 = « Tous critères », 1 = « - de 150 000 € », 2 = « 150 000 à 200 000 € », 3 = « 200 000 à 250 000 € », 4 = « 250 000 à 300 000 € », 5 = « + 300 000 € »), champ caché `action=app`, champ caché `projet`. Tableau résultat `#result` colonnes TYPE, REF, SUPERFICIE, PRIX, EXPOSITION, PLAN (`V_app.php:98-113`), caché par défaut (`hide`).
- JS `app.js` : paramètre d'URL `?project=` pré-remplit `#projet` et soumet (`app.js:1, 35-38`) ; soumission → JSONP `GET http://corim-vente.com/index.php/firstsearch` avec callback `MyFirstCallback` (`app.js:16-24`) ; rendu des lignes avec lien plan `http://corim-vente.com/medias/programmes/{projet_titre_key}/plans/plan-{numero sans espaces}.pdf` (`app.js:57-65`) ; prix suffixé ` €`, superficie ` m²` ; erreur → `<div class="alert alert-warning">{data.errors}</div>` (`app.js:73`).
- État actuel : non fonctionnel. (1) `firstsearch` branche `app` fait `var_dump` puis `die()` (`firstsearch.php:99-102`) ; (2) la page HTTPS charge `app.js`, `app.css`, `awesome-bootstrap-checkbox.css`, Questrial et jquery-migrate en `http://` (`V_app.php:10,12,13,15,17`), bloqués comme contenu mixte par les navigateurs. Comportement voulu d'après le code mort : `AppSearchProgram($typologie,$budget,$projet)` (`model.php:102`, `firstsearch.php:103-110`), réponse `{errors:1, program:[...]}` ou `{errors:"<b>Aucun résultat pour cette recherche.</b>"}`.

### 3.7 Page 404
- En production, toute URL inconnue déclenche `Event system.404` → `Kohana::show_404` (`system/core/Kohana.php:839`) → page d'erreur Kohana `system/views/kohana_error_page.php` avec message « La page demandée {uri} n'a pu être trouvée. », et détails techniques car `display_errors = TRUE` (`config.php:97`, `index.php:124`) et `IN_PRODUCTION = FALSE` (`index.php:34`).
- `OLD/404.html` (fond `#444`, `<h3>` 60px `#eee`, texte « 404, the file you requested does not exist! ») n'est référencé nulle part (aucun `ErrorDocument` dans `.htaccess`).

### 3.8 Maintenance
- `OLD/maintenance.html` : page autonome (titre « Maintenance en cours », texte « Nous sommes désolé pour le désagrément, nous effectuons actuellement des travaux de maintenance. », « — Corim Promotion », styles inline Helvetica 20px, lien `#dc8100`).
- Activation : décommenter `index.php:2-3` (`header("Location: https://corim-vente.com/maintenance.html"); die();`), redirection 302.

### 3.9 `search` (partie HTML)
- `search.php` n'a aucune sortie HTML : `index` et `news` écrivent uniquement du JSONP (`search.php:45,122,126-143`) ; la vue `V_Json` créée (`search.php:9,50`) est vide (`V_Json.php:1`) et jamais assignée. Tout le comportement relève du module `public-api`.

### 3.10 Méthodes publiques exposées par le routage
Toutes les méthodes publiques de `__Main_Controller` sont appelables sur chaque contrôleur qui en hérite (`Kohana.php:258-270`) :
- `GET /{ctrl}/shutdown` : détruit la session et redirige vers `/connexion` (`__main.php:79-82`) → déconnexion forcée par simple lien (CSRF).
- `GET /{ctrl}/isConnected`, `isAdmin`, `isDeconnected`, `Connected`, `ToolbarActif`, `KillThisFuckinApostrophe`, `Get_NumberOfMessage/{id}`, `Get_NumberOfMessageAdmin/{id}` (`__main.php:46-115`) : aucune sortie utile, rendu du gabarit ou erreur PHP selon les arguments.
- Idem pour `__Json_Controller` (`__json.php:17-47`) sur `app`, `search`, `firstsearch`, `json`.
Décision : ne pas reproduire.

### 3.11 Administration (code mort) — `GET|POST /administration`, `/administration/Ajouter_Appartement`
- Rôle : **aucun contrôle** (`administration.php:7-103`, pas d'appel à `isConnected`).
- `index` : vue `admin/welcome` (page XHTML vide « Document sans nom ») injectée dans `template/template`.
- `Ajouter_Appartement` GET : formulaire généré par `form::` (`administration.php:52-101`) affiché via `admin/V_Ajouter_Appartement.php:2-5`. POST : `Add_Appartement($_POST)` insère dans `appartements` chaque clé POST comme colonne (`model.php:1848-1882`), puis `Add_Ville` (`model.php:1905-1910`). Les champs obligatoires `nom` et `nombre_de_pieces` n'existent pas dans `appartements` : l'INSERT échoue toujours avec une page d'erreur affichant la requête.
- Toute autre méthode → texte « This text is generated by __call... » (`administration.php:106-110`).
- `admin/V_Ajouter_Projet.php` n'est utilisé par aucun contrôleur.
Décision : ne pas porter (404).

## 4. Actions AJAX / JSON

Seules les actions déclenchées par le gabarit sont décrites ici ; elles sont implémentées dans `json.php` (module `workflow`).

| Action | Déclencheur | Réponse | Effet |
|---|---|---|---|
| `GET /index.php/json/ShowToolbar/` | lien « Verrouiller les outils » (`template.php:115`, `template_welcome.php:66`) → `ShowToolbar()` `global.js:1764-1775` | `{"errors":0}` (`json.php:1505-1510`), puis `document.location.reload()` | `session SessionToolbar = '1'` |
| `GET /index.php/json/HideToolbar/` | lien « Déverrouiller les outils » (`template.php:114`, `template_welcome.php:65`) → `HideToolbar()` `global.js:1777-1788` | `{"errors":0}` (`json.php:1512-1517`), puis reload | supprime `SessionToolbar` |

Les deux liens ne sont affichés que si `SessionToolbar == "1"` (`__main.php:84-90`) : une fois masquée, la barre ne peut plus être réactivée depuis l'interface. Et la barre `toolbar.php` n'est jamais affichée (echo commenté, `template.php:74`, `template_welcome.php:26`). Aucun contrôle de connexion sur ces deux actions.

## 5. Données

Requêtes exécutées à chaque rendu du gabarit (tous les écrans `template`/`template_welcome` connectés) :
- Admin : `Get_NumberOfMessageAdmin(SessionId)` (`template.php:94-95`) → `model.php:1930-1955` : `SELECT COUNT(mp.sujet) AS nb FROM mp INNER JOIN user ON mp.idemetteur = user.id WHERE mp.idreceveur = $id AND mp.sujet != '...' (12 sujets automatiques exclus) AND mp.lu = 0`. Badge `<span class="label label-info">{nb}</span>` si > 0 (`template.php:111`).
- Non admin : `Get_NumberOfMessage(SessionId)` (`template.php:107`) → `model.php:1922-1928` : `mp` où `lu = 0 AND idreceveur = $id`, renvoie un entier (`count()`).
- Règle implicite : les sujets automatiques sont comparés en texte brut, mais 1 599 messages locaux ont le sujet `Vous avez une demande d&#039;option en attente` (encodé HTML), non exclu → comptés dans le badge admin.

Accueil (`__main.php:19-36`) :
- Admin : `GetProgrammes()` `model.php:1694-1699` : `SELECT projet.* FROM projet WHERE projet.archive=0 ORDER BY projet.projet_id DESC`.
- Prescripteur : `GetProgrammesByAccess(SessionId)` `model.php:1652-1662` : `SELECT acces.idprojet, projet.projet_id, projet_titre, projet_titre_key, projet_lieu, projet_image, projet_nb_appartements, projet.dept FROM acces INNER JOIN projet ON acces.idprojet = projet.projet_id WHERE acces.iduser=$iduser ORDER BY projet.projet_id DESC` — **pas de filtre `archive`** (localement 28 programmes archivés ont des lignes `acces`).
- Compteurs, `$user` = `SessionId`, `$access` = `SessionAcces`, filtre `iduser`/`vendeur` seulement si `$access==='0'` (`model.php:1153-1260`) :
  - `CountVentes` (1153) : `SELECT COUNT(id) as number FROM appartements WHERE [vendeur=$user AND] statut="act"`.
  - `CountAskOptions` (1210) : `COUNT(iddoption) FROM d_options [WHERE iduser=$user]`.
  - `CountOptions` (1219) : `COUNT(idoption) FROM options INNER JOIN projet ON options.idprojet = projet.projet_id INNER JOIN user ON options.iduser = user.id INNER JOIN appartements ON options.idlot = appartements.id WHERE [iduser=$user AND] options.statut = 1`.
  - `CountAskResas` (1236) : `COUNT(iddresa) FROM d_reservations [WHERE iduser=$user]`.
  - `CountResas` (1246) : comme `CountOptions` sur `reservations`, `reservations.statut = 1`.
  - `CountAskDenonciations` (1188) : `COUNT(idprospect) FROM d_prospects [WHERE iduser=$user]`.
  - `CountDenonciations` (1199) : `COUNT(idprospect) FROM prospects [WHERE iduser=$user]`.
- Valeurs magiques : `appartements.statut` `"act"` = acté, `''` = libre ; `options.statut`/`reservations.statut` `1` = actif ; `privileges.acces` `1`/`0`.

Recherche (`rechercher.php:16-17`) : `Get_AllDept` `model.php:1912-1915` (`SELECT DISTINCT dept FROM villes`, table vide en local) ; `Get_AllType` `model.php:1917-1920` (`SELECT DISTINCT type FROM appartements ORDER BY type ASC`, 10 valeurs en local).

Session (`config/session.php`) : driver `database` table `sessions` (7-15), cookie `kohanasession` (21), validation `user_agent` (26), pas de chiffrement (33), expiration 50 000 s (39), pas de régénération (45). Clés utilisées par le gabarit : `SessionId`, `SessionAcces`, `SessionName` (`toolbar.php:44`), `SessionToolbar`.

Aucune écriture en base dans ce module (hors `administration`, non porté).

## 6. Emails

Aucun email envoyé par les écrans de ce module.

## 7. Fichiers

- Lecture disque : `module_programmes.php:25-26` ouvre `$_SERVER["DOCUMENT_ROOT"]/medias/programmes/{projet_titre_key}/thumb/` à chaque affichage de l'accueil (`opendir`/`readdir`, ordre non garanti). Dans la copie locale, 6 des 8 programmes non archivés n'ont pas de dossier `thumb/`.
- URL publiques servies directement par Apache (fichiers existants, `.htaccess`) : `medias/**`, `bootstrap/**`, `font-awesome-4.5.0/**`, `images/**`, `favicon.ico`, `apple-touch-icon.png`, `robots.txt` (`User-agent: *` seul, sans `Disallow`), `404.html`, `maintenance.html`.
- Les plans PDF `medias/programmes/{key}/plans/plan-{numero}.pdf` sont liés depuis `/app` (`app.js:57`) ; leur stockage relève de `programmes-lots`.

## 8. Design

### 8.1 Gabarits et structure à recopier en Blade

Les vues Kohana sont rendues dans le scope du contrôleur (`$this->session`, `$this->uri`, `$this->Get_NumberOfMessage...` dans les vues). En Blade : un view composer partagé fournit `sessionAcces`, `messageCount`, `toolbarActif`, `segment1`.

Variables assignées par `__main.php:8-44` :
| Variable | Valeur | Source |
|---|---|---|
| `template` | `template/template_welcome` si segment 1 ∈ {`""`, `welcome`}, sinon `template/template` | `__main.php:19-20, 38` |
| `module_programmes` | vue `template/module_programmes` avec `programmes` (selon rôle) | `__main.php:21-26` (accueil seulement) |
| `module_dashboard` | vue `template/module_dashboard` avec `ventes`, `ask_options`, `options`, `ask_resas`, `resas`, `ask_denonciations`, `denonciations` (tableaux `[0]["number"]`) | `__main.php:27-36` (accueil seulement) |
| `toolbar` | vue `template/toolbar` (jamais affichée) | `__main.php:40` |
| `header` | vue `template/header` (jamais affichée) | `__main.php:41` |
| `content` | vue de page, `''` par défaut | `__main.php:42` |
| `title` | `'Corim promotion - '` par défaut, complété ou remplacé par chaque contrôleur, échappé `html::specialchars` | `__main.php:43`, `template.php:9` |

**`template/template.php`** (pages internes) :
1. `<!DOCTYPE html><html lang="en">`, metas `charset=utf-8`, `X-UA-Compatible IE=edge`, `viewport width=device-width, initial-scale=1` (`2-7`).
2. `<title>` (`9`).
3. Feuilles et scripts dans cet ordre exact (`11-32`) : Bootstrap 3.3.6 CSS (CDN), bootstrap-select 1.13.14 CSS (CDN), Google Fonts Questrial, `medias/css/print.css` (media print), `medias/css/print-preview.css` (media screen), `medias/css/smoothness/jquery-ui-1.9.0.custom.min.css`, jQuery 1.11.3, jquery-migrate 1.2.1, Bootstrap 3.3.6 JS, jQuery UI 1.11.3 JS, bootstrap-select 1.13.14 JS + `i18n/defaults-fr_FR`, CKEditor 4.5.9 full-all (CDN), `medias/tablesorter/js/jquery.tablesorter.combined.js`, `medias/tablesorter/css/theme.green.css`, `medias/colorbox/colorbox.css`, `medias/colorbox/jquery.colorbox-min.js`, `medias/uploadify/jquery.uploadify-3.1.min.js`, `medias/uploadify/uploadify.css`.
4. CSS conditionnelle selon segment 1 (`33-66`) : `connexion` → `connexion.css` ; `rechercher` → `recherche.css` ; `messagerie` → `messagerie.css` ; `contact` → `contact.css` ; `agenda` → `agenda.css` ; `programme` → `flexigrid/css/flexigrid.pack.css` + `flexigrid/js/flexigrid.pack.js` ; `gestion_programmes` → `css/jquery.jscrollpane.css` + `js/jquery.mousewheel.js` + `js/jquery.jscrollpane.min.js`.
5. Puis toujours (`67-71`) : `confirm/jquery.confirm.css`, `confirm/jquery.confirm.js`, `css/global.css`, `js/jquery.print-preview.js`, `js/global.js`.
6. `<body>` : `nav.navbar.navbar-default > div.container-fluid` ; `div.navbar-header` avec bouton `navbar-toggle collapsed` (`data-target="#mini-menu"`, `sr-only` « Toggle navigation », 3 `icon-bar`) et `a.navbar-brand` vers `/` contenant `<img src="/medias/images/logo-corim.png" height="40" alt="Corim Immobilier" />` (`77-89`).
7. `div.collapse.navbar-collapse#mini-menu > ul.nav.navbar-nav` (`90-118`) : si admin, `li` Programmes (`/gestion_programmes`), Clients (`/gestion_clients`), Prospects (`/gestion_prospects`), Prescripteurs (`/gestion_promoteurs`), Privilèges (`/gestion_privilege`), Emailing (`/emailing`), Emails (`/emails`), Flash info (`/flash_info`) ; puis pour tous : Dénonciations (`/denonciations`), `Messagerie ` + badge `span.label.label-info` si > 0 (`/messagerie`), Contacter Corim (`/contact`), si `ToolbarActif` les deux liens `javascript:void(0)` « Déverrouiller les outils » / « Verrouiller les outils » avec `class="item-menu-right"`, enfin Déconnexion (`/logout`). Le texte est mis en majuscules par CSS (`global.css:11`).
8. `div.container > div.row > div.col-xs-12.col-sm-12.col-md-12.form-group` contenant `$content`, puis `footer.no-print > div.col-xs-12.col-sm-12.col-md-12.form-group.text-center > small` « Copyright&copy; Corim Promotion - Tous droits réservés » (`123-134`).

**`template/template_welcome.php`** (accueil) : même `head` réduit et dans cet ordre (`11-23`) : Bootstrap 3.3.6 CSS, Questrial, `print.css`, `print-preview.css`, `jquery.jscrollpane.css`, `tablesorter/css/theme.green.css`, `global.css`, jQuery 1.11.3, Bootstrap 3.3.6 JS, jQuery UI 1.11.3, tablesorter combined, `jquery.print-preview.js`, `global.js` (pas de jquery-migrate, colorbox, confirm, uploadify, CKEditor, bootstrap-select, smoothness). Même `nav` (`28-72`, sans la classe `item-menu-right` sur les liens outils). Corps (`74-93`) : `div.container > div.row > div.col-xs-12.col-sm-12.col-md-12.form-group` contenant `div.col-xs-6.col-sm-5.col-md-3` (programmes), `div.col-xs-6.col-sm-7.col-md-9` (dashboard), `div.col-xs-12.col-sm-12.col-md-12` (`$content`) ; puis `footer` **sans** `no-print` ; un `</div>` orphelin en ligne 94.

**`template/connexion.php`** (module auth, listé pour l'inventaire) : Bootstrap CSS, Questrial **en http://**, `print.css`, `print-preview.css`, smoothness, `connexion.css`, `global.css`, jQuery 1.11.3, jquery-migrate **en http://**, Bootstrap JS, jQuery UI 1.11.3, tablesorter, print-preview, `global.js` (`10-24`) ; corps : `$content` + `div.clear` (`26-29`).

**`template/json.php`** : `echo $content` seul (`1-2`).

**`template/header.php`** et **`template/toolbar.php`** : assignés mais jamais affichés (`template.php:74-75`, `template_welcome.php:26`). `toolbar.php` contient la barre `#topmenu` (Accueil, Actualiser, Imprimer `#print-preview`, Dénonciations, Contact, « Fermer »), le lien « Barre d'outils », un lien déconnexion `/connexion/deconnection` avec `18px-logout.svg.png` et « Espace de {SessionName} ». À ne pas porter en markup ; conserver les règles CSS correspondantes dans `global.css` (copie à l'identique).

### 8.2 Inventaire des assets

Légende « Chargé par » : vue (fichier:ligne) ou CSS qui le référence. « Non référencé » = aucun usage trouvé dans `application/`, `medias/css`, `medias/js`, plugins, `404.html`, `maintenance.html`, ni dans les tables `news`/`emailing` en local.

**Bibliothèques externes (CDN)**
| Ressource | Version | Chargé par | Décision |
|---|---|---|---|
| Bootstrap CSS + JS (maxcdn) | 3.3.6 | `template.php:11,20`, `template_welcome.php:11,19`, `connexion.php:10,20`, `V_app.php:9,16` | auto-hébergé, même version ; ajouter les polices glyphicons (absentes de `OLD/bootstrap/3.3.6/`, qui ne contient que `css/bootstrap.min.css`) car utilisées (`module_dashboard.php:67`, `V_Gestion_promoteurs.php`) |
| jQuery (googleapis) | 1.11.3 | `template.php:18`, `template_welcome.php:18`, `connexion.php:18`, `V_app.php:14` | garder (voir questions sur une montée de version) |
| jquery-migrate (code.jquery.com) | 1.2.1 | `template.php:19`, `connexion.php:19` (http), `V_app.php:15` (http) | garder tant que des plugins utilisent `$.browser`/`.live` (`jquery.print-preview.js`, `flexigrid.pack.js`, `colorbox` en repli `.live`) |
| jQuery UI JS (code.jquery.com) | 1.11.3 | `template.php:21`, `template_welcome.php:20`, `connexion.php:21` | garder pour `datepicker` (`global.js:20-49`, 8 vues) ; le thème CSS est la version locale 1.9.0 |
| bootstrap-select (jsdelivr) + i18n fr_FR | 1.13.14 | `template.php:12,22-23` | garder (`.selectpicker` dans `V_Gestion_promoteurs.php:34,40`) |
| CKEditor full-all (cdn.ckeditor.com) | 4.5.9 | `template.php:25` | voir questions (EOL, failles connues) ; utilisé par `V_Add_Flash_Info.php`, `V_Edit_Flash_Info.php`, `V_emailing.php` |
| Font Awesome (maxcdn) | 4.5.0 | `V_app.php:11` | auto-héberger `OLD/font-awesome-4.5.0` ; seul usage : la coche de `awesome-bootstrap-checkbox.css:52-53,242-243` |
| Google Fonts Questrial | — | `template.php:13`, `template_welcome.php:12`, `connexion.php:11` (http), `V_app.php:10` (http) | auto-héberger (police principale, `global.css:1`) ; voir questions sur les pages en http |
| `ckeditor/ckeditor.js` local | — | `V_emailing.php:2` | dossier `OLD/ckeditor/` inexistant (404) : ne pas reproduire |

**`OLD/medias/css/`**
| Fichier | Chargé par | Notes |
|---|---|---|
| `global.css` (141 l.) | `template.php:69`, `template_welcome.php:17`, `connexion.php:16` | styles globaux ; référence les images `accueil.png`, `actualiser.png`, `rechercher.png`, `imprimer.png`, `contact.png`, `denonciations.png`, `informations.png`, `menu-content.png`, `leftcorner.png`, `rightcorner.png`, `menu-separateur.png`, `outils.png`, `close-menu.png`, `template/info.png`, `template/success.png`, `template/warning.png`, `template/error.png` ; bloc accueil `#programmes` (hauteur 476px, `overflow:auto`) `global.css:100-129` ; `@media print .no-print` `132-142` |
| `print.css` | `template.php:14`, `template_welcome.php:13`, `connexion.php:12` (media print) | masque `#topmenu`, `.noprint`, police Georgia |
| `print-preview.css` | `template.php:15`, `template_welcome.php:14`, `connexion.php:13` | images `css/images/icon-print.png`, `icon-close.png` |
| `smoothness/jquery-ui-1.9.0.custom.min.css` | `template.php:16`, `connexion.php:14` | thème jQuery UI 1.9.0, 13 images `smoothness/images/ui-*.png` |
| `smoothness/jquery-ui-1.9.0.custom.css` | non référencé | version non minifiée |
| `jquery-ui.css` | non référencé | jQuery UI 1.8.14 |
| `connexion.css` | `template.php:36`, `connexion.php:15` | logo `logo-corim.png` |
| `recherche.css` | `template.php:40` | page cassée (3.4) |
| `messagerie.css` | `template.php:44` | `check_plein.png`, `check_vide.png`, `contact-top.png`, `contact-bottom.png`, `fond-message-valid.png` |
| `contact.css` | `template.php:48` | fichier vide (0 ligne) |
| `agenda.css` | `template.php:52` | — |
| `jquery.jscrollpane.css` | `template.php:61`, `template_welcome.php:15` | jScrollPane jamais initialisé |
| `app.css` | `V_app.php:13` | fichier vide |
| `awesome-bootstrap-checkbox.css` | `V_app.php:12` | dépend de FontAwesome |
| `green/style.css` + `green/asc.png`, `bg.png`, `desc.png` | non référencé | ancien thème tablesorter |

**`OLD/medias/js/`**
| Fichier | Version | Chargé par | Notes |
|---|---|---|---|
| `global.js` (1 824 l.) | — | `template.php:71`, `template_welcome.php:23`, `connexion.php:24` | tout le JS applicatif ; `SITE="/"` (`1-2`) ; init tablesorter `6-18` ; datepicker `20-49` ; `$.confirm` (`77, 111, 223, 332, 367, 422, 476, 529, 619, 656, 693, 728, 804, 857, 910, 963, 1130, 1158, 1186, 1335, 1363, 1391`) ; `$.colorbox` (`186, 205, 429, 483, 536, 626, 663, 700, 735, 811, 864, 917, 970, 1008, 1117, 1290, 1530`) ; `printPreview` `1569` ; barre d'outils `1571-1609` ; `Recherche*` `1707-1762` ; `ShowToolbar`/`HideToolbar` `1764-1788` ; nombreux `eval('(' + callback + ')')` |
| `app.js` | — | `V_app.php:17` (http) | 3.6 |
| `jquery.print-preview.js` | 1.0 (2011) | `template.php:70`, `template_welcome.php:22`, `connexion.php:23` | seul déclencheur `a#print-preview` dans `toolbar.php:24`, jamais affiché |
| `jquery.jscrollpane.min.js` | 2.0.0beta11 | `template.php:63` (segment `gestion_programmes`) | jamais appelé (`jScrollPane(` absent des vues et de `global.js`) |
| `jquery.mousewheel.js` | 3.0.4 | `template.php:62` | dépendance de jScrollPane seulement |
| `jquery-1.8.2.js` | 1.8.2 | non référencé | |
| `jquery-ui-1.9.0.custom.min.js` | 1.9.0 | non référencé | |
| `jquery.tools.min.js` | 1.2.5 (inclut flashembed) | non référencé | |
| `jquery.bgpos.js` | 1.02 | non référencé | |
| `jquery.tablesorter.min.js` | ancienne 2.x | non référencé | |
| `jquery.tablesorter.combined.js` | 2.24.3 | non référencé (doublon : les gabarits chargent `medias/tablesorter/js/`) | |

**`OLD/medias/images/`** (référencées) : `logo-corim.png` (`template.php:87`, `template_welcome.php:38`, `header.php:4`, `connexion.css:12`, aussi dans les emails de `contact.php`, `emailing.php`, `gestion_promoteurs.php`, `json.php`), `18px-logout.svg.png` (`toolbar.php:42`), `loader.gif` (`V_connexion.php`, `global.js`), `accueil.png`, `actualiser.png`, `rechercher.png`, `imprimer.png`, `contact.png`, `denonciations.png`, `informations.png`, `menu-content.png`, `menu-separateur.png`, `leftcorner.png`, `rightcorner.png`, `outils.png`, `close-menu.png` (`global.css`), `check_plein.png`, `check_vide.png`, `contact-top.png`, `contact-bottom.png`, `fond-message-valid.png` (`messagerie.css`), `template/info.png`, `success.png`, `warning.png`, `error.png` (`global.css:62-81`), `template/bouton-detail.png`, `template/bouton-print.png` (`flexigrid.pack.css`), `template/delete-icon.png` (`V_Agenda.php`), `template/panel-couleur.png` (`V_programme.php`).
Non référencées : `background.png`, `background2.png`, `box-answer-remplissage.png`, `box-connexion-remplissage.png`, `check_plein.jpg`, `check_vide.jpg`, `contact-in.png`, `content-bottom.png`, `content-in.png`, `content-top.png`, `deconnexion.png`, `fond-input-email.png`, `fond-message.png`, `fond-message-read.png`, `fond-push.jpg`, `forgottenpassword-ok.png`, `item-menu-right.png`, `vignette.png`, `menu-accueil/bouton-vos-demandes.png`, `bouton-vos-ok.png`, `bouton-vos-stats.png`, `bouton-vos-ventes.png`.

**Plugins `OLD/medias/*`**
| Plugin | Version | Fichiers chargés | Chargé par | Utilisé par | Remplacement proposé (sans changement visuel) |
|---|---|---|---|---|---|
| colorbox | 1.6.3 | `colorbox/colorbox.css`, `jquery.colorbox-min.js`, images `colorbox/images/border.png`, `controls.png`, `loading_background.png`, `loading.gif`, `overlay.png` | `template.php:29-30` | `global.js` (lignes ci-dessus), `V_programme.php:575,585,593,631` (diaporama `rel='slideshow'`) | `<dialog>` natif portant les mêmes id/classes (`#colorbox`, `#cboxOverlay`, `#cboxContent`...) et `colorbox.css` recopié pour un rendu identique ; diaporama : petit script de navigation précédent/suivant |
| jquery.confirm (Tutorialzine) | sans version | `confirm/jquery.confirm.css`, `jquery.confirm.js`, images `body_bg.jpg`, `buttons.png`, `header_bg.jpg`, `ie.png` | `template.php:67-68` | `global.js` (22 appels) | `<dialog>` avec le markup généré par le plugin (`#confirmOverlay`, `#confirmBox`, `#confirmButtons a.button`) et la même CSS |
| uploadify | 3.1.1 (Flash, `uploadify.swf`) | `uploadify/jquery.uploadify-3.1.min.js`, `uploadify.css` | `template.php:31-32` | `V_ajouter_plan.php:63-76`, `V_modifier_programme.php`, `V_Ajouter_clients.php`, `V_ajouter_appartements.php` | `<input type="file" multiple>` + `fetch` vers une route authentifiée ; bouton stylé avec les classes `uploadify`/`uploadify-button` et la file `uploadify-queue-item` de `uploadify.css` ; `uploadify-cancel.png` à conserver pour le bouton annuler ; supprimer `uploadify.php`, `uploadify-plan.php`, `uploadify-docs.php`, `check-exists.php`, `.swf` |
| flexigrid | 1.1 | `flexigrid/css/flexigrid.pack.css` (+17 images `flexigrid/css/images/`), `flexigrid/js/flexigrid.pack.js` | `template.php:56-57` (segment `programme`) | `V_programme.php:506` (`#datagrid`), aussi `jquery.print-preview.js` | tableau HTML + script de tri vanilla qui produit le même markup (`div.flexigrid`, `div.hDiv`, `div.bDiv`, `div.pDiv`) et garde `flexigrid.pack.css` ; non référencés : `flexigrid.css`, `flexigrid.js`, `_flexigrid.pack.js`, `btn-sprite.gif` |
| tablesorter (fork Mottie) | 2.24.3 | `tablesorter/js/jquery.tablesorter.combined.js`, `tablesorter/css/theme.green.css` (+ images `tablesorter/css/images/`) | `template.php:27-28`, `template_welcome.php:16,21`, `connexion.php:22` | 16 tables `.tablesorter-green` dans 16 vues ; options : zebra, filtre (ligne de filtres, `filter_saveFilters`, placeholder « Recherche... », `.search`, `.reset`) `global.js:6-18` | script vanilla de tri + ligne de filtres générant les mêmes classes (`tablesorter-header`, `tablesorter-filter-row`, `odd`/`even`, `tablesorter-headerAsc/Desc`) avec `theme.green.css` recopié ; le reste de `tablesorter/` (autres thèmes, `.less`, `psd/`, `widgets/`, `parsers/`, `extras/`, `jquery.tablesorter.js`, `jquery.tablesorter.widgets.js`) n'est pas chargé |
| jScrollPane + mousewheel | 2.0.0beta11 / 3.0.4 | `css/jquery.jscrollpane.css`, `js/jquery.jscrollpane.min.js`, `js/jquery.mousewheel.js` | `template.php:61-63`, `template_welcome.php:15` | jamais initialisé | supprimer (aucun effet visuel) ; garder la CSS seulement si un sélecteur `.jsp*` est rendu (aucun trouvé) |
| print-preview | 1.0 | `css/print-preview.css`, `js/jquery.print-preview.js` | `template.php:15,70`, `template_welcome.php:14,22`, `connexion.php:13,23` | aucun déclencheur rendu | supprimer le JS ; garder `print.css` (impression navigateur) |

**`OLD/bootstrap/`** : `3.3.6/css/bootstrap.min.css` seul, non référencé (les vues chargent le CDN).
**`OLD/font-awesome-4.5.0/`** : `css/font-awesome.css`, `css/font-awesome.min.css`, `fonts/FontAwesome.otf`, `fontawesome-webfont.{eot,svg,ttf,woff,woff2}`, `less/` (13), `scss/` (13) ; non référencé (CDN utilisé par `V_app.php:11`).
**`OLD/libraries/`** : dossier vide.
**`OLD/images/`** (51 fichiers : `13-06-13-voltaire-00{1,4,8,9}.jpg`, `aero.jpg`, `Allures_A5-1.jpg`, `arrow.gif`, `bando.jpg`, `bg.gif`, `bg2.gif`, `bgcell.gif`, `bigredarrow.gif`, `calaverde-jardin.jpg`, `calaverde.png`, `canopee_thumb.jpg`, `CORIM-ALLURE.{gif,jpg,png}`, `CORIM-ALLURE2.jpg`, `date-prev.png`, `doc.gif`, `down.gif`, `gif.gif`, `govideo.gif`, `gradient.png`, `intro.jpg`, `jpg.gif`, `label.gif`, `left.gif`, `loading.gif`, `logo.gif`, `menu.gif`, `menu2.gif`, `ombre1.gif`, `overlay-85.png`, `pdf.gif`, `plan.jpg`, `puce.gif`, `puceo.gif`, `pucev.gif`, `quai_voltaire.jpg`, `regalissa.jpg`, `regalissa2.jpg`, `right.gif`, `sep1.gif`, `spacer.gif`, `thumb_quai_voltaire.jpg`, `top.gif`, `visu.jpg`, `xls.gif`, `zip.gif`) : aucun référencé par le code ni par les tables `news`/`emailing`.
**Racine** : `favicon.ico`, `apple-touch-icon.png` (non liés dans les `<head>`, demandés automatiquement par les navigateurs), `robots.txt`.
**Autres dossiers `medias/` hors design** : `clients/import.csv`, `csv/import.csv`, `export/mydata.csv`, `upload/` (171 fichiers), `news/` (23), `programmes/` — données, relèvent des modules métier.

### 8.3 Règles pour la recopie
1. Copier à l'octet près `global.css`, `print.css`, `colorbox.css`, `jquery.confirm.css`, `uploadify.css`, `theme.green.css`, `flexigrid.pack.css`, `connexion.css`, `messagerie.css`, `agenda.css`, `recherche.css`, `awesome-bootstrap-checkbox.css`, smoothness 1.9.0, et leurs images, aux mêmes chemins relatifs (les CSS utilisent `url(../images/...)` et `url(images/...)`), sous `public/medias/...`.
2. Garder le même ordre de chargement CSS que 8.1 (les règles de `global.css` surchargent Bootstrap).
3. Garder les classes et id du markup (`navbar`, `#mini-menu`, `item_programme`, `vignette`, `list-group-item-warning`, `no-print`...).
4. Ne pas porter les assets non référencés listés ci-dessus.

## 9. Bugs et failles constatés

| Problème | Fichier:ligne | Décision proposée (reproduire / corriger) |
|---|---|---|
| Contrôleur `administration` accessible sans authentification, INSERT de colonnes arbitraires dans `appartements` depuis `$_POST` | `administration.php:12-39`, `model.php:1848-1882` | corriger : ne pas porter (404) |
| Méthodes publiques des contrôleurs de base appelables en URL, dont `shutdown` (déconnexion par GET) | `__main.php:46-115`, `__json.php:17-47`, `Kohana.php:258-270` | corriger : routes explicites seulement |
| Pages d'erreur Kohana avec chemins, lignes et SQL affichés en production | `config.php:97`, `index.php:34,124` | corriger : pages génériques, `APP_DEBUG=false` |
| `/rechercher*` en erreur 500 (vue `rechercher` absente) | `rechercher.php:11,25,33`, `logs/2023-09-04.log.php` | voir questions ; par défaut ne pas porter |
| Réécritures `rechercher-par-residence`/`par-lot` jamais atteintes | `.htaccess` (règles après la règle générale `[L]`) | ne pas reproduire |
| `/app` non fonctionnel (`var_dump`/`die` côté `firstsearch`, scripts en http bloqués) | `firstsearch.php:99-102`, `V_app.php:10-17`, `app.js:18,57` | corriger : comportement voulu (`AppSearchProgram`), URL relatives en HTTPS ; voir questions |
| `/app` sans authentification | `app.php:7-9` | reproduire (page publique) si conservée |
| Badge admin : sujets encodés HTML (`d&#039;option`) non exclus du compteur | `model.php:1930-1955`, données `mp` | voir questions |
| Accueil prescripteur : programmes archivés affichés (pas de filtre `archive`) ; leur page `programme/show` est vide | `model.php:1652-1662` | voir questions |
| `projet_titre`, `projet_lieu` affichés sans échappement | `module_programmes.php:31` | corriger : échappement Blade |
| `module_programmes` lit le disque à chaque affichage ; URL d'image = concaténation de tous les fichiers de `thumb/` ; `function list_dir` déclarée dans la vue ; appel `$this->list_dir` invalide | `module_programmes.php:4-26` | corriger : premier fichier de `thumb/` (même rendu avec 1 fichier) |
| Barre d'outils jamais affichée ; liens Verrouiller/Déverrouiller visibles seulement si déjà activée ; `ShowToolbar`/`HideToolbar` sans contrôle de connexion | `template.php:74,113-116`, `json.php:1505-1517` | voir questions |
| Balise `</div>` orpheline ; `<html lang="en">` sur un site en français | `template_welcome.php:94`, `template.php:3` | reproduire (aucun effet visuel), sauf avis contraire |
| Titre CGU « Conditions générales de vente » mais contenu « .Conditions générales d'utilisation » sans texte ; titre vide sur mentions et recherche | `cgu.php:11`, `V_Cgu.php:2`, `mentions_legales.php:11` | reproduire |
| Mentions légales obsolètes (capital `XXXXXX`, hébergeur Bodéva, emails `web@bodeva.fr`) | `V_Mentions.php:9, 22-25, 42` | reproduire ; voir questions |
| Session : pas de régénération d'id, cookie sans `Secure`/`HttpOnly` explicites, pas de chiffrement | `session.php:33,45` | corriger (module auth) |
| `404.html` jamais servi | `.htaccess` (pas d'`ErrorDocument`) | corriger : vue 404 Laravel reprenant `404.html` |
| Script `/ckeditor/ckeditor.js` en 404 | `V_emailing.php:2` | ne pas reproduire |
| Thème tablesorter configuré `blue` alors que seule `theme.green.css` est chargée | `global.js:8`, `template.php:28` | reproduire le rendu vert |
| `robots.txt` sans `Disallow` (application privée indexable, pages redirigées vers connexion) | `robots.txt:1` | voir questions |
| `eval()` sur les réponses AJAX | `global.js:35, 1765-1786` et autres | corriger : `JSON.parse` / `dataType: 'json'` |
| Librairies obsolètes avec failles connues (jQuery 1.11.3, jQuery UI 1.11.3, Bootstrap 3.3.6, CKEditor 4.5.9) | 8.2 | voir questions |

## 10. Critères d'acceptation

1. `GET /` sans session → 302 vers `/connexion`.
2. `GET /welcome` et `GET /welcome/index` → même réponse que `GET /`.
3. `GET /` admin connecté → 200, `<title>Corim promotion - Tableau de bord</title>`, gabarit accueil (3 colonnes `col-md-3`, `col-md-9`, `col-md-12`).
4. Accueil admin : une `div.item_programme` par programme `archive=0`, triés par `projet_id` décroissant, lien `/programme/show/{projet_titre_key}`, image `medias/programmes/{key}/thumb/{fichier}` `width="120"`.
5. Accueil prescripteur : une vignette par ligne `acces` de l'utilisateur, triées par `projet_id` décroissant (filtre `archive` selon décision 11.4).
6. Dashboard : les 7 compteurs sont égaux aux requêtes de la section 5 (globaux pour admin, filtrés pour prescripteur).
7. Dashboard : compteur 0 → `href="javascript:void(0)"` ; > 0 → lien vers la page cible.
8. Dashboard : demandes > 0 → classe `list-group-item-warning` et icône `glyphicon-exclamation-sign`.
9. Dashboard : lien `RAPPORT` vers `/report` présent pour admin seulement.
10. Menu admin : 8 entrées admin dans l'ordre Programmes, Clients, Prospects, Prescripteurs, Privilèges, Emailing, Emails, Flash info, puis Dénonciations, Messagerie, Contacter Corim, Déconnexion.
11. Menu prescripteur : Dénonciations, Messagerie, Contacter Corim, Déconnexion seulement.
12. Badge Messagerie : `span.label.label-info` avec le nombre de messages non lus (`lu=0`, `idreceveur`=utilisateur), sujets automatiques exclus pour l'admin ; aucun badge si 0.
13. Liens Verrouiller/Déverrouiller les outils affichés seulement si `SessionToolbar = 1` (si conservés).
14. Pied de page : `Copyright© Corim Promotion - Tous droits réservés` ; `footer.no-print` sur les pages internes, `footer` sans classe sur l'accueil.
15. Le `<head>` des pages internes charge les CSS/JS dans l'ordre de 8.1 ; CSS conditionnelle selon le segment 1 (`messagerie`, `contact`, `agenda`, `programme`, `gestion_programmes`, `connexion`, `rechercher`).
16. `GET /cgu` connecté → 200, titre `Conditions générales de vente`, contenu `<h3>.Conditions générales d'utilisation</h3><br />`.
17. `GET /mentions_legales` connecté → 200, titre vide, texte identique à `V_Mentions.php` (comparaison du HTML rendu).
18. `GET /cgu`, `/mentions_legales` sans session → 302 vers `/connexion`.
19. Les mêmes URL préfixées `/index.php/` répondent comme sans préfixe.
20. `GET /app` sans session → 200, page « DISPONIBILITES » avec les 6 cases T1-T6, le select budget (6 options, libellés exacts) et le tableau `#result` caché.
21. `/app?project={key}` : soumission automatique, appel `firstsearch?action=app` qui renvoie `MyFirstCallback({...})` (format arrêté avec `public-api`) et remplit le tableau ; aucun `var_dump`.
22. `/app` : aucune ressource chargée en `http://`.
23. URL inconnue → 404 avec le rendu de `404.html`, sans trace technique.
24. Erreur serveur → page générique, sans SQL ni chemin.
25. Mode maintenance (`php artisan down`) → page identique à `maintenance.html`.
26. `GET /administration`, `/administration/Ajouter_Appartement`, `POST /administration/Ajouter_Appartement` → 404, aucune écriture en base.
27. `GET /welcome/shutdown` (et autres méthodes internes) → 404, la session reste valide.
28. `GET /rechercher`, `/rechercher/par_residence`, `/rechercher/par_lot` → comportement selon décision 11.1 (par défaut 404).
29. HTTP → redirection HTTPS ; `/application/*`, `/system/*`, `/modules/*` et `*.csv` non servis.
30. `projet_titre` contenant `<script>` est affiché échappé sur l'accueil.
31. Comparaison visuelle (capture) accueil admin, accueil prescripteur, CGU, mentions, `/app` : identique à l'ancienne app à 1280px et 375px.

## 11. Questions ouvertes

1. **Recherche par résidence / par lot** : cassée depuis au moins 2023 (vue absente) et retirée du menu. La supprimer (404) ou la reconstruire ? Si reconstruction : aucune maquette ni vue d'origine n'existe.
2. **Page `/app`** : qui l'utilise (iframe sur un site partenaire avec `?project=`) ? Faut-il la reconstruire avec le comportement voulu (`AppSearchProgram`) ou la supprimer ? Les accès réels sont à vérifier dans les logs Apache de prod.
3. **Badge messages admin** : conserver le compte actuel (sujets encodés `&#039;` comptés) ou normaliser les sujets à la migration pour que l'exclusion fonctionne ? Le nombre affiché changerait.
4. **Programmes archivés** sur l'accueil prescripteur : reproduire (vignettes vers une page vide) ou filtrer `archive=0` comme pour l'admin ?
5. **Barre d'outils / verrouillage** : fonction invisible et inutilisable aujourd'hui. La supprimer avec ses liens et les actions `ShowToolbar`/`HideToolbar` ?
6. **Montée de versions front** : rester sur jQuery 1.11.3 / jQuery UI 1.11.3 / Bootstrap 3.3.6 (rendu garanti, failles XSS connues) ou passer à jQuery 3.7 + migrate 3, jQuery UI 1.13, Bootstrap 3.4.1 (dernière 3.x, corrige les XSS, rendu quasi identique) ? À valider par comparaison visuelle.
7. **CKEditor 4.5.9** : passer à 4.22.1 (dernière version open source ; les versions suivantes sont sous licence LTS commerciale) avec le skin `moono` pour ne pas changer l'apparence, ou autre choix ? Concerne aussi le module `communication`.
8. **tablesorter** : CLAUDE.md prévoit un tri vanilla, mais les tables utilisent aussi la ligne de filtres et la sauvegarde des filtres. Garder tablesorter (Mottie, maintenu) pour éviter tout écart visuel, ou réécrire ?
9. **Pages chargées en http://** (`template/connexion.php:11,19`, `V_app.php`) : en HTTPS, Questrial et jquery-migrate y sont bloqués aujourd'hui. Pour un rendu « identique », faut-il reproduire la police de repli sur la page de connexion, ou charger Questrial comme sur les autres pages ?
10. **Mentions légales** : texte obsolète (capital `XXXXXX`, hébergeur Bodéva, RCS Marmande, emails `web@bodeva.fr`). Recopier tel quel ou faire fournir un texte à jour par le client ?
11. **CGU** : page vide avec titre « Conditions générales de vente ». Le client a-t-il un texte ?
12. **`robots.txt`** : ajouter `Disallow: /` (application privée) ? En recette, `noindex` est déjà prévu.
13. **Vignettes** : en local, 6 des 8 programmes non archivés n'ont pas de dossier `thumb/`. Le dossier `medias/programmes` local est-il complet par rapport à la prod ? Sinon, quel rendu attendu sans vignette (aujourd'hui, `opendir` échoue et le gestionnaire d'erreurs Kohana `Kohana.php:152` peut interrompre la page) ?
14. **`OLD/images/`** (51 fichiers non référencés) : sont-ils liés depuis des sites externes ou d'anciens emails ? À vérifier dans les logs d'accès avant de les abandonner.
15. **Assets en CDN** : tout auto-héberger (CSP stricte, pas de dépendance externe) est proposé ; à confirmer.
