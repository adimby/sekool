# FANABE — Décisions actées

> **Statut : validé le 21 août 2026.**
> Cette page fige les réponses à [`open-questions.md`](./open-questions.md) et ouvre l'implémentation (étape 9).
> En cas de conflit avec une formulation antérieure des documents de conception, **ce fichier l'emporte**.

## Synthèse

| Question | Décision | Écart par rapport au défaut |
|---|---|---|
| `Q-01` | Modèle à deux plans **confirmé**, avec un flux d'inscription spécifique | **Oui** — contacts parent non visibles sans consentement ; lien parent = consentement ; transfert à double validation |
| `Q-02` | Option A (email + mot de passe, TOTP pour les rôles à privilèges, code d'invitation parent) | **Oui** — **pas de SMS**, même optionnel |
| `Q-03` | Option A (FANABE enregistre, n'encaisse pas) ; School Kit payé chez le fournisseur | Non (défaut retenu, précision School Kit confirmée) |
| `Q-04` à `Q-20` | Défauts recommandés **retenus** | Non |

---

## `Q-01` — Frontière plateforme / tenant et flux d'inscription

Le découpage à deux plans est **confirmé** (`D-01`). Ce qui change, ce sont les trois questions de visibilité et le **parcours d'inscription**, désormais normatif.

### Visibilité

| # | Question | Décision |
|---|---|---|
| 1 | L'école voit-elle le téléphone du parent sans consentement ? | **Non.** Aucune coordonnée de contact du plan plateforme n'est exposée tant qu'un lien n'est pas établi. |
| 2 | L'école voit-elle l'historique / les autres écoles de l'élève ? | **Non**, hors consentement explicite. Confirmé. |
| 3 | L'école voit-elle la fratrie ailleurs ? | **Non**, hors consentement. Confirmé. |

Conséquence sur `R2` : le plafond d'attributs civils **n'inclut pas** téléphone ni email. Ceux-ci ne deviennent visibles qu'après établissement d'un lien (rédemption d'un lien parent, ou confirmation d'une demande initiée par identifiant public).

### Parcours d'inscription — désormais la source de vérité

```
L'école inscrit un élève.

1. Le parent a-t-il déjà un compte FANABE ?

   OUI ────────────────────────────────────────────────────────────
   L'école demande :
     • le FANABE Person ID du parent,  OU
     • un lien généré par le parent, qui FAIT OFFICE DE CONSENTEMENT.

   • Lien parent  → consentement déjà donné. Le lien est rédimé.
                    Relation + consentement créés. L'école voit alors
                    les attributs autorisés de ce parent (y compris contacts).
   • Identifiant  → ce n'est PAS un consentement (l'ID n'est pas un secret).
                    Demande de rattachement créée, réponse uniforme
                    (aucune confirmation d'existence). Le parent doit
                    confirmer. Tant que ce n'est pas fait, l'école
                    ne voit aucune coordonnée.

   NON ────────────────────────────────────────────────────────────
   L'école crée le compte parent (état civil + contacts saisis par l'école,
   ce sont des données qu'elle produit, pas une consultation du plan
   plateforme). Puis enchaîne sur l'élève.

2. L'élève existe-t-il déjà (identité FANABE) ?

   OUI ────────────────────────────────────────────────────────────
   • Validation du parent pour intégrer l'élève dans CETTE école.
   • SI l'élève a une inscription active dans une autre école FANABE :
        validation de l'école d'origine pour le DÉTACHER de son effectif.
        Les deux validations sont obligatoires (transfert).
   • SINON (ancien élève, période externe, pas d'inscription active) :
        validation du parent uniquement.
   • Puis création de l'inscription dans l'école d'accueil.

   NON ────────────────────────────────────────────────────────────
   L'école crée l'identité élève, la relation parent_of, et l'inscription.
```

### Règles qui en découlent

1. **Une seule inscription active à la fois** dans le réseau FANABE (MVP). Un élève qui rejoint une école FANABE quitte l'école FANABE précédente. Les enfants *différents* d'un même parent peuvent être dans des écoles différentes ; ce n'est pas le même cas.
2. **Le lien généré par le parent est un consentement**, pas un identifiant. Jeton à haute entropie, stocké haché, durée de vie courte, portée explicite (quels enfants, quelles catégories de données, éventuellement quelle école). Une fois rédimé, il est consommé.
3. **L'identifiant public n'est jamais un consentement.** Le saisir pointe vers une personne ; il n'autorise rien. C'est la confirmation du parent qui autorise.
4. **Le transfert est un objet de premier plan** (`EnrollmentTransfer`) : école d'accueil, école d'origine, parent, statuts d'approbation indépendants, historisé. Un refus de l'école d'origine laisse l'élève dans son effectif.
5. **Créer n'est pas consulter.** Quand le parent n'a pas de compte, l'école saisit des contacts pour *créer* une identité. Ce n'est pas une lecture du plan plateforme.

### Voie exceptionnelle

La voie `staff_attested` (agent qui a vu un justificatif papier) reste disponible pour les familles sans accès numérique qui *ont* déjà un compte mais ne peuvent produire ni identifiant ni lien. Elle est nominative, journalisée, dénombrée, et n'ouvre **aucun** historique d'une autre école (`R3`).

---

## `Q-02` — Authentification, sans SMS

Option A **retenue**, avec une restriction supplémentaire :

| Public | Mécanisme |
|---|---|
| Personnel | Email + mot de passe (Argon2id). TOTP **obligatoire** pour `platform_admin`, `school_owner`, `school_admin`, `accountant`. |
| Parent | Code d'invitation émis par l'école (papier ou in-app), puis mot de passe. |
| Élève | Compte **en lecture seule** possible (démo Fanja) pour l’espace « ma scolarité ». L’inscription d’une famille **n’ouvre pas** de compte élève automatiquement. |
| Sessions | 8 h personnel (renouvellement glissant), 30 jours parent. |

**Pas de SMS**, y compris comme second facteur optionnel. Conséquence sur la communication : le MVP s'appuie sur **l'application, l'email et le papier**. Le port `SmsGateway` reste défini (Null) pour ne pas fermer une évolution, mais aucun parcours du MVP ne l'exige. WhatsApp idem.

---

## `Q-03` — Paiements

- FANABE **enregistre** les paiements encaissés par l'école (caisse, virement, mobile money reçu directement). Aucun flux financier ne transite par FANABE. Port `PaymentGateway` = `ManualPaymentRecorder`.
- **School Kit** : le parent paie **chez le fournisseur**. FANABE transmet la commande et suit le statut. Aucun encaissement, aucune commission collectée par FANABE au MVP (la commission reste un *paramètre* de contrat, pas un flux).

---

## Autres questions — défauts retenus

| Question | Décision retenue |
|---|---|
| `Q-04` | Deux régimes de consentement (« Accès de mon école » / « Partages que j'autorise ») ; granularité par catégorie + par document familial ; durée 12 mois ; bascule des droits à 18 ans |
| `Q-05` | Table de rétention proposée ; obligation de conservation comptable prime sur l'effacement ; avis juridique à instruire avant le pilote réel |
| `Q-06` | Facture par élève et par année ; `PayerAccount` par (école, famille, responsable) ; remises au MVP avec motif obligatoire |
| `Q-07` | Hors ligne = lecture + saisie de présence uniquement |
| `Q-08` | Quatre paliers et seuils proposés ; recalcul à l'événement + nocturne ; dérogation manuelle motivée et temporaire |
| `Q-09` | Divulgation minimale + option date de naissance pour le nom complet ; jeton non expirant, révocable, limité en débit |
| `Q-10` | Quatre statuts de document ; un établissement peut attester un document qu'il n'a pas émis |
| `Q-11` | Numérotation de reçus sans trou, par (école, année), annulation par avoir |
| `Q-12` | Région européenne ou Maurice pour le pilote ; abstraction de stockage pour migration |
| `Q-13` | Templates plateforme paramétrés par l'école ; plafond et heures de silence bornés par la plateforme |
| `Q-14` | Année scolaire paramétrable, trimestres par défaut |
| `Q-15` | Attributs minimaux + `birth_date_precision` ; fusion de doublons dès la phase 1 |
| `Q-16` | Français par défaut, chaînes externalisées |
| `Q-17` | Marque FANABE ; domaine de vérification à sécuriser avant le premier certificat (phase 5) |
| `Q-18` | Jeu de démonstration du brief (3 écoles, 100 élèves) en attendant les pilotes réels |
| `Q-19` | Référentiel de niveaux fourni, modifiable par l'école |
| `Q-20` | Hiérarchie `Organization` modélisée dès la phase 0, vue consolidée non implémentée |

---

## Décisions d'architecture désormais actées

Toutes les `D-01` à `D-17` de [`architecture.md`](./architecture.md#17-journal-de-décisions) passent au statut **Acté**, avec les ajouts :

| # | Décision |
|---|---|
| `D-18` | Parcours d'inscription `Q-01` ci-dessus — source de vérité |
| `D-19` | Une inscription active à la fois dans le réseau FANABE ; transfert = double validation parent + école d'origine |
| `D-20` | Aucun SMS dans le MVP (ni auth, ni canal obligatoire) |
| `D-21` | Paiement School Kit chez le fournisseur |
| `D-22` | Lien parent = consentement consommable ; identifiant public ≠ consentement |
