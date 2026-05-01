import { Api } from './modules/Api.js';
import { AppState } from './modules/State.js';
import { EntityList } from './pages/EntityList.js';
import { Login } from './pages/Login.js';

const STORAGE_TOKEN_KEY = 'xestify_access_token';
const API_BASE = '/api/v1';

const app = document.getElementById('app');

if (app instanceof HTMLElement) {
  bootstrap(app);
}

function bootstrap(container) {
  const token = localStorage.getItem(STORAGE_TOKEN_KEY);

  if (token !== null && token !== '') {
    setAuthToken(token);
    renderDashboard(container);
    return;
  }

  renderLogin(container);
}

function renderLogin(container) {
  const loginApi = new Api(API_BASE);

  const loginPage = new Login(container, {
    api: loginApi,
    onSuccess: ({ accessToken }) => {
      localStorage.setItem(STORAGE_TOKEN_KEY, accessToken);
      setAuthToken(accessToken);
      renderDashboard(container);
    },
  });

  return loginPage;
}

async function renderDashboard(container) {
  container.innerHTML = '';

  const shell = document.createElement('section');
  shell.className = 'xt-shell';

  const header = document.createElement('header');
  header.className = 'xt-shell__header';

  const title = document.createElement('h1');
  title.className = 'xt-shell__title';
  title.textContent = 'Xestify';
  header.appendChild(title);

  const logoutButton = document.createElement('button');
  logoutButton.type = 'button';
  logoutButton.className = 'xt-btn xt-btn--secondary';
  logoutButton.textContent = 'Salir';
  logoutButton.addEventListener('click', () => {
    clearAuth();
    renderLogin(container);
  });
  header.appendChild(logoutButton);

  shell.appendChild(header);

  const content = document.createElement('main');
  content.className = 'xt-shell__content';
  shell.appendChild(content);

  container.appendChild(shell);

  const dashboardApi = new Api(API_BASE);
  dashboardApi.setToken(AppState.getToken());

  const page = new EntityList(content, { api: dashboardApi });

  try {
    await page.init();
  } catch {
    content.innerHTML = '<p>No se pudo cargar el dashboard.</p>';
  }
}

function setAuthToken(token) {
  AppState.setToken(token);
}

function clearAuth() {
  AppState.reset();
  localStorage.removeItem(STORAGE_TOKEN_KEY);
}
