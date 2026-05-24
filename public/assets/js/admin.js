const root = () => document.querySelector('[data-admin-root]');
const qs = (selector, scope = document) => scope.querySelector(selector);
const qsa = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

const setFeedback = (message = '', type = 'info') => {
  const box = qs('[data-admin-feedback]');
  if (!box) return;

  box.textContent = message;
  box.hidden = message === '';
  box.className = `admin-feedback is-${type}`;
};

const csrfToken = () => root()?.dataset.csrf || '';

const formPayload = (form) => {
  const data = Object.fromEntries(new FormData(form).entries());

  qsa('input[type="checkbox"][name]', form).forEach((input) => {
    data[input.name] = input.checked ? '1' : '0';
  });

  return data;
};

const postJson = async (url, payload) => {
  const response = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken(),
    },
    body: JSON.stringify(payload),
  });

  let body = null;
  try {
    body = await response.json();
  } catch (error) {
    body = { ok: false, error: 'Админка получила странный ответ. Попробуй ещё раз.' };
  }

  if (!response.ok || body.ok === false) {
    throw new Error(body.error || 'Админ-действие не выполнилось.');
  }

  return body;
};

const handleAdminForm = async (event) => {
  const form = event.currentTarget;
  event.preventDefault();

  const confirmText = form.dataset.confirm;
  if (confirmText && !window.confirm(confirmText)) {
    return;
  }

  const endpoint = form.dataset.endpoint || form.getAttribute('action');
  if (!endpoint) {
    setFeedback('У формы нет endpoint. Это уже немного неловко.', 'error');
    return;
  }

  setFeedback('Сохраняю...', 'loading');

  try {
    const response = await postJson(endpoint, formPayload(form));
    setFeedback(response.message || 'Готово.', 'success');

    if (form.dataset.successRedirect) {
      window.location.href = form.dataset.successRedirect;
      return;
    }

    if (form.dataset.successAction === 'reload') {
      window.location.reload();
    }
  } catch (error) {
    setFeedback(error.message || 'Не получилось. Попробуй ещё раз.', 'error');
  }
};

const handleReorder = async (event) => {
  event.preventDefault();

  if (!window.confirm('Сохранить порядок паков?')) {
    return;
  }

  const items = qsa('[data-sort-id]').map((input) => ({
    id: Number(input.dataset.sortId),
    sort_order: Number(input.value || 0),
  }));

  setFeedback('Сохраняю порядок...', 'loading');

  try {
    const response = await postJson('/admin/api/packs/reorder', { items });
    setFeedback(response.message || 'Порядок сохранён.', 'success');
    window.location.reload();
  } catch (error) {
    setFeedback(error.message || 'Порядок не сохранился.', 'error');
  }
};

const chartColors = {
  page_view: '#b9a7ff',
  pack_opened: '#8ee7e0',
  card_saved: '#f0cd7b',
  register: '#8bd49e',
  login: '#8fa7ff',
  save_failed_guest: '#df8a9b',
  common: '#aeb8c8',
  uncommon: '#55d6b1',
  rare: '#60b7ff',
  epic: '#b985ff',
  legendary: '#f0cd7b',
  mythic: '#f3a6c8',
};

const chartLabels = {
  page_view: 'Просмотры',
  pack_opened: 'Открытия паков',
  card_saved: 'Сохранения',
  register: 'Регистрации',
  login: 'Входы',
};

const readChartData = (box) => {
  const script = qs('script[type="application/json"]', box);
  if (!script) return null;

  try {
    return JSON.parse(script.textContent || 'null');
  } catch (error) {
    return null;
  }
};

const emptyChart = (box, message) => {
  box.innerHTML = `<div class="chart-empty">${message}</div>`;
};

const createSvg = (width = 760, height = 280) => {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
  svg.setAttribute('role', 'img');
  svg.setAttribute('aria-hidden', 'false');
  return svg;
};

const svgEl = (name, attrs = {}) => {
  const node = document.createElementNS('http://www.w3.org/2000/svg', name);
  Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, String(value)));
  return node;
};

const renderLegend = (items) => {
  const legend = document.createElement('div');
  legend.className = 'chart-legend';
  items.forEach((item) => {
    const pill = document.createElement('span');
    const dot = document.createElement('i');
    dot.style.background = item.color;
    pill.append(dot, document.createTextNode(item.label));
    legend.append(pill);
  });
  return legend;
};

const renderActivityChart = (box, rows) => {
  const series = ['page_view', 'pack_opened', 'card_saved', 'register', 'login'];
  const hasActivity = Array.isArray(rows)
    && rows.some((row) => series.some((key) => Number(row[key] || 0) > 0));

  if (!hasActivity) {
    emptyChart(box, 'Пока данных мало. Открой пару паков или зайди как гость — графики оживут.');
    return;
  }

  const rawMax = Math.max(1, ...rows.flatMap((row) => series.map((key) => Number(row[key] || 0))));
  const magnitude = 10 ** Math.floor(Math.log10(rawMax));
  const normalized = rawMax / magnitude;
  const niceMax = (normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10) * magnitude;
  const max = Math.max(5, niceMax);
  const width = 820;
  const height = 320;
  const pad = { left: 68, right: 24, top: 28, bottom: 62 };
  const plotW = width - pad.left - pad.right;
  const plotH = height - pad.top - pad.bottom;
  const x = (index) => pad.left + (rows.length === 1 ? plotW / 2 : (plotW * index) / (rows.length - 1));
  const y = (value) => pad.top + plotH - (plotH * Number(value || 0)) / max;
  const svg = createSvg(width, height);

  [0, 0.25, 0.5, 0.75, 1].forEach((step) => {
    const gy = pad.top + plotH * step;
    const value = Math.round(max * (1 - step));
    svg.append(svgEl('line', { x1: pad.left, y1: gy, x2: width - pad.right, y2: gy, class: 'chart-grid-line' }));
    const yText = svgEl('text', { x: pad.left - 10, y: gy + 4, 'text-anchor': 'end', class: 'chart-axis-label chart-axis-label--y' });
    yText.textContent = String(value);
    svg.append(yText);
  });

  svg.append(svgEl('line', { x1: pad.left, y1: pad.top, x2: pad.left, y2: pad.top + plotH, class: 'chart-axis-line' }));
  svg.append(svgEl('line', { x1: pad.left, y1: pad.top + plotH, x2: width - pad.right, y2: pad.top + plotH, class: 'chart-axis-line' }));

  series.forEach((key) => {
    const points = rows.map((row, index) => `${x(index)},${y(row[key])}`).join(' ');
    const line = svgEl('polyline', {
      points,
      fill: 'none',
      stroke: chartColors[key],
      'stroke-width': key === 'pack_opened' ? 3 : 2,
      'stroke-linecap': 'round',
      'stroke-linejoin': 'round',
    });
    svg.append(line);

    rows.forEach((row, index) => {
      const value = Number(row[key] || 0);
      if (value <= 0) return;
      const dot = svgEl('circle', { cx: x(index), cy: y(value), r: 3.4, fill: chartColors[key] });
      dot.append(svgEl('title'));
      dot.firstChild.textContent = `${chartLabels[key]}: ${value} (${row.date})`;
      svg.append(dot);
    });
  });

  rows.forEach((row, index) => {
    if (index !== 0 && index !== rows.length - 1 && rows.length > 6 && index % Math.ceil(rows.length / 5) !== 0) return;
    const text = svgEl('text', { x: x(index), y: height - 14, 'text-anchor': 'middle', class: 'chart-axis-label' });
    text.textContent = String(row.date).slice(5);
    svg.append(text);
  });

  const yTitle = svgEl('text', {
    x: 18,
    y: pad.top + plotH / 2,
    'text-anchor': 'middle',
    transform: `rotate(-90 18 ${pad.top + plotH / 2})`,
    class: 'chart-axis-title',
  });
  yTitle.textContent = 'Количество событий';
  svg.append(yTitle);

  const xTitle = svgEl('text', {
    x: pad.left + plotW / 2,
    y: height - 34,
    'text-anchor': 'middle',
    class: 'chart-axis-title',
  });
  xTitle.textContent = 'Дата';
  svg.append(xTitle);

  box.innerHTML = '';
  box.append(svg, renderLegend(series.map((key) => ({ label: chartLabels[key], color: chartColors[key] }))));
};

const renderPackBars = (box, rows) => {
  if (!Array.isArray(rows) || !rows.length) {
    emptyChart(box, 'За этот период паки ещё не открывали.');
    return;
  }

  const max = Math.max(1, ...rows.map((row) => Number(row.opens || row.cards || 0)));
  const wrap = document.createElement('div');
  wrap.className = 'chart-bars';
  rows.slice(0, 10).forEach((row) => {
    const value = Number(row.opens || row.cards || 0);
    const item = document.createElement('div');
    item.className = 'chart-bar-row';
    const label = document.createElement('span');
    label.textContent = row.title || row.slug || 'Пак';
    const bar = document.createElement('b');
    bar.style.setProperty('--bar-width', `${Math.max(4, (value / max) * 100)}%`);
    const valueNode = document.createElement('strong');
    valueNode.textContent = `${value} откр. / ${Number(row.cards || 0)} карт`;
    item.append(label, bar, valueNode);
    wrap.append(item);
  });
  box.innerHTML = '';
  box.append(wrap);
};

const renderRarityChart = (box, rows) => {
  if (!Array.isArray(rows) || !rows.some((row) => Number(row.count || 0) > 0)) {
    emptyChart(box, 'Редкости появятся после первых открытий карточек.');
    return;
  }

  const total = rows.reduce((sum, row) => sum + Number(row.count || 0), 0);
  const svg = createSvg(240, 240);
  const cx = 120;
  const cy = 120;
  const radius = 72;
  const circumference = 2 * Math.PI * radius;
  let offset = 0;

  rows.forEach((row) => {
    const count = Number(row.count || 0);
    if (count <= 0) return;
    const part = count / total;
    const circle = svgEl('circle', {
      cx,
      cy,
      r: radius,
      fill: 'none',
      stroke: chartColors[row.rarity] || '#b9a7ff',
      'stroke-width': 28,
      'stroke-dasharray': `${part * circumference} ${circumference}`,
      'stroke-dashoffset': -offset,
      transform: `rotate(-90 ${cx} ${cy})`,
    });
    circle.append(svgEl('title'));
    circle.firstChild.textContent = `${row.label}: ${count}`;
    svg.append(circle);
    offset += part * circumference;
  });

  const center = svgEl('text', { x: cx, y: cy - 3, 'text-anchor': 'middle', class: 'donut-total' });
  center.textContent = String(total);
  const caption = svgEl('text', { x: cx, y: cy + 18, 'text-anchor': 'middle', class: 'chart-axis-label' });
  caption.textContent = 'карточек';
  svg.append(center, caption);

  box.innerHTML = '';
  box.append(svg, renderLegend(rows.map((row) => ({
    label: `${row.label}: ${Number(row.count || 0)}`,
    color: chartColors[row.rarity] || '#b9a7ff',
  }))));
};

const renderFunnelChart = (box, data) => {
  if (!data || typeof data !== 'object') {
    emptyChart(box, 'Воронка появится после первых событий.');
    return;
  }

  const rows = [
    ['Посетители', Number(data.visitors || 0)],
    ['Открыли пак', Number(data.opened_pack || 0)],
    ['Гости пытались сохранить', Number(data.guest_save_attempts || 0)],
    ['Зарегистрировались', Number(data.registrations || 0)],
    ['Сохранили карточку', Number(data.saved_cards || 0)],
  ];
  if (!rows.some(([, value]) => value > 0)) {
    emptyChart(box, 'Воронка появится после первых событий.');
    return;
  }

  const max = Math.max(1, ...rows.map(([, value]) => value));
  const wrap = document.createElement('div');
  wrap.className = 'chart-funnel';
  rows.forEach(([labelText, value]) => {
    const item = document.createElement('div');
    item.className = 'chart-funnel-row';
    const label = document.createElement('span');
    label.textContent = labelText;
    const bar = document.createElement('b');
    bar.style.setProperty('--bar-width', `${Math.max(5, (value / max) * 100)}%`);
    const strong = document.createElement('strong');
    strong.textContent = String(value);
    item.append(label, bar, strong);
    wrap.append(item);
  });
  box.innerHTML = '';
  box.append(wrap);
};

const initCharts = () => {
  qsa('[data-admin-chart]').forEach((box) => {
    const type = box.dataset.adminChart;
    const data = readChartData(box);
    if (type === 'activity') renderActivityChart(box, data);
    if (type === 'packs') renderPackBars(box, data);
    if (type === 'rarities') renderRarityChart(box, data);
    if (type === 'funnel') renderFunnelChart(box, data);
  });
};

const init = () => {
  if (!root()) return;

  qsa('[data-admin-form]').forEach((form) => {
    form.addEventListener('submit', handleAdminForm);
  });

  qs('[data-admin-reorder]')?.addEventListener('submit', handleReorder);
  initCharts();
};

document.addEventListener('DOMContentLoaded', init);
