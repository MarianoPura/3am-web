/**
 * Landing Page JavaScript — /get-a-quote
 * =======================================
 * Handles:
 *  1. Reading landing config (Meta Pixel ID, content name)
 *  2. Meta Pixel initialization and event tracking
 *  3. UTM parameter capture from URL → hidden form fields
 *  4. AJAX form submission with validation error rendering
 *  5. Mobile sticky CTA show/hide based on scroll position
 */

(function () {
  'use strict';

  // ── Config ──────────────────────────────────────────────────────────────
  const config = document.getElementById('landing-config');
  const PIXEL_ID    = config?.dataset.pixelId    ?? '';
  const CONTENT_NAME = config?.dataset.contentName ?? 'Landing Page';

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
    fbq('track', 'PageView');
  }

  /**
   * Track a specific Meta Pixel event. Safe to call without a Pixel loaded.
   *
   * @param {string} eventName  Standard or custom event name.
   * @param {object} [params]   Optional event parameters.
   */
  function trackPixel(eventName, params = {}) {
    if (typeof fbq !== 'function') return;
    fbq('track', eventName, params);
  }

  // Fire ViewContent once when the visitor scrolls into page content.
  function setupViewContent() {
    if (!PIXEL_ID) return;

    const section = document.getElementById('services');
    if (!section) return;

    const observer = new IntersectionObserver(
      (entries, obs) => {
        if (entries[0].isIntersecting) {
          trackPixel('ViewContent', { content_name: CONTENT_NAME });
          obs.disconnect();
        }
      },
      { threshold: 0.15 }
    );
    observer.observe(section);
  }

  // ── UTM Capture ─────────────────────────────────────────────────────────
  /**
   * Fill hidden UTM fields from the current page URL's query parameters.
   * This preserves campaign attribution through the lead form submission.
   */
  function captureUtms() {
    const params = new URLSearchParams(window.location.search);
    document.querySelectorAll('[data-lp-utm]').forEach(input => {
      const val = params.get(input.name);
      if (val) input.value = val;
    });
  }

  // ── Form Submission ─────────────────────────────────────────────────────
  /**
   * Render per-field validation errors returned from the server.
   *
   * @param {object} errors  Map of { fieldName: 'Error message' }
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
   *
   * @param {string|null} message  The error text, or null to hide.
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
   *
   * @param {string} [reference]  Optional server-generated reference number.
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
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        const data = await response.json();

        if (data.success) {
          // ── Only fire pixels on confirmed server success ──────────────
          trackPixel('Lead',    { content_name: CONTENT_NAME });
          trackPixel('Contact', { content_name: CONTENT_NAME });
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

    let heroFormVisible = true;

    const formObserver = new IntersectionObserver(
      ([entry]) => { heroFormVisible = entry.isIntersecting; },
      { threshold: 0.1 }
    );
    formObserver.observe(form);

    const finalObserver = new IntersectionObserver(
      ([entry]) => {
        sticky.classList.toggle('is-hidden', entry.isIntersecting);
      },
      { threshold: 0.1 }
    );
    if (finalEl) finalObserver.observe(finalEl);

    // Show sticky once hero form is scrolled out of view
    const scrollObserver = new IntersectionObserver(
      ([entry]) => {
        sticky.classList.toggle('is-hidden', entry.isIntersecting);
      },
      { threshold: 0.05 }
    );
    scrollObserver.observe(form);
  }

  // ── Smooth Scroll for #lead-form CTAs ───────────────────────────────────
  function setupCTAScroll() {
    document.querySelectorAll('[data-lp-cta]').forEach(cta => {
      cta.addEventListener('click', (e) => {
        const target = document.querySelector(cta.getAttribute('href'));
        if (!target) return;
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // Focus the first visible input for accessibility
        const firstInput = target.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) {
          setTimeout(() => firstInput.focus(), 500);
        }
      });
    });
  }

  // ── Initialise ──────────────────────────────────────────────────────────
  initPixel();
  captureUtms();
  setupViewContent();
  setupForm();
  setupStickyCTA();
  setupCTAScroll();

})();
