# Contrat — communication

Statut : brouillon (à valider par Victor)
Sources lues :
- `OLD/application/controllers/messagerie.php` (1-78), `message.php` (1-142), `emailing.php` (1-136), `emails.php` (1-26), `flash_info.php` (1-112), `agenda.php` (1-74), `contact.php` (1-69), `formulaire.php` (1-62)
- `OLD/application/controllers/__main.php` (1-116), `__form.php` (1-16), `__json.php` (1-48), `json.php` (1-13, 78-95, 1301-1314, 1597-1605), `search.php` (7-30, 137-138), `firstsearch.php` (20-32)
- `OLD/application/libraries/Calendar.php` (1-200)
- `OLD/application/models/model.php` (5-23, 26-89, 136-145, 405-430, 605-657, 770-774, 1460-1490, 1568-1597, 1641-1663, 1694-1700, 1885-1893, 1922-2095)
- Vues `OLD/application/views/` : `V_messagerie.php`, `V_message.php`, `V_emailing.php`, `V_Emails.php`, `V_Flash_Info.php`, `V_Add_Flash_Info.php`, `V_Edit_Flash_Info.php`, `V_Agenda.php`, `V_Contact.php`, `template/template.php` (1-125), `template/template_welcome.php` (45-63), `template/json.php`
- `OLD/medias/js/global.js` (51-54, 143-215, 1472-1488, 1539-1567, 1790-1807)
- `OLD/system/libraries/drivers/Database.php` (302-325) pour la sémantique de `escape()`
- Base locale `sql_corim_vente_` (snapshot prod du 2026-10-27) : `SHOW CREATE TABLE` de `mp`, `emailing`, `news`, `agenda`, `docs` ; comptages uniquement.

## 1. Périmètre

Le module regroupe toute la communication de l'application :

- **Messagerie privée interne** (table `mp`) : chaque utilisateur connecté voit les messages qu'il a reçus ou envoyés, les consulte (marqués lus), y répond. L'admin voit sa boîte sans les notifications automatiques du workflow. Le compteur de non-lus est affiché dans le menu.
- **Envoi de message (admin)** : l'admin écrit à un prescripteur, à tous les prescripteurs, aux prescripteurs d'un programme (message interne), ou à un/tous les clients (email réel via `mail()`).
- **Emailing groupé (admin)** : email HTML (CKEditor) à un/tous les prescripteurs, aux prescripteurs ayant accès à un programme, à un/tous les clients, ou à une sélection de clients trouvée par mots-clés (AJAX). Chaque envoi est historisé dans `emailing`.
- **Page Emails (admin)** : liste en lecture des demandes de plans enregistrées dans `docs` par l'API publique (`firstsearch?action=email`).
- **Flash infos (admin)** : CRUD de la table `news` (titre, lien, sous-titre, info, footer, vignette = image d'un programme). Ces infos sont exposées publiquement par `search/news?action=allnews` (module `public-api`).
- **Agenda** : désactivé en prod (toutes les actions redirigent vers l'accueil), table `agenda` vide.
- **Contact (« Contacter Corim »)** : un utilisateur connecté envoie un message à l'admin (message interne + email de notification).
- **Formulaire de demande d'infos** (`/formulaire`) : formulaire public envoyant un email à Corim ; cassé en prod (vue absente).

## 2. Rôles et droits

Les rôles sont définis dans le module `auth-users-privileges`. Valeurs observées : `privileges.acces = 1` (admin, 1 seul utilisateur, `iduser = 1`), `privileges.acces = 0` (prescripteur, 264 lignes). Session : `SessionId` (id user), `SessionAcces` (`'1'`/`'0'`).

| Rôle (valeur en base) | Peut faire | Contrôle dans l'ancien code (fichier:ligne) |
|---|---|---|
| Anonyme | `/formulaire` (cassé) ; `json/SearchClientEmailing` et `json/DeleteEvent` (aucun contrôle, faille) ; `agenda/calendar/*` (méthode publique sans contrôle, sans effet) | `formulaire.php:3` hérite de `__form.php:3-14` sans contrôle ; `json.php:78-95`, `json.php:1301-1314` sans appel à `isConnected` ; `agenda.php:67-71` |
| Prescripteur (`acces = 0`) | Messagerie (liste, consulter, supprimer, supprimer tout), répondre, contact | `isConnected()` `__main.php:46-53` appelé dans `messagerie.php:8,23,48,65`, `message.php:8,13,45`, `contact.php:10` |
| Admin (`acces = 1`) | Tout ce qui précède + envoyer un message, emailing, page Emails, Flash infos | `isAdmin()` `__main.php:55-61` dans `emailing.php:11`, `emails.php:11`, `flash_info.php:11` ; test direct `SessionAcces==='1'` dans `message.php:47`, `emailing.php:19`, `messagerie.php:14` |

Attention : les contrôles admin font `header("Location: ...")` **sans `exit`** (`emailing.php:12`, `emails.php:12`, `flash_info.php:12`, `message.php:48`). Le code continue de s'exécuter et le corps de la page est envoyé (voir §9). Le contrat retient le comportement **voulu** : 302 vers `/` sans effet ni contenu.

Menu : liens Emailing / Emails / Flash info visibles seulement si `SessionAcces==='1'` (`template.php:92-104`, `template_welcome.php:45-55`) ; Messagerie (+ badge non-lus) et « Contacter Corim » pour tous les connectés (`template.php:106-112`, `template_welcome.php:58-63`).

## 3. Écrans et URL

Routage Kohana par défaut `/<contrôleur>/<méthode>/<arg>` (pas de `routes.php` applicatif). `SITE_URL = '/'` (`config.php:8,128`). Toute page non connectée redirige vers `/connexion` (`__main.php:50`). Gabarit : `template/template.php` (`__main.php:38`). Titre de page préfixé par `Corim promotion - ` quand le contrôleur utilise `.=` (`__main.php:43`), échappé par `html::specialchars` (`template.php:9`).

Messages d'erreur de validation générés en boucle : `Le champ <nom> est requis <br />` (ex. `message.php:34`, `emailing.php:123`, `contact.php:60`, `agenda.php:39`).

### 3.1 `GET /messagerie` — boîte de messages
- Rôle : connecté. `messagerie.php:7-20`. Vue `V_messagerie`. Titre « Corim Promotion - Messagerie privée » (`messagerie.php:11`).
- Admin : `Get_Message_Admin` (`model.php:1971-1995`) ; sinon `Get_Message` (`model.php:1958-1970`) (`messagerie.php:14-18`).
- Affichage (`V_messagerie.php:13-35`) : une ligne par message, classe `news` si `lu=0`, `news_read` sinon ; date `dd.mm.YYYY<br />HH:ii` ; nom de l'émetteur en gras + sujet, lien `messagerie/consulter/{cle}` et bouton « CONSULTER ».
- En-tête : « Messagerie privée », plus lien « Envoyer un message » (`message/envoyer`) pour l'admin (`V_messagerie.php:3`).
- Un formulaire `form_messagerie` caché (`delete_message`) est rendu (`V_messagerie.php:16-17`) mais aucune case à cocher `.checkboxvide` ni bouton n'est généré par la vue : la suppression sélective n'est plus accessible depuis l'interface (JS orphelin `global.js:1539-1567`, `1790-1807`).

### 3.2 `GET /messagerie/consulter/{cle}` — lire un message
- Rôle : connecté. `messagerie.php:22-44`. Vue `V_messagerie`. Titre « Corim Promotion - Consultation des messages privés ».
- `Get_ReadingMessage($me, $cle)` (`model.php:1996-2008`) : **filtre sur `cle` seulement**, pas sur l'utilisateur.
- Fil : racine = `id` si `parent=0`, sinon `parent` (`messagerie.php:30-35`) ; `GetParentMessage` (`model.php:2023-2034`) puis `GetChildMessage(racine)` (`model.php:2010-2021`).
- Marquage lu : `Get_MessageRead` met `lu=1` où `idreceveur=me AND cle=...` (`model.php:2065-2074`, appel `messagerie.php:43`).
- Affichage (`V_messagerie.php:36-65`) : bloc `last_message` (date, nom, « Objet du message : », corps brut) ; si racine et enfants existent, titre « Conversation », bloc `first_message` puis un `item_message` par enfant.
- Lien « Répondre au message » (`message/repondre/{cle}`) si l'émetteur n'est pas l'utilisateur courant (`V_messagerie.php:3`).
- Note : la liste `Get_Message` n'est pas réaffichée sur cette page.

### 3.3 `POST /messagerie/supprimer`
- Rôle : connecté. `messagerie.php:47-63`. Paramètre `delete_message` (string, clés séparées par `|`, terminée par `|`). Le dernier élément est retiré (`array_pop`, `messagerie.php:55`).
- `Del_Message` : `DELETE FROM mp WHERE idreceveur=$id AND cle IN (...)` (`model.php:2076-2085`).
- Affiche « N messages effacés avec succès » / « 1 message effacé avec succès » (`V_messagerie.php:6-12`) puis la liste (non admin : `Get_Message` même pour l'admin, `messagerie.php:58`).
- Sans POST : redirection `/messagerie` (`messagerie.php:61`).

### 3.4 `GET /messagerie/supprimer_tout`
- Rôle : connecté. `messagerie.php:64-72`. Aucun lien dans les vues. `Del_AllMessage` : `DELETE FROM mp WHERE idreceveur=$id` (`model.php:2087-2095`). Affiche le nombre supprimé. Action destructive en GET, sans confirmation ni CSRF.

### 3.5 `/messagerie/<autre>`
- `__call` affiche le texte « This text is generated by __call. If you expected the index page, you need to use: welcome/index/... » (`messagerie.php:74-77`). Décision proposée : 404.

### 3.6 `GET /message` → redirection `/` (`message.php:7-10`).

### 3.7 `GET|POST /message/repondre/{cle}`
- Rôle : connecté. `message.php:12-42`. Vue `V_message` (branche réponse, `V_message.php:105-138`). Titre « Corim Promotion - Répondre à un message ».
- Affiche l'ancien message (bloc `oldmsg` : « Objet du message : » + corps), destinataire = nom de l'émetteur, textarea `message`, bouton « ENVOYER LE MESSAGE ». Champs cachés `objet` (= sujet d'origine) et `destinataire` (= `idemetteur` d'origine) (`V_message.php:134-135`).
- POST : `objet`, `message`, `destinataire` obligatoires (`message.php:19-22`). Insertion `Envoyer_Message_Perso(me, $_POST['destinataire'], objet, message, parent = id du message lu)` (`message.php:28`). Succès : « Votre message à bien été envoyé » (`message.php:29`).
- Le titre H3 reste « Envoyer un nouveau message » (`V_message.php:2`).
- Aucun email n'est envoyé.

### 3.8 `GET|POST /message/envoyer` — nouveau message (admin)
- Rôle : admin (`message.php:47`, sans `exit`). Vue `V_message` (branche formulaire, `V_message.php:15-103`). Titre « Corim Promotion - Envoyer un nouveau message ».
- Listes : `getPrescripteurs()` (`model.php:1568-1576`, tous les `acces=0`, actifs ou non), `GetProgrammes()` (`model.php:1694-1700`, `archive=0`), `GetClients()` (`model.php:770-774`).
- Formulaire : `objet` (texte), radio `method` ∈ {`prescripteur`, `client`, `programme`}, `prescripteur_select` (`0`=Choisir, `all`, ou `user.id`), `client_select` (`0`, `all`, ou `clients.email`), `programme_select` (`0` ou `projet_id`), `message` (textarea simple, pas de CKEditor).
- Règles (`message.php:58-130`) :
  - `objet` et `message` obligatoires.
  - `method` vide → « Veuillez choisir un destinataire » (`message.php:121`).
  - `prescripteur` : `0` → « Veuillez choisir un prescripteur » (`:78`) ; `all` → un `mp` par prescripteur (`:67-71`) ; sinon un `mp` vers l'id (`:73-74`).
  - `client` : `0` → « Veuillez choisir un client » (`:99`) ; sinon **email réel** (§6.2) à tous les clients ou à l'adresse choisie (`:84-95`).
  - `programme` : `0` → « Veuillez choisir un programme » (`:113`) ; sinon un `mp` par prescripteur de `GetPrescripteursByProject` (`model.php:1578-1597`) (`:105-109`).
  - Succès : « Votre message à bien été envoyé ». Erreur : formulaire réaffiché avec `objet`, `method`, `message` (`callback`).

### 3.9 `GET|POST /emailing` — emailing groupé (admin)
- Rôle : admin (`emailing.php:7-14` + `:19`). Vue `V_emailing`. Titre « Corim Promotion - Envoyer un emailing ».
- Formulaire (`V_emailing.php:19-121`) : `objet`, radio `method` ∈ {`prescripteur`, `client`, `programme`, `keywords`}, `prescripteur_select` (`0`, `all`, ou **email** du prescripteur, `V_emailing.php:40`), `client_select` (`0`, `all`, email client), `programme_select` (`0` ou `projet_id`), `keyword_select1..3` (non postés au serveur pour l'envoi), `keyword_select` (caché, emails séparés par `;`, alimenté par §4.1), `message` (CKEditor, `V_emailing.php:124`).
- Libellés : « Prescripteur(s) », « Client(s) », « Prescripteur(s) par programme », « Recherche par Mots-clé », boutons « Rechercher », « Vider la liste » (vide `keyword_select` et le compteur, `global.js:51-54`), compteur « N personne(s) dans la liste » (`V_emailing.php:104-111`).
- Règles (`emailing.php:27-124`) :
  - `objet`, `message` obligatoires.
  - `prescripteur` : `0` → « Veuillez choisir un prescripteur » (`:57`) ; `all` → email à chaque `getPrescripteurs()` (`:45-48`) puis historique `('prescripteur','all',...)` (`:49`) ; sinon email à l'adresse postée + historique `('prescripteur', email, ...)` (`:51-53`).
  - `programme` : `0` → « Veuillez choisir un programme » (`:78`) ; destinataires `GetEmailByAccess` (`model.php:405-416`) ; aucun → « Aucun prescripteur n'est enregistré sur ce projet » (`:73`) ; sinon emails + historique `('programme', projet_id, ...)` (`:64-71`).
  - `client` : `0` → « Veuillez choisir un client » (`:97`) ; `all` → tous `GetClients()` (`:85-89`) ; sinon l'adresse postée (`:91-93`).
  - `keywords` : `keyword_select` vide → « Attention, aucun client n'a été sélectionné » (`:110`) ; sinon `explode(';')` et un email par adresse (`:103-106`), historique `('autre', liste, ...)` (`:107`).
  - Aucun `method` → « Attention, aucun type de destinataire n'a été sélectionné » (`:115`).
  - Succès : « Votre message à bien été envoyé » (`alert alert-success`) ; erreurs en `alert alert-warning`.

### 3.10 `GET /emails` — emails provenant de Corim Promotion (admin)
- Rôle : admin (`emails.php:7-14`). `emails.php:16-24`. Vue `V_Emails`. Titre « Corim promotion - Emails provenant de Corim Promotion ».
- Données : `SELECT * FROM docs ORDER BY time DESC` (`model.php:85-89`), 2135 lignes.
- Tableau `tablesorter-green` (`V_Emails.php:8-44`) : Date (`d/m/Y` de `time`), Email, Programme (titre via `Get_Project_Name_By_Key(segment 0 de l'URL)`, sinon la clé brute), Plan (segment 2 de l'URL). URL nettoyée des préfixes `https://corim-vente.com/medias/programmes/` et `http://...` (`V_Emails.php:20-26`). Une requête SQL par ligne (`V_Emails.php:28-29`).
- Rien si 0 ligne (pas de message « aucun »).

### 3.11 `GET /flash_info` — liste (admin)
- `flash_info.php:63-71`. Vue `V_Flash_Info`. Titre « Corim promotion - Flash Info pour Corim Promotion ».
- `getAllInfos` : `SELECT * FROM news ORDER BY date DESC` (`model.php:72-76`).
- Tableau (`V_Flash_Info.php:8-46`) : Date (`d/m/Y`), vignette `medias/news/{thumb}.jpg` hauteur 160, Titre (`<h3>`, lien si `url` non vide), sous-titre `<h4>`, `string3`, `string4` (HTML via `htmlspecialchars_decode`), boutons « Editer » (`flash_info/edit/{id}`) et « Supprimer » (`flash_info/delete/{id}`). Lien « Ajouter un Flash Info » dans le titre.

### 3.12 `GET|POST /flash_info/add`
- `flash_info.php:32-61`. Vue `V_Add_Flash_Info`. Titre « Corim promotion - Ajouter un Flash Info pour Corim Promotion ».
- Champs : `string1` (Titre), `url` (Lien sur le titre), `string2` (Sous Titre), `string3` (Info, CKEditor), `string4` (Footer, CKEditor), `thumb` (select : `projet_id` des programmes non archivés + `0` « AUTRE »). Chaîne vide → `NULL` (`:39-45`).
- `thumb` non vide → `AddFlashInfoById` (`model.php:33-47`), « Le flash info a été ajouté » ; sinon « La vignette est obligatoire » (`:52`).
- Formulaire en `multipart/form-data` sans champ fichier (`V_Add_Flash_Info.php:14`).

### 3.13 `GET|POST /flash_info/edit/{id}`
- `flash_info.php:73-110`. Vue `V_Edit_Flash_Info`. Titre « Corim promotion - &Eacute;diter un Flash Info pour Corim Promotion » (affiché littéralement à cause de l'échappement `template.php:9`).
- POST : mêmes champs + `id` caché ; `thumb` et `id` requis → `UpdateFlashInfoById` (`model.php:49-63`, **remet `date` à `time()`**), « Le flash info a été mis à jour » ; sinon « La vignette est obligatoire ».
- `getInfoById($id)` (`model.php:65-70`) ; si ≠ 1 ligne → redirection `/` (`:102-104`, sans `exit`).
- Vue : H1 « Modifier le Flash Info {string1} », champs préremplis, aperçu de la vignette (`V_Edit_Flash_Info.php:45-50`).

### 3.14 `GET /flash_info/delete/{id}`
- `flash_info.php:16-30`. `getInfoById` ; si ≠ 1 → redirection `/` (sans `exit`) ; `DeleteFlashInfoById` (`model.php:26-31`) ; redirection `/flash_info`. Suppression en GET, sans confirmation.

### 3.15 Agenda (désactivé)
- `GET /agenda` : `isConnected` puis redirection `/` (`agenda.php:7-12`).
- `/agenda/event`, `/agenda/show/{Y-m}`, `/agenda/classique` : `header("Location: /")` en première ligne puis le code continue (`agenda.php:15,48,58`) : vue `V_Agenda` rendue dans le corps de la 302, et `event` en POST **insère** un évènement (`agenda.php:19-34`, `insertEvent` `model.php:1470-1482`).
- `/agenda/calendar/{date}` : méthode publique sans `isConnected` (`agenda.php:67-71`), sans effet visible.
- Comportement voulu retenu : toutes ces URL redirigent vers `/` (connecté) ou `/connexion`, sans écriture. Table `agenda` : 0 ligne. Voir §11.

### 3.16 `GET|POST /contact` — Contacter Corim
- Rôle : connecté. `contact.php:8-67`. Vue `V_Contact`. Titre « Corim promotion - Nous contacter ».
- Liste « Programme concerné » : `GetProgrammesByAccess(me)` (`model.php:1652-1663`), valeur de l'option = **titre** du programme (`V_Contact.php:28`), `0` = « Choisir un programme ».
- POST : `objet`, `message` obligatoires (`contact.php:19-20`). Corps = `<b>Programme concerné :</b> {titre}<br /><br />{message}` si programme ≠ `0` (`:25-30`). `Envoyer_Message(me, objet, corps)` → `mp` vers `idreceveur = '1'` codé en dur (`model.php:2036-2046`). Puis email de notification (§6.1). « Votre message à bien été envoyé » seulement si `send()` renvoie 1 (`:52-54`).
- Les champs ne sont pas réaffichés après erreur (`V_Contact.php:20,35`).

### 3.17 `GET|POST /formulaire` — demande d'infos (public)
- `formulaire.php:8-61`, contrôleur `__Form_Controller` qui charge `template/formulaire` (`__form.php:12`) puis la vue `formulaire` (`formulaire.php:10`). **Aucune de ces deux vues n'existe dans `OLD/`** : la page lève une exception Kohana (HTTP 500) avant tout traitement.
- Logique prévue : `nom`, `prenom`, `email`, `commentaire` obligatoires ; `adresse`, `cp`, `ville`, `fax`, `telephone` facultatifs (`:14-27`) ; email §6.4 ; message « VOTRE MESSAGE A ETE ENVOYE AVEC SUCCES » (`:51`).

## 4. Actions AJAX / JSON

Contrôleur `json.php` hérite de `__Json_Controller` (`__json.php:3-15`) : gabarit `template/json` (affiche `$content`), **aucun contrôle de session** pour ces deux actions. Réponse : `JsonMe` (`json.php:1597-1605`) écrit `json_encode($data)`, ou `{callback}({json});` si `$_GET['callback']` est présent (valeur non filtrée). Content-Type par défaut Kohana (HTML). Sans POST : corps vide.

### 4.1 `POST /index.php/json/SearchClientEmailing/`
- Appelant : `global.js:143-215` (bouton `#keyword_select_push`, au moins un mot-clé sinon message client « Veuillez saisir au moins un mot clé pour effectuer une recherche de client. »).
- Paramètres : `keyword1`, `keyword2`, `keyword3` (string, facultatifs).
- Requête `SearchClientEmailing` (`model.php:605-657`) : chaque mot-clé non vide produit `clients.date LIKE '%k%' OR source OR projets OR nom OR adresse OR tel OR email OR type_achat OR type_logement OR budget`, groupes joints par `AND` **sans parenthèses** ; mots-clés passés par `htmlspecialchars(ENT_QUOTES)` (`model.php:13`). Tous vides → `WHERE` vide → erreur SQL.
- Réponse : `{"call":[{<toutes les colonnes de clients>}],"errors":1}` si résultat, sinon `{"errors":0}` (`json.php:87-93`). Colonnes `clients` : `idclient,date,source,projets,civilite,nom,adresse,tel,email,type_achat,type_logement,budget,observation,reponse`.
- Côté client : tableau dans une colorbox 980×580 avec cases `myclient[]` (valeur = email), bouton « VALIDER LA SELECTION » qui remplit `#keyword_select` (emails joints par `;`) et « N personne(s) dans la liste » ; aucun résultat → « Aucun résultat pour la recherche. » (`global.js:162-210`). Le JS lit `item.projet` (colonne réelle `projets`) → affiche `undefined` (`global.js:169`).
- Effet en base : aucun.

### 4.2 `POST /index.php/json/DeleteEvent/`
- Appelant : `.deleteEvent` dans `V_Agenda` (`global.js:1472-1488`), redirige vers `agenda/classique` si succès.
- Paramètre : `idevent`. `DELETE FROM agenda WHERE agenda_id = ...` (`model.php:1460-1468`), sans filtre utilisateur.
- Réponse : `{"errors":1}` si 1 ligne supprimée, sinon `{"errors":2}` (`json.php:1307-1312`).
- Décision proposée : ne pas reprendre (agenda désactivé), ou réservé au propriétaire connecté si l'agenda est réactivé (§11).

## 5. Données

Les fonctions du modèle utilisent soit `escapeSQL()` = `Database::escape()` (ajoute les quotes, `Database.php:302-325`), soit `escape()`/`normalize()` = `htmlspecialchars(ENT_QUOTES)` (`model.php:11-19`) : les textes des messages sont donc **stockés échappés HTML** en base.

### Tables (snapshot local)

| Table | Moteur | Colonnes | Lignes |
|---|---|---|---|
| `mp` | MyISAM, latin1 | `id` PK, `idemetteur` int, `idreceveur` int, `sujet` varchar(250), `message` text, `date` datetime, `lu` int défaut 0, `cle` varchar(250), `parent` int | 10 596 |
| `emailing` | MyISAM, latin1 | `id` PK, `type` varchar(100), `destinataire` longtext, `sujet` text, `message` longtext, `date` datetime | 2 |
| `news` | InnoDB, latin1 | `id` PK, `string1..4` varchar(250) NOT NULL, `thumb` varchar(250), `date` int (timestamp), `url` varchar(250) NOT NULL | 3 |
| `agenda` | MyISAM, latin1 | `agenda_id` PK, `user_id` int, `agenda_titre` varchar(250), `agenda_description` text, `agenda_date` varchar(8) `YYYYMMDD`, `agenda_heure` varchar(10) `HHhMM` | 0 |
| `docs` | InnoDB, utf8mb3 | `iddocs` PK, `email` text, `url` text, `time` varchar(20) (timestamp) | 2 135 |

Lues : `user` (`id,name,email,statut`), `privileges` (`iduser,acces`), `acces` (`iduser,idprojet`), `projet`, `clients` (`nom,email`, `*`).

### Statistiques utiles
- `mp` : 8 849 non lus / 1 747 lus ; 10 423 adressés à l'id 1 (admin) ; 8 885 ont un sujet « système » (liste ci-dessous) ; 5 réponses (`parent>0`) ; 1 383 dont l'émetteur n'existe plus dans `user` (invisibles à cause des `INNER JOIN user`) ; 49 valeurs de `cle` dupliquées (max 6 lignes par clé) ; 15 messages contenant du HTML échappé (`&lt;`).
- `emailing` : 2 lignes, `type = 'prescripteur'`, 2013-02-14 et 2015-12-03.
- `news.thumb` ∈ {`19`,`22`,`23`} (ids de programmes), `url` toujours non vide.
- `docs.time` : de 1432296243 à 1652039483.

### Requêtes (fichier:ligne)
- `Get_Message` : `SELECT mp.sujet,mp.cle,mp.lu,DATE_FORMAT(mp.date,'%d.%m.%Y %H:%i') AS date,user.name FROM mp INNER JOIN user ON mp.idemetteur=user.id WHERE mp.idreceveur=$id OR mp.idemetteur=$id ORDER BY mp.id DESC` (`model.php:1962-1967`).
- `Get_Message_Admin` : idem + `WHERE (...)` et exclusion de 12 sujets système (`model.php:1975-1992`).
- `Get_NumberOfMessage` : nombre de `mp` `lu=0 AND idreceveur=$id` (`model.php:1922-1928`) ; `Get_NumberOfMessageAdmin` : `COUNT(mp.sujet) AS nb` avec `INNER JOIN user`, `lu=0`, mêmes 12 exclusions (`model.php:1930-1956`). Badge `label label-info` si > 0 (`template.php:111`).
- Sujets système exclus (valeurs magiques écrites par le module `workflow`) : « Votre demande de réservation a été validée », « Vous avez une demande de réservation en attente », « Votre réservation a été annulée », « Votre option a été annulée », « Vous avez une dénonciation en attente », « Votre demande de dénonciation a été validée », « Votre demande de dénonciation a été refusée », « Votre demande d'option a été validée », « Votre demande d'option a été refusée », « Vous avez une demande d'option en attente », « Votre option a été passée en réservation », « Votre dossier a été acté » (`model.php:1980-1991`).
- `Get_ReadingMessage`, `GetChildMessage`, `GetParentMessage` (`model.php:1996-2034`) : voir §3.2.
- `Envoyer_Message` (`model.php:2036-2046`) et `Envoyer_Message_Perso` (`model.php:2048-2063`) : insertion `mp` avec `date = date('Y-m-d H:i:s')`, `lu = 0`, `cle = sha1(HASH . time() . idemetteur)` (constante `HASH` de `config.php`), `parent` (0 par défaut). `Envoyer_Message_Perso` est aussi appelée par d'autres modules : `optionner.php:33`, `reserver.php:33`, `json.php:925,994,1271` (vers l'id 1).
- `Get_MessageRead`, `Del_Message`, `Del_AllMessage` (`model.php:2065-2095`).
- `getPrescripteurs` : `user INNER JOIN privileges WHERE acces=0 ORDER BY name` (`model.php:1568-1576`) → 241 lignes dont 98 `statut=1`.
- `GetPrescripteursByProject` : liste des `iduser` de `acces` pour le projet, puis `user ... WHERE acces=0 AND user.id IN (...)` (`model.php:1578-1597`) ; liste vide → `IN ()` → erreur SQL.
- `GetEmailByAccess` : `SELECT acces.iduser,user.email FROM acces INNER JOIN user ... WHERE acces.idprojet=$program` (`model.php:405-416`), sans filtre de rôle ni de statut ; max 67 lignes par programme.
- `GetClients` : `SELECT nom,email FROM clients ORDER BY nom` (`model.php:770-774`) → 212 lignes, 188 avec email, 185 distincts.
- `StocKEmailBDD` : `INSERT INTO emailing VALUES (NULL,$type,$destinataire,$sujet,$message,$date)` (`model.php:418-430`) — `$date` **non quotée**. Valeurs de `type` : `prescripteur`, `programme`, `client`, `autre`. `destinataire` : `all`, un email, un `projet_id`, ou la liste `;`.
- `getEmailsCorimPromotion` (`model.php:85-89`), `Get_Project_Name_By_Key` (`model.php:1885-1893`). `docs` est alimentée par `ExternalSendEmail` (`model.php:136-145`) depuis `firstsearch.php:20-32` (module `public-api`).
- `news` : `AddFlashInfoById` `INSERT INTO news VALUES (NULL,s1,s2,s3,s4,thumb,time(),url)` (`model.php:42`), `UpdateFlashInfoById` (`model.php:59`), `getInfoById` (`model.php:67`), `getAllInfos` (`model.php:73`), `DeleteFlashInfoById` (`model.php:28`). `news` est aussi lue par `search.php:19-30` (`allnews`, module `public-api`).
- `agenda` : `insertEvent` (`model.php:1478`), `getEvents` (`model.php:1487`), `GetActivities` (`model.php:1646`), `DeleteEvent` (`model.php:1464`).

### Règles implicites
- Admin destinataire des contacts = `user.id = 1` codé en dur (`model.php:2044`).
- Les messages internes ne déclenchent aucun email, sauf `/contact` (§6.1).
- Les dates `mp` sont des `datetime` ; `news.date` et `docs.time` des timestamps Unix ; `agenda_date` une chaîne `YYYYMMDD`.
- Éditer un flash info remet sa date à maintenant, ce qui change l'ordre de la liste (`model.php:59`).

## 6. Emails

Aucun email de ce module n'utilise le SMTP configuré (`config.php:142-146`, `EMAIL_SMTP`/`EMAIL_USER`/`EMAIL_PASS`) : tous passent par la fonction PHP `mail()` (sendmail local du VPS), directement ou via `Swift_MailTransport`. `EMAIL` = `contact@corim-promotion.com` (`config.php:143`). `BASE` = `https://corim-vente.com/` (`config.php:127`).

### 6.1 Notification de nouveau message (contact)
- Déclencheur : POST valide sur `/contact` (`contact.php:22-51`).
- Destinataire : `EMAIL` (`contact.php:47`). Expéditeur : `EMAIL`, nom « Corim promotion » (`:46`).
- Sujet : `[Corim promotion] - Vous avez reçu un nouveau message` (`:45`).
- Corps HTML (`:36-43`) : `<table width="500">`, logo `https://corim-vente.com/medias/images/logo-corim.png`, ligne vide, « Vous avez reçu un nouveau message<br /><br />Pour y accéder, vous pouvez cliquer <a href="https://corim-vente.com/messagerie">ici</a> », ligne vide, pied « Immeuble "Le Cristal"<br />1475 av.Albert Einstein<br />34000  MONTPELLIER<br />Tél : 04 99 58 86 86<br />Fax : 04 67 60 40 77 ». Partie texte = `strip_tags` du HTML (`:49`). Le contenu du message n'est pas inclus.
- Transport : SwiftMailer `Swift_MailTransport` (= `mail()`) (`contact.php:23,34-35,51`).

### 6.2 Message admin vers client(s) (`/message/envoyer`, `method=client`)
- Déclencheur : `message.php:82-102`.
- Destinataires : `client_select = all` → chaque `clients.email` de `GetClients()` (212 lignes, dont 24 sans email) ; sinon l'adresse postée (`message.php:88-95`).
- Expéditeur : `From: "Corim Promotion"<contact@corim-promotion.com>`, `Reply-To: contact@corim-promotion.com` (`message.php:84-85`).
- Sujet : `objet` saisi, brut. Corps : `message` saisi (textarea texte brut), envoyé tel quel avec `Content-Type: text/html; charset="iso-8859-1"`, `Content-Transfer-Encoding: 8bit` (`message.php:86-87`) alors que l'application est en UTF-8.
- Pas d'historique en base. Transport : `mail()` un appel par destinataire, dans la requête HTTP.

### 6.3 Emailing groupé (`/emailing`)
- Déclencheur : POST valide sur `/emailing` (`emailing.php:27-113`).
- Destinataires, liste construite selon `method` :
  - `prescripteur` + `all` : `user.email` de `getPrescripteurs()` (241 adresses, actifs **et inactifs**) (`emailing.php:45-48`) ; sinon l'email posté (`:51-52`).
  - `programme` : `user.email` de `GetEmailByAccess(projet_id)` (jusqu'à 67 ; inclut tout utilisateur ayant un accès, quel que soit son rôle ou statut) (`:64-69`).
  - `client` + `all` : `clients.email` de `GetClients()` (212 lignes, 188 non vides, doublons possibles) (`:85-88`) ; sinon l'email posté (`:91-92`).
  - `keywords` : emails choisis dans la recherche §4.1, joints par `;` (`:103-106`), sans limite ni validation.
- Expéditeur : `From: "Corim Promotion"<contact@corim-promotion.com>`, `Reply-To: contact@corim-promotion.com` ; `Content-Type: text/html; charset="utf-8"`, `Content-Transfer-Encoding: 8bit` (`emailing.php:37-40`).
- Sujet : `objet` saisi, brut, non encodé MIME.
- Corps (`emailing.php:32-36`) : `<html><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /><title>{objet}</title><body><table width="500"><tr><td align="left"><img src="https://corim-vente.com/medias/images/logo-corim.png" /></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td>{message CKEditor}</td></tr><tr><td align="left">&nbsp;</td></tr><tr><td align="left">Immeuble "Le Cristal"<br />1475 av.Albert Einstein<br />34000  MONTPELLIER<br />Tél : 04 99 58 86 86<br />Fax : 04 67 60 40 77</td></tr></table></body></html>`.
- Historique : une ligne `emailing` par envoi (pas par destinataire) via `StocKEmailBDD`, **après** les envois. La requête est invalide (date non quotée, `model.php:426`) : les emails partent puis la page tombe en erreur SQL et rien n'est historisé (les 2 seules lignes datent de 2013 et 2015).
- Transport : `mail()`, un appel synchrone par destinataire, sans pause, sans gestion d'échec (valeur de retour ignorée), sans lien de désinscription.
- Volume et risque : aujourd'hui jusqu'à ~241 envois par clic (prescripteurs « tous »), 212 (clients « tous »), 67 (programme). Tous partent du sendmail du VPS OVH avec un `From` en `@corim-promotion.com` : risque de blocage/listing (SPF/DKIM non garantis depuis ce serveur, OVH limite les envois de masse), de dépassement de `max_execution_time` (envoi partiel non tracé), et de renvoi en double par l'utilisateur à cause de l'erreur SQL finale. Nouvelle app : un job Laravel en file d'attente (`ShouldQueue`) par destinataire ou par lot, avec limitation de débit (`RateLimited`), réessais, historique `emailing` écrit **avant** la mise en file et statut par destinataire, envoi par le service SMTP dédié (SPF/DKIM/DMARC). Lien de désinscription pour les clients : voir §11.

### 6.4 Demande d'infos (`/formulaire`, actuellement cassé)
- Déclencheur : POST valide (`formulaire.php:18-55`), jamais atteint car la vue manque (§3.17).
- Destinataires : 3 adresses `@corim-promotion.com` codées en dur, séparées par des virgules, dont une nominative (`formulaire.php:28`). À mettre en configuration.
- Expéditeur : `From: "Corim Promotion"<contact@corim-promotion.com>`, `Reply-To: {email saisi}` non validé (`:45-46`). `Content-Type: text/html; charset="iso-8859-1"`.
- Sujet : `demande infos : site CORIM` (`:50`).
- Corps (`:33-43`) : `{nom} {prenom} ({email}) <br /> - - - - - - - - - - - - - - - - - <br /> vous envoie ce message <br /> - - - - - - - - - - - - - - - - - <br /> {commentaire} <br /> - - - - - - - - - - - - - - - - - <br /> adresse : {adresse} <br /> code : {cp} <br /> ville : {ville} <br /> tel : {telephone} <br /> fax : {fax} <br /> ` (valeurs brutes, non échappées).
- Transport : `mail()`.

### Hors module (pour mémoire)
Notifications du workflow et de `gestion_promoteurs` via Swift SMTP (`json.php:129-131` et suivants, `gestion_promoteurs.php:99`) : contrats `workflow` et `promoteurs`.

## 7. Fichiers

- Vignettes flash info : `OLD/medias/news/{thumb}.jpg` où `thumb` = `projet_id` ou `0` (« AUTRE ») (`V_Flash_Info.php:24`, `V_Edit_Flash_Info.php:46`). Fichiers présents : `0.jpg` à `23.jpg` sauf `7.jpg`. **Aucun code ne crée ces fichiers** (pas d'upload, malgré `enctype="multipart/form-data"`). À migrer tels quels.
- Logo des emails : `medias/images/logo-corim.png` en URL absolue `https://corim-vente.com/...` (doit rester accessible publiquement).
- Page Emails : affiche seulement des URL de plans (`medias/programmes/{key}/plans/...`), pas de téléchargement.
- Aucun upload dans ce module.

## 8. Design

- Gabarit : `OLD/application/views/template/template.php` (Bootstrap 3.3.6 CDN, jQuery 1.11.3, jQuery UI 1.11.3, bootstrap-select 1.13.14, police Questrial), menu `template.php:77-121` ; accueil `template/template_welcome.php`.
- CSS spécifiques chargées selon le 1er segment d'URL (`template.php:42-53`) : `OLD/medias/css/messagerie.css`, `OLD/medias/css/contact.css`, `OLD/medias/css/agenda.css` ; commun `OLD/medias/css/global.css`.
- JS : `OLD/medias/js/global.js` (emailing `51-54`, `143-215` ; agenda `1472-1488` ; suppression messagerie orpheline `1539-1567`, `1790-1807`).
- Vues : `V_messagerie.php`, `V_message.php`, `V_emailing.php`, `V_Emails.php`, `V_Flash_Info.php`, `V_Add_Flash_Info.php`, `V_Edit_Flash_Info.php`, `V_Agenda.php`, `V_Contact.php` (toutes dans `OLD/application/views/`). Classes à conserver : `news`, `news_read`, `news-content`, `news-content-date`, `news-push`, `last_message`, `first_message`, `item_message`, `oldmsg`, `success`, `error`, `alert alert-*`, `tablesorter-green`.
- Plugins :
  - CKEditor 4.5.9 « full-all » depuis `//cdn.ckeditor.com` (`template.php:25`) pour emailing et flash infos (`V_emailing.php:124`, `V_Add_Flash_Info.php:65-66`, `V_Edit_Flash_Info.php:72-73`). `V_emailing.php:2` charge aussi `/ckeditor/ckeditor.js` (dossier absent → 404). CKEditor 4 est en fin de vie : voir §11.
  - tablesorter (thème green) pour `V_Emails` et `V_Flash_Info` (`template.php:27-28`) → petit tri JS natif, même rendu.
  - colorbox pour la sélection de clients (`global.js:186-208`) → `<dialog>`.
  - `eval()` des réponses JSON (`global.js:157`, `1480`) → `fetch().json()`.

## 9. Bugs et failles constatés

| Problème | Fichier:ligne | Décision proposée (reproduire / corriger) |
|---|---|---|
| `SearchClientEmailing` sans authentification : n'importe qui sur Internet obtient toutes les colonnes de `clients` (nom, adresse, tél, email, budget, observation) | `json.php:78-95`, `model.php:652-656` | Corriger : admin uniquement, retourner seulement les champs affichés |
| JSONP `callback` non filtré, réponse en `text/html` → XSS réfléchie sur le domaine | `json.php:1600-1601` | Corriger : JSON pur `application/json`, pas de callback (seul appelant interne) |
| `DeleteEvent` sans authentification ni contrôle de propriétaire | `json.php:1301-1314`, `model.php:1464` | Corriger : supprimer la route (agenda désactivé) |
| Contrôles admin sans `exit` : un prescripteur peut ajouter, modifier, supprimer des flash infos et lire la page Emails (2 135 emails de prospects dans le corps de la 302) | `flash_info.php:11-13`, `emails.php:11-13` | Corriger : 302/403 sans exécution (middleware) |
| `message/envoyer` sans `exit` : un prescripteur peut POSTer `method=client` + `client_select=<adresse quelconque>` et faire envoyer un HTML arbitraire depuis `contact@corim-promotion.com` | `message.php:47-48`, `:94-95` | Corriger : admin uniquement |
| Agenda : redirection sans `exit`, `event` en POST insère quand même | `agenda.php:15-34` | Corriger : pas d'écriture |
| Injection SQL dans `Del_Message` : le tableau de clés n'est pas échappé (`escape()` ignore les tableaux) | `messagerie.php:54-56`, `model.php:2078-2081` | Corriger : requête paramétrée |
| Lecture/réponse à n'importe quel message par sa `cle` (pas de contrôle émetteur/destinataire) | `model.php:2005`, `message.php:16` | Corriger : accès limité à l'émetteur, au destinataire |
| `repondre` : destinataire pris dans un champ caché modifiable | `V_message.php:135`, `message.php:25` | Corriger : destinataire = émetteur du message d'origine côté serveur |
| `cle` = `sha1(HASH.time().idemetteur)` : doublons lors des envois groupés (49 clés dupliquées) | `model.php:2057` | Corriger pour les nouveaux messages (jeton aléatoire unique) ; garder les anciennes clés pour les liens |
| Flash infos : HTML stocké sans filtrage et réaffiché brut (admin et API publique `allnews`), `url` brute dans `href` (`javascript:` possible), valeurs non échappées dans les attributs du formulaire d'édition | `model.php:35-42`, `V_Flash_Info.php:27-33`, `V_Edit_Flash_Info.php:18-38` | Corriger : HTML assaini (liste blanche), URL `http(s)` uniquement, échappement Blade. Garder l'API `allnews` compatible |
| Flash infos : champ vide → `NULL` dans une colonne `NOT NULL` → erreur SQL à l'ajout | `flash_info.php:39-45`, `model.php:42` | Corriger : enregistrer `''` |
| `StocKEmailBDD` : date non quotée → erreur SQL après l'envoi, historique jamais écrit | `model.php:425-426` | Corriger : historique écrit avant l'envoi |
| `GetPrescripteursByProject` : `IN ()` quand le programme n'a aucun accès → erreur SQL | `model.php:1585-1593` | Corriger : afficher « Veuillez choisir un programme » ou aucun envoi, sans erreur (texte à valider) |
| Emailing/messages vers prescripteurs « tous » inclut les comptes inactifs (143) ; `GetEmailByAccess` sans filtre de rôle ni de statut | `model.php:1568-1576`, `model.php:405-416` | Reproduire par défaut ; question §11 |
| Envoi synchrone de centaines de `mail()` dans une requête, sans gestion d'échec | `emailing.php:45-106` | Corriger : file d'attente Laravel |
| Injection d'en-têtes mail possible via `objet` (sujet) et `Reply-To` (formulaire) | `emailing.php:47`, `message.php:91`, `formulaire.php:46` | Corriger : Laravel Mail (en-têtes encodés), validation email |
| Encodage : `iso-8859-1` déclaré pour un contenu UTF-8 ; sujets non encodés | `message.php:86`, `formulaire.php:47` | Corriger : UTF-8 partout |
| `/formulaire` en 500 (vues `formulaire` et `template/formulaire` absentes) | `__form.php:12`, `formulaire.php:10` | Question §11 |
| `supprimer_tout` en GET sans confirmation | `messagerie.php:64-72` | Corriger : POST + CSRF (pas de lien UI aujourd'hui) |
| Suppression sélective des messages inaccessible depuis la vue (cases à cocher absentes) | `V_messagerie.php:16-34`, `global.js:1539-1567` | Reproduire (route POST conservée, sans UI) ; question §11 |
| Messages de contact stockés échappés : l'admin voit `<b>Programme concerné :</b>` en texte littéral | `contact.php:26`, `model.php:2038-2045`, `V_messagerie.php:42` | Reproduire l'affichage pour les messages existants ; question §11 pour les nouveaux |
| Messages dont l'émetteur a été supprimé invisibles (1 383) | `model.php:1963-1965` | Reproduire |
| Fil de conversation sur un seul niveau (réponse à une réponse = nouveau fil) | `message.php:28`, `model.php:2018` | Reproduire |
| `supprimer` réaffiche la liste non filtrée même pour l'admin | `messagerie.php:58` | Corriger : même liste que l'index |
| Pas de CSRF sur aucun formulaire ; suppressions flash info en GET | toutes les vues du module, `flash_info.php:16` | Corriger : CSRF ; suppression en POST avec `<dialog>` de confirmation (même lien visuel) |
| XSS réfléchie dans les formulaires réaffichés (`callback` echo brut) | `V_message.php:22,93`, `V_emailing.php:22,103,118` | Corriger : échappement Blade |
| XSS DOM dans la colorbox de sélection (données clients concaténées en HTML) | `global.js:164-181` | Corriger : construction DOM par `textContent` |
| Re-sélection après erreur jamais correcte (comparaison id vs email, `$user` au lieu de `$client`) | `V_emailing.php:39,57`, `V_message.php:61` | Corriger (sans impact visuel au premier affichage) |
| JS lit `item.projet` (colonne `projets`) → « undefined » | `global.js:169` | Corriger |
| Recherche par mots-clés : `AND` sans parenthèses entre groupes d'`OR` | `model.php:624-654` | Question §11 |
| Page Emails : une requête par ligne (2 135) ; index `[2]` absent si URL atypique | `V_Emails.php:20-29` | Corriger : jointure unique, valeur vide si absent |
| Titre « &Eacute;diter » affiché littéralement | `flash_info.php:108`, `template.php:9` | Corriger : « Éditer » |
| Mot de passe MySQL en clair dans le modèle | `model.php:8` | Corriger : `.env` (phase 1) |
| `/messagerie/<inconnu>` affiche un texte de debug | `messagerie.php:74-77` | Corriger : 404 |
| CKEditor chargé depuis `/ckeditor/` inexistant | `V_emailing.php:2` | Corriger : supprimer |

## 10. Critères d'acceptation

1. Non connecté : `GET /messagerie`, `/message/repondre/x`, `/message/envoyer`, `/emailing`, `/emails`, `/flash_info`, `/contact`, `/agenda` redirigent vers `/connexion`.
2. Prescripteur : `GET|POST /message/envoyer`, `/emailing`, `/emails`, `/flash_info`, `/flash_info/add`, `/flash_info/edit/{id}`, `/flash_info/delete/{id}` redirigent vers `/` sans aucune écriture en base, sans email, et sans données dans le corps.
3. `/messagerie` prescripteur liste les `mp` où il est émetteur ou destinataire, triés par `id` décroissant, avec date `dd.mm.YYYY<br />HH:ii`, nom de l'émetteur, sujet, classe `news` si non lu, `news_read` sinon.
4. `/messagerie` admin exclut les 12 sujets système ; le badge du menu compte les non-lus selon les mêmes règles (admin) ou `lu=0 AND idreceveur=me` (prescripteur), et n'apparaît pas à 0.
5. Les messages dont l'émetteur n'existe plus n'apparaissent pas dans la liste.
6. `consulter/{cle}` d'un message reçu le passe à `lu=1` pour ce destinataire uniquement ; un message envoyé reste inchangé.
7. `consulter/{cle}` d'un message dont l'utilisateur n'est ni émetteur ni destinataire (et non admin) renvoie 404/403.
8. `consulter` affiche « Conversation » avec le message racine puis ses réponses directes quand il en existe ; lien « Répondre au message » seulement si l'émetteur n'est pas l'utilisateur.
9. `repondre/{cle}` POST sans `message` affiche « Le champ message est requis <br /> » ; POST valide insère un `mp` (émetteur = moi, destinataire = émetteur d'origine même si `destinataire` est falsifié, `sujet` = sujet d'origine, `parent` = id du message répondu, `lu=0`) et affiche « Votre message à bien été envoyé ». Aucun email.
10. `POST /messagerie/supprimer` avec `delete_message=k1|k2|` supprime seulement les messages reçus par l'utilisateur ayant ces clés et affiche « 2 messages effacés avec succès » ; une clé contenant `'` ne provoque ni erreur ni suppression supplémentaire.
11. `supprimer_tout` (POST + CSRF) supprime tous les messages reçus et affiche le nombre.
12. `message/envoyer` admin : chaque combinaison `method`/sélection produit le message d'erreur exact du §3.8 ; `prescripteur=all` crée un `mp` par ligne de `getPrescripteurs()` ; `programme` crée un `mp` par prescripteur du programme ; programme sans accès → pas d'erreur 500.
13. `message/envoyer` `method=client` met en file un email par adresse client (aucun vers une adresse vide), sujet = objet, From « Corim Promotion » `contact@corim-promotion.com`, Reply-To identique, corps = message saisi, en UTF-8.
14. `/emailing` : chaque erreur du §3.9 s'affiche avec le texte exact en `alert alert-warning` ; succès « Votre message à bien été envoyé ».
15. `/emailing` met en file un email par destinataire construit selon §6.3 (vérifier les comptes pour `all` prescripteurs, `all` clients, programme, liste `;`), corps HTML identique au gabarit §6.3 (logo, message, pied d'adresse), et écrit une ligne `emailing` (`type`, `destinataire`, `sujet`, `message` brut, `date`) avant la mise en file.
16. Les jobs d'emailing respectent la limite de débit configurée et réessaient en cas d'échec SMTP ; un échec n'empêche pas les autres envois.
17. `POST json/SearchClientEmailing` non connecté ou prescripteur → 401/403 ; admin → `{"call":[...],"errors":1}` ou `{"errors":0}`, `application/json`, aucun paramètre `callback` pris en compte.
18. La recherche par mots-clés cherche dans `date, source, projets, nom, adresse, tel, email, type_achat, type_logement, budget` avec la logique retenue au §11 ; une chaîne contenant `'` ou `%` ne provoque pas d'erreur.
19. La sélection dans la modale remplit `keyword_select` (emails séparés par `;`) et affiche « N personne(s) dans la liste » ; « Vider la liste » remet à zéro.
20. `/emails` admin : une ligne par `docs` triée par `time` décroissant, date `d/m/Y`, titre du programme retrouvé par clé (sinon la clé), nom du plan ; tri des colonnes côté client conservé.
21. `/flash_info` liste les `news` par `date` décroissante avec vignette `medias/news/{thumb}.jpg`, titre lié si `url` non vide, boutons Editer/Supprimer.
22. `flash_info/add` avec des champs vides (sauf vignette) enregistre une ligne avec `''` et affiche « Le flash info a été ajouté » ; `date` = timestamp courant.
23. `flash_info/edit/{id}` met à jour la ligne (et `date`), affiche « Le flash info a été mis à jour » ; id inexistant → redirection `/`.
24. `flash_info/delete/{id}` (POST + CSRF après confirmation) supprime puis redirige vers `/flash_info` ; id inexistant → redirection `/` sans suppression.
25. Un `string3` contenant `<script>` ou un `url` `javascript:` est neutralisé à l'affichage, et la réponse `search/news?action=allnews` garde sa structure.
26. `/contact` : liste des programmes accessibles à l'utilisateur ; POST sans objet → « Le champ objet est requis <br /> » ; POST valide insère un `mp` vers l'id admin (`1`), corps préfixé `<b>Programme concerné :</b> {titre}<br /><br />` si un programme est choisi, envoie l'email §6.1 (sujet, expéditeur, destinataire, corps exacts) et affiche « Votre message à bien été envoyé ».
27. `/agenda` et ses sous-URL redirigent vers `/` (connecté) ou `/connexion`, sans écriture dans `agenda` ; `json/DeleteEvent` n'existe plus (404).
28. `/formulaire` : comportement selon la décision §11 (404, ou formulaire fonctionnel envoyant l'email §6.4 aux adresses configurées, Reply-To validé).
29. Tous les formulaires du module rejettent un POST sans jeton CSRF (419).
30. En dev, tous les emails partent sur le mailer `log` (aucun SMTP réel).

## 11. Questions ouvertes

1. **Agenda** : supprimer définitivement (table vide, actions désactivées) ou conserver les routes en redirection ? Faut-il migrer la table `agenda` ?
2. **`/formulaire`** : la page est en 500 en prod (vues absentes). Un site externe pointe-t-il encore dessus (logs d'accès prod) ? Supprimer, ou la refaire (quel design, quelles adresses destinataires) ?
3. **Prescripteurs inactifs** : l'emailing « tous les prescripteurs » et l'envoi de messages doivent-ils continuer d'inclure les 143 comptes `statut=0`, et `GetEmailByAccess` tout utilisateur ayant un accès (même inactif) ?
4. **Emailing clients** : base légale RGPD/LCEN pour l'envoi commercial aux clients/prospects, et ajout d'un lien de désinscription (modifie le gabarit) ?
5. **Débit d'envoi** : quel fournisseur SMTP et quelles limites (emails/minute, /jour) pour configurer la file d'attente ?
6. **Recherche par mots-clés** : garder la logique actuelle (groupes joints par `AND` sans parenthèses, résultat proche d'un `OR`) ou la logique voulue « groupe1 ET groupe2 ET groupe3 » ?
7. **Messages de contact** : continuer d'afficher `<b>Programme concerné :</b>` en texte littéral pour les nouveaux messages (comportement actuel) ou le rendre en gras ? Les messages existants restent stockés échappés.
8. **Suppression de messages** : réintroduire les cases à cocher (JS présent, vue absente) ou laisser la suppression sans interface comme aujourd'hui ?
9. **Vignettes flash info** : comment `medias/news/{id}.jpg` sont-elles créées aujourd'hui (FTP manuel ?) ; faut-il un upload (nouvelle fonctionnalité) ?
10. **CKEditor 4.5.9** (fin de vie, failles connues) : passer à la dernière 4.x LTS, à CKEditor 5 ou à un autre éditeur, en gardant l'apparence ?
11. **Notification de contact** : `EMAIL` ou `TO` (`config.php:143,148`, même valeur aujourd'hui) comme destinataire dans la configuration Laravel ?
12. **Admin destinataire** : l'id `1` codé en dur pour les contacts et notifications doit-il devenir « tous les admins » ou rester l'utilisateur 1 ?
