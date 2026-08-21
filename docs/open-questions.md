# FANABE — Ambiguïtés et décisions à trancher

> **Statut : en attente de réponses.**
> Étape 3 de la séquence bloquante. Chaque question propose un **défaut recommandé** : en l'absence de réponse, c'est ce défaut qui sera retenu, mais les questions marquées **BLOQUANTE** ne peuvent pas être tranchées par défaut car une erreur y est structurellement coûteuse.

## Comment répondre

Le plus efficace : renvoyer uniquement les numéros où vous vous écartez du défaut recommandé, par exemple *« Q-03 option B, Q-06 défaut, Q-12 hébergement en France »*. Les questions non mentionnées seront traitées comme validées au défaut.

| Priorité | Signification |
|---|---|
| **BLOQUANTE** | Réponse nécessaire avant la première ligne de code |
| **Phase n** | Nécessaire avant le démarrage de la phase indiquée |
| **Ouverte** | Peut rester en suspens, le défaut est réversible |

---

## Bloc A — Fondations (bloquantes)

### `Q-01` — Frontière entre données globales et données cloisonnées — **BLOQUANTE**

*Référence : `A-01`, `A-07`. Cahier des charges §6.1, §6.5, §17, §19.2.*

Le produit exige à la fois une identité portable et une isolation stricte par établissement. Ces deux exigences ne peuvent pas être toutes deux absolues (`A-01`). Il faut donc décider ce qui vit **hors** de tout établissement.

**Défaut recommandé.** Vit dans le plan plateforme (sans `school_id`) :

- `Person` et son identifiant public ; état civil **minimal** : nom, prénom, date de naissance, sexe, photo facultative
- Coordonnées de contact de la personne (téléphone, email)
- `Relationship` (ParentOf, GuardianOf, FinancialContactFor, PickupAuthorizedFor)
- `Family` en tant que groupement de foyer
- Comptes d'authentification et sessions
- `Consent`
- Documents **détenus par la famille** (importés par le parent) — condition pour que §19.1 et §19.2 soient tenables
- Journal d'audit

Tout le reste porte un `school_id` non nul : inscriptions, présence, notes, frais, factures, paiements, alertes, communications, kits, risques.

**Questions précises à confirmer :**

1. Une école qui inscrit un élève voit-elle les **coordonnées de contact** du parent (téléphone) sans consentement ? *Défaut proposé : oui, c'est indispensable à sa mission, et l'accès est journalisé.*
2. Une école voit-elle **l'existence** des autres écoles où l'enfant est ou a été inscrit ? *Défaut proposé : non. C'est une information sensible (concurrence, vie privée). Elle n'apparaît qu'avec un consentement explicite.*
3. Le nombre de frères et sœurs, ou leur existence dans d'autres écoles, est-il visible ? *Défaut proposé : non, hors consentement.*

> Le point 2 mérite une attention particulière : révéler la scolarité passée à l'école d'accueil est précisément le service que l'identité portable rend, mais aussi la donnée que le cahier des charges protège le plus fermement (§6.6, §25 dernière puce). Le défaut privilégie la protection ; le consentement reste le chemin d'ouverture.

**Confirmez-vous ce découpage, et les trois réponses ?**

---

### `Q-02` — Mécanismes d'authentification — **BLOQUANTE**

*Référence : `A-11`. Cahier des charges §15.1, §27 (« choisir les mécanismes d'authentification parent/élève/école »).*

§15.1 recommande OIDC/OAuth2 + MFA ; le brief interdit toute dépendance à une API payante sur le chemin critique — ce qui exclut l'OTP SMS comme voie unique.

| Option | Personnel | Parent | Conséquence |
|---|---|---|---|
| **A (défaut)** | Email + mot de passe, TOTP obligatoire pour les rôles à privilèges | Code d'invitation émis par l'école, puis mot de passe ; OTP SMS optionnel si passerelle configurée | Aucune dépendance payante, déployable immédiatement, code d'invitation à distribuer |
| B | Idem A | Téléphone + OTP SMS obligatoire | Simple pour le parent, mais connexion impossible sans crédit SMS et coût par connexion |
| C | OIDC dès le départ (Keycloak) | Idem | Interopérabilité future, mais complexité d'exploitation importante avant tout partenaire réel |

**Défaut recommandé : A**, avec l'authentification derrière une interface pour permettre B ou C plus tard sans reprise.

Sous-questions : (a) un **élève** a-t-il un compte au MVP ? *Défaut : non, accès via le parent ; le rôle existe dans le modèle mais aucun compte n'est ouvert.* (b) Durée de session personnel / parent ? *Défaut : 8 h pour le personnel avec renouvellement glissant, 30 jours pour le parent (usage mobile occasionnel).*

---

### `Q-03` — FANABE encaisse-t-il de l'argent ? — **BLOQUANTE**

*Référence : `G-01`. Le cahier des charges est muet ; §13.2 dit seulement « le paiement suit le modèle du partenaire ».*

C'est la question qui pèse le plus sur le périmètre, la sécurité et le calendrier.

| Option | Description | Conséquences |
|---|---|---|
| **A (défaut)** | FANABE **enregistre** des paiements encaissés hors ligne par l'école (caisse, virement, mobile money reçu directement). Aucun flux financier ne transite par FANABE | Aucune exigence PSP, aucune conservation de données de paiement, MVP atteignable, saisie manuelle à la charge de l'école |
| B | Intégration mobile money (Mvola / Orange Money / Airtel Money) pour paiement parent → école | Forte valeur perçue, mais négociation opérateurs, réconciliation, remboursements, litiges, exigences de conformité |
| C | FANABE encaisse puis reverse à l'école | Modèle le plus lourd : statut réglementaire d'intermédiaire financier, séquestre, comptabilité de tiers. À écarter du MVP |

**Défaut recommandé : A**, avec un port `PaymentGateway` défini mais sans implémentation, pour que B soit un ajout et non une refonte.

Même question pour le School Kit (phase 6) : le parent paie-t-il dans FANABE ou chez le fournisseur ? *Défaut : chez le fournisseur ; FANABE transmet la commande et suit le statut.*

---

### `Q-04` — Portée réelle du Consent Center — **BLOQUANTE**

*Référence : `A-02`, `A-03`. Cahier des charges §6.6.*

**Défaut recommandé.** Deux régimes nettement séparés dans l'UI :

- **« Accès de mon école »** — non révocable, journalisé, visible. L'école accède aux données qu'elle produit dans le cadre de la scolarité. Le parent voit **qui a consulté quoi et quand**, sans pouvoir s'y opposer.
- **« Partages que j'autorise »** — révocable à tout moment. Concerne le partage inter-établissement et les documents détenus par la famille.

Catégories (scopes) proposées : `identity.core`, `identity.contact`, `academic.records`, `academic.attendance`, `finance.history`, `documents.external`, `documents.certificates`, `health.notes` (sensible, isolé, hors MVP).

**Questions :**
1. Ce découpage en deux régimes est-il acceptable, sachant qu'il **réduit** la promesse littérale du §6.6 (le parent ne contrôle pas tout) au profit d'une promesse tenable ?
2. Granularité : par catégorie de données uniquement, ou aussi par document individuel ? *Défaut : par catégorie au MVP, par document pour les documents détenus par la famille (le parent choisit ce qu'il montre).*
3. Un consentement a-t-il une **durée par défaut** ? *Défaut : oui, 12 mois, renouvelable, avec notification avant expiration — un consentement perpétuel n'est pas un consentement.*
4. Qui consent pour un élève **majeur** ? *Défaut : lui-même ; les droits du parent basculent à 18 ans, avec notification aux deux parties. Nécessite confirmation juridique (`Q-05`).*

---

### `Q-06` — Cible de la facturation — **BLOQUANTE**

*Référence : `G-02`. Le cahier des charges §16 est lui-même ambigu (« famille / élève »).*

**Défaut recommandé.** Deux niveaux :

- `FeeAssignment` / `Invoice` **par élève et par année scolaire** — l'obligation naît de la scolarité d'un enfant, l'imputation reste juste.
- `PayerAccount` **par (établissement, famille)** — porte le solde consolidé et reçoit les paiements, permettant un règlement groupé pour plusieurs enfants.

Conséquence utile : un paiement unique de 300 000 Ar pour deux enfants s'impute sur deux factures selon une règle d'affectation explicite (échéance la plus ancienne d'abord, sauf imputation manuelle), et le risque se calcule aux deux niveaux.

**Questions :** (a) Une famille peut-elle avoir **plusieurs** comptes payeurs dans la même école (parents séparés, chacun payant pour son enfant) ? *Défaut : oui, un compte payeur est rattaché à un `FinancialContact` désigné ; ne pas le permettre créerait des impasses fréquentes.* (b) Les remises (fratrie, bourse, personnel) sont-elles au MVP ? *Défaut : oui, sous forme d'une ligne de remise sur facture, avec motif obligatoire — sans cela, les montants dus seront faux et le moteur de risque produira de fausses alertes.*

---

### `Q-13` — Qui écrit les règles de workflow ? — **BLOQUANTE avant la phase 3**

*Référence : `G-08`. Absent du cahier des charges.*

| Option | Description | Conséquences |
|---|---|---|
| **A (défaut)** | Templates fournis par la plateforme, paramétrables par l'école (seuils, canaux, destinataires, activation) | Sûr, prévisible, testable ; couvre les trois cas cités par le brief |
| B | Constructeur de règles visuel (conditions et actions libres) | Puissant, mais surface de risque élevée (boucles, tempêtes de notifications) et coût important |
| C | Scripting | À écarter (exécution de code fourni par le tenant) |

**Défaut recommandé : A**, avec ces garde-fous non négociables : idempotence par (règle, sujet, fenêtre), déduplication, plafond quotidien d'actions par établissement, heures de silence, mode simulation obligatoire avant activation, coupe-circuit global, et interdiction qu'une action déclenche sa propre règle.

**Question :** confirmez-vous que le plafond quotidien et les heures de silence sont configurables par l'école mais **bornés par la plateforme** (une école ne peut pas s'autoriser 10 000 SMS ni écrire à 2 h du matin) ?

---

## Bloc B — Conformité et juridique (nécessite un avis externe)

### `Q-05` — Politique de rétention et droits des personnes — **Phase 1**

*Référence : `A-03`, `G-11`. Explicitement listée comme décision à prendre par le cahier des charges (§27).*

Nécessite un avis juridique malgache (loi 2014-038, CMIL). En attendant, défauts proposés, tous configurables :

| Catégorie | Rétention par défaut | Remarque |
|---|---|---|
| Identité (`Person`) | Tant que la personne a un lien actif, puis 5 ans | L'identité est la promesse du produit, la supprimer vite la contredit |
| Dossier scolaire (notes, présence) | 10 ans après la fin de l'inscription | Aligné sur les usages d'archivage scolaire, à confirmer |
| Pièces financières | 10 ans | Obligation comptable probable, à confirmer |
| Communications | 24 mois | |
| Journal d'audit | 5 ans, append-only | Ne peut pas être purgé par une demande d'effacement |
| Documents détenus par la famille | Jusqu'à suppression par la famille | La famille en est maîtresse |
| `TrustEvent` / `BehaviorEvent` | 5 ans, puis anonymisation | Permet de conserver les statistiques sans les personnes |

**Questions :** (a) Quel âge de majorité numérique retenir pour le basculement des droits parent → élève ? *Défaut : 18 ans.* (b) Une déclaration CMIL préalable est-elle requise avant le pilote ? (c) Un effacement doit-il pouvoir aller jusqu'à la destruction des pièces financières, ou l'obligation de conservation prime-t-elle ? *Défaut : elle prime, et l'UI le dit explicitement.*

### `Q-11` — Numérotation légale des reçus — **Phase 2**

*Référence : `G-10`.* Un reçu de paiement doit-il porter une numérotation séquentielle sans trou par établissement, avec mentions obligatoires ? *Défaut : oui — séquence par (établissement, année scolaire), attribuée en transaction, aucun trou possible, annulation par avoir et jamais par suppression.* Cette hypothèse conservatrice est peu coûteuse à tenir et très coûteuse à rattraper. À confirmer avec un comptable local.

### `Q-12` — Localisation des données — **Phase 0**

*Référence : `G-12`.* Où résident les données ? Un hébergement hors de Madagascar peut constituer un transfert transfrontalier encadré par la loi 2014-038.

Options : hébergement local (souveraineté maximale, offre limitée, latence faible), régional (Afrique du Sud, Maurice — bon compromis latence/qualité), européen (qualité et outillage, transfert transfrontalier à encadrer).

*Défaut proposé : région européenne ou Maurice pour le pilote, avec une abstraction de stockage rendant la migration possible, et la question tranchée avant la mise en production réelle.* Un choix est nécessaire dès la phase 0 car il conditionne le fournisseur S3 et la base managée.

---

## Bloc C — Modèle métier (phases 1 à 3)

### `Q-07` — Périmètre de l'usage hors ligne — **Phase 0**

*Référence : `G-03`. Cahier des charges §3.3, §24.*

| Option | Description | Coût |
|---|---|---|
| **A (défaut)** | Lecture hors ligne + une seule écriture différée : la **saisie de présence** | Modéré. Couvre le geste réellement effectué sans réseau |
| B | Lecture seule | Faible, mais ne tient pas la promesse §3.3 |
| C | Écriture hors ligne généralisée | Élevé : résolution de conflits sur des données financières, à éviter |

**Défaut recommandé : A.** L'encaissement hors ligne est délibérément exclu : accepter une écriture financière différée exposerait à des doubles saisies et des soldes divergents.

### `Q-08` — Seuils du moteur de risque — **Phase 3**

*Référence : `A-08`.* Le brief impose 4 niveaux, le cahier des charges ne donne aucun seuil.

**Défaut recommandé** — règle déterministe et documentée, applicable à un couple (élève, établissement) :

| Niveau | Condition (première ligne satisfaite l'emporte) |
|---|---|
| **Critique** | Impayé > 60 jours, **ou** (impayé > 30 jours **et** taux de ponctualité < 50 %) |
| **Élevé** | Impayé de 31 à 60 jours, **ou** (impayé > 15 jours **et** ≥ 2 retards sur les 4 dernières échéances) |
| **Moyen** | Impayé de 8 à 30 jours, **ou** ≥ 1 retard sur les 3 dernières échéances |
| **Faible** | Tout le reste |

Chaque évaluation stocke les faits qui l'ont produite et la version du calculateur ; les seuils sont paramétrables par établissement dans des bornes fixées par la plateforme.

**Questions :** (a) Ces seuils correspondent-ils à la réalité malgache (délais de tolérance usuels, saisonnalité des revenus) ? (b) Recalcul quotidien ou à chaque événement ? *Défaut : à l'événement, plus un recalcul nocturne pour faire vieillir les créances.* (c) La direction peut-elle **abaisser** manuellement un niveau (situation connue, famille en difficulté reconnue) ? *Défaut : oui, avec motif obligatoire, durée limitée et trace d'audit — sans cette soupape, le personnel cessera de faire confiance au moteur.*

### `Q-09` — Contenu de la vérification publique d'un certificat — **Phase 5**

*Référence : `G-04`. Cahier des charges §14.1, §6.4.*

**Défaut recommandé** — réponse anonyme : statut (`VALID` / `REVOKED` / `EXPIRED`), type de document, nom de l'établissement émetteur, date d'émission, année scolaire et classe, prénom + initiale du nom. Jamais : date de naissance complète, adresse, Person ID, autres inscriptions.

**Questions :** (a) Ce niveau est-il suffisant pour qu'un tiers (employeur, autre école, administration) fasse confiance au document ? (b) Le jeton expire-t-il ? *Défaut : non — un certificat doit rester vérifiable longtemps — mais il est révocable et limité en débit.* (c) Faut-il un second facteur de vérification (saisir la date de naissance pour obtenir plus de détails) ? *Défaut recommandé : oui, en option, pour un vérificateur qui détient déjà le document papier — cela permet une divulgation graduée sans exposer les données au scan d'un QR volé.*

### `Q-10` — Qui peut attester un document externe ? — **Phase 5**

*Référence : `G-06`. Cahier des charges §6.5, §14.3.*

**Défaut recommandé** — quatre statuts (`unverified`, `attested_by_school`, `verified_by_issuer`, `disputed`), où `attested_by_school` signifie qu'un agent identifié d'un établissement FANABE a vu l'original papier. Sans cet état intermédiaire, tout document venant d'une école non-FANABE reste éternellement « non vérifié », ce qui vide le cas d'usage principal du §14.3 de son intérêt.

**Question :** un établissement peut-il attester un document **qu'il n'a pas émis** (l'école d'accueil atteste le bulletin de l'école précédente) ? *Défaut : oui, c'est le cas d'usage central, et l'attestation est nominative et engage l'agent qui la pose.*

### `Q-14` — Année scolaire et périodes — **Phase 2**

Absent du cahier des charges. Défauts : année scolaire nommée `2026-2027`, découpée en **trimestres** (3 périodes) ; inscription possible en cours d'année ; transfert en cours d'année avec statut `transferred_out` (jamais de suppression) ; redoublement = nouvelle inscription sur le même niveau. **Question :** trimestres ou semestres, et est-ce paramétrable par établissement ? *Défaut : paramétrable, trimestres par défaut.*

### `Q-15` — Attributs minimaux de création d'une identité — **Phase 1**

*Explicitement listé par le cahier des charges (§27).*

**Défaut recommandé** : nom, prénom, date de naissance (approximative acceptée et **marquée comme telle** — les actes de naissance ne sont pas toujours disponibles), sexe. Facultatifs : lieu de naissance, téléphone, email, photo. Aucun document d'identité exigé pour créer une identité (l'exiger exclurait les familles à couvrir).

**Question :** faut-il un mécanisme de **fusion** de doublons (deux `Person` créés pour la même personne réelle) ? *Défaut : oui, dès la phase 1 — les doublons sont inévitables avec des attributs faibles, et fusionner après coup sans mécanisme prévu est très coûteux. Fusion réservée au Platform Admin, réversible, tracée.*

---

## Bloc D — Ouvertes (défauts réversibles)

### `Q-16` — Langues de l'interface

*Défaut : français par défaut, chaînes externalisées dès le premier écran, malgache ajoutable sans reprise de code. Aucun texte en dur.* Le malgache est-il attendu au pilote ?

### `Q-17` — Marque, domaine et vérification juridique

*Cahier des charges §27 et §2.1 : « à valider juridiquement : disponibilité de marque et domaine ».* Le brief impose `verify.fanabe.mg`. Le domaine est-il acquis ? La marque FANABE est-elle vérifiée ? Cela n'empêche pas de développer, mais l'URL de vérification apparaît **imprimée sur des documents durables** : la changer après émission invaliderait des certificats en circulation. Il est prudent de faire pointer le QR vers un domaine maîtrisé et pérenne dès le premier certificat émis.

### `Q-18` — Écoles pilotes

*Cahier des charges §27 et Phase 0 §22 (« 5-10 écoles pilotes »).* Y a-t-il déjà des établissements identifiés ? Leur réalité (nombre d'élèves, structure des frais, outils existants, calendrier de facturation) devrait orienter le jeu de données de démonstration, aujourd'hui fixé arbitrairement par le brief (3 écoles, 100 élèves).

### `Q-19` — Niveaux scolaires et référentiel malgache

Faut-il un référentiel de niveaux préchargé (Maternelle, T1-T5/CP-CM2, 6ᵉ-3ᵉ, Seconde-Terminale, avec séries) ou chaque école définit-elle librement ses niveaux ? *Défaut : référentiel fourni comme modèle, librement modifiable par l'école — imposer une nomenclature nationale rigide échouerait sur la diversité du privé (systèmes français, malgache, bilingue).*

### `Q-20` — Réseaux d'établissements

*Persona §5, offre « Réseau » §19.1, P2 en §20.* La hiérarchie `Organization` (réseau → écoles) est-elle à **modéliser** dès la phase 0 même si elle n'est implémentée qu'en P2 ? *Défaut : oui, modéliser (un `school_id` orphelin de hiérarchie est difficile à rattacher après coup), ne pas implémenter la vue consolidée.*

---

## Récapitulatif

| Question | Sujet | Priorité |
|---|---|---|
| `Q-01` | Frontière plateforme / tenant | **BLOQUANTE** |
| `Q-02` | Authentification | **BLOQUANTE** |
| `Q-03` | Encaissement des paiements | **BLOQUANTE** |
| `Q-04` | Portée du Consent Center | **BLOQUANTE** |
| `Q-06` | Cible de facturation | **BLOQUANTE** |
| `Q-13` | Auteur des règles de workflow | **BLOQUANTE** (phase 3) |
| `Q-05` | Rétention et droits | Phase 1 — avis juridique |
| `Q-07` | Périmètre hors ligne | Phase 0 |
| `Q-12` | Localisation des données | Phase 0 |
| `Q-08` | Seuils de risque | Phase 3 |
| `Q-09` | Vérification publique | Phase 5 |
| `Q-10` | Attestation des documents externes | Phase 5 |
| `Q-11` | Numérotation des reçus | Phase 2 — avis comptable |
| `Q-14` | Année scolaire et périodes | Phase 2 |
| `Q-15` | Attributs minimaux d'identité | Phase 1 |
| `Q-16` à `Q-20` | Langues, marque, pilotes, niveaux, réseaux | Ouvertes |
