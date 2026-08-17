# Skills locales de Xestify

Índice de las skills propias del proyecto (estándar Anthropic Agent Skills:
carpeta `skills/<nombre>/` con `SKILL.md`, frontmatter `name` + `description`,
recursos opcionales en `scripts/`, `references/`, `assets/`, `evals/`). La
convención estructural vive en `AGENTS.md`, sección "Skills locales del
proyecto" — no se repite aquí. Este README es solo el índice: qué skills
existen, qué hace cada una y qué frase la dispara.

Cada skill se activa por lenguaje natural (Claude Code compara la petición
contra su `description`) o invocándola explícitamente por nombre.

| Skill | Qué hace | Se dispara con |
|---|---|---|
| [`audit-technical-debt`](audit-technical-debt/SKILL.md) | Genera una auditoría de deuda técnica de caja blanca (completa, incremental o acotada a un subsistema/EPIC). Archivo histórico en [`audit-technical-debt/archive/`](audit-technical-debt/archive/README.md). | "haz una auditoría de deuda técnica", "audita el módulo X", "compara con la última auditoría" |
| [`fix-technical-debt`](fix-technical-debt/SKILL.md) | Corrige hallazgos ya auditados fase a fase (prioritarios / barrido MAYOR / MENOR-NIT / cierre), detectando sola la siguiente sesión pendiente. | "corrige la deuda técnica", "sigue con la corrección de la auditoría", "ejecuta la sesión 2.3" |
| [`review-sonarqube-clean-code`](review-sonarqube-clean-code/SKILL.md) | Revisa y corrige findings de SonarQube for IDE/SonarLint sobre el código local (PHP, JS, HTML, tests). | "haz una revisión de clean code" |
| [`seed-business-data`](seed-business-data/SKILL.md) | Siembra datos de negocio de demostración (clientes, distribuidores, pedidos, fichas de optometría/lentillas...) para entornos de desarrollo/demo (STORY 10.6). | "siembra datos de demo", "poblar la base de datos" |

Referenciado también desde `docs/00-meta/README.md` (aviso de que el
proyecto usa skills) y `docs/10-productivity/README.md` (detalle, por ser la
carpeta de productividad e IA).
