# Contrat — clients-prospects

Statut : brouillon (à valider par Victor)
Sources lues :
- `OLD/application/controllers/gestion_clients.php:1-245` (intégral)
- `OLD/application/controllers/gestion_prospects.php:1-18` (intégral)
- `OLD/application/controllers/json.php:1-13, 78-95, 269-291, 1216-1299, 1597-1605`
- `OLD/application/controllers/__main.php:1-70`, `OLD/application/controllers/__json.php:1-48`, `OLD/application/controllers/denonciations.php:1-21`, `OLD/application/controllers/connexion.php:15-32` (lecture du seul point où `SessionAcces` est posé)
- `OLD/application/models/model.php:1-23, 91-100, 375-403, 432-439, 533-568, 605-657, 770-822, 874-925, 986-1015, 1099-1114, 1188-1208, 1387-1458, 2048-2063, 2309-2314`
- `OLD/application/views/V_Clients.php`, `V_Ajouter_client.php`, `V_Ajouter_clients.php`, `V_Edition_Client.php` (intégral), `V_Denonciations.php:1-99`, `V_programme.php:90-175`, `template/template.php:11-71, 92-110`
- `OLD/medias/js/global.js:1-10, 283-360, 1417-1509`
- `OLD/medias/uploadify/uploadify.php:1-18`
- `OLD/system/libraries/Database.php:818-835, 937-960`, `OLD/system/libraries/drivers/Database.php:302-325`, `OLD/application/config/database.php:33-45` (clés non sensibles uniquement), `OLD/application/config/config.php:127-148` (noms des constantes uniquement, valeurs non recopiées)
- `OLD/medias/clients/import.csv`, `OLD/medias/export/mydata.csv` : métadonnées uniquement (taille, empreinte md5, encodage, fin de ligne, nombre de lignes). Aucun contenu recopié.
- Base locale `sql_corim_vente_` (snapshot prod) : `SHOW CREATE TABLE`, comptages, valeurs d'énumération.
- `OLD/application/logs/*.log.php` : occurrences d'erreurs liées à `gestion_clients`.

## 1. Périmètre

Le module couvre deux ensembles de données distincts, sans lien en base entre eux :

1. **Clients** (table `clients`) : fichier de contacts commerciaux saisi par l'administration (date, source, projet(s), civilité, nom, adresse, téléphone, e-mail, type d'achat, type de logement, budget, observation, réponse). L'administrateur peut rechercher par mot-clé (AJAX), ajouter un client, modifier, supprimer, importer en masse un CSV préalablement déposé sur le serveur (`medias/clients/import.csv`), et télécharger l'ensemble au format CSV.
2. **Prospects** (tables `d_prospects`, `prospects`, `r_prospects`) : personnes « dénoncées » par un prescripteur sur un programme. Une demande est créée dans `d_prospects` (formulaire de la page programme, `json/AddProspect`), puis acceptée (copie vers `prospects`) ou refusée (copie vers `r_prospects`) par l'administration. Le menu « Prospects » (`gestion_prospects`) affiche l'écran de recherche des dénonciations acceptées (`json/SearchProspect`).

L'acceptation / le refus / la suppression d'une dénonciation (`json/AcceptAddDenonciation`, `RejetAddDenonciation`, `DeleteDenonciation`, `UpdateDate` type `den`/`askden`), les pages `denonciations`, `ask_denonciations`, `refused_ask` relèvent du module **workflow** ; ils sont seulement référencés ici pour décrire le cycle de vie des données. `json/SearchClientEmailing` et `GetClients()` (lecture de `clients` par emailing/messagerie) relèvent du module **communication**.

## 2. Rôles et droits

Rôles portés par la session `SessionAcces`, posée à la connexion (`OLD/application/controllers/connexion.php:29`, détaillé dans le contrat `auth-users-privileges`). Valeurs observées dans le code : `'1'` = administrateur, `'0'` = prescripteur (`OLD/application/controllers/__main.php:22-25`).

| Rôle (valeur en base) | Peut faire | Contrôle dans l'ancien code (fichier:ligne) |
|---|---|---|
| Non connecté | Prévu : rien. **Réel** : appeler `json/SearchClients`, `json/SearchProspect`, `json/AddProspect` (aucun contrôle) et `medias/uploadify/uploadify.php` | Aucun appel à `isConnected` dans `json.php` (`OLD/application/controllers/json.php:269-299, 1216-1299`) ; `uploadify.php:1-17` sans session |
| Connecté (tout rôle) | Pages `gestion_clients/*` et `gestion_prospects` : prévu non ; **réel** : toutes les actions s'exécutent (voir §9, redirection sans `exit`) | `isConnected()` `OLD/application/controllers/__main.php:46-53` (redirige vers `connexion` puis `exit`) |
| Administrateur (`'1'`) | Tout le module clients ; page Prospects ; recherche prospects sur tous les prescripteurs, critère « prescripteur » (type 3) | `isAdmin()` `OLD/application/controllers/__main.php:55-61` appelé `gestion_clients.php:9, 63, 79, 121, 169, 237`, `gestion_prospects.php:10` ; filtre `privilege==='1'` `model.php:1414` ; radio type 3 `V_Denonciations.php:20-28` ; menu `template/template.php:92, 98-99` |
| Prescripteur (`'0'`) | Créer une dénonciation (`AddProspect`) pour lui-même ; rechercher ses propres prospects acceptés | `sender = SessionId` `json.php:1251-1261` ; filtre `prospects.iduser = SessionId` `model.php:1417` ; select prescripteur réservé admin `V_programme.php:153` |

## 3. Écrans et URL

Toutes les URL sont relatives à `SITE_URL` (`config.php:128`). Paramètres de segment Kohana = arguments de méthode. Gabarit `template/template.php` (via `__Main_Controller`, `__main.php:38`). Titre de page = `'Corim promotion - '` + suffixe (`__main.php:43`).

### 3.1 `GET gestion_clients` — liste / recherche
- Contrôleur : `gestion_clients.php:234-243`. Rôle : admin.
- Vue `V_Clients` ; titre `Corim promotion - Gestion des clients`.
- Contenu : `<h1>Clients</h1>`, `<h2>Consultation de vos clients <small>Rechercher un client</small></h2>` ; boutons `AJOUTER UN CLIENT` → `gestion_clients/ajouter_client`, `AJOUTER EN MASSE` → `gestion_clients/ajouter_clients`, `TÉLÉCHARGER CSV` → `gestion_clients/telecharger_clients` (`V_Clients.php:17-22`).
- Formulaire `#f_recherche_client` : champ texte `f_recherche_client_search` (« MOT CLÉ »), caché `f_recherche_client_type=1`, bouton « Rechercher » (`V_Clients.php:25-34`). Soumis en AJAX vers `json/SearchClients` (§4.1).
- Tableau vide au chargement, `#f_recherche_client_result`, classe `tablesorter-green`, colonnes : Date (tri `shortDate`, `ddmmyyyy`), Source, Projet, Contact, Adresse, Tel, E-mail, Type d'achat, Type logement, budget, (actions) (`V_Clients.php:40-58`).
- Rendu des résultats côté JS (`global.js:300-321`) : une ligne par client (`Contact` = `civilite + ' ' + nom`, e-mail en `mailto:`), boutons `Message` (bascule une ligne cachée `#see_sms_{idclient}` contenant `OBSERVATION` et `REPONSE`), `MODIFIER` → `gestion_clients/edition_client/{idclient}`, `SUPPRIMER` → `gestion_clients/supprimer_client/{idclient}` après confirmation `$.confirm` titre « Supprimer ce client », message « Vous êtes sur le point de supprimer le client.<br />Continuez ? », boutons « Oui » / « Non » (`global.js:329-349`).
- Messages AJAX : « Veuillez saisir un mot clé pour effectuer une recherche » (errors 0), « La recherche n'a retourné aucun résultat » (errors 2) (`global.js:352-356`).

### 3.2 `GET|POST gestion_clients/ajouter_client`
- Contrôleur : `gestion_clients.php:119-165`. Rôle : admin. Vue `V_Ajouter_client`, titre suffixe `Gestion des clients - Ajouter un client`.
- Champs POST (tous `text`, aucun obligatoire sauf `nom`) : `date`, `source`, `projets`, `civilite`, `nom`, `adresse`, `tel`, `email` (`type="email"`), `type_achat`, `type_logement`, `budget`, `observation` (textarea), `reponse` (textarea) (`V_Ajouter_client.php:17-80`). Bouton « Enregistrer le client ».
- Si `nom != ''` : `AddClients(...)` (`model.php:782-799`) ; si 1 ligne insérée : succès « Le client {civilite} {nom} a été ajouté » (`gestion_clients.php:158`). Sinon erreur « Veuillez renseigner le nom du nouveau client » (`gestion_clients.php:161`). Pas de redirection, le formulaire est réaffiché vide.
- Aucune validation de format (date, e-mail, téléphone).

### 3.3 `GET|POST gestion_clients/ajouter_clients` — import en masse
- Contrôleur : `gestion_clients.php:167-232`. Rôle : admin. Vue `V_Ajouter_clients`, titre suffixe `Gestion des clients - Ajouter des clients`, `<h1>Ajouter des clients en masse</h1>`.
- Étape 1 (dépôt) : widget Uploadify (Flash) sur `#csv_upload`, bouton « Parcourir », POST vers `medias/uploadify/uploadify.php` avec `folder = SITE_URL.'medias/clients'` ; réponse `Invalid file type.` → alerte « Le format de fichier CSV n'est pas reconnu », sinon rechargement de la page (`V_Ajouter_clients.php:16-32`). Le fichier est toujours renommé `import.csv` (`uploadify.php:6`).
- Si `import.csv` existe : aperçu en tableau de toutes les lignes (13 colonnes : Date, Source, Projets, Civilité, Nom, Adresse, Tél., E-mail, Type achat, Type logement, Budget, Observation, Réponse) lues avec `fgetcsv(..., 1024, ";")` et `utf8_encode` (`V_Ajouter_clients.php:58-94`), un bouton « Supprimer le fichier CSV du serveur » (lien `href="#"`, sans effet, `V_Ajouter_clients.php:44-46`) et un champ caché `csv=1` (sinon `csv=0`) (`V_Ajouter_clients.php:50-54`).
- Étape 2 (`POST`, bouton « Enregistrer en masse ») : si `csv === '1'`, lecture de `DOCUMENT_ROOT.'medias/clients/import.csv'`, une insertion `AddClients` par ligne (`gestion_clients.php:177-219`). Messages : 1 insertion → « 1 client ont été ajouté » ; >1 → « {n} clients ont été ajoutés » ; 0 → « Problème lors de l'enregistrement » (`gestion_clients.php:220-226`). Si `csv != '1'` : message prévu « Aucun fichier CSV n'est disponible sur le serveur. » jamais affiché (voir §9).
- Le fichier `import.csv` n'est jamais supprimé après import : un second envoi réimporte les mêmes lignes (aucun dédoublonnage).
- Format attendu : voir §7.2.

### 3.4 `GET|POST gestion_clients/edition_client/{id}`
- Contrôleur : `gestion_clients.php:7-59`. Rôle : admin. Vue `V_Edition_Client`, titre suffixe `Gestion des clients - &Eacute;dition d'un client`.
- Mêmes 13 champs que l'ajout, préremplis depuis `GetClientById` (`model.php:555-560`), formulaire posté sur la même URL (`V_Edition_Client.php:19-80`).
- POST : si `nom != ''`, `UpdateClients` (`model.php:533-553`) ; si 1 ligne modifiée : « Le client {civilite} {nom} a été modifié » (`gestion_clients.php:47`). Si aucune valeur n'a changé, MySQL renvoie 0 ligne : ni succès ni erreur. `nom` vide : « Veuillez renseigner le nom du nouveau client » (`gestion_clients.php:50`).
- `id` inconnu : seul l'en-tête « Édition d'un client » est affiché, pas de formulaire (`V_Edition_Client.php:16`). `id` absent : erreur PHP « Missing argument 1 » (HTTP 500), observée dans les logs (`application/logs/2021-10-19.log.php` et suivants).

### 3.5 `GET gestion_clients/supprimer_client/{id}`
- Contrôleur : `gestion_clients.php:61-75`. Rôle : admin. Suppression immédiate en GET, sans jeton (`DeleteClient`, `model.php:562-568`). Vue `V_Clients` (tableau de recherche vide) avec « Le client a été supprimé » si 1 ligne supprimée, rien sinon. Titre suffixe `Gestion des clients - Suppression d'un client`.

### 3.6 `GET gestion_clients/telecharger_clients` — export CSV
- Contrôleur : `gestion_clients.php:77-117`. Rôle : admin. Réponse = fichier CSV, puis `exit()` (le gabarit n'est pas rendu). Format exact en §7.1.

### 3.7 `GET gestion_prospects`
- Contrôleur : `gestion_prospects.php:7-16`. Rôle : admin. Titre `Corim promotion - Gestion des prospects`.
- Vue **`V_Denonciations`** (la même que la page `denonciations`, `denonciations.php:10`), mais **sans** la variable `$denonciations` : le tableau est vide au chargement, seule la recherche AJAX (`json/SearchProspect`, §4.2) le remplit. La page `denonciations` (module workflow) charge en plus la liste complète via `SearchAllProspect` (`denonciations.php:15`).
- Contenu de la vue : bouton « Dénonciations refusées » → `refused_ask`, `<h2>Consultation de vos dénonciations <small>Rechercher une dénonciation</small></h2>`, radios `f_recherche_denonciation_type` 1 « NOM ACQUÉREUR », 2 « RÉSIDENCE », 3 « PRESCRIPTEUR » (admin seulement ; dernière radio cochée par défaut car toutes portent `checked`), champ `f_recherche_denonciation_search` (placeholder « Rechercher »), boutons « Rechercher » et « RESET » → `denonciations` (`V_Denonciations.php:5-36`). Colonnes : Date, Nom, Code Postal, Ville, Programme, Prescripteurs, Action (`V_Denonciations.php:39-47`).

## 4. Actions AJAX / JSON

Contrôleur `Json_Controller extends __Json_Controller` (`json.php:3`), gabarit `template/json`. Réponse produite par `JsonMe` (`json.php:1597-1605`) : `json_encode($callback)` ; si `?callback=` est présent, `callback(json);` (JSONP). Appels faits en POST par `global.js` vers `SITE + "index.php/json/<Action>/"`, réponse évaluée avec `eval` (`global.js:295, 1427, 1498`). Si la requête n'est pas un POST, la réponse est vide.

### 4.1 `POST index.php/json/SearchClients/`
- Paramètres : `f_recherche_client_search` (chaîne), `f_recherche_client_type` (toujours `1`, ignoré par le modèle). Session lue : `SessionId`, `SessionAcces` (passés au modèle mais **ignorés** : `model.php:801-822`). Code : `json.php:269-291`.
- Réponses :
  - `{"errors":0}` si recherche vide ;
  - `{"errors":1,"call":[{...ligne clients complète : idclient, date, source, projets, civilite, nom, adresse, tel, email, type_achat, type_logement, budget, observation, reponse}]}` ;
  - `{"errors":2}` si aucun résultat.
- Requête : `SELECT clients.* FROM clients WHERE clients.date LIKE '%$search%' OR ... source, projets, nom, adresse, tel, email, type_achat, type_logement, budget` (`model.php:808-819`), `$search` passé par `htmlspecialchars(ENT_QUOTES)` (`model.php:11-14, 803`). `civilite`, `observation`, `reponse` ne sont pas cherchés. Pas d'ORDER BY, pas de limite.
- Effets en base : aucun.

### 4.2 `POST index.php/json/SearchProspect/`
- Paramètres : `f_recherche_denonciation_search`, `f_recherche_denonciation_type` (`1` nom, `2` résidence, `3` prescripteur). Session : `SessionId`, `SessionAcces`. Code : `json.php:1216-1238`.
- Réponses : `{"errors":0}` (vide), `{"errors":1,"call":[...]}`, `{"errors":2}` (aucun résultat). Chaque élément : `prospects.*` (idprospect, nomprospect, cpprospect, villeprospect, smspromoteur, idprojet, iduser), `date` reformatée `DATE_FORMAT(prospects.date,'%d.%m.%Y %H:%i')` (écrase la colonne), `projet_titre`, `prescripteur` (= `user.name`) (`model.php:1419-1437`).
- Filtre : admin → tous ; sinon `prospects.iduser = SessionId AND` (`model.php:1414-1418`). Type 1 : `prospects.nomprospect LIKE`, 2 : `projet.projet_titre LIKE`, 3 : `user.name LIKE`. `INNER JOIN projet` et `INNER JOIN user` : les prospects dont le prescripteur n'existe plus sont invisibles (833 lignes sur 6 668 dans le snapshot).
- Rendu JS : `global.js:1432-1466` (bouton `Message` si `smspromoteur` non vide, ligne cachée avec `smspromoteur` injecté en HTML).
- Effets en base : aucun.

### 4.3 `POST index.php/json/AddProspect/` — demande de dénonciation
- Formulaire `#f_new_prospect` de la page programme (modale « Dénonciation d'un prospect », `V_programme.php:90-175`), bouton `#submit_f_new_prospect` (`global.js:1490-1509`). Interaction forte avec le module workflow (à coordonner).
- Paramètres : `f_new_prospect_civilite` (radio : `Mme & M.` coché par défaut, `Mme`, `M`, `Melle` — `V_programme.php:103-127`), `f_new_prospect_name` *, `f_new_prospect_prenom` *, `f_new_prospect_cp` *, `f_new_prospect_ville` *, `f_new_prospect_sms` (« Message au promoteur »), `f_new_prospect_projet` * (caché = `projet_id`, `V_programme.php:170`), `prescripteur_select` (admin uniquement, `0` = « Choisir », `V_programme.php:153-165`).
- Logique (`json.php:1240-1299`) :
  - `sender` = `prescripteur_select` s'il est non vide et ≠ `0`, sinon `SessionId` ;
  - champ obligatoire vide → `{"errors":0}` → « Les champs marqués d'une astérisque doivent être renseignés » ;
  - `sender` vide ou 0 → `{"errors":3}` → « Votre session a expirée. Merci de vous reconnecter. » ;
  - sinon : `AddProspect(civilite, name.' '.prenom, cp, ville, sms, projet, sender)` ; message interne `mp` ; e-mail (§6) ; `{"errors":1}` → « Le prospect a bien été enregistré » si 1 ligne insérée, sinon `{"errors":2}` (aucun message côté JS) (`global.js:1499-1505`).
- Effets en base :
  - `INSERT INTO d_prospects VALUES (NULL, '$name', '$cp', '$ville', '$sms', '$projet', '$iduser','$date')` avec `nomprospect = "{civilite} {NOM} {Prénom}"`, valeurs passées par `htmlspecialchars(ENT_QUOTES)` (d'où `Mme &amp; M. ...` en base : 2 663 lignes de `prospects`), `date = date('Y-m-d H:i:s')` (`model.php:1443-1458`) ;
  - `Envoyer_Message_Perso(sender, 1, "Vous avez une dénonciation en attente", sms)` : `INSERT mp (idemetteur=sender, idreceveur=1, sujet, message=sms, date, lu='0', cle=sha1(HASH.time().idemetteur), parent=0)` (`json.php:1270-1271`, `model.php:2048-2063`). Le destinataire est l'utilisateur d'id 1 en dur.

### 4.4 Cycle de vie des prospects (référence, implémenté dans workflow)
- Création : `d_prospects` (§4.3).
- Acceptation : `MoveAskDenonciation` (`model.php:986-1015`, appelé `json.php:444`) : `INSERT INTO prospects ... SELECT ... FROM d_prospects` (nouvel `idprospect` auto-incrémenté), concaténation `'<div class="separator"></div>'.sms` dans `smspromoteur`, puis `DELETE FROM d_prospects`.
- Refus : `RejetAskDenonciation` (`model.php:900-925`, appelé `json.php:327`) : `REPLACE INTO r_prospects ... SELECT` en **conservant** l'`idprospect` de `d_prospects`, concaténation du message, puis `DELETE FROM d_prospects` (seulement si `count()==2`).
- Conséquence : les `idprospect` de `prospects` et `r_prospects` ne sont pas dans le même espace (1 421 identifiants communs aux deux tables dans le snapshot) ; un même prospect peut exister dans plusieurs tables.
- Suppression d'un prospect accepté : `DeleteDenonciation` (`model.php:432-439`, `json.php:14-28`). Aucune suppression prévue pour `r_prospects`.
- Compteurs du tableau de bord : `CountAskDenonciations` / `CountDenonciations` (`model.php:1188-1208`, `__main.php:34-35`).

## 5. Données

### 5.1 Tables (snapshot local du 2026-10-27, toutes MyISAM, `DEFAULT CHARSET=latin1`, connexion en `utf8` `database.php:41`)

`clients` — 212 lignes, AUTO_INCREMENT 216 :
| Colonne | Type | Remarque |
|---|---|---|
| idclient | int AI PK | |
| date | varchar(20) NOT NULL DEFAULT 'X' | texte libre ; 212/212 au format `jj/mm/aaaa` |
| source | varchar(250) DEFAULT 'X' | texte libre |
| projets | varchar(250) DEFAULT 'X' | texte libre (pas de FK vers `projet`) |
| civilite | varchar(20) DEFAULT 'X' | valeurs : `Mme` 82, `Mr` 60, `M.` 51, `Mr et Mme` 7, `Mlle` 6, `M.Mme` 6 |
| nom | varchar(250) DEFAULT 'X' | nom complet en un champ |
| adresse | varchar(255) DEFAULT 'X' | |
| tel | varchar(70) DEFAULT 'X' | 48 vides |
| email | varchar(200) DEFAULT 'X' | 24 vides |
| type_achat, type_logement, budget | varchar(200) DEFAULT 'X' | texte libre |
| observation, reponse | text NOT NULL | texte libre |

35 lignes contiennent l'entité `&#039;` dans nom/adresse/observation/réponse (texte stocké encodé HTML).

`prospects` (6 668 lignes, dates 2012-03-16 → 2026-10-27), `d_prospects` (1 ligne), `r_prospects` (1 949 lignes, 2013-05-21 → 2026-10-27) — même structure :
| Colonne | Type |
|---|---|
| idprospect | int AI PK |
| nomprospect | varchar(150) NOT NULL (« civilité NOM Prénom », encodé HTML) |
| cpprospect | varchar(6) NOT NULL |
| villeprospect | varchar(150) NOT NULL |
| smspromoteur | text NOT NULL (HTML : 6 655/6 668 lignes de `prospects` contiennent du balisage) |
| idprojet | int NOT NULL (→ `projet.projet_id`, sans FK) |
| iduser | int NOT NULL (→ `user.id`, sans FK ; `DEFAULT '1'` dans `d_prospects` seulement) |
| date | datetime NOT NULL |

Intégrité : aucun prospect sans projet ; 833 `prospects` et 250 `r_prospects` ont un `iduser` absent de `user`.

### 5.2 Requêtes d'origine
| Fonction | SQL | Fichier:ligne |
|---|---|---|
| `AddClients` | `db->set([...13 colonnes...])->insert('clients')` (double échappement, §9) | `model.php:782-799` |
| `UpdateClients` | `UPDATE clients SET date=…, …, reponse=… WHERE idclient=$id` (valeurs `db->escape`) | `model.php:533-553` |
| `GetClientById` | `SELECT * FROM clients WHERE idclient=$id` | `model.php:555-560` |
| `DeleteClient` | `DELETE FROM clients WHERE idclient=$id` | `model.php:562-568` |
| `SaveCSV` | `SELECT date,source,projets,civilite,nom,adresse,tel,email,type_achat,type_logement,budget FROM clients` | `model.php:776-780` |
| `SearchClient` | §4.1 | `model.php:801-822` |
| `GetClients` (communication) | `SELECT nom,email FROM clients ORDER BY nom` | `model.php:770-774` |
| `SearchClientEmailing` (communication) | `SELECT clients.* FROM clients WHERE …LIKE…` (3 mots-clés) | `model.php:605-657` |
| `SearchProspect` | §4.2 | `model.php:1407-1440` |
| `SearchAllProspect` (workflow) | `SELECT prospects.*, DATE_FORMAT(date,'%d.%m.%Y') AS date, projet_titre, user.name AS prescripteur … ORDER BY prospects.date DESC` | `model.php:1387-1405` |
| `AddProspect` | §4.3 | `model.php:1443-1458` |
| `SelectAllInProspect`, `GetProjetAndProspectByAskProspect` | `SELECT d_prospects.*, projet.projet_titre FROM d_prospects JOIN projet … WHERE idprospect=$id` | `model.php:91-100, 2309-2314` |
| `AskDenonciations`, `GetRefusedAsk*`, `Move/RejetAskDenonciation`, `DeleteDenonciation`, `UpdateDate` (`askden`/`den`) | lecture/écriture `d_prospects`, `prospects`, `r_prospects` | `model.php:1099-1114, 874-896, 900-925, 986-1015, 432-439, 382-393` |

### 5.3 Règles implicites
- `SessionAcces === '1'` (chaîne) = admin ; tout le reste = filtré sur `iduser`.
- Les dates clients sont du texte libre (`jj/mm/aaaa` en pratique) ; les dates prospects sont des `datetime` affichés `d.m.Y` ou `d.m.Y H:i`.
- La civilité prospect est préfixée au nom et non stockée à part.
- `smspromoteur` accumule l'historique des échanges (concaténation avec `<div class="separator"></div>`).

## 6. Emails

| Déclencheur | Destinataires | Expéditeur | Sujet exact | Corps | Transport |
|---|---|---|---|---|---|
| `json/AddProspect` après l'insertion (envoyé même si l'insertion échoue, `json.php:1290` avant le test `json.php:1291`) | constante `TO` (`config.php:148`, valeur non recopiée) | `EMAIL` / nom « Corim promotion » (`config.php:143`) | `[Corim promotion] - Vous avez une demande de dénonciation en attente` | Table HTML 500 px : logo `BASE.'medias/images/logo-corim.png'`, « Vous avez reçu un nouveau message concernant une demande de dénonciation », « Pour y accéder, vous pouvez cliquer <a href="BASE messagerie">ici</a> », bloc d'adresse Corim en dur (`json.php:1276-1283`) ; partie texte = `strip_tags($body)` | Swift SMTP SSL, `EMAIL_SMTP`:`EMAIL_PORT` (465), `EMAIL_USER`/`EMAIL_PASS` (`json.php:1268-1275, 1290`, `config.php:142-146`) |

Aucun e-mail dans `gestion_clients` ni `gestion_prospects`. Les données du prospect (nom, message) ne sont pas dans l'e-mail ; le message est copié dans la messagerie interne `mp` (§4.3).

## 7. Fichiers

### 7.1 Export CSV (`gestion_clients/telecharger_clients`, `gestion_clients.php:85-113`)
- Nom de fichier : `export.csv` ; en-têtes HTTP exacts :
  - `Content-disposition: attachment; filename=export.csv`
  - `Content-Type: application/force-download`
  - `Content-Transfer-Encoding: application/vnd.ms-excel` (suivi d'un `\n` dans la chaîne d'origine)
  - `Pragma: no-cache`
  - `Cache-Control: must-revalidate, post-check=0, pre-check=0, public`
  - `Expires: 0`
- Séparateur `;`, fin de ligne `\n` (LF), pas de BOM, aucun guillemet autour des champs.
- Ligne d'en-tête = noms de colonnes SQL : `date;source;projets;civilite;nom;adresse;tel;email;type_achat;type_logement;budget` (11 colonnes ; `idclient`, `observation`, `reponse` non exportés) (`model.php:777`). L'en-tête n'est écrit qu'à la première ligne de données : table vide → fichier vide.
- Valeurs : pour chaque champ, dans l'ordre : remplacement de `\n`, `\r`, `,`, `&amp;`, `&#039;` par une espace → `htmlspecialchars` (drapeaux par défaut PHP 5.6 : `ENT_COMPAT | ENT_HTML401`, donc `&`→`&amp;`, `"`→`&quot;`, `<`/`>` → `&lt;`/`&gt;`, apostrophe inchangée) → `utf8_decode` (sortie **ISO-8859-1**, caractères hors Latin-1 → `?`) → `trim` (`gestion_clients.php:100`). Un `;` dans une valeur n'est pas échappé (décalage de colonnes).
- Ordre des lignes : aucun `ORDER BY` (ordre physique MyISAM).
- Décision proposée : reproduire à l'identique les colonnes, le séparateur, l'encodage ISO-8859-1 et le nom ; corriger seulement l'échappement (`fputcsv` avec `;`, guillemets si nécessaire) et le type MIME (`text/csv; charset=ISO-8859-1`), à valider (Q3).

### 7.2 Import CSV (`medias/clients/import.csv`)
- Dépôt : `medias/uploadify/uploadify.php` — cible `$_SERVER['DOCUMENT_ROOT'] . $_POST["folder"]`, fichier toujours nommé `import.csv`, seule l'extension `csv` est contrôlée, réponse `1` ou `Invalid file type.` (`uploadify.php:2-16`). Partagé avec l'import de lots (`medias/csv/import.csv`, `ajouter_lots.php:28-39`, module programmes-lots).
- Lecture : `fgetcsv($file, 1024, ";")`, chaque champ `utf8_encode` (entrée attendue en ISO-8859-1/Windows-1252) (`gestion_clients.php:183-186`).
- Colonnes positionnelles, **sans ligne d'en-tête** (la première ligne est importée comme un client) : 0 date, 1 source, 2 projets, 3 civilite, 4 nom, 5 adresse, 6 tel, 7 email, 8 type_achat, 9 type_logement, 10 budget, 11 observation, 12 reponse (`gestion_clients.php:188-200`). Pas de contrôle du nombre de colonnes ni d'un `nom` non vide (contrairement à l'ajout unitaire).
- Fichier présent dans `OLD/` : `medias/clients/import.csv`, 56 307 octets, 495 lignes physiques, fins de ligne CRLF, encodage ISO-8859, **pas de ligne d'en-tête** (la première ligne contient déjà des données personnelles), champs entre guillemets contenant des retours à la ligne. Le contenu n'a pas été lu au-delà de la première ligne et n'est pas recopié.
- `medias/export/mydata.csv` : copie octet pour octet de `import.csv` (même md5), référencée nulle part dans le code (`grep` sur `application/` et `medias/` : aucune occurrence hors logs). Accessible publiquement (`medias/` sans `.htaccess`). Les logs montrent des scans de `mydata.*` (`application/logs/2021-12-06.log.php:930-936`).
- Droits : `medias/` inscriptible par Apache (CLAUDE.md, section sécurité).

### 7.3 Cible
- Import : `<input type="file" accept=".csv">` + `fetch` (ou POST classique) vers une route authentifiée admin, fichier stocké hors `public/` (`storage/app/private/clients/import.csv`), extension + MIME (`text/csv`, `text/plain`) contrôlés, chemin choisi par le serveur ; bouton « Supprimer le fichier CSV du serveur » rendu fonctionnel (Q5).
- Ne pas migrer `medias/clients/import.csv` ni `medias/export/mydata.csv` dans le dossier public (Q6).

## 8. Design

- Vues : `OLD/application/views/V_Clients.php`, `V_Ajouter_client.php`, `V_Ajouter_clients.php`, `V_Edition_Client.php`, `V_Denonciations.php` (partagée avec workflow) ; gabarit `OLD/application/views/template/template.php` (menu admin `Clients` / `Prospects` lignes 98-99).
- CSS : Bootstrap 3.3.6 CDN (`template.php:11`), `medias/tablesorter/css/theme.green.css` (`template.php:28`), `medias/confirm/jquery.confirm.css` (`template.php:67`), `medias/uploadify/uploadify.css` (`template.php:32`). Classes : `page-header`, `alert alert-warning|success`, `form-inline`, `btn btn-primary|danger btn-xs`, `tablesorter-green`, `special-small-size`, `hide`.
- JS : jQuery 1.11.3 + migrate (`template.php:18-19`), `medias/js/global.js` (`template.php:71`) : init tablesorter (`global.js:6`), recherche clients (`283-360`), recherche prospects (`1417-1470`), dénonciation (`1490-1509`).
- Plugins obsolètes et remplacement :
  - Uploadify 3.1 Flash (`template.php:31`, `V_Ajouter_clients.php:18-32`) → `<input type="file">` + `fetch`, même bouton « Parcourir », même rechargement de page.
  - jquery.confirm (`template.php:68`, `global.js:332`) → `<dialog>` avec les mêmes titre, message et boutons Oui/Non.
  - tablesorter (`template.php:27`) → petit tri JS natif, tri date `jj/mm/aaaa` sur la 1re colonne.
  - `eval('(' + callback + ')')` → `response.json()`.
- Rendu des lignes AJAX : construire le DOM avec `textContent` (même markup final) au lieu de concaténations HTML.

## 9. Bugs et failles constatés

| Problème | Fichier:ligne | Décision proposée (reproduire / corriger) |
|---|---|---|
| **Contrôle admin inopérant** : `header("Location: ...")` sans `exit()` après `!isAdmin()` ; tout utilisateur connecté peut modifier, supprimer, importer, et récupérer l'export CSV complet (corps envoyé dans la réponse 302) | `gestion_clients.php:9-11, 63-65, 79-81, 121-123, 169-171, 237-239`, `gestion_prospects.php:10-12` | Corriger : policy admin, 403/redirection effective |
| **`json/SearchClients` sans authentification** : un mot-clé d'une lettre renvoie nom, adresse, téléphone, e-mail, observations de tous les clients | `json.php:269-291`, `model.php:801-822` | Corriger : auth + admin ; garder le format de réponse |
| `json/SearchProspect` et `json/AddProspect` sans authentification ; `AddProspect` accepte `prescripteur_select` de n'importe qui (usurpation de prescripteur, envoi d'e-mail et de message interne) | `json.php:1216-1299` | Corriger : auth ; `prescripteur_select` pris en compte seulement pour un admin |
| `f_new_prospect_projet` non vérifié contre les privilèges du prescripteur sur le programme | `json.php:1250, 1269` | Corriger : policy programme (contrat auth-users-privileges) |
| Injection SQL : valeurs protégées uniquement par `htmlspecialchars` et placées entre quotes ; un antislash final ferme la chaîne (`AddProspect` : `prenom`/`cp`/... ; `SearchClient`/`SearchProspect` : erreur SQL) | `model.php:1443-1454, 803-819, 1409-1436` | Corriger : requêtes paramétrées |
| JSONP réfléchi sans filtre (`$_GET['callback']` écrit tel quel) → XSS | `json.php:1600-1601` | Corriger : pas de JSONP sur ces actions internes (le JS ne l'utilise pas) |
| `SearchProspect` avec un `type` hors 1/2/3 : `$query` indéfinie → erreur ; non connecté : `prospects.iduser =  AND` → erreur SQL affichée (page d'erreur Kohana) | `model.php:1414-1438` | Corriger : validation `in:1,2,3`, 422 |
| Double échappement dans `AddClients` : `escapeSQL` ajoute des quotes puis `db->set()` rééchappe → valeurs stockées avec quotes littérales (`'Dupont'`) ; aucune ligne de ce type dans le snapshot | `model.php:784-797`, `system/libraries/Database.php:831`, `drivers/Database.php:309-311` | Corriger (stocker la valeur brute) ; voir Q1 |
| XSS stockée : champs clients et prospects injectés en HTML (vues `echo` sans échappement, JS concaténé, `html_entity_decode` sur `smspromoteur`) | `V_Edition_Client.php:23-73`, `V_Ajouter_clients.php:87`, `global.js:301-320, 1438-1451`, `V_Denonciations.php:90` | Corriger : échappement Blade / `textContent` ; `smspromoteur` voir Q7 |
| XSS réfléchie dans les messages de succès (`civilite`, `nom` du POST) | `gestion_clients.php:47, 158`, `V_Clients.php:12`, `V_Ajouter_client.php:12` | Corriger : échappement, même texte |
| Suppression en GET sans CSRF ; aucun CSRF sur les formulaires | `gestion_clients.php:61-75`, `global.js:313, 339` | Corriger : `DELETE`/`POST` + CSRF, URL GET conservée en redirection vers la liste |
| `uploadify.php` sans auth, dossier cible pris dans `$_POST["folder"]` (écriture arbitraire) ; `medias/` exécute le PHP | `uploadify.php:3-12` | Corriger (CLAUDE.md, sécurité) |
| Fichiers CSV de données personnelles accessibles publiquement (`medias/clients/import.csv`, `medias/export/mydata.csv`) | §7.2 | Corriger : stockage privé, suppression après import |
| Message « Aucun fichier CSV n'est disponible sur le serveur. » jamais affiché (affecté à `template->content` puis écrasé) | `gestion_clients.php:228, 231` | Corriger : afficher le message dans la vue |
| `import.csv` jamais supprimé : réimports en double ; bouton « Supprimer le fichier CSV du serveur » sans action (`href="#"`) | `gestion_clients.php:177-219`, `V_Ajouter_clients.php:45` | Corriger (Q5) |
| Test d'existence incohérent : `$_SERVER['DOCUMENT_ROOT'].'/medias/...'` contre `DOCUMENT_ROOT` (inclut `SITE_URL`) | `V_Ajouter_clients.php:44` vs `:50, :58` | Corriger : un seul chemin |
| `fgetcsv` limité à 1024 octets par ligne : observations longues coupées en plusieurs « clients » | `gestion_clients.php:183`, `V_Ajouter_clients.php:83` | Corriger : pas de limite (`0`) |
| Première ligne du CSV importée comme donnée (pas de gestion d'en-tête) | `gestion_clients.php:183-219` | Reproduire (format sans en-tête) ; voir Q4 |
| Grammaire « 1 client ont été ajouté » | `gestion_clients.php:221` | Reproduire le texte exact (ou corriger, Q8) |
| Édition sans changement : aucun message (0 ligne affectée) | `gestion_clients.php:46` | Corriger : afficher le succès si la ligne existe |
| `edition_client` / `supprimer_client` sans id : erreur PHP 500 « Missing argument 1 » (logs 2021-2023) | `gestion_clients.php:7, 61`, `application/logs/2021-10-19.log.php` | Corriger : 404 |
| Export CSV : `;` non échappé, entités HTML dans les valeurs, MIME `application/force-download`, `Content-Transfer-Encoding` invalide | `gestion_clients.php:100, 106-108` | Corriger l'échappement, garder colonnes/encodage/nom (Q3) |
| `json/AddProspect` envoie l'e-mail même si l'insertion échoue ; `errors:2` sans message côté JS | `json.php:1290-1295`, `global.js:1499-1505` | Corriger : e-mail après succès ; message d'erreur générique |
| Page « Prospects » = page Dénonciations sans liste initiale | `gestion_prospects.php:13` | Reproduire (Q2) |
| `SearchProspect` masque les prospects dont le prescripteur a été supprimé (INNER JOIN user) | `model.php:1420-1436` | Reproduire, voir Q9 |
| `cpprospect varchar(6)` ; un code postal plus long fait échouer l'insertion en mode strict (local) ou est tronqué (non strict) | `SHOW CREATE TABLE d_prospects` | Corriger : validation 5 chiffres ou longueur max 6 |
| Mot de passe MySQL en clair dans le modèle (connexion `mysqli` inutilisée) | `model.php:8` | Corriger (secrets dans `.env`) |

## 10. Critères d'acceptation

1. Un visiteur non connecté qui ouvre `gestion_clients`, `gestion_clients/ajouter_client`, `ajouter_clients`, `edition_client/{id}`, `supprimer_client/{id}`, `telecharger_clients`, `gestion_prospects` est redirigé vers `connexion`.
2. Un prescripteur connecté sur ces mêmes URL est redirigé vers l'accueil et aucune écriture ni export n'a lieu (aucune ligne modifiée/supprimée, pas de corps CSV).
3. `POST index.php/json/SearchClients/` non connecté ou non admin : refusé (401/403), aucune donnée client.
4. Admin, `SearchClients` avec recherche vide → `{"errors":0}` ; sans résultat → `{"errors":2}` ; avec résultat → `{"errors":1,"call":[...]}` contenant les 14 colonnes de `clients`.
5. La recherche clients porte sur date, source, projets, nom, adresse, tel, email, type_achat, type_logement, budget (pas civilite, observation, reponse).
6. `ajouter_client` avec `nom` vide affiche « Veuillez renseigner le nom du nouveau client » et n'insère rien.
7. `ajouter_client` valide insère une ligne avec les 13 valeurs exactes saisies (sans quotes ajoutées) et affiche « Le client {civilite} {nom} a été ajouté » échappé.
8. `edition_client/{id}` affiche les 13 champs préremplis ; un POST valide met à jour la ligne et affiche « Le client {civilite} {nom} a été modifié ».
9. `edition_client/{id}` avec id inexistant affiche seulement l'en-tête ; sans id → 404.
10. La suppression d'un client exige un POST/DELETE avec CSRF, passe par la boîte de dialogue « Supprimer ce client », et affiche « Le client a été supprimé ».
11. `telecharger_clients` renvoie `export.csv` en pièce jointe, encodé ISO-8859-1, séparateur `;`, fins de ligne LF, première ligne `date;source;projets;civilite;nom;adresse;tel;email;type_achat;type_logement;budget`, une ligne par client ; table vide → fichier vide.
12. Un CSV ISO-8859-1 sans en-tête, 13 colonnes `;`, est déposé (admin uniquement, extension/MIME contrôlés) puis prévisualisé sur 13 colonnes ; « Enregistrer en masse » insère une ligne par enregistrement et affiche « {n} clients ont été ajoutés » / « 1 client ont été ajouté » / « Problème lors de l'enregistrement ».
13. Un fichier non CSV déposé est refusé avec « Le format de fichier CSV n'est pas reconnu ».
14. Le fichier d'import n'est pas accessible par URL publique.
15. `gestion_prospects` (admin) affiche la vue Dénonciations, titre « Gestion des prospects », tableau vide, radios 1/2/3.
16. `SearchProspect` admin type 1/2/3 filtre respectivement sur `nomprospect`, `projet_titre`, `user.name` ; prescripteur : uniquement ses prospects ; `date` au format `dd.mm.YYYY HH:ii`.
17. `SearchProspect` avec type hors 1/2/3 → 422 sans erreur SQL.
18. `AddProspect` connecté : champ obligatoire manquant → `{"errors":0}` ; succès → `{"errors":1}`, une ligne `d_prospects` (`nomprospect = "{civilite} {NOM} {Prénom}"`), une ligne `mp` vers l'utilisateur 1 avec le sujet « Vous avez une dénonciation en attente », un e-mail au sujet exact `[Corim promotion] - Vous avez une demande de dénonciation en attente` capturé par le mailer `log`.
19. `AddProspect` par un prescripteur avec `prescripteur_select` d'un autre utilisateur : le prospect est enregistré sous son propre `iduser`.
20. `AddProspect` non connecté → refusé (401), aucune écriture.
21. Les valeurs contenant `<script>`, `'`, `\` sont stockées telles quelles et réaffichées échappées dans toutes les vues et tableaux AJAX.
22. Les données migrées : 212 clients, 6 668 prospects, 1 / 1 949 lignes `d_prospects` / `r_prospects` retrouvées avec des valeurs identiques (hors décodage d'entités décidé en Q7).

## 11. RGPD

### 11.1 Données personnelles stockées
| Où | Personnes | Données | Volume (snapshot) |
|---|---|---|---|
| `clients` | contacts / acquéreurs potentiels | civilité, nom, adresse, téléphone, e-mail, budget, type d'achat/logement, source, observations et réponses en texte libre | 212 |
| `d_prospects`, `prospects`, `r_prospects` | prospects dénoncés | civilité + nom + prénom, code postal, ville, message libre (`smspromoteur`), prescripteur (`iduser`), programme, date | 1 / 6 668 / 1 949 |
| `mp.message` | prospects (indirect) | copie du message de dénonciation (`json.php:1271`) | 10 596 messages (toutes origines) |
| `options`, `d_options`, `reservations`, `d_reservations` (module workflow) | acquéreurs | `nom`, `adresse`, `message` | 1 756 / 0 / 603 / 0 |
| `appartements.client` (workflow) | acquéreurs | nom du client sur le lot acté (`model.php:750`) | — |
| `docs` (module public) | visiteurs | e-mail | 2 135 |
| `emailing.destinataire` (communication) | destinataires | e-mail | 2 |
| `sessions` | utilisateurs | données de session | 890 (non domaine) |
| `medias/clients/import.csv`, `medias/export/mydata.csv` | contacts | fichier client complet (13 colonnes), public | 2 fichiers identiques |
| Logs Kohana (`application/logs`) | tous | les pages d'erreur contiennent les requêtes SQL, donc potentiellement des valeurs saisies | — |

Aucune donnée sensible au sens de l'article 9 n'est prévue par le schéma ; les champs libres (`observation`, `reponse`, `smspromoteur`) peuvent en contenir. Aucune date de création n'existe pour `clients` (le champ `date` est un texte libre) : la durée de conservation ne peut pas être calculée de façon fiable pour cette table.

### 11.2 À prévoir dans la refonte
- **Export par personne** (droit d'accès / portabilité) : commande ou écran admin qui recherche une personne par nom/e-mail/téléphone dans `clients`, `d_prospects`, `prospects`, `r_prospects`, `options`, `reservations`, `appartements.client`, `mp.message`, et produit un JSON/CSV. Pas d'identifiant commun entre tables : rapprochement par nom normalisé (décodage des entités HTML, civilité retirée) et, pour `clients`, e-mail/téléphone.
- **Purge** : suppression ou anonymisation coordonnée sur les mêmes tables ; conserver les lignes de `prospects` utiles à l'historique commercial en anonymisant `nomprospect`, `smspromoteur` (Q10). Supprimer aussi les fichiers d'import.
- **Durée de conservation** : tâche planifiée (scheduler Laravel) de purge/anonymisation des `prospects` et `r_prospects` au-delà d'une durée à fixer (`date` disponible) ; pour `clients`, ajouter `created_at`/`updated_at` dans la migration (valeur de départ = date de migration) (Q10).
- **Minimisation** : ne plus conserver de fichier CSV après import ; ne pas journaliser de données personnelles ; `APP_DEBUG=false`.
- **Sécurité** : accès admin uniquement aux clients, prescripteur limité à ses prospects (policies), CSRF, échappement, chiffrement du transport.
- **Registre des traitements / information des personnes** : hors code, à signaler au client.

## 12. Questions ouvertes

1. L'ajout de clients (unitaire et en masse) est-il encore utilisé ? Le code actuel stockerait des valeurs entourées de quotes et aucune ligne ainsi n'existe en base (AUTO_INCREMENT 216 pour 212 lignes) : les 212 clients semblent antérieurs à ce code ou importés autrement.
2. La page « Prospects » doit-elle rester la page Dénonciations vide (recherche seule), ou afficher la liste comme `denonciations` ? Le module est-il encore utilisé tel quel ?
3. Export CSV : faut-il garder strictement le format actuel (entités HTML dans les valeurs, `;` non échappé, ISO-8859-1) parce qu'un outil tiers le consomme (Excel ?), ou peut-on produire un CSV correctement échappé avec les mêmes colonnes et le même encodage ? Faut-il ajouter `observation`/`reponse` (présents à l'import, absents de l'export) ?
4. Le fichier d'import doit-il rester sans ligne d'en-tête ? (Fichier actuel sans en-tête.)
5. Après import : supprimer automatiquement le CSV, ou garder le bouton « Supprimer le fichier CSV du serveur » (aujourd'hui inactif) ?
6. `medias/clients/import.csv` et `medias/export/mydata.csv` (copie identique, d'origine inconnue, datée de juillet 2020) : à supprimer de la prod dès maintenant ? À exclure de la migration ?
7. Décoder les entités HTML stockées (`&#039;`, `&amp;` dans `nomprospect`, `smspromoteur`, `clients`) lors de la migration, et comment afficher `smspromoteur` (HTML conservé avec liste blanche, ou texte) ?
8. Textes avec fautes (« 1 client ont été ajouté », « Votre session a expirée ») : reproduire à l'identique ou corriger ?
9. Prospects rattachés à un prescripteur supprimé (833 + 250) : restent-ils invisibles ou faut-il les afficher (ex. « prescripteur supprimé ») ?
10. Durées de conservation RGPD pour clients et prospects (acceptés et refusés), et choix suppression vs anonymisation.
11. Le destinataire en dur `idreceveur = 1` de la messagerie et la constante `TO` de l'e-mail doivent-ils devenir configurables (et vers quel(s) compte(s)) ?
12. Les clients (`clients.projets`, texte libre) doivent-ils être liés aux programmes (`projet`) dans le nouveau schéma, ou rester en texte libre (iso-fonctionnel) ?
