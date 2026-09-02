# FANABE — Architecture

> **Statut : validé le 21 août 2026** — voir [`decisions.md`](./decisions.md).
> Prérequis de lecture : [`spec-audit.md`](./spec-audit.md), [`open-questions.md`](./open-questions.md), [`decisions.md`](./decisions.md).

## Sommaire

1. [Principes directeurs](#1-principes-directeurs)
2. [Vue d'ensemble](#2-vue-densemble)
3. [Le modèle à deux plans](#3-le-modèle-à-deux-plans)
4. [Découpage modulaire](#4-découpage-modulaire)
5. [Structure du dépôt](#5-structure-du-dépôt)
6. [Anatomie d'un module](#6-anatomie-dun-module)
7. [Multi-tenancy](#7-multi-tenancy)
8. [Ports et adaptateurs](#8-ports-et-adaptateurs)
9. [Événements, jobs et cohérence](#9-événements-jobs-et-cohérence)
10. [Modèles de lecture et performance](#10-modèles-de-lecture-et-performance)
11. [API](#11-api)
12. [Frontend et PWA](#12-frontend-et-pwa)
13. [Choix techniques et justifications](#13-choix-techniques-et-justifications)
14. [Environnements et outillage](#14-environnements-et-outillage)
15. [Stratégie de test](#15-stratégie-de-test)
16. [Objectifs non fonctionnels](#16-objectifs-non-fonctionnels)
17. [Journal de décisions](#17-journal-de-décisions)

---

## 1. Principes directeurs

Sept principes qui départagent les décisions en cas de doute, par ordre de priorité :

1. **L'isolation des dossiers ne se négocie pas.** Toute fonctionnalité qui rendrait l'isolation multi-tenant plus difficile à prouver est refusée, même si elle est utile.
2. **Les faits avant les états.** On enregistre ce qui s'est produit ; les états dérivés sont recalculables. Un état écrasé est une information perdue (brief §3, cahier des charges §17).
3. **Le cœur fonctionne seul.** Aucune fonctionnalité essentielle ne dépend d'une API externe payante. SMS et WhatsApp sont des adaptateurs facultatifs **hors MVP** (`D-20`) ; le canal papier, l'application et l'email sont les chemins de premier plan. FANABE n'encaisse pas (`D-15`, `D-21`).
4. **Tout score est explicable.** Un indice qu'on ne peut pas décomposer en faits datés n'est pas livrable (§9.1, §18).
5. **Rien d'automatique ne restreint un droit.** L'automatisation informe et priorise ; elle ne refuse, ne bloque et ne sanctionne jamais (`A-04`).
6. **L'action avant le rapport.** Chaque écran répond à « que dois-je faire maintenant, et pourquoi ». Un tableau qui n'induit pas d'action est un tableau à refaire.
7. **Monolithe modulaire, frontières réelles.** Pas de microservices, mais des frontières de modules vérifiées par outil, pas par bonne volonté.

---

## 2. Vue d'ensemble

```
┌──────────────────────────────────────────────────────────────────────┐
│  CLIENTS                                                             │
│  PWA React (direction, personnel, parent)   ·   Vérification publique│
│  (installable, lecture hors ligne)              (page anonyme, QR)   │
└───────────────────────────┬──────────────────────────────────────────┘
                            │ HTTPS · JSON · jeton porteur (Bearer)
┌───────────────────────────▼──────────────────────────────────────────┐
│  API REST versionnée  /api/v1                                        │
│  Middlewares : auth → contexte de tenant → RBAC/ABAC → débit → audit │
└───────────────────────────┬──────────────────────────────────────────┘
                            │
┌───────────────────────────▼──────────────────────────────────────────┐
│  MONOLITHE MODULAIRE LARAVEL                                         │
│                                                                      │
│  Plan Plateforme (sans tenant)      Plan Établissement (tenant)      │
│  ─────────────────────────────      ────────────────────────────     │
│  Identity   Family   Consent        School    Enrollment  Academic   │
│                                     Finance   Collection  SchoolKit  │
│  Transverse : Platform (Tenancy · Audit · Authorization · Outbox)    │
│  Moteurs    : Workflow · Reliability · Analytics                     │
│  Contenus   : Documents · Certificate · Communication                │
└──────┬───────────────┬──────────────┬───────────────┬────────────────┘
       │               │              │               │
┌──────▼─────┐ ┌───────▼──────┐ ┌─────▼──────┐ ┌──────▼──────────────┐
│ PostgreSQL │ │ Redis        │ │ S3/MinIO   │ │ Adaptateurs sortants│
│ 18 (+RLS)  │ │ cache/queues │ │ documents  │ │ SMS·WhatsApp·mail·  │
│            │ │              │ │ (privé)    │ │ impression·paiement │
└────────────┘ └──────────────┘ └────────────┘ └─────────────────────┘
```

Un seul déployable applicatif, une seule base. Les frontières sont logiques et outillées, pas réseau.

---

## 3. Le modèle à deux plans

C'est la décision structurante du projet et la résolution de la contradiction `A-01`. Elle conditionne tout le reste.

### 3.1 Définition

| | Plan Plateforme | Plan Établissement |
|---|---|---|
| **Question à laquelle il répond** | « Qui est cette personne ? » | « Que s'est-il passé dans cette école ? » |
| **`school_id`** | Absent | **Non nul, obligatoire** |
| **Contenu** | `Person`, identifiant public, `Relationship`, `Family`, comptes, `Consent`, documents détenus par la famille, audit | Inscriptions, classes, présence, notes, frais, factures, paiements, risques, alertes, communications, kits, workflows |
| **Isolation** | Par **lien** et par **consentement** | Par **tenant**, stricte, à tous les niveaux |
| **Cycle de vie** | Permanent (§6.1) | Lié à l'abonnement et à la scolarité |

### 3.2 Les trois règles d'accès

Ces trois règles constituent le cœur du modèle de sécurité et sont directement traduites en tests obligatoires.

> **R1 — Règle du lien.** Un établissement ne peut lire une fiche `Person` que s'il détient un **lien actif** : soit une inscription de cette personne, soit une relation entre cette personne et un élève qu'il inscrit. Sans lien, la personne est inexistante de son point de vue — et l'API répond de manière **uniforme**, sans révéler la différence entre « n'existe pas » et « existe mais sans lien ».

> **R2 — Règle du plafond.** Même avec un lien actif, un établissement ne voit du plan plateforme qu'un **jeu d'attributs civils minimal** : nom, prénom, date de naissance, sexe. **Les contacts (téléphone, email) ne sont visibles qu'après établissement d'un lien** — rédemption d'un lien parent, ou confirmation d'une demande initiée par identifiant public (`D-18`, `D-22`). Jamais les autres inscriptions, ni les autres écoles, ni la fratrie ailleurs.

> **R3 — Règle du consentement.** Tout accès à une donnée produite par un **autre** établissement exige un `Consent` actif, non expiré, non révoqué, dont la portée et la finalité couvrent la lecture demandée. Le rattachement d'une personne ne vaut jamais consentement.

`R3` est ce qui rend l'identité portable compatible avec §25 (« ne pas partager automatiquement les dossiers d'une école à l'autre »). Sans elle, l'identité portable deviendrait un canal de fuite entre concurrents.

### 3.3 Conséquence sur la continuité de service

§19.1 et §19.2 exigent qu'une famille conserve son accès quand son école cesse d'être cliente. C'est réalisable **uniquement** si les documents détenus par la famille et son identité vivent dans le plan plateforme. Si ces objets portaient un `school_id`, la désactivation d'un tenant les emporterait. Cette exigence commerciale est donc en réalité une contrainte d'architecture, et elle est satisfaite par construction.

---

## 4. Découpage modulaire

Réconciliation de §15.2 et du brief §2 (voir `A-07`). Trois modules sont **ajoutés** par rapport au brief : `Academic`, `Consent`, `Platform`.

### 4.1 Plan plateforme

| Module | Responsabilité | Ne fait pas |
|---|---|---|
| `Identity` | `Person`, identifiant public FANABE, état civil, rôles, comptes, rattachement, fusion de doublons | Ne connaît ni école ni scolarité |
| `Family` | Foyer, `Relationship` (ParentOf, GuardianOf, FinancialContactFor, PickupAuthorizedFor) | Ne facture pas |
| `Consent` | Portées, finalités, durées, octroi, révocation, historique, expiration | N'applique pas les autorisations (c'est `Platform/Authorization` qui interroge `Consent`) |

### 4.2 Plan établissement

| Module | Responsabilité | Ne fait pas |
|---|---|---|
| `School` | Établissement, réseau, année scolaire, périodes, niveaux, classes, personnel | N'inscrit pas |
| `Enrollment` | Inscription (personne × école × année), statuts, transferts, périodes externes | Ne note pas |
| `Student` | Vue élève dans un établissement, dossier, référentiel de suivi | N'est pas l'identité |
| `Academic` | Présence, notes, périodes d'évaluation, bulletins | Ne décide pas des alertes |
| `Finance` | Barèmes de frais, affectation, factures, échéances, paiements, remises, reçus | Ne juge pas le risque |
| `Collection` | Ancienneté de créance, ponctualité, niveau de risque à 4 paliers, prévision, file de relance | N'envoie pas les messages |
| `SchoolKit` | Liste de fournitures par niveau, gammes Éco/Standard/Luxe (marque + prix), commande partenaire ou auto-fourniture | N'encaisse pas (`D-15`, `D-21`) |

### 4.3 Moteurs

| Module | Responsabilité |
|---|---|
| `Workflow` | `Event → Rule → Action` : templates plateforme, paramètres par école, garde-fous, exécutions, idempotence |
| `Reliability` | `TrustEvent`, calculateurs versionnés, Family / School Reliability, Relationship Health, facteurs d'explication |
| `Analytics` | Modèles de lecture agrégés : cockpit, tableau de recouvrement, KPI (§23) |

### 4.4 Contenus et échanges

| Module | Responsabilité |
|---|---|
| `Documents` | Fichiers, provenance, versions, empreintes, statuts de vérification, documents externes |
| `Certificate` | Émission, révocation, expiration, rendu PDF, jeton et endpoint de vérification, `DocumentSigner` |
| `Communication` | Messages, canaux, boîte d'envoi (outbox), statuts d'acheminement, préférences, joignabilité, file d'impression |

### 4.5 Transverse

`Platform/` contient ce qu'aucun module métier ne peut détenir sans conflit d'intérêt :

- `Tenancy` — résolution et propagation du contexte d'établissement
- `Authorization` — RBAC, ABAC, politiques, interrogation de `Consent`
- `Audit` — journal append-only des accès et actions sensibles
- `Outbox` — publication fiable des messages sortants
- `Support` — types partagés : `Money`, `PublicId`, `DateRange`, `PhoneNumber`

> Un module métier qui journaliserait ses propres accès pourrait cesser de le faire. L'audit est donc placé hors de portée des modules qu'il observe, et invoqué par les middlewares et les politiques.

### 4.6 Règles de dépendance, vérifiées par outil

```
Contenus / Moteurs  ─────►  Plan Établissement  ─────►  Plan Plateforme
        │                            │                        │
        └────────────────────────────┴────────────────────────►  Platform (transverse)
```

1. Le plan plateforme **n'importe jamais** le plan établissement. `Identity` ne connaît pas `Finance`.
2. Un module du plan établissement peut dépendre du plan plateforme et de `Platform`.
3. Deux modules de même niveau ne communiquent **que** par événements de domaine ou par contrats explicites, jamais par accès direct aux modèles Eloquent d'autrui.
4. `Platform` ne dépend d'aucun module métier.

Ces règles sont vérifiées en intégration continue par **Deptrac**. Une violation fait échouer la CI — c'est la différence entre une architecture modulaire et une intention modulaire.

---

## 5. Structure du dépôt

```
fanabe/
├── docs/                          # Ces documents (source de vérité de conception)
├── api/                           # Application Laravel
│   ├── app/
│   │   ├── Domain/
│   │   │   ├── Identity/  Family/  Consent/
│   │   │   ├── School/  Enrollment/  Student/  Academic/
│   │   │   ├── Finance/  Collection/  SchoolKit/
│   │   │   ├── Workflow/  Reliability/  Analytics/
│   │   │   ├── Documents/  Certificate/  Communication/
│   │   │   └── Platform/          # Tenancy · Authorization · Audit · Outbox · Support
│   │   ├── Http/Api/V1/           # Contrôleurs, ressources, requêtes, par module
│   │   └── Providers/
│   ├── database/
│   │   ├── migrations/            # Préfixées par module
│   │   ├── factories/
│   │   └── seeders/               # Jeu de démonstration (brief §8)
│   ├── tests/
│   │   ├── Architecture/          # Deptrac, invariants structurels
│   │   ├── Isolation/             # Tests d'isolation multi-tenant obligatoires
│   │   ├── Feature/
│   │   └── Unit/
│   └── deptrac.yaml  phpstan.neon  pint.json
├── web/                           # Application React
│   ├── src/
│   │   ├── app/                   # Routage, providers, coquille
│   │   ├── features/              # Par domaine, en miroir des modules backend
│   │   ├── shared/                # Design system, client API, hooks, i18n
│   │   └── offline/               # Service worker, cache, file d'actions
│   └── vite.config.ts  tsconfig.json
├── docker/                        # Dockerfiles, nginx, entrypoints
├── compose.yaml
├── Makefile
└── README.md
```

Le monorepo est retenu : le contrat d'API est partagé et les types TypeScript sont générés depuis l'OpenAPI du backend. Deux dépôts imposeraient une synchronisation manuelle sans bénéfice à cette échelle.

---

## 6. Anatomie d'un module

Convention identique partout, pour qu'un développeur qui connaît un module connaisse les quinze autres :

```
app/Domain/Finance/
├── Models/                # Modèles Eloquent (persistance uniquement)
├── ValueObjects/          # Money, DueDate, InvoiceNumber — immuables, auto-validants
├── Actions/               # Un cas d'usage = une classe = une méthode publique
├── Policies/              # Autorisation, par modèle
├── Events/                # Événements de domaine (passé, immuable)
├── Listeners/
├── Jobs/                  # Travaux asynchrones
├── Contracts/             # Interfaces exposées aux autres modules
├── Read/                  # Requêtes de lecture et projections
├── Rules/                 # Règles de validation
└── Support/
```

Choix appuyés :

- **Actions plutôt que services.** `RecordPayment` fait une chose, se teste seule, et son nom dit ce qu'elle fait. Un `PaymentService` de 800 lignes est le point de départ habituel d'un module qu'on n'ose plus toucher.
- **Value objects sur l'argent, les dates et les identifiants.** `Money` rend une addition d'ariary en flottant impossible à écrire par accident, ce qui vaut mieux qu'une revue de code attentive.
- **Modèles Eloquent sans logique métier.** La règle métier est dans l'action ; les modèles font la persistance, les relations et les scopes. Cela évite la classe `Student` de 2 000 lignes.
- **`Contracts/` est la surface publique.** Ce qui n'y est pas ne doit pas être utilisé de l'extérieur, et Deptrac le fait respecter.

---

## 7. Multi-tenancy

Le brief exige une isolation « à TOUS les niveaux » et jamais un masquage frontend. Une seule barrière est insuffisante : la conception retenue en empile **cinq**, dont deux qui restent efficaces même quand le code applicatif est fautif.

### 7.1 Modèle : base unique, schéma partagé, `school_id` obligatoire

Écarté : schéma par tenant (des centaines de schémas, migrations pénibles, requêtes transverses impossibles pour un réseau) et base par tenant (coût d'exploitation prohibitif). Retenu : schéma partagé, colonne `school_id` non nulle sur **chaque** table du plan établissement.

### 7.2 Les cinq barrières

**Barrière 1 — Contexte de tenant explicite.** Un middleware résout le contexte à partir du principal authentifié et d'un sélecteur d'établissement explicite. Le contexte est un objet immuable pour la durée de la requête. **Absence de contexte = refus**, jamais « tous les tenants ». Le défaut, quand il y a doute, est de ne rien montrer.

**Barrière 2 — Portée globale Eloquent.** Un trait `BelongsToTenant` ajoute une portée globale filtrant sur `school_id` et remplit la colonne à la création. Impossible à désactiver hors d'un contexte administrateur plateforme explicitement tracé.

**Barrière 3 — Row Level Security PostgreSQL.** Chaque table du plan établissement porte une politique RLS s'appuyant sur une variable de session posée par requête (`SET LOCAL app.current_school_ids`). C'est la barrière décisive : elle protège aussi le **SQL brut**, les requêtes agrégées et les jobs qui oublieraient la portée applicative. Un développeur qui écrit `DB::select(...)` en oubliant le tenant est arrêté par la base elle-même.

**Barrière 4 — Clés étrangères composites.** Les références internes à un tenant incluent `school_id` :

```sql
FOREIGN KEY (school_id, class_id) REFERENCES classes (school_id, id)
```

Cela rend une référence croisée entre établissements **impossible à écrire**, même par un bug ou une injection de paramètre. Une fuite inter-tenant devient une violation de contrainte, pas un incident silencieux.

**Barrière 5 — Contexte dans les jobs et le stockage.** Un job sérialise son contexte de tenant, le rétablit à l'exécution, et **échoue s'il en est dépourvu** (fail-closed). Les clés de stockage sont préfixées (`schools/{school_id}/…`, `persons/{person_id}/…`) et les URL signées ne sont émises qu'après contrôle d'autorisation, avec une durée de vie courte.

### 7.3 Vérifications automatiques

Trois tests structurels, exécutés en CI, qui échouent si l'architecture dérive :

1. **Recensement des tables** — toute table du plan établissement possède `school_id` non nul, un index le contenant en tête, et une politique RLS active. Une nouvelle migration non conforme casse la CI.
2. **Matrice d'isolation** — pour chaque ressource d'API, un test tente l'accès depuis un second établissement et exige une réponse uniforme (`404`), jamais `403` (un `403` confirmerait l'existence de la ressource).
3. **Absence de fuite par les scores** — aucune politique d'autorisation n'importe le module `Reliability` ni `Collection` (traduction en code de `A-04` et §18).

---

## 8. Ports et adaptateurs

Application du principe n°3. Tout franchissement de la frontière du système passe par une interface définie dans le domaine, avec une implémentation par défaut **gratuite et locale**.

| Port | Défaut (aucune dépendance externe) | Adaptateurs ultérieurs |
|---|---|---|
| `SmsGateway` | `NullSmsGateway` — **aucun parcours MVP ne l'appelle** (`D-20`) | Passerelle locale, plus tard |
| `WhatsAppGateway` | Absent — non configuré = canal indisponible | API Cloud officielle |
| `MailGateway` | SMTP (Mailpit en développement) | Fournisseur transactionnel |
| `PrintSpooler` | **File d'impression + PDF groupé** — canal de premier plan (§12) | — |
| `PaymentGateway` | `ManualPaymentRecorder` (saisie caisse). School Kit : paiement chez le fournisseur (`D-21`) | Mobile money, PSP — hors MVP |
| `DocumentSigner` | `PlatformAttestationSigner` (Ed25519 sur l'empreinte, clé identifiée et rotative) | Signature qualifiée conforme 2014-025 |
| `ObjectStorage` | MinIO en développement, S3 en production | — |
| `NationalIdentityDirectory` | Absent | PRODIGY, le jour où une API existe |

Deux points à ne pas perdre de vue :

- **Le canal papier n'est pas un mode dégradé.** C'est le seul canal qui atteint toutes les familles et le seul strictement gratuit. Il est traité comme un adaptateur de plein droit, pas comme une impression de secours.
- **`PlatformAttestationSigner` ne doit jamais être présenté comme une signature électronique qualifiée.** §14.2 est explicite. Le PDF et l'écran de vérification portent une mention littérale : *« attestation de plateforme FANABE — ne constitue pas une signature électronique qualifiée au sens de la loi 2014-025 »*. Cette phrase est une exigence, pas une précaution rédactionnelle.

---

## 9. Événements, jobs et cohérence

### 9.1 Event sourcing léger, et seulement là où il paie

Le brief demande de privilégier les événements. Appliquer l'event sourcing intégral à tout le système serait payer un coût considérable pour des entités qui n'en tirent rien (une classe, un niveau, un barème). Le choix est donc **ciblé** :

| Approche | Où | Pourquoi |
|---|---|---|
| **État courant simple** | Référentiels : écoles, classes, niveaux, barèmes, packs | Aucun besoin de rejouer l'histoire d'un nom de classe |
| **État + journal d'événements** | Finance, présence, inscriptions, communications, documents, consentements | Traçabilité exigée, corrections nécessaires, reconstruction utile |
| **Événements comme seule source de vérité** | `TrustEvent`, `BehaviorEvent`, alertes | Les indices doivent être recalculables et explicables (§9.1, §17) |

Conséquences pratiques : les tables financières sont **append-only** (une erreur de saisie se corrige par une écriture d'annulation, jamais par un `UPDATE` ni un `DELETE`) ; les statuts d'inscription sont des transitions historisées ; le `TrustEvent` est émis **dès la phase 1** même si aucun indice n'est calculé avant la phase 4 (`A-09`).

### 9.2 Événements de domaine et traitement asynchrone

Un événement de domaine décrit un fait **passé** et immuable (`PaymentRecorded`, `AttendanceMarkedAbsent`, `InvoiceOverdue`, `EnrollmentCreated`). Il est publié dans la transaction, dispatché après commit, et consommé par les moteurs (`Workflow`, `Reliability`, `Analytics`).

Files Redis séparées par criticité : `critical` (authentification, paiements), `default` (workflows, indices), `low` (projections analytiques), `outbox` (messages sortants). Séparer évite qu'un recalcul d'indices nocturne ne retarde l'enregistrement d'un paiement.

### 9.3 Boîte d'envoi (outbox)

Tout message sortant est d'abord **écrit en base** dans la même transaction que le fait qui le motive, puis expédié par un worker. Cela garantit qu'un SMS n'est jamais envoyé pour un paiement dont la transaction a échoué, et qu'aucune notification n'est perdue si la passerelle est indisponible. Chaque message porte une clé d'idempotence : rejouer un job ne produit pas un doublon.

---

## 10. Modèles de lecture et performance

Le School Day Cockpit (§11) affiche une douzaine d'indicateurs hétérogènes. Les calculer à chaque affichage produirait une page lente au pire moment (8 h du matin, tout le personnel connecté simultanément).

Choix : **modèles de lecture dénormalisés**, alimentés par les événements et rafraîchis par tâche planifiée, avec la fraîcheur affichée à l'utilisateur.

| Modèle de lecture | Alimentation | Fraîcheur cible |
|---|---|---|
| `school_day_snapshot` | Événements de présence, paiements, alertes | Temps réel sur les compteurs critiques, ≤ 5 min sur les agrégats |
| `collection_dashboard` | Paiements, échéances, recalcul nocturne d'ancienneté | ≤ 15 min |
| `family_financial_summary` | Factures et paiements | Temps réel |
| `student_attention_list` | Alertes, risques, présence | ≤ 15 min |

Un chiffre affiché sans indication de fraîcheur est un chiffre auquel on ne peut pas se fier ; chaque bloc du cockpit porte donc son horodatage de calcul.

---

## 11. API

REST versionnée sous `/api/v1`, JSON, jetons porteurs. Conventions :

- **Ressources par module** : `/api/v1/schools/{school}/students`, `/api/v1/persons/{person}`, `/api/v1/certificates/{certificate}`.
- **Le tenant est explicite dans l'URL** pour les ressources d'établissement. Un tenant implicite est une source d'erreurs difficiles à détecter ; le rendre explicite permet en plus de vérifier la cohérence entre l'URL et le contexte autorisé.
- **Réponse uniforme sur l'absence de droit** : `404` et non `403` sur les ressources dont l'existence est en soi une information (`R1`).
- **Validation stricte** : rejet de tout champ inconnu, plutôt que de l'ignorer silencieusement.
- **Pagination par curseur** sur les listes potentiellement longues.
- **Idempotence** : en-tête `Idempotency-Key` obligatoire sur les écritures financières et l'émission de certificats.
- **OpenAPI généré** depuis le code, d'où sont dérivés les types TypeScript et les schémas Zod du frontend. Un contrat écrit à la main divergerait dès la deuxième semaine.
- **Endpoints publics séparés** : la vérification de certificat vit sur un préfixe distinct, sans authentification, avec ses propres limites de débit et aucun accès à la couche tenant.

---

## 12. Frontend et PWA

Trois expériences distinctes, une seule application (les rôles partagent la coquille, le design system et le client API) :

| Expérience | Utilisateur | Écran d'entrée |
|---|---|---|
| **Cockpit** | Direction, administration | Le jour en cours, familles, organisation des classes, caisse — **pas l’appel** |
| **Espace classe** | Professeur (titulaire ou enseignant du cours) | Effectif des classes qu’il enseigne ; appel du jour (primaire) ou du créneau (collège / lycée) |
| **Espace élève** | Élève (lecture seule) | Ma classe, mes présences, mon écolage |
| **Espace famille** | Parent, tuteur | Mes enfants, mes échéances, mes documents |
| **Vérification** | Tiers anonyme | Page unique de statut de certificat |

Choix structurants :

- **Organisation par fonctionnalité**, en miroir des modules backend. Un dossier `components/` global devient ingérable à cette taille.
- **TanStack Query comme seule source de vérité serveur.** Aucun état serveur dupliqué dans un store global : les bugs de synchronisation les plus coûteux naissent de cette duplication.
- **Zod à la frontière**, sur les schémas générés depuis l'OpenAPI. Une réponse non conforme échoue à l'entrée, au lieu de produire un `undefined` trois composants plus loin.
- **Accessibilité par défaut** : composants construits sur des primitives accessibles (rôles ARIA, navigation clavier, contrastes AA), cibles tactiles ≥ 44 px, tout doit être utilisable sur un écran de 360 px.
- **Budget de performance** : lot initial ≤ 200 Ko compressé, découpage par route. La plateforme cible est un téléphone d'entrée de gamme sur réseau 3G, pas un poste de bureau.
- **PWA** : coquille précachée, cache de lecture des données consultées, bannière d'état hors ligne. **Une seule écriture différée : la saisie de présence** (`Q-07`), avec file persistée en IndexedDB, clés d'idempotence et rejeu à la reconnexion. Aucune écriture financière hors ligne — un solde divergent coûte plus cher que la commodité gagnée.

---

## 13. Choix techniques et justifications

Étape 7 de la séquence. La pile est imposée par le brief ; cette section justifie les choix imposés (pour qu'ils soient tenus en connaissance de cause) et documente les choix libres.

### 13.1 Pile imposée — pourquoi elle tient

| Choix | Justification | Ce qu'on accepte en échange |
|---|---|---|
| **Laravel 13 / PHP 8.4** | Le projet est riche en règles métier et en workflows, pas en calcul intensif. Laravel apporte files, planification, autorisation, migrations et validation sans assemblage. L'écosystème PHP est disponible localement, ce qui compte pour la maintenance | Moins adapté au temps réel intensif — non requis ici |
| **Monolithe modulaire** | Les frontières métier ne sont pas encore stables ; les figer en frontières réseau maintenant serait une erreur. Les transactions transverses (inscription + facturation + document) restent triviales | Un seul déployable, mise à l'échelle horizontale par réplique |
| **PostgreSQL 18** | Ce projet a besoin de **contraintes** : unicité de l'identifiant public, clés composites anti-fuite, exclusion de chevauchement de périodes, RLS. Aucune base documentaire ne fournit cela. `JSONB` couvre les métadonnées de provenance et les paramètres de règles | Modélisation relationnelle rigoureuse à tenir |
| **Redis** | Files, cache, verrous, limitation de débit | Composant supplémentaire à exploiter |
| **S3 compatible** | Documents et certificats hors base ; URL signées à durée courte | Choix de région à trancher (`Q-12`) |
| **React 19 / TypeScript / Vite** | TypeScript strict est particulièrement rentable ici : les types générés depuis l'API empêchent une classe entière d'erreurs sur des données sensibles | Rigueur de typage à maintenir |
| **Tailwind** | Design system cohérent sans feuille de style qui dérive ; purge agressive, utile pour le budget de charge | Discipline de tokens nécessaire |
| **PWA** | Répond à §3.3 et §24 sans développer d'application native | Complexité du service worker |

Aucune justification de dérogation n'est demandée : la pile imposée est adaptée au problème.

### 13.2 Choix libres à valider

| Sujet | Retenu | Alternatives écartées, et pourquoi |
|---|---|---|
| **Authentification** | Laravel Sanctum (jetons porteurs), TOTP pour les rôles à privilèges | OIDC/Keycloak : complexité d'exploitation sans partenaire d'interopérabilité à ce stade (`A-11`, `Q-02`) |
| **Clés primaires** | UUID v7 pour les entités tenant (localité d'index), **UUID v4 pour `Person`, `Document`, `Certificate`** | ULID/v7 partout : ordonné dans le temps, donc partiellement énumérable et révélant la date de création — inacceptable sur les entités exposées, où le brief exige l'anti-énumération |
| **Isolation** | Portée Eloquent **+ RLS PostgreSQL** | Portée applicative seule : ne protège pas le SQL brut |
| **Frontières de modules** | Deptrac en CI | Revue de code seule : une frontière non outillée se dégrade |
| **Analyse statique** | PHPStan niveau max, Pint, ESLint, `tsc --strict` | — |
| **Tests** | Pest, PostgreSQL réel en CI (jamais SQLite) | SQLite : ne connaît ni RLS ni les contraintes utilisées — les tests d'isolation y seraient sans valeur |
| **E2E** | Playwright, parcours critiques uniquement | Cypress ; suite E2E exhaustive : lente et fragile |
| **Argent** | `bigint` en unités entières d'Ariary, value object `Money` | Flottants : exclus. `decimal` : inutile, l'Ariary n'a pas d'usage décimal courant |
| **Empreintes** | SHA-256 sur l'artefact stocké | Empreinte d'un PDF régénéré : instable (`G-05`) |
| **Jeton de vérification** | 160 bits aléatoires, **stocké haché** | Jeton en clair en base : une lecture de base exposerait tous les certificats |
| **Signature** | Ed25519, clé identifiée et rotative | RSA : signatures plus lourdes dans un QR |
| **Recherche** | `pg_trgm` + index GIN | Elasticsearch : disproportionné avant plusieurs millions de fiches |
| **PDF** | Rendu HTML → PDF côté serveur en job asynchrone | Rendu synchrone : bloque la requête |

### 13.3 Ce qu'on refuse délibérément

Les décisions négatives évitent plus de dérive que les positives :

- **Pas de microservices** (brief explicite, et les frontières ne sont pas stables).
- **Pas de GraphQL** — un contrat REST typé est plus simple à sécuriser par ressource, ce qui est central ici.
- **Pas de multi-base ni de schéma par tenant** (§7.1).
- **Pas d'apprentissage automatique** dans les indices ni les prévisions : §18 exige l'explicabilité. Une régression logistique non expliquable serait non conforme.
- **Pas de dépendance payante sur un chemin critique**, authentification incluse.
- **Pas de rendu serveur (SSR)** : l'application est privée et fortement interactive ; le SSR ajouterait de l'exploitation sans bénéfice. Seule la page publique de vérification est servie statiquement.
- **Pas de bibliothèque de composants lourde** — le design system reste léger pour tenir le budget de charge.

---

## 14. Environnements et outillage

`compose.yaml` fournit : `api` (php-fpm + nginx), `web` (Vite), `db` (PostgreSQL 18), `redis`, `minio`, `mailpit`, `worker` (files), `scheduler`.

`Makefile` : `make up`, `make migrate`, `make seed`, `make test`, `make lint`, `make fresh`, `make analyse`. Objectif : un développeur clone le dépôt et obtient un environnement complet avec jeu de démonstration en une commande.

CI (GitHub Actions), dans l'ordre du plus rapide au plus lent pour un retour utile tôt : `lint` → `analyse` (PHPStan, Deptrac, `tsc`) → `test:unit` → `test:feature` → **`test:isolation`** → `test:e2e` → `build`. La suite d'isolation est un étage distinct et non contournable : c'est l'exigence de sécurité la plus forte du projet et elle mérite d'être visible dans le pipeline.

---

## 15. Stratégie de test

Boucle imposée par le brief §5 : `Requirement → Domain model → Tests → Implementation → Integration → Verification`. TDD strict sur : identifiant public et checksum, autorisation, isolation multi-tenant, workflows, certificats et documents.

| Niveau | Portée | Outil |
|---|---|---|
| Unitaire | Value objects, checksum, calculateurs de risque et d'indices, règles | Pest |
| Fonctionnel | Endpoints API, permissions, validations, cas limites | Pest + PostgreSQL |
| **Isolation** | Suite dédiée, non contournable (§15) | Pest |
| Architecture | Deptrac, recensement des tables, invariants structurels | Deptrac + Pest |
| E2E | Parcours critiques : inscription, paiement, cockpit, vérification | Playwright |
| Performance | Endpoints du cockpit et du recouvrement sous charge réaliste | k6 |

Les sept tests critiques exigés par le brief §5 sont tracés vers des fichiers nommés dans [`mvp-scope.md`](./mvp-scope.md#8-tests-critiques-obligatoires), afin qu'ils soient vérifiables et non simplement affirmés.

---

## 16. Objectifs non fonctionnels

| Dimension | Cible | Justification |
|---|---|---|
| Latence API (p95) | < 300 ms | Lecture confortable sur réseau lent |
| Cockpit, chargement complet | < 1,5 s sur 3G | Consulté à l'ouverture de l'école, par tous, en même temps |
| Lot JS initial | ≤ 200 Ko compressé | Téléphones d'entrée de gamme |
| Écriture financière | Transactionnelle, idempotente | Aucun double encaissement tolérable |
| Vérification de certificat | < 500 ms, disponible sans authentification | Utilisée par des tiers, parfois nombreux |
| Recalcul nocturne | 3 écoles × 100 élèves en < 30 s ; conçu pour 200 × 1 000 | Le jeu de démonstration ne doit pas masquer le passage à l'échelle |
| Disponibilité visée | 99,5 % en heures ouvrées | Pilote ; à revoir en production |
| Sauvegardes | Quotidiennes, restauration testée | Une sauvegarde non restaurée n'est pas une sauvegarde |

---

## 17. Journal de décisions

| # | Décision | Statut |
|---|---|---|
| `D-01` | Modèle à deux plans (plateforme / établissement) | **Acté** |
| `D-02` | Base unique, schéma partagé, `school_id` obligatoire | **Acté** |
| `D-03` | Cinq barrières d'isolation, dont RLS et clés composites | **Acté** |
| `D-04` | Trois modules ajoutés : `Academic`, `Consent`, `Platform` | **Acté** |
| `D-05` | Deptrac en CI comme garde des frontières | **Acté** |
| `D-06` | Event sourcing ciblé, non généralisé | **Acté** |
| `D-07` | Sanctum + TOTP ; pas de SMS ; OIDC repoussé | **Acté** (`D-20`) |
| `D-08` | UUID v4 pour les entités exposées, v7 pour le reste | **Acté** |
| `D-09` | Boîte d'envoi obligatoire pour tout message sortant | **Acté** |
| `D-10` | Impression comme adaptateur de premier plan | **Acté** |
| `D-11` | Modèles de lecture pour le cockpit, fraîcheur affichée | **Acté** |
| `D-12` | `bigint` en unités d'Ariary, `Money` obligatoire | **Acté** |
| `D-13` | Aucune politique d'autorisation ne lit un score | **Acté** |
| `D-14` | Hors ligne : lecture + présence uniquement | **Acté** |
| `D-15` | Aucun encaissement par FANABE au MVP | **Acté** |
| `D-16` | Checksum modulo 23, alphabet de 23 lettres | **Acté** |
| `D-17` | Templates de workflow plateforme, paramétrés par école | **Acté** |
| `D-18` | Parcours d'inscription : ID ou lien parent, puis élève | **Acté** — [`decisions.md`](./decisions.md) |
| `D-19` | Une inscription active à la fois ; transfert à double validation | **Acté** |
| `D-20` | Aucun SMS dans le MVP | **Acté** |
| `D-21` | Paiement School Kit chez le fournisseur | **Acté** |
| `D-22` | Lien parent = consentement ; identifiant public ≠ consentement | **Acté** |
| `D-23` | Un produit, quatre cycles (`grade_levels.stage`) ; pas d’interface par type d’école | **Acté** — [`cycles.md`](./cycles.md) |
