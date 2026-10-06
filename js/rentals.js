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
    document.querySelectorAll('[data-rentals-category-group]').forEach((group) => {
      group.hidden = !group.querySelector('[data-rentals-item]:not([hidden])');
    });
    document.querySelectorAll('[data-rentals-carousel]').forEach((carousel) => {
      carousel.dispatchEvent(new Event('rentals:refresh'));
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
  let selectionRequest = 0;
  let quantityRequest = 0;
  const displayDate = (value) => new Date(`${value}T12:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  const updateDateLabel = () => {
    dateTrigger.textContent = startInput.value
      ? `${displayDate(startInput.value)}${endInput.value ? ` – ${displayDate(endInput.value)}` : ' – choose end date'}`
      : 'Select rental dates';
  };
  const getMonth = async (month, detail = currentDetail, quantity = quantityInput.value || '1') => {
    const url = new URL(form.dataset.availabilityUrl, window.location.href);
    url.searchParams.set('id', detail.id);
    url.searchParams.set('month', month);
    url.searchParams.set('quantity', quantity);
    const response = await fetch(url, { credentials: 'same-origin' });
    let data;
    try {
      data = JSON.parse(await response.text());
    } catch {
      throw new Error(`Availability request returned an invalid response (HTTP ${response.status}). Check the server route and error log.`);
    }
    if (!response.ok || !data.ok) throw new Error(data.message || 'Availability could not be loaded.');
    return data.days;
  };
  const rangeAvailable = async (start, end) => {
    let month = start.slice(0, 7);
    const detail = currentDetail;
    const quantity = quantityInput.value || '1';
    const lookup = new Map();
    while (month <= end.slice(0, 7)) {
      (await getMonth(month, detail, quantity)).forEach((day) => lookup.set(day.date, day.available && !(day.reserved > 0)));
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
    const requestedMonth = calendarMonth;
    const [year, month] = requestedMonth.split('-').map(Number);
    calendar.querySelector('[data-month-label]').textContent = new Date(year, month - 1, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    const first = monthOf(new Date());
    const lastDate = new Date(); lastDate.setDate(1); lastDate.setMonth(lastDate.getMonth() + 12);
    calendar.querySelector('[data-month-prev]').disabled = requestedMonth <= first;
    calendar.querySelector('[data-month-next]').disabled = requestedMonth >= monthOf(lastDate);
    days.setAttribute('aria-busy', 'true');
    days.querySelectorAll('button').forEach((cell) => { cell.disabled = true; });
    if (!days.children.length) days.textContent = 'Loading dates…';
    try {
      const dates = await getMonth(requestedMonth);
      if (thisRequest !== calendarRequest) return;
      days.replaceChildren();
      const offset = (new Date(year, month - 1, 1).getDay() + 6) % 7;
      for (let i = 0; i < offset; i++) days.append(document.createElement('span'));
      dates.forEach((day) => {
        const cell = document.createElement('button');
        cell.type = 'button';
        cell.textContent = String(Number(day.date.slice(-2)));
        cell.dataset.date = day.date;
        const state = day.admin_blocked ? 'blocked' : (day.past ? 'past' : (day.reserved > 0 ? 'reserved' : (day.available ? 'available' : 'unavailable')));
        const label = day.admin_blocked ? 'Admin blocked' : (day.past ? 'Past date' : (day.reserved > 0 ? 'Customer reserved' : (day.available ? `${day.remaining} available` : 'Unavailable')));
        cell.className = `is-${state}`;
        cell.disabled = !day.available || day.reserved > 0;
        cell.setAttribute('aria-label', `${day.date}: ${label}`);
        cell.title = day.reserved > 0 ? label : `${label} · ${day.remaining} remaining`;
        if (day.date === startInput.value || day.date === endInput.value) cell.classList.add('is-selected');
        if (startInput.value && endInput.value && day.date > startInput.value && day.date < endInput.value) cell.classList.add('is-in-range');
        cell.addEventListener('click', async () => {
          const thisSelection = ++selectionRequest;
          if (!startInput.value || endInput.value || day.date < startInput.value) {
            startInput.value = day.date;
            endInput.value = '';
          } else {
            cell.disabled = true;
            try {
              const available = await rangeAvailable(startInput.value, day.date);
              if (thisSelection !== selectionRequest || !dialog.open) return;
              if (!available) {
                message.textContent = 'That range contains an unavailable date. Choose another end date.';
                cell.disabled = false;
                return;
              }
            } catch (error) {
              if (thisSelection !== selectionRequest || !dialog.open) return;
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
      while (days.children.length < 42) days.append(document.createElement('span'));
    } catch (error) {
      if (thisRequest === calendarRequest) days.textContent = error.message || 'Availability could not be loaded. Please try again.';
    } finally {
      if (thisRequest === calendarRequest) days.setAttribute('aria-busy', 'false');
    }
  };
  calendar.querySelector('[data-month-prev]').addEventListener('click', () => {
    if (calendarMonth <= monthOf(new Date())) return;
    selectionRequest++;
    const [year, month] = calendarMonth.split('-').map(Number);
    calendarMonth = monthOf(new Date(year, month - 2, 1)); refreshCalendar();
  });
  calendar.querySelector('[data-month-next]').addEventListener('click', () => {
    const limit = new Date(); limit.setDate(1); limit.setMonth(limit.getMonth() + 12);
    if (calendarMonth >= monthOf(limit)) return;
    selectionRequest++;
    const [year, month] = calendarMonth.split('-').map(Number);
    calendarMonth = monthOf(new Date(year, month, 1)); refreshCalendar();
  });
  dateTrigger.addEventListener('click', () => {
    calendar.hidden = !calendar.hidden;
    dateTrigger.setAttribute('aria-expanded', String(!calendar.hidden));
    if (!calendar.hidden) {
      calendarMonth = startInput.value ? startInput.value.slice(0, 7) : (calendarMonth < monthOf(new Date()) ? monthOf(new Date()) : calendarMonth);
      refreshCalendar();
      calendar.querySelector('[data-month-next]').focus();
    }
  });
  dialog.querySelector('[data-date-clear]').addEventListener('click', () => {
    selectionRequest++;
    startInput.value = ''; endInput.value = ''; updateDateLabel(); refreshCalendar();
    message.textContent = 'Select available rental dates.';
  });
  quantityInput.addEventListener('input', async () => {
    const thisQuantity = ++quantityRequest;
    selectionRequest++;
    calendarRequest++;
    const start = startInput.value; const end = endInput.value;
    if (!quantityInput.checkValidity()) {
      addButton.disabled = true;
      calendar.querySelectorAll('[data-calendar-days] button').forEach(cell => { cell.disabled = true; });
      message.textContent = 'Enter a valid quantity.';
      return;
    }
    addButton.disabled = true;
    message.textContent = 'Checking availability for the selected quantity…';
    if (!calendar.hidden) refreshCalendar();
    try {
      const valid = start ? await rangeAvailable(start, end || start) : false;
      if (thisQuantity !== quantityRequest || !dialog.open) return;
      if (startInput.value !== start || endInput.value !== end) return;
      if (start && !valid) {
        startInput.value = ''; endInput.value = ''; updateDateLabel();
        message.textContent = 'These dates are no longer available for the selected quantity. Please choose new dates.';
        if (!calendar.hidden) refreshCalendar();
      } else {
        message.textContent = valid ? 'Dates remain available for the selected quantity.' : 'Select available rental dates.';
      }
    } catch (error) {
      if (thisQuantity !== quantityRequest || !dialog.open) return;
      if (startInput.value !== start || endInput.value !== end) return;
      startInput.value = ''; endInput.value = ''; updateDateLabel();
      message.textContent = error.message || 'Availability could not be checked. Please choose dates again.';
    } finally {
      if (thisQuantity === quantityRequest && dialog.open) addButton.disabled = !currentDetail.canRent;
    }
  });
  detailButtons.forEach((button) => button.addEventListener('click', () => {
    let detail;
    try { detail = JSON.parse(button.dataset.rentalsDetail || '{}'); } catch { return; }
    opener = button;
    currentDetail = detail;
    calendarRequest++;
    selectionRequest++;
    form.reset();
    quantityRequest++;
    content.hidden = false;
    success.hidden = true;
    calendar.hidden = true;
    dateTrigger.setAttribute('aria-expanded', 'false');
    updateDateLabel();
    calendarMonth = monthOf(new Date());
    setText('[data-detail-category]', detail.category);
    setText('[data-detail-name]', detail.name);
    setText('[data-detail-quantity]', detail.isSample ? 'Not confirmed' : `${detail.available} unit${detail.available === 1 ? '' : 's'}`);
    setText('[data-detail-description]', detail.description);
    setText('[data-detail-ideal]', detail.ideal);
    setText('[data-detail-rate]', detail.rate);
    setText('[data-detail-deposit]', detail.deposit);
    setText('[data-detail-status]', detail.status);
    const picture = dialog.querySelector('[data-detail-image]');
    picture.src = detail.image || picture.dataset.rentalsFallback;
    const imageUnavailable = Boolean(detail.hasImageReference && !detail.image);
    picture.alt = detail.image ? (detail.name || 'Rental equipment') : (imageUnavailable ? 'Equipment image temporarily unavailable' : '3AM Rentals equipment image coming soon');
    dialog.querySelector('[data-rentals-image-status]').hidden = !imageUnavailable;
    dialog.querySelector('[data-detail-id]').value = detail.id || '';
    addButton.disabled = !detail.canRent;
    addButton.textContent = detail.canRent ? 'Add to Cart' : (detail.isSample ? 'Preview only' : 'Currently unavailable');
    message.textContent = 'Select available rental dates.';
    dialog.showModal();
    if (detail.canRent) {
      calendar.hidden = false;
      dateTrigger.setAttribute('aria-expanded', 'true');
      refreshCalendar();
    }
  }));
  dialog.querySelector('[data-detail-continue]').addEventListener('click', () => dialog.close());
  dialog.querySelector('[data-rentals-close]').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
  dialog.addEventListener('close', () => { calendarRequest++; selectionRequest++; quantityRequest++; if (opener) opener.focus(); });
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
  form.querySelectorAll('[data-payment-qr-inline]').forEach((image) => image.addEventListener('error', () => {
    image.hidden = true;
    const figure = image.closest('[data-payment-qr-for]');
    const caption = figure?.querySelector('figcaption');
    if (caption) caption.hidden = true;
    const error = figure?.querySelector('[data-payment-qr-error]');
    if (error) error.hidden = false;
    const button = figure?.closest('[data-payment-instructions]')?.querySelector('[data-payment-qr-open]');
    if (button) button.hidden = true;
  }));
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
  const update = () => panels.forEach((panel) => { panel.hidden = panel.dataset.paymentInstructions !== (select?.value ?? ''); });
  select?.addEventListener('change', update);
  update();
  const submit = form.querySelector('[type="submit"]');
  const submitLabel = submit?.querySelector('[data-rental-submit-label]');
  const spinner = submit?.querySelector('[data-rental-submit-spinner]');
  if (!submit || !submitLabel || !spinner) return;
  const originalLabel = submitLabel.textContent;
  const initiallyDisabled = submit.disabled;
  const restoreSubmit = () => {
    delete submit.dataset.submitting;
    submit.disabled = initiallyDisabled;
    submitLabel.textContent = originalLabel;
    spinner.hidden = true;
    form.removeAttribute('aria-busy');
    submit.removeAttribute('aria-busy');
  };
  // Native POST failures render a fresh form with the original label and error.
  // Also recover the button when the browser restores this page from history.
  window.addEventListener('pageshow', (event) => { if (event.persisted) restoreSubmit(); });
  form.addEventListener('submit', (event) => {
    if (submit.dataset.submitting === 'true') { event.preventDefault(); return; }
    if (event.defaultPrevented || initiallyDisabled || !form.checkValidity()) return;
    submit.dataset.submitting = 'true';
    submit.disabled = true;
    spinner.hidden = false;
    submitLabel.textContent = 'SUBMITTING...';
    form.setAttribute('aria-busy', 'true');
    submit.setAttribute('aria-busy', 'true');
    // The button has no submitted name/value. Keep the existing native POST flow.
  });
};

const initRentalHeader = () => {
  const navToggle = document.querySelector('[data-rentals-nav-toggle]');
  const nav = document.querySelector('#rentals-primary-nav');
  const closeNav = (restoreFocus = false) => {
    if (!navToggle || !nav) return;
    nav.classList.remove('is-open');
    navToggle.setAttribute('aria-expanded', 'false');
    navToggle.setAttribute('aria-label', 'Open Rentals menu');
    if (restoreFocus) navToggle.focus();
  };
  if (navToggle && nav) {
    navToggle.addEventListener('click', () => {
      const open = nav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', String(open));
      navToggle.setAttribute('aria-label', open ? 'Close Rentals menu' : 'Open Rentals menu');
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && nav.classList.contains('is-open')) { event.preventDefault(); closeNav(true); }
    });
    document.addEventListener('click', event => {
      if (!nav.contains(event.target) && !navToggle.contains(event.target)) closeNav();
    });
    window.addEventListener('resize', () => { if (innerWidth > 780) closeNav(); });
  }
  const menu = document.querySelector('[data-account-menu]');
  if (!menu) return;
  const trigger = menu.querySelector('summary');
  let openedByHover = false;
  const close = (restoreFocus = false) => { openedByHover = false; menu.open = false; if (restoreFocus) trigger.focus(); };
  menu.addEventListener('toggle', () => trigger.setAttribute('aria-expanded', String(menu.open)));
  menu.addEventListener('mouseenter', () => {
    if (matchMedia('(hover: hover) and (pointer: fine)').matches && !menu.open) { openedByHover = true; menu.open = true; }
  });
  trigger.addEventListener('click', event => {
    // Keep a hover-opened panel open on the first click; later clicks toggle normally.
    if (openedByHover && event.detail > 0) { event.preventDefault(); menu.open = true; }
    openedByHover = false;
  });
  menu.addEventListener('mouseleave', () => { if (!menu.contains(document.activeElement)) close(); });
  menu.addEventListener('focusout', event => { if (event.relatedTarget && !menu.contains(event.relatedTarget)) close(); });
  document.addEventListener('click', event => { if (!menu.contains(event.target)) close(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && menu.open) { event.preventDefault(); close(true); } });
  const header = menu.closest('header');
  const updateHeight = () => document.body.style.setProperty('--rentals-header-height', `${header.getBoundingClientRect().height}px`);
  if ('ResizeObserver' in window) new ResizeObserver(updateHeight).observe(header);
  else window.addEventListener('resize', updateHeight);
  updateHeight();
};

const initRentalImages = () => {
  document.querySelectorAll('[data-rentals-image]').forEach((image) => image.addEventListener('error', () => {
    const fallbackUrl = new URL(image.dataset.rentalsFallback, document.baseURI).href;
    if (image.src !== fallbackUrl) {
      image.src = fallbackUrl;
      image.alt = 'Equipment image temporarily unavailable';
      const status = image.parentElement.querySelector('[data-rentals-image-status]');
      if (status) status.hidden = false;
    }
  }));
};

const initRentalCarousels = () => {
  document.querySelectorAll('[data-rentals-carousel]').forEach(carousel => {
    const viewport = carousel.querySelector('[data-rentals-scroll]');
    const previous = carousel.querySelector('[data-carousel-prev]');
    const next = carousel.querySelector('[data-carousel-next]');
    if (!viewport || !previous || !next) return;
    const refresh = () => {
      previous.disabled = viewport.scrollLeft <= 2;
      next.disabled = viewport.scrollLeft + viewport.clientWidth >= viewport.scrollWidth - 2;
    };
    const advance = direction => viewport.scrollBy({ left: direction * viewport.clientWidth * .8, behavior: 'smooth' });
    previous.addEventListener('click', () => advance(-1));
    next.addEventListener('click', () => advance(1));
    viewport.addEventListener('scroll', refresh, { passive: true });
    viewport.addEventListener('keydown', event => {
      if (event.target !== viewport) return;
      if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
        event.preventDefault(); advance(event.key === 'ArrowRight' ? 1 : -1);
      }
    });
    carousel.addEventListener('rentals:refresh', () => { viewport.scrollLeft = 0; requestAnimationFrame(refresh); });
    if ('ResizeObserver' in window) new ResizeObserver(refresh).observe(viewport);
    else window.addEventListener('resize', refresh);
    refresh();
  });
};

const initRentals = () => { initRentalImages(); initRentalHeader(); initRentalCarousels(); initRentalsCatalogue(); initRentalCart(); initRentalCheckout(); };

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initRentals, { once: true });
} else {
  initRentals();
}
