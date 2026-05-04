const fs = require('node:fs');
const path = require('node:path');
const vscode = require('vscode');

const REPORT_PATH = path.join('var', 'reports', 'sonarlint-problems.json');
const TRIGGER_PATH = path.join('var', 'reports', 'sonarlint-problems.request.json');
const WORKSPACE_TRIGGER_PATH = path.join('var', 'reports', 'sonarlint-workspace.request.json');
const SONAR_SOURCE_PATTERN = /sonar/i;
const ANALYSIS_DELAY_MS = 300;
const SOURCE_GLOB = '**/*.{php,js,html}';
const EXCLUDED_GLOB = '**/{.git,var,node_modules,dist,vendor}/**';
const DEFAULT_ANALYSIS_COLUMN = vscode.ViewColumn.Beside;

function severityName(severity) {
  switch (severity) {
    case vscode.DiagnosticSeverity.Error:
      return 'error';
    case vscode.DiagnosticSeverity.Warning:
      return 'warning';
    case vscode.DiagnosticSeverity.Information:
      return 'information';
    case vscode.DiagnosticSeverity.Hint:
      return 'hint';
    default:
      return 'unknown';
  }
}

function diagnosticCode(code) {
  if (code === undefined || code === null) {
    return null;
  }

  if (typeof code === 'object' && 'value' in code) {
    return String(code.value);
  }

  return String(code);
}

function workspaceRoot() {
  const folders = vscode.workspace.workspaceFolders;

  if (!folders || folders.length === 0) {
    return null;
  }

  return folders[0].uri.fsPath;
}

function relativePath(root, uri) {
  if (uri.scheme !== 'file') {
    return uri.toString();
  }

  return path.relative(root, uri.fsPath).replaceAll('\\', '/');
}

function sonarDiagnostics(root) {
  const issues = [];

  for (const [uri, diagnostics] of vscode.languages.getDiagnostics()) {
    for (const diagnostic of diagnostics) {
      const source = diagnostic.source || '';

      if (!SONAR_SOURCE_PATTERN.test(source)) {
        continue;
      }

      issues.push({
        source,
        code: diagnosticCode(diagnostic.code),
        severity: severityName(diagnostic.severity),
        message: diagnostic.message,
        path: relativePath(root, uri),
        line: diagnostic.range.start.line + 1,
        character: diagnostic.range.start.character + 1,
        end_line: diagnostic.range.end.line + 1,
        end_character: diagnostic.range.end.character + 1
      });
    }
  }

  return issues;
}

function writeReport(root, issues) {
  const reportPath = path.join(root, REPORT_PATH);
  const report = {
    generated_at: new Date().toISOString(),
    exporter: 'tools/vscode/sonarlint-problems-exporter',
    total: issues.length,
    issues
  };

  fs.mkdirSync(path.dirname(reportPath), { recursive: true });
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2), 'utf8');

  return reportPath;
}

function exportProblems() {
  const root = workspaceRoot();

  if (!root) {
    vscode.window.showErrorMessage('No hay workspace abierto para exportar Problems.');
    return null;
  }

  const issues = sonarDiagnostics(root);
  const reportPath = writeReport(root, issues);

  vscode.window.showInformationMessage(
    `Exportados ${issues.length} hallazgos SonarLint a ${reportPath}`
  );

  return reportPath;
}

function sleep(ms) {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

async function createAnalysisSession() {
  const originalEditor = vscode.window.activeTextEditor;

  try {
    await vscode.commands.executeCommand('workbench.action.newGroupRight');
  } catch {
    return {
      originalEditor,
      tabGroup: null,
      viewColumn: DEFAULT_ANALYSIS_COLUMN
    };
  }

  const tabGroup = vscode.window.tabGroups.activeTabGroup;

  return {
    originalEditor,
    tabGroup,
    viewColumn: tabGroup?.viewColumn ?? DEFAULT_ANALYSIS_COLUMN
  };
}

async function restoreOriginalEditor(originalEditor) {
  if (!originalEditor) {
    return;
  }

  await vscode.window.showTextDocument(originalEditor.document, {
    viewColumn: originalEditor.viewColumn,
    preserveFocus: false,
    preview: false
  });
}

async function closeAnalysisGroup(tabGroup, originalEditor) {
  if (!tabGroup) {
    return;
  }

  await vscode.window.tabGroups.close(tabGroup, true);
  await restoreOriginalEditor(originalEditor);
}

async function analyzeFile(uri, viewColumn) {
  const document = await vscode.workspace.openTextDocument(uri);
  await vscode.window.showTextDocument(document, {
    viewColumn,
    preview: false,
    preserveFocus: false
  });
  await vscode.commands.executeCommand('SonarLint.AnalyseOpenFile');
  await sleep(ANALYSIS_DELAY_MS);
}

async function workspaceFiles() {
  return vscode.workspace.findFiles(SOURCE_GLOB, EXCLUDED_GLOB);
}

async function analyzeWorkspace() {
  const root = workspaceRoot();

  if (!root) {
    vscode.window.showErrorMessage('No hay workspace abierto para analizar con SonarLint.');
    return null;
  }

  const files = await workspaceFiles();
  const session = await createAnalysisSession();

  try {
    for (const uri of files) {
      await analyzeFile(uri, session.viewColumn);
    }

    await sleep(ANALYSIS_DELAY_MS);
  } finally {
    await closeAnalysisGroup(session.tabGroup, session.originalEditor);
  }

  const reportPath = exportProblems();

  vscode.window.showInformationMessage(
    `Analizados ${files.length} archivos con SonarLint.`
  );

  return reportPath;
}

function triggerPattern(root, triggerPath) {
  return new vscode.RelativePattern(root, triggerPath.replaceAll('\\', '/'));
}

function registerTriggerWatcher(context) {
  const root = workspaceRoot();

  if (!root) {
    return;
  }

  const watcher = vscode.workspace.createFileSystemWatcher(triggerPattern(root, TRIGGER_PATH));
  const runExport = () => exportProblems();

  watcher.onDidCreate(runExport);
  watcher.onDidChange(runExport);

  context.subscriptions.push(watcher);
}

function registerWorkspaceTriggerWatcher(context) {
  const root = workspaceRoot();

  if (!root) {
    return;
  }

  const watcher = vscode.workspace.createFileSystemWatcher(triggerPattern(root, WORKSPACE_TRIGGER_PATH));
  const runAnalysis = () => analyzeWorkspace();

  watcher.onDidCreate(runAnalysis);
  watcher.onDidChange(runAnalysis);

  context.subscriptions.push(watcher);
}

function activate(context) {
  const command = vscode.commands.registerCommand('xestifySonarLint.exportProblems', async () => {
    exportProblems();
  });

  const analyzeCommand = vscode.commands.registerCommand('xestifySonarLint.analyzeWorkspace', async () => {
    await analyzeWorkspace();
  });

  context.subscriptions.push(command, analyzeCommand);
  registerTriggerWatcher(context);
  registerWorkspaceTriggerWatcher(context);
}

module.exports = {
  activate
};
