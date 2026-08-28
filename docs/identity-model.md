# FANABE — Modèle d'identité

> **Statut : validé le 21 août 2026** — voir [`decisions.md`](./decisions.md) (`D-18`, `D-19`, `D-22`).
> Traduit les §6.1 à §6.7 du cahier des charges, et tranche les décisions §27 relatives à l'identité.
> Concepts d'architecture supposés connus : le modèle à deux plans de [`architecture.md`](./architecture.md#3-le-modèle-à-deux-plans).

## Sommaire

1. [Thèse](#1-thèse)
2. [Les quatre objets](#2-les-quatre-objets)
3. [FANABE Person ID](#3-fanabe-person-id)
4. [Rôles applicatifs et rôles relationnels](#4-rôles-applicatifs-et-rôles-relationnels)
5. [Inscription et mobilité](#5-inscription-et-mobilité)
6. [Rattachement d'une personne existante](#6-rattachement-dune-personne-existante)
7. [Périodes hors réseau et données externes](#7-périodes-hors-réseau-et-données-externes)
8. [Consent Center](#8-consent-center)
9. [Doublons et fusion](#9-doublons-et-fusion)
10. [Interopérabilité future](#10-interopérabilité-future)
11. [Scénarios de référence](#11-scénarios-de-référence)
12. [Invariants testables](#12-invariants-testables)

---

## 1. Thèse

> **FANABE identifie une personne, pas un élève** (§6.1).

Un identifiant scolaire lie une personne à un établissement : quand elle change d'école, il devient inutilisable et le parcours se fragmente. L'inversion proposée par le cahier des charges consiste à faire de l'**identité** l'objet stable et de la **relation à l'établissement** l'objet temporaire.

Trois conséquences, toutes structurantes :

1. Un élève qui devient parent **ne change pas d'identité** — il acquiert un rôle et des relations.
2. Un élève qui change d'école **ne change pas d'identité** — son inscription précédente se ferme, une nouvelle s'ouvre.
3. Une identité **ne donne aucun accès** par elle-même. C'est la condition sans laquelle une identité portable serait un canal de fuite entre établissements concurrents.

Le point 3 est celui qu'il est le plus tentant d'assouplir, et le plus dangereux à assouplir.

---

## 2. Les quatre objets

Reprise de §6.2, avec les responsabilités précisées.

| Objet | Question | Durée | Plan | `school_id` |
|---|---|---|---|---|
| `Person` | Qui est-ce ? | Toute la vie (§6.2) | Plateforme | — |
| `PersonRole` | En quelle qualité ? | Évolutive, cumulable | Plateforme | — |
| `Relationship` | Quel lien avec qui ? | Jusqu'à révocation | Plateforme | — |
| `Enrollment` | Où, quand, dans quelle classe ? | Une année scolaire | Établissement | **Obligatoire** |

### 2.1 `Person`

Porte l'état civil **minimal** et rien d'autre. Aucune donnée scolaire, financière ou comportementale n'y figure : ces données appartiennent à un établissement, `Person` non.

Attributs (défaut proposé, `Q-15`) : nom, prénom, date de naissance, **précision de la date** (`exact` / `year_only` / `estimated`), sexe, photo facultative, langue préférée, statut vital.

> Le champ `birth_date_precision` n'est pas un détail. Les actes de naissance ne sont pas systématiquement disponibles à Madagascar ; imposer une date exacte conduirait le personnel à saisir des dates inventées, et le système croirait ensuite savoir ce qu'il ne sait pas. Rendre l'approximation explicite est plus honnête et plus exploitable — notamment pour le rattachement (§6), où une date approximative ne peut pas servir de facteur de correspondance.

Aucun document d'identité n'est requis pour créer une identité : l'exiger exclurait précisément les familles que le produit veut servir.

### 2.2 `PersonRole`

Rôle **relationnel et non applicatif** : `student`, `alumni`, `parent`, `guardian`, `financial_contact`, `staff`, `supplier_agent`. Cumulables et datés (`acquired_at`, `ended_at`), jamais écrasés — c'est ce qui permet à un ancien élève devenu parent de conserver la trace des deux qualités (§6.7).

### 2.3 `Relationship`

Lien orienté entre deux `Person`, avec des droits propres (§6.7) :

| Type | Portées de données par défaut |
|---|---|
| `parent_of` | Scolarité, communication, documents, paiements |
| `guardian_of` | Identique, avec justificatif de tutelle |
| `financial_contact_for` | **Paiements et facturation uniquement** |
| `pickup_authorized_for` | Droit logistique seul, **aucun accès pédagogique** |

`financial_contact_for` et `pickup_authorized_for` proviennent directement du §6.7 et illustrent pourquoi les rôles relationnels ne peuvent pas être des rôles RBAC : ils qualifient une relation entre deux personnes, pas une fonction dans un logiciel.

Chaque relation porte : sujet, objet, type, portées, `status` (`pending`, `active`, `revoked`), source de la preuve, date d'établissement, auteur de la vérification.

### 2.4 `Enrollment`

Seul objet des quatre à vivre dans le plan établissement. Relation datée entre une personne et une école, pour une année scolaire et une classe. Statuts : `pre_registered`, `active`, `suspended`, `transferred_out`, `graduated`, `withdrawn`. Les transitions sont **historisées**, jamais écrasées : « pourquoi cet élève est-il parti en mars ? » doit avoir une réponse.

### 2.5 `Family`

§16 définit `Family` comme un « groupe familial logique pour la facturation et la communication ». Décision : **le foyer est global** (plan plateforme, indispensable à un compte parent multi-écoles), mais chaque établissement n'en voit qu'une **projection** — les membres qu'il inscrit et les adultes qui leur sont liés. Le compte payeur, lui, est propre à chaque (établissement, famille) : voir [`domain-model.md`](./domain-model.md) et `Q-06`.

---

## 3. FANABE Person ID

### 3.1 Format

```
7-48372196-P
│ │        └── caractère de contrôle (lettre)
│ └─────────── 8 chiffres tirés aléatoirement
└───────────── namespace FANABE (constante « 7 »)
```

Conforme à §6.3. Stockage canonique **sans tirets** (`748372196P`, 10 caractères) ; les tirets appartiennent à l'affichage. Un seul format en base élimine toute ambiguïté de comparaison.

### 3.2 Ce que l'identifiant ne contient pas

Contrainte absolue du §6.3 : aucune information personnelle. Ni sexe, ni date de naissance, ni établissement, ni ordre d'inscription, ni région, ni année de création. Les 8 chiffres proviennent d'un générateur cryptographiquement sûr, uniformément sur `[0, 99 999 999]`.

Cela interdit une pratique tentante : dériver l'identifiant d'un compteur ou d'un condensé d'attributs. Un compteur révélerait l'ordre d'arrivée et le volume d'inscriptions ; un condensé d'attributs permettrait de tester des hypothèses d'identité hors ligne.

### 3.3 Caractère de contrôle — algorithme retenu

Répond à la décision §27 (« choisir la méthode précise du checksum et son alphabet ») et à `G-15`.

```
ALPHABET = "ABCDEFGHJKLMNPRSTUVWXYZ"      # 23 lettres : I, O et Q retirés
                                          # (confusion visuelle avec 1, 0 et 0)

chiffres = "7" + payload                  # 9 caractères, ex. "748372196"
N        = valeur entière des 9 chiffres   # ex. 748372196
contrôle  = ALPHABET[N mod 23]            # ex. 748372196 mod 23 = 13 → "P"
```

**Pourquoi 23.** C'est un nombre premier, et l'ordre multiplicatif de 10 modulo 23 vaut 22. Les poids positionnels `10⁰ … 10⁸` sont donc `[1, 10, 8, 11, 18, 19, 6, 14, 2]` — tous distincts et non nuls modulo 23. De cette propriété découlent directement les garanties de détection ci-dessous. Et 23 est exactement le nombre de lettres restantes après retrait de `I`, `O` et `Q` : la coïncidence est heureuse, elle permet une bijection sans reste.

**Garanties, vérifiées par énumération exhaustive sur 3 000 identifiants (310 254 cas d'erreur générés) :**

| Type d'erreur de transcription | Détection |
|---|---|
| Substitution d'un chiffre | **100 %** (216 000 / 216 000) |
| Transposition de deux chiffres, adjacents ou distants | **100 %** (75 588 / 75 588) |
| Erreur jumelle (`11` → `22`) | **100 %** (18 666 / 18 666) |
| Confusion `I`/`1`, `O`/`0`, `Q`/`0` | Impossible par construction (lettres absentes de l'alphabet) |

Les erreurs jumelles sont détectées parce qu'il n'existe aucun couple de positions `i, j` dans `[0, 8]` tel que `10ⁱ + 10ʲ ≡ 0 (mod 23)` : cela exigerait `10^(i−j) ≡ −1`, ce qui n'arrive qu'à un écart de 11 positions, hors d'atteinte pour un identifiant de 9 chiffres.

**Comparé à l'alternative habituelle** (ISO 7064 MOD 11,10) : le modulo 11 ne produit que 10 valeurs de contrôle, ne détecte pas toutes les transpositions distantes, et son caractère de contrôle est un chiffre — donc visuellement confondu avec le corps de l'identifiant. Le modulo 23 avec caractère alphabétique offre de meilleures garanties **et** une lisibilité supérieure (la lettre finale marque visuellement la fin de l'identifiant).

> **Conséquence documentaire à acter.** L'exemple `7-48372196-K` du §6.3 devient **`7-48372196-P`**. Le `K` du cahier des charges est antérieur au choix de méthode et purement illustratif ; il n'y a pas d'erreur dans le document, mais il faudra aligner l'exemple pour ne pas diffuser un identifiant que le système rejetterait.

**Ce que le contrôle ne fait pas** (§6.3 le dit, il faut le tenir) : il ne remplace ni l'unicité, ni l'authentification, ni l'autorisation. Il détecte une faute de frappe au comptoir. Rien de plus.

### 3.4 Génération, collision, capacité

```
tenter jusqu'à 5 fois :
    payload = 8 chiffres aléatoires (générateur cryptographique)
    id      = "7" + payload + contrôle(payload)
    insérer  (contrainte UNIQUE sur public_id)
    si violation d'unicité : recommencer
si 5 échecs : lever une alerte d'exploitation (densité anormale) et refuser
```

Conforme à §6.3 (« en cas de collision, le système rejette l'insertion et génère une nouvelle valeur »). La contrainte d'unicité en base est l'**autorité** : aucune vérification préalable en lecture, qui serait sujette à une condition de course entre deux inscriptions simultanées.

**Capacité.** 10⁸ identifiants possibles :

| Personnes enregistrées | Probabilité de collision au tirage suivant | Échec après 5 tentatives |
|---|---|---|
| 100 000 | 0,1 % | 10⁻¹⁵ |
| 1 000 000 | 1 % | 10⁻¹⁰ |
| 10 000 000 | 10 % | 10⁻⁵ |

Largement suffisant à l'échelle visée (Madagascar compte de l'ordre de quelques millions d'élèves, tous établissements confondus, et FANABE ne visera qu'une fraction du privé). **Plan de dépassement, à documenter maintenant plutôt qu'à improviser plus tard** : au-delà de 30 % d'occupation, ouvrir un second namespace à 9 chiffres sous un autre chiffre de tête. Les identifiants existants restent valides ; la validation accepte les deux longueurs. Décider ce plan aujourd'hui coûte un paragraphe ; le décider en urgence coûterait une migration.

### 3.5 L'identifiant est public, donc devinable — et cela doit rester sans conséquence

Un identifiant tiré dans 10⁸ valeurs est devinable, et le contrôle ne réduit l'effort que d'un facteur 23 pour les formats valides. Ce n'est **pas** un défaut du format : c'est la nature d'un identifiant. Les garanties reposent ailleurs (§6.4) :

1. L'identifiant n'est **jamais** un facteur d'authentification, ni un mot de passe, ni un jeton.
2. Toute recherche par identifiant public est authentifiée, autorisée, limitée en débit, journalisée.
3. **Les réponses sont uniformes** : un identifiant inexistant et un identifiant existant hors du périmètre de l'appelant produisent une réponse identique. Sans cela, l'API deviendrait un oracle d'existence, énumérable.
4. La clé primaire interne est un UUID v4 opaque, jamais exposée (§6.4).

Le point 3 est le plus facile à casser par inadvertance : un `403` distinct d'un `404`, un message d'erreur différent, ou même un temps de réponse mesurablement différent suffit à révéler l'existence. Le contrôle du format se fait **avant** toute consultation de la base, afin qu'un identifiant mal formé ne provoque pas une latence distincte d'un identifiant bien formé.

---

## 4. Rôles applicatifs et rôles relationnels

Résolution de `A-10`. Deux notions distinctes, jamais fusionnées.

| | Rôle applicatif (RBAC) | Rôle relationnel |
|---|---|---|
| Répond à | Que peut faire cet utilisateur dans le logiciel ? | Qu'est cette personne pour cette autre personne ? |
| Portée | Un établissement (sauf Platform Admin) | Deux personnes |
| Exemples | `school_admin`, `teacher`, `accountant` | `parent_of`, `financial_contact_for` |
| Support | `SchoolRoleAssignment` | `Relationship` |

Rôles applicatifs (brief §3) : `platform_admin`, `school_owner`, `school_admin`, `principal`, `teacher`, `accountant`, `staff`, `parent`, `supplier`. À prévoir également, non implémenté avant la phase correspondante : `network_supervisor` (persona §5, offre « Réseau » §19.1).

**Pourquoi la distinction est indispensable.** Un « responsable financier » (§6.7) n'accède qu'aux paiements de l'enfant dont il est responsable — pas aux paiements de l'école. Ce n'est pas le rôle `accountant`, qui voit toute la comptabilité de l'établissement. Fusionner les deux notions obligerait à créer un rôle RBAC par situation familiale, ce qui n'a pas de fin.

Une même personne cumule les deux dimensions sans conflit : Mme R. est `teacher` à l'école A (rôle applicatif), `parent_of` de son fils inscrit à l'école B (rôle relationnel), et `accountant` à l'école A (second rôle applicatif). Trois habilitations indépendantes, une seule identité — c'est l'un des tests obligatoires du brief §5.

---

## 5. Inscription et mobilité

### 5.1 Une seule inscription active

Décision `D-19` : au MVP, un élève a **au plus une inscription `active` à la fois** dans tout le réseau FANABE. Un parent peut avoir *plusieurs enfants* dans *plusieurs écoles* ; un même enfant ne peut pas figurer simultanément à l'effectif de deux écoles FANABE.

La contrainte est exprimée en base :

```sql
CREATE UNIQUE INDEX enrollments_one_active_per_person
  ON enrollments (person_id)
  WHERE status = 'active';
```

Elle est globale (pas par `school_id`) : c'est l'une des rares contraintes qui traverse les tenants, et c'est volontaire.

### 5.2 Transfert entre écoles FANABE

Le changement d'école n'est plus un simple changement de statut local. C'est un objet `EnrollmentTransfer` qui exige **deux validations indépendantes** (`D-18`) :

1. le parent autorise l'intégration dans l'école d'accueil ;
2. l'école d'origine autorise le détachement de son effectif.

```
École A  Enrollment #1  active
                │
                │  EnrollmentTransfer créé par l'école B
                │    parent_approved_at     = …
                │    origin_school_approved_at = …
                ▼
École A  Enrollment #1  transferred_out
École B  Enrollment #2  active
```

Un refus ou un silence de l'école d'origine **laisse l'élève dans son effectif**. L'école B ne peut pas « prendre » un élève. Le `Person` est inchangé. L'école B ne voit **rien** de l'école A, sauf consentement (`R3`). Test obligatoire du brief §5.

Si l'élève existe mais n'a **pas** d'inscription active (ancien élève, période externe) : validation du parent uniquement, pas d'école d'origine à consulter.

### 5.3 Élève devenu parent

Aucune nouvelle identité (§6.7). L'ancien élève acquiert le rôle `parent` et des relations `parent_of`. Sa propre inscription passée subsiste, et son rôle `student` est clos par `ended_at` sans être supprimé. Test obligatoire du brief §5.

### 5.4 Multi-écoles pour un même parent

Un parent, un compte, plusieurs enfants dans plusieurs écoles (§6.5). Son espace agrège les établissements auxquels il a **droit d'accès** ; chaque bloc est cloisonné et étiqueté par école. Aucune école ne voit les autres, et le parent voit clairement où chaque information s'arrête.

---

## 6. Parcours d'inscription et rattachement

Source de vérité : [`decisions.md`](./decisions.md) (`D-18`, `D-22`). §6.5 exige la « réutilisation de la même identité **si la relation est confirmée** ». Le parcours ci-dessous est ce « si ».

### 6.1 Le risque

Si une école peut saisir un identifiant public et obtenir une fiche, alors :

- un employé malveillant énumère des identifiants et récolte des données civiles ;
- une école concurrente vérifie si un élève est inscrit ailleurs ;
- l'identifiant public devient de fait un mécanisme d'accès, ce que §6.4 interdit explicitement.

Les contacts (téléphone, email) ne sont **pas** dans le plafond `R2`. Une école ne les voit qu'après établissement d'un lien.

### 6.2 Étape 1 — le parent a-t-il déjà un compte ?

**Non.** L'école *crée* le compte parent (état civil + contacts saisis au comptoir). Ce n'est pas une consultation du plan plateforme : ce sont des données que l'école produit. Puis enchaînement sur l'élève (§6.3).

**Oui.** L'école ne cherche pas par téléphone. Elle demande l'un des deux :

| Voie | Nature | Effet |
|---|---|---|
| **Lien généré par le parent** | Consentement consommable (`D-22`) | Rédemption → relation + consentement. L'école voit alors les attributs autorisés, contacts compris. |
| **FANABE Person ID** | Identifiant, **pas** un secret | Demande de rattachement, **réponse uniforme** (aucune confirmation d'existence). Le parent doit confirmer (application ou code imprimé). Tant que ce n'est pas fait, l'école ne voit aucune coordonnée. |

Le lien parent (`FamilyShareToken`) :

- généré par le parent dans son espace ;
- jeton 160 bits, **stocké haché** ;
- portée : quels enfants, quelles catégories de données, éventuellement quelle école ;
- durée de vie courte (défaut 7 jours) ;
- usage unique (consommé à la rédemption) ;
- journalisé.

L'identifiant public **n'est jamais un consentement**. Le saisir pointe ; il n'autorise rien.

### 6.3 Étape 2 — l'élève existe-t-il déjà ?

**Non.** L'école crée l'identité élève, la relation `parent_of`, et l'inscription.

**Oui.**

1. Validation du parent pour intégrer l'élève dans *cette* école.
2. Si l'élève a une inscription `active` dans une autre école FANABE → `EnrollmentTransfer` : l'école d'origine doit valider le détachement. Les deux validations sont obligatoires.
3. Sinon (pas d'inscription active) → validation du parent seule.
4. Puis création de l'inscription dans l'école d'accueil.

Toute tentative est journalisée (agent, établissement, IP hachée, résultat) et limitée en débit par agent, par école et par IP. Les réponses restent uniformes : un identifiant inexistant et un identifiant hors périmètre produisent la même réponse.

### 6.4 La famille sans accès numérique

Deux cas distincts :

- **Pas de compte** : l'école le crée au comptoir (§6.2). C'est le chemin principal hors ligne.
- **Compte existant, ni identifiant ni lien sous la main** : voie exceptionnelle `staff_attested`. Un agent identifié déclare avoir vu un justificatif, téléverse la preuve, engage sa responsabilité nominative. La famille est notifiée par impression et peut contester. Ces rattachements sont dénombrés dans un tableau de contrôle plateforme.

**Règle qui borne le risque :**

> Un rattachement, quel que soit son mode, ouvre l'accès **aux seules données que la nouvelle école produira elle-même**. Il ne donne accès à **aucune** donnée d'un autre établissement (`R3`). Un rattachement frauduleux ne révèle donc rien d'historique.

---

## 7. Périodes hors réseau et données externes

§6.5 et §14.3. Principe absolu du brief : **ne jamais inventer de données**.

### 7.1 Période hors réseau

Objet explicite `ExternalEducationPeriod`, rattaché à la personne (plan plateforme, car il n'appartient à aucun établissement FANABE) : libellé de l'établissement (texte libre, ce n'est pas un tenant), période, niveau déclaré, qui l'a déclaré, statut de vérification.

Dans l'interface, le parcours d'un élève ne comporte jamais de trou muet : il affiche « 2023-2024 — hors réseau FANABE — déclaré par le parent », ce qui est une information, alors qu'un vide serait une ambiguïté.

### 7.2 Provenance d'un document

Tout document porte (brief §3) : `source_type` (`native` / `external`), `source_school` (libellé pour un externe), `verification_status`, `uploaded_by`, `uploaded_at`, et l'historique de vérification.

### 7.3 Statuts de vérification

Résolution de `G-06`. Un modèle binaire enfermerait tout document venant d'une école non-FANABE dans un état d'échec permanent — c'est-à-dire précisément le cas d'usage du §14.3.

| Statut | Signification | Qui peut le poser |
|---|---|---|
| `unverified` | Importé, non vérifié | — (état initial) |
| `attested_by_school` | Un agent identifié d'un établissement FANABE a vu l'original papier | Tout établissement lié à l'élève |
| `verified_by_issuer` | L'émetteur lui-même, présent sur FANABE, confirme | L'établissement émetteur seul |
| `disputed` | Contesté, avec motif | Établissement ou famille |

**Contrainte d'affichage, non négociable :** l'interface ne présente jamais `attested_by_school` comme équivalent à `verified_by_issuer`, et jamais un document externe comme une donnée native. Étiquette visuelle distincte, systématique, y compris en impression. Test obligatoire du brief §5.

---

## 8. Consent Center

§6.6, avec la résolution de `A-02` et `A-03`.

### 8.1 Deux régimes, nommés différemment dans l'interface

| « Accès de mon école » | « Partages que j'autorise » |
|---|---|
| Données produites par l'école dans le cadre de la scolarité | Partage inter-établissement, documents détenus par la famille |
| Pas de consentement — finalité légitime | Consentement explicite exigé |
| **Non révocable**, mais **entièrement visible** | Révocable à tout moment |
| Le parent voit qui a consulté quoi et quand | Le parent décide qui voit quoi |

Cette séparation réduit la promesse littérale du §6.6, et c'est délibéré : un Consent Center qui prétendrait permettre à un parent d'empêcher son école de consulter les notes qu'elle vient de saisir serait une promesse intenable, donc une perte de confiance à la première confrontation avec la réalité. La transparence sur les accès non révocables est plus honnête, et plus utile.

### 8.2 Portées

`identity.core`, `identity.contact`, `academic.records`, `academic.attendance`, `finance.history`, `documents.external`, `documents.certificates`, `health.notes` (sensible, isolé, hors MVP — §18).

### 8.3 Objet `Consent`

Sujet, auteur de l'octroi, destinataire, portée, finalité, dates d'octroi et d'expiration (défaut 12 mois, `Q-04`), date de révocation, source (`app` / `paper` / `staff_attested`), preuve éventuelle, version des conditions.

Durée par défaut plutôt que perpétuité : un consentement sans échéance cesse d'être un consentement, et §17 impose un principe de durée de conservation. Notification avant expiration, renouvellement en un geste.

### 8.4 Ce que la révocation fait, et ne fait pas

À dire explicitement dans l'interface (`A-03`) :

| Fait | Ne fait pas |
|---|---|
| Coupe immédiatement les lectures futures | N'efface pas ce que l'école doit légalement conserver |
| Retire le partage du parcours de l'élève | Ne rappelle pas ce qui a déjà été consulté ou exporté |
| Est tracée et horodatée | Ne réécrit pas le journal d'audit |

Le troisième point mérite d'être affiché sans détour : « ce document a été consulté par l'école X le 12 mars ; la révocation empêche les consultations futures, elle n'annule pas celle-ci ». Laisser croire l'inverse serait plus grave que de ne rien promettre.

---

## 9. Doublons et fusion

Avec des attributs faibles (homonymie fréquente, dates approximatives, absence d'état civil systématique), les doublons sont **certains**. Un mécanisme de fusion doit donc exister dès la phase 1 : rattraper des doublons sans mécanisme prévu est l'un des chantiers de reprise les plus coûteux qui existent.

- **Détection** : à la création, recherche de similarité (nom en trigramme, date proche, téléphone identique) et avertissement non bloquant. Bloquer serait pire : le personnel créerait des variantes orthographiques pour contourner l'obstacle.
- **Fusion** : réservée au `platform_admin`, sur demande motivée d'un établissement. Une personne est conservée, l'autre devient un alias (`merged_into_person_id`). Les identifiants publics des deux restent **résolvables** — un identifiant imprimé sur un document en circulation ne doit jamais devenir invalide.
- **Réversibilité** : la fusion est enregistrée comme un événement et peut être défaite. Une fusion erronée mêlerait les dossiers de deux enfants réels : l'opération irréversible n'est pas acceptable.

---

## 10. Interopérabilité future

§6.1 et §24 : se raccorder au futur socle national (PRODIGY) **sans prétendre le remplacer** ni le concurrencer (§25).

Préparation minimale et suffisante : une table `PersonExternalIdentifier` (système, valeur, statut de vérification, date, source). Rien d'autre. Aucune API, aucun format présumé, aucune correspondance devinée.

Trois interdits explicites, pour que la préparation ne dérive pas en revendication :

1. Ne jamais présenter le FANABE ID comme un identifiant national ou officiel.
2. Ne jamais exiger un identifiant national pour créer une identité FANABE.
3. Ne jamais fusionner automatiquement deux personnes parce qu'elles partagent un identifiant externe — la vérification reste humaine et tracée.

---

## 11. Scénarios de référence

Les trois cas du brief §8, qui devront exister dans le jeu de démonstration et être couverts par des tests.

### Person A — ancien élève devenu parent, enfants dans deux écoles

```
Person A  ·  7-XXXXXXXX-C  ·  un seul identifiant, depuis toujours

  rôles :        student (2005-2016, clos)  →  alumni  →  parent (depuis 2024)
  relations :    parent_of → Enfant 1        parent_of → Enfant 2
  inscriptions : Enrollment A (École 1, 2005-2016, graduated)
                 Enfant 1 → École 1  (l'école de son père)
                 Enfant 2 → École 2

  vérifie : identité permanente, cumul de rôles, multi-écoles,
            et l'École 2 ne voit RIEN de la scolarité de A à l'École 1
```

### Person B — élève actif

```
Person B  ·  élève, École 1, 5ᵉ, 2026-2027, active
            parent : Person D  ·  facturation, présence, paiements
  vérifie : le parcours nominal
```

### Person C — période externe puis retour

```
Person C
  2022-2023  École 1 (FANABE)          Enrollment, données natives
  2023-2024  « Lycée Saint-Michel »    ExternalEducationPeriod, hors réseau
             bulletin importé par le parent → unverified
             puis vu par l'École 1     → attested_by_school
  2024-2026  École 1 (FANABE)          nouvelle inscription, MÊME identité

  vérifie : identité conservée à travers un trou, aucune donnée inventée,
            donnée externe qui reste étiquetée externe pour toujours
```

---

## 12. Invariants testables

Chaque ligne devient un test nommé. Les sept premiers correspondent aux tests obligatoires du brief §5.

| # | Invariant | Test |
|---|---|---|
| `I-01` | Une école ne peut jamais lire les données d'une autre | `Isolation/CrossSchoolReadTest` |
| `I-02` | Un parent ne voit que les enfants qu'il est autorisé à voir | `Isolation/ParentChildScopeTest` |
| `I-03` | Une école ne lit pas l'historique d'une autre sans consentement | `Isolation/CrossSchoolHistoryTest` |
| `I-04` | Un même `Person` porte plusieurs rôles simultanément | `Identity/MultiRolePersonTest` |
| `I-05` | Un enfant devient parent sans changer d'identité | `Identity/StudentBecomesParentTest` |
| `I-06` | Un élève change d'école sans changer d'identité | `Identity/SchoolTransferKeepsIdentityTest` |
| `I-07` | Une donnée externe reste externe, jamais assimilée à une donnée native | `Documents/ExternalProvenanceTest` |
| `I-08` | Le contrôle détecte 100 % des substitutions et transpositions | `Identity/PublicIdChecksumTest` |
| `I-09` | Une collision d'identifiant provoque une régénération, jamais un doublon | `Identity/PublicIdCollisionTest` |
| `I-10` | Un identifiant public ne contient aucune donnée dérivable des attributs | `Identity/PublicIdEntropyTest` |
| `I-11` | Un identifiant inexistant et un identifiant hors périmètre donnent la même réponse | `Identity/UniformNotFoundTest` |
| `I-12` | Un rattachement n'ouvre aucun accès aux données d'un autre établissement | `Identity/LinkGrantsNoHistoryTest` |
| `I-13` | Une révocation coupe les lectures futures et ne touche pas l'audit | `Consent/RevocationEffectTest` |
| `I-14` | Un consentement expiré ne donne plus accès, sans intervention manuelle | `Consent/ExpiryEnforcementTest` |
| `I-15` | Une fusion conserve les deux identifiants publics résolvables | `Identity/MergeKeepsPublicIdsTest` |
| `I-16` | Un élève n'a qu'une inscription `active` à la fois dans tout FANABE | `Enrollment/SingleActiveEnrollmentTest` |
| `I-17` | Un transfert n'est pas effectif sans validation parent **et** école d'origine | `Enrollment/TransferDualApprovalTest` |
| `I-18` | Un lien parent est un consentement consommable ; un ID public n'en est pas un | `Identity/ShareTokenIsConsentTest` |
| `I-19` | Les contacts d'un parent ne sont pas visibles avant établissement d'un lien | `Isolation/ParentContactVisibilityTest` |
