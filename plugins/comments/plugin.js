/**
 * CommentsPanel — plugin-owned frontend for the comments extension.
 *
 * Self-registers in PluginPanelRegistry under the slug 'comments'.
 *
 * Panel contract:
 *   - get element(): HTMLElement   Mount point for EntityEdit.
 *   - flush(resolvedId): Promise   Persists pending POST/PUT/DELETE to the API.
 */

import { AppState } from '/js/modules/State.js';
import { PluginPanelRegistry } from '/js/modules/PluginPanelRegistry.js';

export class CommentsPanel {
  /** @type {HTMLElement} */
  #element;

  /** @type {(resolvedId: string) => Promise<void>} */
  #flushFn;

  /**
   * @param {{
   *   endpoint: string,
   *   recordId: string|null,
   *   api: import('/src/js/modules/Api.js').Api
   * }} options
   */
  constructor({ endpoint, recordId, api }) {
    const { element, flush } = this.#build(endpoint, recordId, api);
    this.#element = element;
    this.#flushFn = flush;
  }

  get element() {
    return this.#element;
  }

  /** @param {string} resolvedId */
  async flush(resolvedId) {
    return this.#flushFn(resolvedId);
  }

  // ---------------------------------------------------------------------------
  // Private — panel builder
  // ---------------------------------------------------------------------------

  /**
   * @param {string}      endpointTemplate  e.g. /plugins/comments/client/{id}
   * @param {string|null} recordId
   * @param {object}      api
   * @returns {{ element: HTMLElement, flush: (resolvedId: string) => Promise<void> }}
   */
  #build(endpointTemplate, recordId, api) {
    const panel = document.createElement('div');
    panel.className = 'xt-tab-panel';

    // ── In-memory state ──────────────────────────────────────────────────────
    /** @type {{ id: string, body: string, author_id?: string|null, stamp?: string|null, created_at?: string|null, pendingBody?: string }[]} */
    let existing = [];
    /** @type {Set<string>} */
    const toDelete = new Set();
    /** @type {{ tempId: number, body: string, author_id?: string|null, stamp?: string|null, created_at?: string|null, pendingBody?: string }[]} */
    const toCreate = [];
    let tempCounter = 0;

    const hasPendingChanges = () =>
      toCreate.length > 0 ||
      toDelete.size > 0 ||
      existing.some((c) => c.pendingBody !== undefined);

    // ── Lists ─────────────────────────────────────────────────────────────────
    const existingList = document.createElement('ul');
    existingList.className = 'xt-tab-panel__list';

    const newList = document.createElement('ul');
    newList.className = 'xt-tab-panel__pending';

    // ── Render helpers ────────────────────────────────────────────────────────
    const renderExistingList = () => {
      existingList.innerHTML = '';
      const visible = existing.filter((c) => !toDelete.has(c.id));
      if (visible.length === 0 && toCreate.length === 0) {
        existingList.innerHTML = '<li class="xt-placeholder">Sin comentarios aún.</li>';
        return;
      }
      for (const item of visible) {
        existingList.appendChild(this.#buildItem(
          item,
          false,
          () => { /* pending body already stored in item */ },
          () => {
            toDelete.add(item.id);
            renderExistingList();
          }
        ));
      }
    };

    const renderNewList = () => {
      newList.innerHTML = '';
      for (const item of toCreate) {
        newList.appendChild(this.#buildItem(
          item,
          true,
          () => { /* pending body already stored in item */ },
          () => {
            const idx = toCreate.indexOf(item);
            if (idx !== -1) {
              toCreate.splice(idx, 1);
            }
            renderNewList();
            if (toCreate.length === 0) {
              renderExistingList();
            }
          }
        ));
      }
    };

    // ── Flush ─────────────────────────────────────────────────────────────────
    const flush = async (resolvedId) => {
      if (!hasPendingChanges()) {
        return;
      }

      const resolved = endpointTemplate.replace('{id}', resolvedId);
      const ops = [];

      for (const id of toDelete) {
        ops.push(
          api.delete(`${resolved}/${id}`).then(() => {
            toDelete.delete(id);
            existing = existing.filter((c) => c.id !== id);
          })
        );
      }

      for (const item of existing) {
        if (item.pendingBody !== undefined) {
          const newBody = item.pendingBody;
          const payload = {
            body: newBody,
            author_id: AppState.getUserEmail() ?? '',
            stamp: new Date().toISOString(),
          };
          ops.push(
            api.put(`${resolved}/${item.id}`, payload).then(({ data }) => {
              const c = (data?.content !== null && typeof data?.content === 'object') ? data.content : {};
              item.body = newBody;
              item.author_id = typeof c.author_id === 'string' ? c.author_id : (item.author_id ?? null);
              item.stamp = typeof c.stamp === 'string' ? c.stamp : (item.stamp ?? null);
              item.pendingBody = undefined;
            })
          );
        }
      }

      for (const item of toCreate) {
        const body = item.pendingBody ?? item.body;
        const payload = {
          body,
          author_id: AppState.getUserEmail() ?? '',
          stamp: new Date().toISOString(),
        };
        ops.push(
          api.post(resolved, payload).then(({ data }) => {
            const c = (data?.content !== null && typeof data?.content === 'object') ? data.content : {};
            existing.push({
              id: data.id,
              body: typeof c.body === 'string' ? c.body : '',
              author_id: typeof c.author_id === 'string' ? c.author_id : null,
              stamp: typeof c.stamp === 'string' ? c.stamp : (data.created_at ?? null),
              created_at: typeof data.created_at === 'string' ? data.created_at : null,
            });
            const idx = toCreate.indexOf(item);
            if (idx !== -1) {
              toCreate.splice(idx, 1);
            }
          })
        );
      }

      await Promise.all(ops);
      renderExistingList();
      renderNewList();
    };

    // ── Add-comment form ──────────────────────────────────────────────────────
    const addForm = document.createElement('form');
    addForm.className = 'xt-comment-form';

    const textarea = document.createElement('textarea');
    textarea.className = 'xt-comment-form__body';
    textarea.rows = 3;
    textarea.placeholder = 'Escribe un comentario…';
    addForm.appendChild(textarea);

    const addErrorEl = document.createElement('p');
    addErrorEl.className = 'xt-comment-form__error';
    addErrorEl.hidden = true;
    addForm.appendChild(addErrorEl);

    const addBtn = document.createElement('button');
    addBtn.type = 'submit';
    addBtn.className = 'xt-btn xt-btn--secondary';
    addBtn.textContent = 'Añadir';
    addForm.appendChild(addBtn);

    addForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const body = textarea.value.trim();
      if (body === '') {
        addErrorEl.textContent = 'El comentario no puede estar vacío.';
        addErrorEl.hidden = false;
        return;
      }
      addErrorEl.hidden = true;
      textarea.value = '';
      toCreate.push({ tempId: ++tempCounter, body, author_id: null, stamp: null });
      renderNewList();
      const placeholder = existingList.querySelector('.xt-placeholder');
      if (placeholder !== null) {
        placeholder.remove();
      }
    });

    // ── Assembly & initial load ───────────────────────────────────────────────
    panel.appendChild(existingList);
    panel.appendChild(newList);
    panel.appendChild(addForm);

    if (recordId === null) {
      existingList.innerHTML = '<li class="xt-placeholder">Sin comentarios aún.</li>';
    } else {
      existingList.innerHTML = '<li class="xt-loading">Cargando…</li>';
      api.get(endpointTemplate.replace('{id}', recordId))
        .then(({ data }) => {
          existing = Array.isArray(data)
            ? data.map((d) => {
              const c = (d.content !== null && typeof d.content === 'object') ? d.content : {};
              return {
                id: d.id,
                body: typeof c.body === 'string' ? c.body : '',
                author_id: typeof c.author_id === 'string' ? c.author_id : null,
                stamp: typeof c.stamp === 'string' ? c.stamp : (d.created_at ?? null),
                created_at: typeof d.created_at === 'string' ? d.created_at : null,
              };
            })
            : [];
          renderExistingList();
        })
        .catch(() => {
          existingList.innerHTML =
            '<li class="xt-placeholder">Error al cargar los comentarios.</li>';
        });
    }

    return { element: panel, flush };
  }

  // ---------------------------------------------------------------------------
  // Private — item renderer
  // ---------------------------------------------------------------------------

  /**
   * @param {{ id: string|null, body: string, author_id?: string|null, stamp?: string|null, created_at?: string|null, pendingBody?: string }} item
   * @param {boolean}    isNew
   * @param {() => void} onEditApply
   * @param {() => void} onDelete
   * @returns {HTMLLIElement}
   */
  #buildItem(item, isNew, onEditApply, onDelete) {
    const li = document.createElement('li');
    li.className = 'xt-tab-panel__item';
    if (isNew) {
      li.classList.add('xt-tab-panel__item--pending');
    } else if (item.pendingBody !== undefined) {
      li.classList.add('xt-tab-panel__item--edited');
    }

    const displayBody = item.pendingBody ?? item.body;

    const bodyEl = document.createElement('span');
    bodyEl.className = 'xt-tab-panel__item-body';
    bodyEl.textContent = displayBody;
    li.appendChild(bodyEl);

    const formattedStamp = this.#formatStamp(item.stamp ?? null);
    if (formattedStamp !== null || (typeof item.author_id === 'string' && item.author_id !== '')) {
      const isModified = item.stamp !== null
        && item.created_at !== null
        && item.stamp !== undefined
        && item.created_at !== undefined
        && Math.abs(
          new Date(item.stamp).getTime() -
          new Date(/** @type {string} */ (item.created_at)).getTime()
        ) > 5000;
      const verb = isModified ? 'Modificado' : 'Creado';
      const parts = [verb];
      if (formattedStamp !== null) {
        parts.push(`el ${formattedStamp}`);
      }
      if (typeof item.author_id === 'string' && item.author_id !== '') {
        parts.push(`por ${item.author_id}`);
      }
      const metaEl = document.createElement('span');
      metaEl.className = 'xt-tab-panel__item-meta';
      metaEl.textContent = parts.join(' ');
      li.appendChild(metaEl);
    }

    if (isNew) {
      const badge = document.createElement('span');
      badge.className = 'xt-badge xt-badge--pending';
      badge.textContent = 'Nuevo';
      li.appendChild(badge);
    } else if (item.pendingBody !== undefined) {
      const badge = document.createElement('span');
      badge.className = 'xt-badge xt-badge--edited';
      badge.textContent = 'Editado';
      li.appendChild(badge);
    }

    const editInput = document.createElement('textarea');
    editInput.className = 'xt-comment-form__body';
    editInput.rows = 2;
    editInput.value = displayBody;
    editInput.hidden = true;
    li.appendChild(editInput);

    const actions = document.createElement('div');
    actions.className = 'xt-tab-panel__item-actions';

    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'xt-btn xt-btn--icon';
    editBtn.title = 'Editar';
    editBtn.innerHTML = '<i class="fa-solid fa-pencil" aria-hidden="true"></i>';

    const applyBtn = document.createElement('button');
    applyBtn.type = 'button';
    applyBtn.className = 'xt-btn xt-btn--primary xt-btn--sm';
    applyBtn.title = 'Aplicar';
    applyBtn.textContent = 'Aplicar';
    applyBtn.hidden = true;

    const cancelEditBtn = document.createElement('button');
    cancelEditBtn.type = 'button';
    cancelEditBtn.className = 'xt-btn xt-btn--secondary xt-btn--sm';
    cancelEditBtn.title = 'Cancelar';
    cancelEditBtn.textContent = 'Cancelar';
    cancelEditBtn.hidden = true;

    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'xt-btn xt-btn--icon xt-btn--danger';
    deleteBtn.title = 'Eliminar';
    deleteBtn.innerHTML = '<i class="fa-solid fa-trash" aria-hidden="true"></i>';

    editBtn.addEventListener('click', () => {
      bodyEl.hidden = true;
      editInput.hidden = false;
      editBtn.hidden = true;
      deleteBtn.hidden = true;
      applyBtn.hidden = false;
      cancelEditBtn.hidden = false;
      editInput.focus();
    });

    const closeEditMode = () => {
      editInput.hidden = true;
      bodyEl.hidden = false;
      editBtn.hidden = false;
      deleteBtn.hidden = false;
      applyBtn.hidden = true;
      cancelEditBtn.hidden = true;
    };

    cancelEditBtn.addEventListener('click', () => {
      editInput.value = item.pendingBody ?? item.body;
      closeEditMode();
    });

    applyBtn.addEventListener('click', () => {
      const newBody = editInput.value.trim();
      if (newBody === '') {
        return;
      }
      item.pendingBody = newBody;
      bodyEl.textContent = newBody;
      closeEditMode();
      onEditApply();
    });

    deleteBtn.addEventListener('click', onDelete);

    actions.appendChild(editBtn);
    actions.appendChild(applyBtn);
    actions.appendChild(cancelEditBtn);
    actions.appendChild(deleteBtn);
    li.appendChild(actions);

    return li;
  }

  // ---------------------------------------------------------------------------
  // Private — formatting
  // ---------------------------------------------------------------------------

  /**
   * @param {string|null} stamp
   * @returns {string|null}
   */
  #formatStamp(stamp) {
    if (typeof stamp !== 'string' || stamp.trim() === '') {
      return null;
    }
    const date = new Date(stamp);
    if (Number.isNaN(date.getTime())) {
      return stamp;
    }
    return new Intl.DateTimeFormat('es-ES', {
      dateStyle: 'short',
      timeStyle: 'short',
    }).format(date);
  }
}

// Self-register — EntityEdit imports this module dynamically and then calls
// PluginPanelRegistry.build('comments', options).
PluginPanelRegistry.register('comments', CommentsPanel);
