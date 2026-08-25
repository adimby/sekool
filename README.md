# FANABE

**L'école, la famille, connectées.**

Plateforme de pilotage scolaire et familial pour Madagascar : identité portable, recouvrement intelligent, suivi de la relation école-famille, intelligence opérationnelle et School Kit.

FANABE n'est pas un ERP scolaire de plus. C'est une infrastructure de confiance et de pilotage reliant établissement, famille et élève, conçue pour un environnement où la digitalisation est hétérogène.

---

## État du projet

**Conception validée le 21 août 2026. Phases 0 et 1 livrées** (identité, inscriptions, transferts). Phase 2 (classes, présence, frais) pas encore ouverte.

## Tester sur votre machine (recommandé)

Il n’y a pas encore de domaine `fanabe.mg`. Pour une **URL publique de démo**, Render (Docker) est le plus simple. Vercel n’héberge pas Laravel + PostgreSQL/RLS de façon fiable.

### Pourquoi pas Vercel

Vercel est fait pour du frontend (React) et des fonctions serverless. FANABE a besoin d’un process PHP durable, de PostgreSQL avec Row Level Security, et (plus tard) de Redis. Un runtime PHP communautaire existe, ce n’est pas un socle pour ce produit.

Tu pourras un jour mettre **seulement** le frontend sur Vercel, avec l’API ailleurs. Pour tester aujourd’hui, un seul service qui sert l’interface et l’API est plus simple.

### Render (recommandé pour la démo)

1. Compte sur [render.com](https://render.com), GitHub connecté à `adimby/sekool`.
2. **New → Blueprint** → ce dépôt, branche `cursor/fanabe-architecture-docs-3345` (`render.yaml`).
3. Attendre le build Docker. L’URL ressemble à `https://fanabe-xxxx.onrender.com`.
4. Comptes : `direction.antsahabe@fanabe.test` / `password`, `parent.andry@fanabe.test` / `password`.

Le premier démarrage crée les extensions Postgres, migre, et sème les personas. Les redémarrages ne réécrasent pas les données.

**À savoir :** c’est une démo (mots de passe publics, pas de Redis). Le plan gratuit Render peut s’endormir après inactivité et le Postgres gratuit est purgé au bout de 90 jours. Ce n’est pas la prod FANABE.

Sans Blueprint : New → PostgreSQL, puis New → Web Service → Docker, Dockerfile à la racine, `DATABASE_URL` = Internal Database URL, `APP_ENV=production`, `APP_DEBUG=false`.

### Autres alternatives

| Plateforme | Verdict |
|---|---|
| **Railway** | Même `Dockerfile` à la racine, Postgres en un clic. Très proche de Render. |
| **Fly.io** | `fly launch` sur le Dockerfile, plus de contrôle, un peu plus de CLI. |
| **Laravel Cloud** | Le plus natif pour Laravel, souvent plus cher pour une simple démo. |
| **VPS** (Hetzner, OVH, Contabo, etc.) | `make vps` : Docker construit l’image et sert FANABE sur le port 80. |

### VPS (`make vps`) — si `make up` échoue sur le port 6379

`make up` ne lance **pas** FANABE : seulement Postgres, Redis, MinIO et Mailpit, pour développer l’API sur la machine. Sur un VPS, le port **6379** est souvent déjà pris par un Redis installé (erreur `Bind for 0.0.0.0:6379 failed: port is already allocated`). Redis n’est pas requis pour la démo phase 1.

```bash
cd /opt/project/sekool   # ou le chemin du clone
git fetch origin
git checkout cursor/fanabe-architecture-docs-3345
git pull

# Arrêter les conteneurs déjà créés (Mailpit / MinIO ont pu démarrer)
docker compose down

cp -n .env.example .env
# Éditer .env : APP_URL=http://VOTRE_IP   (ou https://votre-domaine)
# Si le port 80 est pris : FANABE_HTTP_PORT=8080

make vps
```

Ouvrir `http://VOTRE_IP` (ou `:8080`). Comptes : `direction.antsahabe@fanabe.test` / `password`.

Postgres n’est plus exposé sur Internet ; seuls HTTP (et le Redis interne au réseau Docker, sans port hôte) restent. La clé Laravel est persistée dans le volume `fanabe_keys` pour survivre aux rebuilds.

Si 5432 est aussi occupé, `make vps` n’en a pas besoin : l’app parle à Postgres **dans** Docker.

Prérequis local (sans `make vps`) : PHP 8.3+, Composer, PostgreSQL, Node 22+. Branche : `cursor/fanabe-architecture-docs-3345` (PR #1).

```bash
git clone https://github.com/adimby/sekool.git
cd sekool
git checkout cursor/fanabe-architecture-docs-3345

# PostgreSQL / Redis / MinIO / Mailpit
make up

cp -n api/.env.example api/.env
cd api && composer install && php artisan key:generate && php artisan migrate --seed && php artisan serve
```

Dans un second terminal :

```bash
cd web && npm install && npm run dev
```

Ouvrir **http://127.0.0.1:5173**

| Compte | Email | Mot de passe | Ce que vous voyez |
|---|---|---|---|
| Direction Antsahabe | `direction.antsahabe@fanabe.test` | `password` | Inscrire une famille, liste des personnes liées |
| Direction Ambohipo | `direction.ambohipo@fanabe.test` | `password` | Idem, autre école (isolation) |
| Parent Andry (persona A) | `parent.andry@fanabe.test` | `password` | Ses deux enfants, deux écoles |
| Parent de Fanja | `parent.d@fanabe.test` | `password` | Espace famille |

Après une inscription, un **code d'invitation** s'affiche : onglet « Code d'invitation » pour activer le compte parent (email + mot de passe choisis).

`make demo` rappelle ces commandes. Les tests : `cd api && php artisan test` (PostgreSQL obligatoire, jamais SQLite).

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
