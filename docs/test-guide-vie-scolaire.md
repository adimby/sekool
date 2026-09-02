# Guide de test — FANABE (Vagues A–E et socle)

Comptes démo (mot de passe `password`) :

| Rôle | Email | Espace |
|---|---|---|
| Direction | `direction.antsahabe@fanabe.test` | Aujourd’hui, Familles, Classes, Finance, Caisse, Kits, Indices |
| Titulaire | `teacher.antsahabe@fanabe.test` | Appel, Classe, Kits |
| Parent | `parent.andry@fanabe.test` | Enfants, Kits, Messages, Compte |

Ouvrir l’interface (Vite `http://127.0.0.1:5173` en local). Après une action, un **bandeau vert** confirme ; un **bandeau rouge** signale une erreur.

Personnages utiles : **Hery** (6ème A, enfant d’Andry) — garder pour l’appel. **Tojo** (6ème A) — élève à radier. **Fanja** (5ème A).

---

## 1. Connexion et espaces

1. Se connecter en **direction**.
2. **Attendu :** onglets Aujourd’hui, Familles, Classes, Finance, Caisse, Kits, Indices. Pas d’onglet Appel.
3. Se déconnecter, se connecter en **professeur**.
4. **Attendu :** onglets Appel, Classe, Kits.
5. Se déconnecter, se connecter en **parent**.
6. **Attendu :** onglets Enfants, Kits, Messages, Compte.

---

## 2. Recherche (direction)

1. Direction → **Aujourd’hui**.
2. Dans **Recherche**, taper `Hery`.
3. **Attendu :** Hery apparaît sous Personnes ; le foyer Rasoanaivo sous Foyers.
4. Cliquer **Hery**.
5. **Attendu :** onglet Familles, foyer ouvert.
6. Revenir à **Aujourd’hui**, taper `6ème`.
7. **Attendu :** la classe **6ème A** sous Classes.
8. Cliquer **6ème A**.
9. **Attendu :** onglet Classes, fiche 6ème A ouverte (titulaire, effectif, documents plus bas).

---

## 3. Appel avec motif et justificatif

1. Professeur → **Appel**, classe **6ème A**, date du jour.
2. Pour **Hery** : pastille **A** (absent).
3. **Attendu :** un menu Motif et un champ Justificatif s’affichent.
4. Motif **Maladie**, justificatif `Certificat médical vu à l’accueil`.
5. **Enregistrer**.
6. **Attendu :** bandeau « Présence enregistrée… familles sont prévenues ».
7. Pour un autre élève (pas Hery) : pastille **A**, laisser Motif vide, **Enregistrer**.
8. **Attendu :** message d’erreur demandant un motif.
9. Pastille **E** (excusé) + motif **Raison familiale** : l’enregistrement passe ; **pas** de nouveau message d’absence pour cet élève.

---

## 4. Notification d’absence (parent)

1. Parent Andry → **Messages**.
2. **Attendu :** un message « Absence de Hery » (pas de mot *score*, *risque*, *décrochage*). Le texte rappelle de contacter l’école ; **pas de SMS**.
3. Parent → **Enfants** → carte Hery → section **Présence**.
4. **Attendu :** la ligne du jour affiche `Absent · Maladie · Certificat médical vu à l’accueil`.

---

## 5. Certificat de scolarité

1. Direction → **Classes** → choisir **6ème A**.
2. Faire défiler jusqu’à la section **Documents** (ce n’est pas un onglet).
3. Sur un élève encore inscrit (Hery ou Tojo), **Scolarité**.
4. **Attendu :** bandeau « Certificat de scolarité émis. » et un lien `/verify/…`.
5. Ouvrir le lien (nouvel onglet).
6. **Attendu :** attestation FANABE, nom masqué (prénom + initiale), mention 2014-025.

---

## 6. Radiation et certificat de radiation

À faire sur **Tojo** (6ème A) pour garder Hery pour l’appel. Action irréversible dans la démo : Tojo sort de l’effectif.

1. Direction → **Classes** → **6ème A** → section **Documents**.
2. Liste **Élève à radier** : **Tojo**, motif `Déménagement`, **Radier**.
3. **Attendu :** « Élève radié. Certificat de radiation émis. » Lien de vérification. Tojo disparaît de l’effectif ; le certificat reste listé dans Documents.
4. Ouvrir le lien.
5. **Attendu :** titre **Certificat de radiation**, motif, date de fin, disclaimer FANABE (loi 2014-025).
6. Professeur → **Classe** → 6ème A → section Documents.
7. **Attendu :** pas de bouton **Radier** (lecture seule). Tojo n’est plus dans la liste Scolarité.

---

## 7. School Kit (déjà en place)

1. Direction → **Kits**.
2. **Attendu :** tableau Article / Qté / Éco / Standard / Luxe (marque + prix). Source fournisseur ou service achat.
3. Parent → **Kits**.
4. **Attendu :** une carte par enfant ; commander une gamme **ou** « Je fournis moi-même ». FANABE n’encaisse pas.

---

## 8. Notes (déjà en place)

1. Professeur → **Classe** → 6ème A → section **Notes**.
2. Saisir une note, **Enregistrer**.
3. Parent → **Enfants** → section **Notes**.
4. **Attendu :** la moyenne / matière apparaît. GS (maternelle) : pas de notes, livret à la place.

---

## 9. Devoirs et cahier journal (Vague B)

Sur **Hery** (6ème A). Le titulaire écrit ; le parent lit.

1. Professeur → **Classe** → **6ème A**.
2. Section **Devoirs** : titre `Exercices Malagasy`, date dans quelques jours, consigne `Faire les exercices 1 à 4 page 12`. **Publier le devoir**.
3. **Attendu :** bandeau « Devoir publié… » (pas de SMS). Le devoir apparaît dans la liste.
4. Section **Cahier journal** : titre `Journée calme`, date du jour, résumé `La classe a travaillé le poème`. **Publier le journal**.
5. **Attendu :** le mot apparaît. Pas de bouton Radier (lecture seule sur titulaire / EDT / radiation).
6. Se déconnecter, parent Andry → **Enfants** → carte Hery.
7. **Attendu :** section **Devoirs** avec `Exercices Malagasy` ; section **Cahier journal** avec `Journée calme`.
8. Parent → **Messages**.
9. **Attendu :** un message « Devoir pour Hery » et un « Mot de classe pour Hery ». Aucun mot *score*, *risque*, *élève en difficulté*.

---

## 10. Discipline sans score (Vague B)

1. Professeur → **Classe** → **6ème A** → section **Discipline**.
2. Élève **Hery**, constat `Bavardage répété pendant le cours`, mesure **Retenue**, **Enregistrer la mesure**.
3. **Attendu :** bandeau « Mesure enregistrée… ». Ligne Hery · Retenue. Pas de note, pas de palier.
4. Parent Andry → **Enfants** → Hery → **Discipline**.
5. **Attendu :** `Retenue` et la date. Le constat détaillé n’est pas affiché à la famille.
6. Parent → **Messages**.
7. **Attendu :** « Mesure enregistrée pour Hery », invitation à contacter l’école, pas de SMS, pas de jugement de score.

---

## 11. Événement d’école (Vague B)

1. Direction → **Classes** → **6ème A** → section **Événements**.
2. Type **Portes ouvertes**, titre `Portes ouvertes 6ème A`, destinataires **Cette classe**, lieu `Salle A1`. **Publier l’événement**.
3. **Attendu :** l’événement apparaît sur la fiche 6ème A.
4. Parent Andry (Hery en 6ème A) → **Enfants** → **Événements**.
5. **Attendu :** `Portes ouvertes 6ème A`.
6. Un parent d’une autre classe (si disponible) ne voit pas cet événement.

Le titulaire voit les événements de sa classe mais **n’a pas** le formulaire de publication (réservé à la direction).

---

## 12. Livret de compétences (Vague C)

Le groupe **GS A** existe dans la démo mais peut n’avoir aucun élève. Le catalogue (Langage, Vivre ensemble…) s’affiche quand même. Pour un élève réel, inscrire un enfant en GS ou s’appuyer sur les tests Pest `CompetencyLivretTest`.

1. Direction ou titulaire → **Classes** / **Classe** → **GS A**.
2. **Attendu :** section **Livret**, pas de section Notes. Aucun champ photo.
3. S’il y a un élève : choisir l’élève, commentaire optionnel, bouton **Acquis** sur « S’exprimer à l’oral ».
4. **Attendu :** bandeau de confirmation ; le niveau **Acquis** s’affiche. Pas une note chiffrée.
5. Parent de cet élève → **Enfants** → **Livret**.
6. **Attendu :** Langage / S’exprimer à l’oral · Acquis. Pas de photo.

---

## 13. Bulletin enrichi (Vague C)

Sur **Hery** (6ème A). Ce n’est pas un LSU.

1. Professeur → **Classe** → **6ème A** → section **Notes**.
2. Choisir Hery dans le sélecteur de notes (le relevé se charge en dessous).
3. **Attendu :** mention « Ce relevé est un document FANABE. Ce n’est pas un LSU. » et le compte d’absences sur 30 jours.
4. Appréciation `Travail régulier. Continuer ainsi.` → **Enregistrer l’appréciation**.
5. **Attendu :** le texte apparaît. Un mot interdit (`élève en difficulté`, `score`) est refusé.
6. Parent Andry → **Enfants** → Hery → **Notes**.
7. **Attendu :** moyenne / matières, absences, appréciation, disclaimer. Pas de LSU, pas de photo.

---

## 14. Early Warning notes (Vague C)

Signalement **interne** : direction uniquement, jamais envoyé à la famille.

1. Professeur → **Classe** → **6ème A** → deux notes en **Mathématiques** pour Hery : `14` puis `8` (baisse de 6 points).
2. Direction → **Aujourd’hui** → **Attention**.
3. **Attendu :** une ligne pour Hery, formulation « Évolution inhabituelle des résultats… ». Aucun mot *score*, *risque*, *élève en difficulté*.
4. **Accuser**.
5. **Attendu :** la ligne disparaît de Attention.
6. Parent Andry → **Messages**.
7. **Attendu :** aucun message d’alerte Early Warning.

Deux devoirs plus une baisse de notes produisent la catégorie travail demandé (toujours interne). Les tests Pest `StudentAlertTest` couvrent ce cas.

---

## 15. Emploi du temps établissement et remplacements (Vague D)

Sur **6ème A**. Un créneau Malagasy lundi 07:30–08:25 salle A1 existe déjà en démo.

1. Direction → **Classes** → **Établissement**.
2. **Attendu :** la grille de toutes les classes (dont 6ème A, Malagasy lundi). Pas de cantine, pas d’infirmerie.
3. Ouvrir **6ème A** → section **Emploi du temps**.
4. Enregistrer un remplacement sur le créneau du **prochain lundi** (date = un lundi), remplaçant au choix, motif `Indisponibilité du titulaire.`.
5. **Attendu :** bandeau « Remplacement enregistré… » (pas de SMS). La ligne apparaît. Un mot interdit (`score`, `risque`) est refusé.
6. Parent Andry → **Enfants** → Hery → **Emploi du temps**.
7. **Attendu :** créneaux de la semaine et le remplacement du lundi. Parent → **Messages** : « Emploi du temps de Hery ».
8. Professeur → **Classe** → 6ème A : la liste des remplacements est visible ; **pas** de formulaire d’enregistrement.

Un second cours au même horaire, même professeur ou même salle, est refusé (422) : « Ce professeur a déjà un cours à cette heure. » / « Cette salle est déjà prise à cette heure. »

---

## 16. Compositions (Vague D)

1. Direction → **Classes** → **6ème A** → section **Compositions**.
2. Titre `Composition Malagasy`, matière `Malagasy`, date dans quelques jours, 08:00–10:00, salle `A1`. **Publier la composition**.
3. **Attendu :** bandeau « Composition publiée… ». Aucun champ photo, aucun plan de salle nominatif.
4. Parent Andry → **Enfants** → Hery → **Compositions**.
5. **Attendu :** `Composition Malagasy` avec date, horaire et salle.
6. Parent → **Messages**.
7. **Attendu :** « Composition pour Hery ». Aucun mot *score*, *risque*, *élève en difficulté*.
8. Professeur → **Classe** → 6ème A : la composition est listée ; **pas** de bouton Publier.

---

## 17. Courrier papier (Vague E)

Le canal papier existe déjà (absence, devoir, composition…). La direction le voit et le coche.

1. Si besoin : professeur → **Appel** → 6ème A → Hery **A** + motif **Maladie** → **Enregistrer**.
2. Direction → **Aujourd’hui** → section **Courrier à remettre**.
3. **Attendu :** une lettre « Absence de Hery » (ou composition / devoir). Texte à remettre en main propre. **Pas de SMS.**
4. **Imprimer** ouvre la lettre. **Remis** la retire de la file.
5. **Attendu :** bandeau « Courrier marqué remis… ». La ligne disparaît.
6. Professeur → **Aujourd’hui** n’existe pas ; un GET courrier côté titulaire est refusé.

---

## 18. Export des données (Vague E)

1. Parent Andry → **Compte**.
2. Section **Mes données** → **Télécharger mes données**.
3. **Attendu :** un fichier `fanabe-mes-donnees.json` avec Hery (et Voahirana), messages, consentements. Mention « Pas un LSU ». Aucun mot *score*, *risque*, *élève en difficulté*.
4. Un parent d’une autre famille ne voit pas Hery dans son archive.

---

## 19. Ce qui ne doit pas arriver

- Aucun SMS.
- Aucun paiement en ligne.
- Le parent ne voit jamais un *score*, un *palier de risque* ou « élève en difficulté ».
- Le titulaire ne peut pas faire l’appel d’une classe dont il n’est pas titulaire.
- Une école ne voit pas les dossiers d’une autre école.
- Le titulaire ne peut pas radier un élève.
- La discipline n’est pas une note qui punit.
- Pas de chat élèves ni de fil de discussion interne.
- Le livret n’a pas de photo d’élève.
- Le bulletin n’est pas un LSU.
- L’Early Warning n’est jamais envoyé aux familles.
- Pas de cantine, pas d’infirmerie, pas de photo d’élève sur les compositions.
