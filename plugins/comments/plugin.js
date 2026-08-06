/**
 * CommentsPanel — plugin-owned frontend for the comments extension.
 *
 * Self-registers in PluginPanelRegistry under the slug 'comments'.
 *
 * Panel contract:
 *   - get element(): HTMLElement   Mount point for EntityEdit.
 *   - flush(resolvedId): Promise   Persists pending POST/PUT/DELETE to the API.
 */

import { PluginPanelRegistry } from '../../js/modules/PluginPanelRegistry.js';

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
  // Private panel builder.
  // ---------------------------------------------------------------------------

  /**
   * @param {string}      endpointTemplate  e.g. /plugins/comments/client/{id}
   * @param {string|null} recordId
   * @param {object}      api
   * @returns {{ element: HTMLElement, flush: (resolvedId: string) => Promise<void> }}
   */
  #build(endpointTemplate, recordId, api) {
    const panel = document.createElement('div');
    panel.className = 'flex flex-col gap-3';

    // In-memory state.
    /** @type {Array<object>} */
    let existing = [];
    /** @type {Set<string>} */
    const toDelete = new Set();
    /** @type {Array<object>} */
    const toCreate = [];
    let tempCounter = 0;

    const hasPendingChanges = () =>
      toCreate.length > 0 ||
      toDelete.size > 0 ||
      existing.some((c) => c.pendingBody !== undefined);

    // Lists.
    const existingList = document.createElement('ul');
    existingList.className = 'flex flex-col gap-2';

    const newList = document.createElement('ul');
    newList.className = 'flex flex-col gap-2';

    // Render helpers.
    const renderExistingList = () => {
      existingList.replaceChildren();
      const visible = existing.filter((c) => !toDelete.has(c.id));
      if (visible.length === 0 && toCreate.length === 0) {
        this.#setListMessage(existingList, 'Sin comentarios aun.');
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
      newList.replaceChildren();
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
          };
          ops.push(
            api.put(`${resolved}/${item.id}`, payload).then(({ data }) => {
              const c = this.#contentObject(data?.content);
              item.body = newBody;
              item.author_id = typeof c.author_id === 'string' ? c.author_id : (item.author_id ?? null);
              item.author_name = typeof c.author_name === 'string' ? c.author_name : (item.author_name ?? null);
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
        };
        ops.push(
          api.post(resolved, payload).then(({ data }) => {
            const c = this.#contentObject(data?.content);
            existing.push({
              id: data.id,
              body: typeof c.body === 'string' ? c.body : '',
              author_id: typeof c.author_id === 'string' ? c.author_id : null,
              author_name: typeof c.author_name === 'string' ? c.author_name : null,
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

    // Add-comment form.
    const addForm = document.createElement('form');
    addForm.className = 'mt-2 flex flex-col gap-2 border-t border-slate-200 pt-3';

    const textarea = document.createElement('textarea');
    textarea.className = 'w-full rounded-lg border-slate-300 text-sm text-slate-900 focus:border-brand-500 focus:ring-brand-500';
    textarea.rows = 3;
    textarea.placeholder = 'Escribe un comentario...';
    addForm.appendChild(textarea);

    const addErrorEl = document.createElement('p');
    addErrorEl.className = 'text-xs text-red-700';
    addErrorEl.hidden = true;
    addForm.appendChild(addErrorEl);

    const addBtn = document.createElement('button');
    addBtn.type = 'submit';
    addBtn.className = 'inline-flex w-fit items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100';
    addBtn.textContent = 'Añadir';
    addForm.appendChild(addBtn);

    addForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const body = textarea.value.trim();
      if (body === '') {
        addErrorEl.textContent = 'El comentario no puede estar vacio.';
        addErrorEl.hidden = false;
        return;
      }
      addErrorEl.hidden = true;
      textarea.value = '';
      toCreate.push({ tempId: ++tempCounter, body, author_id: null, stamp: null });
      renderNewList();
      const placeholder = existingList.querySelector('[data-role="placeholder"]');
      if (placeholder !== null) {
        placeholder.remove();
      }
    });

    // Assembly and initial load.
    panel.appendChild(existingList);
    panel.appendChild(newList);
    panel.appendChild(addForm);

    if (recordId === null) {
      this.#setListMessage(existingList, 'Sin comentarios aun.');
    } else {
      this.#setListMessage(existingList, 'Cargando...', 'text-sm text-slate-500');
      api.get(endpointTemplate.replace('{id}', recordId))
        .then(({ data }) => {
          existing = Array.isArray(data)
            ? data.map((d) => {
              const c = this.#contentObject(d.content);
              return {
                id: d.id,
                body: typeof c.body === 'string' ? c.body : '',
                author_id: typeof c.author_id === 'string' ? c.author_id : null,
                author_name: typeof c.author_name === 'string' ? c.author_name : null,
                stamp: typeof c.stamp === 'string' ? c.stamp : (d.created_at ?? null),
                created_at: typeof d.created_at === 'string' ? d.created_at : null,
              };
            })
            : [];
          renderExistingList();
        })
        .catch(() => {
          this.#setListMessage(existingList, 'Error al cargar los comentarios.');
        });
    }

    return { element: panel, flush };
  }

  // ---------------------------------------------------------------------------
  // Private item renderer.
  // ---------------------------------------------------------------------------

  /**
   * @param {object} item
   * @param {boolean}    isNew
   * @param {() => void} onEditApply
   * @param {() => void} onDelete
   * @returns {HTMLLIElement}
   */
  #buildItem(item, isNew, onEditApply, onDelete) {
    const li = document.createElement('li');
    li.className = 'relative rounded-lg border border-slate-200 bg-slate-50 px-3 pb-8 pt-6 text-sm text-slate-700';
    if (isNew) {
      li.className += ' border-amber-300 bg-amber-50';
    } else if (item.pendingBody !== undefined) {
      li.className += ' border-sky-300 bg-sky-50';
    }

    const displayBody = item.pendingBody ?? item.body;

    const bodyEl = document.createElement('span');
    bodyEl.className = 'break-words';
    bodyEl.textContent = displayBody;
    li.appendChild(bodyEl);

    const authorDisplay = this.#resolveAuthorDisplay(item);
    const formattedStamp = this.#formatStamp(item.stamp ?? null);

    if (formattedStamp !== null || authorDisplay !== null) {
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
      if (authorDisplay !== null) {
        parts.push(`por ${authorDisplay}`);
      }
      const metaEl = document.createElement('span');
      metaEl.className = 'absolute left-3 right-3 top-1 text-[11px] text-slate-400';
      metaEl.textContent = parts.join(' ');
      li.appendChild(metaEl);
    }

    if (isNew) {
      const badge = document.createElement('span');
      badge.className = 'absolute right-3 top-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700';
      badge.textContent = 'Nuevo';
      li.appendChild(badge);
    } else if (item.pendingBody !== undefined) {
      const badge = document.createElement('span');
      badge.className = 'absolute right-3 top-1 inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-700';
      badge.textContent = 'Editado';
      li.appendChild(badge);
    }

    const editInput = document.createElement('textarea');
    editInput.className = 'mb-5 w-full rounded-lg border-slate-300 text-sm text-slate-900 focus:border-brand-500 focus:ring-brand-500';
    editInput.rows = 2;
    editInput.value = displayBody;
    editInput.hidden = true;
    li.appendChild(editInput);

    const actions = document.createElement('div');
    actions.className = 'absolute bottom-3 right-2 flex gap-1';

    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'inline-flex h-8 w-8 items-center justify-center rounded-full border border-sky-200 bg-white text-sky-700 transition duration-150 hover:bg-sky-50 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-200';
    editBtn.title = 'Editar';
    editBtn.appendChild(this.#buildGlyph('✎', 'text-sky-700'));

    const applyBtn = document.createElement('button');
    applyBtn.type = 'button';
    applyBtn.className = 'inline-flex h-8 w-8 items-center justify-center rounded-full border border-emerald-200 bg-white text-emerald-700 transition duration-150 hover:bg-emerald-50 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-200';
    applyBtn.title = 'Guardar cambios';
    applyBtn.appendChild(this.#buildGlyph('✓', 'text-emerald-700'));

    const cancelEditBtn = document.createElement('button');
    cancelEditBtn.type = 'button';
    cancelEditBtn.className = 'inline-flex h-8 w-8 items-center justify-center rounded-full border border-amber-200 bg-white text-amber-700 transition duration-150 hover:bg-amber-50 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-200';
    cancelEditBtn.title = 'Cancelar';
    cancelEditBtn.appendChild(this.#buildGlyph('✕', 'text-amber-700'));

    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'inline-flex h-8 w-8 items-center justify-center rounded-full border border-red-200 bg-white text-red-700 transition duration-150 hover:bg-red-50 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-red-200';
    deleteBtn.title = 'Eliminar';
    deleteBtn.appendChild(this.#buildGlyph('🗑', 'text-red-700'));

    const setEditingMode = (isEditing) => {
      bodyEl.hidden = isEditing;
      editInput.hidden = !isEditing;

      editBtn.hidden = isEditing;
      editBtn.classList.toggle('hidden', isEditing);

      deleteBtn.hidden = isEditing;
      deleteBtn.classList.toggle('hidden', isEditing);

      applyBtn.hidden = !isEditing;
      applyBtn.classList.toggle('hidden', !isEditing);

      cancelEditBtn.hidden = !isEditing;
      cancelEditBtn.classList.toggle('hidden', !isEditing);
    };

    // Estado inicial: solo Editar + Eliminar.
    setEditingMode(false);

    editBtn.addEventListener('click', () => {
      setEditingMode(true);
      editInput.focus();
    });

    const closeEditMode = () => {
      setEditingMode(false);
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

  /**
   * @param {HTMLElement} list
   * @param {string} message
   * @param {string} [className]
   */
  #setListMessage(list, message, className = '') {
    const item = document.createElement('li');
    item.className = `${className} rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-4 text-sm text-slate-500`.trim();
    item.dataset.role = 'placeholder';
    item.textContent = message;
    list.replaceChildren(item);
  }

  /**
   * @param {object} item
   * @returns {string|null}
   */
  #resolveAuthorDisplay(item) {
    const rawItem = item !== null && typeof item === 'object' ? item : {};
    const authorName = typeof rawItem.author_name === 'string'
      ? rawItem.author_name.trim()
      : '';
    if (authorName !== '') {
      return authorName;
    }
    const authorId = typeof rawItem.author_id === 'string'
      ? rawItem.author_id.trim()
      : '';
    if (authorId === '') {
      return null;
    }

    // Avoid showing raw UUIDs when backend could not resolve author_name.
    const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(authorId);
    if (isUuid) {
      return null;
    }

    return authorId;
  }

  /**
   * @param {unknown} content
   * @returns {Record<string, unknown>}
   */
  #contentObject(content) {
    return content !== null && typeof content === 'object' && !Array.isArray(content)
      ? /** @type {Record<string, unknown>} */ (content)
      : {};
  }

  /**
   * @param {string} glyph
   * @param {string} className
   * @returns {HTMLElement}
   */
  #buildGlyph(glyph, className = '') {
    const el = document.createElement('span');
    el.className = `pointer-events-none text-base font-semibold leading-none ${className}`.trim();
    el.setAttribute('aria-hidden', 'true');
    el.textContent = glyph;
    return el;
  }

  // ---------------------------------------------------------------------------
  // Private formatting.
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
    const formattedDate = new Intl.DateTimeFormat('es-ES', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    }).format(date);
    const formattedTime = new Intl.DateTimeFormat('es-ES', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(date);

    return `${formattedDate} a las ${formattedTime}`;
  }
}

// Self-register — EntityEdit imports this module dynamically and then calls
// PluginPanelRegistry.build('comments', options).
PluginPanelRegistry.register('comments', CommentsPanel);
