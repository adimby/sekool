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

Libellé « groupe » vs « classe » : possible plus tard en maternelle. Pas un chantier bloquant. La navigation Direction ne change pas.

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

### C-1b — Garde-fous API (après C-1)

Si la direction pose un délégué en maternelle : **422** avec message clair. Primaire : délégué autorisé, conseil refusé. Collège / lycée : inchangé.

### C-2 — Référentiel de niveaux à l’ouverture

**But.** Tenir `Q-19` : modèle fourni, école libre de le modifier.

Packs proposés à la création des niveaux (cases à cocher, pas un tunnel « choisissez votre type d’école ») :

- Maternelle : PS, MS, GS
- Primaire : CP, CE1, CE2, CM1, CM2 *(variante T1–T5 en option, pas les deux imposés)*
- Collège : 6ème, 5ème, 4ème, 3ème
- Lycée : Seconde, Première, Terminale

Chaque pack crée des `grade_levels` avec le bon `stage`. L’école peut renommer, retirer, ajouter. Pas de nomenclature nationale figée.

Pas de quatrième application : juste un assistant dans Paramètres / Classes.

### C-3 — Libellés

Uniquement si C-1 a été vu par une vraie maternelle :

- « Groupe » pour `preschool` (liste + fiche), « Classe » ailleurs
- Pas de duplication de routes ni de composants

### C-4 — Lycée (plus tard)

Séries, options, enseignements de spécialité. **Après** les notes / bulletins, pas avant. Le dossier actuel (titulaire, enseignants, EDT, conseil) suffit au lycée pour l’instant.

### C-5 — Maternelle (plus tard)

Récupération d’enfant, sieste, lien quotidien parents. **Après** C-1, et seulement si un pilote maternelle le demande. Pas de cantine ERP (`CDC` hors périmètre).

### C-6 — Réseau de campus (déjà tranché)

Maternelle d’un côté, lycée de l’autre, deux `School`, `Q-20`. Vue consolidée non implémentée. Hors de ce plan.

## 6. Hors périmètre de ce plan

- Trois codebases, trois navigations, trois comptes direction
- `school_type` comme produit commercial
- Notes, bulletins, School Kit, certificats (phases 5–7 inchangées)
- EDT établissement (remplacements, conflits multi-classes)
- Plan comptable, cantine ERP

## 7. Critère de terminé (C-1 + C-2)

1. Antsahabe (collège) : fiche 6ème A inchangée (titulaire, délégué, EDT, conseil, activités, pas de barème).
2. Une classe GS : titulaire, effectif, activités ; **pas** de délégué ni de conseil à l’écran.
3. Une école mixte : liste des classes groupée par cycle ; une seule Caisse ; un seul onglet Finance.
4. Créer les niveaux d’un collège se fait par le pack C-2, puis on peut renommer une classe.

## 8. Invariant

`D-23` — **Le cycle ne crée pas de tenant, pas de rôle, pas de politique d’autorisation.** Un professeur de GS et un professeur de 2nde passent par le même `SchoolGate`. Seul l’affichage de la fiche classe (et, en C-1b, quelques validations métier) lit `stage`.
