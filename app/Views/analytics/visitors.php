<?php
/**
 * Analytics — Visitors / Sessions list.
 *
 * @var App\Core\View $this
 * @var array         $user
 * @var list<array>   $visitors
 * @var int           $page
 * @var int           $totalPages
 * @var int           $totalCount
 */
$this->extend('analytics.layout');
$this->start('title'); echo 'Visitors'; $this->end();

function analyticsVisLink(int $pg): string {
    return url('analytics/visitors') . ($pg > 1 ? '?page=' . $pg : '');
}

$this->start('content');
?>
<section class="analytics-page">
  <div class="analytics-shell">

    <div class="analytics-page__heading">
      <div>
        <p class="analytics-kicker">3AM Digital Media / Meta Pixel</p>
        <h1>Visitors</h1>
      </div>
      <p><?= number_format($totalCount) ?> total sessions recorded</p>
    </div>

    <div class="analytics__panel">
      <div class="analytics__panel-heading">
        <h2>Session Records</h2>
      </div>

      <?php if (empty($visitors)): ?>
        <p class="analytics__empty">No sessions recorded yet.</p>
      <?php else: ?>
        <div class="analytics__table-wrap">
          <table class="analytics__table">
            <thead>
              <tr>
                <th>#</th>
                <th>IP Address</th>
                <th>UTM Source</th>
                <th>UTM Medium</th>
                <th>UTM Campaign</th>
                <th>fbp</th>
                <th>fbc</th>
                <th>Events</th>
                <th>First Seen</th>
                <th>Last Seen</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($visitors as $v): ?>
              <tr>
                <td class="mono"><?= (int)$v['id'] ?></td>
                <td class="mono" style="white-space:nowrap"><?= e((string)($v['ip_address'] ?? '—')) ?></td>
                <td style="white-space:nowrap"><?= e((string)($v['utm_source'] ?? '—')) ?></td>
                <td style="white-space:nowrap"><?= e((string)($v['utm_medium'] ?? '—')) ?></td>
                <td style="white-space:nowrap"><?= e((string)($v['utm_campaign'] ?? '—')) ?></td>
                <td class="mono" style="white-space:nowrap">
                  <?php $fbp = (string)($v['fbp'] ?? '');
                  echo $fbp !== '' ? e($fbp) : '—' ?>
                </td>
                <td class="mono" style="white-space:nowrap">
                  <?php $fbc = (string)($v['fbc'] ?? '');
                  echo $fbc !== '' ? e($fbc) : '—' ?>
                </td>
                <td>
                  <?php $cnt = (int)($v['event_count'] ?? 0); ?>
                  <span style="display:inline-block;padding:.25rem .5rem;border-top:2px solid var(--c-tally);background:var(--c-cloud);font:800 .8rem/1 var(--f-display);color:var(--c-ink)"><?= $cnt ?></span>
                </td>
                <td class="mono" style="white-space:nowrap"><?= e((string)($v['first_seen_at'] ?? '—')) ?></td>
                <td class="mono" style="white-space:nowrap"><?= e((string)($v['last_seen_at'] ?? '—')) ?></td>
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
<?php $this->end() ?>
