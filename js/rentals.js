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

    itemCards.forEach((card) => {
      const text = (card.textContent || '').toLowerCase();
      const category = (card.dataset.category || '');
      const matchTerm = term === '' || text.includes(term);
      const matchCategory = selected === 'all' || category === selected;
      card.hidden = !(matchTerm && matchCategory);
    });
  };

  if (searchInput) {
    searchInput.addEventListener('input', applyFilters);
  }

  filterButtons.forEach((button) => {
    button.addEventListener('click', () => {
      filterButtons.forEach((el) => el.classList.toggle('is-active', el === button));
      applyFilters();
    });
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initRentalsCatalogue, { once: true });
} else {
  initRentalsCatalogue();
}
