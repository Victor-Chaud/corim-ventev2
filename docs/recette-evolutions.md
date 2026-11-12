# Recette manuelle des évolutions de novembre 2026

Application locale : http://127.0.0.1:8000 (base de dev `corim_new`, les mails partent dans `NEW/storage/logs/laravel.log`, rien n'est envoyé).

Comptes de test (locaux uniquement, mots de passe dans `/tmp/claude-1000/-home-etiseo-www-corim-vente/c5404bc8-2ce2-4241-975f-cd2bdcde797e/scratchpad/comptes-test.txt`) :
- admin : identifiant `test-admin`
- prescripteur : identifiant `test-presc`, rattaché au programme COCOON et à tous ses lots

Programme de test : COCOON, clé `5714ed1c02fb20b7d636a8786e5ed21c`.

Pour revenir à la base de départ après la recette : `cd NEW && php8.5 artisan legacy:import` (les comptes de test disparaissent).

Cocher chaque case et noter tout écart à côté.

---

## Lot 1. Double clic sur les boutons du workflow

Note client : « Double click valider dénonciation qui envoie 2 fois l'info ».

1. [ ] Connecté en `test-presc`, ouvrir COCOON et faire une demande de dénonciation (un prospect au nom de TEST DOUBLE).
2. [ ] Se déconnecter, se connecter en `test-admin`, aller dans « Demandes de dénonciations ».
3. [ ] Cliquer **deux fois très vite** sur VALIDER, puis sur le bouton de confirmation de la fenêtre.
   - Attendu : le bouton se grise dès le premier clic, un seul message de succès.
4. [ ] Aller dans « Dénonciations » : TEST DOUBLE apparaît **une seule fois**.
5. [ ] Dans `storage/logs/laravel.log`, un seul mail de validation pour TEST DOUBLE.
6. [ ] En `test-presc`, sur la fiche COCOON, remplir une dénonciation et cliquer **deux fois très vite** sur Valider : une seule demande dans « Demandes de dénonciations », un seul mail dans le log. Le bouton redevient cliquable après la réponse pour saisir un autre prospect.
7. [ ] Refaire le même double clic sur : refuser une dénonciation, valider et refuser une demande d'option, valider et refuser une demande de réservation, annuler une option, annuler une réservation. Attendu partout : un seul traitement.

## Lot 2. Import en masse depuis Excel (grille de prix)

Note client : « Grille de prix qui s'incrémente, ajouter en masse et plans en Excel selon les colonnes. A fix pour incrémenter CSV sur le 1er index du tableau de données ».

En `test-admin`, menu Programmes, COCOON, « Ajouter lots en masse » (`/ajouter_lots/show/5714ed1c02fb20b7d636a8786e5ed21c`).

1. [ ] Télécharger le **modèle** depuis la page (`/ajouter_lots/modele`). Il s'ouvre dans Excel avec une ligne d'en-têtes.
2. [ ] Remplir 2 lignes : une **nouvelle référence** (ex. `Z01`) et une **référence existante** de COCOON avec un **prix modifié**. Enregistrer en « CSV (séparateur : point-virgule) ».
3. [ ] Envoyer le fichier. L'aperçu indique « ajout » pour Z01 et « mise à jour » pour la référence existante. La ligne d'en-têtes n'apparaît pas comme un lot.
4. [ ] Valider l'import. Le message donne 1 ajouté et 1 mis à jour.
5. [ ] Dans « Gérer les lots » : Z01 existe, le prix de la référence existante a changé, **aucun doublon**, les autres lots sont intacts.
6. [ ] Refaire le test en **changeant l'ordre des colonnes** dans Excel : même résultat.
7. [ ] Mettre à jour le prix d'un lot **en option ou réservé** : le prix change, le statut reste.
8. [ ] Laisser vide une cellule obligatoire : l'aperçu la signale.
9. [ ] Mettre `=1+1` dans une cellule texte : la valeur est importée telle quelle, sans être calculée.

## Lot 3. Nom des plans et rattachement aux lots

Note client : « Nom des plans comme le nom du fichier sur serveur. Pour le faire remonter ».

En `test-admin`, COCOON, « Ajouter les plans » (`/ajouter_plan/show/5714ed1c02fb20b7d636a8786e5ed21c`).

1. [ ] Envoyer un PDF nommé `Z01.pdf` (le lot créé au lot 2) : accepté.
2. [ ] Envoyer le même plan nommé `Plan Z01.pdf`, puis `plan.Z01.pdf`, puis `PLAN-z01.pdf` : tous acceptés, ils remplacent le plan de Z01.
3. [ ] Envoyer `A99.pdf` : refusé avec le message « aucun lot A99 dans ce programme ».
4. [ ] Sur la fiche programme, le lot Z01 affiche son plan et le lien ouvre le PDF (`/medias/programmes/5714ed1c02fb20b7d636a8786e5ed21c/plans/plan-Z01.pdf`).
5. [ ] L'API publique remonte le plan : http://127.0.0.1:8000/index.php/firstsearch?action=nextjscorim&residence=COCOON contient Z01, et son plan s'ouvre au chemin ci-dessus.

## Lot 4. API prescripteur en lecture seule

Notes client : « Api en lecture simple pas plus pas moins », « Actualisation toutes les heures », « Le prescripteur doit avoir que ses dénonciations et lots qui le concernent lui », « Connexion au flux pour avoir l'actualisation en direct ».

1. [ ] En `test-admin`, « Prescripteurs », fiche de TEST PRESCRIPTEUR : générer la clé. Les liens HTML, CSV et JSON s'affichent **une seule fois**. Les copier.
2. [ ] Ouvrir le lien HTML dans une **fenêtre privée** (sans être connecté) : tableau des lots COCOON puis des dénonciations de TEST PRESCRIPTEUR, sans menu ni bouton.
3. [ ] Ouvrir le lien `&format=csv` : s'ouvre dans Excel.
4. [ ] Ouvrir le lien `&format=json` : données JSON.
5. [ ] Vérifier le cloisonnement : aucun lot d'un autre programme, aucune dénonciation d'un autre prescripteur.
6. [ ] Dans « Privilèges », retirer à TEST PRESCRIPTEUR l'accès à un lot de COCOON, puis recharger le lien : le lot a disparu **tout de suite**.
7. [ ] Changer le statut d'un lot (poser une option, valider une réservation) ou son prix, puis recharger le lien : **le changement apparaît tout de suite**. Idem pour une nouvelle dénonciation.
8. [ ] Générer une nouvelle clé : l'ancien lien répond « non autorisé ».
9. [ ] Ouvrir `/api/v1/lots` sans clé : refusé.
10. [ ] La documentation `/api/v1/documentation` s'affiche et est compréhensible pour un webmaster.

## Lot 6. Rapidité sur téléphone

Note client : « Suivi perf tel ».

1. [ ] En `test-admin`, ouvrir « Dénonciations » (la plus grosse page) sur ordinateur : chargement nettement plus rapide qu'en prod, le calendrier apparaît au clic dans une date.
2. [ ] Ouvrir le site sur un téléphone, ou avec l'outil « mode téléphone » du navigateur (F12) : pages lisibles, menu utilisable.

## Lot 7. Demandes du 30 octobre

### 7a. Description des programmes
1. [ ] Ouvrir la fiche COCOON : la description affiche les retours à la ligne, les liens sont cliquables, plus de `\` ni de `\n` parasites.

### 7b. Messages automatiques et signature
1. [ ] En `test-admin`, menu « Messages automatiques » : les 9 textes et la signature sont modifiables.
2. [ ] Modifier le texte « dénonciation validée » et la signature, enregistrer.
3. [ ] Valider une dénonciation : la fenêtre de validation est pré-remplie avec le nouveau texte, et le mail dans `laravel.log` porte la nouvelle signature (police, taille et retours à la ligne corrects).
4. [ ] Saisir un texte de plus de 5000 caractères : un message d'erreur s'affiche et les autres textes saisis sont conservés.

### 7c. Suivi des refus
1. [ ] Refuser une demande d'option et une demande de réservation avec un message.
2. [ ] En `test-admin` : « Options refusées » (`/options/refusees`) et « Réservations refusées » (`/reservations/refusees`) listent ces refus avec le message.
3. [ ] En `test-presc` : les mêmes pages ne montrent que ses propres refus.
4. [ ] Une dénonciation refusée affiche sa date de décision.

## Correctifs de sécurité visibles

1. [ ] « Mot de passe oublié » sur `/connexion` avec l'e-mail `test-presc@corim.test` : même message qu'avec une adresse inconnue.
2. [ ] Le mail dans `laravel.log` contient un lien. Ouvrir le lien, choisir un mot de passe (8 caractères minimum, saisi deux fois) et se connecter avec.
3. [ ] Rouvrir le même lien : refusé.

---

## Non fait volontairement

- **Lot 5, retrait des fonctions inutilisées** : reporté à ta demande, liste à valider avec la cliente.
- **Mesures Lighthouse sur téléphone** : à faire en préproduction (le local ne représente pas le réseau réel).
