# Contrat — public-api

Statut : brouillon (à valider par Victor)
Sources lues :
- `OLD/application/controllers/firstsearch.php:1-182` (toutes les actions + `JsonMe`)
- `OLD/application/controllers/search.php:1-145` (`news()`, `index()`, `JsonMe`)
- `OLD/application/controllers/__json.php:1-48` (contrôleur parent, session, gabarit)
- `OLD/application/views/V_Json.php:1` (vue vide, instanciée mais jamais rendue)
- `OLD/application/views/template/json.php:1-2` (gabarit : `<?php echo $content ?>`, `$content = ''`)
- `OLD/application/models/model.php:1-24, 72-76, 102-368` (connexion, `normalize`, `escape`, `escapeSQL`, `getAllInfos`, `AppSearchProgram`, `ExternalSendEmail`, `ExternalSearchProgram`, `ExternalSearch2`, `ExternalSearch`, `ExternalLightSearchProgramWithRef`, `ExternalLightSearchWithRef`, `ExternalLightSearchProgram`, `ExternalLightSearch`)
- `OLD/system/libraries/Input.php:52, 99-110, 157-160, 210-228, 400-449` (nettoyage global des entrées GET)
- `OLD/system/libraries/drivers/Database.php:302-325`, `OLD/system/libraries/drivers/Database/Mysqli.php:99-107` (`escape` = quotes + `real_escape_string`)
- `OLD/system/controllers/template.php:22-50`, `OLD/system/core/Kohana.php:239`, `OLD/index.php:34`
- `OLD/application/config/config.php:22, 71, 97`, `OLD/application/config/database.php:29-45` (sans le mot de passe)
- `OLD/.htaccess:1-29`
- `OLD/medias/js/app.js:1-79`, `OLD/application/views/V_app.php:17-95` (consommateur interne de `action=app`)
- Fixtures prod du 2026-10-27 : `NEW/tests/Fixtures/legacy-api/manifest.tsv` + 16 paires `*.body` / `*.headers`
- Consommateurs externes (lecture seule) : `~/www/cocoon/src/components/appartements/AppartmentSection.astro`, `~/www/corim-promotion/frontend/src/…` (voir §1.2)
- Base locale `sql_corim_vente_` (SELECT/SHOW/DESCRIBE uniquement) : `appartements`, `projet`, `news`, `docs`

Vérification faite : en rejouant localement (script personnel, pas de code legacy) les requêtes SQL d'origine de `nextjscorim` et de `search/index?action=light` sur la base locale (MySQL 8.0.46, tables MyISAM, `set_charset('utf8')`, `json_encode` sans option), les 7 réponses obtenues sont **identiques octet pour octet** aux fixtures `nextjscorim_all`, `nextjscorim_cocoon`, `nextjscorim_type_t2`, `nextjscorim_city`, `nextjscorim_budget`, `nextjscorim_combo`, `search_light_prog`. La base locale est donc un instantané fidèle de la prod au moment de l'enregistrement.

## 1. Périmètre

Points d'accès HTTP **publics, sans authentification**, appelés par des sites partenaires (serveur à serveur ou JSONP navigateur) pour lister les lots disponibles des programmes, enregistrer une demande de documentation par email, et lister les flash infos ; plus les plans PDF des lots servis comme fichiers statiques. Ils doivent rester compatibles à l'octet près (URL, paramètres, noms et types des champs, enveloppe JSONP, Content-Type) sans aucune modification côté consommateur (CLAUDE.md, « Public API contract »).

Aucun de ces points d'accès ne lit la session : `__Json_Controller` démarre pourtant une session à chaque appel (`__json.php:11`), d'où les en-têtes `Set-Cookie: kohanasession=…`, `Expires: Thu, 19 Nov 1981…`, `Cache-Control: no-store…`, `Pragma: no-cache` visibles dans toutes les fixtures (`*.headers`).

### 1.1 Inventaire (13 points d'accès)

| # | URL | Action | État prod (fixtures 2026-10-27) |
|---|---|---|---|
| A1 | `/index.php/firstsearch?action=nextjscorim` | JSON brut des lots disponibles | OK 200 (`nextjscorim_*`) |
| A2 | `/index.php/firstsearch` (action absente ou inconnue) | recherche multicritère par programme, JSONP `MyFirstCallback` | 200 mais **toujours sans résultat** sans `livraison` (`first_default_empty`, `first_default_prog`) |
| A3 | `/index.php/firstsearch` sans aucun paramètre GET | message « Aucunes données envoyées » | **500** (`first_noparams`, variable `$action` indéfinie) |
| A4 | `/index.php/firstsearch?action=light` | lots par programme, JSONP `MyFirstCallback2` | **500** (`first_light_all`, `first_light_prog`, `first_callback`, DISTINCT + ORDER BY) |
| A5 | `/index.php/firstsearch?action=withref` | lot par référence, JSONP `MyFirstCallback4` | **500** (`first_withref`, `first_withref_none`, DISTINCT + ORDER BY) |
| A6 | `/index.php/firstsearch?action=email` | insertion dans `docs`, JSONP `MyFirstCallback3` | non enregistré (écrit en base) |
| A7 | `/index.php/firstsearch?action=app` | recherche « app », JSONP `MyFirstCallback` prévu | `var_dump` puis `die()` (non enregistré) |
| A8 | `/index.php/search` (action absente ou inconnue, y compris `allnews`) | recherche multicritère à plat, JSONP `MyCallback` (ou `MyFirstCallbackNews` si `action=allnews`) | 200 mais **toujours sans résultat** sans `livraison` (`search_allnews`) |
| A9 | `/index.php/search?action=light` | lots disponibles à plat, JSONP `MyCallback2` | OK 200 (`search_light_prog`) mais filtre programme jamais appliqué |
| A10 | `/index.php/search?action=withref` | JSONP `MyFirstCallback4` | méthode de modèle inexistante → erreur fatale (déduit du code, non enregistré) |
| A11 | `/index.php/search?action=email` | insertion dans `docs`, JSONP `MyCallback3` | non enregistré (écrit en base) |
| A12 | `/index.php/search/news?action=allnews` | flash infos, JSONP `MyFirstCallbackNews` | non enregistré ; fonctionnel d'après le code |
| A13 | `/medias/programmes/{projet_titre_key}/plans/plan-{numero}.pdf` | fichier PDF statique | servi par Apache (non enregistré) |

Variantes d'URL qui atteignent le même code (`.htaccess:17-21` réécrit tout chemin non existant en `index.php?kohana_uri=…`) : `/firstsearch`, `/index.php/firstsearch`, `/firstsearch/index`, `/index.php/firstsearch/index` ; idem pour `search`, `search/index`, `search/news`. HTTP est redirigé vers HTTPS (`.htaccess:7-8`, `[R]` = 302).

### 1.2 Consommateurs trouvés dans `~/www/` (grep `corim-vente`, lecture seule)

| Projet | Fichier:ligne | Appel | Champs utilisés |
|---|---|---|---|
| cocoon (Astro, rendu serveur/build) | `cocoon/src/components/appartements/AppartmentSection.astro:30-31` | `fetch('https://corim-vente.com/index.php/firstsearch?action=nextjscorim&residence=cocoon')` puis `response.json()` | `type`, `categorie` (`!== 'Local'`), `prix` (`.replace(/\s/g,'')` puis `parseInt`, l.37-58), `numero`, `etage`, `superficie`, `terrasse`, `jardin`, `exposition`, `accession`, `projet_titre_key` |
| cocoon | `AppartmentSection.astro:33-34` | lien plan `https://corim-vente.com/medias/programmes/${projet_titre_key}/plans/plan-${numero}.pdf` (`numero` **non** débarrassé des espaces) | — |
| corim-promotion (Next.js, `revalidate: 0`) | `corim-promotion/frontend/src/components/MainNavigation.js:18`, `FixedElements.js:11` | `fetch('https://corim-vente.com/index.php/firstsearch?action=nextjscorim')` | `type`, `ville` (`charAt`, `slice`, `toLowerCase`, `normalize('NFD')` — `ville` doit être une chaîne non nulle) |
| corim-promotion | `corim-promotion/frontend/src/app/recherche/page.js:4` | `…?action=nextjscorim&residence=${searchParams['residence']}&type=…&city=…&minBudget=…&maxBudget=…` (paramètres vides `''` envoyés ; `residence=undefined` littéral si absent) | tout l'objet, groupé par `projet_titre` (`SearchPageContent.js:31`) |
| corim-promotion | `corim-promotion/frontend/src/components/SearchPageContent.js:95-137` | tri `a.prix.localeCompare(b.prix)` (`prix` doit être une **chaîne**), affichage `numero`, `etage`, `superficie`, `terrasse`, `jardin`, `exposition`, `accession`, `prix`, lien plan `plan-${numero.replaceAll(' ','')}.pdf` | — |
| corim-promotion | `MainNavigationContent.js:194` | lien `https://corim-vente.com/connexion` (module `auth`, hors API) | — |
| corim-vente lui-même | `OLD/medias/js/app.js:16-24` + `OLD/application/views/V_app.php:94-95` | JSONP `http://corim-vente.com/index.php/firstsearch` avec `action=app`, `jsonpCallback: 'MyFirstCallback'` | `errors`, `program[].type/numero/superficie/prix/exposition/projet_titre_key` |

Aucun consommateur local trouvé pour les points d'accès JSONP A2, A4-A6, A8-A12 (seuls `cocoon` et `corim-promotion` référencent le domaine ; `claudeskill/…md` et `sql_corim_vente_.sql` ne sont pas des consommateurs). Leurs consommateurs éventuels (ancien site WordPress corim-promotion ?) ne sont pas identifiables sans les logs d'accès prod → §11.

## 2. Rôles et droits

| Rôle (valeur en base) | Peut faire | Contrôle dans l'ancien code (fichier:ligne) |
|---|---|---|
| Anonyme (aucun) | tous les points d'accès A1-A13 | aucun : `isConnected()` n'est jamais appelé (`firstsearch.php:8-161`, `search.php:7-123`) ; aucune clé d'API |

`const ALLOW_PRODUCTION = FALSE` (`firstsearch.php:6`, `search.php:5`) n'a aucun effet car `IN_PRODUCTION` vaut `FALSE` (`index.php:34`, test en `Kohana.php:239`). Décision (CLAUDE.md) : pas de clé d'API obligatoire ; limitation de débit et cache à la place.

## 3. Écrans et URL

Aucun écran HTML. Tous les points d'accès sont décrits en §4. Le gabarit `template/json` est rendu après l'action (`template.php:35-50`) mais n'écrit rien (`template/json.php:2` avec `$content = ''`, `__json.php:13`) ; le `?>` final suivi d'un seul saut de ligne n'émet aucun octet. Le corps de réponse est donc exactement ce que l'action `echo` (vérifié : `first_default_empty.body` = 30 octets sans saut de ligne final).

## 4. Actions AJAX / JSON

### 4.0 Règles communes

**Traitement des entrées (avant tout code applicatif)** :
- Toute clé GET doit matcher `#^[\pL0-9:_.-]++$#uD`, sinon la réponse est `Disallowed key characters in global data.` (`Input.php:400-407`, appliqué en `Input.php:99-105`). Les crochets `typologie[]` sont acceptés (PHP les transforme en tableau avant).
- Toutes les valeurs GET passent par `xss_clean` car `global_xss_filtering = TRUE` (`config.php:71`, `Input.php:52, 438-441`) ; `\r\n` et `\r` → `\n` (`Input.php:443-447`). Les valeurs ordinaires (lettres, chiffres, espaces) ne sont pas modifiées.
- `$this->input->get('x')` renvoie `NULL` si la clé est absente (`Input.php:215-216`), sinon une chaîne (ou un tableau).
- `normalize($v)` / `escape($v)` = `htmlspecialchars($v, ENT_QUOTES)` si chaîne, sinon valeur inchangée (tableaux et NULL non traités) (`model.php:11-19`).
- `escapeSQL($v)` = `$db->escape($v)` (`model.php:21-23`) : chaîne → `'` + `real_escape_string` + `'` (avec les quotes) ; NULL → `NULL` ; booléen → entier (`drivers/Database.php:302-325`, `Mysqli.php:99-107`). Conséquence majeure : `(int)escapeSQL('41')` = `(int)"'41'"` = **0** — tous les tests `(int)$programme == 0` qui suivent un `escapeSQL` sont toujours vrais (`model.php:317, 332, 346, 360`).

**Types JSON** : le pilote mysqli de Kohana renvoie toutes les colonnes en **chaînes** et `NULL` en `null` (`result_array(FALSE)`). Fixtures : toutes les valeurs sont des chaînes sauf `vendeur` (`null`), y compris `id`, `lot`, `dept` (colonnes `int` en base). Exemple réel (`nextjscorim_city.body`) :

```json
[{"id":"2825","lot":"29","date":"4T2026","dept":"34300","ville":"AGDE","type":"T3","categorie":"Appartement","prix":"219900","prix_m2":"","etage":"R2","RDC":"","jardin":"","terrasse":"12,67","superficie":"63,66","stationnement":"24-32","exposition":"NE","description":"","statut":"","client":"","vendeur":null,"numero":"B202","cellier":"","accession":"Libre","projet_titre":"NATURA","projet_titre_key":"baa692ce3ec9e7dffe643f89936729cb"}]
```

Ordre des clés de `appartements.*` = ordre des colonnes de la table : `id, lot, date, dept, ville, type, categorie, prix, prix_m2, etage, RDC, jardin, terrasse, superficie, stationnement, exposition, description, statut, client, vendeur, numero, cellier, accession`, puis `projet_titre` (et `projet_titre_key` selon la requête).

**`prix`** : `varchar(100)` saisi librement. Formats présents dans les fixtures : `"185 000"`, `"219900"`, `" 478 089"` (espace en tête), `"NC"` ; en base aussi `"143759,85"`. À renvoyer tel quel, jamais converti.

**Encodage JSON** : `json_encode($x)` sans option (PHP 5.6) → `/` échappé en `\/`, non-ASCII en `\uXXXX` (`"FÉLICITÉ"`, `"résultat"`), `<`, `>`, `&`, `'`, `"` non échappés en hex (`"<b>Aucun…<\/b>"`). Connexion en `utf8` (`database.php:41`) sur des tables `latin1` : conversion faite par MySQL.

**Enveloppe JSONP** : `NomFixe(` + JSON + `);` sans espace ni saut de ligne. Le paramètre `callback` est lu (`firstsearch.php:16`, `search.php:15, 56`) mais **ignoré** : `$call` est une variable locale de `index()`, indéfinie dans `JsonMe()` (`firstsearch.php:168`, `search.php:128`) — confirmé par `first_callback` (même erreur que `light`, pas de `foo(`).

| Contrôleur | `action` | Callback (`fichier:ligne`) | Content-Type |
|---|---|---|---|
| firstsearch | `light` | `MyFirstCallback2` (`firstsearch.php:171-172`) | `application/json` (`firstsearch.php:166`, exactement, sans charset) |
| firstsearch | `email` | `MyFirstCallback3` (`:173-174`) | `application/json` |
| firstsearch | `withref` | `MyFirstCallback4` (`:175-176`) | `application/json` |
| firstsearch | autre / absent | `MyFirstCallback` (`:177-178`) | `application/json` |
| search | `light` | `MyCallback2` (`search.php:131-132`) | `text/html; charset=UTF-8` (aucun `header()`, défaut Apache/PHP) |
| search | `email` | `MyCallback3` (`:133-134`) | `text/html; charset=UTF-8` |
| search | `withref` | `MyFirstCallback4` (`:135-136`) | `text/html; charset=UTF-8` |
| search | `allnews` | `MyFirstCallbackNews` (`:137-138`) | `text/html; charset=UTF-8` |
| search | autre / absent | `MyCallback` (`:139-140`) | `text/html; charset=UTF-8` |

`nextjscorim` (A1) n'est pas du JSONP et sort en `text/html; charset=UTF-8` (`nextjscorim_*.headers`, aucun `header()` en `firstsearch.php:111-125`).

**Messages d'erreur exacts** (valeur de `errors`, avant encodage JSON) :
- M1 (http) : `<b>Aucun résultat pour cette recherche.</b><br /><br />Vous pouvez nous contacter directement pour tous renseignements complémentaires en cliquant <a href="http://corim-promotion.com/contactez-nous/"><b>ici</b></a>` — `firstsearch.php:60, 87, 154`, `search.php:99`.
- M2 (https) : identique à M1 avec `https://corim-promotion.com/contactez-nous/` — `search.php:86, 115`. Encodé tel que dans `search_allnews.body` : `{"errors":"<b>Aucun résultat pour cette recherche.<\/b><br \/><br \/>Vous pouvez nous contacter directement pour tous renseignements complémentaires en cliquant <a href=\"https:\/\/corim-promotion.com\/contactez-nous\/\"><b>ici<\/b><\/a>"}`.
- M3 : `<b>Aucun résultat pour cette recherche.</b>` — `firstsearch.php:109` (action `app`).
- M4 : `Aucunes données envoyées` — `firstsearch.php:158`, `search.php:42, 120`.
- M5 : `Votre adresse email n'est pas valide` — `firstsearch.php:34`, `search.php:75`.
- M6 : `Database error` — `firstsearch.php:31`, `search.php:71`.
- Succès : `errors` vaut l'entier `1` (pas `true`, pas `"1"`).

### 4.1 A1 — `firstsearch?action=nextjscorim` (`firstsearch.php:111-125`)

- Méthode : GET. Paramètres optionnels, tous des chaînes, **injectés sans échappement** dans le SQL ; un paramètre est ignoré s'il est `empty()` (absent, `""` ou `"0"`) :
  - `type` → `AND type LIKE '<type>'` (l.117)
  - `city` → `AND ville LIKE '<city>'` (l.118)
  - `residence` → `AND projet_titre LIKE '<residence>'` (l.119)
  - `minBudget` → `AND prix >= '<minBudget>'` (l.120)
  - `maxBudget` → `AND prix <= '<maxBudget>'` (l.121)
- SQL d'origine (l.113-122) :

```sql
SELECT appartements.*, projet.projet_titre, projet.projet_titre_key
FROM appartements
INNER JOIN projet ON appartements.lot = projet.projet_id
WHERE projet.archive=0 AND statut=''
  [AND type LIKE '…'] [AND ville LIKE '…'] [AND projet_titre LIKE '…']
  [AND prix >= '…'] [AND prix <= '…']
ORDER BY appartements.prix
```

- Sémantique à conserver :
  - `LIKE` sans `%` ajouté : égalité insensible à la casse **et aux accents** (collation `latin1_swedish_ci` ; vérifié : `projet_titre LIKE 'carre vias'` trouve `CARRÉ VIAS`, `ville LIKE 'castelnau le lez'` trouve `CASTELNAU LE LEZ`). `%` et `_` envoyés par le client restent des jokers. corim-promotion envoie des villes en casse mixte sans accents (`FixedElements.js:25`) : la comparaison insensible est nécessaire.
  - Bornes de budget : comparaison **de chaînes** (`prix` varchar contre littéral quoté), pas numérique. Ex. `"203 900" >= '200000'` est vrai, `"199 900"` est exclu, `"200 500" >= '200000'` serait faux (espace < `0`). Vérifié : `nextjscorim_budget` (46 lignes) n'est reproduit qu'avec la comparaison de chaînes. Une comparaison numérique (`CAST`) donnerait un autre résultat.
  - Tri `ORDER BY appartements.prix` : tri **de chaînes** (`" 478 089"` en premier, `"NC"` en dernier, `"219900"` entre `"217 140"` et `"228 000"` — `nextjscorim_all`). Ordre des ex aequo non déterministe : dans les fixtures il n'est ni croissant ni décroissant par `id` (ex. `prix "205 900"` → ids `3529,3515,3547,3574,3578` dans `nextjscorim_all` mais `3529,3578,3574,3515,3547` dans `nextjscorim_cocoon`) ; il dépend de MyISAM et du plan d'exécution.
  - Pas de filtre sur `categorie` : les `Local` sont inclus (Cocoon les filtre lui-même).
- Réponse : `json_encode` d'un tableau de lignes (`[]` si aucun résultat, aucun message d'erreur), Content-Type `text/html; charset=UTF-8`, HTTP 200. Volumes au 2026-10-27 : 69 lignes sans filtre, 47 pour `residence=cocoon`, 12 `type=T2`, 1 `city=AGDE`, 46 `minBudget=200000&maxBudget=300000`, 24 `residence=cocoon&type=T3&maxBudget=400000`.
- Sans paramètre autre que `action` : fonctionne (le test `$this->input->get()` est vrai car `action` est présent).
- État prod : OK (6 fixtures 200).

### 4.2 A2 — `firstsearch` recherche par défaut (`firstsearch.php:126-156`)

Déclenchée si `action` est absent ou différent de `email|light|withref|app|nextjscorim` (ex. `action=search`), pourvu qu'au moins un paramètre GET existe.

- Paramètres : `geographique`, `livraison`, `typologie` (`!empty` sinon `0`, l.129), `budget`, `type`, `programmes` (pluriel). Tous passent par `normalize` (`model.php:149-154`, `214-219`) ; un tableau n'est pas échappé.
- Étape 1 — `ExternalSearchProgram` (`model.php:147-210`), comparaisons strictes après cast `(int)` :
  - `(int)geographique === 1` → `AND appartements.ville = 'MONTPELLIER'` ; `=== 3` → `AND appartements.ville != 'MONTPELLIER'` (l.158-162)
  - `(int)typologie !== 0` (vrai pour un tableau non vide `typologie[]=T2`, faux pour une chaîne `T2`) → `AND appartements.type IN ('T2','T3')` (l.164-168)
  - `(int)type !== 0` (faux pour `Appartement`, donc presque jamais appliqué) → `AND appartements.categorie LIKE '%<type>%'` (l.170-172)
  - `budget` 1..5 → tranches de chaînes `<= '150 000'`, `> '150 000' AND <= '200 000'`, `> '200 000' AND <= '250 000'`, `> '250 000' AND <= '300 000'`, `> '300 000'` (l.174-184)
  - `(int)livraison !== 0` → `AND (date = '1T<l>' OR date = '2T<l>' OR date = '3T<l>' OR date = '4T<l>')` (l.186-194)
  - `(int)programmes !== 0` → `AND projet.projet_id = '<programmes>'` (l.196-198)
  - Requête : `SELECT DISTINCT projet.projet_id, projet.projet_titre, projet.projet_lieu FROM projet INNER JOIN appartements ON appartements.lot = projet.projet_id WHERE statut = '' <filtres> ORDER BY projet.projet_titre` (l.200-206). Pas de filtre `archive`.
- Étape 2 — pour chaque programme, `ExternalSearch2` (`model.php:212-263`), comparaisons **lâches** différentes de l'étape 1 :
  - `geographique` : `(int) == 1` / `== 3` (l.222-226) ; `typologie` : idem étape 1 (l.228-232)
  - `$type != '0'` (l.234) : vrai pour `NULL` (PHP 5.6 compare `""` et `"0"` comme chaînes) → `AND appartements.categorie LIKE '%%'` ajouté quand `type` est absent (sans effet)
  - `$livraison != '0'` (l.250) : vrai pour `NULL` → `date = '1T' OR … '4T'` → **aucune ligne** quand `livraison` est absent (vérifié en base : 0 lot avec `date` dans `('1T','2T','3T','4T')`). `livraison=0` désactive le filtre ; `livraison=2028` filtre sur l'année.
  - Requête : `SELECT appartements.*, projet.projet_titre FROM appartements INNER JOIN projet ON appartements.lot = projet.projet_id WHERE statut='' AND appartements.lot = <projet_id> <filtres> ORDER BY projet.projet_titre, appartements.prix` (l.259). Pas de `projet_titre_key`.
- Réponse (`firstsearch.php:137-155`) :
  - Au moins un programme à l'étape 1 → `{"errors":1}` puis, pour chaque programme dont l'étape 2 renvoie des lots, `program[$count] = {"titre": projet_titre, "lieu": projet_lieu, "result": [lignes]}`. `$count` est incrémenté même quand le programme est sauté (l.157) : si un indice manque, `program` est encodé en **objet** `{"1":{…},"3":{…}}` au lieu d'un tableau. Si aucun programme n'a de lot, la clé `program` est absente : `MyFirstCallback({"errors":1});`.
  - Aucun programme → `{"errors": M1}`.
- État prod : 200 ; `first_default_empty` (`action=search`) et `first_default_prog` (`programmes=41&typologie=0`) valent tous deux exactement `MyFirstCallback({"errors":1});` (30 octets), parce que `livraison` est absent (étape 1 trouve COCOON — vérifié en base —, étape 2 ne trouve rien).

### 4.3 A3 — `firstsearch` sans paramètre (`firstsearch.php:13, 157-160`)

- Intention du code : `MyFirstCallback({"errors":"Aucunes données envoyées"});` (`$action` vaut `NULL` → branche `else` de `JsonMe`, l.177-178).
- État prod : **500**, page d'erreur Kohana (`text/html`, 3744 octets) « Undefined variable: action » en `firstsearch.php [160]` avec trace (`first_noparams.body`), car `$action` n'est défini que dans le `if` (l.14). `display_errors = TRUE` (`config.php:97`) expose chemins serveur et objets.
- Même défaut dans `search.php:45` (méthode `news`) et `search.php:122` (méthode `index`) pour un appel sans paramètre ; et dans `search/news` avec des paramètres mais `action != allnews`, `$callback` est en plus indéfini (l.19-45).

### 4.4 A4 — `firstsearch?action=light` (`firstsearch.php:37-61`)

- Paramètre : `programmes` (pluriel). Modèle : `ExternalLightSearchProgram` (`model.php:342-354`) puis `ExternalLightSearch` par programme (`model.php:356-368`), avec `escapeSQL` → `(int)` toujours 0 → toujours la branche « tous les programmes » :
  - Étape 1 exécutée : `SELECT DISTINCT projet.projet_titre, projet.projet_id, projet.projet_lieu FROM appartements INNER JOIN projet ON appartements.lot = projet.projet_id WHERE statut='' ORDER BY appartements.prix` (l.347)
  - Branche jamais atteinte : `… WHERE lot = '<p>' AND statut='' ORDER BY projet.projet_titre, appartements.prix` (l.349)
  - Étape 2 : appelée avec `$first["projet_id"]` (chaîne lue en base, ex. `"41"`) → `escapeSQL` → `"'41'"` → `(int)` 0 → `SELECT appartements.*, projet.projet_titre FROM appartements INNER JOIN projet ON appartements.lot = projet.projet_id WHERE statut='' ORDER BY appartements.prix` (l.361) : **tous** les lots disponibles de tous les programmes sous chaque programme.
- Réponse prévue : `MyFirstCallback2({"errors":1,"program":[{"titre":…,"lieu":…,"result":[…]}, …]});` ou `{"errors": M1}` (l.60).
- État prod : **500** sur tous les appels (`first_light_all`, `first_light_prog`, `first_callback`) : MySQL refuse `ORDER BY appartements.prix` absent d'un `SELECT DISTINCT` (« Expression #1 of ORDER BY clause is not in SELECT list … incompatible with DISTINCT »), page d'erreur Kohana qui affiche la requête SQL.

### 4.5 A5 — `firstsearch?action=withref` (`firstsearch.php:62-88`)

- Paramètres : `programme` (**singulier**, contrairement à `light`) et `ref`. Modèle : `ExternalLightSearchProgramWithRef` (`model.php:312-325`) puis `ExternalLightSearchWithRef` (`model.php:327-340`), `escapeSQL` sur les deux → toujours la branche sans programme :
  - Étape 1 : `SELECT DISTINCT projet.projet_titre, projet.projet_id, projet.projet_lieu FROM appartements INNER JOIN projet ON appartements.lot = projet.projet_id WHERE appartements.numero = '<ref>' ORDER BY appartements.prix` (l.318)
  - Étape 2 : `SELECT appartements.*, projet.projet_titre FROM appartements INNER JOIN projet ON appartements.lot = projet.projet_id WHERE appartements.numero = '<ref>' ORDER BY appartements.prix` (l.333)
  - **Ni filtre `statut`, ni filtre `archive`** : les lots vendus sont inclus (en base, `numero='B31'` existe dans 8 programmes dont 7 archivés avec `statut='act'`).
- Réponse prévue : `MyFirstCallback4({"errors":1,"program":[…]});` ou `{"errors": M1}` (l.87).
- État prod : **500** (`first_withref`, `first_withref_none`) pour la même raison que A4 ; requête visible dans la page d'erreur.

### 4.6 A6 — `firstsearch?action=email` (`firstsearch.php:20-36`)

- Paramètres : `email`, `url`. Validation `filter_var($email, FILTER_VALIDATE_EMAIL)` (l.23).
- Valide → `ExternalSendEmail` (`model.php:136-145`) : `INSERT INTO docs VALUES (NULL, '<email>', '<url>', <time()>)` (valeurs via `escapeSQL`). Aucun email envoyé malgré le nom.
- Réponse : `MyFirstCallback3({"errors":1,"data":"<url>"});` si `count()` == 1 ; sinon `{"errors":"Database error","data":"<url>"}` ; email invalide → `{"errors":"Votre adresse email n'est pas valide","data":"<url>"}`. `data` vaut `null` si `url` est absent. Ordre des clés : `errors` puis `data`.
- État prod : non testé (écrit en base — interdit pendant l'enregistrement). Dernière ligne de `docs` dans l'instantané local : timestamp 1652039483 (mai 2022).

### 4.7 A7 — `firstsearch?action=app` (`firstsearch.php:89-110`)

- Paramètres : `projet` (défaut `'0'`), `typologie` (tableau `typologie[]` envoyé par `V_app.php:33-73`, défaut `'0'`), `budget` (`V_app.php:82`).
- Comportement prod : `var_dump($projet); var_dump($typologie); var_dump($budget); die();` (l.99-102) → sortie texte brute (`string(1) "0"` …), sans enveloppe JSONP, sans `Content-Type` JSON. Code mort ensuite.
- Comportement prévu (l.103-110) : `AppSearchProgram` (`model.php:102-134`) : `SELECT appartements.*, projet.projet_titre, projet.projet_titre_key FROM appartements INNER JOIN projet ON appartements.lot = projet.projet_id WHERE statut='' AND appartements.lot = '<projet>' [AND appartements.type IN (…)] [tranche budget] ORDER BY projet.projet_titre, appartements.prix` ; `typologie != '0'` → `IN` sur le tableau non échappé ; `budget` tranches identiques à A2. Réponse `MyFirstCallback({"errors":1,"program":[lignes à plat]});` ou `{"errors": M3}`. Consommé par `OLD/medias/js/app.js:42-79` (attend `program[].projet_titre_key` et `numero`).

### 4.8 A8 — `search` recherche par défaut (`search.php:102-117`)

- Toute `action` autre que `email|light|withref` sur `search` ou `search/index`, **y compris `action=allnews`** (qui n'est traité que dans `news()`, l.19).
- Paramètres : `geographique`, `livraison`, `typologie` (`'0'` si vide, l.106), `budget`, `type`. Modèle `ExternalSearch` (`model.php:265-310`) : filtres comme `ExternalSearch2` mais `typologie != '0'` (l.280), et `livraison != '0'` → `AND appartements.date = '<livraison>'` (valeur complète, ex. `3T2028`, l.302-304). Requête : `SELECT appartements.*, projet.projet_titre FROM appartements INNER JOIN projet ON appartements.lot = projet.projet_id WHERE statut='' <filtres> ORDER BY projet.projet_titre, appartements.prix` (l.306). Pas de filtre `archive`.
- Sans `livraison` → `date = ''` → aucune ligne → `{"errors": M2}`.
- Réponse : `{"errors":1,"data":[lignes]}` ou `{"errors": M2}` ; callback `MyCallback`, ou `MyFirstCallbackNews` si `action=allnews`.
- État prod : `search_allnews` = 200, `MyFirstCallbackNews({"errors": M2})`, 271 octets, `text/html; charset=UTF-8` — c'est la recherche vide, pas les flash infos.

### 4.9 A9 — `search?action=light` (`search.php:78-88`)

- Paramètre : `programmes`. `ExternalLightSearch` (`model.php:356-368`) avec le même défaut `escapeSQL`/`(int)` → toujours `SELECT appartements.*, projet.projet_titre FROM appartements INNER JOIN projet ON appartements.lot = projet.projet_id WHERE statut='' ORDER BY appartements.prix` (l.361) : **tous** les lots disponibles, **archivés compris** (fixture : 149 lots des programmes 29, 30, 33, 35, 36, 37, 38, 39, 40, 41, dont 33, 35, 38 archivés), sans `projet_titre_key`, tri de chaînes sur `prix`.
- Réponse : `MyCallback2({"errors":1,"data":[…]});` ou `{"errors": M2}` (l.86), `text/html; charset=UTF-8`.
- État prod : OK 200 (`search_light_prog`, 57 338 octets), reproduit octet pour octet sur la base locale.

### 4.10 A10 — `search?action=withref` (`search.php:89-101`)

- Appelle `$DB->ExternalSearchWithRef($programme,$ref)` (l.93) qui **n'existe pas** dans `model.php` (seules `ExternalLightSearchWithRef` et `ExternalLightSearchProgramWithRef` existent, l.312, 327) → erreur fatale PHP. Callback prévu `MyFirstCallback4` (l.135-136), message M1 (l.99). Non enregistré.

### 4.11 A11 — `search?action=email` (`search.php:60-77`)

Identique à A6 (même modèle, mêmes messages, même ordre des clés), callback `MyCallback3`, `text/html`.

### 4.12 A12 — `search/news?action=allnews` (`search.php:7-47`)

- `getAllInfos` (`model.php:72-76`) : `SELECT * FROM news ORDER BY date DESC`.
- Réponse : `MyFirstCallbackNews({"errors":1,"data":[{"id":…,"string1":…,"string2":…,"string3":…,"string4":…,"url":…,"thumb":…}, …]});` — chaque valeur passée par `htmlspecialchars_decode` donc **chaîne** (`id` compris) ; ordre des clés `id, string1, string2, string3, string4, url, thumb` (l.26-32) ; `date` non renvoyée. Vide → `{"errors":"<b>Aucun flash info actuellement.</b>"}` (l.36).
- Base locale : 3 lignes dans `news`. État prod : non enregistré.

### 4.13 Récapitulatif de forme

| Point | Enveloppe | Forme du JSON (succès) |
|---|---|---|
| A1 | aucune | `[ {appartements.* , projet_titre, projet_titre_key}, … ]` |
| A2 / A4 / A5 | `MyFirstCallback` / `2` / `4` | `{"errors":1,"program":[{"titre","lieu","result":[{appartements.*, projet_titre}]}]}` |
| A7 (prévu) | `MyFirstCallback` | `{"errors":1,"program":[{appartements.*, projet_titre, projet_titre_key}]}` |
| A8 / A9 / A10 | `MyCallback` (`MyFirstCallbackNews` si allnews) / `MyCallback2` / `MyFirstCallback4` | `{"errors":1,"data":[{appartements.*, projet_titre}]}` |
| A6 / A11 | `MyFirstCallback3` / `MyCallback3` | `{"errors":1,"data":"<url>"}` |
| A12 | `MyFirstCallbackNews` | `{"errors":1,"data":[{id,string1..4,url,thumb}]}` |

## 5. Données

| Table | Moteur / collation (base locale) | Colonnes lues | Écrite par |
|---|---|---|---|
| `appartements` | MyISAM, `latin1_swedish_ci` | toutes (`appartements.*`) ; filtres sur `lot`, `statut`, `type`, `ville`, `categorie`, `prix`, `date`, `numero` | — (lecture seule ici) |
| `projet` | MyISAM, `latin1_swedish_ci` | `projet_id`, `projet_titre`, `projet_titre_key`, `projet_lieu`, `archive` | — |
| `news` | InnoDB, `latin1_swedish_ci` | toutes | — |
| `docs` | InnoDB, `utf8mb3` | — | A6, A11 : `INSERT INTO docs VALUES (NULL,$email,$url,$time)` (`model.php:142`) ; colonnes `iddocs`, `email` (text), `url` (text), `time` (varchar(20), timestamp Unix) |

Règles implicites :
- Lot disponible = `appartements.statut = ''` (autres valeurs en base : `opt`, `res`, `act`).
- Programme actif = `projet.archive = 0` — appliqué **seulement** par A1 ; A2, A4, A5, A8, A9 renvoient aussi les programmes archivés.
- Jointure `appartements.lot` (varchar(20)) = `projet.projet_id` (int).
- `date` de livraison au format `<trimestre>T<année>` (`3T2028`).
- Tous les champs « numériques » (`prix`, `superficie`, `terrasse`, `prix_m2`) sont des varchar à format libre (`"12,67"`, `"185 000"`).
- `projet_titre_key` = md5 servant de nom de dossier sous `medias/programmes/`.
- Requêtes SQL toutes construites par concaténation (voir §9). `model.php:8` ouvre en plus une connexion `mysqli_connect` inutilisée avec des identifiants en clair dans le code (non recopiés ici).

## 6. Emails

Aucun email envoyé par ce module. `ExternalSendEmail` (`model.php:136-145`) se contente d'insérer dans `docs` ; la lecture de `docs` (`getEmailsCorimPromotion`, `model.php:85-89`) relève du module `communication`.

## 7. Fichiers

- A13 : `medias/programmes/{projet_titre_key}/plans/plan-{numero sans espaces}.pdf`, servi directement par Apache (`.htaccess:16-18` : fichier existant servi tel quel ; sinon réécrit vers Kohana).
- Nommage à l'écriture : `plan-` + `str_replace(' ', '', numero)` + `.pdf` (`gerer_lots.php:54`, `ajouter_lot.php:59`, contrôle d'existence `json.php:1571-1572`) — module `programmes-lots`.
- Consommateurs : corim-promotion retire les espaces (`SearchPageContent.js:137`), `app.js:57` retire les blancs (`/\s+/g`), **Cocoon non** (`AppartmentSection.astro:34`). Lot concerné aujourd'hui : un lot disponible de COCOON a `numero = " A1"` (espace en tête) → Cocoon génère `plan- A1.pdf`, lien déjà cassé en prod si le fichier s'appelle `plan-A1.pdf` (fichier non présent localement, à vérifier).
- Copie locale incomplète : sur 8 programmes actifs, seuls 2 dossiers existent sous `OLD/medias/programmes/` (dont celui de NATURA avec 34 plans) ; COCOON (`5714ed1c…`) est absent. La synchronisation des fichiers prod est un prérequis du test de A13.
- 404 sur plan absent : la réécriture envoie vers Kohana (`.htaccess:17-21`) ; réponse exacte non enregistrée.

## 8. Design

Sans objet (aucune vue rendue). `V_Json` (`views/V_Json.php:1`) est instanciée (`firstsearch.php:10`, `search.php:9, 50`) mais jamais affichée ; elle ne contient que la garde `SYSPATH`. Le gabarit `template/json.php` n'émet rien. Ne pas utiliser de layout Blade pour ces routes.

## 9. Bugs et failles constatés

| Problème | Fichier:ligne | Décision proposée (reproduire / corriger) |
|---|---|---|
| Injection SQL non authentifiée : `type`, `city`, `residence`, `minBudget`, `maxBudget` concaténés sans échappement | `firstsearch.php:117-121` | Corriger : paramètres liés (`?`), même sémantique (LIKE sans `%` ajouté, comparaisons de chaînes) |
| Injection via tableaux `typologie[]` (non échappés par `normalize`) et via `\` final (htmlspecialchars n'échappe pas `\`) | `model.php:13, 110, 165, 229, 281` | Corriger : `whereIn` lié |
| `escapeSQL` puis `(int)` : le filtre programme de `light`/`withref` n'est jamais appliqué | `model.php:314-320, 329-335, 344-349, 358-363` | A9 : **reproduire** (réponse 200 enregistrée, consommateurs inconnus) ; A4/A5 : voir §11 Q2 |
| `SELECT DISTINCT … ORDER BY appartements.prix` → 500 | `model.php:318, 347` (et 320, 349 jamais atteintes) | Corriger (consigne CLAUDE.md : comportement voulu, même forme JSONP) |
| `$action` indéfini sans paramètre → 500 | `firstsearch.php:160`, `search.php:45, 122` | Corriger : renvoyer M4 dans l'enveloppe par défaut |
| `$callback` indéfini dans `search/news` si `action != allnews` → 500 | `search.php:19-45` | Corriger : même forme que A3 (`{"errors":…}`), contenu exact en §11 Q6 |
| `callback` ignoré (`$call` hors portée) | `firstsearch.php:168`, `search.php:128` | Reproduire : noms de callback figés (consigne CLAUDE.md), ne jamais refléter `callback` (évite aussi un XSS JSONP) |
| Recherche par défaut toujours vide sans `livraison` (`NULL != '0'`) | `model.php:250, 302` | Reproduire (fixtures `first_default_*`, `search_allnews` = réponse « vide ») ; voir §11 Q3 |
| Indices `program` non contigus → objet JSON au lieu de tableau | `firstsearch.php:52-57, 79-84, 146-151` | Reproduire (même `$count`) |
| `action=app` : `var_dump` + `die()` | `firstsearch.php:99-102` | Corriger : implémenter le comportement prévu (l.103-110), voir §11 Q4 |
| `search?action=withref` : méthode inexistante | `search.php:93` | Corriger : décision en §11 Q5 |
| Pas de filtre `archive` hors A1 ; `withref` sans filtre `statut` (lots vendus exposés, `client`/`vendeur` compris) | `model.php:201-206, 259, 306, 318, 333, 361` | Reproduire pour A9 (enregistré) ; question pour les autres (§11 Q2) |
| Pages d'erreur Kohana avec SQL, chemins, objets (`display_errors = TRUE`) | `config.php:97`, fixtures 500 | Corriger : `APP_DEBUG=false`, page d'erreur générique |
| Session démarrée et cookie posé à chaque appel public | `__json.php:11` | Corriger : routes sans session ni cookie (aucun consommateur ne l'utilise) ; voir §11 Q7 |
| Identifiants MySQL en clair dans le modèle | `model.php:8` | Corriger : `.env` uniquement |
| Écriture anonyme illimitée dans `docs` (spam, données personnelles) | `model.php:142` | Garder le contrat de réponse ; ajouter limitation de débit ; voir §11 Q1 |
| `nextjscorim` sert du JSON en `text/html` | `firstsearch.php:124` | Reproduire (consommateurs appellent `.json()` sans regarder le type) |
| JSONP `firstsearch` en `application/json` | `firstsearch.php:166` | Reproduire ; **ne pas** poser `X-Content-Type-Options: nosniff` sur ces routes (bloquerait le chargement en `<script>`) — voir §11 Q8 |

## 10. Critères d'acceptation

Conventions pour les tests Pest de rejeu :
- Base de test = instantané prod du 2026-10-27 (base locale actuelle). Rejeu : `GET /index.php/<chemin du manifest>` (et variante sans `index.php/`).
- **Strict** = corps identique octet pour octet au `.body`, plus code HTTP et `Content-Type` égaux à ceux du `.headers`.
- **Strict modulo ex aequo** = corps décodé, la suite des `prix` est identique à la fixture, et pour chaque groupe de `prix` égaux l'ensemble des objets (comparés champ par champ, types compris) est identique ; puis réencodé avec `json_encode` sans option après tri des ex aequo par `id`, le résultat est égal à la fixture triée de même. Nécessaire car l'ordre des ex aequo dépend de MyISAM (§4.1).
- **Structurel** = enveloppe, noms et ordre des clés, types (chaînes / `null` / entier `1`) vérifiés ; valeurs non comparées.

Points d'accès OK en prod :
1. `nextjscorim_cocoon` (`firstsearch?action=nextjscorim&residence=cocoon`) : strict modulo ex aequo, HTTP 200, `Content-Type: text/html; charset=UTF-8`, 47 objets.
2. `nextjscorim_all` : strict modulo ex aequo, 69 objets.
3. `nextjscorim_type_t2` : strict modulo ex aequo, 12 objets.
4. `nextjscorim_city` : strict (1 objet), corps = `nextjscorim_city.body`.
5. `nextjscorim_budget` : strict modulo ex aequo, 46 objets ; inclut `"203 900"` et `"219900"`, exclut `"199 900"` (comparaison de chaînes).
6. `nextjscorim_combo` : strict modulo ex aequo, 24 objets.
7. Pour toute réponse A1 : chaque objet a exactement les 25 clés de §4.0 dans cet ordre ; toutes les valeurs sont des chaînes sauf `vendeur` (chaîne ou `null`) ; `prix` n'est jamais un nombre JSON ; `ville` jamais `null`.
8. A1 avec `residence=COCOON`, `residence=Cocoon` → même résultat que `cocoon` ; `city=castelnau le lez` → mêmes lignes que `city=CASTELNAU LE LEZ` ; `residence=carre vias` trouve `CARRÉ VIAS` (insensible casse et accents).
9. A1 avec `type=&city=&minBudget=&maxBudget=` et `residence=undefined` (appel réel de corim-promotion sans recherche) → `[]`, HTTP 200.
10. A1 avec un paramètre valant `"0"` → paramètre ignoré (égal à la réponse sans ce paramètre).
11. A1 avec `residence=%25coc%25` → mêmes lignes que `residence=cocoon` (jokers LIKE conservés).
12. A1 avec `residence=cocoon' OR '1'='1` → `[]`, HTTP 200, aucune erreur SQL (injection neutralisée).
13. A1 : aucun lot d'un programme `archive=1` ni de `statut != ''` n'apparaît.
14. `first_default_empty` (`firstsearch?action=search`) : strict, corps `MyFirstCallback({"errors":1});`, `Content-Type: application/json`.
15. `first_default_prog` (`firstsearch?programmes=41&typologie=0`) : strict, corps `MyFirstCallback({"errors":1});`.
16. A2 avec `programmes=41&livraison=2028` → `MyFirstCallback({"errors":1,"program":[{"titre":"COCOON","lieu":"CASTELNAU LE LEZ","result":[…]}]});`, `result` = lots `statut=''` de COCOON avec date `1T2028`…`4T2028`, triés `prix` (chaîne), objets à 24 clés (sans `projet_titre_key`) ; structurel.
17. A2 avec un filtre qui ne trouve aucun programme (ex. `programmes=999999`) → `MyFirstCallback({"errors":"<b>Aucun résultat … href=\"http:\/\/corim-promotion.com\/contactez-nous\/\"…"});` strict (message M1 encodé, `http`).
18. `search_allnews` (`search?action=allnews`) : strict, 271 octets, `Content-Type: text/html; charset=UTF-8`.
19. `search_light_prog` (`search/index?action=light&programmes=41`) : strict modulo ex aequo, `MyCallback2(` … `);`, 149 objets à 24 clés, programmes archivés inclus.
20. `search?action=light&callback=foo` → commence par `MyCallback2(` ; aucune réponse ne commence jamais par la valeur de `callback`.

Points d'accès en 500 en prod (comportement voulu, forme JSONP conservée, tests structurels) :
21. `first_noparams` (`firstsearch`) → HTTP 200, `Content-Type: application/json`, corps exact `MyFirstCallback({"errors":"Aucunes données envoyées"});`.
22. `search` sans paramètre → HTTP 200, `Content-Type: text/html; charset=UTF-8`, corps exact `MyCallback({"errors":"Aucunes données envoyées"});` (callback de la branche `else`, `search.php:139-140`) ; `search/news` sans paramètre → même corps (sous réserve §11 Q6).
23. `first_light_all` (`firstsearch?action=light&programmes=0`) → HTTP 200, `application/json`, `MyFirstCallback2({"errors":1,"program":[{"titre":string,"lieu":string,"result":[objets à 24 clés]}…]});` ; un élément par programme ayant des lots `statut=''`.
24. `first_light_prog` (`programmes=41`) → même forme ; contenu selon décision §11 Q2.
25. `first_callback` (`action=light&programmes=41&callback=foo`) → identique au critère 24 (callback ignoré).
26. `first_withref` (`action=withref&programme=41&ref=B31`) → HTTP 200, `MyFirstCallback4({"errors":1,"program":[…]});` avec au moins le lot B31 de COCOON ; contenu exact selon §11 Q2.
27. `first_withref_none` (`action=withref&programme=29&ref=ZZZ`) → strict : `MyFirstCallback4({"errors":"<b>Aucun résultat pour cette recherche.<\/b><br \/><br \/>Vous pouvez nous contacter directement pour tous renseignements complémentaires en cliquant <a href=\"http:\/\/corim-promotion.com\/contactez-nous\/\"><b>ici<\/b><\/a>"});`.
28. Aucune réponse de l'API ne contient `SELECT`, `sql_corim_vente_`, `/var/www` ni de trace d'exécution, même sur paramètre invalide.

Non enregistrés (tests sur base de test jetable) :
29. A6 `firstsearch?action=email&email=<adresse valide>&url=https://exemple.test/doc.pdf` → `MyFirstCallback3({"errors":1,"data":"https:\/\/exemple.test\/doc.pdf"});`, une ligne ajoutée dans `docs` (`time` = timestamp Unix en chaîne).
30. A6 avec `email=invalide` → `MyFirstCallback3({"errors":"Votre adresse email n'est pas valide","data":"…"});`, aucune ligne ajoutée ; sans `url` → `"data":null`.
31. A11 : mêmes cas avec `MyCallback3`, `Content-Type: text/html; charset=UTF-8`.
32. A12 `search/news?action=allnews` → `MyFirstCallbackNews({"errors":1,"data":[…]});`, 3 objets (base du 2026-10-27), clés `id,string1,string2,string3,string4,url,thumb`, toutes chaînes, ordre `date DESC` ; table vide → `{"errors":"<b>Aucun flash info actuellement.<\/b>"}`.
33. A7 `firstsearch?action=app&projet=41&budget=0` → `MyFirstCallback({"errors":1,"program":[…]});` (lots à 25 clés avec `projet_titre_key`), `application/json` ; `projet` absent → `{"errors":"<b>Aucun résultat pour cette recherche.<\/b>"}` (sous réserve §11 Q4).
34. A13 `GET /medias/programmes/baa692ce3ec9e7dffe643f89936729cb/plans/plan-B202.pdf` (si présent après synchro) → 200, `Content-Type: application/pdf`, octets identiques au fichier d'origine ; chaque lot renvoyé par A1 dont le plan existe dans `OLD/medias` doit l'avoir au même chemin dans NEW.
35. Variantes d'URL : `/firstsearch?action=nextjscorim` et `/index.php/firstsearch/index?action=nextjscorim` renvoient la même chose que `/index.php/firstsearch?action=nextjscorim` ; `http://` redirige vers `https://` (même chemin et query).
36. Encodage : les corps contiennent `\/` et `É` (pas `/` brut ni `É` brut, pas `<`) — vérifie que l'encodeur est `json_encode` sans option et non `JsonResponse` de Laravel/Symfony (qui applique `JSON_HEX_*`).
37. Types : aucun entier JSON dans les lignes de lots (`id`, `lot`, `dept` restent des chaînes) — vérifie que PDO (qui renvoie des entiers natifs depuis PHP 8.1) est neutralisé (`PDO::ATTR_STRINGIFY_FETCHES` ou cast explicite).
38. Limitation de débit active sur toutes les routes de l'API sans modifier les réponses en dessous du seuil ; aucune réponse ne pose de cookie de session.

## 11. Questions ouvertes

1. **Consommateurs JSONP** : qui appelle encore A2, A4-A12 (ancien site WordPress corim-promotion, autres partenaires) ? Seuls `nextjscorim` (cocoon, corim-promotion) et `action=app` (interne) ont un consommateur trouvé localement. À trancher avec les logs d'accès Apache de prod avant de figer (ou de supprimer) chaque action, surtout `email` (dernière écriture dans `docs` de l'instantané : mai 2022).
2. **Filtre programme de `light` / `withref`** : le code n'a jamais filtré (bug `escapeSQL` + `(int)`), et `withref` ignore `statut`/`archive`. Comportement « voulu » à implémenter pour A4/A5 : filtrer par `programmes`/`programme` quand il est non nul (intention lisible `model.php:320, 335, 349, 363`), avec `statut=''` ? Et pour A9 qui marche aujourd'hui sans filtre : garder la réponse actuelle (149 lots, archivés compris) ou appliquer le filtre ? Ordre des programmes pour A4 « tous » : `projet_titre` (comme la branche filtrée) ou prix minimal ?
3. **Recherche par défaut toujours vide** sans `livraison` (A2, A8) : reproduire le bug (réponses enregistrées) ou corriger (`livraison` absente = pas de filtre) ? Une correction change les réponses de `first_default_*` et `search_allnews`.
4. **`action=app`** : la page interne `/app` (`V_app.php`) est-elle encore utilisée ? Si oui, `projet` absent donne `appartements.lot = '0'` (aucun résultat) : est-ce voulu ? Ce point relève peut-être du module `public`.
5. **`search?action=withref`** : brancher sur `ExternalLightSearchWithRef` (forme `{"errors":1,"data":[…]}`), ou renvoyer M1 ? Aucune trace d'usage.
6. **`search/news` sans `action=allnews`** et **`search` sans paramètre** : corps exact attendu (`MyCallback({"errors":"Aucunes données envoyées"})` ? ) — non déductible car le code plante avant d'écrire.
7. **En-têtes** : faut-il conserver `Cache-Control: no-store…`/`Pragma`/`Expires` (aujourd'hui effet de bord de la session) ? Un cache côté serveur est prévu ; un cache HTTP côté consommateur changerait la fraîcheur pour corim-promotion (`revalidate: 0`).
8. **En-têtes de sécurité** : la check-list prévoit CSP/nosniff globaux. `nosniff` sur une réponse JSONP `application/json` empêche son exécution en `<script>` dans les navigateurs. Exclure ces routes, ou passer le JSONP en `application/javascript` (modification de `Content-Type` visible des consommateurs) ?
9. **Ordre des ex aequo sur `prix`** : accepter l'ordre du nouveau moteur (InnoDB) ou ajouter `appartements.id` comme second critère de tri pour un résultat stable ? Aucun consommateur trouvé ne dépend de cet ordre (corim-promotion retrie, Cocoon calcule un minimum).
10. **Plans** : le fichier du lot COCOON `numero = " A1"` existe-t-il sous `plan-A1.pdf` ou `plan- A1.pdf` en prod ? (Cocoon n'ôte pas les espaces.) Et quelle réponse exacte sur un plan absent (page 404 Kohana, `404.html` racine) ? Non enregistré, pas de dossier COCOON en local.
11. **Base d'URL des fixtures** : `manifest.tsv` donne des chemins relatifs (`firstsearch?…`) ; ont-ils été enregistrés sous `https://corim-vente.com/index.php/` (comme les consommateurs) ou sous `/` ? Sans effet sur le code (les deux routes mènent au même contrôleur) mais à préciser pour le test de rejeu.
12. **`xss_clean` global** : sa transformation exacte des valeurs inhabituelles (`<`, `javascript:`…) n'est pas reproduite ; acceptable puisque les requêtes deviennent paramétrées ?
