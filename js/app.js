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
    setupAnchors();
    setupReveals();
    setupHero();
    // Expanding supporting content changes downstream reveal positions.
    document.querySelectorAll('details').forEach((detail) => {
      detail.addEventListener('toggle', () => { if (hasGsap) ScrollTrigger.refresh(); });
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
