# FANABE — Périmètre du MVP et plan de phases

> **Statut : validé le 21 août 2026** — voir [`decisions.md`](./decisions.md).
> Étape 6 de la séquence bloquante. Concilie §20 du cahier des charges (priorités P0/P1/P2), la roadmap §22, et la roadmap en 8 phases du brief §6.

## Sommaire

1. [Critère de réussite](#1-critère-de-réussite)
2. [Définition du MVP](#2-définition-du-mvp)
3. [Hors périmètre du MVP](#3-hors-périmètre-du-mvp)
4. [Découpage par phases](#4-découpage-par-phases)
5. [Détail des phases du MVP](#5-détail-des-phases-du-mvp)
6. [Phases post-MVP](#6-phases-post-mvp)
7. [Définition de terminé](#7-définition-de-terminé)
8. [Tests critiques obligatoires](#8-tests-critiques-obligatoires)
9. [Jeu de données de démonstration](#9-jeu-de-données-de-démonstration)
10. [Indicateurs](#10-indicateurs)
11. [Risques d'exécution](#11-risques-dexécution)

---

## 1. Critère de réussite

Le brief §7 fixe trois démonstrations, et elles constituent le seul critère d'acceptation du MVP :

1. **Une école peut créer ses élèves et ses familles.**
2. **Une famille possède une identité portable, rattachable à plusieurs enfants dans plusieurs écoles.**
3. **La direction obtient une valeur opérationnelle réelle : paiements, risques, alertes, actions.**

Trois conséquences sur la manière de trancher les arbitrages :

- **La complétude n'est pas un critère.** §20 est explicite : « le MVP ne doit pas tenter de reproduire toutes les fonctions d'un ERP scolaire ». Une fonction absente n'est pas un défaut ; une fonction présente mais non sécurisée en est un.
- **La démonstration n°3 est la plus exigeante.** Elle suppose une chaîne complète — frais, factures, échéances, paiements, ancienneté, risque, alerte, action, workflow — sans laquelle le cockpit n'affiche que des zéros. C'est le chemin critique du projet.
- **La démonstration n°2 est celle qui différencie.** Elle ne coûte pas cher en écrans, mais elle est irrattrapable si le modèle d'identité est mal posé. Elle est donc traitée en premier.

Un test de recette formulable en une phrase : *une direction ouvre FANABE le matin, voit qui relancer et pourquoi, agit, et la trace de son action est vérifiable.*

---

## 2. Définition du MVP

**Le MVP correspond aux phases 0 à 3 du brief §6**, plus le Family Reliability de la phase 4 dont l'inclusion est discutée en `A-09`.

| Domaine | Contenu du MVP |
|---|---|
| Socle | Multi-tenancy, RBAC/ABAC, audit, authentification, design system, environnement Docker |
| Identité | `Person`, FANABE ID, rôles, relations, foyer, inscription, rattachement, Consent Center minimal, fusion de doublons |
| School Core | Établissement, année, périodes, niveaux, classes, personnel, inscriptions, **présence** |
| Finance | Barèmes, factures, échéances, remises, **enregistrement** de paiements, reçus |
| Collection | Ancienneté, ponctualité, risque à 4 paliers avec facteurs, prévision explicable, file de relance |
| Workflow | 3 templates avec garde-fous : absence répétée, retard de paiement, document manquant |
| Communication | Notification dans l'application, boîte d'envoi, **canal papier**, adaptateur SMS derrière indicateur |
| Cockpit | Vue du jour, actions prioritaires, élèves à suivre |
| Documents | Téléversement, provenance, statuts de vérification, périodes externes |
| Transparence | Historique des accès visible du parent, export des données |
| Reliability | `TrustEvent` émis dès la phase 1 ; Family Reliability, School Reliability et Relationship Health calculés (phase 4) |

### 2.1 Trois inclusions à justifier

**La présence (`Academic`), absente du brief §7.** Sans elle, le cockpit du §11 est vide sur la moitié de ses lignes, le workflow « absence répétée » exigé par le brief est irréalisable, et la ligne « 3 élèves nécessitant une attention » n'a aucune source. La présence est un préalable, pas une extension. Les **notes** sont livrées en phase 7 (bulletins et Early Warning) et n'autorisent jamais rien (`S-30`).

**Le canal papier.** §12 le mentionne, le brief l'omet. C'est le seul canal qui atteint toutes les familles et le seul strictement gratuit ; sans lui, la démonstration n°3 ne fonctionne que pour les familles équipées, soit une minorité dans le contexte cible.

**L'export des données.** §19.3 (« pas de verrouillage artificiel ») est une promesse commerciale centrale. Un export minimal — CSV pour l'école, archive de documents pour la famille — coûte peu et rend la promesse vérifiable.

### 2.2 Ce que le MVP démontre, mis en regard de §20

| Priorité §20 | Fonction | Dans le MVP |
|---|---|---|
| P0 | Identity + Family Account | Oui |
| P0 | School Core minimal | Oui (présence ; notes en phase 7) |
| P0 | Collection Intelligence | Oui |
| P0 | Communication multi-canal de base | Oui (application, papier ; SMS en option) |
| P0 | Reliability familial et relationnel | Family Reliability, School Reliability et Relationship Health |
| P0 | School Day Cockpit | Oui |
| P1 | School Kit | Oui — phase 6 |
| P1 | Certificat vérifiable + QR | Oui — phase 5 |
| P1 | Student Early Warning | Oui — phase 7 |
| P2 | Marketplace, réseaux | Non |

Le calcul de Relationship Health suppose des signaux de communication accumulés. Sur trop peu de faits, **aucun chiffre n'est affiché** (`band = insufficient`, seuil de 5 événements instrumentés). Le papier et le statut `unknown` sont exclus (`G-07`) : l'absence d'indice n'est jamais un mauvais indice.

---

## 3. Hors périmètre du MVP

Explicitement exclu, sans que cela remette en cause la roadmap :

**Reporté à une phase ultérieure :** WhatsApp, réinscription automatisée, cantine, emploi du temps établissement, remplacements.

**Exclu par le cahier des charges lui-même (§25) :** comptabilité complète, marketplace ouverte, réseau social scolaire, identité biométrique, signature qualifiée comme prérequis, partage automatique entre écoles.

**Exclu par décision technique :** encaissement en ligne (`Q-03`), écriture hors ligne au-delà de la présence (`Q-07`), données de santé (§13.3 du modèle de sécurité), OIDC (`Q-02`), applications natives, apprentissage automatique dans les indices.

> Une exclusion mérite d'être signalée à part : **l'encaissement en ligne** est ce que l'on demandera le plus souvent d'ajouter, et c'est le changement de périmètre le plus lourd (`G-01`). Le MVP est conçu pour que cet ajout soit un adaptateur, pas une refonte — mais il reste une décision à part entière (`Q-03`).

---

## 4. Découpage par phases

Ordre du brief §6 respecté, sans parallélisation ni saut d'étape. Chaque phase est livrable, testée et démontrable avant d'ouvrir la suivante.

| Phase | Contenu | Dans le MVP | Bloqué par |
|---|---|---|---|
| **0 — Architecture** | Structure, conventions, auth, multi-tenancy, DB, design system | Oui | **levé** |
| **1 — Identity Foundation** | Person, FANABE ID, comptes, foyer, relations, inscription, permissions, audit, lien parent, transferts | Oui | **levé** (`Q-05` avis juridique en parallèle) |
| **2 — School Core** | Écoles, classes, élèves, inscriptions, présence, frais, paiements | Oui | `Q-06`, `Q-11`, `Q-14` |
| **3 — Collection Intelligence** | Moteur de risque, dashboard, alertes, workflows | Oui | `Q-08`, `Q-13` |
| **4 — Reliability** | TrustEvent, Family / School Reliability, Relationship Health | Oui | — |
| **5 — Documents** | Documents, certificats, vérification QR | Oui | `Q-09`, `Q-10`, `Q-17` |
| **6 — School Kit** | Catalogue, packs, fournisseurs, commandes | Oui | `Q-03` |
| **7 — Intelligence avancée** | Early Warning, cockpit enrichi, recommandations | Oui | — |

Les questions bloquantes sont tranchées — voir [`decisions.md`](./decisions.md). La phase 0 démarre.

---

## 5. Détail des phases du MVP

### Phase 0 — Architecture

**Objectif.** Rendre l'isolation multi-tenant démontrable avant qu'il existe une seule fonctionnalité métier. C'est l'inverse de l'ordre habituel, et c'est délibéré : rétrofiter une isolation sur un modèle existant est un des chantiers les plus coûteux et les plus risqués qui existent.

Contenu : structure du dépôt et frontières Deptrac ; PostgreSQL 18 avec RLS activée ; contexte de tenant et cinq barrières ; authentification Sanctum + TOTP ; RBAC/ABAC et journal d'audit ; value objects `Money`, `PublicId`, `PhoneNumber` ; squelette d'API v1 et OpenAPI ; coquille React, design system, PWA ; `compose.yaml` et `Makefile` ; pipeline CI complet, étage d'isolation inclus.

**Livré quand** : deux écoles factices existent, un test prouve qu'aucune ne voit l'autre par aucun chemin (ORM, SQL brut, job, fichier), le recensement des tables passe, et `make up && make test` fonctionne sur une machine vierge.

### Phase 1 — Identity Foundation

**Objectif.** Poser le modèle d'identité correctement, une fois. C'est la phase la moins visible et la plus irréversible.

Contenu : `Person` et état civil minimal ; **génération du FANABE ID en TDD strict** (checksum, collision, format, uniformité des réponses) ; rôles et relations avec portées ; foyer ; comptes et rattachement par code d'invitation ; flux de rattachement d'une personne existante, y compris la voie hors ligne attestée ; détection et fusion de doublons ; périodes externes ; Consent Center minimal (voir, octroyer, révoquer, historique) ; historique des accès visible du parent ; **émission des `TrustEvent`** (`A-09`).

**Livré quand** : les sept tests critiques du brief §5 passent, les trois personas A/B/C du jeu de démonstration existent et se comportent comme spécifié, et un parent voit qui a consulté le dossier de son enfant.

### Phase 2 — School Core

**Objectif.** Rendre l'école utilisable : inscrire, suivre la présence, facturer, encaisser.

Contenu : établissement, année scolaire, périodes, niveaux, classes, personnel et rôles ; inscriptions et transitions de statut historisées ; **présence** avec saisie rapide par classe et file hors ligne (`Q-07`) ; barèmes de frais et affectation par niveau ; factures, lignes, échéances, remises motivées ; **enregistrement** de paiements append-only et idempotent ; affectation d'un paiement sur les échéances ; reçus numérotés sans trou ; espace parent : mes enfants, mes échéances, mon solde ; export CSV.

**Livré quand** : un cycle complet est démontrable — créer une classe, inscrire un élève, générer sa facture, marquer sa présence, enregistrer un paiement partiel, obtenir un reçu, et le parent voit son solde exact.

### Phase 3 — Collection Intelligence

**Objectif.** Passer du tableau d'impayés à la décision opérationnelle (§8.2 : « qui relancer, quand, par quel canal, avec quelle priorité »).

Contenu : ancienneté de créance et tranches ; taux de ponctualité ; **moteur de risque à 4 paliers avec ses facteurs** ; dérogation manuelle motivée et temporaire (`Q-08`) ; prévision hebdomadaire explicable (`A-05`) ; tableau de recouvrement par classe, niveau, ancienneté ; file d'actions priorisée ; moteur de workflow et ses trois templates, avec tous les garde-fous ; boîte d'envoi, notification dans l'application, canal papier, adaptateur SMS derrière indicateur ; **School Day Cockpit** ; suivi des actions (prise en charge, résolution).

**Livré quand** : la direction ouvre le cockpit, voit trois actions prioritaires justifiées par des faits, en exécute une, et la trace est auditable. Et : un test prouve qu'aucun niveau de risque n'apparaît dans un message envoyé à une famille.

### Fin de MVP — Family Reliability

Calculateur versionné, facteurs explicatifs, affichage réservé au personnel, et le test `S-30` qui prouve qu'aucune autorisation ne dépend d'un score.

### Phase 4 — Reliability (livrée)

School Reliability (visible du seul établissement concerné), Relationship Health (aucun chiffre affiché sous 5 faits instrumentés — `G-07` exclut le papier et le statut `unknown`), versionnement et comparaison des calculateurs (`calculator_version`, `inputs_digest`), tableau d'explicabilité (`reliability_score_factors`).

---

## 6. Phases 5 à 7 (livrées)

**Phase 5 — Documents.** Émission de certificats de scolarité, artefact HTML rendu une fois et haché (`G-05`), jeton 160 bits stocké haché, vérification publique `{APP_URL}/verify/{token}`, divulgation minimale `Q-09`, révocation, `DocumentSigner` Ed25519, attestation de documents externes (la provenance `external` reste figée).

**Phase 6 — School Kit.** Liste de fournitures par niveau et année, trois gammes (Éco / Standard / Luxe) avec marque et prix par article, publiée par la direction ou le titulaire, recopiable d’une année sur l’autre. Le parent commande une gamme chez le partenaire **ou** fournit lui-même. Paiement **chez le fournisseur** (`D-21`). Commission paramètre, jamais encaissée.

**Phase 7 — Intelligence avancée.** Notes et bulletins (hors maternelle), Student Early Warning à formulation neutre et accusé humain, liste **Attention** du cockpit distincte des actions de recouvrement.

---

## 7. Définition de terminé

Brief §5 : prouver, pas affirmer. Une fonctionnalité n'est terminée que lorsque les sept preuves sont produites et vérifiables par un tiers.

| # | Preuve | Comment elle est établie |
|---|---|---|
| 1 | Les tests passent | CI verte, couverture des chemins critiques |
| 2 | Les permissions sont vérifiées | Un test par rôle, autorisé **et** refusé |
| 3 | Les cas limites sont testés | Valeurs nulles, zéro, limites, concurrence, doublons |
| 4 | L'isolation multi-tenant est testée | Suite `tests/Isolation/` étendue à la nouvelle ressource |
| 5 | L'UX est vérifiée | Parcours sur 360 px, clavier, contraste, état hors ligne, état vide |
| 6 | Les migrations sont vérifiées | Aller **et retour**, sur une base contenant déjà des données |
| 7 | Les performances critiques sont vérifiées | Mesure sur le jeu de démonstration, et sur un jeu 10× |

Deux exigences complémentaires :

- **TDD obligatoire** sur : identité et checksum, autorisation, isolation multi-tenant, workflows, documents et certificats (brief §5).
- **Livraison incrémentale**, jamais de « big bang » (brief §4) : chaque phase est fusionnée et démontrable avant l'ouverture de la suivante.

Le point 6 mérite d'être souligné : une migration testée uniquement sur une base vide passe, puis échoue en production sur les données réelles. La vérification se fait sur une base peuplée par le seeder.

---

## 8. Tests critiques obligatoires

Les sept tests exigés par le brief §5, tracés vers des fichiers nommés. Ils sont écrits **en phase 1**, avant l'implémentation correspondante.

| # | Exigence du brief | Fichier | Phase |
|---|---|---|---|
| 1 | L'école A ne peut jamais lire les données de l'école B | `tests/Isolation/CrossSchoolReadTest.php` | 0 |
| 2 | Un parent ne voit que ses enfants autorisés | `tests/Isolation/ParentChildScopeTest.php` | 1 |
| 3 | Une école ne peut pas lire l'historique d'une autre sans autorisation | `tests/Isolation/CrossSchoolHistoryTest.php` | 1 |
| 4 | Un même Person ID peut porter plusieurs rôles | `tests/Feature/Identity/MultiRolePersonTest.php` | 1 |
| 5 | Un enfant peut devenir parent sans changer d'identité | `tests/Feature/Identity/StudentBecomesParentTest.php` | 1 |
| 6 | Un élève peut changer d'école sans changer d'identité | `tests/Feature/Identity/SchoolTransferKeepsIdentityTest.php` | 1 |
| 7 | Une donnée externe reste identifiée comme externe | `tests/Feature/Documents/ExternalProvenanceTest.php` | 1 |

Les suites complètes figurent dans [`security-model.md`](./security-model.md#14-tests-de-sécurité-obligatoires) (`S-01` à `S-36`) et [`identity-model.md`](./identity-model.md#12-invariants-testables) (`I-01` à `I-15`).

---

## 9. Jeu de données de démonstration

Brief §8. Le seeder est **déterministe** (graine fixe) : un jeu aléatoire rendrait les tests instables et les démonstrations non reproductibles.

```
3 écoles          École Antsahabe (privée, 45 élèves, plan Plus)
                  École Ambohipo  (privée, 38 élèves, plan Starter)
                  École Itaosy    (privée, 17 élèves, plan Starter)

10 classes        réparties sur les 3 écoles, du primaire au collège
                  (cycles `D-23` : un produit, `grade_levels.stage` ; voir [`cycles.md`](./cycles.md))
100 élèves        avec inscriptions actives sur 2026-2027
50 familles       dont 12 avec plusieurs enfants, 4 multi-écoles
                  parents, tuteurs, responsables financiers distincts
rôles             chaque rôle applicatif représenté au moins une fois
paiements         ~180 paiements, dont partiels et à temps
retards           ~25 échéances en retard, réparties sur les 4 paliers
alertes           quelques alertes d'absence et de retard de paiement
documents         quelques natifs, quelques externes non vérifiés
                  et un attesté par l'école
School Kits       listes de fournitures par niveau (Éco / Standard / Luxe, marque + prix)
```

### 9.1 Les trois cas obligatoires

Ils ne sont pas de la décoration : ce sont les preuves vivantes du modèle d'identité, et chacun est adossé à un test.

| Persona | Scénario | Ce qu'il prouve |
|---|---|---|
| **Person A** | Ancien élève de l'École Antsahabe (2005-2016) → parent depuis 2024 → un enfant à Antsahabe, un à Ambohipo | Identité permanente, cumul de rôles, multi-écoles, **et qu'Ambohipo ne voit rien de sa scolarité passée à Antsahabe** |
| **Person B** | Élève actif, École Antsahabe, 5ᵉ, 2026-2027, parent Person D | Le parcours nominal complet |
| **Person C** | Antsahabe (2022-2023) → « Lycée Saint-Michel » hors réseau (2023-2024, bulletin importé) → retour à Antsahabe (2024-2026), **même identité** | Continuité à travers un trou, aucune donnée inventée, provenance externe préservée |

### 9.2 Exigences sur le seeder

Noms et lieux plausibles pour Madagascar ; montants réalistes en Ariary ; dates cohérentes avec un calendrier scolaire malgache ; **aucune donnée réelle** ; rejouable en une commande (`make seed`) ; et un jeu 10× (`make seed --scale=10`) pour les mesures de performance, afin que le jeu de démonstration ne masque pas les problèmes de passage à l'échelle.

---

## 10. Indicateurs

Sous-ensemble de §23 mesurable dès le MVP :

| Domaine | Indicateur | Instrumenté en |
|---|---|---|
| Adoption | Part des parents activés parmi les élèves inscrits | Phase 1 |
| Usage | Familles actives mensuelles | Phase 1 |
| Recouvrement | Taux de collecte à échéance, créances > 30 jours, DSO | Phase 3 |
| Communication | Taux de remise, taux de lecture (canaux instrumentés **uniquement** — `G-07`) | Phase 3 |
| Action | Alertes traitées, délai de prise en charge | Phase 3 |
| Documents | Certificats vérifiés | Phase 5 |
| School Kit | Commandes transmises au fournisseur | Phase 6 |
| Early Warning | Signalements ouverts / accusés | Phase 7 |

Reportés : rétention (au-delà du MVP).

---

## 11. Risques d'exécution

| Risque | Effet | Réponse |
|---|---|---|
| Dérive vers l'ERP (« il manque les notes, la cantine, l'emploi du temps ») | MVP jamais livré, différenciation diluée | §3 opposable ; §20 et §25 font foi |
| Isolation ajoutée après coup | Reprise massive, risque de fuite | Phase 0 la traite en premier, et la prouve |
| Modèle d'identité mal posé | Le plus coûteux à corriger de tout le projet | TDD strict en phase 1, personas A/B/C dès le seeder |
| Blocage sur les paiements en ligne | Retard de plusieurs phases | `Q-03` tranché avant la phase 2 |
| Volume de communication non maîtrisé | Coût, exaspération des familles | Garde-fous du moteur dès la phase 3, mode simulation obligatoire |
| Indices non explicables | Non-conformité à §18 | `calculator_version` et facteurs obligatoires par contrainte de schéma |
| Cockpit lent au pire moment | Rejet par la direction | Modèles de lecture dès la phase 3, mesure sur jeu 10× |
| Questions bloquantes sans réponse | Phase 0 impossible à démarrer | [`open-questions.md`](./open-questions.md), défauts proposés partout |
