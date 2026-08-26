# FANABE — Documentation de conception

> **FANABE — L'école, la famille, connectées.**
>
> **État du projet : conception validée le 21 août 2026. Phases 0, 1 et 2 livrées.**
> Décisions figées dans [`decisions.md`](./decisions.md).

## Source de vérité

Le document fonctionnel de référence est [`FANABE_Cahier_des_charges_SchoolOS_Madagascar.docx`](../FANABE_Cahier_des_charges_SchoolOS_Madagascar.docx) (v0.1, cadrage fonctionnel, 27 sections). **En cas de conflit entre ce document et la documentation technique ci-dessous, le cahier des charges fait foi.** Toute divergence proposée est signalée explicitement et fait l'objet d'une question ouverte.

## Ordre de lecture

| # | Document | Objet | Étape |
|---|---|---|---|
| 1 | [`spec-audit.md`](./spec-audit.md) | Audit de cohérence du cahier des charges : tensions structurelles, divergences avec le brief, lacunes | 2 |
| 2 | [`open-questions.md`](./open-questions.md) | 20 ambiguïtés (historique) | 3 |
| 2b | [`decisions.md`](./decisions.md) | Réponses actées — **lire en priorité** | 8 |
| 3 | [`architecture.md`](./architecture.md) | Modèle à deux plans, découpage modulaire, isolation multi-tenant, ports et adaptateurs, choix techniques justifiés | 4 et 7 |
| 4 | [`identity-model.md`](./identity-model.md) | FANABE Person ID, checksum, rattachement, données externes, Consent Center | 4 |
| 5 | [`domain-model.md`](./domain-model.md) | Entités, schéma de données, contraintes, index, invariants | 5 |
| 6 | [`security-model.md`](./security-model.md) | Menaces, isolation, authentification, autorisation, gouvernance des indices, conformité | 4 |
| 7 | [`mvp-scope.md`](./mvp-scope.md) | Périmètre du MVP, plan de phases, définition de terminé, jeu de démonstration | 6 |

Pour une lecture rapide : [`decisions.md`](./decisions.md), puis `architecture.md` §3 (le modèle à deux plans).

## Les trois idées à retenir

**1. Le modèle à deux plans.** L'identité vit hors de tout établissement ; les dossiers vivent dans un établissement et y restent. C'est la résolution de la contradiction centrale du cahier des charges — identité portable *et* cloisonnement strict — et elle repose sur trois règles : un établissement ne voit une personne que s'il a un lien avec elle, il n'en voit qu'un état civil minimal, et toute donnée produite par un autre établissement exige un consentement. Voir [`architecture.md`](./architecture.md#3-le-modèle-à-deux-plans).

**2. L'isolation est structurelle, pas procédurale.** Cinq barrières empilées, dont deux (RLS PostgreSQL et clés étrangères composites) restent efficaces même quand le code applicatif est fautif — ce qui est le cas de la plupart des fuites réelles. Voir [`security-model.md`](./security-model.md#2-isolation-multi-tenant).

**3. Aucun automatisme ne restreint un droit.** Les indices informent et priorisent ; ils ne refusent, ne bloquent et ne sanctionnent jamais. Traduit en code par un invariant testé : aucune politique d'autorisation ne lit un score. Voir [`security-model.md`](./security-model.md#12-gouvernance-des-indices).

## Décisions

Toutes les questions bloquantes sont tranchées. Voir [`decisions.md`](./decisions.md).

## Conventions

- **Documentation en français**, code et identifiants en anglais.
- Les documents portent des identifiants stables (`A-nn` constats d'audit, `G-nn` lacunes, `Q-nn` questions, `D-nn` décisions, `I-nn` / `S-nn` / `M-nn` invariants testables) afin d'être référençables sans ambiguïté.
- **FANABE** est le nom produit partout. « SchoolOS » n'apparaît que comme référence historique au nom de fichier du cahier des charges.
