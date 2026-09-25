const initRentalsCatalogue = () => {
  document.documentElement.dataset.rentalsReady = 'true';
  const searchInput = document.querySelector('[data-rentals-search]');
  const filterButtons = document.querySelectorAll('[data-rentals-filter]');
  const itemCards = document.querySelectorAll('[data-rentals-item]');

  if (!searchInput && filterButtons.length === 0 && itemCards.length === 0) {
    return;
  }

  const applyFilters = () => {
    const term = (searchInput ? searchInput.value.trim().toLowerCase() : '');
    const activeFilter = document.querySelector('[data-rentals-filter].is-active');
    const selected = activeFilter ? activeFilter.dataset.rentalsFilter : 'all';

    let visible = 0;
    itemCards.forEach((card) => {
      const text = (card.textContent || '').toLowerCase();
      const category = (card.dataset.category || '');
      const matchTerm = term === '' || text.includes(term);
      const matchCategory = selected === 'all' || category === selected;
      card.hidden = !(matchTerm && matchCategory);
      if (!card.hidden) visible++;
    });
    const status = document.querySelector('[data-rentals-results]');
    if (status) status.textContent = visible ? `${visible} item${visible === 1 ? '' : 's'} found` : 'No matching equipment. Try another search or category.';
  };

  if (searchInput) {
    searchInput.addEventListener('input', applyFilters);
  }

  filterButtons.forEach((button) => {
    button.addEventListener('click', () => {
      filterButtons.forEach((el) => { el.classList.toggle('is-active', el === button); el.setAttribute('aria-pressed', String(el === button)); });
      applyFilters();
    });
  });
  const requested = new URLSearchParams(window.location.search).get('category');
  const chosen = [...filterButtons].find(button => button.dataset.rentalsFilter === requested);
  filterButtons.forEach(button => {
    if (chosen) button.classList.toggle('is-active', button === chosen);
    button.setAttribute('aria-pressed', String(button.classList.contains('is-active')));
  });
  applyFilters();

  const dialog = document.querySelector('[data-rentals-dialog]');
  const detailButtons = document.querySelectorAll('[data-rentals-detail]');
  if (!dialog || typeof dialog.showModal !== 'function') return;

  const setText = (selector, value) => {
    const target = dialog.querySelector(selector);
    if (target) target.textContent = value || '—';
  };
  let opener = null;
  const form = dialog.querySelector('[data-detail-form]');
  const startInput = form.elements.rental_start_date;
  const endInput = form.elements.rental_end_date;
  const quantityInput = form.elements.quantity;
  const addButton = dialog.querySelector('[data-detail-add]');
  const message = dialog.querySelector('[data-detail-message]');
  const calendar = dialog.querySelector('[data-availability-calendar]');
  const dateTrigger = dialog.querySelector('[data-date-trigger]');
  const content = dialog.querySelector('[data-detail-content]');
  const success = dialog.querySelector('[data-detail-success]');
  let currentDetail = null;
  // Build the month from local date components; locale formats differ between browsers.
  const monthOf = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
  let calendarMonth = monthOf(new Date());
  let calendarRequest = 0;
  const monthCache = new Map();
  const displayDate = (value) => new Date(`${value}T12:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  const updateDateLabel = () => {
    dateTrigger.textContent = startInput.value
      ? `${displayDate(startInput.value)}${endInput.value ? ` – ${displayDate(endInput.value)}` : ' – choose end date'}`
      : 'Select rental dates';
  };
  const getMonth = async (month) => {
    const key = `${currentDetail.id}:${quantityInput.value}:${month}`;
    if (monthCache.has(key)) return monthCache.get(key);
    const url = new URL(form.dataset.availabilityUrl, window.location.href);
    url.searchParams.set('id', currentDetail.id);
    url.searchParams.set('month', month);
    url.searchParams.set('quantity', quantityInput.value || '1');
    const response = await fetch(url, { credentials: 'same-origin' });
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.message || 'Availability could not be loaded.');
    monthCache.set(key, data.days);
    return data.days;
  };
  const rangeAvailable = async (start, end) => {
    let month = start.slice(0, 7);
    const lookup = new Map();
    while (month <= end.slice(0, 7)) {
      (await getMonth(month)).forEach((day) => lookup.set(day.date, day.available));
      const [year, number] = month.split('-').map(Number);
      month = monthOf(new Date(year, number, 1));
    }
    for (let date = new Date(`${start}T12:00:00`); date <= new Date(`${end}T12:00:00`); date.setDate(date.getDate() + 1)) {
      const value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
      if (!lookup.get(value)) return false;
    }
    return true;
  };
  const refreshCalendar = async () => {
    if (!currentDetail || !currentDetail.canRent) return;
    const thisRequest = ++calendarRequest;
    const days = calendar.querySelector('[data-calendar-days]');
    days.textContent = 'Loading availability…';
    try {
      const dates = await getMonth(calendarMonth);
      if (thisRequest !== calendarRequest) return;
      days.replaceChildren();
      const [year, month] = calendarMonth.split('-').map(Number);
      calendar.querySelector('[data-month-label]').textContent = new Date(year, month - 1, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
      const offset = (new Date(year, month - 1, 1).getDay() + 6) % 7;
      for (let i = 0; i < offset; i++) days.append(document.createElement('span'));
      dates.forEach((day) => {
        const cell = document.createElement('button');
        cell.type = 'button';
        cell.textContent = String(Number(day.date.slice(-2)));
        cell.dataset.date = day.date;
        cell.className = day.available ? 'is-available' : 'is-unavailable';
        cell.disabled = !day.available;
        cell.setAttribute('aria-label', `${day.date}: ${day.available ? `${day.remaining} available` : 'unavailable'}`);
        if (day.date === startInput.value || day.date === endInput.value) cell.classList.add('is-selected');
        if (startInput.value && endInput.value && day.date > startInput.value && day.date < endInput.value) cell.classList.add('is-in-range');
        cell.addEventListener('click', async () => {
          if (!startInput.value || endInput.value || day.date < startInput.value) {
            startInput.value = day.date;
            endInput.value = '';
          } else {
            cell.disabled = true;
            try {
              if (!(await rangeAvailable(startInput.value, day.date))) {
                message.textContent = 'That range contains an unavailable date. Choose another end date.';
                cell.disabled = false;
                return;
              }
            } catch (error) {
              message.textContent = error.message || 'Availability could not be loaded.';
              cell.disabled = false;
              return;
            }
            endInput.value = day.date;
            calendar.hidden = true;
            dateTrigger.setAttribute('aria-expanded', 'false');
            dateTrigger.focus();
          }
          updateDateLabel();
          message.textContent = endInput.value ? 'Dates selected. Add to Cart when ready.' : 'Choose an end date.';
          refreshCalendar();
        });
        days.append(cell);
      });
      const first = monthOf(new Date());
      const lastDate = new Date(); lastDate.setMonth(lastDate.getMonth() + 12);
      calendar.querySelector('[data-month-prev]').disabled = calendarMonth <= first;
      calendar.querySelector('[data-month-next]').disabled = calendarMonth >= monthOf(lastDate);
    } catch (error) {
      if (thisRequest === calendarRequest) days.textContent = error.message || 'Availability could not be loaded. Please try again.';
    }
  };
  calendar.querySelector('[data-month-prev]').addEventListener('click', () => {
    const [year, month] = calendarMonth.split('-').map(Number);
    calendarMonth = monthOf(new Date(year, month - 2, 1)); refreshCalendar();
  });
  calendar.querySelector('[data-month-next]').addEventListener('click', () => {
    const [year, month] = calendarMonth.split('-').map(Number);
    calendarMonth = monthOf(new Date(year, month, 1)); refreshCalendar();
  });
  dateTrigger.addEventListener('click', () => {
    calendar.hidden = !calendar.hidden;
    dateTrigger.setAttribute('aria-expanded', String(!calendar.hidden));
    if (!calendar.hidden) { refreshCalendar(); calendar.querySelector('[data-month-prev]').focus(); }
  });
  dialog.querySelector('[data-date-clear]').addEventListener('click', () => {
    startInput.value = ''; endInput.value = ''; updateDateLabel(); refreshCalendar();
    message.textContent = 'Select available rental dates.';
  });
  quantityInput.addEventListener('change', () => {
    startInput.value = ''; endInput.value = ''; updateDateLabel(); monthCache.clear();
    message.textContent = 'Quantity changed. Select rental dates again.';
    if (!calendar.hidden) refreshCalendar();
  });
  detailButtons.forEach((button) => button.addEventListener('click', () => {
    let detail;
    try { detail = JSON.parse(button.dataset.rentalsDetail || '{}'); } catch { return; }
    opener = button;
    currentDetail = detail;
    form.reset();
    monthCache.clear();
    content.hidden = false;
    success.hidden = true;
    calendar.hidden = true;
    dateTrigger.setAttribute('aria-expanded', 'false');
    updateDateLabel();
    calendarMonth = monthOf(new Date());
    setText('[data-detail-category]', detail.category);
    setText('[data-detail-name]', detail.name);
    setText('[data-detail-description]', detail.description);
    setText('[data-detail-ideal]', detail.ideal);
    setText('[data-detail-rate]', detail.rate);
    setText('[data-detail-deposit]', detail.deposit);
    setText('[data-detail-status]', detail.status);
    const picture = dialog.querySelector('[data-detail-image]');
    picture.hidden = !detail.image;
    if (detail.image) { picture.src = detail.image; picture.alt = detail.name || ''; }
    else { picture.removeAttribute('src'); }
    dialog.querySelector('[data-detail-id]').value = detail.id || '';
    addButton.disabled = !detail.canRent;
    addButton.textContent = detail.canRent ? 'Add to Cart' : (detail.isSample ? 'Preview only' : 'Currently unavailable');
    message.textContent = 'Select available rental dates.';
    dialog.showModal();
  }));
  dialog.querySelector('[data-detail-continue]').addEventListener('click', () => dialog.close());
  dialog.querySelector('[data-rentals-close]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
  dialog.addEventListener('close', () => { if (opener) opener.focus(); });
  form.addEventListener('submit', async (event) => {
    if (!window.fetch) return;
    event.preventDefault();
    const form = event.currentTarget;
    if (!startInput.value || !endInput.value) {
      message.textContent = 'Select a start and end date first.';
      calendar.hidden = false;
      dateTrigger.setAttribute('aria-expanded', 'true');
      refreshCalendar();
      return;
    }
    addButton.disabled = true;
    try {
      const response = await fetch(form.action, {
        method: 'POST', body: new FormData(form), credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const result = await response.json().catch(() => ({ ok: false, message: 'Could not complete the request. Please refresh and try again.' }));
      message.textContent = result.message || 'Unable to add this item.';
      if (!response.ok || !result.ok) { addButton.disabled = false; return; }
      const count = document.querySelector('[data-rentals-cart-count]');
      if (count) { count.textContent = String(result.count); count.hidden = false; }
      content.hidden = true;
      success.hidden = false;
      dialog.querySelector('[data-detail-continue]').focus();
    } catch {
      message.textContent = 'Connection interrupted. Try again or open Cart.';
      addButton.disabled = false;
    }
  });
};

const initRentalCart = () => {
  document.querySelectorAll('.rentals-cart-form--update').forEach((form) => {
    const start = form.elements.rental_start_date;
    const end = form.elements.rental_end_date;
    const save = () => {
      if (!form.checkValidity() || start.value > end.value) return;
      form.querySelectorAll('input:not([type="hidden"])').forEach((input) => { input.readOnly = true; });
      form.requestSubmit();
    };
    start.addEventListener('change', () => {
      end.min = start.value;
      if (end.value && end.value < start.value) end.value = '';
      save();
    });
    end.addEventListener('change', save);
    form.elements.quantity.addEventListener('change', save);
  });
};

const initRentalCheckout = () => {
  const form = document.querySelector('[data-rental-checkout]');
  if (!form) return;
  const qrDialog = document.querySelector('[data-payment-qr-dialog]');
  let qrOpener = null;
  document.querySelectorAll('[data-payment-qr-open]').forEach((button) => button.addEventListener('click', () => {
    if (!qrDialog || typeof qrDialog.showModal !== 'function') return;
    qrOpener = button;
    qrDialog.querySelector('[data-payment-qr-title]').textContent = `${button.dataset.qrName} payment QR`;
    const image = qrDialog.querySelector('[data-payment-qr-image]');
    image.src = button.dataset.qrSrc;
    image.alt = `${button.dataset.qrName} payment QR code`;
    qrDialog.querySelector('[data-payment-qr-link]').href = button.dataset.qrSrc;
    qrDialog.showModal();
    qrDialog.querySelector('[data-payment-qr-close]').focus();
  }));
  qrDialog?.querySelector('[data-payment-qr-close]')?.addEventListener('click', () => qrDialog.close());
  qrDialog?.addEventListener('click', (event) => { if (event.target === qrDialog) qrDialog.close(); });
  qrDialog?.addEventListener('close', () => qrOpener?.focus());
  const select = form.elements.payment_method_id;
  const panels = form.querySelectorAll('[data-payment-instructions]');
  const update = () => panels.forEach((panel) => { panel.hidden = panel.dataset.paymentInstructions !== select.value; });
  select?.addEventListener('change', update);
  update();
  form.addEventListener('submit', (event) => {
    if (!form.checkValidity()) return;
    const submit = form.querySelector('[type="submit"]');
    if (submit.dataset.submitting === 'true') { event.preventDefault(); return; }
    submit.dataset.submitting = 'true';
    submit.textContent = 'Submitting…';
    // Keep the button enabled so its value and the native form submission remain intact.
  });
};

const initRentals = () => { initRentalsCatalogue(); initRentalCart(); initRentalCheckout(); };

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initRentals, { once: true });
} else {
  initRentals();
}
