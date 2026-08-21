# FANABE

**L'école, la famille, connectées.**

Plateforme de pilotage scolaire et familial pour Madagascar : identité portable, recouvrement intelligent, suivi de la relation école-famille, intelligence opérationnelle et School Kit.

FANABE n'est pas un ERP scolaire de plus. C'est une infrastructure de confiance et de pilotage reliant établissement, famille et élève, conçue pour un environnement où la digitalisation est hétérogène.

---

## État du projet

**Conception validée le 21 août 2026. Implémentation en cours — phase 0 (architecture).**

Décisions figées dans [`docs/decisions.md`](./docs/decisions.md).

## Documentation

| Document | Objet |
|---|---|
| [`docs/README.md`](./docs/README.md) | Index et ordre de lecture |
| [`docs/decisions.md`](./docs/decisions.md) | Décisions actées |
| [`docs/spec-audit.md`](./docs/spec-audit.md) | Audit de cohérence du cahier des charges |
| [`docs/open-questions.md`](./docs/open-questions.md) | Ambiguïtés à trancher |
| [`docs/architecture.md`](./docs/architecture.md) | Architecture et choix techniques |
| [`docs/identity-model.md`](./docs/identity-model.md) | Identité portable et FANABE Person ID |
| [`docs/domain-model.md`](./docs/domain-model.md) | Modèle de domaine et schéma de données |
| [`docs/security-model.md`](./docs/security-model.md) | Sécurité, confidentialité, conformité |
| [`docs/mvp-scope.md`](./docs/mvp-scope.md) | Périmètre du MVP et plan de phases |

Le document fonctionnel de référence est [`FANABE_Cahier_des_charges_SchoolOS_Madagascar.docx`](./FANABE_Cahier_des_charges_SchoolOS_Madagascar.docx). En cas de conflit, il fait foi.

## Pile technique cible

**Backend** — Laravel 13, PHP 8.4, API REST, PostgreSQL 18 (avec Row Level Security), Redis, stockage objet S3-compatible.
**Frontend** — React 19, TypeScript, Vite, Tailwind CSS, PWA, React Router, TanStack Query, Zod.
**Architecture** — Monolithe modulaire organisé par domaine métier, multi-tenant strict, intégrations externes derrière des adaptateurs.

Justifications détaillées dans [`docs/architecture.md`](./docs/architecture.md#13-choix-techniques-et-justifications).

## Démarrage local (phase 0)

Prérequis : PHP 8.3+, Composer, PostgreSQL, Redis, Node 22+.

```bash
# Base et rôle applicatif (non-superuser, sans BYPASSRLS)
createdb fanabe && createdb fanabe_test
# ou : make up  (PostgreSQL / Redis / MinIO / Mailpit)

cp api/.env.example api/.env
# renseigner DB_* puis :
cd api && composer install && php artisan key:generate && php artisan migrate --seed

cd ../web && npm install && npm run dev
# API : cd api && php artisan serve
```

Comptes de démonstration (mot de passe `password`) :

- `direction.antsahabe@fanabe.test` — École Antsahabe
- `direction.ambohipo@fanabe.test` — École Ambohipo

```bash
make test              # unitaires, fonctionnels, isolation, architecture
make test-isolation    # école A ne lit jamais l'école B
```

Les tests d'isolation s'exécutent sur PostgreSQL réel (jamais SQLite) : Row Level Security n'existe pas ailleurs.

## Principes non négociables

1. **L'isolation des dossiers ne se négocie pas.** Cloisonnement par établissement à tous les niveaux — jamais un masquage côté interface.
2. **Les faits avant les états.** On enregistre ce qui s'est produit ; les états dérivés sont recalculables.
3. **Le cœur fonctionne seul.** Aucune fonctionnalité essentielle ne dépend d'une API externe payante.
4. **Tout score est explicable.** Un indice qu'on ne peut pas décomposer en faits datés n'est pas livrable.
5. **Rien d'automatique ne restreint un droit.** L'automatisation informe et priorise ; elle ne sanctionne jamais.
6. **L'action avant le rapport.** Chaque écran répond à « que dois-je faire maintenant, et pourquoi ».
7. **L'identité n'est jamais une clé d'accès.** Le FANABE Person ID identifie ; il n'authentifie pas.

## Licence

Non déterminée.
