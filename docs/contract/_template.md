# Contrat — <module>

Statut : brouillon (à valider par Victor)
Sources lues : <liste des fichiers OLD/ avec lignes>

## 1. Périmètre

Ce que fait le module, en une dizaine de lignes, du point de vue de l'utilisateur.

## 2. Rôles et droits

| Rôle (valeur en base) | Peut faire | Contrôle dans l'ancien code (fichier:ligne) |
|---|---|---|

## 3. Écrans et URL

Pour chaque URL : méthode, paramètres GET/POST (nom, type, obligatoire), rôle requis, contrôleur:ligne, vue utilisée, comportement, redirections, messages affichés (texte exact).

## 4. Actions AJAX / JSON

Pour chaque action : URL, paramètres, réponse exacte (structure JSON, codes), effets en base.

## 5. Données

Tables et colonnes lues / écrites, avec les requêtes SQL d'origine (fichier:ligne). Règles métier implicites (statuts, valeurs magiques, dates en timestamp, etc.).

## 6. Emails

Pour chaque email : déclencheur, destinataires, expéditeur, sujet exact, corps (HTML d'origine résumé ou cité), transport utilisé (mail() / Swift SMTP).

## 7. Fichiers

Uploads / téléchargements : chemins, extensions, nommage, droits.

## 8. Design

Vues, gabarit, CSS, JS et plugins utilisés par le module (chemins exacts dans OLD/). Plugins obsolètes et remplacement natif prévu.

## 9. Bugs et failles constatés

| Problème | Fichier:ligne | Décision proposée (reproduire / corriger) |
|---|---|---|

## 10. Critères d'acceptation

Liste numérotée de vérifications testables (une par comportement), qui serviront à écrire les tests Pest.

## 11. Questions ouvertes

Ce qui ne peut pas être tranché à partir du code.
