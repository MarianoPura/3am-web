<?php
/**
 * Analytics — Events list.
 *
 * @var App\Core\View $this
 * @var array         $user
 * @var list<array>   $events
 * @var list<array>   $eventNames
 * @var string        $filterEvent
 * @var string        $filterDate
 * @var int           $page
 * @var int           $totalPages
 * @var int           $totalCount
 */
$this->extend('analytics.layout');
$this->start('title'); echo 'Events'; $this->end();

/** Human-readable label for a raw event name. */
function analyticsEvtLabel(string $name): string {
    return match($name) {
        'PageView'         => 'Page View',
        'ViewContent'      => 'Content Viewed',
        'Lead'             => 'Lead Captured',
        'Contact'          => 'Contact Form Submitted',
        'Purchase'         => 'Purchase Completed',
        'form_start'       => 'Form Started',
        'form_field_focus' => 'Form Field Focused',
        'scrolldepth'      => 'Scroll Depth Reached',
        'cta_click'        => 'Button Clicked',
        'video_25'         => 'Video — 25% Watched',
        'video_50'         => 'Video — 50% Watched',
        'video_75'         => 'Video — 75% Watched',
        'Video_Complete'   => 'Video Fully Watched',
        default            => ucfirst(str_replace(['_', '-'], ' ', $name)),
    };
}

/** Human-readable label for an event trigger source. */
function analyticsSourceLabel(string $src): string {
    return match(strtolower(trim($src))) {
        'browser' => 'Website (Browser)',
        'server'  => 'Server',
        'pixel'   => 'Meta Pixel',
        'api'     => 'API',
        default   => ucfirst($src),
    };
}

/** Human-readable label for an event_data JSON key. */
function analyticsDataKey(string $key): string {
    return match(strtolower($key)) {
        'depth'        => 'Scroll Depth',
        'percent'      => 'Percentage',
        'field'        => 'Field',
        'field_name'   => 'Field Name',
        'duration'     => 'Duration (s)',
        'page'         => 'Page',
        'url'          => 'URL',
        'label'        => 'Label',
        'value'        => 'Value',
        'title'        => 'Page Title',
        'content_name' => 'Content Name',
        'button'       => 'Button',
        'text'         => 'Button Text',
        default        => ucfirst(str_replace(['_', '-'], ' ', $key)),
    };
}

/** Badge CSS class for an event type. */
function analyticsEvtBadge(string $name): string {
    if ($name === 'Video_Complete' || str_starts_with($name, 'video')) return 'analytics__badge--video';
    if (str_starts_with($name, 'form')) return 'analytics__badge--form';
    if ($name === 'scrolldepth')        return 'analytics__badge--scroll';
    if ($name === 'cta_click')          return 'analytics__badge--cta';
    if (in_array($name, ['PageView', 'ViewContent', 'Lead', 'Contact', 'Purchase'], true)) return 'analytics__badge--lead';
    return 'analytics__badge--other';
}

function analyticsPageLink(int $pg, string $fe, string $fd): string {
    $q = array_filter(['event' => $fe, 'date' => $fd, 'page' => $pg > 1 ? (string)$pg : '']);
    return url('analytics/events') . ($q ? '?' . http_build_query($q) : '');
}

$this->start('content');
?>
<section class="analytics-page">
  <div class="analytics-shell">

    <div class="analytics-page__heading">
      <div>
        <p class="analytics-kicker">3AM Digital Media / Website Tracking</p>
        <h1>Tracked Events</h1>
      </div>
      <p><?= number_format($totalCount) ?> total actions recorded</p>
    </div>

    <!-- Filters -->
    <form method="GET" action="<?= e_attr(url('analytics/events')) ?>">
      <div class="analytics__filter">
        <label>
          Filter by Action Type
          <select name="event">
            <option value="">All actions</option>
            <?php foreach ($eventNames as $en): ?>
            <option value="<?= e_attr($en['event_name']) ?>"<?= $filterEvent === $en['event_name'] ? ' selected' : '' ?>><?= e(analyticsEvtLabel($en['event_name'])) ?></option>
            <?php endforeach ?>
          </select>
        </label>
        <label>
          Filter by Date
          <input type="date" name="date" value="<?= e_attr($filterDate) ?>" max="<?= date('Y-m-d') ?>">
        </label>
        <button type="submit" class="analytics-btn analytics-btn--primary" style="align-self:end">Apply Filter</button>
        <?php if ($filterEvent !== '' || $filterDate !== ''): ?>
        <a href="<?= e_attr(url('analytics/events')) ?>" class="analytics-btn analytics-btn--ghost" style="align-self:end">Clear Filter</a>
        <?php endif ?>
      </div>
    </form>

    <!-- Table panel -->
    <div class="analytics__panel">
      <div class="analytics__panel-heading">
        <div>
          <h2>Event Log</h2>
          <p class="analytics__panel-sub">Every visitor interaction captured on the website — page views, form activity, video engagement, and button clicks.</p>
        </div>
      </div>

      <?php if (empty($events)): ?>
        <p class="analytics__empty">No events found for the selected filters. Try adjusting the date or action type.</p>
      <?php else: ?>
        <div class="analytics__table-wrap">
          <table class="analytics__table">
            <thead>
              <tr>
                <th title="Record ID">#</th>
                <th>Action / Event</th>
                <th>Triggered From</th>
                <th>Visitor Session</th>
                <th>Visitor IP</th>
                <th>Traffic Source</th>
                <th>Campaign</th>
                <th>Additional Details</th>
                <th>Date &amp; Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($events as $ev): ?>
              <?php
                $rawName   = (string)($ev['event_name'] ?? '');
                $rawSource = (string)($ev['event_source'] ?? 'browser');
                $sessionId = (string)($ev['session_id'] ?? '');
              ?>
              <tr>
                <td class="mono"><?= (int)$ev['id'] ?></td>
                <td>
                  <span class="analytics__badge <?= e(analyticsEvtBadge($rawName)) ?>" title="Raw event name: <?= e_attr($rawName) ?>">
                    <?= e(analyticsEvtLabel($rawName)) ?>
                  </span>
                </td>
                <td><?= e(analyticsSourceLabel($rawSource)) ?></td>
                <td class="mono" style="white-space:nowrap;font-size:.72rem" title="Full session ID: <?= e_attr($sessionId) ?>">
                  <?php if ($sessionId !== ''): ?>
                    <span style="opacity:.5">…</span><?= e(substr($sessionId, -10)) ?>
                  <?php else: ?>
                    <span class="analytics__empty-cell">—</span>
                  <?php endif ?>
                </td>
                <td class="mono" style="white-space:nowrap"><?= e((string)($ev['ip_address'] ?? '—')) ?></td>
                <td style="white-space:nowrap"><?= e($ev['utm_source'] !== '' && $ev['utm_source'] !== null ? (string)$ev['utm_source'] : '—') ?></td>
                <td style="white-space:nowrap"><?= e($ev['utm_campaign'] !== '' && $ev['utm_campaign'] !== null ? (string)$ev['utm_campaign'] : '—') ?></td>
                <td>
                  <?php
                    $rawData = $ev['event_data'] ?? null;
                    if ($rawData !== null && $rawData !== '') {
                        $decoded = json_decode((string)$rawData, true);
                        if (is_array($decoded) && !empty($decoded)) {
                            $parts = [];
                            foreach ($decoded as $k => $v) {
                                $parts[] = '<span class="analytics__data-key">' . e(analyticsDataKey((string)$k)) . '</span>'
                                         . '<span class="analytics__data-val">' . e((string)$v) . '</span>';
                            }
                            echo '<span class="analytics__data-pairs">' . implode('', $parts) . '</span>';
                        } else {
                            echo '<span class="analytics__empty-cell">—</span>';
                        }
                    } else {
                        echo '<span class="analytics__empty-cell">—</span>';
                    }
                  ?>
                </td>
                <td class="mono" style="white-space:nowrap"><?= e((string)($ev['occurred_at'] ?? '—')) ?></td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="analytics__pagination">
          <span class="analytics__pagination-info"><?= number_format($totalCount) ?> actions &middot; page <?= $page ?> of <?= $totalPages ?></span>
          <?php if ($page > 1): ?>
            <a href="<?= e_attr(analyticsPageLink($page - 1, $filterEvent, $filterDate)) ?>">‹ Prev</a>
          <?php endif ?>
          <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <?php if ($p === $page): ?>
              <span class="is-active"><?= $p ?></span>
            <?php else: ?>
              <a href="<?= e_attr(analyticsPageLink($p, $filterEvent, $filterDate)) ?>"><?= $p ?></a>
            <?php endif ?>
          <?php endfor ?>
          <?php if ($page < $totalPages): ?>
            <a href="<?= e_attr(analyticsPageLink($page + 1, $filterEvent, $filterDate)) ?>">Next ›</a>
          <?php endif ?>
        </div>
      <?php endif ?>
    </div>

  </div>
</section>
<?php $this->end() ?>
