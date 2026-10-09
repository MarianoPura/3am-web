document.querySelectorAll('.rentals-admin__table-wrap').forEach(wrapper => {
  wrapper.tabIndex = 0;
  wrapper.setAttribute('role', 'region');
  wrapper.setAttribute('aria-label', wrapper.querySelector('caption')?.textContent.trim() || 'Admin records');
});

// Manual blocks are separate from reservations. This calendar never releases an order.
document.querySelectorAll('[data-admin-availability]').forEach((panel) => {
  const form = panel.querySelector('.rentals-admin__blackout-form');
  const start = form.elements.start_date;
  const end = form.elements.end_date;
  const grid = panel.querySelector('[data-admin-days]');
  const message = panel.querySelector('[data-admin-calendar-message]');
  const previous = panel.querySelector('[data-admin-prev]');
  const next = panel.querySelector('[data-admin-next]');
  const monthOf = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
  const today = new Date(); today.setDate(1);
  const first = new Date(today); first.setMonth(first.getMonth() - 12);
  const last = new Date(today); last.setMonth(last.getMonth() + 12);
  let month = monthOf(today);
  let request = 0;
  let anchor = null;
  const updateMode = () => {
    const removing = form.querySelector('[name="availability_action"]:checked')?.value === 'available';
    const note = form.querySelector('[data-admin-block-note]');
    note.hidden = removing;
    note.querySelector('input').disabled = removing;
    form.querySelector('[data-admin-block-submit]').textContent = removing ? 'Remove block' : 'Block dates';
    message.textContent = removing
      ? 'Select the dates to remove manual blocks. Customer reservations remain unchanged.'
      : 'Select the dates to block for maintenance or internal use.';
  };
  form.querySelectorAll('[name="availability_action"]').forEach(mode => mode.addEventListener('change', updateMode));
  updateMode();
  const highlight = () => {
    grid.querySelectorAll('[data-date]').forEach(cell => {
      const selected = Boolean(start.value && end.value && cell.dataset.date >= start.value && cell.dataset.date <= end.value);
      cell.classList.toggle('is-selected', selected);
      cell.setAttribute('aria-pressed', String(selected));
    });
  };
  const load = async () => {
    const sequence = ++request;
    const requestedMonth = month;
    const [year, number] = month.split('-').map(Number);
    panel.querySelector('[data-admin-month]').textContent = new Date(year, number - 1, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    previous.disabled = month <= monthOf(first);
    next.disabled = month >= monthOf(last);
    grid.setAttribute('aria-busy', 'true');
    grid.querySelectorAll('button').forEach(cell => { cell.disabled = true; });
    message.textContent = 'Loading availability…';
    try {
      const url = new URL(panel.dataset.availabilityUrl, location.href);
      url.searchParams.set('month', requestedMonth);
      const response = await fetch(url, { credentials: 'same-origin' });
      const data = await response.json();
      if (sequence !== request) return;
      if (!response.ok || !data.ok) throw new Error(data.message || 'Availability could not be loaded.');
      grid.replaceChildren();
      const offset = (new Date(year, number - 1, 1).getDay() + 6) % 7;
      for (let i = 0; i < offset; i++) grid.append(document.createElement('span'));
      data.days.forEach(day => {
        const cell = document.createElement('button');
        cell.type = 'button'; cell.dataset.date = day.date;
        const state = day.past ? 'past' : (day.admin_blocked ? 'blocked' : (day.reserved > 0 ? 'reserved' : (day.remaining > 0 ? 'available' : 'unavailable')));
        const label = day.past ? 'Past / unavailable' : (day.admin_blocked ? 'Admin blocked' : (day.reserved > 0
          ? (day.remaining > 0 ? `Reserved · ${day.remaining} available` : 'Fully reserved')
          : (day.remaining > 0 ? 'Available' : 'Unavailable')));
        cell.className = `is-${state}`;
        const date = document.createElement('strong'); date.textContent = String(Number(day.date.slice(-2)));
        const status = document.createElement('small'); status.textContent = !day.past && !day.admin_blocked && day.reserved > 0 && day.remaining > 0
          ? `${day.remaining} available` : label;
        cell.append(date, status);
        cell.setAttribute('aria-label', `${day.date}: ${label}; ${day.reserved} reserved, ${day.remaining} remaining${day.admin_blocked ? '; Admin blocked' : ''}. Select dates to manage manual blocks.`);
        cell.title = `${label} · ${day.reserved} reserved · ${day.remaining} remaining${day.admin_blocked ? ' · Admin blocked' : ''}`;
        cell.addEventListener('click', () => {
          if (anchor === null) { anchor = day.date; start.value = day.date; end.value = day.date; }
          else { start.value = anchor < day.date ? anchor : day.date; end.value = anchor > day.date ? anchor : day.date; anchor = null; }
          highlight();
          const removing = form.querySelector('[name="availability_action"]:checked')?.value === 'available';
          message.textContent = `Selected ${start.value} to ${end.value}. ${removing ? 'Remove manual blocks; customer reservations remain unchanged.' : 'Block these dates for maintenance or internal use.'}`;
        });
        grid.append(cell);
      });
      while (grid.children.length < 42) grid.append(document.createElement('span'));
      highlight();
      message.textContent = 'Select one date, or two dates for a range. Removing a manual block never removes customer reservations.';
    } catch (error) {
      if (sequence === request) message.textContent = error.message || 'Availability could not be loaded. Try another month or reopen this panel.';
    } finally {
      if (sequence === request) grid.setAttribute('aria-busy', 'false');
    }
  };
  previous.addEventListener('click', () => {
    if (month <= monthOf(first)) return;
    const [year, number] = month.split('-').map(Number);
    month = monthOf(new Date(year, number - 2, 1)); load();
  });
  next.addEventListener('click', () => {
    if (month >= monthOf(last)) return;
    const [year, number] = month.split('-').map(Number);
    month = monthOf(new Date(year, number, 1)); load();
  });
  [start, end].forEach(input => input.addEventListener('change', () => { anchor = null; highlight(); }));
  panel.querySelector('[data-admin-clear]').addEventListener('click', () => {
    anchor = null; start.value = ''; end.value = ''; highlight(); message.textContent = 'Selection cleared. Existing blocks are unchanged.';
  });
  panel.addEventListener('toggle', () => { if (panel.open) load(); else request++; });
  if (location.hash === '#equipment-availability') panel.open = true;
});
// The live host may accept smaller uploads than the application's 5 MB limit.
document.querySelector('[data-admin-save-error]')?.scrollIntoView({ block: 'center' });
document.querySelectorAll('[data-product-image-limit]').forEach(input => {
  const validate = () => {
    const file = input.files[0];
    const limit = Number(input.dataset.productImageLimit);
    input.setCustomValidity(file && file.size > limit
      ? `Choose an image no larger than ${(limit / (1024 * 1024)).toFixed(2)} MB on this server.` : '');
  };
  input.addEventListener('change', validate);
  validate();
});
document.querySelectorAll('[data-admin-proof-image]').forEach(image => {
  const showError = () => {
    image.hidden = true;
    const message = image.closest('.rentals-admin__proof-detail')?.querySelector('[data-admin-proof-error]');
    if (message) message.hidden = false;
  };
  image.addEventListener('error', showError);
  if (image.complete && image.naturalWidth === 0) showError();
});

// Fit the complete number to its own card, including after a resize or font load.
const fitAdminMetricValue = (value) => {
  value.style.removeProperty('--metric-fit-size');
  const width = value.clientWidth;
  const naturalWidth = value.scrollWidth;
  if (width <= 0 || naturalWidth <= width) return;
  const base = parseFloat(getComputedStyle(value).fontSize);
  value.style.setProperty('--metric-fit-size', `${base * width / naturalWidth}px`);
  if (value.scrollWidth > width) {
    value.style.setProperty('--metric-fit-size', `${parseFloat(getComputedStyle(value).fontSize) * width / value.scrollWidth}px`);
  }
};
document.querySelectorAll('.rentals-admin__metric strong').forEach(value => {
  fitAdminMetricValue(value);
  document.fonts?.ready.then(() => fitAdminMetricValue(value));
  if (typeof ResizeObserver !== 'undefined') {
    let previousWidth;
    new ResizeObserver(entries => {
      const width = entries[0].contentRect.width;
      if (width !== previousWidth) { previousWidth = width; fitAdminMetricValue(value); }
    }).observe(value);
  } else {
    window.addEventListener('resize', () => fitAdminMetricValue(value));
  }
  new MutationObserver(() => fitAdminMetricValue(value)).observe(value, { childList: true, characterData: true, subtree: true });
});

// One handler for Add/Edit; disabled equipment controls never submit stale values.
document.querySelectorAll('[data-rental-product-form]').forEach(form => {
  const type = form.querySelector('[data-rental-type]');
  const category = form.querySelector('[name="category_id"]');
  if (!type || !category) return;
  // Type is only a category filter; the backend derives classification from the category.
  const filterCategories = () => {
    Array.from(category.options).forEach(option => {
      const matches = option.value === '' || option.dataset.isService === type.value;
      option.hidden = !matches;
      option.disabled = !matches;
    });
    if (category.selectedOptions[0]?.disabled) {
      category.value = '';
    }
  };
  const updateType = () => {
    const service = type.value === '1';
    const heading = form.closest?.('.rentals-admin__editor')?.querySelector('[data-create-product-heading]');
    if (heading) heading.textContent = service ? 'Add service' : 'Add equipment';
    form.querySelectorAll('[data-product-label]').forEach(label => {
      label.textContent = service ? label.dataset.serviceText : label.dataset.equipmentText;
    });
    form.querySelectorAll('[data-product-placeholder]').forEach(input => {
      input.placeholder = service ? input.dataset.servicePlaceholder : input.dataset.equipmentPlaceholder;
    });
    form.querySelectorAll('[data-service-field]').forEach(group => { group.hidden = !service; });
    form.querySelectorAll('[data-equipment-field]').forEach(group => {
      group.hidden = service;
      group.querySelectorAll('input, select, textarea').forEach(input => { input.disabled = service; });
    });
  };
  type.addEventListener('change', () => {
    filterCategories();
    updateType();
  });
  category.addEventListener('change', updateType);
  filterCategories();
  updateType();
});

document.querySelectorAll('[data-service-proof-image]').forEach(image => image.addEventListener('error', () => { image.hidden = true; image.parentElement.querySelector('[data-service-proof-error]').hidden = false; }));

// Exact calendar labels are server-rendered; no JS date/timezone conversion.
document.querySelectorAll('[data-rental-time-chart]').forEach(chart => {
  const select = chart.querySelector('[data-chart-period]');
  const output = chart.querySelector('[data-chart-value]');
  if (select && output) select.addEventListener('change', () => {
    output.textContent = select.selectedOptions[0]?.dataset.summary || '';
  });
});
