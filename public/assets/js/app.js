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

const featuredCopy = {
  daily: {
    label: 'Первое открытие',
    title: 'Карта на сегодня',
    text: 'Самый короткий вход в сцену: одна карта, чтобы поймать тон дня без долгих раскладов.',
  },
  weekly: {
    label: 'На период',
    title: 'Неделя в трёх кадрах',
    text: 'Открой сцену с тремя картами: с чего начать, где не расплескаться и чем закрыть круг.',
  },
  monthly: {
    label: 'На период',
    title: 'Месяц крупным планом',
    text: 'Пять карт для длинного захода: не план жизни, а спокойная сводка в сцене открытия.',
  },
  mood: {
    label: 'Внутренний тон',
    title: 'Проверить настроение',
    text: 'Мягкий старт для момента, когда внутри шумно и хочется вытащить короткое название.',
  },
  question: {
    label: 'Вопрос рандому',
    title: 'Вопрос рандому',
    text: 'Выбери вопрос, открой сцену и дай карточке ответить без лишней серьёзности.',
  },
  rare: {
    label: 'Редкий старт',
    title: 'Странная удача на витрине',
    text: 'Для случаев, когда хочется начать с необычного пака и немного прищуриться.',
  },
  'inner-weather': {
    label: 'Внутренний тон',
    title: 'Проверить внутренний тон',
    text: 'Небольшая сцена для состояния, которое сложно объяснить одним словом.',
  },
};

const state = {
  packs: [],
  history: [],
  reducedMotion: false,
  favoriteSlug: '',
};

const qs = (selector, root = document) => root.querySelector(selector);
const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));
const app = () => qs('[data-card-app]');
const uiText = (key, fallback = '') => String(window.NCHT_TEXTS?.[key] ?? fallback);

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
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...(options.headers ?? {}),
    },
    ...options,
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
    const apiError = new Error(payload.error || 'Рандом споткнулся. Попробуй ещё раз.');
    apiError.status = response.status;
    apiError.payload = payload;
    throw apiError;
  }

  return payload;
};

const safeClass = (value) => String(value || 'default').replace(/[^a-z0-9-]/gi, '-').toLowerCase();

const cardCountForPack = (pack) => {
  if (!pack) return 1;
  if (pack.slug === 'take-leave') return Math.max(2, Number(pack.cards_per_open || 2));
  if (pack.type === 'weekly') return 3;
  if (pack.type === 'monthly') return 5;
  return Math.max(1, Number(pack.cards_per_open || 1));
};

const pluralShortCards = (count) => {
  const normalized = Math.abs(Number(count || 0)) % 100;
  const last = normalized % 10;

  if (normalized > 10 && normalized < 20) return 'карт';
  if (last === 1) return 'карта';
  if (last >= 2 && last <= 4) return 'карты';
  return 'карт';
};

const pluralShortPacks = (count) => {
  const normalized = Math.abs(Number(count || 0)) % 100;
  const last = normalized % 10;

  if (normalized > 10 && normalized < 20) return 'паков';
  if (last === 1) return 'пак';
  if (last >= 2 && last <= 4) return 'пака';
  return 'паков';
};

const pluralShortCardItems = (count) => {
  const normalized = Math.abs(Number(count || 0)) % 100;
  const last = normalized % 10;

  if (normalized > 10 && normalized < 20) return 'карточек';
  if (last === 1) return 'карточка';
  if (last >= 2 && last <= 4) return 'карточки';
  return 'карточек';
};

const packLabel = (pack) => packSlugLabels[pack?.slug] || packTypeLabels[pack?.type] || pack?.type || 'пак';
const packSymbol = (pack) => packSymbols[pack?.slug] || packSymbols[pack?.type] || '✦';
const packUrl = (pack) => `/open?pack=${encodeURIComponent(pack.slug)}`;

const featuredSlugs = () => {
  const preferredGroups = [
    ['daily'],
    ['monthly', 'weekly'],
    ['inner-weather', 'mood'],
  ];
  const selected = preferredGroups
    .map((group) => group.find((slug) => state.packs.some((pack) => pack.slug === slug)))
    .filter(Boolean);

  state.packs.forEach((pack) => {
    if (selected.length < 3 && !selected.includes(pack.slug)) {
      selected.push(pack.slug);
    }
  });

  return selected.slice(0, 3);
};

const packCategoryDefinitions = () => [
  {
    id: 'quick',
    sectionId: 'section-fast',
    icon: '⚡',
    title: uiText('pack.category.quick.title', 'Быстрый старт'),
    text: uiText('pack.category.quick.text', 'Паки на один короткий взгляд: день, настроение, совет и маленькое действие.'),
    slugs: ['daily', 'mood', 'normal-advice', 'action', 'direction'],
  },
  {
    id: 'period',
    sectionId: 'section-periods',
    icon: '◷',
    title: uiText('pack.category.period.title', 'Периоды и сводки'),
    text: uiText('pack.category.period.text', 'Неделя, месяц и состояние внутри, когда хочется посмотреть чуть шире.'),
    slugs: ['weekly', 'monthly', 'inner-weather'],
  },
  {
    id: 'choice',
    sectionId: 'section-choice',
    icon: '?',
    title: uiText('pack.category.choice.title', 'Вопросы и выбор'),
    text: uiText('pack.category.choice.text', 'Для вопросов, развилок, пауз и вариантов, которые выглядят подозрительно одинаково.'),
    slugs: ['question', 'choice', 'take-leave', 'not-now'],
  },
  {
    id: 'extra',
    sectionId: 'section-rare-friends',
    icon: '◆',
    title: uiText('pack.category.extra.title', 'Редкое и для друзей'),
    text: uiText('pack.category.extra.text', 'Странные удачные карточки и штуки, которые хочется кому-нибудь кинуть.'),
    slugs: ['rare', 'send-to-friend'],
  },
];

let packNavObserver = null;

const setActivePackNavLink = (sectionId) => {
  qsa('[data-pack-nav-link]').forEach((link) => {
    const isActive = link.getAttribute('href') === `#${sectionId}`;
    link.classList.toggle('is-active', isActive);
    if (isActive) {
      link.setAttribute('aria-current', 'true');
    } else {
      link.removeAttribute('aria-current');
    }
  });
};

const setupPackNavScrollSpy = () => {
  const links = qsa('[data-pack-nav-link]');
  const sections = links
    .map((link) => document.getElementById((link.getAttribute('href') || '').replace('#', '')))
    .filter(Boolean);

  if (packNavObserver) {
    packNavObserver.disconnect();
    packNavObserver = null;
  }

  if (!links.length || !sections.length) return;

  setActivePackNavLink(sections[0].id);

  if (!('IntersectionObserver' in window)) return;

  packNavObserver = new IntersectionObserver((entries) => {
    const visible = entries
      .filter((entry) => entry.isIntersecting)
      .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

    if (visible?.target?.id) {
      setActivePackNavLink(visible.target.id);
    }
  }, {
    rootMargin: '-24% 0px -58% 0px',
    threshold: [0.08, 0.22, 0.42, 0.62],
  });

  sections.forEach((section) => packNavObserver.observe(section));
};

const renderPackNavigation = (categorySections = []) => {
  const target = qs('[data-pack-nav-links]');
  if (!target) return;

  const items = [
    {
      sectionId: 'section-start',
      icon: '✦',
      title: uiText('home.featured.title', 'Стартовые паки'),
    },
    ...categorySections.map((category) => ({
      sectionId: category.sectionId,
      icon: category.icon || '•',
      title: category.title,
    })),
  ].filter((item) => item.sectionId && item.title);

  target.innerHTML = items.map((item, index) => `
    <a class="pack-nav__link${index === 0 ? ' is-active' : ''}" href="#${escapeHtml(item.sectionId)}" data-pack-nav-link${index === 0 ? ' aria-current="true"' : ''}>
      <span aria-hidden="true">${escapeHtml(item.icon)}</span>
      <strong>${escapeHtml(item.title)}</strong>
    </a>
  `).join('');

  setupPackNavScrollSpy();
};

const renderFeaturedPacks = () => {
  const target = qs('[data-featured-packs]');
  if (!target) return;

  if (!state.packs.length) {
    target.innerHTML = '<p class="empty-state">Паки ещё грузятся. Витрина скоро появится.</p>';
    return;
  }

  const items = featuredSlugs()
    .map((slug) => state.packs.find((pack) => pack.slug === slug))
    .filter(Boolean);

  target.innerHTML = items.map((pack) => {
    const copy = featuredCopy[pack.slug] || {
      label: 'Можно начать',
      title: pack.title,
      text: pack.description || 'Пак выглядит достаточно уверенно, чтобы открыть для него сцену.',
    };
    const count = cardCountForPack(pack);
    const typeClass = safeClass(pack.type);
    const slugClass = safeClass(pack.slug);

    return `
      <article class="featured-pack featured-pack--${typeClass} featured-pack--${slugClass}">
        <a class="featured-pack__booster mini-booster mini-booster--${typeClass} mini-booster--${slugClass}" href="${escapeHtml(packUrl(pack))}" aria-label="Открыть ${escapeHtml(pack.title)}">
          <span class="mini-booster__shine" aria-hidden="true"></span>
          <span class="mini-booster__type">${escapeHtml(packLabel(pack))}</span>
          <span class="mini-booster__sigil" aria-hidden="true">${escapeHtml(packSymbol(pack))}</span>
          <span class="mini-booster__title">${escapeHtml(pack.title)}</span>
          <span class="mini-booster__count">${count} ${pluralShortCards(count)}</span>
        </a>
        <div class="featured-pack__copy">
          <span class="featured-pack__label">${escapeHtml(copy.label)}</span>
          <h3>${escapeHtml(copy.title)}</h3>
          <p>${escapeHtml(copy.text)}</p>
          <a class="button featured-pack__action" href="${escapeHtml(packUrl(pack))}">Перейти к открытию</a>
        </div>
      </article>
    `;
  }).join('');
};

const renderPackGrid = () => {
  const grid = qs('[data-pack-grid]');
  if (!grid) return;

  if (!state.packs.length) {
    grid.innerHTML = `<p class="empty-state">${escapeHtml(uiText('home.packs.error', 'Паки не загрузились. Попробуй обновить витрину.'))}</p>`;
    renderFeaturedPacks();
    return;
  }

  const renderPack = (pack) => {
    const count = cardCountForPack(pack);
    const typeClass = safeClass(pack.type);
    const slugClass = safeClass(pack.slug);

    return `
      <a
        class="mini-booster mini-booster--${typeClass} mini-booster--${slugClass}"
        href="${escapeHtml(packUrl(pack))}"
        data-select-pack="${escapeHtml(pack.slug)}"
        aria-label="Открыть пак ${escapeHtml(pack.title)}"
      >
        <span class="mini-booster__shine" aria-hidden="true"></span>
        <span class="mini-booster__type">${escapeHtml(packLabel(pack))}</span>
        <span class="mini-booster__sigil" aria-hidden="true">${escapeHtml(packSymbol(pack))}</span>
        <span class="mini-booster__title">${escapeHtml(pack.title)}</span>
        <span class="mini-booster__count">${count} ${pluralShortCards(count)}</span>
        <span class="mini-booster__desc">${escapeHtml(pack.description)}</span>
        <span class="mini-booster__action">${escapeHtml(uiText('home.pack.open', 'Открыть'))}</span>
      </a>
    `;
  };

  const used = new Set();
  const visibleCategories = [];
  const sections = packCategoryDefinitions().map((category) => {
    const items = category.slugs
      .map((slug) => state.packs.find((pack) => pack.slug === slug))
      .filter(Boolean);

    items.forEach((pack) => used.add(pack.slug));
    if (!items.length) return '';
    visibleCategories.push(category);

    return `
      <section class="pack-category pack-category--${escapeHtml(safeClass(category.id))}" id="${escapeHtml(category.sectionId)}" data-pack-section>
        <header class="pack-category__head">
          <div>
            <h3>${escapeHtml(category.title)}</h3>
            <p>${escapeHtml(category.text)}</p>
          </div>
          <span>${items.length} ${pluralShortPacks(items.length)}</span>
        </header>
        <div class="pack-category__items">
          ${items.map(renderPack).join('')}
        </div>
      </section>
    `;
  }).filter(Boolean);

  const uncategorized = state.packs.filter((pack) => !used.has(pack.slug));
  if (uncategorized.length) {
    visibleCategories.push({
      sectionId: 'section-other',
      icon: '•',
      title: 'Ещё паки',
    });

    sections.push(`
      <section class="pack-category pack-category--other" id="section-other" data-pack-section>
        <header class="pack-category__head">
          <div>
            <h3>Ещё паки</h3>
            <p>Всё, что не попало в основные полки, но всё ещё открывается.</p>
          </div>
          <span>${uncategorized.length}</span>
        </header>
        <div class="pack-category__items">
          ${uncategorized.map(renderPack).join('')}
        </div>
      </section>
    `);
  }

  grid.innerHTML = `<div class="pack-category-grid">${sections.join('')}</div>`;
  renderPackNavigation(visibleCategories);
  renderFeaturedPacks();
  return;

  grid.innerHTML = state.packs.map((pack) => {
    const count = cardCountForPack(pack);
    const typeClass = safeClass(pack.type);
    const slugClass = safeClass(pack.slug);

    return `
      <a
        class="mini-booster mini-booster--${typeClass} mini-booster--${slugClass}"
        href="${escapeHtml(packUrl(pack))}"
        data-select-pack="${escapeHtml(pack.slug)}"
        aria-label="Открыть пак ${escapeHtml(pack.title)}"
      >
        <span class="mini-booster__shine" aria-hidden="true"></span>
        <span class="mini-booster__type">${escapeHtml(packLabel(pack))}</span>
        <span class="mini-booster__sigil" aria-hidden="true">${escapeHtml(packSymbol(pack))}</span>
        <span class="mini-booster__title">${escapeHtml(pack.title)}</span>
        <span class="mini-booster__count">${count} ${pluralShortCards(count)}</span>
        <span class="mini-booster__desc">${escapeHtml(pack.description)}</span>
        <span class="mini-booster__action">Открыть</span>
      </a>
    `;
  }).join('');

  renderFeaturedPacks();
};

const loadPacks = async () => {
  const grid = qs('[data-pack-grid]');
  if (grid) grid.innerHTML = `<p class="empty-state">${escapeHtml(uiText('home.packs.loading', 'Загружаю паки. Собираю витрину.'))}</p>`;

  try {
    const payload = await api('/api/packs');
    state.packs = payload.packs || [];
    renderPackGrid();
  } catch (error) {
    if (grid) grid.innerHTML = `<p class="empty-state">${escapeHtml(error.message || uiText('home.packs.error', 'Паки не загрузились.'))}</p>`;
  }
};

const loadHistory = async () => {
  const target = qs('[data-history]');
  if (!target) return;

  try {
    const payload = await api('/api/history');
    state.history = payload.history || [];
    const items = state.history.slice(0, 5);

    if (!items.length) {
      target.innerHTML = `<p class="empty-state">${escapeHtml(uiText('home.history.empty', 'Пока пусто. Последние открытия появятся здесь.'))}</p>`;
      return;
    }

    target.innerHTML = items.map((item) => `
      <article class="history-item">
        <span class="rarity-badge rarity-${escapeHtml(item.rarity)}">${escapeHtml(rarityLabels[item.rarity] || item.rarity)}</span>
        <h3>${escapeHtml(item.pack_title)}</h3>
        <p>${escapeHtml(item.prediction_text)}</p>
        <time datetime="${escapeHtml(item.opened_at)}">${escapeHtml(formatDate(item.opened_at))}</time>
      </article>
    `).join('');
  } catch (error) {
    target.innerHTML = `<p class="empty-state">${escapeHtml(error.message || 'История сейчас не открылась.')}</p>`;
  }
};

const loadStats = async () => {
  const target = qs('[data-stats]');
  if (!target) return;

  try {
    const payload = await api('/api/stats');
    const stats = payload.stats || {};

    const packCounts = stats.pack_openings_by_slug && Object.keys(stats.pack_openings_by_slug).length
      ? stats.pack_openings_by_slug
      : (stats.packs_opened_by_slug || {});
    const favorite = favoritePack(packCounts);
    const rarities = Object.entries(stats.rarity_counts || {});
    const totalCards = Number(stats.total_openings || 0);
    const todayCards = Number(stats.openings_today || 0);
    const totalPacks = Number(stats.pack_openings_total || 0) || packOpenCountFromStats(stats.packs_opened_by_slug || {});
    const todayPacks = Number(stats.pack_openings_today || 0) || packOpenCountFromHistory(state.history);

    target.innerHTML = `
      <div class="stat-card stat-card--cards">
        <small class="stat-card__kicker">Карточки</small>
        <span>${totalCards}</span>
        <p>открыто всего</p>
      </div>
      <div class="stat-card stat-card--packs">
        <small class="stat-card__kicker">Паки</small>
        <span>${totalPacks}</span>
        <p>открыто всего</p>
      </div>
      <div class="stat-card stat-card--cards">
        <small class="stat-card__kicker">Сегодня</small>
        <span>${todayCards}</span>
        <p>${pluralShortCardItems(todayCards)}</p>
      </div>
      <div class="stat-card stat-card--packs">
        <small class="stat-card__kicker">Сегодня</small>
        <span>${todayPacks}</span>
        <p>${pluralShortPacks(todayPacks)}</p>
      </div>
      <div class="stat-card is-wide">
        <span>${escapeHtml(favorite || 'пока нет')}</span>
        <p>самый частый пак</p>
      </div>
      <div class="rarity-summary">
        ${rarities.length ? rarities.map(([rarity, count]) => `
          <span class="rarity-badge rarity-${escapeHtml(rarity)}">${escapeHtml(rarityLabels[rarity] || rarity)}: ${Number(count)}</span>
        `).join('') : '<span class="muted-text">Редкости появятся после пары открытий.</span>'}
      </div>
    `;

    state.favoriteSlug = favoritePackSlug(packCounts) || state.favoriteSlug;
    renderFeaturedPacks();
  } catch (error) {
    target.innerHTML = `<p class="empty-state">${escapeHtml(error.message || 'Статистика решила прилечь на минутку.')}</p>`;
  }
};

const favoritePack = (counts) => {
  const slug = favoritePackSlug(counts);
  const pack = state.packs.find((item) => item.slug === slug);
  return pack?.title || slug || '';
};

const favoritePackSlug = (counts) => {
  const [slug] = Object.entries(counts).sort((a, b) => Number(b[1]) - Number(a[1]))[0] || [];
  return slug || '';
};

const packOpenCountFromStats = (counts) => Object.entries(counts).reduce((total, [slug, count]) => {
  const pack = state.packs.find((item) => item.slug === slug);
  const cardsPerOpen = cardCountForPack(pack || { slug, cards_per_open: 1 });
  return total + Math.ceil(Number(count || 0) / Math.max(1, cardsPerOpen));
}, 0);

const packOpenCountFromHistory = (history) => {
  const today = new Date().toISOString().slice(0, 10);

  return history.reduce((total, item) => {
    if (!String(item.opened_at || '').startsWith(today)) return total;

    const context = parseResultContext(item.result_context);
    return Number(context.card_index || 1) === 1 ? total + 1 : total;
  }, 0);
};

const parseResultContext = (value) => {
  if (!value) return {};

  try {
    const parsed = JSON.parse(value);
    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch (error) {
    return {};
  }
};

const formatDate = (value) => {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date);
};

const refreshHistoryAndStats = async () => {
  await Promise.allSettled([loadHistory(), loadStats()]);
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
  qs('[data-refresh-packs]')?.addEventListener('click', () => loadPacks());
  qs('[data-refresh-history]')?.addEventListener('click', () => refreshHistoryAndStats());
  qsa('[data-scroll-packs]').forEach((button) => {
    button.addEventListener('click', () => {
      const rail = qs('[data-pack-grid]');
      if (!rail) return;

      const direction = button.dataset.scrollPacks === 'prev' ? -1 : 1;
      rail.scrollBy({
        left: direction * Math.max(320, Math.round(rail.clientWidth * 0.72)),
        behavior: state.reducedMotion ? 'auto' : 'smooth',
      });
    });
  });
  qs('[data-random-pack]')?.addEventListener('click', () => {
    if (!state.packs.length) return;
    const pack = state.packs[Math.floor(Math.random() * state.packs.length)];
    window.location.href = packUrl(pack);
  });

  qs('[data-refresh-status]')?.addEventListener('click', () => {
    window.location.reload();
  });
};

const init = async () => {
  if (!app()) return;

  document.documentElement.dataset.js = 'ready';
  setupReducedMotion();
  bindEvents();
  await loadPacks();
  await refreshHistoryAndStats();
};

document.addEventListener('DOMContentLoaded', init);
