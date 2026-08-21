# FANABE — Audit de cohérence du cahier des charges

> **Statut : proposition — en attente de validation.**
> Source de vérité auditée : `FANABE_Cahier_des_charges_SchoolOS_Madagascar.docx` (v0.1, cadrage fonctionnel, 27 sections).
> Ce document correspond à l'**étape 2** de la séquence bloquante. Il ne contient aucune décision définitive : chaque contradiction est accompagnée d'une **résolution proposée** qui doit être validée.

## Convention de lecture

| Code | Signification |
|---|---|
| `A-nn` | Constat d'audit (contradiction, tension ou risque de conception) |
| `G-nn` | Lacune (sujet nécessaire au produit, absent du cahier des charges) |
| `Q-nn` | Renvoi vers une question ouverte détaillée dans [`open-questions.md`](./open-questions.md) |
| Sévérité **Bloquante** | Doit être tranchée avant la première ligne de code de la phase concernée |
| Sévérité **Structurante** | N'empêche pas de démarrer mais coûte cher à corriger après |
| Sévérité **Mineure** | Correction documentaire ou de confort |

---

## 1. Verdict global

Le cahier des charges est **globalement cohérent, inhabituellement mûr sur les aspects éthiques et réglementaires**, et son positionnement produit est défendable. Les cinq moteurs (identité portable, recouvrement, relation école-famille, intelligence opérationnelle, School Kit) sont articulés autour d'une thèse unique et vérifiable : *séparer l'identité permanente de la personne des relations temporaires avec les établissements*. C'est cette thèse qui porte la différenciation, et elle est techniquement réalisable.

Trois qualités notables, à préserver explicitement dans l'implémentation :

1. **Les indices sont définis par leur méthode, pas par leur résultat** (§9.1 : événements objectifs, pas d'étoiles). C'est ce qui rend le produit auditable et conforme à la loi 2014-038.
2. **Le refus du verrouillage de la donnée** (§19.3) est cohérent avec le modèle économique (§19.1 : l'école paie, la famille non). Ce n'est pas de la générosité, c'est ce qui rend l'identité portable crédible.
3. **Le hors-périmètre est explicite** (§25), ce qui est rare et évite la dérive vers l'ERP.

Les problèmes réels ne sont donc pas des erreurs de raisonnement mais des **points de sous-spécification à des endroits où le coût d'une erreur est élevé** : la frontière entre le plan global (identité) et le plan cloisonné (dossiers), le statut des paiements en ligne, et la gouvernance des actions automatiques.

Un seul point mérite le qualificatif de **tension structurelle non résolue par le document** : `A-01`.

---

## 2. Tensions structurelles

### `A-01` — Identité portable **contre** cloisonnement multi-tenant strict — *Bloquante*

C'est la contradiction centrale, et elle n'est pas un défaut du cahier des charges : c'est le problème que le produit existe pour résoudre. Mais elle est formulée deux fois de manière apparemment incompatible.

- §6.1 / §6.5 / §19.2 : une personne possède **une** identité, réutilisable d'un établissement à l'autre, conservée même hors du réseau FANABE.
- §17 et brief de mission §2 : **« isolation stricte des données par établissement à TOUS les niveaux »**, jamais un masquage frontend.

Si tout est cloisonné par établissement, `Person` ne peut pas exister comme entité partagée. Si `Person` est global, il existe par construction une table lisible hors tenant, et le mot « strict » devient faux.

**Résolution proposée — modèle à deux plans.** L'isolation ne porte pas sur *les personnes* mais sur *les dossiers*.

| Plan | Contenu | Portée | Règle d'accès |
|---|---|---|---|
| **Plan Plateforme** (sans tenant) | `Person`, identifiant public, `Relationship`, `Consent`, comptes d'authentification, documents détenus par la famille | Global | Une école n'y accède que via un **lien actif** qu'elle possède (inscription, ou relation avec un élève qu'elle inscrit), et seulement sur un **jeu d'attributs civils minimal** |
| **Plan Établissement** (tenant) | `Enrollment`, présence, notes, factures, paiements, alertes, communications, kits | `school_id` non nul, obligatoire | Isolation stricte à tous les niveaux (requête, autorisation, API, stockage), y compris en SQL brut |

**Invariant à tester, qui résout la contradiction :** *rattacher un `Person` ne donne, à lui seul, aucun accès aux données produites par un autre établissement.* L'accès inter-établissement passe exclusivement par un `Consent` actif, dont la portée et la finalité sont explicites (§6.6). Voir [`identity-model.md`](./identity-model.md) et [`security-model.md`](./security-model.md).

Conséquence à assumer : la phrase « isolation stricte à tous les niveaux » doit être reformulée en **« isolation stricte de tout dossier scolaire, financier et pédagogique ; l'identité civile est un référentiel partagé dont l'accès est gouverné par lien et par consentement »**. Sans cette reformulation, l'une des deux exigences serait mécaniquement violée à la première ligne de code.

---

### `A-02` — Le sens du mot « consentement » diffère selon le côté de la frontière — *Bloquante*

§6.6 décrit un Consent Center où le parent contrôle « qui peut voir quelles catégories de données ». Lu littéralement et combiné à §17, cela signifierait qu'une école a besoin du consentement du parent pour consulter les notes qu'elle a elle-même produites — ce qui est ingérable opérationnellement et juridiquement inexact (l'école a une finalité légitime propre).

**Résolution proposée.** Deux régimes distincts, nommés différemment dans l'UI :

- **Finalité légitime intra-établissement** : l'école accède aux données qu'elle produit dans le cadre de la scolarité, sans consentement, mais **avec journalisation** des consultations sensibles. Le parent voit ces accès dans un onglet « Historique des accès » (transparence), sans pouvoir les révoquer.
- **Partage inter-établissement et données détenues par la famille** : soumis à un `Consent` explicite, avec portée, finalité, durée, révocabilité (§6.6).

Cette distinction doit être visible dans le produit, sinon le Consent Center promet un contrôle qu'il ne peut pas tenir. Voir `Q-04`.

---

### `A-03` — Révocation présentée comme un droit, mais conditionnée — *Structurante*

§6.6 : « Possibilité de retirer un partage **lorsque le cadre juridique et opérationnel le permet** ». §17 : « procédures de suppression, correction et export ». §17 exige aussi un journal d'audit et le brief de mission impose « favoriser les événements et l'historique plutôt que d'écraser les valeurs précédentes ».

Ces trois exigences entrent en conflit : on ne peut pas simultanément garantir un historique immuable, un droit à l'effacement, et une conservation légale par l'établissement.

**Résolution proposée.** Hiérarchie explicite et documentée dans le produit :

1. La révocation d'un consentement est **immédiate et absolue pour les lectures futures**.
2. Elle n'efface pas les données que l'établissement a l'obligation de conserver ; l'UI le dit honnêtement au lieu de le laisser croire.
3. Elle ne réécrit jamais le journal d'audit (l'audit est append-only : il est la garantie du parent, pas une donnée personnelle exploitée).
4. L'effacement des PII se fait par **suppression ciblée + conservation d'événements anonymisés** (les indices restent calculables historiquement sans identifier la personne).
5. Ce qui a déjà été exporté ne peut pas être rappelé ; l'audit doit le montrer (« consulté par X le … ») plutôt que de le taire.

Nécessite un arbitrage juridique : `Q-05`.

---

### `A-04` — « Aucune sanction automatique » **contre** relances automatiques — *Structurante*

§9.2 et §18 : les indices ne doivent pas fonder automatiquement une décision, conformément à la loi 2014-038. Mais §7.3 / §8.2 et le brief de mission demandent un moteur de workflow qui déclenche des **relances automatiques** sur la base d'un niveau de risque.

Une relance est-elle une « décision impliquant une appréciation sur un comportement humain » ? Selon la formulation, oui.

**Résolution proposée — classification des actions automatiques, appliquée dans le code :**

| Classe | Exemples | Automatisation |
|---|---|---|
| **Informationnelle** | rappel d'échéance, notification d'absence, demande de document manquant | Automatisable. Contenu neutre et factuel, sans mention d'un score ni d'un niveau de risque |
| **Priorisation interne** | ordre de la file d'actions de la direction, segmentation des créances | Automatisable, visible du personnel uniquement, jamais du parent |
| **Restrictive** | blocage d'inscription, refus de document, exclusion, suspension d'un service | **Jamais automatique.** Nécessite un acteur humain identifié, un motif saisi, et une trace d'audit |

Invariant testable associé : **aucune politique d'autorisation ne lit jamais un score de fiabilité ou un niveau de risque.** Un test automatisé doit le vérifier statiquement — c'est la traduction en code de l'exigence §18.

Corollaire : le contenu des messages sortants ne doit jamais exposer un indice au parent. Le score sert à décider *qui contacter en premier*, jamais à *justifier le message*.

---

### `A-05` — Prévision de trésorerie sans « promesse de paiement » — *Structurante*

§8.1 exclut explicitement le concept de promesse de paiement (le brief de mission le confirme), mais §8.2 exige une « prévision hebdomadaire » et §7.3 une « prévision de collecte ». Sans engagement déclaré par la famille, la prévision doit être **inférée**, et une inférence non explicable retomberait sous le coup de §18.

**Résolution proposée.** Prévision déterministe et explicable, sans apprentissage automatique :

```
collecte_attendue(semaine) = Σ  montant_échéance × p(paiement_à_temps)
                          échéances ouvertes de la semaine

p = (paiements_à_temps_famille + k × taux_de_base_école)
    ────────────────────────────────────────────────────
        (échéances_famille + k)
```

Moyenne bayésienne empirique (`k` = force de lissage, ~5 échéances) : une famille sans historique hérite du taux de l'école, une famille avec historique dérive vers son propre comportement. Chaque prévision est décomposable en la liste des échéances qui la composent, avec le `p` de chacune et sa justification. Aucune boîte noire, `k` et la fenêtre glissante sont versionnés comme paramètres du calculateur.

---

### `A-06` — « Marketplace » désigne trois choses différentes — *Mineure (mais source de dérive de périmètre)*

§7.9 intitule une brique « Marketplace Fournitures » ; §13.1 affirme que « School Kit n'est pas une marketplace générale » ; §25 exclut « une marketplace ouverte sans contrôle des fournisseurs » ; §20 place « Marketplace fournisseur avancée » en P2 ; le brief de mission tranche : « Pas de marketplace générale pour l'instant ».

**Résolution proposée.** Abandonner le terme « marketplace » dans le code et l'UI. Un seul module `SchoolKit`, avec un modèle d'achat **contextualisé** : `School` définit les besoins → `Supplier` (partenaire agréé, pas ouvert) associe les références → FANABE assemble le panier par enfant et par classe → commission fournisseur configurable. La chaîne `School → FANABE → Supplier → Parent` du brief est retenue. Aucune notion de vendeur libre, de recherche produit transverse ou de mise en concurrence.

---

## 3. Divergences entre le cahier des charges et le brief de mission

Le brief opérationnalise le document mais s'en écarte sur quatre points. Le document faisant foi, ces écarts nécessitent une décision explicite.

### `A-07` — Découpage modulaire : deux listes non superposables — *Bloquante (structure du dépôt)*

§15.2 liste 11 modules ; le brief de mission en liste 14. Ils ne coïncident pas.

| §15.2 (cahier des charges) | Brief de mission | Traitement proposé |
|---|---|---|
| Identity & Access | `Identity/` | Fusionné dans `Identity/` |
| Organizations / Tenants | `School/` | `School/` porte l'établissement ; la mécanique de tenant devient transverse (`Platform/Tenancy`) |
| Families & Relationships | `Family/` | Aligné |
| Students & Enrollments | `Student/` + `Enrollment/` | Aligné (séparation acceptée : l'élève est un rôle, l'inscription est une relation datée) |
| **Attendance & Grades** | *absent* | **Ajout de `Academic/`** — indispensable au cockpit (§11), aux workflows d'absence et à l'Early Warning (§10) |
| Collections & Payments | `Finance/` + `Collection/` | Aligné (`Finance/` = référentiel et faits ; `Collection/` = intelligence) |
| Reliability & Risk Engine | `Reliability/` | Aligné |
| Communication | `Communication/` | Aligné |
| Documents & Verifiable Credentials | `Documents/` + `Certificate/` | Aligné |
| School Kit / Marketplace | `SchoolKit/` | Aligné, « Marketplace » retiré (`A-06`) |
| **Audit & Compliance** | *absent* | **Devient transverse** `Platform/Audit` (un module métier ne peut pas être juge de ses propres accès) |
| *absent* | `Workflow/`, `Analytics/` | Conservés (le brief est plus juste ici : ce sont bien des moteurs distincts) |
| *absent* | *absent* | **Ajout de `Consent/`** — le Consent Center (§6.6) est une surface produit de premier plan, il ne peut pas être un détail d'`Identity/` |

Trois ajouts sont donc proposés (`Academic/`, `Consent/`, `Platform/`) : ils comblent des lacunes du brief, pas du cahier des charges. Voir `Q-01`.

### `A-08` — Nombre de niveaux de risque non spécifié par le document — *Structurante*

Le brief impose 4 niveaux (Faible / Moyen / Élevé / Critique). Le cahier des charges ne les mentionne pas ; §8.1 liste seulement des signaux. Il manque donc les seuils, les poids, la fréquence de recalcul et le droit de correction manuelle. Le brief est retenu (4 niveaux), le reste est à définir : `Q-08`.

### `A-09` — Le Reliability Index est P0 au cahier des charges, phase 4 au brief — *Structurante*

§20 classe « Reliability Index familial et relationnel » en **P0**. La roadmap du brief (§6) le place en **phase 4**, après Collection Intelligence, avec interdiction de sauter des étapes.

**Résolution proposée.** Respecter l'ordre du brief (phase 4) **mais émettre les `TrustEvent` dès la phase 1**. Les événements sont la partie coûteuse et irrattrapable : un indice non calculé se calcule plus tard, un événement non capté est perdu à jamais. La phase 4 devient alors un calculateur et une UI au-dessus d'un historique déjà complet, et le P0 du cahier des charges est honoré sur le fond (les faits sont là) sans casser la séquence.

### `A-10` — Rôles applicatifs et rôles relationnels confondus — *Structurante*

Le brief donne une liste RBAC : `Platform Admin, School Owner, School Admin, Principal/Direction, Teacher, Accountant, Staff, Parent/Guardian, Supplier`. Le cahier des charges parle de `Role` (§16 : `Student, Parent, Guardian, FinancialContact, Staff`) et §6.7 ajoute « personne autorisée à récupérer l'enfant » avec un « droit logistique spécifique, sans accès pédagogique ».

Ce sont **deux concepts différents** qu'il serait coûteux de fusionner :

- **Rôle applicatif** (RBAC, portée établissement) : ce que l'utilisateur peut faire dans le logiciel.
- **Rôle relationnel** (`Relationship`, portée personne) : ce qu'une personne est vis-à-vis d'une autre — `ParentOf`, `GuardianOf`, `FinancialContactFor`, `PickupAuthorizedFor` — chacun portant ses propres portées de données.

Un « responsable financier » (§6.7) qui n'accède qu'aux paiements est un rôle **relationnel**, pas un rôle RBAC : il doit rester exprimable sans créer un rôle applicatif par cas. Modélisation proposée dans [`domain-model.md`](./domain-model.md).

Manque également un rôle **Réseau scolaire** (persona §5, offre « Réseau » §19.1, P2 en §20) : à modéliser dès maintenant (hiérarchie `Organization`), à ne pas implémenter avant la phase correspondante.

### `A-11` — Authentification : OIDC/MFA **contre** interdiction de dépendre d'une API payante — *Bloquante*

§15.1 recommande « OIDC/OAuth2 + MFA selon rôle ». Le brief impose que « le cœur du produit ne doit jamais dépendre d'une API externe payante pour fonctionner ». Or l'authentification d'un parent malgache passe naturellement par un OTP SMS — c'est-à-dire une API payante, sur le chemin critique de connexion.

**Résolution proposée.** Authentification première partie, sans dépendance externe obligatoire :

- **Personnel d'établissement** : email + mot de passe, TOTP obligatoire pour les rôles à privilèges (School Owner, School Admin, Accountant, Platform Admin). TOTP est gratuit et hors ligne.
- **Parent** : rattachement par **code d'invitation** émis par l'école (remis sur papier ou par message), puis mot de passe ou code PIN long. OTP SMS **optionnel**, activé seulement si une passerelle est configurée, et jamais unique voie d'accès.
- OIDC est traité comme une **évolution** derrière une interface d'authentification, pas comme le socle du MVP. Le déployer d'emblée serait payer un coût d'interopérabilité avant d'avoir un seul partenaire avec qui interopérer.

Voir `Q-02`.

---

## 4. Lacunes du cahier des charges

Sujets nécessaires au produit et absents du document. Aucun n'est une critique du cadrage — ils relèvent du niveau de détail suivant.

### `G-01` — Paiements en ligne : le silence le plus coûteux — *Bloquante*

Le document parle abondamment d'« encaissements » (§11), de paiements, de retards, de School Kit où « le paiement suit le modèle du partenaire » (§13.2), mais **ne dit jamais si FANABE encaisse de l'argent**. C'est la lacune la plus lourde du cadrage : intégrer Mvola / Orange Money / Airtel Money ou un PSP change le périmètre réglementaire, la surface d'attaque, la responsabilité financière et le rythme du projet.

**Résolution proposée pour le MVP** : FANABE **enregistre** des paiements encaissés hors ligne (caisse, virement, mobile money reçu directement par l'école) et n'en collecte aucun. Un port `PaymentGateway` est défini mais sans implémentation payante. Cela suffit intégralement aux trois démonstrations exigées (brief §7). Voir `Q-03`.

### `G-02` — Cible de facturation non tranchée : élève ou famille ? — *Bloquante*

§16 définit `PaymentAccount` comme « situation financière **d'une famille / d'un élève** » — l'ambiguïté est dans le document lui-même. Or le choix détermine tout le module `Finance` : deux enfants dans la même école, une remise fratrie, un parent séparé responsable d'un seul enfant, un « responsable financier » (§6.7) distinct du parent.

**Résolution proposée** : facturation **par élève et par année scolaire** (l'obligation naît de la scolarité d'un enfant), payée depuis un **compte payeur par (établissement, famille)** qui consolide et porte le solde. Cela permet un paiement groupé sans perdre l'imputation par enfant, et rend les remises fratrie exprimables. Voir `Q-06`.

### `G-03` — Périmètre de l'usage hors ligne non borné — *Structurante*

§3.3 exige de « supporter des usages offline ou différés » et §24 répond « PWA/offline/synchronisation différée ». Mais lecture hors ligne et **écriture** hors ligne diffèrent d'un ordre de grandeur en complexité (résolution de conflits, idempotence, ordre causal).

**Résolution proposée** : MVP = lecture hors ligne (coquille applicative précachée + dernières données consultées) + **file d'actions sortantes limitée à un seul cas d'écriture : la saisie de présence**, qui est le seul geste réellement effectué dans une salle sans réseau. Toute autre écriture exige la connexion. Voir `Q-07`.

### `G-04` — Aucune spécification de la vérification publique d'un certificat — *Structurante*

§14.1 exige un QR de vérification et §6.4 interdit d'embarquer des données personnelles en clair. Mais le document ne dit pas **ce qu'un vérificateur anonyme voit**. Un endpoint public renvoyant nom + date de naissance + établissement serait une fuite de données à grande échelle, exploitable par énumération.

**Résolution proposée** : divulgation minimale — statut (`VALID` / `REVOKED` / `EXPIRED`), type de document, établissement émetteur, date d'émission, année scolaire et classe, et **identité masquée** (prénom + initiale du nom). Jamais de date de naissance complète, d'adresse, ni de Person ID. Jeton à haute entropie stocké haché, limitation de débit par IP et par jeton, et **chaque vérification est journalisée**. Voir `Q-09`.

### `G-05` — Le hash du document se contredit avec un PDF régénéré — *Structurante*

§14.1 exige un « Document Hash » pour « détecter une modification du document ». Si le PDF est régénéré à chaque téléchargement, son empreinte change (horodatage interne, ordre des objets, police embarquée) et la vérification échoue systématiquement.

**Résolution** : le certificat est rendu **une seule fois**, l'artefact est stocké immuable en S3, et c'est **cet artefact** qui est haché. Toute réémission crée une **nouvelle version** avec son propre identifiant, la précédente étant marquée comme remplacée. Contrainte d'implémentation, pas d'arbitrage produit.

### `G-06` — Un document externe non vérifiable ne l'est pas seulement « pas encore » — *Structurante*

§6.5 prévoit : document importé par le parent = « non vérifié », puis « l'ancien établissement vérifie » = « vérifié ». Mais si l'ancien établissement **n'est pas** sur FANABE — le cas exact que le produit veut couvrir (§6.5, §14.3) — le document ne peut *jamais* atteindre l'état vérifié. Le modèle binaire enferme le cas d'usage principal dans un état d'échec permanent.

**Résolution proposée** — quatre statuts distinguant *qui* atteste et *avec quelle force* :

| Statut | Signification |
|---|---|
| `unverified` | Importé, aucune vérification |
| `attested_by_school` | Un établissement FANABE a vu l'original papier et l'atteste (traçabilité : quel agent, quand) — **valeur intermédiaire honnête** |
| `verified_by_issuer` | L'établissement émetteur lui-même, présent sur FANABE, confirme |
| `disputed` | Contesté, avec motif |

L'UI ne doit jamais présenter `attested_by_school` comme équivalent à `verified_by_issuer`. Voir `Q-10`.

### `G-07` — Mesure de joignabilité biaisée par les canaux muets — *Structurante*

§12 veut enregistrer les états d'envoi / livraison / lecture « lorsque le canal le permet » et en nourrir un indicateur de joignabilité, puis §8.1 utilise « WhatsApp lu, SMS non lu » comme signal de fiabilité. Le piège est direct : une famille joignable uniquement par un canal qui ne renvoie pas d'accusé (papier, SMS sans rapport de livraison, appel vocal) apparaîtrait comme **moins fiable** qu'une famille équipée d'un smartphone. Le produit pénaliserait la pauvreté, en contradiction avec §9.2 et §18.

**Résolution proposée** : le statut `unknown` est un état de première classe, distinct de `not_read`. Un événement de communication dont le canal ne peut structurellement pas rapporter la lecture est **exclu du calcul** de joignabilité, il ne le dégrade pas. Une famille sans canal instrumenté n'a pas d'indice de joignabilité — et l'absence d'indice ne vaut jamais mauvais indice. À rendre testable explicitement.

### `G-08` — Garde-fous du moteur de workflow absents — *Bloquante avant la phase 3*

Le brief demande un moteur `Event → Rule → Action` configurable par établissement. Le document n'en parle pas. Sans garde-fous, un moteur de règles configurable envoie 4 000 SMS une nuit, ou boucle sur ses propres événements.

**Garde-fous proposés, non négociables** : templates fournis par la plateforme et paramétrés par l'école (pas de scripting libre en MVP) ; clé d'idempotence par (règle, sujet, fenêtre) ; fenêtre de déduplication ; plafond quotidien d'actions par tenant ; heures de silence ; mode simulation obligatoire avant activation ; coupe-circuit global ; interdiction pour une action de produire un événement déclencheur de sa propre règle.

### `G-09` — Monnaie, fuseau et langue non spécifiés — *Mineure, mais irréversible si mal fait*

Absents du document. Décisions proposées : montants en **entiers d'Ariary** (`MGA`, exposant 0, jamais de flottant, `bigint` — un cockpit affiche « 8,7 M Ar », les agrégats annuels d'un réseau dépassent le milliard) ; stockage des instants en UTC, affichage en `Indian/Antananarivo` (UTC+3, sans heure d'été) ; interface en **français** par défaut, chaînes externalisées dès le premier écran pour permettre le malgache sans reprise (aucun texte en dur) ; téléphones normalisés E.164 `+261`.

### `G-10` — Numérotation légale des reçus non traitée — *Structurante*

Le document parle de paiements mais pas de pièces comptables. Beaucoup de juridictions exigent une numérotation séquentielle sans trou par établissement, et §25 précise seulement que FANABE ne remplace pas le système comptable — pas qu'il n'émet aucun reçu. Un reçu émis avec des trous de séquence est un problème pour l'école. Nécessite une vérification juridique : `Q-11`.

### `G-11` — Rétention par catégorie de donnée non définie — *Structurante*

Explicitement listé comme décision à prendre par le document lui-même (§27). Bloque la conception de l'archivage et l'article « durée de conservation » de la loi 2014-038. Voir `Q-05`.

### `G-12` — Localisation des données et transferts hors frontières — *Structurante*

§17 s'appuie sur la loi 2014-038 mais le document ne dit pas où les données résident. Un hébergement hors de Madagascar peut constituer un transfert transfrontalier encadré. Le choix du fournisseur S3 et de la région n'est pas une décision d'infrastructure mais de conformité. Voir `Q-12`.

### `G-13` — Résidus de nommage « SchoolOS » — *Mineure*

§16 nomme encore `PersonIdentifier` « **SchoolOS**/FANABE ID public » et §24 parle de « **SchoolOS** ID sectoriel ». Le brief §9 impose FANABE partout en visible. §2.1 du document autorise « SchoolOS » comme terme d'architecture interne uniquement. Décision : **FANABE partout**, y compris dans le code ; aucun symbole ne portera « SchoolOS ». Le `README.md` racine (actuellement « sekool ») est également à corriger.

### `G-14` — Un identifiant public de 8 chiffres est plus étroit qu'il n'y paraît — *Structurante*

§6.3 fixe 8 chiffres aléatoires, soit 10⁸ valeurs. Le document traite la collision (régénération sur conflit d'unicité) mais pas la **densité** ni l'**énumérabilité** :

| Personnes enregistrées | Probabilité de collision à la génération suivante |
|---|---|
| 100 000 | 0,1 % |
| 1 000 000 | 1 % |
| 10 000 000 | 10 % |

La régénération absorbe cela sans difficulté à l'échelle malgache (~7 millions d'élèves au maximum théorique). En revanche l'espace est **devinable** : un attaquant tire un identifiant valide sur 10⁸ essais, et la clé de contrôle ne divise l'effort que par 23 pour les formats valides. C'est acceptable **à condition** que l'identifiant ne soit jamais un facteur d'authentification (§6.4 le dit) et que toute recherche par identifiant public soit authentifiée, autorisée, limitée en débit et journalisée, avec des réponses **uniformes** pour ne pas confirmer l'existence d'un identifiant. Plan de dépassement de capacité documenté dans [`identity-model.md`](./identity-model.md).

### `G-15` — La méthode de checksum est laissée ouverte, et l'exemple du document ne survivra pas au choix — *Mineure, à acter*

§27 demande explicitement de « choisir la méthode précise du checksum et son alphabet ». Une méthode est proposée et vérifiée dans [`identity-model.md`](./identity-model.md) : modulo 23 sur les 9 chiffres, alphabet de 23 lettres sans `I`, `O` ni `Q`. Elle détecte **100 %** des substitutions d'un chiffre, **100 %** des transpositions (adjacentes ou distantes) et **100 %** des erreurs jumelles — propriétés vérifiées par énumération exhaustive sur 3 000 identifiants (310 254 cas d'erreur testés, aucun raté).

Conséquence à acter : l'exemple `7-48372196-K` du §6.3 devient **`7-48372196-P`**. La lettre `K` du document est illustrative et antérieure au choix de méthode ; il n'y a pas d'erreur, mais la documentation devra être alignée pour ne pas propager un exemple invalide.

---

## 5. Points où le cahier des charges est plus juste que le brief de mission

À conserver contre le brief, qui les omet :

1. **Canal « Impression »** (§12) — Le brief ne cite que SMS / WhatsApp / email. Or l'impression est le seul canal qui atteint *toutes* les familles, et §12 la mentionne explicitement. Elle doit être un adaptateur de communication à part entière (file d'impression + PDF groupé), pas une fonctionnalité oubliée. C'est aussi le seul canal qui satisfait pleinement « ne jamais dépendre d'une API payante ».
2. **`Attendance & Grades`** (§15.2) — absent du découpage du brief, indispensable (`A-07`).
3. **`Audit & Compliance`** (§15.2) — absent du brief, imposé par §17.
4. **Versionnement des règles de calcul des indices** (§17, dernière puce) — absent du brief, c'est la condition d'auditabilité exigée par §18. Un score doit rester reproductible : `calculator_version` sur chaque score calculé.
5. **Portabilité et export** (§19.3) — le brief ne les mentionne pas ; ils sont pourtant au cœur de la promesse (« pas de verrouillage artificiel ») et doivent figurer au MVP sous une forme minimale.
6. **Continuité gratuite pour la famille** (§19.1) — une famille conserve son accès même quand son école cesse d'être cliente. C'est une contrainte d'architecture, pas de facturation : les données détenues par la famille ne peuvent pas vivre dans le plan tenant (`A-01`).

---

## 6. Synthèse des points bloquants

À trancher avant toute implémentation :

| # | Sujet | Question |
|---|---|---|
| `A-01` | Frontière plan plateforme / plan tenant | `Q-01` |
| `A-02` | Deux régimes de consentement | `Q-04` |
| `A-07` | Découpage modulaire définitif | `Q-01` |
| `A-11` | Authentification sans dépendance payante | `Q-02` |
| `G-01` | FANABE encaisse-t-il de l'argent ? | `Q-03` |
| `G-02` | Facturation par élève ou par famille | `Q-06` |
| `G-08` | Garde-fous du moteur de workflow | `Q-13` |

Le détail, les options et les défauts recommandés figurent dans [`open-questions.md`](./open-questions.md).
