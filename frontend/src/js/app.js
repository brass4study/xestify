import { AppController } from './controllers/AppController.js';

const appRoot = document.getElementById('app');

if (appRoot instanceof HTMLElement) {
  const controller = new AppController(appRoot);
  void controller.start();
}
