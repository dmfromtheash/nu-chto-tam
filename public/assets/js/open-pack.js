const rarityLabels = {
  common: 'обычная',
  uncommon: 'необычная',
  rare: 'редкая',
  epic: 'эпическая',
  legendary: 'легендарная',
  mythic: 'мифическая',
};

const packTypeLabels = {
  daily: 'день',
  weekly: 'неделя',
  monthly: 'месяц',
  mood: 'вайб',
  question: 'вопрос',
  choice: 'выбор',
  light: 'лёгкий',
  action: 'действие',
  rare: 'редкий',
};

const packSlugLabels = {
  'take-leave': 'взять',
  direction: 'маршрут',
  'not-now': 'пауза',
  'inner-weather': 'погода',
  'normal-advice': 'совет',
  'send-to-friend': 'другу',
};

const packSymbols = {
  daily: '☼',
  weekly: '⋯',
  monthly: '◈',
  mood: '≋',
  question: '?',
  choice: '⤨',
  'take-leave': '±',
  direction: '↗',
  action: '⚡',
  rare: '✦',
  'not-now': 'Ⅱ',
  'inner-weather': '☁',
  'normal-advice': '☕',
  'send-to-friend': '✉',
  light: '✧',
};

const state = {
  packs: [],
  selectedPack: null,
  isOpening: false,
  reducedMotion: false,
};

const qs = (selector, root = document) => root.querySelector(selector);
const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));
const app = () => qs('[data-open-pack-app]');
const csrfToken = () => app()?.dataset.csrf || '';

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#039;',
})[char]);

const api = async (path, options = {}) => {
  const { headers: optionHeaders = {}, ...fetchOptions } = options;
  const response = await fetch(path, {
    credentials: 'same-origin',
    ...fetchOptions,
    headers: {
      Accept: 'application/json',
      ...(fetchOptions.body ? { 'Content-Type': 'application/json' } : {}),
      ...optionHeaders,
    },
  });

  let payload = null;

  try {
    payload = await response.json();
  } catch (error) {
    payload = {
      ok: false,
      error: 'Рандом споткнулся. Попробуй ещё раз.',
    };
  }

  if (!response.ok || payload.ok === false) {
    const message = response.status === 429
      ? 'Ты слишком быстро открываешь паки. Дай карточкам отдышаться.'
      : payload.error || 'Рандом споткнулся. Попробуй ещё раз.';
    const apiError = new Error(message);
    apiError.status = response.status;
    apiError.payload = payload;
    throw apiError;
  }

  return payload;
};

const wait = (ms) => new Promise((resolve) => {
  window.setTimeout(resolve, state.reducedMotion ? 0 : ms);
});

const setFeedback = (message = '', type = 'info') => {
  const feedback = qs('[data-feedback]');
  if (!feedback) return;

  feedback.textContent = message;
  feedback.className = `feedback open-feedback ${message ? `is-${type}` : ''}`;
};

const clearFeedback = () => setFeedback('');

const safeClass = (value) => String(value || 'default').replace(/[^a-z0-9-]/gi, '-').toLowerCase();

const cardCountForPack = (pack) => {
  if (!pack) return 1;
  if (pack.slug === 'take-leave') return Math.max(2, Number(pack.cards_per_open || 2));
  if (pack.type === 'weekly') return 3;
  if (pack.type === 'monthly') return 5;
  return Math.max(1, Number(pack.cards_per_open || 1));
};

const pluralShortCards = (count) => {
  if (count === 1) return 'карта';
  if (count >= 2 && count <= 4) return 'карты';
  return 'карт';
};

const packLabel = (pack) => packSlugLabels[pack?.slug] || packTypeLabels[pack?.type] || pack?.type || 'пак';
const packSymbol = (pack) => packSymbols[pack?.slug] || packSymbols[pack?.type] || '✦';

const renderSelectedPack = () => {
  const card = qs('[data-selected-pack-card]');
  const title = qs('[data-selected-pack-title]');
  const description = qs('[data-pack-description]');
  const meta = qs('[data-pack-meta]');
  if (!card || !title) return;

  if (!state.selectedPack) {
    title.textContent = 'Пак не найден';
    if (description) description.textContent = 'Такого пака здесь нет. Возможно, ссылка устала по дороге.';
    if (meta) meta.innerHTML = '<a class="text-button" href="/#packs">Вернуться к выбору</a>';
    card.innerHTML = '<p>Выбери другой пак на главной.</p>';
    return;
  }

  const count = cardCountForPack(state.selectedPack);
  const typeClass = safeClass(state.selectedPack.type);
  const slugClass = safeClass(state.selectedPack.slug);
  title.textContent = state.selectedPack.title;
  if (description) description.textContent = state.selectedPack.description || 'Пак выбран. Карты уже шуршат.';
  if (meta) {
    meta.innerHTML = `
      <span>${escapeHtml(packLabel(state.selectedPack))}</span>
      <span>${count} ${pluralShortCards(count)}</span>
    `;
  }

  card.innerHTML = `
    <button
      class="pack-booster pack-booster--${typeClass} pack-booster--${slugClass} open-pack-booster"
      type="button"
      data-stage-pack-button
      aria-label="Открыть выбранный пак ${escapeHtml(state.selectedPack.title)}"
    >
      <span class="pack-booster__foil" aria-hidden="true"></span>
      <span class="pack-booster__top">${escapeHtml(packLabel(state.selectedPack))}</span>
      <span class="pack-booster__sigil" aria-hidden="true">${escapeHtml(packSymbol(state.selectedPack))}</span>
      <span class="pack-booster__title">${escapeHtml(state.selectedPack.title)}</span>
      <span class="pack-booster__description">${escapeHtml(state.selectedPack.description)}</span>
      <span class="pack-booster__count">${count} ${pluralShortCards(count)}</span>
    </button>
  `;

  qs('[data-stage-pack-button]', card)?.addEventListener('click', () => openSelectedPack());
};

const renderDeckPreview = (count = 1, isActive = false) => {
  const preview = qs('[data-deck-preview]');
  if (!preview) return;

  const limited = Math.max(1, Math.min(5, Number(count || 1)));
  preview.dataset.cardCount = String(limited);
  preview.innerHTML = Array.from({ length: limited }).map((_, index) => `
    <span
      class="preview-card ${isActive ? 'is-active' : ''}"
      style="--card-index: ${index}; --deck-size: ${limited};"
      aria-hidden="true"
    >
      <span class="preview-card__sigil">${escapeHtml(packSymbol(state.selectedPack))}</span>
    </span>
  `).join('');
};

const renderContextFields = () => {
  const fields = qs('[data-context-fields]');
  if (!fields) return;

  const pack = state.selectedPack;
  if (!pack) {
    fields.innerHTML = '';
    return;
  }

  if (pack.slug === 'question') {
    fields.innerHTML = `
      <label class="form-field scene-field">
        <span>Что спросим у рандома?</span>
        <textarea name="user_question" rows="3" placeholder="Например: стоит ли сегодня начинать новое?"></textarea>
      </label>
    `;
    return;
  }

  if (pack.slug === 'choice') {
    fields.innerHTML = `
      <div class="choice-fields scene-choice-fields">
        <label class="form-field scene-field">
          <span>Вариант А</span>
          <input name="choice_a" type="text" placeholder="Сделать сейчас">
        </label>
        <label class="form-field scene-field">
          <span>Вариант Б</span>
          <input name="choice_b" type="text" placeholder="Отложить">
        </label>
      </div>
    `;
    return;
  }

  if (pack.slug === 'mood') {
    fields.innerHTML = segmentedControl('mood', [
      ['спокойно', 'спокойно'],
      ['хаотично', 'хаотично'],
      ['вдохновлённо', 'вдохновлённо'],
      ['местами бесит', 'местами бесит'],
    ]);
    return;
  }

  if (pack.slug === 'direction') {
    fields.innerHTML = segmentedControl('direction', [
      ['к людям', 'к людям'],
      ['к делу', 'к делу'],
      ['к себе', 'к себе'],
      ['от лишнего', 'от лишнего'],
    ]);
    return;
  }

  if (pack.slug === 'not-now') {
    fields.innerHTML = '<p class="context-hint">Этот пак не требует объяснений. Иногда нормальный ответ — нажать кнопку и выдохнуть.</p>';
    return;
  }

  fields.innerHTML = '';
};

const segmentedControl = (name, options) => `
  <fieldset class="segment-group scene-segments">
    <legend>${name === 'mood' ? 'Выбери вайб' : 'Куда вообще грести?'}</legend>
    <div class="segments">
      ${options.map(([value, label], index) => `
        <label>
          <input type="radio" name="${name}" value="${escapeHtml(value)}" ${index === 0 ? 'checked' : ''}>
          <span>${escapeHtml(label)}</span>
        </label>
      `).join('')}
    </div>
  </fieldset>
`;

const formContext = () => {
  const form = qs('[data-open-form]');
  if (!form) return {};

  const formData = new FormData(form);
  const context = {};

  ['user_question', 'choice_a', 'choice_b', 'mood', 'direction'].forEach((key) => {
    const value = String(formData.get(key) ?? '').trim();
    if (value) context[key] = value;
  });

  return context;
};

const renderStageCards = (cardHtml, count) => {
  if (Number(count) !== 5) {
    return cardHtml.join('');
  }

  return `
    <div class="card-stage-row card-stage-row--top">
      ${cardHtml.slice(0, 3).join('')}
    </div>
    <div class="card-stage-row card-stage-row--bottom">
      ${cardHtml.slice(3).join('')}
    </div>
  `;
};

let fitTextFrame = 0;

const hasTextOverflow = (element) => (
  element.scrollHeight > element.clientHeight + 1
  || element.scrollWidth > element.clientWidth + 1
);

const fitTextBlock = (element, maxSize, minSize, lineHeight) => {
  if (!element) return;

  element.style.setProperty('--fit-body-line-height', String(lineHeight));
  element.style.removeProperty('--fit-body-font-size');
  element.style.removeProperty('--fit-title-font-size');
  element.style.fontSize = `${maxSize}px`;
  element.style.lineHeight = String(lineHeight);
  element.style.textAlign = 'center';

  for (let size = maxSize; size >= minSize; size -= 0.5) {
    element.style.fontSize = `${size}px`;

    if (!hasTextOverflow(element)) {
      return true;
    }
  }

  element.style.fontSize = `${minSize}px`;
  return !hasTextOverflow(element);
};

const fitProfileForCount = (count, cardWidth) => {
  if (count >= 5) {
    return {
      bodyMax: Math.round(Math.min(17, Math.max(14, cardWidth * 0.055))),
      bodyMin: 13,
      bodyLineHeight: 1.34,
      titleMax: Math.round(Math.min(23, Math.max(17, cardWidth * 0.07))),
      titleMin: 14,
      titleLineHeight: 1.08,
    };
  }

  if (count >= 2) {
    return {
      bodyMax: Math.round(Math.min(20, Math.max(16, cardWidth * 0.06))),
      bodyMin: 14,
      bodyLineHeight: 1.36,
      titleMax: Math.round(Math.min(28, Math.max(19, cardWidth * 0.075))),
      titleMin: 15,
      titleLineHeight: 1.08,
    };
  }

  return {
    bodyMax: Math.round(Math.min(26, Math.max(20, cardWidth * 0.062))),
    bodyMin: 16,
    bodyLineHeight: 1.4,
    titleMax: Math.round(Math.min(34, Math.max(24, cardWidth * 0.085))),
    titleMin: 18,
    titleLineHeight: 1.08,
  };
};

const fitCardText = (cardElement) => {
  if (!cardElement) return;

  const grid = qs('[data-result-grid]');
  const count = Number(grid?.dataset.cardCount || 1);
  const title = qs('.game-card__title', cardElement);
  const body = qs('.game-card__body', cardElement);

  const cardWidth = cardElement.getBoundingClientRect().width || 300;
  const profile = fitProfileForCount(count, cardWidth);

  cardElement.classList.remove('is-text-compact', 'is-text-scroll');

  fitTextBlock(title, profile.titleMax, profile.titleMin, profile.titleLineHeight);

  const bodyFits = fitTextBlock(body, profile.bodyMax, profile.bodyMin, profile.bodyLineHeight);
  if (!bodyFits && body) {
    cardElement.classList.add('is-text-compact');
    body.style.lineHeight = String(Math.max(1.28, profile.bodyLineHeight - 0.06));

    if (hasTextOverflow(body)) {
      cardElement.classList.add('is-text-scroll');
    }
  }
};

const fitResultText = () => {
  const grid = qs('[data-result-grid]');
  if (!grid) return;

  qsa('.game-card', grid).forEach((cardElement) => fitCardText(cardElement));
};

const scheduleTextFit = () => {
  window.cancelAnimationFrame(fitTextFrame);
  fitTextFrame = window.requestAnimationFrame(() => {
    window.requestAnimationFrame(fitResultText);
  });
};

const renderIncomingBacks = (count) => {
  const grid = qs('[data-result-grid]');
  if (!grid) return;

  const limited = Math.max(1, Math.min(5, Number(count || 1)));
  enterResultMode();
  setResultCountClass(grid, limited);
  const cardHtml = Array.from({ length: limited }).map((_, index) => `
    <article class="game-card game-card--closed is-dealt" style="--card-index: ${index}; --deck-size: ${limited}; --delay: ${index * 80}ms">
      <div class="game-card__inner">
        <div class="game-card__face game-card__back">
          <span class="game-card__back-symbol" aria-hidden="true">${escapeHtml(packSymbol(state.selectedPack))}</span>
          <span class="game-card__back-text">Ну что там?</span>
        </div>
      </div>
    </article>
  `);

  grid.innerHTML = renderStageCards(cardHtml, limited);
};

const renderResult = (pack, cards) => {
  const grid = qs('[data-result-grid]');
  if (!grid) return;

  if (!cards?.length) {
    grid.innerHTML = '<p class="empty-state">Карточки не пришли. Очень странно, но без паники.</p>';
    return;
  }

  enterResultMode();
  setResultCountClass(grid, cards.length);
  const cardHtml = cards.map((card, index) => {
    const rarity = card.rarity || 'common';
    const context = parseContext(card.result_context);
    const slotLabel = takeLeaveLabel(pack, context, index);
    const title = slotLabel || card.title || `Карточка ${index + 1}`;
    const rarityText = rarityLabels[rarity] || rarity;

    return `
      <article
        class="game-card game-card--closed is-awaiting-reveal rarity-${escapeHtml(rarity)}"
        style="--card-index: ${index}; --deck-size: ${cards.length}; --delay: ${index * 120}ms"
        tabindex="0"
        role="button"
        aria-label="Раскрыть карточку ${index + 1}: ${escapeHtml(rarityText)}"
      >
        <div class="game-card__inner">
          <div class="game-card__face game-card__back">
            <span class="game-card__back-symbol" aria-hidden="true">${escapeHtml(packSymbol(pack))}</span>
            <span class="game-card__back-text">Ну что там?</span>
          </div>
          <div class="game-card__face game-card__front">
            <div class="game-card__ornament" aria-hidden="true">${escapeHtml(packSymbol(pack))}</div>
            <header class="game-card__header">
              <span class="game-card__pack">${escapeHtml(pack.title)}</span>
              <span class="game-card__rarity rarity-badge rarity-${escapeHtml(rarity)}">${escapeHtml(rarityText)}</span>
            </header>
            <h3 class="game-card__title">${escapeHtml(title)}</h3>
            <p class="game-card__body">${escapeHtml(card.text)}</p>
            <footer class="game-card__footer">
              <button class="button is-secondary save-card-button" type="button" data-save-card="${Number(card.opening_id)}">
                Сохранить в коллекцию
              </button>
              <div class="save-message" data-save-message="${Number(card.opening_id)}" aria-live="polite"></div>
            </footer>
          </div>
        </div>
      </article>
    `;
  });

  grid.innerHTML = renderStageCards(cardHtml, cards.length);
  scheduleTextFit();

  qsa('[data-save-card]', grid).forEach((button) => {
    button.addEventListener('click', () => saveCard(Number(button.dataset.saveCard), button));
  });

  qsa('.game-card.is-awaiting-reveal', grid).forEach((cardElement) => {
    cardElement.addEventListener('click', () => revealCard(cardElement));
    cardElement.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;

      event.preventDefault();
      revealCard(cardElement);
    });
  });
};

const revealCard = (cardElement) => {
  if (!cardElement || cardElement.classList.contains('is-revealed')) return;

  cardElement.classList.remove('game-card--closed', 'is-awaiting-reveal');
  cardElement.classList.add('is-revealed');
  cardElement.setAttribute('aria-label', 'Карточка раскрыта');
  cardElement.removeAttribute('role');
  cardElement.tabIndex = -1;
  scheduleTextFit();
};

const setResultCountClass = (grid, count) => {
  grid.dataset.cardCount = String(count);
  ['1', '2', '3', '4', '5'].forEach((value) => {
    grid.classList.remove(`card-results--count-${value}`);
    grid.classList.remove(`card-stage-results--count-${value}`);
  });
  grid.classList.add(`card-results--count-${count}`);
  grid.classList.add(`card-stage-results--count-${count}`);
};

const enterResultMode = () => {
  qs('[data-opening-stage]')?.classList.add('has-results');
};

const parseContext = (raw) => {
  if (!raw) return {};
  try {
    return JSON.parse(raw);
  } catch (error) {
    return {};
  }
};

const takeLeaveLabel = (pack, context, index) => {
  if (pack.slug !== 'take-leave') return '';
  if (context.slot === 'take') return 'Взять с собой';
  if (context.slot === 'leave') return 'Оставить дома';
  return index === 0 ? 'Взять с собой' : 'Оставить дома';
};

const saveCard = async (openingId, button) => {
  const messageNode = qs(`[data-save-message="${openingId}"]`);
  button.disabled = true;
  button.textContent = 'Сохраняю...';

  try {
    const payload = await api('/api/save-card', {
      method: 'POST',
      headers: {
        'X-CSRF-Token': csrfToken(),
      },
      body: JSON.stringify({ opening_id: openingId }),
    });
    button.textContent = payload.message?.includes('уже') ? 'Уже в коллекции' : 'Сохранено';
    if (messageNode) messageNode.textContent = payload.message || 'Сохранено.';
  } catch (error) {
    if (error.status === 401) {
      button.textContent = 'Сохранить в коллекцию';
      button.disabled = false;
      if (messageNode) {
        messageNode.innerHTML = `
          Чтобы сохранить, нужно <a href="/login">войти</a> или
          <a href="/register">зарегистрироваться</a>. У рандома с памятью сложно.
        `;
      }
      return;
    }

    button.textContent = 'Сохранить в коллекцию';
    button.disabled = false;
    if (messageNode) messageNode.textContent = error.message || 'Не получилось сохранить.';
  }
};

const openSelectedPack = async () => {
  if (!state.selectedPack || state.isOpening) return;

  state.isOpening = true;
  const openButton = qs('[data-open-button]');
  const openAgainButton = qs('[data-open-again]');
  const stage = qs('[data-opening-stage]');
  const stageButton = qs('[data-stage-pack-button]');
  const count = cardCountForPack(state.selectedPack);

  if (openButton) {
    openButton.disabled = true;
    openButton.textContent = 'Открываю...';
  }
  if (openAgainButton) openAgainButton.disabled = true;
  if (stageButton) stageButton.disabled = true;

  renderDeckPreview(count, true);
  setFeedback('Карты уже шуршат.', 'loading');
  stage?.classList.add('is-opening');

  try {
    const payload = await api('/api/open-pack', {
      method: 'POST',
      body: JSON.stringify({
        pack: state.selectedPack.slug,
        ...formContext(),
      }),
    });

    renderIncomingBacks(payload.cards?.length || count);
    await wait(320);
    renderResult(payload.pack, payload.cards);
    clearFeedback();
  } catch (error) {
    setFeedback(error.message || 'Рандом споткнулся. Попробуй ещё раз.', 'error');
  } finally {
    state.isOpening = false;
    stage?.classList.remove('is-opening');
    renderDeckPreview(cardCountForPack(state.selectedPack));
    if (openButton) {
      openButton.disabled = false;
      openButton.textContent = 'Открыть пак';
    }
    if (openAgainButton) openAgainButton.disabled = false;
    if (stageButton) stageButton.disabled = false;
  }
};

const loadPack = async () => {
  const initialSlug = app()?.dataset.initialPack || '';
  setFeedback('Загружаю пак. Он где-то тут, просто шуршит тихо.', 'loading');

  try {
    const payload = await api('/api/packs');
    state.packs = payload.packs || [];
    state.selectedPack = state.packs.find((pack) => pack.slug === initialSlug) || null;

    if (!state.selectedPack) {
      renderSelectedPack();
      renderDeckPreview(1);
      setFeedback('Такого пака нет. Лучше выбрать другой на главной.', 'error');
      return;
    }

    renderSelectedPack();
    renderDeckPreview(cardCountForPack(state.selectedPack));
    renderContextFields();
    clearFeedback();

    const openButton = qs('[data-open-button]');
    const openAgainButton = qs('[data-open-again]');
    if (openButton) openButton.disabled = false;
    if (openAgainButton) openAgainButton.disabled = false;
  } catch (error) {
    setFeedback(error.message || 'Пак не загрузился. Попробуй вернуться к выбору.', 'error');
  }
};

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
    // Motion preference still works for the current page even without storage.
  }
};

const setupReducedMotion = () => {
  const toggle = qs('[data-motion-toggle]');
  const stored = storageGet('reducedMotion');
  const systemPrefers = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  state.reducedMotion = stored === null ? systemPrefers : stored === 'true';
  document.documentElement.dataset.reducedMotion = String(state.reducedMotion);
  if (toggle) toggle.checked = state.reducedMotion;

  toggle?.addEventListener('change', () => {
    state.reducedMotion = toggle.checked;
    document.documentElement.dataset.reducedMotion = String(state.reducedMotion);
    storageSet('reducedMotion', String(state.reducedMotion));
  });
};

const bindEvents = () => {
  qs('[data-open-form]')?.addEventListener('submit', (event) => {
    event.preventDefault();
    openSelectedPack();
  });

  qs('[data-open-again]')?.addEventListener('click', () => openSelectedPack());
  window.addEventListener('resize', scheduleTextFit);
};

const init = async () => {
  if (!app()) return;

  document.documentElement.dataset.js = 'ready';
  setupReducedMotion();
  bindEvents();
  await loadPack();
};

document.addEventListener('DOMContentLoaded', init);
