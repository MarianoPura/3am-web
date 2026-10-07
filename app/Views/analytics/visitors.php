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
 * @var list<array>   $visitors     Each row has: id, fbc, fbp, lpv_email, lpv_contact, inq_name, inq_email, inq_phone, event_count, visit_count, first_seen_at, last_seen_at
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
 * Extract only the last unique characters of a Facebook Click ID.
 * e.g. "IwZXh0bg..._aem_0Y1ScDuBzWHShQX1Z6tIcA" -> "0Y1ScDuBzWHShQX1Z6tIcA"
 */
function extractShortFbId(string $raw): string {
    $val = trim($raw);
    if ($val === '') {
        return '';
    }

    // Strip leading "fb.1.<timestamp>." or "fb.0.<timestamp>." if present
    if (preg_match('/^fb\.[01]\.\d+\.(.+)$/', $val, $m)) {
        $val = $m[1];
    }

    // Modern Meta Click ID: extract suffix after "_aem_"
    if (($pos = strrpos($val, '_aem_')) !== false) {
        return substr($val, $pos + 5);
    }

    // If it has dot notation, take the final segment
    if (str_contains($val, '.')) {
        $parts = explode('.', $val);
        $val = end($parts);
    }

    // If still very long, display the trailing 20 unique characters
    if (strlen($val) > 24) {
        return substr($val, -20);
    }

    return $val;
}

/**
 * Determine the best identifier label for a visitor row.
 * Priority: FB Click ID (unique short suffix) → inquiry email → inquiry phone → "Anonymous"
 */
function visitorIdentifier(array $v): array {
    $fbc   = trim((string) ($v['fbc'] ?? ''));
    $email = trim((string) ($v['inq_email'] ?? '')) ?: trim((string) ($v['lpv_email'] ?? ''));
    $phone = trim((string) ($v['inq_phone'] ?? '')) ?: trim((string) ($v['lpv_contact'] ?? ''));

    if ($fbc !== '') {
        $short = extractShortFbId($fbc);
        return [
            'label' => 'FB ID',
            'value' => $short,
            'title' => $fbc, // Full original raw ID preserved in hover tooltip
            'type'  => 'fb',
        ];
    }

    if ($email !== '') {
        return ['label' => 'Email', 'value' => $email, 'title' => $email, 'type' => 'email'];
    }

    if ($phone !== '') {
        return ['label' => 'Phone', 'value' => $phone, 'title' => $phone, 'type' => 'phone'];
    }

    return ['label' => 'Direct Visit', 'value' => 'Visitor #' . (int) ($v['id'] ?? 0), 'title' => 'This visitor did not arrive from a Facebook ad', 'type' => 'direct'];
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
                <th title="Total visit sessions recorded for this visitor">Visits</th>
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
                $visitCnt = max(1, (int) ($v['visit_count'] ?? 1));
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

                <!-- Visits count -->
                <td>
                  <span class="analytics__count-badge analytics__count-badge--visits"><?= $visitCnt ?> visit<?= $visitCnt !== 1 ? 's' : '' ?></span>
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

<?= $this->partial('analytics.partials.events_modal', ['nonce' => $nonce ?? '']) ?>
<?php $this->end() ?>
