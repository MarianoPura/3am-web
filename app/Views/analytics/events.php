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
        <p class="analytics-kicker">3AM Digital Media / Meta Pixel</p>
        <h1>Events</h1>
      </div>
      <p><?= number_format($totalCount) ?> total events tracked</p>
    </div>

    <!-- Filters -->
    <form method="GET" action="<?= e_attr(url('analytics/events')) ?>">
      <div class="analytics__filter">
        <label>
          Event Type
          <select name="event">
            <option value="">All events</option>
            <?php foreach ($eventNames as $en): ?>
            <option value="<?= e_attr($en['event_name']) ?>"<?= $filterEvent === $en['event_name'] ? ' selected' : '' ?>><?= e($en['event_name']) ?></option>
            <?php endforeach ?>
          </select>
        </label>
        <label>
          Date
          <input type="date" name="date" value="<?= e_attr($filterDate) ?>" max="<?= date('Y-m-d') ?>">
        </label>
        <button type="submit" class="analytics-btn analytics-btn--primary" style="align-self:end">Apply</button>
        <?php if ($filterEvent !== '' || $filterDate !== ''): ?>
        <a href="<?= e_attr(url('analytics/events')) ?>" class="analytics-btn analytics-btn--ghost" style="align-self:end">Clear</a>
        <?php endif ?>
      </div>
    </form>

    <!-- Table panel -->
    <div class="analytics__panel">
      <div class="analytics__panel-heading">
        <h2>Event Records</h2>
      </div>

      <?php if (empty($events)): ?>
        <p class="analytics__empty">No events found matching your filters.</p>
      <?php else: ?>
        <div class="analytics__table-wrap">
          <table class="analytics__table">
            <thead>
              <tr>
                <th>#</th>
                <th>Event</th>
                <th>Source</th>
                <th>Session ID</th>
                <th>IP Address</th>
                <th>UTM Source</th>
                <th>Campaign</th>
                <th>Event Data</th>
                <th>Occurred At</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($events as $ev): ?>
              <tr>
                <td class="mono"><?= (int)$ev['id'] ?></td>
                <td><span class="analytics__badge <?= e(analyticsEvtBadge($ev['event_name'])) ?>"><?= e($ev['event_name']) ?></span></td>
                <td class="mono"><?= e((string)($ev['event_source'] ?? 'browser')) ?></td>
                <td class="mono"><?= e(substr((string)($ev['session_id'] ?? '—'), 0, 12)) ?>…</td>
                <td class="mono"><?= e((string)($ev['ip_address'] ?? '—')) ?></td>
                <td><?= e((string)($ev['utm_source'] ?? '—')) ?></td>
                <td><?= e((string)($ev['utm_campaign'] ?? '—')) ?></td>
                <td>
                  <?php
                    $rawData = $ev['event_data'] ?? null;
                    if ($rawData !== null && $rawData !== '') {
                        $decoded = json_decode((string)$rawData, true);
                        if (is_array($decoded) && !empty($decoded)) {
                            $parts = [];
                            foreach ($decoded as $k => $v) {
                                $parts[] = '<strong>' . e((string)$k) . '</strong>:' . e((string)$v);
                            }
                            echo '<span class="mono" style="font-size:.7rem;line-height:1.6">' . implode(' &nbsp; ', $parts) . '</span>';
                        }
                    }
                  ?>
                </td>
                <td class="mono" style="white-space:nowrap"><?= e(substr((string)($ev['occurred_at'] ?? ''), 0, 16)) ?></td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="analytics__pagination">
          <span class="analytics__pagination-info"><?= number_format($totalCount) ?> records &middot; page <?= $page ?> of <?= $totalPages ?></span>
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
      <?php endif ?>
    </div>

  </div>
</section>
<?php $this->end() ?>
