---
name: review-sonarqube-clean-code
description: Review and fix clean-code findings using SonarQube for IDE/SonarLint diagnostics exported by the local Xestify VSCode tools. Use this skill whenever the user says "Haz una revision de clean code" or mentions SonarQube, SonarLint, SonarQube for IDE, Problems diagnostics, clean code, code smells, duplicated literals, complexity, unused code, maintainability warnings, or quality regressions in PHP, JavaScript, HTML, tests, or documentation within Xestify. By default, perform a code cleanup: inspect findings, edit files to fix safe issues, verify, and report what changed.
---

# Review SonarQube Clean Code

Use this skill to inspect the same SonarQube for IDE diagnostics visible in
VSCode Problems, decide which findings matter, and apply small behavior-preserving
clean-code fixes.

Read `CONTRIBUTING.md` before changing PHP, JavaScript, tests, or technical
structure. Keep the review focused: Sonar findings are evidence to inspect, not
automatic instructions to refactor broad areas of the project.

When the user says "Haz una revision de clean code", treat it as an execution
request. Do not stop at an audit unless the user explicitly asks for analysis
only. Export or read findings, clean the code, verify the result, and summarize
the cleanup.

## Workflow

1. Infer the scope when possible:
   - If the user says "Haz una revision de clean code" with no extra scope, use
     changed files first; if there are no changed files, use the current task
     files or the current Sonar report.
   - If the scope is ambiguous and a broad cleanup would be risky, ask one short
     question before editing.
2. Export diagnostics:
   - If the user is working on a specific file or the current editor context is a
     single file, run `scripts/export-sonarlint-problems.ps1 -TargetPath <relative-path>`
     using the current file path as the target. This should be the preferred
     path for focused reviews.
   - For the current VSCode Problems state without a target file, run
     `scripts/export-sonarlint-problems.ps1`.
   - For a broader pass over `php`, `js`, and `html` files, run
     `scripts/analyze-sonarlint-workspace.ps1`.
3. If export fails, report that VSCode must be open, the local exporter must be
   installed from `assets/vscode-extension`, and SonarQube for IDE must have
   published diagnostics.
4. Read `var/reports/sonarlint-problems.json` and use `total`, `generated_at`,
   and `issues[]` as the source of truth. Each issue includes `source`, `code`,
   `severity`, `message`, `path`, `line`, `character`, `end_line`, and
   `end_character`.
5. Group findings by file and rule code. Prioritize correctness, security,
   production code, repeated issues with one clear pattern, and files already in
   the requested scope.
6. Fix in small batches by default. Preserve behavior, avoid unrelated
   refactors, and do not introduce Composer, npm, or new tooling without
   explicit approval.
7. After changes, run the relevant project tests or syntax checks. Export
   SonarQube findings again when feasible and compare the remaining count.

## Bundled Resources

- `scripts/export-sonarlint-problems.ps1`: canonical trigger-based export for
  the current VSCode Problems state.
- `scripts/analyze-sonarlint-workspace.ps1`: canonical trigger-based full
  workspace analysis for `php`, `js`, and `html`.
- `assets/vscode-extension/`: local VSCode extension that listens for trigger
  files, exports Sonar diagnostics, and can analyze the whole workspace.

## Fixing Guidance

- Treat Sonar findings as evidence, not automatic truth. Check the surrounding
  code before editing.
- Prefer simple, local fixes:
  - Extract a private helper for real duplication.
  - Name constants for meaningful repeated literals.
  - Reduce complexity by separating decisions.
  - Delete unused code only when usage search confirms it is unused.
- For tests, keep readability higher priority than over-abstracting assertions.
  Do not hide test intent just to silence duplication warnings.
- For `Router`, `PluginClassLoader` and `PluginHookRegistrar`, respect the
  existing project exception for controlled dynamic dispatch marked with
  `// NOSONAR` and covered by tests.
- When a finding is intentionally accepted, explain the reason and avoid adding
  suppressions unless the project already uses that pattern for the same case.

## Report Structure

Use this structure when reporting the result:

```text
Resumen Sonar
- Reporte: <generated_at or "no disponible">
- Hallazgos iniciales: <total>
- Hallazgos abordados: <count and short grouping>

Cambios
- <path>: <rules/messages addressed and behavior preserved>

Verificacion
- <command>: <result>
- Reexport Sonar: <new total or why it was not run>

Pendiente
- <remaining findings or "sin pendientes conocidos">
```

## Test Prompts

Use `evals/evals.json` as the skill's lightweight validation set. These prompts
cover the main expected behaviors: exporting diagnostics, working from an
existing report, and reviewing only the active scope.
