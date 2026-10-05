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
    $email = trim((string) ($v['inq_email'] ?? '')) ?: trim((string) ($v['lpv_email'] ?? ''));
    $phone = trim((string) ($v['inq_phone'] ?? '')) ?: trim((string) ($v['lpv_contact'] ?? ''));

    if ($fbc !== '') {
        // Show shortened FB Click ID — keep only the last segment for readability
        $parts = explode('.', $fbc);
        $short = count($parts) >= 4 ? end($parts) : $fbc;
        return ['label' => 'FB ID', 'value' => $short, 'title' => $fbc, 'type' => 'fb'];
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
  <div class="vmodal__backdrop"></div>
  <div class="vmodal__box">

    <div class="vmodal__header">
      <div>
        <h2 class="vmodal__title" id="vmodal-title">Visitor Events</h2>
        <p class="vmodal__sub" id="vmodal-sub">Loading…</p>
      </div>
      <button class="vmodal__close" aria-label="Close events panel" id="vmodal-close-btn">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="currentColor" aria-hidden="true">
          <path d="M14.53 4.53L13.47 3.47 9 7.94 4.53 3.47 3.47 4.53 7.94 9l-4.47 4.47 1.06 1.06L9 10.06l4.47 4.47 1.06-1.06L10.06 9z"/>
        </svg>
      </button>
    </div>

    <div class="vmodal__body" id="vmodal-body">
      <!-- Events list is rendered here by JavaScript -->
    </div>

  </div>
</div>

<script nonce="<?= e_attr($nonce ?? '') ?>">
(function () {
  'use strict';

  const modal   = document.getElementById('visitor-events-modal');
  const body    = document.getElementById('vmodal-body');
  const sub     = document.getElementById('vmodal-sub');
  const closeBtn = document.getElementById('vmodal-close-btn');

  // ── Helper: decode HTML entities (e.g. &amp; → &) ──────────
  function decodeHtml(str) {
    var txt = document.createElement('textarea');
    txt.innerHTML = str;
    return txt.value;
  }

  // ── Open modal and fetch events via AJAX ────────────────────
  function openModal(visitId, visitorLabel, eventsUrl) {
    sub.textContent = 'Visitor: ' + visitorLabel;
    body.innerHTML  = '<p class="vmodal__loading">Loading activity…</p>';
    modal.removeAttribute('hidden');
    document.body.style.overflow = 'hidden';
    closeBtn.focus();

    fetch(eventsUrl, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.ok || !data.events.length) {
          body.innerHTML = '<p class="vmodal__empty">No activity recorded for this visitor.</p>';
          return;
        }
        body.innerHTML = buildEventList(data.events);
      })
      .catch(function () {
        body.innerHTML = '<p class="vmodal__empty">Could not load activity. Please try again.</p>';
      });
  }

  function closeModal() {
    modal.setAttribute('hidden', '');
    document.body.style.overflow = '';
  }

  // ── Build the HTML list of events ───────────────────────────
  function buildEventList(events) {
    var html = '<ol class="vmodal__event-list">';

    events.forEach(function (ev) {
      var desc       = describeEvent(ev.event_name, ev.event_data || {});
      var dateStr    = formatDate(ev.occurred_at);
      var badgeClass = eventBadgeClass(ev.event_name);

      html += '<li class="vmodal__event">';
      html += '<div class="vmodal__event-header">';
      html += '<span class="analytics__badge ' + badgeClass + '">' + escHtml(desc.label) + '</span>';
      html += '<time class="vmodal__event-time">' + escHtml(dateStr) + '</time>';
      html += '</div>';
      if (desc.detail) {
        html += '<p class="vmodal__event-detail">' + escHtml(desc.detail) + '</p>';
      }
      html += '</li>';
    });

    html += '</ol>';
    return html;
  }

  /**
   * Translate a raw event name + data into a friendly label and optional detail line.
   * Returns { label, detail } — both plain strings, safe to escHtml().
   */
  function describeEvent(name, data) {
    // Decode any HTML entities that may be stored in the data values
    function val(key) {
      var v = data[key];
      return v != null && v !== '' ? decodeHtml(String(v)) : null;
    }

    switch (name) {

      // ── Page & content ──────────────────────────────────────
      case 'PageView':
        return { label: 'Opened the page', detail: null };

      case 'ViewContent':
        return {
          label:  'Viewed a section',
          detail: val('content_name') ? 'Section: ' + val('content_name') : null
        };

      // ── Scroll depth ────────────────────────────────────────
      case 'scrolldepth': {
        var pct = val('percent');
        return {
          label:  pct ? 'Scrolled ' + pct + '% down the page' : 'Scrolled the page',
          detail: null
        };
      }

      // ── Video milestones ────────────────────────────────────
      case 'video_25':
        return { label: 'Watched 25% of the video', detail: null };
      case 'video_50':
        return { label: 'Watched 50% of the video', detail: null };
      case 'video_75':
        return { label: 'Watched 75% of the video', detail: null };
      case 'Video_Complete':
        return { label: 'Finished watching the video', detail: null };

      // ── Form interactions ───────────────────────────────────
      case 'form_start':
        return { label: 'Started filling the inquiry form', detail: null };
      case 'form_field_focus':
        return { label: 'Interacted with the inquiry form', detail: null };

      // ── CTA clicks ──────────────────────────────────────────
      case 'cta_click': {
        var btnText = val('cta_text') || val('text') || val('label') || val('button');
        var loc     = val('location');
        var href    = val('cta_href');
        var ctaId   = val('cta_id');

        // Friendly section name
        var locName = '';
        if (loc === 'hero') locName = 'Hero section (top of page)';
        else if (loc === 'navbar') locName = 'Navigation bar';
        else if (loc === 'problems') locName = 'Why Us / Problems section';
        else if (loc === 'final') locName = 'Bottom CTA section';
        else if (loc === 'modal') locName = 'Quote modal';
        else if (loc && loc !== 'body') locName = loc;

        // Build clear description details for non-technical users
        var descParts = [];
        if (btnText) {
          descParts.push('Button: “' + btnText + '”');
        }
        if (locName) {
          descParts.push('Section: ' + locName);
        }
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
          label:  btnText ? 'Clicked “' + btnText + '”' : 'Clicked a button',
          detail: detailText
        };
      }

      // ── Conversion ──────────────────────────────────────────
      case 'Lead':
      case 'Contact':
        return { label: 'Submitted an inquiry 🎉', detail: null };

      // ── Unknown / fallback ──────────────────────────────────
      default:
        return {
          label:  name.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }),
          detail: null
        };
    }
  }

  // ── Format "YYYY-MM-DD HH:MM:SS" → "Month D, YYYY · H:MM AM/PM" ──
  function formatDate(raw) {
    if (!raw) return '—';
    try {
      var d = new Date(raw.replace(' ', 'T'));
      if (isNaN(d)) return raw;
      return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
        + ' · '
        + d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    } catch (e) {
      return raw;
    }
  }

  // ── Badge colour per event type ──────────────────────────────
  function eventBadgeClass(name) {
    if (name === 'Lead' || name === 'Contact')                  return 'analytics__badge--lead';
    if (name === 'PageView' || name === 'ViewContent')          return 'analytics__badge--page';
    if (name.startsWith('video') || name === 'Video_Complete')  return 'analytics__badge--video';
    if (name === 'form_start' || name === 'form_field_focus')   return 'analytics__badge--form';
    if (name === 'scrolldepth')                                 return 'analytics__badge--scroll';
    if (name === 'cta_click')                                   return 'analytics__badge--cta';
    return 'analytics__badge--other';
  }

  // ── Basic HTML escaping ──────────────────────────────────────
  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ── Wire up buttons ─────────────────────────────────────────
  document.querySelectorAll('.visitor-events-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal(
        btn.dataset.visitId,
        btn.dataset.visitorLabel,
        btn.dataset.eventsUrl
      );
    });
  });

  closeBtn.addEventListener('click', closeModal);
  modal.querySelector('.vmodal__backdrop').addEventListener('click', closeModal);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.hasAttribute('hidden')) closeModal();
  });
}());
</script>
<?php $this->end() ?>
