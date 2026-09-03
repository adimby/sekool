# FANABE — Cycles scolaires

> **Décision `D-23` : un seul produit.** Maternelle, primaire, collège et lycée partagent la même application. Le cycle est un attribut du **niveau**, pas une interface ni un tenant.
>
> Plan de mise en place. Ne pas forker l’UI. Ne pas créer trois FANABE.

## 1. Pourquoi on ne sépare pas

À Madagascar, l’opérateur courant est un **complexe** : maternelle + primaire sous la même direction, collège + lycée, parfois tout le continuum. Une famille a souvent un enfant en GS et un autre en 4ème. Trois interfaces casseraient le foyer, la caisse et l’identité portable — le cœur de FANABE.

Un campus maternelle **juridiquement distinct** d’un lycée, c’est déjà `Q-20` : deux établissements FANABE, éventuellement un réseau plus tard. Ce n’est pas deux produits.

## 2. Source de vérité

Le cycle vit déjà sur `grade_levels.stage` (`Q-19`) :

| `stage` | Cycle | Exemples de niveaux |
|---|---|---|
| `preschool` | Maternelle | TPS, PS, MS, GS |
| `primary` | Primaire | CP–CM2, T1–T5 |
| `middle` | Collège | 6ème–3ème |
| `high` | Lycée | 2nde, 1ère, Terminale |

- Une **classe** hérite du cycle de son niveau.
- Les **cycles de l’école** = les `stage` distincts de ses niveaux (dérivés, pas une table).
- On n’ajoute **pas** de `school_type` ni de SKU « FANABE Collège ».
- Une école mixte (primaire + collège) est le cas normal, pas l’exception.

Déclarer des cycles *avant* d’avoir des niveaux (`schools.settings.cycles`) n’est utile que pour un wizard d’ouverture d’école. Ce n’est pas le premier chantier.

## 3. Ce qui ne varie jamais

Même écrans, mêmes règles, tous cycles :

- Familles, inscription (classe obligatoire), présence
- Finance : barèmes **par niveau**, caisse, relances, cockpit
- Identité, consentement, messages, indices

La classe n’est **jamais** une caisse. Les soldes restent dans Finance / Caisse.

## 4. Ce qui varie — fiche classe

Un seul composant (`ClassFilePanel`). Des sections se montrent ou se cachent selon `classroom.grade_level.stage`.

| Section | Maternelle | Primaire | Collège | Lycée |
|---|---|---|---|---|
| Titulaire | oui | oui | oui | oui |
| Effectif | oui | oui | oui | oui |
| Enseignants (matière) | rare, optionnel | optionnel | oui | oui |
| Délégué / vice | **non** | optionnel | oui | oui |
| Emploi du temps | allégé | simple | lun–sam | lun–sam |
| Conseil de classe | **non** | **non** | oui | oui |
| Activités | oui (parents) | oui | oui | oui |
| Barème / solde | jamais | jamais | jamais | jamais |

Libellé : « Groupe » en maternelle (liste + fiche), « Classe » ailleurs. L’onglet reste **Classes**.

## 5. Tranches de mise en place

Ordre d’exécution. Chaque tranche est livrable seule, testée, démontrable. On n’ouvre pas la suivante tant que la précédente n’est pas dans l’interface.

### C-1 — Le cycle arrive jusqu’à l’écran

**But.** L’UI sait dans quel cycle elle est, sans nouvelle table.

- Exposer `grade_level.stage` (et un libellé FR) dans le payload classe / fiche classe.
- `ClassFilePanel` : masquer délégué + conseil si `preschool` ; masquer conseil si `primary`.
- Liste Classes : regrouper par cycle quand l’école en a plusieurs (Maternelle / Primaire / Collège / Lycée).
- L’API **n’interdit pas** encore délégué ou conseil sur un GS : l’UI suffit. Le durcissement API vient en C-1b si une école s’en sert mal.

**Fichiers.** `ClassroomFilePayload`, types `web/src/App.tsx`, tests `ClassroomLifeTest` (une classe `preschool` sans bloc délégué côté client ; le GET reste 200).

**Démo.** Une classe maternelle (GS) sur une des écoles starter, sans casser Antsahabe collège.

### C-1b — Garde-fous API

Si la direction pose un délégué en maternelle : **422**. Primaire : délégué autorisé, conseil **422**. Collège / lycée : inchangé. Un GET d’une fiche avec d’anciennes données reste **200**.

### C-2 — Référentiel de niveaux à l’ouverture

**But.** Tenir `Q-19` : modèle fourni, école libre de le modifier.

Packs dans l’onglet Classes (cases à cocher, pas un tunnel « choisissez votre type d’école ») :

- Maternelle : PS, MS, GS
- Primaire : CP, CE1, CE2, CM1, CM2 *(variante T1–T5 en option, pas les deux imposés)*
- Collège : 6ème, 5ème, 4ème, 3ème
- Lycée : Seconde, Première, Terminale

`POST /schools/{school}/grade-levels/packs` — idempotent (les noms déjà présents sont ignorés). L’école peut encore créer / renommer un niveau un par un.

### C-3 — Libellés

- « Groupe » pour `preschool` (liste + fiche), « Classe » ailleurs
- Pas de duplication de routes ni de composants. L’onglet reste **Classes**.

### C-4 — Lycée (léger)

Champ `classrooms.series` (nullable, texte court : A, C, D, L, S, Technique, ou saisie libre). Visible sur la fiche **seulement** si `stage === high`. Pas de notes, pas de spécialités.

**Démo.** Niveau Terminale + classe `Tle S` (série S) sur Antsahabe, effectif vide.

### C-5 — Récupération maternelle

Sur la fiche **preschool** uniquement, section **Récupération** à partir des relations existantes (`pickup_authorized_for`, sinon `parent_of` / `guardian_of`). Pas de cantine ERP. Pas de nouveau rôle pédagogique pour les personnes autorisées (`S-10`).

### C-6 — Réseau de campus (modèle + métadonnées)

`Q-20` : table plateforme `school_networks` (**pas** de `school_id`, **pas** de RLS). `schools.network_id` → FK. `GET /schools/{school}/network` : `{ id, name, campuses: [{ id, name, code, city }] }` ou `null`. Noms seulement — **pas** d’effectifs ni d’inscriptions.

**Démo.** « Réseau Analakanga » relie Antsahabe et Ambohipo. Itaosy reste indépendant. La direction Antsahabe voit la ligne campus ; un GET classe Ambohipo reste **404**.

## 6. Hors périmètre de ce plan

- Trois codebases, trois navigations, trois comptes direction
- `school_type` comme produit commercial
- Notes, bulletins, School Kit, certificats : livrés en phases 5–7
- EDT établissement (remplacements, conflits multi-classes)
- Plan comptable, cantine ERP

## 7. Critère de terminé (C-1 → C-6)

1. Antsahabe (collège) : fiche 6ème A inchangée (titulaire, délégué, EDT, conseil, activités, pas de barème).
2. Une classe GS : titulaire, effectif, activités, récupération ; **pas** de délégué ni de conseil à l’écran ; libellé **Groupe**.
3. Une école mixte : liste des classes groupée par cycle ; une seule Caisse ; un seul onglet Finance.
4. Créer les niveaux d’un collège se fait par le pack C-2, puis on peut renommer.
5. Délégué en maternelle et conseil au primaire : **422**.
6. Fiche `Tle S` : champ série, pas de notes.
7. Ligne campus « Réseau Analakanga » sur Antsahabe ; Itaosy sans réseau ; isolation des classes conservée.

## 8. Invariant

`D-23` — **Le cycle ne crée pas de tenant, pas de rôle distinct.** Un professeur de GS et un professeur de 2nde passent par le même `SchoolGate`. L’affichage de la fiche classe lit `stage`. L’appel aussi : jour en maternelle / primaire, créneau en collège / lycée (les élèves restent en salle, les professeurs tournent). Ce n’est pas un produit séparé.

## 9. Appel selon le cycle (Vague H)

À Madagascar, au collège et au lycée, **les élèves restent dans la même salle**. Les professeurs se relaient. Le titulaire est un responsable désigné ; il n’appelle pas toutes les heures.

| Cycle | Qui fait l’appel | Unité |
|---|---|---|
| Maternelle / primaire | Titulaire (ou enseignant du groupe) | Jour (`full_day` / matin / après-midi) |
| Collège / lycée | Professeur du créneau, ou remplaçant du jour | Cours (`timetable_slot_id`) |

- Effectif numéroté (`enrollments.student_number`) : la colonne **N°** apparaît sur l’appel.
- L’espace professeur n’ouvre **pas** le dossier de classe (effectif, EDT, notes). Direction seulement.
- L’appel commence par **Mes cours** du jour, puis **Démarrer** : l’effectif n’est chargé que pour ce créneau (ou l’appel du jour en maternelle / primaire).
- Une classe collège / lycée **sans EDT** encore : appel du jour, pour ne pas bloquer la mise en place.
- Une absence le même jour, plusieurs cours : **un seul** avis famille (pas de SMS).
- Le titulaire ne pointe pas le cours d’un autre professeur. Un enseignant de maths ne pointe pas le Malagasy.
