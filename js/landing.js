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
  const VISIT_ID     = config?.dataset.visitId    ?? '';
  const CSRF_TOKEN   = config?.dataset.csrfToken  ?? '';
  const TRACK_URL    = config?.dataset.trackUrl   ?? '';

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
      }).catch(() => {});
    } catch {
      // Ignore network failures for tracking beacons
    }
  }

  // ── Meta Pixel ──────────────────────────────────────────────────────────
  /**
   * Initialise the Meta Pixel (Facebook). Only runs when PIXEL_ID is set.
   * We avoid using fbq('init') until the Pixel ID is confirmed to prevent
   * unnecessary requests to Facebook's servers.
   */
  function initPixel() {
    if (!PIXEL_ID) return;

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

    const pvEventId = generateEventId('pv');
    fbq('track', 'PageView', {}, { eventID: pvEventId });
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

  // ── Form Submission ─────────────────────────────────────────────────────
  /**
   * Render per-field validation errors returned from the server.
   */
  function showErrors(errors) {
    // Clear all previous errors
    document.querySelectorAll('[data-lp-error]').forEach(el => {
      el.textContent = '';
      el.style.display = 'none';
    });
    document.querySelectorAll('.field__input[aria-invalid]').forEach(el => {
      el.removeAttribute('aria-invalid');
    });

    // Show new errors
    Object.entries(errors).forEach(([field, message]) => {
      const errorEl = document.querySelector(`[data-lp-error="${field}"]`);
      const inputEl = document.querySelector(`[name="${field}"]`);
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
  function showGeneralError(message) {
    const banner = document.getElementById('form-general-error');
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
    const formBody    = document.querySelector('[data-lp-form-body]');
    const successBody = document.querySelector('[data-lp-success]');
    if (formBody)    formBody.style.display    = 'none';
    if (successBody) {
      successBody.style.display = '';
      successBody.focus();
    }
    // Fill in reference number if provided
    const refEl = document.getElementById('success-reference');
    if (refEl && reference) {
      refEl.textContent = `Reference: ${reference}`;
    }
  }

  function setupForm() {
    const form = document.getElementById('quote-form');
    if (!form) return;

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
      const submitBtn = document.getElementById('submit-btn');
      const submitLabel = document.getElementById('submit-text');
      if (submitBtn) submitBtn.classList.add('is-loading');
      if (submitLabel) submitLabel.textContent = 'Submitting…';
      showGeneralError(null);
      showErrors({});

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
          // ── Only fire pixels on confirmed server success ──────────────
          trackPixel('Lead',    { content_name: CONTENT_NAME, currency: 'PHP' }, leadEventId);
          trackPixel('Contact', { content_name: CONTENT_NAME }, contactEventId);

          // Record browser-side tracking event confirmations
          trackServerEvent('Lead', leadEventId, { content_name: CONTENT_NAME, reference: data.reference }, 'browser');
          trackServerEvent('Contact', contactEventId, { content_name: CONTENT_NAME, reference: data.reference }, 'browser');

          showSuccess(data.reference ?? null);
        } else if (data.errors) {
          showErrors(data.errors);
        } else {
          showGeneralError(data.message ?? 'Something went wrong. Please try again.');
        }

      } catch {
        showGeneralError('Network error. Please check your connection and try again.');
      } finally {
        if (submitBtn) submitBtn.classList.remove('is-loading');
        if (submitLabel) submitLabel.textContent = 'Submit My Inquiry';
      }
    });
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
        if (window.innerWidth < 992 && cta.hasAttribute('data-lp-modal-trigger')) return;

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

  // ── Hero Video Autoplay & Loop ──────────────────────────────────────────
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
  }

  // ── Initialise ──────────────────────────────────────────────────────────
  initPixel();
  captureUtms();
  setupViewContent();
  setupForm();
  setupModal();
  setupStickyCTA();
  setupCTAScroll();
  setupServicesCarousel();
  setupHeroVideo();

})();
