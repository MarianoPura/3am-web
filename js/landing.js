/**
 * Landing Page JavaScript — /get-a-quote
 * =======================================
 * Handles:
 *  1. Reading landing config (Meta Pixel ID, visit ID, CSRF token, content name)
 *  2. Meta Pixel initialization with deduplicated event IDs
 *  3. UTM & Facebook Click/Browser ID capture (_fbp, _fbc, fbclid)
 *  4. Client-side and server-side tracking event synchronization
 *  5. AJAX form submission with validation error rendering
 *  6. Mobile sticky CTA show/hide based on scroll position
 */

(function () {
  'use strict';

  // ── Config ──────────────────────────────────────────────────────────────
  const config = document.getElementById('landing-config');
  const PIXEL_ID     = config?.dataset.pixelId    ?? '';
  const CONTENT_NAME = config?.dataset.contentName ?? 'Event Production & Livestreaming';
  let   VISIT_ID     = config?.dataset.visitId    ?? '';
  const CSRF_TOKEN   = config?.dataset.csrfToken  ?? '';
  const TRACK_URL    = config?.dataset.trackUrl   ?? '';

  /**
   * Helper to check whether the visitor has Facebook tracking identifiers.
   * If the user visited manually or without any FB ID, movements are not tracked
   * unless they submit an inquiry.
   */
  function checkHasFbId() {
    if (config?.dataset.hasFbId === '1') return true;
    const params = new URLSearchParams(window.location.search);
    if (params.get('fbclid') || params.get('fbc')) return true;
    return false;
  }

  /**
   * Helper to generate a unique, collision-resistant event ID for Meta deduplication.
   */
  function generateEventId(prefix = 'evt') {
    return `evt_${prefix}_${Math.random().toString(36).slice(2, 9)}_${Date.now().toString(36)}`;
  }

  /**
   * Helper to read cookie by name.
   */
  function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[2]) : null;
  }

  // ── Server Event Beacon ─────────────────────────────────────────────────
  /**
   * Record a tracking event into the backend database (tracking_events).
   * Safe, non-blocking, and never interrupts UX.
   */
  async function trackServerEvent(eventName, eventId, eventData = {}, eventSource = 'browser') {
    if (!TRACK_URL) return;

    try {
      const payload = new URLSearchParams({
        _token: CSRF_TOKEN,
        visit_id: VISIT_ID,
        event_name: eventName,
        event_id: eventId,
        event_source: eventSource,
      });

      // Append event_data object
      Object.entries(eventData).forEach(([key, value]) => {
        payload.append(`event_data[${key}]`, typeof value === 'object' ? JSON.stringify(value) : String(value));
      });

      fetch(TRACK_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': CSRF_TOKEN,
        },
        body: payload.toString(),
        keepalive: true,
      })
      .then(res => res.json())
      .then(data => {
        if (data && data.visit_id && !VISIT_ID) {
          VISIT_ID = String(data.visit_id);
        }
      })
      .catch(() => {});
    } catch {
      // Ignore network failures for tracking beacons
    }
  }

  // ── Meta Pixel ──────────────────────────────────────────────────────────
  let pixelLoaded = false;

  /**
   * Ensure Meta Pixel SDK is loaded and initialised.
   */
  function ensurePixelLoaded() {
    if (!PIXEL_ID || pixelLoaded) return;

    /* eslint-disable */
    !function(f,b,e,v,n,t,s) {
      if(f.fbq) return; n=f.fbq=function(){n.callMethod?
      n.callMethod.apply(n,arguments):n.queue.push(arguments)};
      if(!f._fbq) f._fbq=n; n.push=n; n.loaded=!0; n.version='2.0';
      n.queue=[]; t=b.createElement(e); t.async=!0;
      t.src=v; s=b.getElementsByTagName(e)[0];
      s.parentNode.insertBefore(t,s)
    }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
    /* eslint-enable */

    fbq('init', PIXEL_ID);
    pixelLoaded = true;
  }

  /**
   * Initialise the Meta Pixel (Facebook). Only runs when PIXEL_ID is set and visitor has FB ID.
   */
  function initPixel() {
    const pvEventId = generateEventId('pv');

    if (PIXEL_ID) {
      ensurePixelLoaded();
      fbq('track', 'PageView', {}, { eventID: pvEventId });
    }

    trackServerEvent('PageView', pvEventId, { path: window.location.pathname, title: document.title });
  }

  /**
   * Track a specific Meta Pixel event with optional eventID deduplication.
   *
   * @param {string} eventName  Standard or custom event name.
   * @param {object} [params]   Optional event parameters.
   * @param {string} [eventId]  Unique event deduplication ID.
   */
  function trackPixel(eventName, params = {}, eventId = null) {
    if (typeof fbq !== 'function') return;
    if (eventId) {
      fbq('track', eventName, params, { eventID: eventId });
    } else {
      fbq('track', eventName, params);
    }
  }

  // Fire ViewContent once when the visitor scrolls into page content.
  function setupViewContent() {
    const section = document.getElementById('services');
    if (!section) return;

    let fired = false;
    const observer = new IntersectionObserver(
      (entries, obs) => {
        if (entries[0].isIntersecting && !fired) {
          fired = true;
          const vcEventId = generateEventId('vc');
          trackPixel('ViewContent', { content_name: CONTENT_NAME }, vcEventId);
          trackServerEvent('ViewContent', vcEventId, { content_name: CONTENT_NAME });
          obs.disconnect();
        }
      },
      { threshold: 0.15 }
    );
    observer.observe(section);
  }

  // ── UTM & Click ID Capture ──────────────────────────────────────────────
  /**
   * Fill hidden UTM, fbclid, and Facebook cookie (_fbp, _fbc) fields.
   * This preserves campaign attribution through the lead form submission.
   */
  function captureUtms() {
    const params = new URLSearchParams(window.location.search);
    const fbclid = params.get('fbclid');
    const fbpCookie = getCookie('_fbp');
    const fbcCookie = getCookie('_fbc');

    document.querySelectorAll('[data-lp-utm]').forEach(input => {
      const name = input.name;
      let val = params.get(name);

      if (!val) {
        if (name === 'fbp' && fbpCookie) {
          val = fbpCookie;
        } else if (name === 'fbc') {
          val = fbcCookie || (fbclid ? `fb.1.${Date.now()}.${fbclid}` : null);
        }
      }

      if (val) input.value = val;
    });

    // Ensure visit_id is populated in the form if set on the page
    const visitInput = document.querySelector('[data-lp-visit-id]');
    if (visitInput && !visitInput.value && VISIT_ID) {
      visitInput.value = VISIT_ID;
    }
  }

  // ── Form Submission & Error Display ─────────────────────────────────────
  /**
   * Render per-field validation errors returned from the server.
   */
  function showErrors(errors, form = null) {
    const scope = form || document;
    scope.querySelectorAll('[data-lp-error]').forEach(el => {
      el.textContent = '';
      el.style.display = 'none';
    });
    scope.querySelectorAll('.field__input[aria-invalid]').forEach(el => {
      el.removeAttribute('aria-invalid');
    });

    Object.entries(errors).forEach(([field, message]) => {
      const errorEl = scope.querySelector(`[data-lp-error="${field}"]`);
      const inputEl = scope.querySelector(`[name="${field}"]`);
      if (errorEl) {
        errorEl.textContent = message;
        errorEl.style.display = '';
      }
      if (inputEl) {
        inputEl.setAttribute('aria-invalid', 'true');
      }
    });
  }

  /**
   * Show or hide the general form error banner.
   */
  function showGeneralError(message, form = null) {
    const banner = (form && (form.querySelector('[data-lp-general-error]') || form.querySelector('#form-general-error')))
      || document.getElementById('form-general-error');
    if (!banner) return;
    if (message) {
      banner.textContent = message;
      banner.style.display = '';
    } else {
      banner.textContent = '';
      banner.style.display = 'none';
    }
  }

  /**
   * Transition the form card from the form view to the success view.
   */
  function showSuccess(reference) {
    document.querySelectorAll('[data-lp-form-body]').forEach(el => el.style.display = 'none');
    document.querySelectorAll('[data-lp-success]').forEach(el => {
      el.style.display = '';
      el.focus();
    });
    document.querySelectorAll('[data-lp-reference]').forEach(el => {
      if (reference) el.textContent = `Reference: ${reference}`;
    });
    const refEl = document.getElementById('success-reference');
    if (refEl && reference) {
      refEl.textContent = `Reference: ${reference}`;
    }
  }

  function setupForm() {
    const forms = document.querySelectorAll('form.lp-form, #quote-form');
    if (forms.length === 0) return;

    forms.forEach(form => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Ensure Facebook cookies are captured right before submit
        captureUtms();

        // Generate unique event IDs for Lead and Contact events
        const leadEventId = generateEventId('lead');
        const contactEventId = generateEventId('contact');

        const leadIdInput = form.querySelector('[data-lp-lead-event-id]');
        const contactIdInput = form.querySelector('[data-lp-contact-event-id]');
        if (leadIdInput) leadIdInput.value = leadEventId;
        if (contactIdInput) contactIdInput.value = contactEventId;

        // Set loading state
        const submitBtn = form.querySelector('[data-lp-submit]') || document.getElementById('submit-btn');
        const submitLabel = form.querySelector('[data-lp-submit-label]') || document.getElementById('submit-text');
        if (submitBtn) submitBtn.classList.add('is-loading');
        if (submitLabel) submitLabel.textContent = 'Submitting…';
        showGeneralError(null, form);
        showErrors({}, form);

        try {
          const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-Token': CSRF_TOKEN,
            },
          });

          const data = await response.json();

          if (data.ok || data.success) {
            // If server returned a visit_id (e.g. for a manual visitor whose visit was recorded on submit),
            // update client state so subsequent calls have the visit ID
            if (data.visit_id) {
              VISIT_ID = String(data.visit_id);
              if (config) config.dataset.visitId = VISIT_ID;
              document.querySelectorAll('[data-lp-visit-id]').forEach(input => {
                input.value = VISIT_ID;
              });
            }

            // Ensure Meta Pixel is loaded for conversion events
            ensurePixelLoaded();

            // ── Only fire pixels on confirmed server success ──────────────
            trackPixel('Lead',    { content_name: CONTENT_NAME, currency: 'PHP' }, leadEventId);
            trackPixel('Contact', { content_name: CONTENT_NAME }, contactEventId);

            // Record browser-side tracking event confirmations into database
            trackServerEvent('Lead', leadEventId, { content_name: CONTENT_NAME, reference: data.reference }, 'browser');
            trackServerEvent('Contact', contactEventId, { content_name: CONTENT_NAME, reference: data.reference }, 'browser');

            showSuccess(data.reference ?? null);
          } else if (data.errors) {
            showErrors(data.errors, form);
          } else {
            showGeneralError(data.message ?? 'Something went wrong. Please try again.', form);
          }

        } catch {
          showGeneralError('Network error. Please check your connection and try again.', form);
        } finally {
          if (submitBtn) submitBtn.classList.remove('is-loading');
          if (submitLabel) submitLabel.textContent = 'Submit My Inquiry';
        }
      });
    });
  }

  // ── Form Engagement Tracking (form_start, form_field_focus) ─────────────
  function setupFormTracking() {
    let formStarted = false;
    const focusedFields = new Set();
    const forms = document.querySelectorAll('.lp-form, #quote-form');
    if (forms.length === 0) return;

    forms.forEach(form => {
      // 1. form_start: first user engagement with the form
      const onFormStart = (e) => {
        if (formStarted) return;
        const target = e.target;
        if (!target || !target.matches('input:not([type="hidden"]), select, textarea')) return;

        formStarted = true;
        const eventId = generateEventId('fs');
        const isModal = Boolean(form.closest('#lp-quote-modal'));
        trackServerEvent('form_start', eventId, {
          form_id: form.id || 'quote-form',
          field_name: target.name || target.id,
          location: isModal ? 'modal' : 'hero',
        });
        trackPixel('form_start', {
          form_id: form.id || 'quote-form',
          field_name: target.name || target.id,
        }, eventId);
      };

      form.addEventListener('focusin', onFormStart, { passive: true });
      form.addEventListener('input', onFormStart, { passive: true });

      // 2. form_field_focus: fires when any specific field receives focus
      form.addEventListener('focusin', (e) => {
        const target = e.target;
        if (!target || !target.matches('input:not([type="hidden"]), select, textarea')) return;

        const fieldName = target.name || target.id;
        if (!fieldName) return;

        // Dedup per unique field name to avoid database flooding on repeated clicks
        if (!focusedFields.has(fieldName)) {
          focusedFields.add(fieldName);
          const eventId = generateEventId('fff');
          const isModal = Boolean(form.closest('#lp-quote-modal'));
          trackServerEvent('form_field_focus', eventId, {
            field_name: fieldName,
            field_type: target.type || target.tagName.toLowerCase(),
            location: isModal ? 'modal' : 'hero',
          });
          trackPixel('form_field_focus', { field_name: fieldName }, eventId);
        }
      }, { passive: true });
    });
  }

  // ── Scroll Depth Tracking (scrolldepth) ─────────────────────────────────
  function setupScrollDepthTracking() {
    const milestones = [25, 50, 75, 100];
    const firedDepths = new Set();
    let ticking = false;

    function checkScrollDepth() {
      const doc = document.documentElement;
      const body = document.body;
      const docHeight = Math.max(doc.scrollHeight, body.scrollHeight, doc.offsetHeight, body.offsetHeight);
      const winHeight = window.innerHeight || doc.clientHeight;
      const scrollable = docHeight - winHeight;

      if (scrollable <= 0) return;

      const scrollTop = window.pageYOffset || doc.scrollTop || body.scrollTop || 0;
      const currentPercent = Math.min(100, Math.round((scrollTop / scrollable) * 100));

      for (const milestone of milestones) {
        if (currentPercent >= milestone && !firedDepths.has(milestone)) {
          firedDepths.add(milestone);
          const eventId = generateEventId(`sd${milestone}`);
          trackServerEvent('scrolldepth', eventId, {
            depth: milestone,
            percent: currentPercent,
          });
          trackPixel('scrolldepth', { depth: milestone }, eventId);
        }
      }

      if (firedDepths.size === milestones.length) {
        window.removeEventListener('scroll', onScroll);
      }
    }

    function onScroll() {
      if (!ticking) {
        window.requestAnimationFrame(() => {
          checkScrollDepth();
          ticking = false;
        });
        ticking = true;
      }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    checkScrollDepth();
  }

  // ── CTA Click Tracking (cta_click) ──────────────────────────────────────
  function setupCTAClickTracking() {
    document.addEventListener('click', (e) => {
      const cta = e.target.closest('[data-lp-cta], [data-lp-modal-trigger], [data-lp-submit], a[href="#lead-form"], button.lp-form__submit, #submit-btn');
      if (!cta) return;

      const ctaText = (cta.innerText || cta.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 80);
      const ctaHref = cta.getAttribute('href') || '';
      const ctaId = cta.id || cta.getAttribute('data-lp-cta') || '';
      const location = cta.closest('nav') ? 'navbar'
        : (cta.closest('.lp-hero') ? 'hero'
        : (cta.closest('.lp-problems') ? 'problems'
        : (cta.closest('.lp-final') ? 'final'
        : (cta.closest('#lp-quote-modal') ? 'modal' : 'body'))));

      const eventId = generateEventId('cta');
      trackServerEvent('cta_click', eventId, {
        cta_text: ctaText,
        cta_href: ctaHref,
        cta_id: ctaId,
        location: location,
      });
      trackPixel('cta_click', { cta_text: ctaText, location: location }, eventId);
    }, { passive: true });
  }

  // ── Mobile Sticky CTA ───────────────────────────────────────────────────
  /**
   * Show the sticky bottom CTA when the user scrolls past the in-hero form,
   * and hide it again when the final CTA section comes into view.
   */
  function setupStickyCTA() {
    const sticky  = document.getElementById('mobile-sticky-cta');
    const form    = document.getElementById('lead-form');
    const finalEl = document.querySelector('.lp-final');
    if (!sticky || !form) return;

    const scrollObserver = new IntersectionObserver(
      ([entry]) => {
        sticky.classList.toggle('is-hidden', entry.isIntersecting);
      },
      { threshold: 0.05 }
    );
    scrollObserver.observe(form);

    if (finalEl) {
      const finalObserver = new IntersectionObserver(
        ([entry]) => {
          if (entry.isIntersecting) {
            sticky.classList.add('is-hidden');
          }
        },
        { threshold: 0.1 }
      );
      finalObserver.observe(finalEl);
    }
  }

  // ── Smooth Scroll for #lead-form CTAs ───────────────────────────────────

  // ── Logo: scroll back to hero top on click ──────────────────────────────
  function setupLogoHomeScroll() {
    document.querySelectorAll('[data-lp-logo-home]').forEach(el => {
      el.addEventListener('click', e => {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    });
  }

  // ── Quote Modal (mobile bottom sheet) ─────────────────────────────────────
  function setupModal() {
    const modal = document.getElementById('lp-quote-modal');
    if (!modal) return;

    let closing = false;

    function openModal() {
      if (window.innerWidth >= 992) return; // desktop: never use modal
      modal.removeAttribute('hidden');
      modal.classList.add('is-open');
      modal.classList.remove('is-closing');
      document.body.style.overflow = 'hidden';
      requestAnimationFrame(() => {
        const first = modal.querySelector('input:not([type="hidden"]), select, textarea');
        if (first) first.focus({ preventScroll: true });
      });
    }

    function closeModal() {
      if (closing) return;
      closing = true;
      modal.classList.add('is-closing');
      const sheet = modal.querySelector('.lp-modal__sheet');
      const done = () => {
        modal.classList.remove('is-open', 'is-closing');
        modal.setAttribute('hidden', '');
        document.body.style.overflow = '';
        closing = false;
      };
      if (sheet) {
        sheet.addEventListener('animationend', done, { once: true });
      } else {
        done();
      }
    }

    // Open triggers (buttons/links with data-lp-modal-trigger)
    document.querySelectorAll('[data-lp-modal-trigger]').forEach(btn => {
      btn.addEventListener('click', e => {
        if (window.innerWidth >= 992) return;
        e.preventDefault();
        openModal();
      });
    });

    // Close triggers (backdrop + close button)
    modal.querySelectorAll('[data-lp-modal-close]').forEach(el => {
      el.addEventListener('click', closeModal);
    });

    // Escape key
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    // If page loaded with #lead-form hash on mobile, open modal automatically
    if (window.location.hash === '#lead-form' && window.innerWidth < 992) {
      setTimeout(openModal, 150);
    }
  }

  function setupCTAScroll() {
    document.querySelectorAll('[data-lp-cta]').forEach(cta => {
      cta.addEventListener('click', (e) => {
        // On mobile, modal triggers are handled by setupModal; bail out here
        if (window.innerWidth < 992 && cta.hasAttribute('data-lp-modal-trigger')) {
          e.preventDefault();
          return;
        }

        const href = cta.getAttribute('href');
        if (!href || !href.startsWith('#')) return;
        const target = document.querySelector(href);
        if (!target) return;
        e.preventDefault();

        const isDesktop = window.innerWidth >= 992;
        const firstInput = target.querySelector('input:not([type="hidden"]), select, textarea');

        // On desktop, if already at the hero section, avoid scrolling down and cutting the top
        if (isDesktop && href === '#lead-form' && window.scrollY < 160) {
          if (firstInput) {
            firstInput.focus({ preventScroll: true });
          }
          return;
        }

        // Calculate destination with sticky navbar clearance
        const nav = document.querySelector('.nav');
        const navHeight = nav ? nav.offsetHeight : 68;
        const targetTop = (isDesktop && href === '#lead-form')
          ? 0
          : Math.max(0, target.getBoundingClientRect().top + window.pageYOffset - (navHeight + 16));

        window.scrollTo({
          top: targetTop,
          behavior: 'smooth'
        });

        if (firstInput) {
          setTimeout(() => firstInput.focus({ preventScroll: true }), 450);
        }
      });
    });

    // Handle initial #lead-form hash if visitor arrives with direct anchor
    if (window.location.hash === '#lead-form') {
      setTimeout(() => {
        const target = document.getElementById('lead-form');
        if (!target) return;
        const isDesktop = window.innerWidth >= 992;
        const nav = document.querySelector('.nav');
        const navHeight = nav ? nav.offsetHeight : 68;
        const targetTop = isDesktop
          ? 0
          : Math.max(0, target.getBoundingClientRect().top + window.pageYOffset - (navHeight + 16));
        window.scrollTo({ top: targetTop, behavior: 'smooth' });
      }, 100);
    }

    // Toggle condensed styling on minimal header when scrolling
    const navMinimal = document.querySelector('.nav--minimal');
    if (navMinimal) {
      window.addEventListener('scroll', () => {
        navMinimal.classList.toggle('is-condensed', window.scrollY > 40);
      }, { passive: true });
    }
  }

  // ── Services Section Carousel ───────────────────────────────────────────
  function setupServicesCarousel() {
    const carousel = document.querySelector('[data-lp-carousel]');
    if (!carousel) return;

    const track   = carousel.querySelector('[data-lp-carousel-track]');
    const slides  = Array.from(carousel.querySelectorAll('[data-lp-carousel-slide]'));
    const dots    = Array.from(carousel.querySelectorAll('[data-lp-carousel-dot]'));
    const prevBtn = document.querySelector('[data-lp-carousel-prev]');
    const nextBtn = document.querySelector('[data-lp-carousel-next]');

    if (!track || slides.length === 0) return;

    let currentIndex = 0;
    let autoTimer    = null;
    let isInteracting = false;
    const intervalMs = 4200;

    function isCarouselMode() {
      return window.innerWidth < 992;
    }

    function updateActiveState(index) {
      currentIndex = index;
      dots.forEach((dot, i) => {
        dot.classList.toggle('is-active', i === index);
        dot.setAttribute('aria-selected', i === index ? 'true' : 'false');
      });
      slides.forEach((slide, i) => {
        slide.classList.toggle('is-active', i === index);
      });
    }

    function scrollToSlide(index, smooth = true) {
      if (index < 0) index = slides.length - 1;
      if (index >= slides.length) index = 0;

      const targetSlide = slides[index];
      if (!targetSlide) return;

      const targetLeft = targetSlide.offsetLeft - track.offsetLeft;
      track.scrollTo({
        left: targetLeft,
        behavior: smooth ? 'smooth' : 'auto'
      });
      updateActiveState(index);
    }

    function nextSlide() {
      scrollToSlide(currentIndex + 1);
    }

    function prevSlide() {
      scrollToSlide(currentIndex - 1);
    }

    function startAutoScroll() {
      stopAutoScroll();
      if (!isCarouselMode() || isInteracting) return;
      autoTimer = setInterval(() => {
        if (!isInteracting && isCarouselMode()) {
          nextSlide();
        }
      }, intervalMs);
    }

    function stopAutoScroll() {
      if (autoTimer) {
        clearInterval(autoTimer);
        autoTimer = null;
      }
    }

    // Dot navigation
    dots.forEach((dot, i) => {
      dot.addEventListener('click', () => {
        stopAutoScroll();
        scrollToSlide(i);
        startAutoScroll();
      });
    });

    // Arrow navigation
    if (prevBtn) {
      prevBtn.addEventListener('click', () => {
        stopAutoScroll();
        prevSlide();
        startAutoScroll();
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', () => {
        stopAutoScroll();
        nextSlide();
        startAutoScroll();
      });
    }

    // Synchronize active dot with user's native swipe / scroll
    let scrollTimeout = null;
    track.addEventListener('scroll', () => {
      if (!isCarouselMode()) return;

      clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(() => {
        const scrollCenter = track.scrollLeft + track.clientWidth / 2;
        let closestIndex = 0;
        let minDiff = Infinity;

        slides.forEach((slide, i) => {
          const slideCenter = slide.offsetLeft - track.offsetLeft + slide.clientWidth / 2;
          const diff = Math.abs(scrollCenter - slideCenter);
          if (diff < minDiff) {
            minDiff = diff;
            closestIndex = i;
          }
        });

        if (closestIndex !== currentIndex) {
          updateActiveState(closestIndex);
        }
      }, 50);
    }, { passive: true });

    // Pause on hover or touch
    carousel.addEventListener('mouseenter', () => {
      isInteracting = true;
      stopAutoScroll();
    });
    carousel.addEventListener('mouseleave', () => {
      isInteracting = false;
      startAutoScroll();
    });
    carousel.addEventListener('touchstart', () => {
      isInteracting = true;
      stopAutoScroll();
    }, { passive: true });
    carousel.addEventListener('touchend', () => {
      setTimeout(() => {
        isInteracting = false;
        startAutoScroll();
      }, 1500);
    }, { passive: true });

    // Pause when tab is backgrounded
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        stopAutoScroll();
      } else {
        startAutoScroll();
      }
    });

    // Handle window resize
    window.addEventListener('resize', () => {
      if (isCarouselMode()) {
        startAutoScroll();
      } else {
        stopAutoScroll();
      }
    }, { passive: true });

    updateActiveState(0);
    startAutoScroll();
  }

  // ── Hero Video Autoplay, Loop & Milestone Tracking ──────────────────────
  function setupHeroVideo() {
    const video = document.querySelector('.lp-hero__media video');
    if (!video) return;

    video.muted = true;
    video.defaultMuted = true;
    video.loop = true;

    const playPromise = video.play();
    if (playPromise !== undefined) {
      playPromise.catch(() => {
        const tryPlay = () => {
          video.play().catch(() => {});
        };
        window.addEventListener('click', tryPlay, { once: true, passive: true });
        window.addEventListener('touchstart', tryPlay, { once: true, passive: true });
      });
    }

    // Video milestones: video_25, video_50, video_75, Video_Complete
    const firedVideoEvents = new Set();
    let lastTime = 0;

    function checkVideoProgress() {
      const duration = video.duration;
      if (!duration || duration <= 0 || isNaN(duration)) return;

      const current = video.currentTime;
      const pct = (current / duration) * 100;

      // 25% milestone
      if (pct >= 25 && !firedVideoEvents.has('video_25')) {
        firedVideoEvents.add('video_25');
        const eventId = generateEventId('v25');
        trackServerEvent('video_25', eventId, {
          duration: Math.round(duration),
          current_time: Math.round(current),
        });
        trackPixel('video_25', { progress: 25 }, eventId);
      }

      // 50% milestone
      if (pct >= 50 && !firedVideoEvents.has('video_50')) {
        firedVideoEvents.add('video_50');
        const eventId = generateEventId('v50');
        trackServerEvent('video_50', eventId, {
          duration: Math.round(duration),
          current_time: Math.round(current),
        });
        trackPixel('video_50', { progress: 50 }, eventId);
      }

      // 75% milestone
      if (pct >= 75 && !firedVideoEvents.has('video_75')) {
        firedVideoEvents.add('video_75');
        const eventId = generateEventId('v75');
        trackServerEvent('video_75', eventId, {
          duration: Math.round(duration),
          current_time: Math.round(current),
        });
        trackPixel('video_75', { progress: 75 }, eventId);
      }

      // Video_Complete milestone (either reached 98%+ duration or looped back after 75%)
      if (!firedVideoEvents.has('Video_Complete')) {
        if (pct >= 98 || (lastTime > 0.75 * duration && current < 0.25 * duration)) {
          firedVideoEvents.add('Video_Complete');
          const eventId = generateEventId('vcmp');
          trackServerEvent('Video_Complete', eventId, {
            duration: Math.round(duration),
          });
          trackPixel('Video_Complete', { duration: Math.round(duration) }, eventId);
        }
      }

      lastTime = current;
    }

    video.addEventListener('timeupdate', checkVideoProgress, { passive: true });
    video.addEventListener('ended', () => {
      if (!firedVideoEvents.has('Video_Complete')) {
        firedVideoEvents.add('Video_Complete');
        const eventId = generateEventId('vcmp');
        trackServerEvent('Video_Complete', eventId, {
          duration: Math.round(video.duration || 0),
        });
        trackPixel('Video_Complete', {}, eventId);
      }
    });
  }

  // ── Initialise ──────────────────────────────────────────────────────────
  initPixel();
  captureUtms();
  setupViewContent();
  setupForm();
  setupFormTracking();
  setupScrollDepthTracking();
  setupCTAClickTracking();
  setupModal();
  setupCTAScroll();
  setupLogoHomeScroll();
  setupServicesCarousel();
  setupHeroVideo();

})();
