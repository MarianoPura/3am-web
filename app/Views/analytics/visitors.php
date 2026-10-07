<?php
/**
 * Analytics — Visitors / Sessions list.
 *
 * Table columns:
 *  - ID / Identifier  : FB Click ID (priority), else inquiry email, else inquiry phone
 *  - Name             : from the linked inquiry, if the visitor submitted the form
 *  - Events           : count of tracking events for this session
 *  - First Visit      : formatted as "F j, Y · g:i A"
 *  - Last Visit       : formatted as "F j, Y · g:i A"
 *  - Actions          : "View Events" button that opens a modal
 *
 * @var App\Core\View $this
 * @var array         $user
 * @var list<array>   $visitors     Each row has: id, fbc, fbp, lpv_email, lpv_contact, inq_name, inq_email, inq_phone, event_count, first_seen_at, last_seen_at
 * @var int           $page
 * @var int           $totalPages
 * @var int           $totalCount
 */
$this->extend('analytics.layout');
$this->start('title'); echo 'Visitors'; $this->end();

function analyticsVisLink(int $pg): string {
    return url('analytics/visitors') . ($pg > 1 ? '?page=' . $pg : '');
}

/**
 * Format a datetime string nicely, e.g. "October 5, 2026 · 4:30 PM"
 */
function formatVisitorDate(string $datetime): string {
    if ($datetime === '' || $datetime === '—') return '—';
    try {
        $dt = new DateTimeImmutable($datetime);
        return $dt->format('F j, Y') . ' · ' . $dt->format('g:i A');
    } catch (\Throwable) {
        return e($datetime);
    }
}

/**
 * Determine the best identifier label for a visitor row.
 * Priority: FB Click ID → inquiry email → inquiry phone → "Anonymous"
 */
function visitorIdentifier(array $v): array {
    $fbc   = trim((string) ($v['fbc'] ?? ''));
    $fbp   = trim((string) ($v['fbp'] ?? ''));
    $email = trim((string) ($v['inq_email'] ?? '')) ?: trim((string) ($v['lpv_email'] ?? ''));
    $phone = trim((string) ($v['inq_phone'] ?? '')) ?: trim((string) ($v['lpv_contact'] ?? ''));

    if ($fbc !== '') {
        // Show shortened FB Click ID — keep only the last segment for readability
        $parts = explode('.', $fbc);
        $short = count($parts) >= 4 ? end($parts) : $fbc;
        return ['label' => 'FB ID', 'value' => $short, 'title' => $fbc, 'type' => 'fb'];
    }

    if ($fbp !== '') {
        // Show shortened FB Browser ID
        $parts = explode('.', $fbp);
        $short = count($parts) >= 4 ? end($parts) : $fbp;
        return ['label' => 'FB ID', 'value' => $short, 'title' => $fbp, 'type' => 'fb'];
    }

    if ($email !== '') {
        return ['label' => 'Email', 'value' => $email, 'title' => $email, 'type' => 'email'];
    }

    if ($phone !== '') {
        return ['label' => 'Phone', 'value' => $phone, 'title' => $phone, 'type' => 'phone'];
    }

    return ['label' => '', 'value' => 'Anonymous', 'title' => '', 'type' => 'anon'];
}

$this->start('content');
?>
<section class="analytics-page">
  <div class="analytics-shell">

    <div class="analytics-page__heading">
      <div>
        <p class="analytics-kicker">3AM Digital Media / Website Tracking</p>
        <h1>Website Visitors</h1>
      </div>
      <p><?= number_format($totalCount) ?> unique visitor sessions recorded</p>
    </div>

    <div class="analytics__panel">
      <div class="analytics__panel-heading">
        <div>
          <h2>Visitor Sessions</h2>
          <p class="analytics__panel-sub">Each row represents one browsing session. Visitors who submitted an inquiry will show their name and contact details.</p>
        </div>
      </div>

      <?php if (empty($visitors)): ?>
        <p class="analytics__empty">No visitor sessions recorded yet.</p>
      <?php else: ?>
        <div class="analytics__table-wrap">
          <table class="analytics__table visitors-table">
            <thead>
              <tr>
                <th title="Best available identifier for this visitor">Visitor ID</th>
                <th>Name</th>
                <th title="Number of tracking events fired during this session">Events</th>
                <th>First Visit</th>
                <th>Last Visit</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($visitors as $v):
                $ident    = visitorIdentifier($v);
                $name     = trim((string) ($v['inq_name'] ?? ''));
                $eventCnt = (int) ($v['event_count'] ?? 0);
              ?>
              <tr>

                <!-- Visitor identifier cell -->
                <td>
                  <div class="visitor-id">
                    <span class="mono visitor-id__value"
                          <?= $ident['title'] !== '' ? 'title="' . e_attr($ident['title']) . '"' : '' ?>>
                      <?= e($ident['value']) ?>
                    </span>
                    <?php if ($ident['type'] !== 'anon'): ?>
                      <span class="visitor-id__label visitor-id__label--<?= e_attr($ident['type']) ?>">
                        <?= e($ident['label']) ?>
                      </span>
                    <?php endif ?>
                  </div>
                  <div class="visitor-id__meta mono">#<?= (int) $v['id'] ?></div>
                </td>

                <!-- Name from inquiry -->
                <td>
                  <?php if ($name !== ''): ?>
                    <span class="visitor-name"><?= e($name) ?></span>
                  <?php else: ?>
                    <span class="analytics__empty-cell">—</span>
                  <?php endif ?>
                </td>

                <!-- Event count -->
                <td>
                  <span class="analytics__count-badge"><?= $eventCnt ?> event<?= $eventCnt !== 1 ? 's' : '' ?></span>
                </td>

                <!-- First seen date -->
                <td class="visitor-date">
                  <?= formatVisitorDate((string) ($v['first_seen_at'] ?? '')) ?>
                </td>

                <!-- Last seen date -->
                <td class="visitor-date">
                  <?= formatVisitorDate((string) ($v['last_seen_at'] ?? '')) ?>
                </td>

                <!-- Actions -->
                <td>
                  <?php if ($eventCnt > 0): ?>
                    <button
                      class="analytics-btn analytics-btn--ghost visitor-events-btn"
                      data-visit-id="<?= (int) $v['id'] ?>"
                      data-visitor-label="<?= e_attr($ident['value']) ?>"
                      data-events-url="<?= e_attr(url('analytics/visitors/' . (int) $v['id'] . '/events')) ?>"
                      aria-label="View events for visitor <?= e_attr($ident['value']) ?>"
                    >
                      View Events
                    </button>
                  <?php else: ?>
                    <span class="analytics__empty-cell">No events</span>
                  <?php endif ?>
                </td>

              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="analytics__pagination">
          <span class="analytics__pagination-info"><?= number_format($totalCount) ?> sessions &middot; page <?= $page ?> of <?= $totalPages ?></span>
          <?php if ($page > 1): ?>
            <a href="<?= e_attr(analyticsVisLink($page - 1)) ?>">‹ Prev</a>
          <?php endif ?>
          <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <?php if ($p === $page): ?>
              <span class="is-active"><?= $p ?></span>
            <?php else: ?>
              <a href="<?= e_attr(analyticsVisLink($p)) ?>"><?= $p ?></a>
            <?php endif ?>
          <?php endfor ?>
          <?php if ($page < $totalPages): ?>
            <a href="<?= e_attr(analyticsVisLink($page + 1)) ?>">Next ›</a>
          <?php endif ?>
        </div>
      <?php endif ?>
    </div>

  </div>
</section>

<!-- ── Events Modal ──────────────────────────────────────────────────── -->
<div id="visitor-events-modal" class="vmodal" role="dialog" aria-modal="true" aria-labelledby="vmodal-title" hidden>
  <div class="vmodal__backdrop" id="vmodal-backdrop"></div>
  <div class="vmodal__box">

    <div class="vmodal__header">
      <div class="vmodal__header-content">
        <div class="vmodal__header-title-row">
          <h2 class="vmodal__title" id="vmodal-title">Visitor Events</h2>
          <span class="vmodal__total-badge" id="vmodal-total-badge" hidden>0 events</span>
        </div>
        <div class="vmodal__meta-row">
          <span class="vmodal__meta-label">Visitor:</span>
          <span class="vmodal__meta-val mono" id="vmodal-visitor-id">—</span>
        </div>
      </div>
      <button class="vmodal__close" aria-label="Close events modal" id="vmodal-close-btn" type="button">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="currentColor" aria-hidden="true">
          <path d="M14.53 4.53L13.47 3.47 9 7.94 4.53 3.47 3.47 4.53 7.94 9l-4.47 4.47 1.06 1.06L9 10.06l4.47 4.47 1.06-1.06L10.06 9z"/>
        </svg>
      </button>
    </div>

    <div class="vmodal__body" id="vmodal-body">
      <!-- Table and states rendered here by JavaScript -->
    </div>

    <div class="vmodal__footer" id="vmodal-footer" hidden>
      <div class="vmodal__footer-info mono" id="vmodal-footer-info"></div>
      <nav class="vmodal__pagination" id="vmodal-pagination-nav" aria-label="Events pagination"></nav>
    </div>

  </div>
</div>

<script nonce="<?= e_attr($nonce ?? '') ?>">
(function () {
  'use strict';

  const modal        = document.getElementById('visitor-events-modal');
  const body         = document.getElementById('vmodal-body');
  const visitorIdEl  = document.getElementById('vmodal-visitor-id');
  const totalBadgeEl = document.getElementById('vmodal-total-badge');
  const closeBtn     = document.getElementById('vmodal-close-btn');
  const backdrop     = document.getElementById('vmodal-backdrop');
  const footer       = document.getElementById('vmodal-footer');
  const footerInfo   = document.getElementById('vmodal-footer-info');
  const paginationNav= document.getElementById('vmodal-pagination-nav');

  let currentVisitId       = null;
  let currentVisitorLabel  = '';
  let currentEventsUrl     = '';
  let currentPage          = 1;
  let activeAbortCtrl      = null;
  let lastActiveTrigger    = null;

  // ── Helper: decode HTML entities (e.g. &amp; → &) ──────────
  function decodeHtml(str) {
    var txt = document.createElement('textarea');
    txt.innerHTML = str;
    return txt.value;
  }

  // ── Basic HTML escaping ──────────────────────────────────────
  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ── Format timestamp into date & time components ────────────
  function formatDateTime(raw) {
    if (!raw) return { date: '—', time: '' };
    try {
      var d = new Date(raw.replace(' ', 'T'));
      if (isNaN(d.getTime())) return { date: raw, time: '' };
      return {
        date: d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
        time: d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit' })
      };
    } catch (e) {
      return { date: raw, time: '' };
    }
  }

  // ── Map event type to semantic badge style & readable label ─
  function getEventBadge(name) {
    switch (name) {
      case 'Lead':
        return { label: 'Lead', className: 'analytics__badge--lead' };
      case 'Contact':
        return { label: 'Contact', className: 'analytics__badge--lead' };
      case 'PageView':
        return { label: 'Page View', className: 'analytics__badge--page' };
      case 'ViewContent':
        return { label: 'View Content', className: 'analytics__badge--page' };
      case 'cta_click':
        return { label: 'Button Click', className: 'analytics__badge--cta' };
      case 'scrolldepth':
        return { label: 'Scroll Depth', className: 'analytics__badge--scroll' };
      case 'form_start':
        return { label: 'Form Start', className: 'analytics__badge--form' };
      case 'form_field_focus':
        return { label: 'Form Focus', className: 'analytics__badge--form' };
      case 'video_25':
        return { label: 'Video 25%', className: 'analytics__badge--video' };
      case 'video_50':
        return { label: 'Video 50%', className: 'analytics__badge--video' };
      case 'video_75':
        return { label: 'Video 75%', className: 'analytics__badge--video' };
      case 'Video_Complete':
        return { label: 'Video 100%', className: 'analytics__badge--video' };
      default:
        var friendly = name.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
        return { label: friendly, className: 'analytics__badge--other' };
    }
  }

  // ── Describe event data in friendly terms ────────────────────
  function describeEvent(name, data) {
    function val(key) {
      var v = data[key];
      return v != null && v !== '' ? decodeHtml(String(v)) : null;
    }

    switch (name) {
      case 'PageView':
        return { label: 'Loaded landing page', detail: null };

      case 'ViewContent':
        return {
          label: val('content_name') ? 'Viewed section: ' + val('content_name') : 'Viewed page section',
          detail: null
        };

      case 'scrolldepth': {
        var pct = val('percent');
        var depth = val('depth');
        return {
          label: pct ? 'Scrolled ' + pct + '% down the page' : 'Scrolled down the page',
          detail: depth ? 'Milestone: ' + depth + '% depth marker' : null
        };
      }

      case 'video_25':
        return { label: 'Watched 25% of the video', detail: null };
      case 'video_50':
        return { label: 'Watched 50% of the video', detail: null };
      case 'video_75':
        return { label: 'Watched 75% of the video', detail: null };
      case 'Video_Complete':
        return { label: 'Finished watching video', detail: null };

      case 'form_start':
        return { label: 'Started filling out inquiry form', detail: null };
      case 'form_field_focus':
        return { label: 'Interacted with inquiry field', detail: null };

      case 'cta_click': {
        var btnText = val('cta_text') || val('text') || val('label') || val('button');
        var loc     = val('location');
        var href    = val('cta_href');
        var ctaId   = val('cta_id');

        var locName = '';
        if (loc === 'hero') locName = 'Hero section (top of page)';
        else if (loc === 'navbar') locName = 'Navigation bar';
        else if (loc === 'problems') locName = 'Why Us / Problems section';
        else if (loc === 'final') locName = 'Bottom CTA section';
        else if (loc === 'modal') locName = 'Quote modal';
        else if (loc && loc !== 'body') locName = loc;

        var descParts = [];
        if (btnText) descParts.push('Button: “' + btnText + '”');
        if (locName) descParts.push('Section: ' + locName);
        if (ctaId === 'submit-btn') {
          descParts.push('Action: Submitted inquiry form');
        } else if (href === '#lead-form') {
          descParts.push('Action: Scrolled to quote form');
        } else if (href && href !== '#') {
          descParts.push('Target: ' + href);
        }

        var detailText = descParts.length > 0
          ? descParts.join(' · ')
          : 'Clicked a call-to-action button to navigate or interact with the page.';

        return {
          label: btnText ? 'Clicked “' + btnText + '”' : 'Clicked call-to-action button',
          detail: detailText
        };
      }

      case 'Lead':
      case 'Contact':
        return { label: 'Submitted inquiry / quote request 🎉', detail: 'High-intent conversion recorded' };

      default:
        return {
          label: name.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }),
          detail: null
        };
    }
  }

  // ── Render skeleton loading state ───────────────────────────
  function renderSkeleton() {
    var html = '<div class="vmodal__table-wrap">'
      + '<table class="vmodal__table" aria-label="Loading events">'
      + '<thead><tr>'
      + '<th scope="col" class="vmodal__th-event">Events</th>'
      + '<th scope="col" class="vmodal__th-desc">Description</th>'
      + '<th scope="col" class="vmodal__th-time">Date and Time</th>'
      + '</tr></thead>'
      + '<tbody>';

    for (var i = 0; i < 5; i++) {
      html += '<tr class="vmodal__skeleton-row">'
        + '<td><span class="vmodal__skeleton-bar" style="width: ' + (75 + (i % 3) * 15) + 'px;"></span></td>'
        + '<td><span class="vmodal__skeleton-bar" style="width: ' + (55 + (i * 9) % 35) + '%;"></span></td>'
        + '<td><span class="vmodal__skeleton-bar" style="width: 125px;"></span></td>'
        + '</tr>';
    }

    html += '</tbody></table></div>';
    body.innerHTML = html;
  }

  // ── Render empty state ──────────────────────────────────────
  function renderEmpty() {
    body.innerHTML = '<div class="vmodal__state-box">'
      + '<div class="vmodal__state-icon">'
      + '<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
      + '<circle cx="12" cy="12" r="10"></circle>'
      + '<line x1="12" y1="8" x2="12" y2="12"></line>'
      + '<line x1="12" y1="16" x2="12.01" y2="16"></line>'
      + '</svg>'
      + '</div>'
      + '<h3 class="vmodal__state-title">No Activity Recorded</h3>'
      + '<p class="vmodal__state-sub">There is no tracking activity recorded for this visitor session.</p>'
      + '</div>';
    footer.hidden = true;
  }

  // ── Render error state ──────────────────────────────────────
  function renderError(message) {
    body.innerHTML = '<div class="vmodal__state-box">'
      + '<div class="vmodal__state-icon" style="color: var(--c-alert);">'
      + '<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
      + '<circle cx="12" cy="12" r="10"></circle>'
      + '<line x1="15" y1="9" x2="9" y2="15"></line>'
      + '<line x1="9" y1="9" x2="15" y2="15"></line>'
      + '</svg>'
      + '</div>'
      + '<h3 class="vmodal__state-title">Unable to Load Events</h3>'
      + '<p class="vmodal__state-sub">' + escHtml(message) + '</p>'
      + '<button type="button" class="analytics-btn analytics-btn--ghost vmodal__retry-btn" id="vmodal-retry-btn">Try Again</button>'
      + '</div>';

    footer.hidden = true;

    var retryBtn = document.getElementById('vmodal-retry-btn');
    if (retryBtn) {
      retryBtn.addEventListener('click', function () {
        fetchEvents(currentPage);
      });
    }
  }

  // ── Render table with events data ───────────────────────────
  function renderTable(events) {
    var html = '<div class="vmodal__table-wrap">'
      + '<table class="vmodal__table" id="vmodal-table" aria-label="Visitor events">'
      + '<thead><tr>'
      + '<th scope="col" class="vmodal__th-event">Events</th>'
      + '<th scope="col" class="vmodal__th-desc">Description</th>'
      + '<th scope="col" class="vmodal__th-time">Date and Time</th>'
      + '</tr></thead>'
      + '<tbody id="vmodal-table-rows">';

    events.forEach(function (ev) {
      var badge = getEventBadge(ev.event_name);
      var desc  = describeEvent(ev.event_name, ev.event_data || {});
      var time  = formatDateTime(ev.occurred_at);

      html += '<tr>'
        + '<td class="vmodal__td-event">'
        +   '<div class="vmodal__event-cell">'
        +     '<div class="vmodal__event-badge-wrap">'
        +       '<span class="analytics__badge ' + badge.className + '">' + escHtml(badge.label) + '</span>'
        +     '</div>'
        +     '<span class="vmodal__event-raw mono">' + escHtml(ev.event_name) + '</span>'
        +   '</div>'
        + '</td>'
        + '<td class="vmodal__td-desc">'
        +   '<div class="vmodal__desc-cell">'
        +     '<div class="vmodal__desc-main">' + escHtml(desc.label) + '</div>'
        +     (desc.detail ? '<div class="vmodal__desc-detail">' + escHtml(desc.detail) + '</div>' : '')
        +   '</div>'
        + '</td>'
        + '<td class="vmodal__td-time">'
        +   '<div class="vmodal__time-cell mono" title="' + escHtml(ev.occurred_at || '') + '">'
        +     '<span class="vmodal__time-date">' + escHtml(time.date) + '</span>'
        +     '<span class="vmodal__time-hour">' + escHtml(time.time) + '</span>'
        +   '</div>'
        + '</td>'
        + '</tr>';
    });

    html += '</tbody></table></div>';
    body.innerHTML = html;
  }

  // ── Render pagination controls ──────────────────────────────
  function renderPagination(data) {
    var page       = data.page || 1;
    var totalPages = data.total_pages || 1;
    var total      = data.total || 0;
    var perPage    = data.per_page || 5;

    if (total === 0) {
      footer.hidden = true;
      return;
    }

    footer.hidden = false;

    // Info string: Showing X–Y of Z events · Page A of B
    var start = (page - 1) * perPage + 1;
    var end   = Math.min(page * perPage, total);
    footerInfo.textContent = 'Showing ' + start + '–' + end + ' of ' + total + ' event' + (total !== 1 ? 's' : '') + ' · Page ' + page + ' of ' + totalPages;

    if (totalPages <= 1) {
      paginationNav.innerHTML = '';
      return;
    }

    var navHtml = '';

    // Prev button
    navHtml += '<button type="button" class="vmodal__page-btn" data-page="' + (page - 1) + '"'
      + (page <= 1 ? ' disabled aria-disabled="true"' : ' aria-label="Previous page"')
      + '>‹ Prev</button>';

    // Page buttons logic with ellipses for > 6 pages
    if (totalPages <= 6) {
      for (var p = 1; p <= totalPages; p++) {
        navHtml += buildPageBtn(p, page);
      }
    } else {
      navHtml += buildPageBtn(1, page);

      var startRange = Math.max(2, page - 1);
      var endRange   = Math.min(totalPages - 1, page + 1);

      if (startRange > 2) {
        navHtml += '<span class="vmodal__page-ellipsis" aria-hidden="true">…</span>';
      }

      for (var p = startRange; p <= endRange; p++) {
        navHtml += buildPageBtn(p, page);
      }

      if (endRange < totalPages - 1) {
        navHtml += '<span class="vmodal__page-ellipsis" aria-hidden="true">…</span>';
      }

      navHtml += buildPageBtn(totalPages, page);
    }

    // Next button
    navHtml += '<button type="button" class="vmodal__page-btn" data-page="' + (page + 1) + '"'
      + (page >= totalPages ? ' disabled aria-disabled="true"' : ' aria-label="Next page"')
      + '>Next ›</button>';

    paginationNav.innerHTML = navHtml;

    // Wire pagination button clicks
    paginationNav.querySelectorAll('.vmodal__page-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.disabled || btn.classList.contains('is-active')) return;
        var targetPage = parseInt(btn.dataset.page, 10);
        if (!isNaN(targetPage) && targetPage > 0 && targetPage !== currentPage) {
          fetchEvents(targetPage);
        }
      });
    });
  }

  function buildPageBtn(p, currentPage) {
    if (p === currentPage) {
      return '<button type="button" class="vmodal__page-btn is-active" aria-current="page" data-page="' + p + '">' + p + '</button>';
    }
    return '<button type="button" class="vmodal__page-btn" aria-label="Page ' + p + '" data-page="' + p + '">' + p + '</button>';
  }

  // ── Fetch events via AJAX with page parameter ───────────────
  function fetchEvents(page) {
    currentPage = page;

    // If modal already shows a table, apply loading state without jarring replacement
    var existingTable = document.getElementById('vmodal-table');
    if (existingTable) {
      existingTable.classList.add('vmodal__table-loading');
      paginationNav.querySelectorAll('.vmodal__page-btn').forEach(function (btn) {
        btn.setAttribute('disabled', 'disabled');
      });
    } else {
      renderSkeleton();
      footer.hidden = true;
    }

    if (activeAbortCtrl) {
      activeAbortCtrl.abort();
    }
    activeAbortCtrl = new AbortController();

    var fetchUrl = currentEventsUrl + (currentEventsUrl.indexOf('?') === -1 ? '?' : '&') + 'page=' + encodeURIComponent(page);

    fetch(fetchUrl, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      signal: activeAbortCtrl.signal
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.ok) {
          renderError(data.error || 'Could not load visitor events.');
          return;
        }

        var total = typeof data.total === 'number' ? data.total : (data.events ? data.events.length : 0);
        totalBadgeEl.textContent = total + ' event' + (total !== 1 ? 's' : '');
        totalBadgeEl.hidden = false;

        if (!data.events || data.events.length === 0) {
          renderEmpty();
          return;
        }

        renderTable(data.events);
        renderPagination(data);
      })
      .catch(function (err) {
        if (err.name === 'AbortError') return;
        renderError('Could not load activity. Please try again.');
      });
  }

  // ── Open modal ──────────────────────────────────────────────
  function openModal(visitId, visitorLabel, eventsUrl, triggerBtn) {
    currentVisitId       = visitId;
    currentVisitorLabel  = visitorLabel;
    currentEventsUrl     = eventsUrl;
    currentPage          = 1;
    lastActiveTrigger    = triggerBtn || null;

    visitorIdEl.textContent = visitorLabel;
    totalBadgeEl.hidden     = true;

    modal.removeAttribute('hidden');
    document.body.style.overflow = 'hidden';
    closeBtn.focus();

    fetchEvents(1);
  }

  function closeModal() {
    if (activeAbortCtrl) {
      activeAbortCtrl.abort();
    }
    modal.setAttribute('hidden', '');
    document.body.style.overflow = '';
    if (lastActiveTrigger && typeof lastActiveTrigger.focus === 'function') {
      lastActiveTrigger.focus();
    }
  }

  // ── Wire up visitor table buttons ───────────────────────────
  document.querySelectorAll('.visitor-events-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal(
        btn.dataset.visitId,
        btn.dataset.visitorLabel,
        btn.dataset.eventsUrl,
        btn
      );
    });
  });

  closeBtn.addEventListener('click', closeModal);
  backdrop.addEventListener('click', closeModal);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.hasAttribute('hidden')) closeModal();
  });
}());
</script>
<?php $this->end() ?>
