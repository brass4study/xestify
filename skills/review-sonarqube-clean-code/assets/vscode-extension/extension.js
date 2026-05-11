const fs = require('node:fs');
const path = require('node:path');
const vscode = require('vscode');

const REPORT_PATH = path.join('var', 'reports', 'sonarlint-problems.json');
const TRIGGER_PATH = path.join('var', 'reports', 'sonarlint-problems.request.json');
const WORKSPACE_TRIGGER_PATH = path.join('var', 'reports', 'sonarlint-workspace.request.json');
const WORKSPACE_STATUS_PATH = path.join('var', 'reports', 'sonarlint-workspace.status.json');
const SONAR_SOURCE_PATTERN = /sonar/i;
const ANALYSIS_DELAY_MS = 300;
const REQUEST_READ_RETRY_MS = 120;
const REQUEST_READ_RETRIES = 10;
const SOURCE_GLOB = '**/*.{php,js,html}';
const EXCLUDED_GLOB = '**/{.git,var,node_modules,dist,vendor}/**';
const DEFAULT_ANALYSIS_COLUMN = vscode.ViewColumn.Beside;
const SUPPORTED_ANALYSIS_EXTENSIONS = new Set(['.php', '.js', '.html']);
const EXTENSION_BUILD = 'workspace-analysis-v2';

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

function normalizeFilePath(root, filePath) {
  const resolved = path.isAbsolute(filePath) ? path.normalize(filePath) : path.resolve(root, filePath);
  const relative = path.relative(root, resolved);

  if (relative.startsWith('..') || path.isAbsolute(relative)) {
    throw new Error(`El archivo '${filePath}' queda fuera del workspace.`);
  }

  if (!fs.existsSync(resolved) || !fs.statSync(resolved).isFile()) {
    throw new Error(`El archivo '${filePath}' no existe o no es un fichero regular.`);
  }

  const extension = path.extname(resolved).toLowerCase();
  if (!SUPPORTED_ANALYSIS_EXTENSIONS.has(extension)) {
    throw new Error(`El archivo '${filePath}' no es analizable por SonarLint en este flujo.`);
  }

  return vscode.Uri.file(resolved);
}

function requestedFilesFromPayload(root, request) {
  const requestedFiles = Array.isArray(request?.files) ? request.files : [];
  if (requestedFiles.length === 0) {
    return null;
  }

  const seen = new Set();
  const uris = [];

  for (const value of requestedFiles) {
    if (typeof value !== 'string' || value.trim() === '') {
      throw new Error('La solicitud de analisis contiene una entrada de fichero vacia o invalida.');
    }

    const uri = normalizeFilePath(root, value.trim());
    const key = uri.fsPath.toLowerCase();

    if (seen.has(key)) {
      continue;
    }

    seen.add(key);
    uris.push(uri);
  }

  if (uris.length === 0) {
    throw new Error('La solicitud de analisis no incluye ficheros validos.');
  }

  return uris;
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
    exporter: 'skills/review-sonarqube-clean-code/assets/vscode-extension',
    exporter_build: EXTENSION_BUILD,
    total: issues.length,
    issues
  };

  fs.mkdirSync(path.dirname(reportPath), { recursive: true });
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2), 'utf8');

  return reportPath;
}

function writeWorkspaceStatus(root, status) {
  const statusPath = path.join(root, WORKSPACE_STATUS_PATH);

  fs.mkdirSync(path.dirname(statusPath), { recursive: true });
  fs.writeFileSync(statusPath, JSON.stringify(status, null, 2), 'utf8');

  return statusPath;
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
  const previousGroups = new Set(vscode.window.tabGroups.all);

  try {
    await vscode.commands.executeCommand('workbench.action.newGroupRight');
  } catch (error) {
    throw new Error(
      `No se pudo crear el grupo temporal de analisis: ${error instanceof Error ? error.message : String(error)}`
    );
  }

  const tabGroup = vscode.window.tabGroups.all.find((group) => !previousGroups.has(group));
  if (!tabGroup) {
    throw new Error('No se pudo identificar el grupo temporal de analisis recien creado.');
  }

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
    throw new Error('No existe grupo temporal de analisis para cerrar.');
  }

  const groupStillOpen = vscode.window.tabGroups.all.includes(tabGroup);
  if (!groupStillOpen) {
    throw new Error('El grupo temporal de analisis ya no existe; no se puede garantizar su limpieza.');
  }

  await vscode.window.tabGroups.close(tabGroup, true);
  await restoreOriginalEditor(originalEditor);
}

async function analyzeFile(uri, viewColumn) {
  const document = await vscode.workspace.openTextDocument(uri);
  await vscode.window.showTextDocument(document, {
    viewColumn,
    preview: true,
    preserveFocus: false
  });
  await vscode.commands.executeCommand('SonarLint.AnalyseOpenFile');
  await sleep(ANALYSIS_DELAY_MS);
}

async function workspaceFiles() {
  return vscode.workspace.findFiles(SOURCE_GLOB, EXCLUDED_GLOB);
}

function requestSummary(files, request) {
  if (Array.isArray(request?.files) && request.files.length > 0) {
    return `${files.length} archivos seleccionados`;
  }

  return `${files.length} archivos del workspace`;
}

async function loadWorkspaceAnalysisRequest(root) {
  const requestPath = path.join(root, WORKSPACE_TRIGGER_PATH);

  for (let attempt = 0; attempt < REQUEST_READ_RETRIES; attempt++) {
    try {
      if (!fs.existsSync(requestPath)) {
        return null;
      }

      return JSON.parse(fs.readFileSync(requestPath, 'utf8'));
    } catch (error) {
      if (attempt === REQUEST_READ_RETRIES - 1) {
        throw new Error(
          `No se pudo leer la solicitud de analisis del workspace: ${error instanceof Error ? error.message : String(error)}`
        );
      }

      await sleep(REQUEST_READ_RETRY_MS);
    }
  }

  return null;
}

async function resolveWorkspaceFiles(root, request) {
  const requestedFiles = requestedFilesFromPayload(root, request);
  if (requestedFiles !== null) {
    return requestedFiles;
  }

  return workspaceFiles();
}

async function analyzeWorkspace(request = null) {
  const root = workspaceRoot();

  if (!root) {
    vscode.window.showErrorMessage('No hay workspace abierto para analizar con SonarLint.');
    return null;
  }

  const files = await resolveWorkspaceFiles(root, request);
  const summary = requestSummary(files, request);
  const requestedAt = new Date().toISOString();
  writeWorkspaceStatus(root, {
    requested_at: requestedAt,
    finished_at: null,
    state: 'started',
    message: `Analisis SonarLint iniciado para ${summary}.`
  });

  let session = null;
  let analysisError = null;
  let closeError = null;

  try {
    session = await createAnalysisSession();

    for (const uri of files) {
      await analyzeFile(uri, session.viewColumn);
    }

    await sleep(ANALYSIS_DELAY_MS);
  } catch (error) {
    analysisError = error;
  }

  if (session !== null) {
    try {
      await closeAnalysisGroup(session.tabGroup, session.originalEditor);
    } catch (error) {
      closeError = error;
    }
  }

  const finalError = closeError ?? analysisError;
  if (finalError) {
    const message = finalError instanceof Error ? finalError.message : String(finalError);
    writeWorkspaceStatus(root, {
      requested_at: requestedAt,
      finished_at: new Date().toISOString(),
      state: 'error',
      message
    });
    vscode.window.showErrorMessage(`Analisis SonarLint abortado: ${message}`);
    return null;
  }

  const reportPath = exportProblems();
  writeWorkspaceStatus(root, {
    requested_at: requestedAt,
    finished_at: new Date().toISOString(),
    state: 'completed',
    message: `Analizados ${summary} con SonarLint.`,
    report_path: reportPath
  });

  vscode.window.showInformationMessage(
    `Analizados ${summary} con SonarLint.`
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
  const runAnalysis = async () => {
    const request = await loadWorkspaceAnalysisRequest(root);
    await analyzeWorkspace(request);
  };

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
