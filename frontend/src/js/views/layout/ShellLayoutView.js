import { component } from '../modules/ComponentFactory.js';

export const ShellLayoutView = {
  createDashboardLayout(container) {
    container.replaceChildren();

    const shell = component.create('sectionTag');
    shell.className = 'mx-auto flex min-h-screen w-full max-w-[1280px] flex-col';
    shell.dataset.role = 'app-shell';

    const navbarContainer = component.create('div');
    navbarContainer.className = 'sticky top-0 z-50';
    navbarContainer.dataset.role = 'shell-navbar';
    shell.appendChild(navbarContainer);

    const contentContainer = component.create('main');
    contentContainer.className = 'flex-1 px-3 py-4 sm:px-4 sm:py-6';
    contentContainer.dataset.role = 'shell-content';
    shell.appendChild(contentContainer);

    container.appendChild(shell);

    return {
      shell,
      navbarContainer,
      contentContainer,
    };
  },

  showPlaceholder(container, message) {
    const msg = component.create('p');
    msg.className = 'rounded-xl border border-dashed border-slate-300 bg-white/70 px-4 py-10 text-center text-sm text-slate-500';
    msg.dataset.role = 'placeholder';
    msg.textContent = message;
    container.replaceChildren(msg);
  },
};