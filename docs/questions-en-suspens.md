# Questions en suspens (mise à jour du 12 novembre 2026)

## À poser à la cliente

1. **Fonctions inutilisées (lot 5).** Lesquelles retirer ? Les données restent archivées en base.
   - Fiches clients (`/gestion_clients`, import et export CSV) : dernière utilisation en août 2012, 212 fiches.
   - Emailing aux prescripteurs (`/emailing`) : dernière utilisation en décembre 2015, 2 envois. Le retirer supprime aussi le besoin d'un worker de file d'attente en prod.
   - Flash info (`/flash_info`) : 3 actualités, la dernière en 2021.
   - Agenda (`/agenda`) : jamais utilisé.
   - Question secrète du mot de passe oublié : jamais renseignée. Le lien de réinitialisation par mail la remplace.
   - Liste des demandes de documentation (`/emails`) : dernière demande en mai 2022.
   - Bouton « nouveau message » de la messagerie : plus aucun message écrit à la main depuis 2021. La messagerie elle-même reste, elle porte les notifications automatiques.
2. **Doublons et données orphelines à la migration.**
   - 13 dénonciations en double (dues au double clic dans l'ancienne version) : les supprimer ?
   - 34 options et 16 réservations actives rattachées à des lots supprimés, invisibles aujourd'hui dans les listes : les supprimer ?
3. **Import Excel (lot 2).** Valider le modèle de colonnes et la référence du lot comme clé de mise à jour. Si le prix d'un lot réservé change dans le fichier, on le met à jour sans toucher au statut : est-ce bien ce qu'elle veut ?
4. **CKEditor 4.5.9** (éditeur de l'emailing et du flash info). Il affiche le bandeau rouge « version non sécurisée ». Faut-il le mettre à jour ? La question tombe si ces pages sont retirées (question 1).
5. **« Suivi perf tel ».** On l'a compris comme la rapidité du site sur téléphone. À confirmer, sinon le lot est à rechiffrer.
6. **API prescripteur.** Quels prescripteurs brancher en premier, pour leur générer une clé ?
7. **Recette.** Une date pour la recette sur l'URL de préproduction.

## À décider de notre côté

1. **Hébergement en PHP 8.5.** Le VPS actuel est en Debian 11 / PHP 5.6. Il faut soit un nouveau VPS, soit une mise à jour de l'OS de l'actuel. OLD doit rester en ligne jusqu'à la bascule.
2. **URL de préproduction**, en HTTPS et en `noindex`.
3. **SMTP dédié** pour les mails (SPF, DKIM, DMARC) : quel fournisseur ? La limite d'envoi de l'emailing (`MAIL_EMAILING_PER_MINUTE`) se règle selon ce fournisseur.
4. **Plans à trier à la main.** `plans:normalize` doit renommer 3 plans, et 3 fichiers sont en doublon d'un plan existant (`plan.A01.pdf`, `plan-A204 .pdf`, `plan-A003..pdf`).
5. **Mode sombre.** Évolution hors refonte, à chiffrer et à proposer à la cliente si on la fait.
6. **RGPD (export et purge des données d'un client ou prospect).** Absent dans l'ancienne et la nouvelle version. À reprendre plus tard, probablement comme évolution chiffrée.
7. **Ancienne version en prod.** Le badge « Demandes d'options » peut encore compter une demande invisible (cas de Mme GUENIN du 11 novembre). Faut-il un petit correctif sur OLD d'ici la bascule ?

## Nécessite un accès à la prod (accord à donner à chaque fois)

1. **Lecture des journaux d'accès Apache.** Pour savoir si `/report`, `/contact`, `/emails` et `search?action=allnews` servent encore, et finaliser la question 1 à la cliente.
2. **Répétition de la migration** sur un dump frais de la base et des fichiers. Ensuite : `programmes:clean-descriptions --apply`, `plans:normalize --apply`, `passwords:wrap-legacy --apply`, puis comparaison des données.

## À faire en préproduction

- Mesures de rapidité sur téléphone (Lighthouse, 4G simulée), avant et après.
- Compression gzip sur le vhost.
- `.env` de prod : `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `APP_URL=https://corim-vente.com`, proxies de confiance si besoin.
- Worker de file d'attente, si l'emailing est gardé.
- Rendu des mails dans Outlook et Gmail.
