/**
 * 3AM — motion layer
 * ==================
 *
 * GOVERNING RULE
 * Every animation here is an enhancement to content that is already visible.
 * Nothing starts at opacity:0 in CSS. If GSAP fails to load — blocked CDN,
 * CSP mistake, offline — the page is fully readable, just without transitions.
 *
 * That is why reveals work by ADDING a class rather than by animating out of a
 * hidden state. The common alternative (hide in CSS, reveal with JS) turns any
 * script failure into a blank page, which is the single most common way a
 * motion-heavy site breaks in production.
 *
 * Motion respects the OS prefers-reduced-motion setting. The footer toggle
 * that used to override it was removed along with the footer status line; the
 * data-motion="reduced" hook is kept in the CSS so it can be reinstated
 * without touching the stylesheet.
 */

(() => {
  'use strict';

  const root = document.documentElement;
  const gsap = window.gsap;
  const ScrollTrigger = window.ScrollTrigger;
  const hasGsap = Boolean(gsap && ScrollTrigger);

  if (hasGsap) gsap.registerPlugin(ScrollTrigger);

  // ── Motion preference ──────────────────────────────────────
  const STORAGE_KEY = '3am:reduce-motion';

  const systemReduced = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const storedPreference = () => {
    // localStorage throws outright in some privacy modes — a preference
    // lookup must never be the thing that breaks the page.
    try { return localStorage.getItem(STORAGE_KEY); } catch { return null; }
  };

  let reduced = storedPreference() === '1' || (storedPreference() === null && systemReduced());

  const applyMotionPreference = () => {
    root.dataset.motion = reduced ? 'reduced' : 'full';
  };

  applyMotionPreference();

  // ── Reveal on scroll ───────────────────────────────────────
  //
  // once:true throughout. Replaying a reveal when the reader scrolls back up
  // is the fastest way to make a site tiring on the second read.
  const setupReveals = () => {
    const targets = document.querySelectorAll('[data-reveal]');
    const lines = document.querySelectorAll('[data-reveal-line]');

    if (reduced || !hasGsap) {
      // Reduced motion, or no GSAP: show everything immediately.
      targets.forEach((el) => el.classList.add('is-revealed'));
      lines.forEach((el) => el.classList.add('is-revealed'));
      return;
    }

    targets.forEach((el) => {
      gsap.fromTo(
        el,
        { y: 40, opacity: 0 },
        {
          y: 0,
          opacity: 1,
          duration: 0.7,
          ease: 'power3.out',
          scrollTrigger: { trigger: el, start: 'top 88%', once: true },
          onStart: () => el.classList.add('is-revealed'),
        }
      );
    });

    // Hero headline: each line masked and lifted, staggered.
    // The inner <span> moves inside a clipped parent, so the type appears to
    // rise out of the line above rather than simply fading in.
    lines.forEach((line, i) => {
      const inner = line.firstElementChild;
      line.classList.add('is-revealed');

      gsap.fromTo(
        inner,
        { yPercent: 110 },
        {
          yPercent: 0,
          duration: 1.1,
          ease: 'expo.out',
          delay: 0.15 + i * 0.09,
        }
      );
    });
  };

  // ── Number counters: REMOVED, deliberately ─────────────────
  //
  // There was a setupCounters() here driving "0 → 5 Years operating" and
  // similar. It shipped a page reading "0 Years operating / 0 Channels /
  // 0 Capabilities" to a live audience, because the markup carried 0 as its
  // resting state and depended on this function to make it true.
  //
  // The lesson is not "fix the animation". It is that a factual claim must
  // never depend on script execution to be correct. Those figures are now
  // stated in words in the Track Record section, server-rendered.
  //
  // If a counter is ever wanted again: server-render the REAL number as the
  // element's text, and have JS animate from 0 up to it. Then a script
  // failure costs the animation, not the truth.

  // ── Hero ───────────────────────────────────────────────────
  const setupHero = () => {
    const hero = document.querySelector('[data-hero]');
    if (!hero || reduced || !hasGsap) return;

    // Gentle parallax lift as the hero leaves. Scrubbed, so it tracks the
    // scroll position exactly rather than running on its own clock.
    gsap.to(hero.querySelector('.shell'), {
      y: -60,
      ease: 'none',
      scrollTrigger: {
        trigger: hero,
        start: 'top top',
        end: 'bottom top',
        scrub: 0.5,
      },
    });
  };

  // ── Navigation ─────────────────────────────────────────────
  const setupNav = () => {
    const nav = document.querySelector('[data-nav]');
    const toggle = document.querySelector('[data-nav-toggle]');
    const panel = document.querySelector('[data-nav-panel]');

    if (nav) {
      // Use the actual fixed header height for both clicks and direct hashes.
      const syncHeader = () => root.style.setProperty('--header-offset', `${nav.getBoundingClientRect().height}px`);
      new ResizeObserver(syncHeader).observe(nav);
      syncHeader();
      const onScroll = () => {
        nav.classList.toggle('is-condensed', window.scrollY > 80);
      };
      onScroll();
      window.addEventListener('scroll', onScroll, { passive: true });
    }

    if (!toggle || !panel) return;

    let lockedScroll = 0;
    const setOpen = (open) => {
      if (open === !panel.hidden) return;
      if (open) {
        lockedScroll = window.scrollY;
        document.body.style.position = 'fixed';
        document.body.style.top = `-${lockedScroll}px`;
        document.body.style.width = '100%';
      } else {
        document.body.style.removeProperty('position');
        document.body.style.removeProperty('top');
        document.body.style.removeProperty('width');
      }
      toggle.setAttribute('aria-expanded', String(open));
      toggle.querySelector('.sr-only').textContent = open ? 'Close menu' : 'Menu';
      panel.hidden = !open;
      root.classList.toggle('is-nav-open', open);
      document.querySelector('main').inert = open;
      document.querySelector('.footer').inert = open;
      nav.querySelector('.nav__mark').inert = open;
      document.querySelector('.skip-link').inert = open;
      if (!open) {
        window.scrollTo({ top: lockedScroll, behavior: 'instant' });
        if (panel.contains(document.activeElement) || document.activeElement === toggle) {
          (window.matchMedia('(min-width: 1101px)').matches
            ? nav.querySelector('.nav__mark') : toggle).focus();
        }
      }
    };

    toggle.addEventListener('click', () => {
      setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    // Close on selection, and on Escape — a full-screen overlay with no
    // keyboard exit is a trap.
    panel.addEventListener('click', (e) => {
      if (e.target.closest('a')) setOpen(false);
    }, { capture: true });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Tab' && !panel.hidden) {
        const controls = [toggle, ...panel.querySelectorAll('a[href]')];
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
      if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
        setOpen(false);
        toggle.focus();
      }
    });
    window.matchMedia('(min-width: 1101px)').addEventListener('change', (e) => {
      if (e.matches) setOpen(false);
    });
  };

  // One controlled card at a time below 901px; normal grids above it.
  const setupCarousels = () => {
    const mobile = window.matchMedia('(max-width: 900px)');
    document.querySelectorAll('[data-carousel]').forEach((track) => {
      const cards = [...track.children];
      if (cards.length < 2) return;
      let index = 0;
      const controls = document.createElement('div');
      controls.className = 'carousel-controls';
      const makeButton = (direction, text) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'carousel-button';
        button.textContent = text;
        button.setAttribute('aria-label', `${direction} ${track.dataset.carousel} card`);
        return button;
      };
      const prev = makeButton('Previous', '←');
      const next = makeButton('Next', '→');
      controls.append(prev, next);
      track.after(controls);
      cards.forEach((card, i) => {
        card.removeAttribute('data-reveal');
        card.classList.add('carousel-card');
        // All copy remains available to screen readers. Focusing a link in
        // another card also brings that card into view for keyboard users.
        card.addEventListener('focusin', () => { index = i; update(); });
      });
      const update = () => {
        track.classList.toggle('carousel-ready', mobile.matches);
        controls.hidden = !mobile.matches;
        cards.forEach((card, i) => card.classList.toggle('is-active', i === index));
        prev.disabled = index === 0;
        next.disabled = index === cards.length - 1;
      };
      prev.addEventListener('click', () => { index = Math.max(0, index - 1); update(); });
      next.addEventListener('click', () => { index = Math.min(cards.length - 1, index + 1); update(); });
      mobile.addEventListener('change', update);
      update();
    });
  };

  // Information uses a dedicated peek carousel: the active card stays in the
  // centre while its neighbours remain visible. It is separate from the
  // mobile-only content carousels above because its interaction model applies
  // at every viewport size.
  const setupInformationCarousel = () => {
    document.querySelectorAll('[data-information-carousel]').forEach((carousel) => {
      const viewport = carousel.querySelector('[data-information-viewport]');
      const slides = [...carousel.querySelectorAll('[data-information-slide]')];
      const dots = carousel.querySelector('[data-information-dots]');
      const status = carousel.querySelector('[data-information-status]');
      if (!viewport || slides.length < 2) return;

      let index = 0;
      let pointerStart = 0;
      let pointerId = null;
      let wheelLocked = false;
      let nextAdvance = performance.now() + 4500;
      const paused = new Set();
      const dotButtons = slides.map((slide, slideIndex) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('aria-label', `Show slide ${slideIndex + 1}`);
        button.addEventListener('click', () => goTo(slideIndex, true));
        dots?.append(button);
        return button;
      });

      const relativePosition = (slideIndex) => {
        let position = slideIndex - index;
        if (position > slides.length / 2) position -= slides.length;
        if (position < -slides.length / 2) position += slides.length;
        return position;
      };

      const update = (announce = false) => {
        slides.forEach((slide, slideIndex) => {
          const position = relativePosition(slideIndex);
          slide.dataset.position = String(position);
          slide.classList.toggle('is-active', position === 0);
          slide.setAttribute('aria-hidden', String(Math.abs(position) > 2));
        });
        dotButtons.forEach((button, slideIndex) => {
          const active = slideIndex === index;
          button.classList.toggle('is-active', active);
          button.setAttribute('aria-current', active ? 'true' : 'false');
        });
        if (announce && status) status.textContent = `Slide ${index + 1} of ${slides.length}: ${slides[index].querySelector('h3')?.textContent || ''}`;
      };

      const goTo = (newIndex, announce = false) => {
        index = (newIndex + slides.length) % slides.length;
        nextAdvance = performance.now() + 4500;
        update(announce);
      };

      viewport.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') { event.preventDefault(); goTo(index - 1, true); }
        if (event.key === 'ArrowRight') { event.preventDefault(); goTo(index + 1, true); }
      });

      carousel.addEventListener('pointerenter', () => paused.add('hover'));
      carousel.addEventListener('pointerleave', () => paused.delete('hover'));
      carousel.addEventListener('focusin', () => paused.add('focus'));
      carousel.addEventListener('focusout', () => requestAnimationFrame(() => {
        if (!carousel.contains(document.activeElement)) paused.delete('focus');
      }));

      viewport.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse' && event.button !== 0) return;
        pointerId = event.pointerId;
        pointerStart = event.clientX;
        paused.add('drag');
        viewport.classList.add('is-dragging');
        viewport.setPointerCapture(pointerId);
      });
      viewport.addEventListener('pointermove', (event) => {
        if (event.pointerId !== pointerId) return;
        const drag = Math.max(-110, Math.min(110, event.clientX - pointerStart));
        viewport.style.setProperty('--carousel-drag', `${drag}px`);
      });
      const finishPointer = (event) => {
        if (event.pointerId !== pointerId) return;
        const distance = event.clientX - pointerStart;
        viewport.style.removeProperty('--carousel-drag');
        viewport.classList.remove('is-dragging');
        pointerId = null;
        paused.delete('drag');
        if (Math.abs(distance) >= 42) goTo(index + (distance < 0 ? 1 : -1), true);
      };
      viewport.addEventListener('pointerup', finishPointer);
      viewport.addEventListener('pointercancel', finishPointer);

      viewport.addEventListener('wheel', (event) => {
        const horizontal = Math.abs(event.deltaX) > Math.abs(event.deltaY);
        const movement = horizontal ? event.deltaX : event.deltaY;
        if (Math.abs(movement) < 8) return;
        // Horizontal gestures belong to the carousel. A normal mouse wheel
        // also changes the active card, but keeps scrolling the page.
        if (horizontal) event.preventDefault();
        if (wheelLocked) return;
        wheelLocked = true;
        goTo(index + (movement > 0 ? 1 : -1), true);
        window.setTimeout(() => { wheelLocked = false; }, 450);
      }, { passive: false });

      document.addEventListener('visibilitychange', () => {
        if (document.hidden) paused.add('hidden');
        else { paused.delete('hidden'); nextAdvance = performance.now() + 4500; }
      });

      const autoplay = (now) => {
        if (!reduced && paused.size === 0 && now >= nextAdvance) goTo(index + 1);
        requestAnimationFrame(autoplay);
      };
      update();
      requestAnimationFrame(autoplay);
    });
  };

  // Lightweight Rentals catalogue filtering. With JavaScript unavailable all
  // sample options remain visible; this only adds search and category views.
  const setupRentalCatalog = () => {
    document.querySelectorAll('[data-rental-catalog]').forEach((catalog) => {
      const cards = [...catalog.querySelectorAll('[data-rental-item]')];
      const search = catalog.querySelector('[data-rental-search]');
      const filters = [...catalog.querySelectorAll('[data-rental-filter]')];
      const status = catalog.querySelector('[data-rental-status]');
      const empty = catalog.querySelector('[data-rental-empty]');
      const more = catalog.querySelector('[data-rental-more]');
      const mobile = window.matchMedia('(max-width: 720px)');

      if (cards.length === 0) return;

      let activeCategory = 'all';
      let expanded = false;
      const update = () => {
        const query = (search?.value || '').trim().toLowerCase();
        const matching = cards.filter((card) => {
          const categoryMatches = activeCategory === 'all'
            || card.dataset.rentalCategory === activeCategory;
          const searchable = (card.dataset.rentalSearch || card.textContent || '').toLowerCase();
          return categoryMatches && (!query || searchable.includes(query));
        });
        const limit = mobile.matches ? 4 : 6;
        const shown = expanded ? matching : matching.slice(0, limit);
        const shownSet = new Set(shown);

        cards.forEach((card) => {
          card.hidden = !shownSet.has(card);
        });

        filters.forEach((button) => {
          const isActive = (button.dataset.rentalFilter || 'all') === activeCategory;
          button.classList.toggle('is-active', isActive);
          button.setAttribute('aria-pressed', String(isActive));
        });

        if (status) {
          const noun = matching.length === 1 ? 'option' : 'options';
          const scope = query || activeCategory !== 'all' ? 'matching rental' : 'rental';
          status.textContent = shown.length === matching.length
            ? `Showing ${shown.length} ${scope} ${noun}.`
            : `Showing ${shown.length} of ${matching.length} ${scope} ${noun}.`;
        }
        if (empty) empty.hidden = matching.length !== 0;
        if (more) {
          const remaining = matching.length - shown.length;
          more.hidden = remaining <= 0;
          more.textContent = expanded ? 'Show fewer options' : `Show more options (${remaining})`;
          more.setAttribute('aria-expanded', String(expanded));
        }
      };

      filters.forEach((button) => {
        button.addEventListener('click', () => {
          activeCategory = button.dataset.rentalFilter || 'all';
          expanded = false;
          update();
        });
      });
      document.querySelectorAll('[data-rental-category-link]').forEach((link) => {
        link.addEventListener('click', () => {
          activeCategory = link.dataset.rentalCategoryLink || 'all';
          expanded = false;
          update();
        });
      });
      search?.addEventListener('input', () => {
        expanded = false;
        update();
      });
      more?.addEventListener('click', () => {
        expanded = !expanded;
        update();
      });
      mobile.addEventListener('change', () => {
        expanded = false;
        update();
      });
      update();
    });
  };

  // ── Smooth anchor scrolling ────────────────────────────────
  const setupAnchors = () => {
    // A cross-page hash must settle after images and fonts reserve their space.
    const landOnSection = async () => {
      await document.fonts.ready;
      if (hasGsap) ScrollTrigger.refresh();
      requestAnimationFrame(() => requestAnimationFrame(() => {
        const target = document.getElementById(decodeURIComponent(location.hash.slice(1)));
        if (target) {
          target.scrollIntoView({ behavior: 'instant', block: 'start' });
          target.setAttribute('tabindex', '-1');
          target.focus({ preventScroll: true });
        }
      }));
    };
    if (location.hash) {
      if (document.readyState === 'complete') landOnSection();
      else window.addEventListener('load', landOnSection, { once: true });
    }
    document.querySelectorAll('a[href]').forEach((link) => {
      link.addEventListener('click', (e) => {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const destination = new URL(link.href, location.href);
        if (destination.origin !== location.origin || destination.pathname !== location.pathname || destination.search !== location.search) return;
        const id = destination.hash;
        if (id === '#' || id.length < 2) return;

        const target = document.getElementById(decodeURIComponent(id.slice(1)));
        if (!target) return;

        e.preventDefault();
        target.scrollIntoView({
          behavior: reduced ? 'auto' : 'smooth',
          block: 'start',
        });

        // scrollIntoView does not move focus, so a keyboard user would land
        // visually at the section but keep tabbing from the nav.
        target.setAttribute('tabindex', '-1');
        target.focus({ preventScroll: true });

        if (location.hash !== id) history.pushState(null, '', id);
      });
    });
  };

  // ── Boot ───────────────────────────────────────────────────
  const init = () => {
    setupNav();
    setupCarousels();
    setupInformationCarousel();
    setupRentalCatalog();
    setupAnchors();
    setupReveals();
    setupHero();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
