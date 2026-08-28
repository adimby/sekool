# FANABE — Modèle de domaine et schéma de données

> **Statut : validé le 21 août 2026** — voir [`decisions.md`](./decisions.md).
> Étape 5 de la séquence bloquante. Traduit §16 du cahier des charges en un schéma exécutable.
> Prérequis : [`architecture.md`](./architecture.md) (modèle à deux plans) et [`identity-model.md`](./identity-model.md).

## Conventions

| Règle | Détail |
|---|---|
| Nommage | Tables au pluriel, colonnes en `snake_case`, code et identifiants en anglais |
| Clés primaires | `uuid` — **v4** pour `persons`, `documents`, `certificates` (exposées, anti-énumération) ; **v7** ailleurs (localité d'index) |
| Tenant | Toute table du plan établissement porte `school_id uuid NOT NULL` |
| Argent | `bigint`, **unités entières d'Ariary**, jamais de flottant. Colonnes suffixées `_amount` |
| Temps | `timestamptz`, stocké en UTC, affiché en `Indian/Antananarivo` |
| Immuabilité | Les tables marquées **append-only** n'admettent ni `UPDATE` ni `DELETE` — correction par écriture inverse |
| Suppression | `deleted_at` là où la suppression logique est admise ; interdite sur les tables append-only |
| Traçabilité | `created_at`, `updated_at`, et `created_by_person_id` sur toute écriture métier significative |

Les listes de colonnes ci-dessous sont **indicatives sur les champs de confort** et **normatives sur les clés, contraintes et index** : c'est là que se joue la correction du modèle.

---

## 1. Vue d'ensemble

```
PLAN PLATEFORME (sans school_id)
────────────────────────────────
  persons ──┬── person_roles
            ├── person_public_ids          (historique, résolution permanente)
            ├── person_external_identifiers (PRODIGY, plus tard)
            ├── relationships              (person → person, avec portées)
            ├── family_members ── families
            ├── user_accounts ── sessions
            ├── consents ── consent_events
            ├── documents (détenus par la famille)
            ├── external_education_periods
            └── audit_events               (append-only)

PLAN ÉTABLISSEMENT (school_id NOT NULL)
───────────────────────────────────────
  schools ──┬── school_years ── academic_terms
            ├── grade_levels ── classrooms
            ├── school_role_assignments
            ├── enrollments ── enrollment_status_changes
            │       ├── attendance_records
            │       ├── grade_entries
            │       ├── fee_assignments ── invoices ── invoice_lines
            │       │                          └── installments
            │       ├── risk_assessments ── risk_factors
            │       └── student_alerts
            ├── payer_accounts ── payments ── payment_allocations
            ├── receipts
            ├── documents (émis par l'école) ── certificates
            │                                       └── certificate_verifications
            ├── messages ── message_deliveries
            ├── workflow_rules ── workflow_runs ── workflow_actions
            ├── kit_definitions ── kit_packs ── kit_items ── kit_orders
            └── read models : school_day_snapshots, collection_dashboards…

TRANSVERSE
──────────
  trust_events (append-only) ── reliability_scores ── reliability_score_factors
```

---

## 2. Identity

### `persons` — plan plateforme

| Colonne | Type | Notes |
|---|---|---|
| `id` | `uuid` PK | **v4**, opaque, jamais affichée (§6.4) |
| `public_id` | `varchar(10)` | **`UNIQUE`**, canonique sans tirets (`748372196P`) |
| `first_name`, `last_name` | `varchar` | |
| `birth_date` | `date` | Nullable |
| `birth_date_precision` | `enum` | `exact` / `year_only` / `estimated` — voir `identity-model.md` §2.1 |
| `sex` | `enum` | `female` / `male` / `unspecified` |
| `preferred_language` | `varchar(5)` | `fr` par défaut |
| `photo_path` | `varchar` | Clé de stockage privée |
| `merged_into_person_id` | `uuid` FK → `persons.id` | Non nul ⇒ cette fiche est un alias (§9) |
| `deceased_at` | `date` | |

Contraintes et index :

```sql
UNIQUE (public_id)                                   -- autorité de l'unicité (§6.3)
CHECK  (public_id ~ '^7[0-9]{8}[ABCDEFGHJKLMNPRSTUVWXYZ]$')
INDEX  (last_name, first_name)
INDEX  gin (last_name gin_trgm_ops)                  -- détection de doublons
CHECK  (merged_into_person_id IS NULL OR merged_into_person_id <> id)
```

L'expression `CHECK` sur le format n'est pas décorative : elle interdit à tout chemin d'écriture, y compris un correctif SQL manuel, d'introduire un identifiant hors format ou porteur d'une lettre ambiguë.

### `person_public_ids`

Historique des identifiants attribués à une personne, pour que la fusion (§9) ne casse jamais un identifiant imprimé sur un document en circulation.

`person_id`, `public_id UNIQUE`, `assigned_at`, `retired_at`, `reason`.

### `person_roles`

`person_id`, `role` (`student` / `alumni` / `parent` / `guardian` / `financial_contact` / `staff` / `supplier_agent`), `acquired_at`, `ended_at`.

```sql
UNIQUE (person_id, role, acquired_at)   -- cumul autorisé, doublon exact refusé
```

Jamais de suppression : un rôle terminé porte `ended_at`. C'est ce qui rend `I-05` (l'enfant devenu parent) vérifiable.

### `relationships`

| Colonne | Type | Notes |
|---|---|---|
| `subject_person_id` | `uuid` | Le titulaire du droit (l'adulte) |
| `object_person_id` | `uuid` | La personne concernée (l'enfant) |
| `type` | `enum` | `parent_of` / `guardian_of` / `financial_contact_for` / `pickup_authorized_for` |
| `scopes` | `jsonb` | Portées accordées (§6.7) |
| `status` | `enum` | `pending` / `active` / `revoked` |
| `verification_method` | `enum` | `family_approved` / `staff_attested` / `document_verified` |
| `evidence_document_id` | `uuid` | Justificatif |
| `verified_by_person_id`, `established_at`, `revoked_at` | | |

```sql
UNIQUE (subject_person_id, object_person_id, type) WHERE status <> 'revoked'
CHECK  (subject_person_id <> object_person_id)
INDEX  (object_person_id, status)      -- « qui a des droits sur cet enfant ? »
```

L'index sur `object_person_id` est le chemin d'accès de la question la plus fréquente du système, posée à chaque contrôle d'autorisation d'un parent.

### `person_link_requests`

Support de la voie « identifiant public » (`D-18`, `D-22`). `school_id`, `submitted_public_id_hash`, `matched_person_id` (**jamais renvoyé au client**), `status` (`pending` / `approved` / `denied` / `expired`), `requested_by_person_id`, `ip_hash`, `expires_at`, `resolved_at`.

`submitted_public_id_hash` plutôt que l'identifiant en clair : une base de tentatives en clair constituerait une liste d'identifiants réels exploitable. L'école ne reçoit **aucune** coordonnée tant que le parent n'a pas confirmé.

### `family_share_tokens` — plan plateforme

Lien généré par le parent, qui **fait office de consentement** (`D-22`).

| Colonne | Type | Notes |
|---|---|---|
| `created_by_person_id` | `uuid` | Le parent |
| `token_hash` | `bytea` UNIQUE | SHA-256 de 160 bits d'aléa — **jamais le jeton en clair** |
| `child_person_ids` | `uuid[]` | Enfants concernés |
| `scopes` | `jsonb` | Portées de consentement accordées |
| `target_school_id` | `uuid` nullable | Restreint à une école, ou ouvert |
| `expires_at` | `timestamptz` | Défaut 7 jours |
| `redeemed_at`, `redeemed_by_school_id`, `redeemed_by_person_id` | | Consommé à la première rédemption |
| `revoked_at` | | Révoqué par le parent avant usage |

```sql
CHECK (redeemed_at IS NULL OR redeemed_by_school_id IS NOT NULL)
```

### `families` et `family_members`

`families` : `id`, `label`, `primary_language`, `created_by_person_id`.
`family_members` : `family_id`, `person_id`, `role_in_family` (`parent` / `child` / `other_adult`), `joined_at`, `left_at`.

Le foyer est global ; chaque école n'en voit que la projection de ses propres élèves et de leurs responsables (`R2`).

### `user_accounts`

`person_id`, `email` (nullable — beaucoup de parents n'en ont pas), `phone_e164`, `password_hash`, `totp_secret_encrypted`, `totp_enabled_at`, `last_login_at`, `failed_attempts`, `locked_until`, `must_change_password`.

```sql
UNIQUE (email) WHERE email IS NOT NULL
UNIQUE (phone_e164) WHERE phone_e164 IS NOT NULL
CHECK  (email IS NOT NULL OR phone_e164 IS NOT NULL)   -- au moins un identifiant
```

### `person_external_identifiers`

`person_id`, `system` (`prodigy`, `cin`, …), `value_encrypted`, `verification_status`, `verified_at`, `source`. Aucune logique associée avant qu'un socle national existe réellement (§10 du modèle d'identité).

---

## 3. Consent

### `consents`

| Colonne | Type | Notes |
|---|---|---|
| `subject_person_id` | `uuid` | La personne concernée |
| `granted_by_person_id` | `uuid` | Le titulaire de l'autorité (parent, ou l'intéressé majeur) |
| `grantee_school_id` | `uuid` | Destinataire |
| `scope` | `enum` | `identity.core`, `identity.contact`, `academic.records`, `academic.attendance`, `finance.history`, `documents.external`, `documents.certificates`, `health.notes` |
| `purpose` | `text` | Finalité, obligatoire (§6.6, loi 2014-038) |
| `granted_at`, `expires_at`, `revoked_at` | `timestamptz` | Défaut : 12 mois (`Q-04`) |
| `source` | `enum` | `app` / `paper` / `staff_attested` |
| `evidence_document_id`, `terms_version` | | |

```sql
INDEX (subject_person_id, grantee_school_id, scope)
    WHERE revoked_at IS NULL          -- chemin du contrôle d'autorisation
```

`consent_events` — **append-only** : `consent_id`, `event` (`granted` / `renewed` / `revoked` / `expired` / `scope_changed`), `occurred_at`, `actor_person_id`, `metadata`.

L'objet `consents` porte l'état courant, `consent_events` porte l'histoire. C'est cette dernière que le parent consulte, et elle ne peut pas être réécrite.

---

## 4. School

### `schools`

`id`, `network_id` (FK → `school_networks`, nullable — `Q-20`), `name`, `short_name`, `code UNIQUE`, `address`, `city`, `region`, `phone_e164`, `email`, `logo_path`, `timezone` (défaut `Indian/Antananarivo`), `currency` (`MGA`), `locale`, `status` (`active` / `suspended` / `archived`), `plan` (`starter` / `plus` / `network` — §19.1), `settings jsonb`.

Le statut `suspended` est ce qui rend §19.1 tenable : une école qui cesse de payer voit son plan tenant gelé, sans que l'identité et les documents de la famille (plan plateforme) soient affectés.

### `school_networks`

Table **plateforme** (pas de `school_id`, pas de RLS) : `id`, `name UNIQUE`. Un campus n’est pas un tenant. `Q-20` : métadonnées (noms des campus) maintenant ; dossiers consolidés plus tard.

### `school_years`, `academic_terms`

`school_years` : `school_id`, `label` (`2026-2027`), `starts_on`, `ends_on`, `is_current`.
`academic_terms` : `school_id`, `school_year_id`, `label`, `sequence`, `starts_on`, `ends_on` (`Q-14`).

```sql
UNIQUE (school_id, label)
EXCLUDE USING gist (school_id WITH =, daterange(starts_on, ends_on) WITH &&)
```

La contrainte d'exclusion empêche deux années scolaires de se chevaucher dans une même école — le genre d'incohérence qui produit ensuite des factures en double sans qu'on comprenne pourquoi.

### `grade_levels`, `classrooms`

`grade_levels` : `school_id`, `name` (`6ᵉ`), `stage` (`preschool` / `primary` / `middle` / `high`), `sequence` (`Q-19`, `D-23`).
`classrooms` : `school_id`, `school_year_id`, `grade_level_id`, `name` (`6ᵉ A`), `capacity`, `series` (nullable, lycée), `main_teacher_person_id`.

Le `stage` est le **cycle** de l’école, pas un type d’établissement. Une même école peut porter plusieurs cycles. La fiche classe en hérite ; familles, finance et caisse ne se dupliquent pas. Voir [`cycles.md`](./cycles.md).

```sql
FOREIGN KEY (school_id, grade_level_id) REFERENCES grade_levels (school_id, id)
FOREIGN KEY (school_id, school_year_id) REFERENCES school_years (school_id, id)
```

Ces clés composites sont la **barrière 4** de l'isolation : une classe ne peut pas référencer le niveau d'une autre école, y compris sous l'effet d'un paramètre falsifié.

### `school_role_assignments`

`school_id`, `person_id`, `role` (rôles applicatifs, `identity-model.md` §4), `granted_at`, `revoked_at`, `granted_by_person_id`.

```sql
UNIQUE (school_id, person_id, role) WHERE revoked_at IS NULL
```

---

## 5. Enrollment

### `enrollments`

| Colonne | Type | Notes |
|---|---|---|
| `school_id`, `school_year_id`, `classroom_id` | `uuid` | Clés composites |
| `person_id` | `uuid` | → plan plateforme |
| `student_number` | `varchar` | Numéro **interne** à l'école, distinct du Person ID |
| `status` | `enum` | `pre_registered` / `active` / `suspended` / `transferred_out` / `graduated` / `withdrawn` |
| `enrolled_on`, `ended_on`, `exit_reason` | | |

```sql
UNIQUE (school_id, school_year_id, person_id)   -- une inscription par année et par école
UNIQUE (school_id, student_number)
FOREIGN KEY (school_id, classroom_id) REFERENCES classrooms (school_id, id)
INDEX (school_id, status, school_year_id)
-- Une seule inscription active à la fois dans TOUT le réseau FANABE (D-19) :
CREATE UNIQUE INDEX enrollments_one_active_per_person
  ON enrollments (person_id) WHERE status = 'active';
```

`enrollment_status_changes` — **append-only** : `enrollment_id`, `from_status`, `to_status`, `reason`, `occurred_at`, `actor_person_id`. Un statut n'est jamais écrasé sans laisser de trace ; « pourquoi cet élève est-il parti en mars ? » a une réponse.

### `enrollment_transfers`

Objet de premier plan du transfert (`D-18`, `D-19`). Ne porte **pas** de `school_id` unique : il relie deux établissements. Isolation : chaque école ne voit que les transferts où elle est origine ou accueil.

| Colonne | Type | Notes |
|---|---|---|
| `person_id` | `uuid` | L'élève |
| `origin_school_id`, `origin_enrollment_id` | `uuid` | École et inscription à quitter |
| `destination_school_id` | `uuid` | École d'accueil |
| `requested_by_person_id` | `uuid` | Agent de l'école d'accueil, ou parent |
| `parent_approved_at`, `parent_approved_by_person_id` | | Validation parent |
| `origin_school_approved_at`, `origin_approved_by_person_id` | | Détachement |
| `status` | `enum` | `pending_parent` / `pending_origin_school` / `approved` / `rejected` / `cancelled` / `completed` |
| `completed_at`, `rejected_at`, `rejection_reason` | | |

Un transfert ne passe à `approved` que lorsque **les deux** validations sont présentes. L'application à `completed` ferme l'inscription d'origine (`transferred_out`) et ouvre l'inscription d'accueil dans une **même transaction**.

### `external_education_periods` — plan plateforme

`person_id`, `school_label` (texte libre — ce n'est pas un tenant), `starts_on`, `ends_on`, `declared_grade_level`, `declared_by_person_id`, `verification_status`, `notes`.

Volontairement dans le plan plateforme : cette période n'appartient à aucun établissement FANABE, et elle doit survivre à la désactivation de n'importe quel tenant.

---

## 6. Academic

### `attendance_records`

`school_id`, `enrollment_id`, `date`, `session` (`morning` / `afternoon` / `full_day`), `status` (`present` / `absent` / `late` / `excused`), `minutes_late`, `reason`, `recorded_by_person_id`, `recorded_via` (`web` / `offline_sync`), `client_reference` (idempotence de la file hors ligne, `Q-07`).

```sql
UNIQUE (school_id, enrollment_id, date, session)
UNIQUE (school_id, client_reference) WHERE client_reference IS NOT NULL
INDEX  (school_id, date, status)     -- compteurs du cockpit
```

Le `client_reference` unique est ce qui rend le rejeu de la file hors ligne inoffensif : un envoi dupliqué après reconnexion ne crée pas un second enregistrement.

### `grade_entries`

Modélisé maintenant, implémenté en phase 7 (`mvp-scope.md`). `school_id`, `enrollment_id`, `academic_term_id`, `subject_id`, `value`, `max_value`, `coefficient`, `assessed_on`, `recorded_by_person_id`. Nécessaire au Student Early Warning (§10).

---

## 7. Finance

Module entièrement **append-only** sur les mouvements : un paiement erroné se corrige par une écriture d'annulation, jamais par un `UPDATE`. C'est la condition d'une réconciliation possible et d'un reçu défendable (`Q-11`).

### `fee_schedules`, `fee_items`

`fee_schedules` : `school_id`, `school_year_id`, `grade_level_id`, `name`, `status`.
`fee_items` : `school_id`, `fee_schedule_id`, `label` (« Écolage T1 »), `amount` (`bigint`, Ariary), `due_on`, `category` (`tuition` / `registration` / `exam` / `other`), `is_recurring`.

### `payer_accounts`

Résolution de `G-02` / `Q-06` : compte payeur par (établissement, famille).

`school_id`, `family_id`, `responsible_person_id`, `balance_amount` (dérivé, recalculable), `status`.

```sql
UNIQUE (school_id, family_id, responsible_person_id)
```

L'unicité inclut le responsable : elle autorise deux comptes payeurs distincts dans la même école pour des parents séparés (`Q-06`), situation fréquente qu'un modèle plus rigide rendrait impossible à représenter.

### `invoices`, `invoice_lines`, `installments`

`invoices` : `school_id`, `enrollment_id`, `payer_account_id`, `school_year_id`, `number` (séquence par école et par année, sans trou — `Q-11`), `issued_on`, `total_amount`, `discount_amount`, `net_amount`, `status` (`draft` / `issued` / `partially_paid` / `paid` / `cancelled`).

`invoice_lines` : `invoice_id`, `fee_item_id`, `label`, `amount`, `discount_amount`, `discount_reason` (**obligatoire si remise non nulle**).

`installments` : `school_id`, `invoice_id`, `sequence`, `due_on`, `amount`, `paid_amount`, `status` (`pending` / `partially_paid` / `paid` / `overdue`).

```sql
UNIQUE (school_id, school_year_id, number)         -- numérotation légale
CHECK  (net_amount = total_amount - discount_amount)
CHECK  (discount_amount = 0 OR discount_reason IS NOT NULL)
INDEX  (school_id, status, due_on)                 -- créances échues
```

L'échéance (`installments`) est l'unité du recouvrement : c'est sur elle que se calculent l'ancienneté de dette et la ponctualité (§8.1), pas sur la facture.

### `payments`, `payment_allocations`

`payments` — **append-only** : `school_id`, `payer_account_id`, `amount`, `method` (`cash` / `bank_transfer` / `mobile_money` / `cheque` / `other`), `received_on`, `reference`, `recorded_by_person_id`, `idempotency_key UNIQUE`, `reversed_by_payment_id`, `notes`.

`payment_allocations` : `payment_id`, `installment_id`, `amount`. Règle d'affectation par défaut : échéance la plus ancienne d'abord, sauf imputation manuelle explicite (`Q-06`).

```sql
UNIQUE (school_id, idempotency_key)
CHECK  (amount > 0)                       -- une annulation est une écriture liée, pas un montant négatif
INDEX  (school_id, received_on)           -- encaissement du jour (§11)
```

### `receipts`

`school_id`, `payment_id`, `number` (séquence sans trou), `issued_at`, `issued_by_person_id`, `document_id`, `cancelled_by_receipt_id`.

Séquence attribuée **en transaction**. Une annulation produit un avoir, jamais une suppression (`Q-11`).

---

## 8. Collection

### `risk_assessments`

`school_id`, `enrollment_id`, `payer_account_id`, `level` (`low` / `medium` / `high` / `critical` — brief §3), `outstanding_amount`, `days_overdue`, `on_time_ratio`, `calculator_version`, `computed_at`, `manual_override_level`, `override_reason`, `override_until`, `override_by_person_id`.

`risk_factors` : `risk_assessment_id`, `factor_key`, `human_label`, `contribution`, `evidence` (`jsonb` : références aux échéances et paiements concernés).

Deux points essentiels :

- **`calculator_version`** est exigé par §17 (« versionnement des règles de calcul des indices pour permettre l'audit »). Sans lui, un score du passé n'est plus reproductible et l'audit devient impossible.
- **`risk_factors` est la table qui rend le score défendable.** Un niveau « Critique » sans la liste des faits qui le produisent serait exactement le score opaque que §9.2 et §18 interdisent. Cette table n'est pas un confort d'affichage, c'est une obligation.

Les seuils par défaut figurent dans `Q-08`, paramétrables par école dans des bornes fixées par la plateforme.

### `collection_forecasts`

`school_id`, `week_starting_on`, `expected_amount`, `confidence_low_amount`, `confidence_high_amount`, `method_version`, `computed_at`. Méthode explicable détaillée en `A-05` — aucune inférence non décomposable.

---

## 9. Reliability — transverse

### `trust_events` — **append-only**

| Colonne | Type | Notes |
|---|---|---|
| `subject_type` | `enum` | `family` / `school` / `relationship` |
| `subject_id` | `uuid` | |
| `school_id` | `uuid` **nullable** | Nul pour un indice d'établissement calculé par la plateforme |
| `event_type` | `varchar` | `payment_on_time`, `payment_late`, `document_provided`, `message_answered`, `school_responded_within_sla`… |
| `occurred_at` | `timestamptz` | Date du **fait**, pas de l'enregistrement |
| `source_type`, `source_id` | | Traçabilité vers le fait d'origine |
| `metadata` | `jsonb` | |

```sql
INDEX (subject_type, subject_id, occurred_at)
INDEX (event_type, occurred_at)
```

> **Émis dès la phase 1**, bien qu'aucun indice ne soit calculé avant la phase 4 (`A-09`). Un indice non calculé se calcule plus tard ; un événement non capté est perdu définitivement. C'est la seule partie de la phase 4 qu'il serait coûteux de reporter.

### `reliability_scores`, `reliability_score_factors`

`reliability_scores` : `subject_type`, `subject_id`, `index_type` (`family_reliability` / `school_reliability` / `relationship_health`), `value`, `band`, `calculator_version`, `computed_at`, `inputs_digest`, `event_count`. Unique `(school_id, subject_type, subject_id, index_type, calculator_version)` : une nouvelle version de calculateur conserve l'historique.

`reliability_score_factors` : `score_id`, `event_type`, `human_label`, `contribution`, `event_count`, `sample_event_ids`.

`reliability_score_factors` : `score_id`, `event_type`, `human_label`, `contribution`, `event_count`, `sample_event_ids`.

`inputs_digest` permet de vérifier qu'un score recalculé sur les mêmes faits donne le même résultat — c'est la reproductibilité exigée par §17.

**Contraintes de gouvernance, à faire respecter par le code et par les tests :**

1. Aucune politique d'autorisation n'importe le module `Reliability` (`M-08` et `S-30`, traduction de §18).
2. `school_reliability` n'est jamais visible d'un autre établissement, ni du public (§9.2, `A-05`).
3. Aucun score n'apparaît dans un message adressé à un parent (`A-04`).

---

## 10. Student Early Warning

`student_alerts` : `school_id`, `enrollment_id`, `category` (`grades_decline` / `absence_increase` / `lateness_pattern` / `homework_decline` / `combined`), `severity` (`info` / `attention` / `priority`), `reason_summary`, `detected_at`, `detector_version`, `recommended_action`, `status` (`open` / `acknowledged` / `in_progress` / `resolved` / `dismissed`), `acknowledged_by_person_id`, `resolved_at`, `resolution_notes`.

`student_alert_signals` : `alert_id`, `signal_type`, `observed_value`, `baseline_value`, `window_start`, `window_end`, `evidence`.

**Contrainte rédactionnelle, appliquée aux gabarits et testée :** la formulation est neutre et orientée aide (§10, brief §3). « Évolution inhabituelle nécessitant une attention », jamais « élève en difficulté » ni aucun jugement sur la personne. Les libellés vivent dans des gabarits versionnés et revus, pas dans du texte généré à la volée.

---

## 11. Workflow

`workflow_rules` : `school_id`, `template_key`, `enabled`, `params jsonb`, `version`, `dry_run`, `daily_action_cap`, `quiet_hours jsonb`, `updated_by_person_id`.

`workflow_runs` : `school_id`, `rule_id`, `trigger_event_type`, `trigger_event_id`, `subject_type`, `subject_id`, `idempotency_key`, `status`, `started_at`, `finished_at`, `error`.

`workflow_actions` : `run_id`, `type` (`notify` / `create_task` / `flag_document` / `escalate`), `status`, `payload`, `attempts`, `last_error`.

```sql
UNIQUE (school_id, rule_id, idempotency_key)   -- garde-fou central (G-08)
```

Cette contrainte d'unicité est le garde-fou le plus efficace du moteur : la clé d'idempotence étant construite sur (règle, sujet, fenêtre), la base elle-même empêche d'envoyer deux fois la même relance, y compris si le job est rejoué ou si deux workers traitent le même événement.

Templates du MVP : `repeated_absence`, `payment_overdue`, `missing_document` (brief §3). Aucun scripting fourni par le tenant (`Q-13`).

---

## 12. Communication

`message_templates` : `school_id` (nullable si fourni par la plateforme), `key`, `channel`, `locale`, `subject`, `body`, `version`.

`messages` : `school_id`, `template_key`, `subject_person_id`, `recipient_person_id`, `channel` (`in_app` / `sms` / `whatsapp` / `email` / `print`), `payload`, `queued_at`, `sent_at`, `priority`, `workflow_run_id`, `idempotency_key`.

`message_deliveries` — **append-only** : `message_id`, `status` (`queued` / `sent` / `delivered` / `read` / `answered` / `failed` / **`unknown`**), `occurred_at`, `provider_reference`, `error_code`.

```sql
UNIQUE (school_id, idempotency_key)
INDEX  (school_id, channel, queued_at)
```

`contact_preferences` : `person_id`, `channel`, `priority`, `opted_out_at`, `verified_at`.

> **`unknown` est un statut de première classe**, distinct de `not_read`. Résolution de `G-07` : un canal qui ne peut structurellement pas rapporter la lecture (papier, SMS sans rapport de livraison) produit `unknown`, et ces événements sont **exclus** du calcul de joignabilité au lieu de le dégrader. Sans cette règle, le système classerait les familles les moins équipées comme les moins fiables — exactement ce que §9.2 et §18 interdisent. À couvrir par un test dédié.

---

## 13. Documents et certificats

### `documents`

| Colonne | Type | Notes |
|---|---|---|
| `id` | `uuid` PK | **v4** (exposée) |
| `school_id` | `uuid` **nullable** | **Nul = document détenu par la famille** — condition de §19.1/§19.2 |
| `owner_person_id` | `uuid` | |
| `type` | `enum` | `report_card` / `enrollment_certificate` / `birth_certificate` / `receipt` / `other` |
| `source_type` | `enum` | `native` / `external` (brief §3) |
| `source_school_label` | `varchar` | Libellé libre pour un document externe |
| `issuer_school_id` | `uuid` | Émetteur si natif |
| `verification_status` | `enum` | `unverified` / `attested_by_school` / `verified_by_issuer` / `disputed` (`G-06`) |
| `uploaded_by_person_id`, `uploaded_at` | | Brief §3 |
| `storage_key`, `sha256`, `byte_size`, `mime_type` | | |
| `version`, `supersedes_document_id` | | Versionnement plutôt qu'écrasement |
| `provenance` | `jsonb` | Chaîne complète des acteurs |

`document_verification_events` — **append-only** : `document_id`, `from_status`, `to_status`, `actor_person_id`, `actor_school_id`, `method`, `evidence`, `occurred_at`.

Le `school_id` nullable est le mécanisme qui permet à une famille de conserver ses documents quand son école quitte la plateforme (§19.2). Ce n'est pas une souplesse de modélisation, c'est l'implémentation d'une promesse commerciale.

### `certificates`

`school_id`, `document_id`, `subject_person_id`, `enrollment_id`, `type`, `public_reference`, `issued_at`, `expires_at`, `status` (`valid` / `revoked` / `expired`), `revoked_at`, `revocation_reason`, `template_version`, `payload_snapshot jsonb`, `artifact_sha256`, `signer_key_id`, `signature`.

`payload_snapshot` fige les données au moment de l'émission : un certificat émis en 2026 doit rester lisible tel qu'émis, même si l'élève change de classe ensuite. `artifact_sha256` porte sur l'**artefact PDF stocké**, rendu une seule fois (résolution de `G-05` — le hash d'un PDF régénéré serait instable et la vérification échouerait systématiquement).

### `certificate_verification_tokens`

`certificate_id`, `token_hash` (SHA-256 de 160 bits d'aléa — **jamais le jeton en clair**), `created_at`, `expires_at`, `revoked_at`.

### `certificate_verifications` — **append-only**

`token_id`, `verified_at`, `ip_hash`, `user_agent_hash`, `outcome`.

Table utile à trois titres : elle alimente le KPI « nombre de certificats vérifiés » (§23), elle permet de détecter un balayage automatisé, et elle donne au parent la visibilité sur l'usage de ses documents.

---

## 14. School Kit

`kit_definitions` : `school_id`, `school_year_id`, `grade_level_id`, `name`, `status`, `price_source` (`supplier` / `purchasing`), `copied_from_id`.
`kit_needs` : `kit_definition_id`, `label` (« Cahier 200 pages »), `quantity`, `notes`.
`suppliers` : `id`, `name`, `contact`, `commission_rate_bps` (points de base — évite l'arrondi d'un pourcentage flottant), `status`.
`kit_packs` : `kit_definition_id`, `supplier_id`, `tier` (`eco` / `standard` / `premium` affiché Luxe — §13.1), `total_amount` (somme des lignes), `available_from`, `available_until`.
`kit_pack_items` : `kit_pack_id`, `need_id`, `brand`, `product_reference`, `unit_amount`, `quantity`.
`kit_orders` : `school_id`, `payer_account_id`, `enrollment_id`, `kit_definition_id`, `kit_pack_id` (null si le parent fournit), `fulfillment` (`partner` / `self`), `status` (`draft` / `submitted` / `confirmed` / `fulfilled` / `self_supplied` / `cancelled`), `total_amount`, `commission_amount`, `supplier_id`, `placed_at`.

À l’inscription (ou au début d’année), l’école publie **une liste par niveau**. Direction ou titulaire de classe. Marques et prix des trois gammes (éco / standard / luxe) viennent du fournisseur partenaire **ou** du service achat. Le parent commande une gamme chez le partenaire, ou fournit lui-même. La liste se recopie d’une année sur l’autre.

Chaîne `School → FANABE → Supplier → Parent` (brief §3). Aucun encaissement par FANABE au MVP (`Q-03`), aucun catalogue transverse, aucune mise en concurrence (`A-06`).

---

## 15. Audit — plan plateforme, append-only

### `audit_events`

`id`, `occurred_at`, `actor_person_id`, `actor_school_id`, `actor_role`, `action`, `resource_type`, `resource_id`, `subject_person_id`, `context` (`jsonb` : IP hachée, agent, motif), `outcome` (`allowed` / `denied`).

```sql
INDEX (subject_person_id, occurred_at)   -- « qui a consulté mon dossier ? »
INDEX (actor_school_id, occurred_at)
INDEX (action, occurred_at)
```

Ni `UPDATE` ni `DELETE`, y compris pour un `platform_admin`. Une demande d'effacement ne réécrit pas l'audit (`A-03`) : l'audit est la garantie du parent, il ne peut pas être effacé par celui qu'il protège ni par celui qu'il surveille.

Sont journalisés : consultation d'une fiche `Person`, téléchargement de document, vérification de certificat, octroi ou révocation de consentement, tentative de rattachement, export, écriture financière, dérogation manuelle sur un niveau de risque, changement de rôle. Volontairement **pas** toute lecture : un journal saturé de bruit ne se lit pas, donc ne sert pas.

---

## 16. Modèles de lecture

Alimentés par événements, jamais écrits directement, reconstructibles à tout moment (`architecture.md` §10).

| Table | Contenu | Fraîcheur |
|---|---|---|
| `school_day_snapshots` | Compteurs du cockpit : attendus, présents, absents, retards, encaissé, attendu, à risque, actions (§11) | Temps réel sur les compteurs critiques, ≤ 5 min sur les agrégats |
| `collection_dashboards` | Créances par tranche d'ancienneté, par classe, par niveau (§8.2) | ≤ 15 min |
| `family_financial_summaries` | Solde consolidé multi-écoles pour le parent | Temps réel |
| `student_attention_lists` | Élèves nécessitant une attention | ≤ 15 min |

Chaque ligne porte son `computed_at`, affiché à l'utilisateur : un chiffre sans indication de fraîcheur est un chiffre auquel on ne peut pas se fier.

---

## 17. Invariants du modèle

Traduits en tests d'architecture exécutés en CI.

| # | Invariant |
|---|---|
| `M-01` | Toute table du plan établissement a `school_id NOT NULL`, un index le contenant en tête, et une politique RLS active |
| `M-02` | Toute FK entre deux tables du plan établissement est **composite** et inclut `school_id` |
| `M-03` | Aucune table du plan plateforme ne possède de colonne `school_id` |
| `M-04` | Les tables append-only n'ont ni chemin de mise à jour ni chemin de suppression dans le code |
| `M-05` | Tout montant est un `bigint` et transite par le value object `Money` |
| `M-06` | Tout score et toute évaluation de risque portent un `calculator_version` |
| `M-07` | Toute donnée dérivée est reconstructible depuis les faits (test de reconstruction) |
| `M-08` | Aucune politique d'autorisation n'importe `Reliability` ni `Collection` |
| `M-09` | Tout document porte une provenance complète et non nulle |
| `M-10` | Aucun jeton de vérification n'est stocké en clair |
