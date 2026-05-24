const rarityLabels = {
  common: 'обычная',
  uncommon: 'необычная',
  rare: 'редкая',
  epic: 'эпическая',
  legendary: 'легендарная',
  mythic: 'мифическая',
};

const rarityRank = {
  mythic: 6,
  legendary: 5,
  epic: 4,
  rare: 3,
  uncommon: 2,
  common: 1,
};

const state = {
  packs: [],
  user: null,
  stats: null,
  savedCards: [],
  history: [],
};

const qs = (selector, root = document) => root.querySelector(selector);
const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));
const root = () => qs('[data-cabinet-app]');
const csrfToken = () => root()?.dataset.csrf || '';

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#039;',
})[char]);

const api = async (path, options = {}) => {
  const response = await fetch(path, {
    credentials: 'same-origin',
    ...options,
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(options.headers ?? {}),
    },
  });

  let payload = null;

  try {
    payload = await response.json();
  } catch (error) {
    payload = {
      ok: false,
      error: 'Кабинет споткнулся на ровном месте. Попробуй обновить страницу.',
    };
  }

  if (!response.ok || payload.ok === false) {
    const apiError = new Error(payload.error || 'Что-то не загрузилось. Попробуй ещё раз.');
    apiError.status = response.status;
    apiError.payload = payload;
    throw apiError;
  }

  return payload;
};

const cabinetMutation = (path, payload) => api(path, {
  method: 'POST',
  headers: {
    'X-CSRF-Token': csrfToken(),
  },
  body: JSON.stringify(payload),
});

const storageGet = (key) => {
  try {
    return window.localStorage.getItem(key);
  } catch (error) {
    return null;
  }
};

const storageSet = (key, value) => {
  try {
    window.localStorage.setItem(key, value);
  } catch (error) {
    // Текущая настройка всё равно применится на странице.
  }
};

const formatDate = (value) => {
  if (!value) return 'Пока нет';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return String(value);

  return new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date);
};

const rarityBadge = (rarity) => {
  const label = rarityLabels[rarity] || rarity || 'редкость';
  return `<span class="rarity-badge rarity-${escapeHtml(rarity)}">${escapeHtml(label)}</span>`;
};

const setMessage = (message = '', type = 'info') => {
  const box = qs('[data-cabinet-feedback]');
  if (!box) return;

  box.textContent = message;
  box.dataset.type = type;
  box.className = `feedback cabinet-feedback is-${type}`;
  box.hidden = message === '';
};

const inlineFeedbackTimers = new WeakMap();
const savedStateTimers = new WeakMap();

const showInlineFeedback = (target, message, type = 'success') => {
  const box = typeof target === 'string' ? qs(target) : target;
  if (!box) return;

  const previousTimer = inlineFeedbackTimers.get(box);
  if (previousTimer) window.clearTimeout(previousTimer);

  box.textContent = message || (type === 'success' ? 'Сохранено.' : '');
  box.classList.remove('is-success', 'is-error', 'is-loading');
  box.classList.add(`is-${type}`);
  box.hidden = box.textContent === '';

  if (type === 'success' && !box.hidden) {
    const timer = window.setTimeout(() => {
      box.hidden = true;
      box.textContent = '';
      box.classList.remove('is-success', 'is-error', 'is-loading');
      inlineFeedbackTimers.delete(box);
    }, 2600);
    inlineFeedbackTimers.set(box, timer);
  }
};

const flashSavedState = (...elements) => {
  elements.filter((element) => element instanceof Element).forEach((element) => {
    const className = element.matches('button') ? 'button-saved' : 'field-saved';
    const previousTimer = savedStateTimers.get(element);
    if (previousTimer) window.clearTimeout(previousTimer);

    element.classList.add(className);
    const timer = window.setTimeout(() => {
      element.classList.remove(className);
      savedStateTimers.delete(element);
    }, 2600);
    savedStateTimers.set(element, timer);
  });
};

const renderProfile = () => {
  if (!state.user) return;

  const username = qs('[data-profile-username]');
  const email = qs('[data-profile-email]');
  const facts = qs('[data-profile-facts]');
  const input = qs('[data-profile-form] input[name="username"]');

  if (username) username.textContent = state.user.username;
  if (email) email.textContent = state.user.email;
  if (input) input.value = state.user.username;

  if (facts) {
    facts.innerHTML = `
      <div>
        <dt>Email</dt>
        <dd>${escapeHtml(state.user.email)}</dd>
      </div>
      <div>
        <dt>Регистрация</dt>
        <dd>${escapeHtml(formatDate(state.user.created_at))}</dd>
      </div>
      <div>
        <dt>Последний вход</dt>
        <dd>${escapeHtml(formatDate(state.user.last_login_at))}</dd>
      </div>
    `;
  }
};

const renderStats = () => {
  const container = qs('[data-cabinet-stats]');
  if (!container || !state.stats) return;

  const topRarity = state.stats.top_rarity?.rarity
    ? rarityLabels[state.stats.top_rarity.rarity] || state.stats.top_rarity.rarity
    : 'Пока нет';

  const favoritePack = state.stats.favorite_pack?.title || 'Пока не выбран';

  container.innerHTML = `
    <article class="stat-card">
      <span>Открытий всего</span>
      <strong>${Number(state.stats.total_openings || 0)}</strong>
    </article>
    <article class="stat-card">
      <span>Сегодня</span>
      <strong>${Number(state.stats.openings_today || 0)}</strong>
    </article>
    <article class="stat-card">
      <span>В коллекции</span>
      <strong>${Number(state.stats.saved_count || 0)}</strong>
    </article>
    <article class="stat-card">
      <span>Любимый пак</span>
      <strong>${escapeHtml(favoritePack)}</strong>
    </article>
    <article class="stat-card">
      <span>Частая редкость</span>
      <strong>${escapeHtml(topRarity)}</strong>
    </article>
    <article class="stat-card">
      <span>Первое открытие</span>
      <strong>${escapeHtml(formatDate(state.stats.first_opened_at))}</strong>
    </article>
    <article class="stat-card">
      <span>Последнее открытие</span>
      <strong>${escapeHtml(formatDate(state.stats.last_opened_at))}</strong>
    </article>
  `;
};

const savedFilters = () => ({
  rarity: qs('[data-saved-rarity]')?.value || 'all',
  search: (qs('[data-saved-search]')?.value || '').trim().toLowerCase(),
  sort: qs('[data-saved-sort]')?.value || 'new',
});

const filteredSavedCards = () => {
  const filters = savedFilters();
  let cards = [...state.savedCards];

  if (filters.rarity !== 'all') {
    cards = cards.filter((card) => card.rarity === filters.rarity);
  }

  if (filters.search !== '') {
    cards = cards.filter((card) => [
      card.prediction_title,
      card.prediction_text,
      card.pack_title,
      card.note,
    ].some((value) => String(value ?? '').toLowerCase().includes(filters.search)));
  }

  if (filters.sort === 'old') {
    cards.sort((a, b) => new Date(a.saved_at) - new Date(b.saved_at));
  } else if (filters.sort === 'rarity') {
    cards.sort((a, b) => (rarityRank[b.rarity] || 0) - (rarityRank[a.rarity] || 0));
  } else {
    cards.sort((a, b) => new Date(b.saved_at) - new Date(a.saved_at));
  }

  return cards;
};

const renderSavedCards = () => {
  const container = qs('[data-saved-list]');
  if (!container) return;

  const cards = filteredSavedCards();

  if (state.savedCards.length === 0) {
    container.innerHTML = '<p class="empty-state">Пока коллекция пустая. Открой пару паков и сохрани то, что подозрительно попало в точку.</p>';
    return;
  }

  if (cards.length === 0) {
    container.innerHTML = '<p class="empty-state">По этим фильтрам ничего нет. Карточки не пропали, они просто в другом ящике.</p>';
    return;
  }

  container.innerHTML = cards.map((card) => `
    <article class="saved-card rarity-card rarity-${escapeHtml(card.rarity)}" data-saved-id="${card.saved_id}">
      <div class="card-meta-row">
        ${rarityBadge(card.rarity)}
        <span>${escapeHtml(card.pack_title)}</span>
      </div>
      ${card.prediction_title ? `<h3>${escapeHtml(card.prediction_title)}</h3>` : ''}
      <p>${escapeHtml(card.prediction_text)}</p>
      <p class="muted-line">Сохранено: ${escapeHtml(formatDate(card.saved_at))}</p>
      <label class="note-field">
        <span>Заметка</span>
        <textarea maxlength="500" data-note-input="${card.saved_id}" placeholder="Например: это было слишком в тему">${escapeHtml(card.note || '')}</textarea>
      </label>
      <div class="card-actions">
        <button class="button is-secondary" type="button" data-save-note="${card.saved_id}" aria-label="Сохранить заметку">Сохранить заметку</button>
        <button class="text-button danger-link" type="button" data-delete-saved="${card.saved_id}" aria-label="Удалить карточку из сохранённых">Удалить</button>
      </div>
      <div class="inline-feedback note-inline-feedback" data-note-feedback="${card.saved_id}" aria-live="polite" hidden></div>
    </article>
  `).join('');
};

const renderPackOptions = () => {
  const select = qs('[data-history-pack]');
  if (!select) return;

  select.innerHTML = [
    '<option value="all">Все паки</option>',
    ...state.packs.map((pack) => `<option value="${escapeHtml(pack.slug)}">${escapeHtml(pack.title)}</option>`),
  ].join('');
};

const historyQuery = () => {
  const params = new URLSearchParams();
  const pack = qs('[data-history-pack]')?.value || 'all';
  const rarity = qs('[data-history-rarity]')?.value || 'all';
  const limit = qs('[data-history-limit]')?.value || '50';
  const savedOnly = qs('[data-history-saved]')?.checked || false;

  if (pack !== 'all') params.set('pack', pack);
  if (rarity !== 'all') params.set('rarity', rarity);
  if (savedOnly) params.set('saved', '1');
  params.set('limit', limit);

  return params.toString();
};

const renderHistory = () => {
  const container = qs('[data-cabinet-history]');
  if (!container) return;

  if (state.history.length === 0) {
    container.innerHTML = '<p class="empty-state">История пока чистая. Рандом ещё не успел оставить следы.</p>';
    return;
  }

  container.innerHTML = `
    <div class="history-items">
      ${state.history.map((item) => `
        <article class="history-card ${item.saved ? 'is-saved' : ''}">
          <div class="card-meta-row">
            ${rarityBadge(item.rarity)}
            <span>${escapeHtml(item.pack_title)}</span>
            ${item.saved ? '<span class="saved-pill">в коллекции</span>' : ''}
          </div>
          ${item.prediction_title ? `<h3>${escapeHtml(item.prediction_title)}</h3>` : ''}
          <p>${escapeHtml(item.prediction_text)}</p>
          ${item.user_question ? `<p class="context-line">Вопрос: ${escapeHtml(item.user_question)}</p>` : ''}
          ${(item.choice_a || item.choice_b) ? `<p class="context-line">Выбор: ${escapeHtml(item.choice_a || 'А')} / ${escapeHtml(item.choice_b || 'Б')}</p>` : ''}
          <p class="muted-line">${escapeHtml(formatDate(item.opened_at))}</p>
        </article>
      `).join('')}
    </div>
  `;
};

const loadSummary = async () => {
  const payload = await api('/api/cabinet/summary');
  state.user = payload.user;
  state.stats = payload.stats;
  renderProfile();
  renderStats();
};

const loadPacks = async () => {
  const payload = await api('/api/packs');
  state.packs = payload.packs || [];
  renderPackOptions();
};

const loadSaved = async () => {
  const container = qs('[data-saved-list]');
  if (container) container.innerHTML = '<p class="empty-state">Коллекция загружается. Карточки ищут приличную позу.</p>';

  const payload = await api('/api/cabinet/saved');
  state.savedCards = payload.cards || [];
  renderSavedCards();
};

const loadHistory = async () => {
  const container = qs('[data-cabinet-history]');
  if (container) container.innerHTML = '<p class="empty-state">История загружается. Сейчас вспомним, что тут было.</p>';

  const query = historyQuery();
  const payload = await api(`/api/cabinet/history${query ? `?${query}` : ''}`);
  state.history = payload.history || [];
  renderHistory();
};

const refreshCabinet = async () => {
  await Promise.allSettled([loadSummary(), loadSaved(), loadHistory()]);
};

const updateNote = async (savedId) => {
  const input = qs(`[data-note-input="${savedId}"]`);
  const note = input?.value || '';

  const payload = await cabinetMutation('/api/cabinet/saved/update-note', {
    saved_id: Number(savedId),
    note,
  });

  await loadSaved();

  const card = qs(`[data-saved-id="${savedId}"]`);
  const feedback = qs(`[data-note-feedback="${savedId}"]`, card ?? document);
  const updatedInput = qs(`[data-note-input="${savedId}"]`, card ?? document);
  const button = qs(`[data-save-note="${savedId}"]`, card ?? document);

  if (feedback) {
    setMessage('');
    showInlineFeedback(feedback, payload.message || 'Заметка сохранена.');
    flashSavedState(updatedInput, button);
    return;
  }

  setMessage(payload.message || 'Заметка сохранена.', 'success');
};

const deleteSaved = async (savedId) => {
  const payload = await cabinetMutation('/api/cabinet/saved/delete', {
    saved_id: Number(savedId),
  });

  setMessage(payload.message || 'Карточка убрана из коллекции.', 'success');
  await Promise.allSettled([loadSummary(), loadSaved(), loadHistory()]);
};

const updateProfile = async (form) => {
  const payload = await cabinetMutation('/api/cabinet/profile/update', {
    username: new FormData(form).get('username'),
  });

  await loadSummary();
  setMessage('');
  showInlineFeedback(qs('[data-profile-feedback]', form), payload.message || 'Профиль обновлён.');
  flashSavedState(qs('input[name="username"]', form), qs('button[type="submit"]', form));
};

const changePassword = async (form) => {
  const data = new FormData(form);
  const payload = await cabinetMutation('/api/cabinet/profile/change-password', {
    current_password: data.get('current_password'),
    new_password: data.get('new_password'),
    new_password_confirm: data.get('new_password_confirm'),
  });

  form.reset();
  setMessage('');
  showInlineFeedback(qs('[data-password-feedback]', form), payload.message || 'Пароль обновлён.');
  flashSavedState(...qsa('input', form), qs('button[type="submit"]', form));
};

const setupReducedMotion = () => {
  const toggle = qs('[data-cabinet-motion-toggle]');
  const stored = storageGet('reducedMotion');
  const systemPrefers = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const reduced = stored === null ? systemPrefers : stored === 'true';

  document.documentElement.dataset.reducedMotion = String(reduced);
  if (toggle) toggle.checked = reduced;

  toggle?.addEventListener('change', () => {
    document.documentElement.dataset.reducedMotion = String(toggle.checked);
    storageSet('reducedMotion', String(toggle.checked));
  });
};

const bindEvents = () => {
  qs('[data-refresh-saved]')?.addEventListener('click', () => loadSaved().catch((error) => setMessage(error.message, 'error')));
  qs('[data-refresh-history]')?.addEventListener('click', () => loadHistory().catch((error) => setMessage(error.message, 'error')));

  qsa('[data-saved-rarity], [data-saved-sort]').forEach((input) => {
    input.addEventListener('change', renderSavedCards);
  });

  qs('[data-saved-search]')?.addEventListener('input', renderSavedCards);

  qsa('[data-history-pack], [data-history-rarity], [data-history-limit], [data-history-saved]').forEach((input) => {
    input.addEventListener('change', () => loadHistory().catch((error) => setMessage(error.message, 'error')));
  });

  qs('[data-saved-list]')?.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    const noteButton = target.closest('[data-save-note]');
    const deleteButton = target.closest('[data-delete-saved]');

    if (noteButton) {
      updateNote(noteButton.dataset.saveNote).catch((error) => setMessage(error.message, 'error'));
    }

    if (deleteButton) {
      deleteSaved(deleteButton.dataset.deleteSaved).catch((error) => setMessage(error.message, 'error'));
    }
  });

  qs('[data-profile-form]')?.addEventListener('submit', (event) => {
    event.preventDefault();
    updateProfile(event.currentTarget).catch((error) => setMessage(error.message, 'error'));
  });

  qs('[data-password-form]')?.addEventListener('submit', (event) => {
    event.preventDefault();
    changePassword(event.currentTarget).catch((error) => setMessage(error.message, 'error'));
  });
};

const init = async () => {
  const cabinet = root();
  if (!cabinet) return;

  document.documentElement.dataset.js = 'ready';
  setupReducedMotion();

  if (cabinet.dataset.authenticated !== 'true') {
    return;
  }

  bindEvents();
  setMessage('');

  try {
    await Promise.all([loadPacks(), refreshCabinet()]);
  } catch (error) {
    setMessage(error.message || 'Кабинет не загрузился. Попробуй обновить страницу.', 'error');
  }
};

document.addEventListener('DOMContentLoaded', init);
