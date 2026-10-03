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
        <p class="analytics-kicker">3AM Digital Media / Website Tracking</p>
        <h1>Website Visitors</h1>
      </div>
      <p><?= number_format($totalCount) ?> unique visitor sessions recorded</p>
    </div>

    <div class="analytics__panel">
      <div class="analytics__panel-heading">
        <div>
          <h2>Visitor Sessions</h2>
          <p class="analytics__panel-sub">Each row represents one browsing session. A visitor may appear multiple times if they return to the website.</p>
        </div>
      </div>

      <?php if (empty($visitors)): ?>
        <p class="analytics__empty">No visitor sessions recorded yet.</p>
      <?php else: ?>
        <div class="analytics__table-wrap">
          <table class="analytics__table">
            <thead>
              <tr>
                <th title="Record ID">#</th>
                <th>Visitor IP</th>
                <th>Traffic Source <small style="font-weight:400;opacity:.6">(utm_source)</small></th>
                <th>Channel <small style="font-weight:400;opacity:.6">(utm_medium)</small></th>
                <th>Campaign <small style="font-weight:400;opacity:.6">(utm_campaign)</small></th>
                <th title="Facebook Browser ID — identifies the browser across visits">FB Browser ID <small style="font-weight:400;opacity:.6">(fbp)</small></th>
                <th title="Facebook Click ID — set when the visitor arrived via a Meta ad click">FB Ad Click ID <small style="font-weight:400;opacity:.6">(fbc)</small></th>
                <th>Actions</th>
                <th>First Visit</th>
                <th>Last Visit</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($visitors as $v): ?>
              <tr>
                <td class="mono"><?= (int)$v['id'] ?></td>
                <td class="mono" style="white-space:nowrap"><?= e((string)($v['ip_address'] ?? '—')) ?></td>
                <td style="white-space:nowrap"><?= e($v['utm_source'] !== '' && $v['utm_source'] !== null ? (string)$v['utm_source'] : '—') ?></td>
                <td style="white-space:nowrap"><?= e($v['utm_medium'] !== '' && $v['utm_medium'] !== null ? (string)$v['utm_medium'] : '—') ?></td>
                <td style="white-space:nowrap"><?= e($v['utm_campaign'] !== '' && $v['utm_campaign'] !== null ? (string)$v['utm_campaign'] : '—') ?></td>
                <td class="mono" style="white-space:nowrap;font-size:.72rem">
                  <?php $fbp = (string)($v['fbp'] ?? '');
                  echo $fbp !== '' ? e($fbp) : '<span class="analytics__empty-cell">—</span>' ?>
                </td>
                <td class="mono" style="white-space:nowrap;font-size:.72rem">
                  <?php $fbc = (string)($v['fbc'] ?? '');
                  echo $fbc !== '' ? e($fbc) : '<span class="analytics__empty-cell">—</span>' ?>
                </td>
                <td>
                  <?php $cnt = (int)($v['event_count'] ?? 0); ?>
                  <span class="analytics__count-badge"><?= $cnt ?> action<?= $cnt !== 1 ? 's' : '' ?></span>
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
