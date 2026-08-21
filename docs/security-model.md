# FANABE — Modèle de sécurité et de conformité

> **Statut : validé le 21 août 2026** — voir [`decisions.md`](./decisions.md).
> Traduit §17 et §18 du cahier des charges, et les exigences de sécurité du brief §3.
> Prérequis : [`architecture.md`](./architecture.md#7-multi-tenancy) et [`identity-model.md`](./identity-model.md).

## Position de départ

§17 énonce que « la sécurité doit être une composante fondatrice, pas une étape ultérieure ». Concrètement, cela se traduit par un choix de méthode : les propriétés de sécurité de FANABE doivent être **structurelles** (garanties par le schéma, les contraintes et les types) plutôt que **procédurales** (garanties par la vigilance des développeurs).

Une règle appliquée par une contrainte de base de données tient dans dix-huit mois avec une équipe qui a changé. Une règle appliquée par une convention ne tient pas. C'est le critère qui départage les décisions de ce document.

## Sommaire

1. [Actifs et modèle de menace](#1-actifs-et-modèle-de-menace)
2. [Isolation multi-tenant](#2-isolation-multi-tenant)
3. [Authentification](#3-authentification)
4. [Autorisation](#4-autorisation)
5. [Anti-énumération](#5-anti-énumération)
6. [Fichiers et documents](#6-fichiers-et-documents)
7. [Vérification publique de certificat](#7-vérification-publique-de-certificat)
8. [Chiffrement et secrets](#8-chiffrement-et-secrets)
9. [Limitation de débit et abus](#9-limitation-de-débit-et-abus)
10. [Audit](#10-audit)
11. [Sécurité applicative](#11-sécurité-applicative)
12. [Gouvernance des indices](#12-gouvernance-des-indices)
13. [Conformité réglementaire](#13-conformité-réglementaire)
14. [Tests de sécurité obligatoires](#14-tests-de-sécurité-obligatoires)

---

## 1. Actifs et modèle de menace

### 1.1 Actifs, par gravité de compromission

| # | Actif | Conséquence d'une compromission |
|---|---|---|
| 1 | Dossiers d'élèves mineurs (identité, scolarité, adresses, contacts) | **Maximale.** Données de mineurs, préjudice durable, atteinte à la vie privée familiale |
| 2 | Cloisonnement inter-établissements | Perte de confiance irréversible, avantage concurrentiel détourné, non-conformité |
| 3 | Données financières familiales | Exploitable pour du chantage ou de la stigmatisation sociale |
| 4 | Certificats et leur vérification | Faux documents scolaires en circulation |
| 5 | Journal d'audit | Perte de la capacité à démontrer la conformité |
| 6 | Comptes à privilèges | Compromission de tout ce qui précède |

L'actif n°1 impose une posture plus stricte qu'un SaaS B2B ordinaire : les personnes concernées sont majoritairement des mineurs, elles n'ont pas choisi d'être dans le système, et elles ne peuvent pas en sortir.

### 1.2 Menaces retenues

| # | Menace | Réponse principale |
|---|---|---|
| `T-01` | Une école accède aux données d'une autre (bug, paramètre falsifié, SQL brut) | Cinq barrières d'isolation (§2) |
| `T-02` | Un membre du personnel consulte des dossiers sans motif légitime | Audit + périmètre par rôle + revue des accès anormaux |
| `T-03` | Énumération d'identifiants publics pour récolter de l'état civil | Réponses uniformes, débit limité, absence d'oracle (§5) |
| `T-04` | Un parent accède au dossier d'un enfant qui n'est pas le sien | Autorisation par relation, testée systématiquement |
| `T-05` | Un compte à privilèges compromis | TOTP obligatoire, sessions courtes, audit, moindre privilège |
| `T-06` | Fuite d'un lien de document | URL signées à durée courte, jamais d'objet public |
| `T-07` | Faux certificat, ou certificat révoqué présenté comme valide | Vérification serveur, statut faisant foi, empreinte de l'artefact |
| `T-08` | Balayage massif de l'endpoint public de vérification | Débit limité, divulgation minimale, journalisation |
| `T-09` | Une école exfiltre les dossiers d'un élève transféré | Consentement obligatoire, portées, audit |
| `T-10` | Un moteur automatique produit une décision défavorable | Interdiction structurelle (§12) |
| `T-11` | Tempête de notifications, coût SMS incontrôlé | Garde-fous du moteur de workflow |
| `T-12` | Rattachement frauduleux d'une identité | Double attribut, approbation, et surtout : le rattachement n'ouvre aucun historique |

`T-02` mérite une attention particulière : c'est statistiquement la menace la plus probable, et la moins spectaculaire. La réponse n'est pas technique mais organisationnelle — rendre les accès visibles au parent (§10) est plus dissuasif qu'un contrôle supplémentaire.

---

## 2. Isolation multi-tenant

Exigence la plus forte du projet. Détail des cinq barrières en [`architecture.md`](./architecture.md#7-multi-tenancy) ; propriétés de sécurité ici.

### 2.1 Pourquoi cinq barrières

Chaque barrière échoue d'une manière différente, et surtout : les barrières 3 et 4 restent efficaces **quand le code applicatif est fautif**, ce qui est le cas de la grande majorité des fuites réelles.

| Barrière | Protège contre | Reste efficace si… |
|---|---|---|
| 1. Contexte explicite, refus par défaut | Absence de portée | — |
| 2. Portée globale Eloquent | Requête ORM sans filtre | Le développeur utilise l'ORM |
| 3. **RLS PostgreSQL** | **SQL brut, agrégats, jobs mal cadrés** | **Le code applicatif est fautif** |
| 4. **Clés étrangères composites** | **Référence croisée entre tenants** | **Le code applicatif est fautif** |
| 5. Contexte dans les jobs, préfixes de stockage | Travaux asynchrones, fichiers | — |

### 2.2 Fail-closed, sans exception

- Pas de contexte de tenant ⇒ **refus**, jamais « tous les tenants ».
- Un job sans contexte ⇒ **échec**, jamais exécution en aveugle.
- Une requête hors politique RLS ⇒ **zéro ligne**, jamais toutes les lignes.
- Un rôle inconnu ⇒ **aucune permission**, jamais permissions par défaut.

L'échappatoire à la portée de tenant (nécessaire pour l'administration plateforme) est une opération explicite, nommée, réservée à `platform_admin`, **systématiquement journalisée avec un motif obligatoire**, et impossible à déclencher depuis un contrôleur d'API.

### 2.3 Réponses uniformes

Un `403` sur une ressource d'un autre établissement révélerait son existence. Toute ressource hors périmètre renvoie donc **`404`**, avec le même corps de réponse et une latence indistinguable. C'est vérifié par la matrice d'isolation, pour chaque ressource d'API et non par échantillonnage.

---

## 3. Authentification

Décision `Q-02` / `A-11` : aucune dépendance à une API payante sur le chemin de connexion.

### 3.1 Personnel d'établissement

- Email + mot de passe (Argon2id), politique de longueur minimale plutôt que de complexité arbitraire — les règles de complexité produisent des mots de passe notés sur un papier collé à l'écran.
- **TOTP obligatoire** pour `platform_admin`, `school_owner`, `school_admin`, `accountant`. Gratuit, hors ligne, sans dépendance opérateur.
- Verrouillage progressif après échecs, par compte et par IP.
- Session 8 h avec renouvellement glissant ; réauthentification pour les opérations sensibles (changement de rôle, export, révocation de certificat).

### 3.2 Parent

- Rattachement par **code d'invitation** émis par l'école (papier ou in-app), à usage unique, expirant.
- Puis mot de passe ou code PIN long.
- **Pas de SMS** (`D-20`) : ni comme second facteur, ni comme voie de récupération. Récupération par code imprimé remis par l'école, ou par email si un email est enregistré.
- Session 30 jours (usage mobile occasionnel), révocable depuis l'appareil et par l'école.

### 3.3 Jetons

Sanctum, jetons opaques stockés hachés. Rotation à chaque réauthentification sensible. Révocation immédiate possible côté serveur — un jeton auto-porteur non révocable serait inacceptable sur des données de mineurs. Un `jti` figure dans l'audit pour tracer une session compromise.

### 3.4 Ce que l'authentification n'est jamais

> Le FANABE Person ID **n'est jamais** un facteur d'authentification (§6.4). Ni identifiant de connexion, ni secret, ni jeton de récupération, ni preuve de possession. Il est public par nature ; le traiter comme un secret ruinerait le modèle.

---

## 4. Autorisation

RBAC (rôle × établissement) complété par de l'ABAC (attributs de relation, de consentement et de périmètre).

### 4.1 Les quatre questions, dans l'ordre

Toute décision d'accès répond successivement à :

1. **Qui** — principal authentifié.
2. **Où** — contexte d'établissement, présent et cohérent avec l'URL.
3. **À quel titre** — rôle applicatif dans cet établissement, ou relation avec la personne concernée.
4. **Sous quelle réserve** — consentement requis pour toute donnée produite par un autre établissement (`R3`).

Une réponse manquante à n'importe laquelle des quatre ⇒ refus.

### 4.2 Matrice indicative (MVP)

| Ressource | Platform Admin | School Owner / Admin | Principal | Teacher | Accountant | Parent |
|---|---|---|---|---|---|---|
| Élèves de l'établissement | — (métadonnées seules) | Lecture / écriture | Lecture | Lecture (ses classes) | Lecture (état civil restreint) | Ses enfants seulement |
| Présence | — | Lecture / écriture | Lecture | Écriture (ses classes) | — | Lecture (ses enfants) |
| Factures et paiements | — | Lecture / écriture | Lecture | — | Lecture / écriture | Lecture (ses enfants) |
| Risque et recouvrement | — | Lecture | Lecture | — | Lecture / écriture | **Jamais** |
| Reliability (famille) | — | Lecture | Lecture | — | Lecture | **Jamais** |
| Reliability (établissement) | Lecture | Lecture (le sien) | Lecture (le sien) | — | — | **Jamais** |
| Consentements | — | Lecture (le concernant) | — | — | — | Lecture / écriture |
| Documents natifs | — | Lecture / écriture | Lecture | Lecture (ses classes) | — | Lecture (ses enfants) |
| Documents familiaux | — | Lecture **si consentement** | Idem | — | — | Propriétaire |
| Audit | Lecture | Lecture (le sien) | Lecture (le sien) | — | — | Lecture (le concernant) |
| Règles de workflow | Lecture / écriture (templates) | Lecture / écriture (params) | Lecture | — | — | — |

Deux points à souligner :

- **`platform_admin` ne lit pas les dossiers d'élèves.** Il administre la plateforme : tenants, abonnements, incidents, fusions d'identité. Tout accès à un dossier réel est une opération exceptionnelle, motivée, journalisée et notifiée à l'établissement. Un administrateur qui peut tout lire est une faille permanente.
- **Un parent ne voit jamais un score.** Ni le sien, ni un niveau de risque (§9.2, `A-04`). Le score sert à prioriser le travail de l'école, pas à qualifier une famille auprès d'elle-même.

### 4.3 Périmètre du parent

Le contrôle le plus fréquent du système. Un parent accède à un élève si et seulement si :

```
relation active (parent_of | guardian_of | financial_contact_for)
   ET portée de la relation couvrant la donnée demandée
   ET (donnée produite par l'école qui inscrit l'élève
       OU consentement actif couvrant la portée)
```

`financial_contact_for` illustre la nécessité des portées : ce titulaire accède aux factures et paiements, et **à rien d'autre** (§6.7). Ni notes, ni présence, ni documents pédagogiques.

---

## 5. Anti-énumération

Exigence explicite du brief §3. Le risque n'est pas théorique : l'état civil de mineurs est une cible.

| Mesure | Détail |
|---|---|
| Clés primaires opaques | UUID v4 sur `persons`, `documents`, `certificates`. Jamais d'entier séquentiel, jamais d'identifiant ordonné dans le temps sur une entité exposée |
| Réponses uniformes | `404` identique pour « inexistant » et « hors périmètre », même corps, même latence |
| Validation avant lecture | Le format et le checksum sont contrôlés **avant** toute requête en base, pour qu'un identifiant mal formé ne se distingue pas par son temps de réponse |
| Pas d'oracle de rattachement | Une demande de rattachement ne confirme jamais l'existence d'un identifiant (`identity-model.md` §6.2) |
| Débit strict | Recherche par identifiant public : 10/min et 100/jour par utilisateur, 30/min par IP |
| Journalisation | Toute résolution d'identifiant public est auditée, avec détection de rafales |
| Pas de recherche transverse | Aucun endpoint ne permet de parcourir les personnes hors du périmètre de l'établissement |

L'attaque résiduelle — deviner un identifiant valide dans 10⁸ valeurs — est acceptable **parce que** connaître un identifiant ne donne aucun accès. C'est la propriété qui rend l'ensemble tenable, et elle ne doit jamais être affaiblie pour un gain de commodité.

---

## 6. Fichiers et documents

- Stockage objet **privé**, aucun accès public, aucun objet en lecture anonyme.
- Accès par **URL signée** émise après contrôle d'autorisation, durée de vie **5 minutes**, usage unique quand le canal le permet.
- Clés préfixées par propriétaire : `schools/{school_id}/…` pour un document natif, `persons/{person_id}/…` pour un document familial. Un préfixe erroné devient détectable par recensement.
- Nom de fichier d'origine assaini ; type MIME **vérifié sur le contenu**, pas sur l'extension ni sur l'en-tête déclaré.
- Analyse antivirus à l'ingestion (adaptateur ; ClamAV en développement).
- Téléversement limité en taille et en type ; SVG refusé (vecteur d'exécution de script).
- **Toute émission d'URL signée est auditée** : c'est le seul moyen de répondre plus tard à « qui a téléchargé ce bulletin ? ».
- Empreinte SHA-256 calculée à l'ingestion et à l'émission, stockée, vérifiable.

---

## 7. Vérification publique de certificat

Le seul endpoint non authentifié du système, donc le plus exposé. §14.1 l'exige, §6.4 en fixe la limite : aucune donnée personnelle en clair dans le QR.

### 7.1 Ce que contient le QR

Une URL et un jeton opaque de 160 bits. **Rien d'autre.** Aucune donnée personnelle, aucun identifiant de personne, aucun nom d'école. Un QR photographié ne révèle rien par lui-même : il faut interroger le serveur, qui décide ce qu'il divulgue et le journalise.

### 7.2 Ce que voit un vérificateur anonyme

Divulgation minimale (`Q-09`) :

| Divulgué | Non divulgué |
|---|---|
| Statut : `VALID` / `REVOKED` / `EXPIRED` | Date de naissance complète |
| Type de document | Adresse, contacts |
| Nom de l'établissement émetteur | FANABE Person ID |
| Date d'émission, année scolaire, classe | Autres inscriptions, fratrie |
| Prénom + initiale du nom | Situation financière |

Divulgation graduée optionnelle : un vérificateur qui détient le document papier peut saisir la date de naissance pour obtenir le nom complet. Un balayage de QR volés ne donne alors pas accès à l'identité complète, alors qu'un vérificateur légitime obtient ce dont il a besoin.

### 7.3 Protections

Débit limité par IP et par jeton ; jeton stocké **haché** (une lecture de base ne donnerait pas la liste des certificats vérifiables) ; chaque vérification journalisée avec IP hachée ; révocation immédiate d'un jeton ou d'un certificat ; endpoint isolé, sans accès à la couche tenant.

### 7.4 Mention obligatoire

Sur l'écran de vérification **et** sur le PDF, littéralement, conformément à §14.2 :

> « Attestation de plateforme FANABE. Ne constitue pas une signature électronique qualifiée au sens de la loi n° 2014-025. »

Cette mention n'est pas une précaution rédactionnelle mais une exigence du cahier des charges. L'abstraction `DocumentSigner` prépare une signature qualifiée future ; jusque-là, prétendre l'inverse serait faux.

---

## 8. Chiffrement et secrets

| Élément | Mesure |
|---|---|
| Transit | TLS 1.3, HSTS, redirection systématique |
| Repos, base | Chiffrement du volume, plus chiffrement applicatif des champs sensibles |
| Champs chiffrés | Secret TOTP, identifiants externes, coordonnées bancaires éventuelles, notes de santé (si un jour activées) |
| Repos, fichiers | Chiffrement côté serveur du stockage objet |
| Mots de passe | Argon2id |
| Jetons et clés de vérification | Stockés hachés, jamais en clair |
| Signature de documents | Ed25519, clé identifiée (`signer_key_id`) et **rotative** — un certificat ancien reste vérifiable avec la clé de son époque |
| Secrets applicatifs | Hors du dépôt, injectés par l'environnement, rotation documentée |
| Journaux | Aucune donnée personnelle, aucun jeton ; IP et agents **hachés** |

Le point sur les journaux est le plus souvent négligé : un fichier de log contenant des noms d'élèves annule les efforts faits ailleurs, et il est copié partout (agrégateur, sauvegarde, poste de développeur).

---

## 9. Limitation de débit et abus

| Cible | Limite proposée |
|---|---|
| Connexion | 5/min par IP, 10/h par compte, verrouillage progressif |
| Recherche par identifiant public | 10/min et 100/jour par utilisateur |
| Rattachement d'identité | 5/jour par agent, 20/jour par école |
| Vérification de certificat | 30/min par IP, 100/jour par jeton |
| API générale | 120/min par utilisateur |
| Actions de workflow | Plafond quotidien par école, borné par la plateforme (`Q-13`) |
| Téléversement | 20/h par utilisateur |

Détection d'anomalies exposée dans un tableau plateforme : rafales de résolutions d'identifiants, pic d'attestations hors ligne dans une école, taux d'échec de connexion inhabituel, consultations massives de dossiers par un même agent. Les seuils sont moins importants que le fait que **quelqu'un regarde** : ces indicateurs doivent être visibles, pas seulement enregistrés.

---

## 10. Audit

### 10.1 Propriétés

Append-only, sans exception ni pour `platform_admin`. Chaque entrée porte : acteur, établissement de l'acteur, rôle, action, ressource, personne concernée, résultat (`allowed` / `denied`), contexte (IP hachée, agent, motif). Les refus sont journalisés autant que les succès — une série de refus est le signal le plus utile qui existe.

### 10.2 Ce qui est journalisé

Consultation d'une fiche `Person`, téléchargement de document, vérification de certificat, octroi ou révocation de consentement, tentative de rattachement, export, écriture financière, dérogation manuelle sur un niveau de risque, changement de rôle, échappatoire à la portée de tenant.

Volontairement **pas** toute lecture : un journal saturé de bruit ne se lit pas, donc ne protège personne.

### 10.3 Le parent est destinataire de l'audit

§6.6 demande de la transparence, pas seulement de la conservation. Le parent voit, dans son espace, qui a consulté le dossier de son enfant et quand. C'est ce qui donne un sens au régime non révocable de `A-02` : l'école accède librement aux données qu'elle produit, mais elle le fait **sous le regard** de la famille.

C'est aussi la réponse la plus efficace à `T-02` (curiosité interne) : la dissuasion par la visibilité fonctionne mieux qu'un contrôle d'accès supplémentaire.

---

## 11. Sécurité applicative

| Vecteur | Mesure |
|---|---|
| Injection SQL | Requêtes préparées, aucune concaténation ; RLS en filet |
| XSS | Échappement par défaut de React, jamais de HTML brut inséré ; CSP stricte |
| CSRF | Jetons porteurs sur l'API, `SameSite` sur les cookies de session, en-tête `Origin` vérifié |
| Validation | Schémas stricts, **rejet des champs inconnus** plutôt qu'ignorance silencieuse |
| Assignation de masse | Listes blanches explicites sur tous les modèles |
| Référence directe d'objet | Autorisation vérifiée sur **chaque** ressource, jamais déduite d'une appartenance de liste |
| Dépendances | Analyse automatisée, mises à jour de sécurité suivies, versions verrouillées |
| En-têtes | CSP, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` |
| Erreurs | Messages génériques côté client, détails côté serveur uniquement |
| CORS | Origines explicitement listées, jamais `*` |

---

## 12. Gouvernance des indices

Traduction de §9.2 et §18 en contraintes de code. C'est la partie la plus inhabituelle de ce modèle de sécurité, et l'une des plus importantes : elle protège les personnes contre le produit lui-même.

### 12.1 Les cinq règles

1. **Aucun score ne restreint un droit.** Aucune politique d'autorisation ne lit `Reliability` ni `Collection`. Vérifié par un test d'architecture, pas par une revue.
2. **Tout score est décomposable** en faits datés et sourcés (`reliability_score_factors`, `risk_factors`). Un score sans facteurs est un défaut bloquant.
3. **Tout score est versionné** (`calculator_version`) et reproductible sur les mêmes entrées (§17).
4. **Aucun score n'est visible du parent ni du public.** `school_reliability` n'est jamais visible d'un autre établissement (§9.2).
5. **Aucune action restrictive n'est automatique.** Blocage d'inscription, refus de document, exclusion, suspension de service : acteur humain identifié, motif saisi, trace d'audit (`A-04`).

### 12.2 Classification des actions automatiques

| Classe | Automatisable | Exemples |
|---|---|---|
| Informationnelle | Oui, contenu factuel neutre, **sans mention d'un score** | Rappel d'échéance, notification d'absence, demande de document |
| Priorisation interne | Oui, visible du personnel uniquement | File d'actions, segmentation des créances |
| Restrictive | **Non, jamais** | Blocage, refus, exclusion, suspension |

### 12.3 Neutralité de formulation

Les libellés d'alertes et de messages vivent dans des **gabarits versionnés et revus**, jamais dans du texte assemblé à la volée. §10 impose une formulation orientée aide : « évolution inhabituelle nécessitant une attention », et non un jugement sur l'élève. Un test vérifie qu'aucun gabarit destiné à une famille ne contient de score, de niveau de risque ou de terme de qualification de la personne.

---

## 13. Conformité réglementaire

### 13.1 Loi n° 2014-038 — protection des données personnelles

| Exigence | Réponse |
|---|---|
| Finalité | `purpose` obligatoire sur chaque consentement ; portées explicites |
| Pertinence, minimisation | État civil minimal (`Q-15`) ; divulgation minimale en vérification publique ; règle du plafond (`R2`) |
| Exactitude | Correction possible, historisée ; `birth_date_precision` plutôt qu'une date inventée |
| Durée de conservation | Politique par catégorie (`Q-05`), purge et anonymisation automatisées |
| Information des personnes | Consent Center, historique des accès visible du parent |
| Droits d'accès, opposition, rectification | Export des données, correction tracée, révocation de partage |
| Pas de décision automatisée sur profilage | §12 de ce document, vérifié par test |
| Déclaration CMIL | À instruire avant le pilote (`Q-05`) |
| Transferts hors frontières | Dépend du choix d'hébergement (`Q-12`) |

### 13.2 Loi n° 2014-025 — signature électronique

Le MVP produit un document **vérifiable côté plateforme** (empreinte, identifiant, émetteur, QR), ce que §14.2 autorise explicitement. Il ne produit **pas** de signature qualifiée, et la mention obligatoire du §7.4 le dit sans ambiguïté. L'abstraction `DocumentSigner` permettra d'intégrer un prestataire conforme le jour où ce sera pertinent, sans refonte.

### 13.3 Données sensibles

§18 impose séparation et minimisation. Décision : **aucune donnée de santé ni biométrique au MVP.** La portée `health.notes` existe dans le modèle mais reste désactivée, sans écran ni endpoint. Ouvrir cette porte demande une finalité légitime établie, un cadre juridique vérifié et un stockage séparé — pas une case à cocher.

### 13.4 Ce que FANABE ne prétend pas être

§25 et §6.1 : FANABE n'est pas une identité nationale, ne concurrence pas le futur socle de l'État, et n'émet pas de document à valeur juridique renforcée. Ces limites doivent être visibles **dans le produit** (mentions, libellés, documentation utilisateur), pas seulement dans les documents de conception : c'est là que la confusion se produirait.

---

## 14. Tests de sécurité obligatoires

Aucune fonctionnalité n'est déclarée terminée sans les tests correspondants au vert (brief §5).

### 14.1 Isolation — suite `tests/Isolation/`

| # | Test |
|---|---|
| `S-01` | L'école A ne peut jamais lire les données de l'école B — matrice sur **toutes** les ressources d'API |
| `S-02` | Réponse uniforme (`404`) sur une ressource hors périmètre, jamais `403` |
| `S-03` | RLS bloque une requête SQL brute sans contexte de tenant |
| `S-04` | Une FK composite refuse une référence croisée entre établissements |
| `S-05` | Un job sans contexte de tenant échoue |
| `S-06` | Toute table du plan établissement a `school_id`, un index et une politique RLS (recensement) |
| `S-07` | Une URL signée émise pour l'école A ne donne pas accès à un fichier de l'école B |

### 14.2 Autorisation

| # | Test |
|---|---|
| `S-08` | Un parent ne voit que les enfants qu'il est autorisé à voir |
| `S-09` | `financial_contact_for` accède aux paiements et à rien d'autre |
| `S-10` | `pickup_authorized_for` n'accède à aucune donnée pédagogique |
| `S-11` | Un enseignant n'accède qu'à ses classes |
| `S-12` | `platform_admin` n'accède pas aux dossiers d'élèves sans opération motivée et journalisée |
| `S-13` | Un rôle révoqué perd l'accès immédiatement |
| `S-14` | Une école ne lit pas l'historique d'une autre sans consentement |
| `S-15` | Un consentement expiré ou révoqué coupe l'accès sans intervention manuelle |

### 14.3 Identité et anti-énumération

| # | Test |
|---|---|
| `S-16` | Checksum : 100 % des substitutions, transpositions et erreurs jumelles détectées |
| `S-17` | Collision d'identifiant : régénération, jamais de doublon |
| `S-18` | Identifiant inexistant et identifiant hors périmètre : réponses indistinguables |
| `S-19` | Le rattachement ne confirme jamais l'existence d'un identifiant |
| `S-20` | Un rattachement n'ouvre aucun accès aux données d'un autre établissement |
| `S-21` | Le débit est appliqué sur la résolution d'identifiants publics |

### 14.4 Documents et certificats

| # | Test |
|---|---|
| `S-22` | Aucun objet de stockage n'est lisible publiquement |
| `S-23` | Une URL signée expire effectivement |
| `S-24` | Le QR ne contient aucune donnée personnelle |
| `S-25` | La vérification publique ne divulgue que les champs autorisés |
| `S-26` | Un certificat révoqué renvoie `REVOKED` immédiatement |
| `S-27` | Une modification de l'artefact invalide l'empreinte |
| `S-28` | Un document externe reste étiqueté externe, y compris à l'impression |
| `S-29` | Aucun jeton de vérification n'est stocké en clair |

### 14.5 Gouvernance

| # | Test |
|---|---|
| `S-30` | Aucune politique d'autorisation n'importe `Reliability` ni `Collection` |
| `S-31` | Tout score expose ses facteurs et sa version de calcul |
| `S-32` | Aucun gabarit destiné à une famille ne contient de score ni de niveau de risque |
| `S-33` | Aucune action restrictive n'est déclenchée sans acteur humain |
| `S-34` | Un canal muet produit `unknown` et ne dégrade pas la joignabilité (`G-07`) |
| `S-35` | Une table append-only n'a aucun chemin de mise à jour ni de suppression |
| `S-36` | L'audit ne peut pas être modifié ni supprimé, même par `platform_admin` |
