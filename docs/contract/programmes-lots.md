# Contrat — programmes-lots

Statut : brouillon (à valider par Victor)
Sources lues :
- `OLD/application/controllers/gestion_programmes.php` (1-94), `ajouter_programme.php` (1-121), `modifier_programme.php` (1-160), `programme.php` (1-86), `ajouter_lot.php` (1-97), `ajouter_lots.php` (1-122), `gerer_lots.php` (1-87), `modifier_appartement.php` (1-45), `ajouter_plan.php` (1-46), `administration.php` (1-111), `__main.php` (1-104), `__json.php` (1-48)
- `OLD/application/controllers/json.php` : 1-13, 48-77 (`DeleteFile`, `DeleteAllInProject`), 293-312 (`SupprimerLot`), 1170-1214 (`DeleteDiapo`, `ArchivageProjet`, `DesarchivageProjet`), 1519-1605 (`JsonDatagrid`, `JsonMe`)
- `OLD/application/models/model.php` : 1-23, 85-89, 136-145, 570-603, 659-727, 824-872, 1369-1385, 1568-1576, 1652-1705, 1848-1883, 2098-2306
- `OLD/application/views/` : `V_gestion_programme.php`, `V_ajouter_programme.php`, `V_modifier_programme.php`, `V_gestion_diaporama.php`, `V_programme.php` (1-197, 392-672), `V_gerer_lots.php`, `V_modifier_lot.php`, `V_ajouter_appartement.php`, `V_ajouter_appartements.php`, `V_ajouter_plan.php`, `V_modifier_appartement.php`, `V_Json.php`, `template/template.php` (11-71, 85-110), `template/template_welcome.php` (40-55), `template/module_programmes.php`, `template/json.php`
- `OLD/medias/uploadify/uploadify.php`, `uploadify-plan.php`, `uploadify-docs.php`, `check-exists.php`
- `OLD/medias/js/global.js` : 1-18, 56-73, 109-140, 255-281, 363-397, 1333-1415
- `OLD/application/config/config.php` : 8, 22, 71, 97, 128-133 (lignes de constantes uniquement) ; `OLD/system/config/upload.php` : 7, 17 ; `OLD/system/helpers/upload.php` : 24-49 ; `OLD/system/config/image.php` : 7 ; `OLD/.htaccess`
- Base locale (lecture seule) : `SHOW CREATE TABLE projet / appartements / docs`, `DESCRIBE acces / acces_lot / ventes`, comptages et distributions de formats (chiffres masqués).
- Arborescence locale `OLD/medias/programmes/`, `OLD/medias/csv/`, `OLD/medias/upload/` (comptages uniquement).

## 1. Périmètre

Le module gère les programmes immobiliers (table `projet`) et leurs lots (table `appartements`) :

- **Administrateur** : liste des programmes en cours et archivés, création d'un programme (titre, livraison, ville, description, vignette), modification (plus département et vignette), dépôt de fichiers (visuels, documents, plans) et suppression de fichiers, archivage et réactivation.
- **Lots, côté administrateur** : liste des lots d'un programme (tableau triable et filtrable), ajout unitaire avec plan PDF, modification avec remplacement du plan, suppression unitaire ou de tous les lots, import en masse par CSV (point-virgule), dépôt en masse des plans PDF nommés `plan-{référence}.pdf`, avec un contrôle des plans manquants.
- **Fiche programme connectée** (`programme/show`, tout utilisateur connecté) : vignette, description, diaporama, fenêtre « Téléchargements » (visuels et documents), compteurs de lots actés, réservés et optionnés, grille des lots (flexigrid alimentée en JSON) avec lien « Plan » et couleur selon le statut. Les boutons Optionner, Réserver et « Dénoncer un prospect » sont affichés ici mais leur traitement relève des modules `workflow` et `clients-prospects`.
- **Diaporama** : il n'existe qu'un écran d'upload, qui ne fonctionne pas (§9). L'affichage se fait sur la fiche programme.
- Plusieurs écrans sont du code mort ou cassé (`ajouter_programme/validation`, `modifier_appartement/*`, `administration/Ajouter_Appartement`, `medias/uploadify/uploadify-docs.php`, `check-exists.php`) : voir §3 et §9.

Hors périmètre : authentification et privilèges (`auth-users-privileges`), options, réservations et ventes (`workflow`), dénonciation de prospect (`clients-prospects`), API publique `firstsearch` (`public-api`), rapport `report.php` (il réutilise `GetSum*`, model.php:659-727).

## 2. Rôles et droits

Valeurs de session : `SessionId` (id utilisateur) et `SessionAcces` (`'1'` = admin, `'0'` = prescripteur), issues de `privileges.acces` (détail dans le contrat `auth-users-privileges`).

| Rôle (valeur en base) | Peut faire | Contrôle dans l'ancien code (fichier:ligne) |
|---|---|---|
| Admin (`privileges.acces = 1`, `SessionAcces === '1'`) | Toutes les pages du §3, voit tous les lots dans la grille | `isConnected()` __main.php:46-53 puis `isAdmin()` __main.php:55-61, appelés dans gestion_programmes.php:10-13, ajouter_programme.php:8-11 et 77-80, modifier_programme.php:10-13, ajouter_lot.php:13-17, ajouter_lots.php:16-19, gerer_lots.php:10-13, modifier_appartement.php:10-13, ajouter_plan.php:16-19 |
| Prescripteur (`privileges.acces = 0`, `SessionAcces === '0'`) | Fiche `programme/show/{key}` de **n'importe quel** programme. La grille ne montre que les lots présents dans `acces_lot` pour son id | `isConnected()` seul (programme.php:10, 17). Filtre des lots model.php:2125-2136 et 2146-2157 |
| Anonyme | En pratique **tout le §4** (aucun contrôle) et les 4 scripts `medias/uploadify/*.php` (exécutés hors Kohana) | Aucun : `__Json_Controller::__construct` (__json.php:8-15) ne vérifie rien, et les actions JSON n'appellent pas `isConnected()` |

Point essentiel : quand `isAdmin()` échoue, le code envoie `header("Location: ...")` **sans `exit()`** (par exemple gestion_programmes.php:11-13, ajouter_programme.php:9-11, ajouter_lot.php:15-17, ajouter_lots.php:17-19, gerer_lots.php:11-13, modifier_programme.php:11-13, ajouter_plan.php:17-19, modifier_appartement.php:11-13). Le traitement POST continue donc pour un prescripteur connecté : il peut créer ou modifier des programmes et des lots. Seul `isConnected()` fait un `exit()` (__main.php:50-51).

## 3. Écrans et URL

Préfixe : `SITE_URL = '/'` (config.php:8, 128). Les URL sont accessibles avec et sans `index.php/` grâce à la réécriture `.htaccess:17-21` (`index_page = 'index.php'`, config.php:22). Routage Kohana 2 classique `/{contrôleur}/{méthode}/{arguments}`. Titre de page par défaut : `'Corim promotion - '` (__main.php:43), complété par `.=` sauf mention contraire. Gabarit : `template/template` (__main.php:38). Le menu « Programmes » vers `gestion_programmes` n'est affiché qu'à l'admin (template.php:92-97, template_welcome.php:48).

### 3.1 `GET /gestion_programmes`
- Rôle : admin. Contrôleur gestion_programmes.php:16-23. Vue `V_gestion_programme`.
- Titre (affectation `=`, pas `.=`) : `Corim Promotion - Gestion des programmes` (gestion_programmes.php:21).
- Données : `GetProgrammes()` (archive=0, tri `projet_id DESC`, model.php:1694-1700) et `GetProgrammesArchive()` (archive=1, ordre non spécifié, model.php:1702-1705).
- Affichage (V_gestion_programme.php) :
  - En-tête « Gestion des programmes », avec le lien « Ajouter un programme » vers `ajouter_programme` (ligne 2).
  - Bloc `<h4>PROGRAMMES EN COURS</h4>`, tableau `table table-striped table-hover` (25-26). Chaque ligne contient : la vignette (src = concaténation de tous les fichiers de `thumb/`, 5-23 et 28-32) avec un lien vers `modifier_programme/show/{key}/` ; le titre, le lieu et `(projet_id)` ; « ARCHIVER LE PROJET » (`a.push_archivage rel={key}`) ; « GÉRER LES LOTS » vers `gerer_lots/show/{key}` ; « AJOUTER EN MASSE » vers `ajouter_lots/show/{key}` ; « AJOUTER LES PLANS » vers `ajouter_plan/show/{key}` (30-37).
  - Bloc `<h4>PROGRAMMES ARCHIVÉS</h4>`, identique mais avec « RÉACTIVER LE PROJET » (`a.push_desarchivage`) (43-57).
- Les fonctions JS d'archivage et de réactivation sont décrites au §4.

### 3.2 `GET|POST /gestion_programmes/diaporama_projet/{key}`
- Rôle : admin. gestion_programmes.php:25-73. Vue `V_gestion_diaporama`. Titre (`=`) : `Corim Promotion - Gestion du diaporama`.
- GET : affiche la vignette, le libellé `STRTOUPPER(titre - lieu)`, la description, puis les images de `diapo/` en tableau de 4 colonnes, chacune avec un lien « Supprimer » (`a.delete_diapo rel={chemin}`) (V_gestion_diaporama.php:3-66).
- POST : le formulaire n'envoie que `diapo` (fichier) (V_gestion_diaporama.php:41-45). Le contrôleur exige `titre`, `ville`, `description` et `diapo` (ligne 35), il affiche donc toujours « Tous les champs sont requis » (68). S'ils étaient fournis, il **créerait un nouveau programme** (copie de l'ajout, 37-58) avec un appel `AddProgramme` à 4 arguments au lieu de 5. Aucun lien de l'application ne mène à cet écran (recherche sur `diaporama_projet` : seul le formulaire de la vue elle-même).
- Décision proposée : voir §9 et §11 (Q1).

### 3.3 `GET|POST /ajouter_programme`
- Rôle : admin. ajouter_programme.php:7-74. Vue `V_ajouter_programme`. Titre : `Corim promotion - Ajouter un programme`.
- Formulaire `multipart/form-data` en POST vers `ajouter_programme` (V_ajouter_programme.php:13). Champs :

| Nom | Type | Obligatoire |
|---|---|---|
| `titre` | texte | oui |
| `livraison` | texte (aide « Indiquer par ex: **2T2015** pour 2ème Trimestre 2015 », ligne 23) | oui |
| `ville` | texte | oui |
| `thumb` | fichier | oui |
| `description` | textarea | oui |

- Traitement (ligne 15) : si un champ est vide ou s'il n'y a pas de fichier, message « Tous les champs sont requis » (70).
  - Validation de `thumb` : `upload::valid`, `upload::type[gif,jpg,png]`, `upload::size[5M]` (18). En cas d'échec : « L'image doit être au format .gif, .jpg, .png et doit être inférieur à 5M » (65).
  - `key = md5(titre)` (20). L'extension est celle du nom d'origine (`pathinfo`, casse conservée) (23).
  - Création de `medias/programmes/{key}/` et de `thumb`, `diapo`, `documents`, `plans`, `visuels` en 0755 (27-55).
  - `upload::save` copie l'original dans `medias/upload/{time}{nom}` (§7). La vignette est redimensionnée à 120 px de large (`Image::WIDTH`, driver GD) puis enregistrée en `thumb/thumb.{ext}` (57-60).
  - `AddProgramme(titre, livraison, key, ville, description)` (62, model.php:2171-2183), puis message « Le programme a été ajouté » (63).
- Messages : `div.alert.alert-warning` pour une erreur, `div.alert.alert-success` pour un succès (V_ajouter_programme.php:6-10). Pas de redirection. Le formulaire est réaffiché vide.

### 3.4 `POST /ajouter_programme/validation`
- ajouter_programme.php:76-115. Code mort : il exige `$_FILES['diapo']`, n'utilise pas `livraison` et appelle `AddProgramme` avec 4 arguments au lieu de 5 (104). Aucun formulaire ne poste vers cette URL. Décision : ne pas reproduire (§9).

### 3.5 `GET /modifier_programme`
- modifier_programme.php:16-21 : redirige vers `gestion_programmes` (sans exit).

### 3.6 `GET|POST /modifier_programme/show/{key}`
- Rôle : admin. modifier_programme.php:23-114. Vue `V_modifier_programme`. Titre : `Corim promotion - Modifier un programme`. Si `{key}` est absent : `exit()`, page blanche (25-27).
- GET : formulaire « Informations générales » pré-rempli depuis `Get_infos_programmes(key)` (111, model.php:2098-2104). Affiche la vignette `thumb.jpg`, sinon `.png`, sinon `.gif` (minuscules uniquement), sinon « Aucune image » (V_modifier_programme.php:45-66). Viennent ensuite les blocs « Téléchargement de fichier », « Suppression de fichier », puis les listes Visuels, Documents et Plans (101-149).
- Listes de fichiers (modifier_programme.php:116-134) : ordre de `readdir`, sans tri. Chaque fichier donne `<li class="list-group-item"><input type="checkbox" name="selectfile[]" value="{/medias/programmes/key/type/fichier}" />&nbsp;<a href="..." target="_blank">{fichier}</a> <a href="#" class="file btn btn-danger btn-xs pull-right" rel="...">SUPPRIMER</a></li>`.
- POST `action=project` (39-78). Champs :

| Nom | Type | Obligatoire |
|---|---|---|
| `titre` | texte | oui |
| `livraison` | texte | oui |
| `ville` | texte | oui |
| `dept` | texte | oui |
| `description` | textarea | oui |
| `thumb` | fichier | non |
| `key` | caché | — |
| `idprojet` | caché | — |
| `action=project` | caché | — |

  - Si un champ est vide : « Tous les champs sont requis » (77).
  - Si une vignette est envoyée : validation `gif,jpg,png`, `10M` (45). En cas d'échec, le message dit pourtant « … inférieur à 5M » (63). Si elle est valide : suppression de **tous** les fichiers de `medias/programmes/{$_POST['key']}/thumb/`, puis enregistrement de `thumb.{ext}` en 120 px de large (48-60).
  - Ensuite, `UpdateProgramme(titre, livraison, ville, description, key, idprojet, dept)` est **toujours** appelé (66-74), même si la vignette est refusée. Les deux messages peuvent alors s'afficher ensemble. Message de succès : « Le programme a bien été modifié » (75).
  - Effet de bord métier : `UpdateProgramme` recopie `ville`, `dept` et `date = livraison` sur **tous les lots** du programme (model.php:2195, §5).
  - La clé `projet_titre_key` ne change pas quand le titre change (model.php:2186).
- POST `action=uploader` (79-104) : `filer[]` (fichiers multiples) et `type` ∈ {`visuels`, `documents`, `plans`}. Chaque fichier est déplacé sous son `basename` d'origine dans le sous-dossier choisi, sans contrôle d'extension.
  - Un `type` invalide affiche « Veuillez choisir un type de fichier » (98).
  - Le premier fichier en erreur provoque `return false` (82-84) : la suite du traitement est interrompue et les variables de vue ne sont pas renseignées.
  - Il n'y a ni message de succès ni redirection.
- « Supprimer les fichiers sélectionnés » (`[data-action="delete-selected-files"]`) et « SUPPRIMER » (`.file`) appellent `json/DeleteFile` (§4.5).

### 3.7 `GET /programme`
- programme.php:8-14 : `isConnected()` puis vue `V_programme` sans données (des notices sont probables). Aucun lien n'y mène. Décision proposée : rediriger vers l'accueil (§11 Q8).

### 3.8 `GET /programme/show/{key}` (fiche programme connectée)
- Rôle : tout utilisateur connecté (programme.php:16-17). Vue `V_programme`. Titre : `Corim promotion - Programme`. CSS et JS flexigrid chargés seulement quand le premier segment vaut `programme` (template.php:54-58).
- Point d'entrée : vignettes `module_programmes` de l'accueil (module_programmes.php:23-34). L'admin y voit tous les programmes non archivés, le prescripteur seulement ceux de `acces` (__main.php:22-26).
- Données :
  - `Get_infos_programmes(key)` (sans filtre d'archive, model.php:2098-2104).
  - `GetCountHisOpt`, `GetCountHisResa`, `GetCountHisAct` (model.php:673-706).
  - `Get_all_appartement` (model.php:2106-2113), non utilisée par la vue.
  - Listes `documents` et `visuels` (`<a href target="_blank">{fichier}</a><br />`, programme.php:46-64), liste `plans` (non utilisée par la vue), images `diapo` (tableau d'URL, 66-84).
  - `getPrescripteurs()` si admin (40-43, model.php:1568-1576).
- Affichage (V_programme.php) :
  - Vignette 120 px. Si des diapos existent, un lien `[Diaporama]` suivi de liens cachés `rel="slideshow"`, ouverts en diaporama colorbox (`$("a[rel='slideshow']").colorbox({slideshow:true})`, 593) (28-42).
  - Libellé en majuscules `TITRE - LIEU` (3, 45).
  - Boutons « Téléchargements » (modal `#telechargements` avec `<h3>VISUELS</h3>` et `<h3>DOCUMENTS</h3>`, 58-84) et « Dénoncer un prospect » (modal `#denonciations`, 86-179, module `clients-prospects`).
  - Description non échappée (51).
  - Modals « Optionner un lot » / « Réserver un lot » (199-389, module `workflow`). Le select prescripteur n'apparaît que pour l'admin (153-165, 266, 362).
  - Légende `images/template/panel-couleur.png` et compteurs `LOTS ACTÉS :` (couleur #E01C18), `RÉSERVATIONS :` (#B70B61), `OPTIONS :` (#ADC42B) (492-500).
  - Phrase : « Pour optionner ou réserver un lot, cliquez sur celui-ci pour le mettre en surbrillance puis cliquez l'une des actions » (502).
  - Grille flexigrid `#datagrid` (506-572), voir §4.7.
- Configuration flexigrid à conserver visuellement :
  - Colonnes, dans l'ordre : Livraison, Type, Catégorie, Prix, Prix m2 (masquée), Eta., RDC (masquée), Jard., Terr., Superficie, Stationnement, Exposition, Plans, Référence, Cellier, Accession (515-530).
  - Boutons « Optionner », « Réserver », « print » (537-541).
  - Recherche (`searchitems`, 543-560).
  - Tri initial `numero asc`, `rp: 100`, `width: 1170`, `height: 440`, `singleSelect`.
  - `onSuccess: itemState` colore les lignes : `.opt` en #ADC42B, `.res` en #B70B61, `.act` en #E01C18 (597-603).
  - Optionner ou Réserver n'ouvre le modal que si le lot sélectionné n'a pas de statut (605-622).
  - « print » ouvre une fenêtre avec le HTML de la grille et lance l'impression (647-661).

### 3.9 `GET /ajouter_lot`, `/ajouter_lots`, `/ajouter_plan`, `/gerer_lots`
- `ajouter_lot.php:7-10`, `ajouter_lots.php:8-12` et `ajouter_plan.php:8-12` redirigent vers `/`. `gerer_lots.php:16-21` redirige vers `gestion_programmes`.

### 3.10 `GET|POST /ajouter_lot/show/{key}`
- Rôle : admin. ajouter_lot.php:12-95. Vue `V_ajouter_appartement`. Titre : `Corim promotion - Ajouter un lot`. Lien d'accès : bouton « Ajouter un lot » de `gerer_lots/show` (V_gerer_lots.php:6).
- Données : `GetProgrammesByKey(key)`, filtré sur `archive=0` (model.php:1672-1681).
- Champs (V_ajouter_appartement.php:15-138) :

| Nom | Type | Obligatoire (ajouter_lot.php:28-34) |
|---|---|---|
| `name` | titre du programme, lecture seule | oui |
| `date` | texte (Livraison) | oui |
| `departement` | lecture seule, plus doublon caché | non |
| `ville` | lecture seule, plus doublon caché | non |
| `type` | select : T1, T2, T3, T4, T5, T6, Studio, Local, Bureau, Garage | oui |
| `cat` | select : Maison, Villa, Appartement, Local, Terrain, Immeuble, Stationnement | oui |
| `prix` | texte | non |
| `prix_m2` | texte | non |
| `option[]` | cases : `etage`, `RDC`, `jardin`, `terrain` | non |
| `superficie` | texte | non |
| `stationnement` | texte | non |
| `exposition` | texte | non |
| `description` | textarea | non |
| `numero` | texte (Numéro) | oui, valeur évaluée comme booléen : `"0"` est refusé |
| `cellier` | texte | non |
| `accession` | texte | non |
| `plan` | fichier | non |
| `projet_id` | caché | — |

- Traitement :
  - `option[]` est compté en `etage`, `rdc`, `jardin`, `terrasse` (0 ou 1, 40-54). La case de la vue a la valeur `terrain` alors que le contrôleur attend `terrasse`, donc **terrasse reste toujours à 0**.
  - Si un plan est envoyé, il est enregistré en `medias/programmes/{projet_titre_key de GetProgrammesById(projet_id)}/plans/plan-{numero sans espaces}.pdf`, sans contrôle d'extension ni de type (56-61). `GetLastAppartementId()` est appelé sans être utilisé (58).
  - `AddAppartement(projet_id, …)` (63-83, model.php:2228-2255).
  - Message de succès : « L'appartement a bien été ajouté. » (85). Message d'erreur : « Tous les champs sont requis » (87). Pas de redirection.

### 3.11 `GET|POST /ajouter_lots/show/{key}` (import CSV)
- Rôle : admin. ajouter_lots.php:14-120. Vue `V_ajouter_appartements`. Titre : `Corim promotion - Ajouter lot en masse`.
- Affichage :
  - En-tête « Ajouter des lots en masse », sous-titre « Programme {titre} à {lieu} ».
  - Encadré `alert-info` : « Attention, si vous souhaitez ajouter des lots en ne conservant pas les anciens, n'oubliez pas de supprimer les lots déjà enregistrés pour ce projet avant de faire l'ajout. » avec un bouton « ici » vers `gerer_lots/show/{key}` (V_ajouter_appartements.php:16-19).
  - Formulaire : `csv_upload` (fichier, aide « Le fichier doit être au format CSV avec séparteur point-virgule »), bouton « Enregistrer en masse », champs cachés `name=projet_id`, `departement`, `ville` et `csv` (`1` si `medias/csv/import.csv` existe, sinon `0`) (21-43).
  - Si le fichier serveur existe : bouton « Supprimer le fichier CSV du serveur » (`csv_delete=true`, 45-54), et aperçu « Fichier CSV sur le serveur » dans un tableau `tablesorter-green special-small-size` dont les cellules vides ont un fond `#A30106` (57-104).
  - Uploadify est branché sur `#csv_upload` et envoie vers `medias/uploadify/uploadify.php` avec `folder=/medias/csv` (108-126).
- POST `csv_delete` : `unlink(DOCUMENT_ROOT/medias/csv/import.csv)` (27-28), sans message.
- POST import :
  - Si `$_FILES` est non vide, ce qui est le cas dès que le champ fichier existe, même vide, le fichier est déplacé vers `/medias/csv/import.csv` et `csv='1'` (31-34).
  - Si `csv === '1'`, chaque ligne du fichier est insérée par `AddAppartement` (38-102). Format : §5.4.
  - Messages : 1 lot importé donne « 1 lot ont été ajouté » (texte exact, 104) ; N > 1 donne « N lots ont été ajoutés » (106) ; 0 donne « Problème lors de l'enregistrement » (108).
  - Sinon, « Aucun fichier CSV n'est disponible sur le serveur. » est assigné à `$this->template->content` **avant** que la vue soit affectée (111, puis 119), et le message est perdu.
- Le fichier `import.csv` est **unique pour tout le serveur** et n'est pas supprimé après l'import.

### 3.12 `GET|POST /ajouter_plan/show/{key}` (plans en masse)
- Rôle : admin. ajouter_plan.php:14-45. Vue `V_ajouter_plan`. Titre : `Corim promotion - Ajouter les plans`.
- Affichage :
  - En-tête « Ajouter les plans en masse », sous-titre « Programme {titre} à {lieu} ».
  - Si le programme a des lots (`GetIdLots`, model.php:1683-1692) : formulaire `plan_upload[]` (multiple, libellé « Ajouter vos plans au format PDF ») et bouton « Enregistrer en masse ». Sinon : « Veuillez tout d'abord enregistrer les lots pour y associer les plans » (V_ajouter_plan.php:17-42).
  - Pour chaque lot : un lien `plan-{numero sans espaces}.pdf` avec un badge `OK` si le fichier existe, sinon « Le fichier **plan-{numero}.pdf** est manquant » (en `#F60`) (44-59).
  - Uploadify est branché sur `#plan_upload` et envoie vers `uploadify-plan.php` avec `folder=/medias/programmes/{key}/plans/` (61-79).
- POST : chaque fichier est enregistré **sous son nom d'origine** dans `medias/programmes/{key}/plans/`, sans contrôle d'extension (28-36). En cas d'échec, le contrôleur écrit `echo "{nom} échoué."` en sortie brute avant le gabarit (34). Pour être reconnu, le fichier doit déjà s'appeler `plan-{numero sans espaces}.pdf`.

### 3.13 `GET /gerer_lots/show/{key}`
- Rôle : admin. gerer_lots.php:23-32. Vue `V_gerer_lots`. Titre : `Corim promotion - Gérer les lots`.
- Données : `GetLots(key)` (tous statuts, tri `id ASC`, model.php:853-862) et `GetProgrammesByKey(key)` (archive=0).
- Affichage :
  - En-tête « GÉRER LES LOTS », bouton « Ajouter un lot » vers `ajouter_lot/show/{key}`, bouton « Supprimer les lots » (`#supprimer_all rel={projet_id}`) (V_gerer_lots.php:2-8), puis `div.call` pour les messages AJAX.
  - Tableau `tablesorter-green` (tri et filtre par colonne, placeholder « Recherche... », filtres mémorisés, global.js:6-18). Colonnes : RÉFÉRENCE (`numero`), STATUT (valeur brute `''`/`opt`/`res`/`act`), PROJET, PRIX, ACCESSION, CELLIER, TYPE, CAT., puis les actions « Éditer » vers `gerer_lots/modifier/{key}-{id}` et « Supprimer » (`.delete_lot rel={projet_id}-{id}`) (14-51).

### 3.14 `GET|POST /gerer_lots/modifier/{key}-{idlot}`
- Rôle : admin. gerer_lots.php:34-86. Vue `V_modifier_lot`. Titre : `Corim promotion - Modifier le lot`. L'argument est découpé sur `-` (35-37).
- En-tête « Modifier un lot », sous-titre « Programme {titre} à {lieu} ». Formulaire pré-rempli par `GetLot(key, idlot)` (model.php:863-872). Champs :

| Nom | Type | Obligatoire (gerer_lots.php:42-51) |
|---|---|---|
| `date` | texte (Livraison) | non |
| `numero` | texte (Référence) | oui |
| `type` | select, mêmes 10 valeurs qu'à l'ajout | oui |
| `cat` | select, mêmes 7 valeurs qu'à l'ajout | oui |
| `prix` | texte | oui |
| `prix_m2` | texte | non |
| `cellier` | texte | non |
| `accession` | texte | non |
| `etage`, `rdc`, `jardin`, `terrasse` | **texte libre** | non |
| `superficie` | texte (« Superficie en m² ») | oui |
| `stationnement` | texte | oui |
| `exposition` | texte | oui |
| `plan` | fichier | non |
| `description` | textarea | non |
| `name=projet_id`, `departement`, `idlot`, `ville` | cachés | `name` et `ville` obligatoires |

  Le libellé de terrasse est écrit « Terasse » (V_modifier_lot.php:85).
- Traitement :
  - Le plan éventuel est enregistré en `plans/plan-{numero sans espaces}.pdf` du programme `GetProgrammesById(name)` (52-56).
  - `UpdateAppartement(idlot, …)` (57-75, model.php:2258-2282) : la mise à jour se fait sur `id` seul, sans vérifier le projet.
  - Les deux issues utilisent la variable `success`, donc l'erreur « Tous les champs sont requis » s'affiche en vert (78). Succès : « Le lot a bien été modifié. » suivi du lien « Retour à la liste des lots » (76, V_modifier_lot.php:10-13).
  - Si une valeur de select ne figure pas dans la liste (par exemple `T2B` ou `Appartement (Bât A)`, §5.3), aucune option n'est `selected` et le navigateur prend la première. **Enregistrer écrase alors la valeur réelle** (V_modifier_lot.php:29-52).

### 3.15 `/modifier_appartement`, `/modifier_appartement/show/{id}`, `/modifier_appartement/validation`
- modifier_appartement.php:16-44, vue `V_modifier_appartement` (titre « Ajouter un appartement », champs `nom` et `nombre_de_pieces` qui n'existent pas en base). `validation` appelle `UpdateProgramme` avec 4 arguments au lieu de 7 (37). Aucun lien n'y mène. Code mort : ne pas reproduire.

### 3.16 `GET|POST /administration/Ajouter_Appartement`
- administration.php:12-103 : **aucun contrôle de connexion**. Insertion générique de `$_POST` dans `appartements` (`Add_Appartement`, model.php:1848-1883), avec des colonnes `nom` et `nombre_de_pieces` qui n'existent pas. `__call` renvoie un texte de debug (105-109). Code mort et dangereux : ne pas reproduire, à supprimer.

### 3.17 Scripts directs `medias/uploadify/*.php` (hors Kohana, sans session)
| Script | Paramètres | Comportement | Utilisé par |
|---|---|---|---|
| `uploadify.php` | POST `folder`, fichier `Filedata` | Si l'extension (sensible à la casse) vaut `csv`, déplace le fichier vers `DOCUMENT_ROOT.folder/import.csv` et répond `1`, sinon `Invalid file type.` (1-17) | V_ajouter_appartements.php:110-125 |
| `uploadify-plan.php` | idem | Extension `pdf` : enregistré sous le nom d'origine dans `DOCUMENT_ROOT.folder` (9-25) | V_ajouter_plan.php:63-78 |
| `uploadify-docs.php` | idem | Extensions `pdf, jpeg, jpg, gif, png, doc, docx, xls, xlsx` (17) | Seulement du code commenté (V_modifier_programme.php:150-197) |
| `check-exists.php` | POST `filename` | `echo 1` ou `0` selon `file_exists(DOCUMENT_ROOT/uploads/filename)` (11-15) | Aucun |

## 4. Actions AJAX / JSON

Commun à toutes les actions :
- URL `/index.php/json/{Action}/`, appelée en POST par `$.ajax` (global.js).
- Contrôleur `Json_Controller extends __Json_Controller` **sans authentification** (__json.php:8-15).
- Sortie par `JsonMe()` : `echo json_encode($data)`, ou `{callback}({json});` si `$_GET['callback']` est présent (json.php:1597-1605).
- Aucun en-tête `Content-Type` n'est posé, donc la réponse part avec le type par défaut du serveur (`text/html`). Le gabarit `template/json` ajoute ensuite `$content`, vide (template/json.php:2).
- Le JS lit la réponse par `eval('(' + callback + ')')`.
- **Convention inversée** : `errors: 1` signifie succès et `errors: 0` signifie échec.
- Sans POST, la réponse est vide.

### 4.1 `SupprimerLot`
- Paramètres : `idlot`, `idprojet` (json.php:293-312).
- Réponse : `{"errors":1}` si exactement une ligne est supprimée, sinon `{"errors":0}`, y compris si un paramètre est vide.
- Effets :
  - `DELETE FROM appartements WHERE id=:idlot AND lot=:idprojet` (model.php:829).
  - Si une ligne est supprimée, `SupprimerAllParms` efface aussi `d_options` (`iddlot`), `d_reservations` (`iddlot`), `options` (`idlot`) et `reservations` (`idlot`) pour ce lot et ce projet (model.php:838-851).
  - **Ne supprime ni `acces_lot`, ni `ventes`, ni le fichier plan.** En base locale : 144 lignes `acces_lot` et 2 lignes `ventes` pointent vers un lot inexistant.
- JS (global.js:363-397) : `$.confirm`, titre « Supprimer ce lot », message « Vous êtes sur le point de supprimer le lot.<br />Continuez ? », boutons Oui/Non. En cas de succès, `div.call` affiche `<div class="success">La suppression a bien été effectuée</div>` puis la page se recharge après 3 s. En cas d'échec : `<div class="error">Une erreur interne est survenue. Veuillez réessayer SVP</div>`.

### 4.2 `DeleteAllInProject`
- Paramètre : `project` (projet_id) (json.php:62-76).
- Réponse : `{"errors":1}` si au moins un lot est supprimé, sinon `{"errors":0}`.
- Effets : `DELETE FROM acces_lot WHERE idlot=…` pour chaque lot, puis `DELETE FROM appartements WHERE lot=:projet` (model.php:591-603). **Ne touche ni aux options, ni aux réservations, ni aux dénonciations, ni aux ventes, ni aux plans.** `SelectAllInProject` (model.php:577-589) est un doublon exact jamais appelé.
- JS (global.js:109-140) : confirmation « Supprimer les lots », message « Vous êtes sur le point de supprimer les lots.<br />Continuez ? ». Ensuite `alert('Suppression effectuée')` et rechargement, ou `alert('bad request')`.

### 4.3 `ArchivageProjet` / 4.4 `DesarchivageProjet`
- Paramètre : `key` (projet_titre_key) (json.php:1184-1214).
- Effets : `UPDATE projet SET archive=1` (respectivement `0`) `WHERE projet_titre_key='…'` (model.php:1369-1385).
- Réponse : `{"errors":1}` si une ligne est modifiée, sinon `{"errors":0}` (par exemple si le projet est déjà dans cet état).
- JS (global.js:1361-1415) : confirmation « Confirmation d'archivage », message « Vous êtes sur le point d'archiver ce projet<br />Continuer ? ». Pour la réactivation : « Confirmation de réactivation », message « Vous êtes sur le point réactiver ce projet<br />Continuer ? ». La page se recharge quelle que soit la réponse.
- Conséquences de l'archivage : le programme disparaît de l'accueil admin (`GetProgrammes`, model.php:1697) mais **reste** sur l'accueil prescripteur (`GetProgrammesByAccess` ne filtre pas `archive`, model.php:1656-1660 ; §11 Q6), de l'API publique (`projet.archive = 0`, CLAUDE.md) et de toutes les fonctions `GetProgrammesByKey/ById` (filtre `archive=0`, model.php:1669, 1678).

### 4.5 `DeleteFile`
- Paramètre : `url`, un chemin web du type `/medias/programmes/{key}/{type}/{fichier}` (json.php:48-60).
- Effet : `unlink($_SERVER['DOCUMENT_ROOT'] . $url)`, **sans aucun contrôle de chemin**.
- Réponse : `{"errors":1}` si la suppression réussit, sinon `{"errors":0}`.
- JS :
  - `.file` (global.js:56-73) : sans confirmation, `alert('Suppression effectuée')` puis rechargement, ou `alert('bad request')`.
  - `[data-action="delete-selected-files"]` (global.js:255-281) : un appel par case cochée, puis rechargement quand tous ont réussi. Si un appel échoue, il n'y a pas de rechargement.

### 4.6 `DeleteDiapo`
- Paramètre : `key`, un chemin web d'image diapo (json.php:1170-1182).
- Effet : `unlink(DOCUMENT_ROOT . key)` avec `DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'].'/'` (config.php:129), sans contrôle.
- Réponse : `{"errors":1|0}`.
- JS (global.js:1333-1359) : confirmation « Confirmation de suppression », message « Vous êtes sur le point de supprimer cette image<br />Continuer ? », puis rechargement.

### 4.7 `JsonDatagrid/{idprojet}/{iduser}` (grille de la fiche programme)
- URL construite par la vue : `/index.php/json/JsonDatagrid/{projet_id}/{SessionId}` (V_programme.php:507). **`iduser` vient de l'URL** ; `privilege` vient de la session (json.php:1522).
- POST envoyé par flexigrid : `page`, `rp`, `sortname`, `sortorder`, `query`, `qtype`.
  - Valeurs par défaut : page 1, tri `id asc`. `rp` n'a pas de défaut : sans lui, `limit` est invalide.
  - `sortname=numero` est remplacé par `LPAD(numero,2,'00')` (1534-1535). Toute autre valeur est injectée telle quelle dans `order by` (1553).
  - `searchSql = "where {qtype} LIKE '%{query}%'"` (1554).
- Requêtes :
  - Comptage `GetTotalCountApartments` (model.php:2115-2141) et lignes `GetTotalApartmentsInfos` (model.php:2143-2163).
  - Admin : tous les lots `WHERE lot=:idprojet {sort} {limit}`, **la recherche est ignorée** pour les lignes.
  - Autre utilisateur, y compris sans session : lots dont l'id figure dans `acces_lot` pour `iduser`, via `appartements.id IN (…)`. Si une recherche est active, **le filtre `IN` disparaît** (`$Condition="AND"`, model.php:2156-2157).
- Réponse : `{"page": <valeur postée, chaîne>, "total": "<COUNT(*) en chaîne>", "rows": [{"id": "<id>", "cell": [date, type, categorie, prix, prix_m2, etage, RDC, jardin, terrasse, "<superficie> m²", stationnement, exposition, actions, numero, cellier, accession]}]}` (json.php:1563-1592).
  - `date` est enveloppée dans `<span class="opt|res|act">` selon `statut` (1576-1584).
  - `actions` vaut `<a href="/medias/programmes/{key}/plans/plan-{numero sans espaces}.pdf" target="_blank" class="btn btn-info btn-xs btn-block">Plan</a>` si le fichier existe, sinon `-` (1571-1575). Le `{key}` vient de `GetProgrammesById(idprojet)`, filtré sur archive=0 : pour un programme archivé, `actions` vaut toujours `-`.
- Les lots sont listés **quel que soit leur statut** (libres, optionnés, réservés, actés).

## 5. Données

### 5.1 Table `projet` (MyISAM, latin1, 40 lignes : 8 `archive=0`, 32 `archive=1`)
`projet_id` int AI PK · `projet_titre` varchar(100) · `projet_titre_key` varchar(100) · `projet_lieu` varchar(100) · `projet_image` varchar(100) · `projet_nb_appartements` int · `projet_description` text · `archive` tinyint unsigned · `dept` varchar(10) · `livraison` varchar(100). Aucun index hors PK, et en particulier aucune contrainte d'unicité sur la clé.

| Requête | Fichier:ligne |
|---|---|
| `INSERT` (titre, key, lieu, `projet_image=''`, `archive=0`, `dept=''`, `projet_nb_appartements=0`, description, livraison) | model.php:2171-2183 |
| `UPDATE projet SET projet_titre, projet_lieu, projet_description, dept, livraison WHERE projet_titre_key=:key` | model.php:2186-2193 |
| `UPDATE appartements SET ville, dept, date=:livraison WHERE lot=:idprojet` (cascade) | model.php:2195 |
| `UPDATE projet SET archive=1/0 WHERE projet_titre_key=…` | model.php:1373, 1382 |
| `SELECT projet.* WHERE archive=0 ORDER BY projet_id DESC` | model.php:1695-1697 |
| `SELECT … WHERE archive=1` | model.php:1703 |
| `SELECT … WHERE archive=0 AND projet_id=…` / `AND projet_titre_key=…` | model.php:1669, 1676-1679 |
| `SELECT … WHERE projet_titre_key=…` (sans filtre d'archive) | model.php:2102 |
| Programmes d'un prescripteur : `acces INNER JOIN projet WHERE acces.iduser=…` | model.php:1656-1660 |

Règles implicites :
- `projet_titre_key = md5(titre saisi à la création)`, 32 caractères hexadécimaux (ajouter_programme.php:20). La clé est **figée** : 10 projets sur 40 ont une clé différente du md5 de leur titre actuel. C'est l'identifiant public des URL et des dossiers.
- `projet_image` vaut toujours `''` et `projet_nb_appartements` toujours `0` : 0 ligne non vide en base, colonnes inutilisées.
- `dept` est vide à la création et n'est renseigné qu'en modification. Distribution : `34` (15), vide (10), `30` (6), puis des codes postaux à 5 chiffres (8).
- `livraison`, formats observés (chiffres remplacés par 9) : `9T9999` (35, trimestre, par exemple 2T2015), `9S9999` (4, semestre probable), `99.99.9999` (1).
- Une ligne de `projet_titre` contient une entité HTML (`&#039;` ou `&amp;`).

### 5.2 Table `appartements` (MyISAM, latin1, 1228 lignes, aucun lot orphelin)
`id` int AI PK · `lot` varchar(20), qui contient en fait le **projet_id** · `date` varchar(200), la livraison · `dept` **int** NULL · `ville` varchar(100) · `type` varchar(20) · `categorie` varchar(20) · `prix` varchar(100) · `prix_m2` varchar(100) · `etage`, `RDC`, `jardin`, `terrasse` varchar(50) par défaut `'0'` · `superficie` varchar(50) · `stationnement` varchar(100) · `exposition` varchar(100) · `description` text · `statut` varchar(100) · `client` varchar(100) · `vendeur` int NULL · `numero` varchar(150), la référence du lot · `cellier` varchar(150) · `accession` varchar(250).

| Requête | Fichier:ligne |
|---|---|
| `INSERT` (toutes colonnes, `statut=''`, `client=''`, `vendeur=NULL`), valeurs passées par `htmlspecialchars` puis échappées par Kohana | model.php:2228-2255 |
| `UPDATE appartements SET date, type, categorie, prix, prix_m2, etage, RDC, jardin, terrasse, superficie, stationnement, exposition, description, numero, cellier, accession WHERE id=:idlot`, valeurs échappées SQL sans `htmlspecialchars` | model.php:2258-2282 |
| `SELECT … FROM appartements WHERE lot=:idprojet ORDER BY id ASC` | model.php:859 |
| `SELECT … WHERE id=:lot AND lot=:idprojet` | model.php:869 |
| `SELECT id, numero WHERE lot=…` | model.php:1687-1690 |
| `SELECT COUNT(id) … WHERE lot=… AND statut='opt'/'res'/'act'` | model.php:675, 689, 703 |
| `DELETE FROM appartements WHERE id=… AND lot=…`, puis `d_options`, `d_reservations`, `options`, `reservations` | model.php:829-850 |
| `DELETE FROM acces_lot WHERE idlot=…`, puis `DELETE FROM appartements WHERE lot=…` | model.php:597, 600 |
| Grille (comptage et lignes) | model.php:2138, 2145, 2159 |

### 5.3 Statuts et formats (valeurs réelles en base locale)
- **`statut`** : `''` = libre (149), `opt` = optionné (6), `res` = réservé (55), `act` = acté/vendu (1018). Aucune autre valeur. `client` n'est renseigné et `vendeur` non NULL que pour `act` (1018 sur 1018). Les transitions relèvent du module `workflow` : par exemple `res` model.php:494, `act` model.php:750, retour à `''` model.php:457 (qui met alors `vendeur = 0`), 511, 527, 979, 1067, et `ChangeStatut` model.php:2301-2307. Ce module n'écrit `statut=''` qu'à la création.
- **`prix`** : chaîne, **à conserver telle quelle**, car l'API publique renvoie `"185 000"` (CLAUDE.md). Formats observés : `999 999` avec espace des milliers (658), `999999` sans espace (516), espace initial ` 999 999` (33), vide (8), `99999` (4), décimales avec virgule `999999,99` / `999999,9` (5), `9` (1), ` 999999   ` (1), `99 9999` (1), `NC` (1). Les sommes utilisent `REPLACE(prix,' ','')` (model.php:682). Le tri de la grille est **lexicographique**.
- **`prix_m2`** : vide (1043), `-` (151), `.` (11), quelques nombres.
- **`superficie`** : `99`, `99,99`, `99,9` (virgule), `99.99`, `99.9` (point), `999`. La grille ajoute « ` m²` ».
- **`date`** (livraison du lot) : `9T9999` (975), `9S9999` (175), `99.99.9999` (59), `99/99/9999` (19). Elle est écrasée par la livraison du programme à chaque modification du programme.
- **`type`** : T1 (91), T2 (499), T3 (498), T4 (118), T5 (10), Local (5), Bureau (1), Garage (1), **T1C (1), T2B (4)**. Les deux dernières ne figurent pas dans le select.
- **`categorie`** : Appartement (1120), Maison (51), Local (14), Villa (3), Garage (1), Terrain (1), **Appartement (Bât A|B|C|D)** (38). Garage et les variantes « Bât » ne figurent pas dans le select.
- **`etage` / `RDC` / `jardin` / `terrasse`** : texte libre. `etage` : `RDC`, `R+1`, `R1`…`R4`, `0`, vide. `RDC` : vide (1016), `-` (151), `0`, `1`, `RDC`, `ENTRESOL`, `R1`, `R2`. `jardin` et `terrasse` : surfaces (`25,4`, `18,26`…), `0`, `-`, `/`, vide. L'ajout unitaire écrit `0`/`1`, alors que l'import CSV et la modification écrivent du texte libre.
- **`numero`** (référence) : `A999`, `B99`, `A99`, `B999`, `999`, `C99`, `99`, `D99`, `9`… et **des valeurs avec espace** comme `C 99` (17) ou `D 99` (15). Le nom du plan supprime les espaces.
- **`dept`** (int) : `34` (471), `0` (237, issu de `''` converti par MySQL en mode non strict), `30` (169), codes postaux à 5 chiffres. Le MySQL local est en `STRICT_TRANS_TABLES` : l'insertion de `''` y échouerait.
- **`lot`** est un varchar qui contient le projet_id. À typer `unsignedInteger` avec une clé étrangère vers `projet` dans la refonte.

### 5.4 Format du CSV d'import (ajouter_lots.php:39-102, V_ajouter_appartements.php:64-103)
- Séparateur `;`, guillemet par défaut `"`, `fgetcsv($file, 1024, ";")` : une ligne de plus de 1024 caractères est découpée. **Pas de ligne d'en-tête** : toutes les lignes sont importées. Le fichier local `import.csv` a 17 champs par ligne, est encodé en UTF-8 et finit par CRLF.
- Chaque valeur passe par `utf8_encode()` : le code suppose un fichier en **ISO-8859-1/Windows-1252**. Un fichier UTF-8 produit des accents doublement encodés.
- Projet, département et ville ne viennent pas du fichier mais du formulaire (`name=projet_id`, `departement`, `ville`).
- Colonnes (index à partir de 0) :

| # | Colonne CSV (libellé de l'aperçu) | Champ base |
|---|---|---|
| 0 | Date de livraison | `date`, texte brut (le découpage sur `/` des lignes 49-58 n'est pas utilisé) |
| 1 | Type | `type` |
| 2 | Catégorie | `categorie` |
| 3 | Prix | `prix` |
| 4 | Prix m² | `prix_m2` |
| 5 | Etage | `etage` |
| 6 | RDC | `rdc` |
| 7 | Jardin | `jardin` |
| 8 | Terrasse | `terrasse` |
| 9 | Superficie | `superficie` |
| 10 | Stationnement | `stationnement` |
| 11 | Exposition | `exposition` |
| 12 | Description | `description` |
| 13 | Plan d'architecte | lu dans `$plan`, **ignoré** (ajouter_lots.php:72) |
| 14 | Référence | `numero` |
| 15 | Cellier | `cellier` |
| 16 | Accession | `accession` (valeurs vues : `ABORDABLE`, `LIBRE`) |

- Aucune validation, aucun dédoublonnage, aucune transaction (MyISAM). Le compteur augmente pour chaque insertion qui renvoie 1. La boucle `for(…; !feof; …)` ignore probablement la dernière ligne quand le fichier ne finit pas par un saut de ligne (§11 Q4).

### 5.5 Table `docs` (InnoDB, utf8mb3, 2135 lignes)
`iddocs` int AI PK · `email` text · `url` text · `time` varchar(20), un timestamp Unix (de 1432296243 à 1652039483). Elle n'est **ni lue ni écrite par ce module**. Elle est écrite par `ExternalSendEmail` (model.php:136-145, `firstsearch?action=email`, module `public-api`) et lue par `getEmailsCorimPromotion` (model.php:85-89, module `communication`). Elle concerne ce module parce que `url` contient des URL absolues de fichiers de programme (`http://corim-vente.com/medias/programmes/…` pour 1564 lignes, `https://…` pour 571). L'arborescence de fichiers doit donc rester servie aux mêmes chemins.

### 5.6 Tables annexes touchées
- `acces_lot` (`iduser`, `idlot`, PK composite) : lue par la grille (model.php:2127, 2148) et purgée par `DeleteAllInProject`, mais pas par `SupprimerLot`.
- `acces` (`iduser`, `idprojet`) : lue pour la liste des programmes d'un prescripteur (model.php:1656-1660).
- `d_options`, `d_reservations`, `options`, `reservations` : purgées par `SupprimerLot` (model.php:843-850).
- `user` et `privileges` : lus par `getPrescripteurs` (model.php:1568-1576).

### 5.7 Encodage des entrées
- `global_xss_filtering = TRUE` (config.php:71) : Kohana nettoie `$_POST`, `$_GET` et `$_COOKIE` avant les contrôleurs.
- `AddAppartement` applique `htmlspecialchars(ENT_QUOTES)` (model.php:13-19) ; `UpdateAppartement` et `AddProgramme` ne le font pas.
- Les vues affichent tout sans échappement.
- En base locale, aucune entité dans `appartements.description` et une seule dans `projet_titre`.

## 6. Emails

Aucun. Aucun contrôleur du module n'appelle `Swift`, `mail()` ou `email::` (recherche sur les 10 contrôleurs).

## 7. Fichiers

Racine physique : `$_SERVER['DOCUMENT_ROOT'] . '/medias/'`. URL publique : `MEDIAS = '/medias/'` (config.php:128-130).

```
medias/
├── programmes/{projet_titre_key}/      md5 de 32 caractères, dossier 0755 créé à l'ajout (ajouter_programme.php:27-55)
│   ├── thumb/thumb.{ext}               vignette 120 px de large (GD), ext = extension d'origine (casse conservée : 29 .jpg, 1 .JPG, 1 .png en local)
│   ├── diapo/                          images du diaporama : vide pour les 31 programmes en local, aucun upload fonctionnel
│   ├── documents/{nom d'origine}       local : 341 PDF ; upload via modifier_programme (type=documents)
│   ├── plans/plan-{numero sans espaces}.pdf   local : 993 PDF ; noms d'origine possibles via ajouter_plan / modifier_programme
│   └── visuels/{nom d'origine}         local : 50 jpg, 75 pdf, 1 « .pdf0 »
├── csv/import.csv                      fichier d'import unique, partagé par tous les programmes ; *.csv refusé en HTTP (.htaccess:27-29)
└── upload/{time()}{nom d'origine, espaces → _}   originaux des vignettes (upload::save, system/config/upload.php:7,17 ; system/helpers/upload.php:29-38), jamais purgés (171 fichiers en local)
```

- **Nommage des plans** : `plan-` + `str_replace(' ', '', numero)` + `.pdf`. Seuls les espaces sont retirés : pas de mise en minuscules, pas de translittération (ajouter_lot.php:59, gerer_lots.php:54, json.php:1571-1572, V_ajouter_plan.php:48-54). **Ce chemin est un contrat public** (API `nextjscorim`, site Cocoon) : `medias/programmes/{projet_titre_key}/plans/plan-{numero}.pdf` doit continuer à répondre.
- **Contrôles d'extension et de taille** :
  - vignette : gif, jpg ou png, 5 Mo à l'ajout, 10 Mo à la modification ;
  - plan unitaire, plans en masse et fichiers de `modifier_programme` (`uploader`) : **aucun contrôle** ;
  - `uploadify-*.php` : contrôle par extension seulement, sensible à la casse.
- **Listage** : `readdir`, ordre du système de fichiers, sans tri. Les vues de liste affichent la vignette en concaténant tous les fichiers du dossier `thumb/` (V_gestion_programme.php:5-23 ; même fonction dans V_programme.php:5-23, V_gestion_diaporama.php:5-23, module_programmes.php:4-22).
- **Suppression** : unitaire via `json/DeleteFile` et `json/DeleteDiapo` (chemin fourni par le client). Supprimer un lot ou un programme ne supprime jamais de fichier, et aucun programme n'est jamais supprimé (seulement archivé).
- **État local** : 40 projets en base contre 31 dossiers. 6 projets actifs et 4 archivés n'ont pas de dossier, et 1 dossier n'a pas de projet. La copie locale de `medias/` est donc probablement incomplète (§11 Q5).
- `medias/` exécute du PHP (CLAUDE.md). Les uploads sans contrôle permettent donc l'exécution de code (§9).

## 8. Design

- Gabarit `OLD/application/views/template/template.php`, avec :
  - Bootstrap 3.3.6 et jQuery 1.11.3, jQuery Migrate 1.2.1, jQuery UI 1.11.3 et bootstrap-select 1.13.14, tous en CDN (11-23) ;
  - `medias/tablesorter/` (27-28), `medias/colorbox/` (29-30), `medias/uploadify/` (31-32) et `medias/confirm/` (67-68) ;
  - `css/global.css` et `js/global.js` (69-71).
- Selon le premier segment d'URL : `flexigrid/css/flexigrid.pack.css` et `flexigrid/js/flexigrid.pack.js` pour `programme` (54-58) ; `jquery.jscrollpane` et `jquery.mousewheel` pour `gestion_programmes` (59-64), chargés mais sans usage visible dans `V_gestion_programme`.
- Vues du module : `V_gestion_programme.php`, `V_ajouter_programme.php`, `V_modifier_programme.php`, `V_gestion_diaporama.php`, `V_programme.php`, `V_gerer_lots.php`, `V_modifier_lot.php`, `V_ajouter_appartement.php`, `V_ajouter_appartements.php`, `V_ajouter_plan.php`, ainsi que `template/module_programmes.php` (vignettes de l'accueil).
- Image : `medias/images/template/panel-couleur.png` (légende des couleurs).
- Couleurs des statuts : opt `#ADC42B`, res `#B70B61`, act `#E01C18`, appliquées en ligne par JS (V_programme.php:597-603). Cellule CSV vide : `#A30106`. Plan manquant : `#F60`.
- Remplacement des plugins obsolètes, à rendu identique :

| Plugin | Où | Remplacement prévu |
|---|---|---|
| uploadify 3.1 (Flash `uploadify.swf`) | V_ajouter_appartements.php:108-126, V_ajouter_plan.php:61-79 | `<input type="file" (multiple)>` natif plus `fetch` ou formulaire classique vers une route authentifiée. Suppression des 4 scripts `medias/uploadify/*.php` |
| `$.confirm` | global.js:109-140, 363-397, 1333-1415 | `<dialog>` avec les mêmes titres, messages et boutons Oui/Non |
| colorbox (diaporama) | V_programme.php:593 | `<dialog>` avec navigation et défilement automatique |
| flexigrid | V_programme.php:503-672 | Tableau HTML et petit JS natif (tri, pagination de 100, recherche, sélection unique, boutons Optionner, Réserver et Imprimer) reproduisant la classe `.flexigrid`. Le JSON du §4.7 peut rester l'API interne |
| tablesorter (zebra, filtre) | V_gerer_lots.php:14, V_ajouter_appartements.php:64, global.js:6-18 | Tri et filtre par colonne en JS natif, placeholder « Recherche... » |
| `eval()` des réponses | global.js:63, 89, 123, 267, 379 | `response.json()` |

## 9. Bugs et failles constatés

| Problème | Fichier:ligne | Décision proposée (reproduire / corriger) |
|---|---|---|
| **Actions JSON sans authentification** : un anonyme peut archiver ou réactiver, supprimer un lot ou tous les lots d'un programme, supprimer un fichier, lire la grille de n'importe quel programme | __json.php:8-15 ; json.php:48-76, 293-312, 1170-1214, 1519-1595 | Corriger : auth et policy admin (lecture de la grille : utilisateur connecté), CSRF |
| **Suppression de fichier arbitraire** (traversée de chemin) : `unlink(DOCUMENT_ROOT . $_POST['url'|'key'])` | json.php:53, 1175 | Corriger : identifiant de fichier ou nom validé, chemin construit côté serveur et confiné au dossier du programme |
| **`isAdmin()` sans `exit`** : un prescripteur connecté exécute les POST d'administration (création ou modification de programme ou de lot, uploads, import CSV) | gestion_programmes.php:11-13, ajouter_programme.php:9-11, modifier_programme.php:11-13, ajouter_lot.php:15-17, ajouter_lots.php:17-19, gerer_lots.php:11-13, ajouter_plan.php:17-19 | Corriger : middleware ou policy, 403 ou redirection effective |
| **Upload sans contrôle d'extension dans un dossier qui exécute du PHP**, donc exécution de code à distance (par un admin, ou par un prescripteur via le bug précédent) | modifier_programme.php:81-101, ajouter_plan.php:28-36, ajouter_lot.php:56-61, gerer_lots.php:52-56 | Corriger : liste blanche d'extensions et de types MIME (plans pdf ; visuels jpg/png/gif/pdf ; documents pdf/doc(x)/xls(x)/jpg/png), pas d'exécution PHP dans le dossier d'upload |
| **Scripts uploadify anonymes** : dossier cible fourni par le client (`$_POST['folder']`), donc écriture de `.csv`, `.pdf` ou `.doc` n'importe où ; `check-exists.php` sert d'oracle d'existence de fichier | medias/uploadify/uploadify.php:3-13, uploadify-plan.php:9-21, uploadify-docs.php:9-21, check-exists.php:11 | Corriger : supprimer les scripts, routes authentifiées |
| Traversée de chemin via `numero` dans le nom du plan (`plan-../../x.pdf`) et via `$_POST['key']` (suppression de tous les fichiers d'un dossier arbitraire) | ajouter_lot.php:59, gerer_lots.php:54, modifier_programme.php:48-56 | Corriger : clé lue en base, nom de plan assaini (refuser `/`, `\` et `..`) |
| **Injection SQL** dans la grille : `sortname`, `sortorder`, `qtype`, `query`, `page`, `rp` et `idprojet` concaténés | json.php:1534-1561 ; model.php:2138, 2145, 2159 | Corriger : liste blanche des colonnes de tri et de recherche, requêtes paramétrées |
| **IDOR dans la grille** : `iduser` vient de l'URL, si bien qu'on voit les lots attribués à un autre prescripteur ; une recherche supprime le filtre `acces_lot` | V_programme.php:507 ; json.php:1519 ; model.php:2135-2136, 2156-2157 | Corriger : utilisateur pris dans la session, filtre `acces_lot` toujours appliqué |
| Grille d'un prescripteur sans aucun lot attribué : `IN ()`, erreur SQL | model.php:2133-2135, 2154-2156 | Corriger : liste vide renvoyée |
| Recherche admin : `normalize()` transforme les quotes en `&#039;`, d'où une erreur SQL sur le comptage, et la recherche est ignorée pour les lignes | model.php:2118, 2138, 2145 | Corriger : recherche appliquée partout |
| Tri et recherche sur des colonnes inexistantes (`cat`, `actions`, `nom`), donc erreur SQL | V_programme.php:517, 527, 544, 547 ; json.php:1537 | Corriger : correspondance `cat` → `categorie`, `rdc` → `RDC` ; `nom` et `actions` non triables |
| Tri du prix lexicographique (varchar) | json.php:1553 | Question (§11 Q7) : reproduire par défaut |
| Callback JSONP réfléchi `$_GET['callback']` servi en `text/html` (XSS) | json.php:1600-1601 | Corriger : supprimer le JSONP de ces actions internes |
| `SupprimerLot` ne purge ni `acces_lot` ni `ventes` (144 et 2 orphelins en local) ; `DeleteAllInProject` ne purge ni options, ni réservations, ni dénonciations, ni ventes | model.php:824-851, 591-603 | Corriger : suppression en cascade (clés étrangères InnoDB) ; nettoyer les orphelins à la migration |
| Diaporama : upload impossible (champs exigés absents du formulaire) ; s'il passait, il créerait un programme ; aucun lien vers l'écran | gestion_programmes.php:35-68 ; V_gestion_diaporama.php:41-45 | Question (§11 Q1) |
| Case « Terrasse » de l'ajout de lot envoyée avec la valeur `terrain` : terrasse toujours à 0 | V_ajouter_appartement.php:90 ; ajouter_lot.php:47-48 | Corriger : valeur `terrasse` (libellé « Terrain » conservé ? §11 Q9) |
| Modification de lot : erreur affichée en vert (variable `success`) | gerer_lots.php:78 | Corriger : alerte `alert-warning` |
| Modification de lot : valeur `type` ou `categorie` absente du select, donc écrasée par la première option | V_modifier_lot.php:29-52 | Corriger : ajouter la valeur courante comme option sélectionnée |
| `UpdateAppartement` met à jour le lot par `id` seul ; `UpdateProgramme` met à jour les lots par `idprojet` posté, qui peut différer de la `key` | model.php:2278, 2195 | Corriger : vérifier l'appartenance au programme de la route |
| Modification de programme : la vignette refusée n'empêche pas la mise à jour, et le message parle de 5 Mo pour une limite de 10 Mo | modifier_programme.php:45, 63, 66-75 | Reproduire la limite de 10 Mo, corriger le texte du message (§11 Q10) |
| `uploader` : un fichier en erreur provoque `return false` et une page incomplète ; aucun message de succès | modifier_programme.php:82-84 | Corriger : erreur par fichier, pas d'arrêt |
| Import CSV : fichier global unique (conflit entre programmes ou sessions), import de l'ancien fichier si aucun fichier n'est choisi, message « Aucun fichier CSV… » perdu, `utf8_encode` qui double-encode l'UTF-8, colonne Plan ignorée, « 1 lot ont été ajouté » | ajouter_lots.php:28-34, 47, 72, 104, 111 | Corriger : fichier par import (temporaire), détection de l'encodage, message affiché ; conserver les textes (§11 Q3) |
| `ajouter_plan` : `echo "… échoué."` avant le gabarit ; aucun message de succès | ajouter_plan.php:34 | Corriger : message dans la vue |
| Programmes archivés : `gerer_lots`, `ajouter_lot`, `ajouter_lots` et `ajouter_plan` filtrent `archive=0`, si bien que l'en-tête est vide et les formulaires cassés alors que les liens existent dans la liste « archivés » ; plans absents de la grille | model.php:1669, 1678 ; V_gestion_programme.php:55-56 | Question (§11 Q6) |
| Titre en double : même `md5` donc même clé et même dossier pour deux programmes (aucune contrainte d'unicité) | ajouter_programme.php:20 ; table `projet` | Corriger : refuser un titre dont le md5 existe déjà |
| Clé `{key}` inconnue : `Get_infos_programmes` est vide, d'où `lot=` vide et une erreur SQL (`GetLots`) ou des notices | model.php:855-859 ; programme.php:22-27 | Corriger : 404 |
| Fiche `programme/show` accessible à tout connecté pour tout programme, y compris archivé, sans vérifier `acces` | programme.php:16-44 | Question (§11 Q2) |
| `is_dir(...MEDIAS."programmes".$key)` sans `/`, donc toujours faux (sans effet grâce à `@mkdir`) | ajouter_programme.php:27 | Sans objet dans la refonte |
| Code mort : `ajouter_programme/validation`, `modifier_appartement/*`, `administration/Ajouter_Appartement` (non authentifié, insertion générique de `$_POST`), `SelectAllInProject`, `GetLastAppartementId`, `Get_all_appartement` et `Get_Plans` non affichés | ajouter_programme.php:76-115 ; modifier_appartement.php ; administration.php:12-109 ; model.php:577-589, 2296-2299 ; programme.php:27, 35 | Ne pas reproduire |
| Mot de passe MySQL en clair dans le modèle (connexion `mysqli` inutilisée) | model.php:8 | Corriger : secret uniquement dans `.env` |
| `display_errors = TRUE` : pages d'erreur Kohana avec SQL | config.php:97 | Corriger : `APP_DEBUG=false` |
| `appartements.dept` en int qui reçoit `''` (237 lignes à 0) : échec en MySQL strict | model.php:2232 ; ajouter_lots.php:36 | Corriger : colonne nullable ou varchar (§11 Q11) |
| Description du programme et toutes les valeurs affichées sans échappement (XSS stockée, saisie par un admin) | V_programme.php:51 ; V_gerer_lots.php:34-41 ; V_modifier_lot.php | Corriger : échappement Blade ; décider si la description admet du HTML (§11 Q12) |
| Originaux des vignettes jamais purgés dans `medias/upload/` | system/helpers/upload.php:29-33 | Corriger : ne garder que la vignette |

## 10. Critères d'acceptation

1. `GET /gestion_programmes` en admin affiche les blocs « PROGRAMMES EN COURS » (archive=0, tri `projet_id` décroissant) et « PROGRAMMES ARCHIVÉS » (archive=1), avec pour chaque ligne la vignette, le titre, le lieu, `(id)` et les liens Archiver ou Réactiver, Gérer les lots, Ajouter en masse et Ajouter les plans, aux URL du §3.1.
2. `GET /gestion_programmes` en prescripteur ou en anonyme ne renvoie pas la page (redirection vers `/`, ou vers `connexion` pour l'anonyme). Un POST d'administration envoyé par un prescripteur ne modifie rien en base ni sur disque (vrai pour chaque URL des §3.3, 3.6 et 3.10 à 3.14).
3. `POST /ajouter_programme` sans l'un des champs `titre`, `livraison`, `ville`, `description` ou `thumb` affiche « Tous les champs sont requis » et n'insère rien.
4. `POST /ajouter_programme` avec une vignette `.txt`, ou de plus de 5 Mo, affiche « L'image doit être au format .gif, .jpg, .png et doit être inférieur à 5M » et n'insère rien.
5. Un `POST /ajouter_programme` valide insère un `projet` avec `projet_titre_key = md5(titre)`, `archive=0`, `dept=''`, `projet_image=''` et `projet_nb_appartements=0`, crée les 5 sous-dossiers et `thumb/thumb.{ext}` large de 120 px, puis affiche « Le programme a été ajouté ».
6. `POST /ajouter_programme` avec un titre dont le md5 existe déjà est refusé (correction).
7. `POST /modifier_programme/show/{key}` avec `action=project` met à jour titre, lieu, description, dept et livraison **sans changer la clé**, recopie `ville`, `dept` et `date` sur tous les lots du programme, puis affiche « Le programme a bien été modifié ».
8. La même requête avec une nouvelle vignette valide remplace l'unique fichier de `thumb/`.
9. `POST /modifier_programme/show/{key}` avec `action=uploader` et `type=plans` dépose les fichiers dans `plans/` ; ils apparaissent dans la liste « Plans » avec une case à cocher et un bouton SUPPRIMER. `type` vide affiche « Veuillez choisir un type de fichier ».
10. L'upload d'un fichier `.php`, ou dont le type MIME est hors liste blanche, est refusé sur toutes les routes d'upload.
11. `POST json/ArchivageProjet` avec `key` en admin donne `archive=1` et la réponse `{"errors":1}`. Un second appel renvoie `{"errors":0}`. `DesarchivageProjet` fait l'inverse. Les deux répondent 401/403 pour un anonyme ou un prescripteur, sans modifier la base.
12. `POST json/SupprimerLot` (`idlot`, `idprojet` concordants) en admin supprime le lot ainsi que les lignes `d_options`, `d_reservations`, `options`, `reservations` et `acces_lot` liées, et répond `{"errors":1}`. Avec des identifiants qui ne concordent pas, il répond `{"errors":0}` et ne supprime rien.
13. `POST json/DeleteAllInProject` (`project`) en admin supprime tous les lots du projet et leurs `acces_lot`, et répond `{"errors":1}`. Sur un projet sans lot, il répond `{"errors":0}`.
14. `POST json/DeleteFile` avec un chemin hors de `medias/programmes/{key}/{documents|visuels|plans|diapo}/`, par exemple contenant `../`, ne supprime rien et renvoie `{"errors":0}`. Avec un fichier valide en admin, il le supprime et renvoie `{"errors":1}`. Mêmes règles pour `DeleteDiapo`.
15. `GET /gerer_lots/show/{key}` liste tous les lots du programme, tous statuts confondus, triés par `id`, avec les 8 colonnes du §3.13 et les actions Éditer et Supprimer.
16. Un `POST /ajouter_lot/show/{key}` valide insère un lot avec `lot=projet_id`, `statut=''`, `client=''`, `vendeur=NULL`, `dept` et `ville` du programme, et `etage`, `RDC`, `jardin`, `terrasse` à `0` ou `1` selon les cases (terrasse incluse), puis affiche « L'appartement a bien été ajouté. ».
17. La même requête avec un plan enregistre `plans/plan-{numero sans espaces}.pdf` : `numero = "C 12"` donne `plan-C12.pdf`.
18. `POST /ajouter_lot/show/{key}` sans `numero`, `date`, `type` ou `cat` affiche « Tous les champs sont requis ».
19. `POST /gerer_lots/modifier/{key}-{id}` valide met à jour les 16 champs du §5.2 et affiche « Le lot a bien été modifié. » avec le lien « Retour à la liste des lots ». Un `idlot` appartenant à un autre programme est refusé.
20. Un lot `type=T2B` ouvert en modification puis enregistré sans changement garde `T2B`.
21. Un `prix` saisi `"185 000"` (ajout, modification ou CSV) est stocké tel quel, en chaîne avec espace, et ressort tel quel dans `JsonDatagrid` et dans l'API publique.
22. L'import CSV de 17 colonnes séparées par `;`, sans en-tête, fourni en Windows-1252, insère une ligne par enregistrement avec les champs de l'ordre du §5.4. La colonne 13 est ignorée, projet, dept et ville viennent du programme. Messages : « 1 lot ont été ajouté », « N lots ont été ajoutés », « Problème lors de l'enregistrement ».
23. L'import d'un CSV en UTF-8 conserve les accents sans double encodage (correction).
24. L'aperçu du CSV montre les 17 en-têtes du §3.11 et colore les cellules vides en `#A30106`. « Supprimer le fichier CSV du serveur » retire le fichier, et l'aperçu disparaît.
25. `GET /ajouter_plan/show/{key}` affiche, pour chaque lot, `plan-{numero}.pdf` avec le badge `OK` si le fichier existe, sinon « Le fichier plan-{numero}.pdf est manquant ». Sans lot, il affiche « Veuillez tout d'abord enregistrer les lots pour y associer les plans ».
26. Un `POST /ajouter_plan/show/{key}` multiple enregistre les PDF sous leur nom d'origine dans `plans/`. Les fichiers non PDF sont refusés.
27. `GET /programme/show/{key}` en connecté affiche le libellé `TITRE - LIEU` en majuscules, la description, les compteurs actés, réservés et optionnés (`COUNT` par `statut`), la fenêtre Téléchargements (Visuels, Documents) et la grille. En anonyme, il redirige vers `connexion`. Une clé inconnue renvoie 404.
28. `POST json/JsonDatagrid/{idprojet}` en admin (`page=1`, `rp=100`, `sortname=numero`, `sortorder=asc`) renvoie `{"page":"1","total":"<n>","rows":[{"id":"…","cell":[16 éléments]}]}` : `cell[0]` est enveloppé dans `<span class="opt|res|act">` selon le statut, `cell[9]` se termine par ` m²`, `cell[12]` est le lien « Plan » si le PDF existe, sinon `-`.
29. Le même appel en prescripteur ne renvoie que les lots de `acces_lot` de l'utilisateur **de la session**, même si un autre `iduser` figure dans l'URL et même avec une recherche active. Un prescripteur sans lot attribué reçoit `rows: []`.
30. `sortname` ou `qtype` hors liste blanche (par exemple `id;DROP`) sont ignorés ou renvoient 422, jamais une erreur SQL.
31. Après migration des fichiers, `GET /medias/programmes/{key}/plans/plan-{numero}.pdf` répond 200 pour chaque plan présent en prod. Les URL de `docs.url` restent servies.
32. Aucun fichier déposé dans `medias/programmes/**` n'est exécuté comme PHP.

## 11. Questions ouvertes

1. **Diaporama** : l'upload n'a jamais pu fonctionner (§3.2) et les dossiers `diapo/` sont vides en local. Faut-il supprimer l'écran (tout en gardant l'affichage `[Diaporama]` si des images existent en prod), ou implémenter l'intention (upload d'images dans `diapo/` et suppression) ? Il faut aussi vérifier en prod si des `diapo/` contiennent des fichiers.
2. **Visibilité de la fiche programme** : un prescripteur peut-il voir la fiche de n'importe quel programme, y compris archivé, ou seulement ceux de la table `acces` (ceux de sa page d'accueil) ? Le code n'impose aucune restriction (programme.php:16-44).
3. **Encodage attendu du CSV** : les fichiers fournis par les utilisateurs sont-ils en Windows-1252 (exports Excel, ce que suppose `utf8_encode`) ? Le fichier local est en UTF-8. Faut-il aussi accepter une ligne d'en-tête ?
4. Faut-il reproduire la perte probable de la dernière ligne d'un CSV sans saut de ligne final ? Ce comportement n'est pas vérifiable sans exécuter du PHP 5.6.
5. **Fichiers** : la copie locale de `medias/programmes` est-elle complète ? 10 projets n'y ont pas de dossier. Il faut un inventaire de prod (lecture seule, avec accord) avant la migration des fichiers.
6. **Programmes archivés** : l'admin doit-il pouvoir gérer leurs lots et plans (ce qu'impliquent les liens existants) ? Les pages correspondantes sont aujourd'hui cassées par le filtre `archive=0`. Côté accueil prescripteur, `GetProgrammesByAccess` (model.php:1656-1660) **ne filtre pas** `archive` : faut-il le reproduire ?
7. Faut-il un tri numérique du prix dans la grille, ou garder le tri de chaînes actuel ?
8. `GET /programme` sans clé : redirection vers l'accueil, ou 404 ?
9. Ajout de lot : le libellé de la 4ᵉ case est « Terrain » alors que la colonne est `terrasse`. Faut-il renommer le libellé en « Terrasse », ce qui change le visuel ?
10. Taille maximale de la vignette : 5 Mo (ajout et message) ou 10 Mo (modification) ? La proposition est 10 Mo partout avec un message cohérent.
11. `appartements.dept` : faut-il le garder en int (avec NULL au lieu de 0), ou l'aligner sur `projet.dept` en varchar(10) ? Il contient à la fois des numéros de département et des codes postaux. Le `sql_mode` de prod est inconnu.
12. `projet_description` et `appartements.description` doivent-elles accepter du HTML ? CKEditor est chargé dans le gabarit (template.php:25) mais ne semble pas utilisé dans ces vues. En base, aucune description ne contient `<`.
13. Que faire des données historiques encodées en entités HTML (1 `projet_titre`) : les décoder à la migration ?
14. Uploadify reposant sur Flash, quel chemin d'upload est réellement utilisé en prod pour le CSV et les plans : le formulaire natif ou le script uploadify ? Les deux aboutissent aux mêmes fichiers, mais seul le premier passe par Kohana. À vérifier dans les journaux d'accès de prod (`uploadify*.php`).
15. Faut-il purger, lors de la migration, les orphelins `acces_lot` (144) et `ventes` (2) qui pointent vers des lots supprimés ?
