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
      opacity: 0.3,
      ease: 'none',
      scrollTrigger: {
        trigger: hero,
        start: 'top top',
        end: 'bottom top',
        scrub: 0.5,
      },
    });
  };

  // ── Signal chain pulse ─────────────────────────────────────
  const setupChain = () => {
    const pulse = document.querySelector('[data-chain-pulse]');
    if (!pulse || reduced || !hasGsap) return;

    const length = pulse.getTotalLength ? pulse.getTotalLength() : 340;

    gsap.set(pulse, { strokeDasharray: `60 ${length}`, strokeDashoffset: length });

    gsap.to(pulse, {
      strokeDashoffset: -60,
      duration: 2.4,
      ease: 'none',
      repeat: -1,
      scrollTrigger: {
        trigger: pulse,
        start: 'top 90%',
        end: 'bottom 10%',
        // Only animate while it is on screen — an infinite repeat left running
        // off-screen burns battery on a phone for something nobody can see.
        toggleActions: 'play pause resume pause',
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

    const setOpen = (open) => {
      toggle.setAttribute('aria-expanded', String(open));
      panel.hidden = !open;
      root.classList.toggle('is-nav-open', open);
    };

    toggle.addEventListener('click', () => {
      setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    // Close on selection, and on Escape — a full-screen overlay with no
    // keyboard exit is a trap.
    panel.addEventListener('click', (e) => {
      if (e.target.tagName === 'A') setOpen(false);
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
        setOpen(false);
        toggle.focus();
      }
    });
  };

  // ── Smooth anchor scrolling ────────────────────────────────
  const setupAnchors = () => {
    document.querySelectorAll('a[href^="#"]').forEach((link) => {
      link.addEventListener('click', (e) => {
        const id = link.getAttribute('href');
        if (id === '#' || id.length < 2) return;

        const target = document.querySelector(id);
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

        history.replaceState(null, '', id);
      });
    });
  };

  // ── Boot ───────────────────────────────────────────────────
  const init = () => {
    setupNav();
    setupAnchors();
    setupReveals();
    setupHero();
    setupChain();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
