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
        const state = day.admin_blocked ? 'blocked' : (day.reserved > 0 ? 'reserved' : (day.remaining > 0 ? 'available' : 'unavailable'));
        const label = day.admin_blocked ? 'Admin blocked' : (day.reserved > 0 ? 'Customer reserved' : (day.remaining > 0 ? 'Available' : 'Unavailable'));
        cell.className = `is-${state}`;
        const date = document.createElement('strong'); date.textContent = String(Number(day.date.slice(-2)));
        const status = document.createElement('small'); status.textContent = label;
        cell.append(date, status);
        cell.setAttribute('aria-label', `${day.date}: ${label}; ${day.reserved} reserved, ${day.remaining} remaining. Select manual block dates.`);
        cell.title = `${label} · ${day.reserved} reserved · ${day.remaining} remaining`;
        cell.addEventListener('click', () => {
          if (anchor === null) { anchor = day.date; start.value = day.date; end.value = day.date; }
          else { start.value = anchor < day.date ? anchor : day.date; end.value = anchor > day.date ? anchor : day.date; anchor = null; }
          highlight();
          message.textContent = `Selected ${start.value} to ${end.value}. Add a note and use Block dates to save.`;
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
