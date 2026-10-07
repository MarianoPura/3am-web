<?php
/**
 * Analytics dashboard.
 *
 * @var App\Core\View $this
 * @var array         $user
 * @var int           $totalEvents
 * @var int           $totalVisits
 * @var int           $todayEvents
 * @var int           $todayVisits
 * @var list<array>   $eventsByType
 * @var list<array>   $eventsOverTime
 * @var list<array>   $visitsOverTime
 * @var list<array>   $utmSources
 * @var list<array>   $recentVisitors
 */
$this->extend('analytics.layout');
$this->start('title'); echo 'Dashboard'; $this->end();

/**
 * Format a datetime string using "F j, Y · g:i A" format, e.g. "October 7, 2026 · 3:15 PM"
 * Consistent with visitors.php.
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
 * Consistent with visitors.php.
 */
function extractShortFbId(string $raw): string {
    $val = trim($raw);
    if ($val === '') {
        return '';
    }

    if (preg_match('/^fb\.[01]\.\d+\.(.+)$/', $val, $m)) {
        $val = $m[1];
    }

    if (($pos = strrpos($val, '_aem_')) !== false) {
        return substr($val, $pos + 5);
    }

    if (str_contains($val, '.')) {
        $parts = explode('.', $val);
        $val = end($parts);
    }

    if (strlen($val) > 24) {
        return substr($val, -20);
    }

    return $val;
}

/**
 * Determine the best identifier label for a visitor row.
 * Consistent with visitors.php.
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
            'title' => $fbc,
            'type'  => 'fb',
        ];
    }

    if ($email !== '') {
        return ['label' => 'Email', 'value' => $email, 'title' => $email, 'type' => 'email'];
    }

    if ($phone !== '') {
        return ['label' => 'Phone', 'value' => $phone, 'title' => $phone, 'type' => 'phone'];
    }

    return [
        'label' => 'Direct Visit',
        'value' => 'Visitor #' . (int) ($v['visit_id'] ?? $v['id'] ?? 0),
        'title' => 'This visitor did not arrive from a Facebook ad',
        'type'  => 'direct',
    ];
}

/**
 * Map raw event name into friendly labels tailored for non-developer business users.
 */
function humanizeEventName(string $name): array {
    return match ($name) {
        'PageView'         => ['badge' => 'Page View',      'class' => 'analytics__badge--lead',   'title' => 'Visited Landing Page'],
        'ViewContent'      => ['badge' => 'View Content',   'class' => 'analytics__badge--lead',   'title' => 'Explored Services'],
        'Lead'             => ['badge' => 'Quote Form',     'class' => 'analytics__badge--lead',   'title' => 'Quote Form Submitted'],
        'Contact'          => ['badge' => 'Contact Info',   'class' => 'analytics__badge--lead',   'title' => 'Contact Details Sent'],
        'form_start'       => ['badge' => 'Form Start',     'class' => 'analytics__badge--form',   'title' => 'Started Filling Form'],
        'form_field_focus' => ['badge' => 'Form Activity',  'class' => 'analytics__badge--form',   'title' => 'Interacting with Form'],
        'cta_click'        => ['badge' => 'Button Click',   'class' => 'analytics__badge--cta',    'title' => 'Clicked Call-to-Action'],
        'scrolldepth'      => ['badge' => 'Page Scroll',    'class' => 'analytics__badge--scroll', 'title' => 'Browsed Down Page'],
        'video_25'         => ['badge' => 'Video 25%',      'class' => 'analytics__badge--video',  'title' => 'Watched Video (25%)'],
        'video_50'         => ['badge' => 'Video 50%',      'class' => 'analytics__badge--video',  'title' => 'Watched Video (50%)'],
        'video_75'         => ['badge' => 'Video 75%',      'class' => 'analytics__badge--video',  'title' => 'Watched Video (75%)'],
        'Video_Complete'   => ['badge' => 'Video 100%',     'class' => 'analytics__badge--video',  'title' => 'Watched Full Video'],
        default            => ['badge' => ucfirst($name),    'class' => 'analytics__badge--other',  'title' => ucfirst(str_replace(['_', '-'], ' ', $name))],
    };
}

// Fill 14-day gaps
$days14 = [];
for ($i = 13; $i >= 0; $i--) {
    $days14[date('Y-m-d', strtotime("-{$i} days"))] = 0;
}
$eventsChart = $days14;
foreach ($eventsOverTime as $r) { $eventsChart[$r['day']] = (int) $r['total']; }
$visitsChart = $days14;
foreach ($visitsOverTime as $r) { $visitsChart[$r['day']] = (int) $r['total']; }
$maxEvt = max(1, max($eventsChart));
$maxVis = max(1, max($visitsChart));
$etMax  = max(1, (int) ($eventsByType[0]['total'] ?? 1));
$etSum  = max(1, (int) array_sum(array_column($eventsByType, 'total')));
$utmMax = max(1, (int) ($utmSources[0]['total']   ?? 1));
$utmSum = max(1, (int) array_sum(array_column($utmSources, 'total')));

$totalChartEvt = array_sum($eventsChart);
$totalChartVis = array_sum($visitsChart);

$this->start('content');
?>
<section class="analytics-page">
  <div class="analytics-shell">

    <!-- Page heading -->
    <div class="analytics-page__heading">
      <div>
        <p class="analytics-kicker">
          <span class="pulse-dot"></span>
          3AM Digital Media &bull; Website Tracking
        </p>
        <h1>Analytics Dashboard</h1>
        <p class="analytics-page__desc">Live activity tracking, visitor sessions, and marketing campaign attribution.</p>
      </div>
      <div class="analytics-page__header-actions">
        <div class="analytics-status-chip">
          <span class="pulse-dot"></span>
          <span>Live Tracking Active</span>
        </div>
        <a href="<?= e_attr(url('analytics/visitors')) ?>" class="analytics-btn analytics-btn--ghost">
          View All Visitors &rarr;
        </a>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="analytics__metrics">
      <!-- Card 1: Total Actions -->
      <div class="analytics__metric analytics__metric--tally">
        <div class="analytics__metric-header">
          <span class="analytics__metric-kicker">Total Actions Tracked</span>
          <span class="analytics__metric-chip">All Time</span>
        </div>
        <div class="analytics__metric-body">
          <strong><?= number_format($totalEvents) ?></strong>
        </div>
        <div class="analytics__metric-footer">
          <span class="analytics__metric-dot analytics__metric-dot--tally"></span>
          <span>Every click, view &amp; interaction</span>
        </div>
      </div>

      <!-- Card 2: Total Visitors -->
      <div class="analytics__metric analytics__metric--signal">
        <div class="analytics__metric-header">
          <span class="analytics__metric-kicker">Total Visitors</span>
          <span class="analytics__metric-chip">All Time</span>
        </div>
        <div class="analytics__metric-body">
          <strong><?= number_format($totalVisits) ?></strong>
        </div>
        <div class="analytics__metric-footer">
          <span class="analytics__metric-dot analytics__metric-dot--signal"></span>
          <span>Unique browsing sessions</span>
        </div>
      </div>

      <!-- Card 3: Actions Today -->
      <div class="analytics__metric analytics__metric--emerald">
        <div class="analytics__metric-header">
          <span class="analytics__metric-kicker">Actions Today</span>
          <span class="analytics__metric-chip analytics__metric-chip--active">Today</span>
        </div>
        <div class="analytics__metric-body">
          <strong><?= number_format($todayEvents) ?></strong>
        </div>
        <div class="analytics__metric-footer">
          <span class="analytics__metric-dot analytics__metric-dot--emerald"></span>
          <span>Since midnight (Manila time)</span>
        </div>
      </div>

      <!-- Card 4: Visitors Today -->
      <div class="analytics__metric analytics__metric--royal">
        <div class="analytics__metric-header">
          <span class="analytics__metric-kicker">Visitors Today</span>
          <span class="analytics__metric-chip analytics__metric-chip--active">Today</span>
        </div>
        <div class="analytics__metric-body">
          <strong><?= number_format($todayVisits) ?></strong>
        </div>
        <div class="analytics__metric-footer">
          <span class="analytics__metric-dot analytics__metric-dot--royal"></span>
          <span>New sessions started today</span>
        </div>
      </div>
    </div>

    <!-- 14-day performance trends: 2 equal-height charts side-by-side -->
    <div class="analytics__charts-grid">

      <!-- Chart 1: Actions Tracked -->
      <div class="analytics__panel" style="border-top:3px solid var(--c-tally)">
        <div class="analytics__panel-heading">
          <div>
            <span class="analytics-kicker" style="margin:0 0 .25rem">Interaction Volume</span>
            <h2>Actions Tracked (14 Days)</h2>
            <p class="analytics__panel-sub">Daily tracked clicks, scrolls, video views &amp; form events</p>
          </div>
          <div class="analytics__chart-badge">
            <strong><?= number_format($totalChartEvt) ?></strong>
            <span>14-day actions</span>
          </div>
        </div>
        <div class="analytics__chart-bars" role="img" aria-label="Tracked actions bar chart, last 14 days">
          <?php foreach ($eventsChart as $day => $count): ?>
          <div title="<?= e(date('F j, Y', strtotime($day))) ?>: <?= (int)$count ?> actions">
            <span style="height:<?= round(($count / $maxEvt) * 100) ?>%"></span>
            <small><?= e(date('M j', strtotime($day))) ?></small>
          </div>
          <?php endforeach ?>
        </div>
        <p class="analytics__chart-caption">Each bar represents one day. Hover for exact date and action count.</p>
      </div>

      <!-- Chart 2: Visitor Sessions -->
      <div class="analytics__panel" style="border-top:3px solid #00b4d8">
        <div class="analytics__panel-heading">
          <div>
            <span class="analytics-kicker" style="margin:0 0 .25rem">Traffic Volume</span>
            <h2>Visitor Sessions (14 Days)</h2>
            <p class="analytics__panel-sub">Daily unique browsing sessions arriving at the website</p>
          </div>
          <div class="analytics__chart-badge analytics__chart-badge--signal">
            <strong><?= number_format($totalChartVis) ?></strong>
            <span>14-day sessions</span>
          </div>
        </div>
        <div class="analytics__chart-bars" role="img" aria-label="Visitor sessions bar chart, last 14 days">
          <?php foreach ($visitsChart as $day => $count): ?>
          <div title="<?= e(date('F j, Y', strtotime($day))) ?>: <?= (int)$count ?> visitors">
            <span style="height:<?= round(($count / $maxVis) * 100) ?>%;background:var(--c-signal)"></span>
            <small><?= e(date('M j', strtotime($day))) ?></small>
          </div>
          <?php endforeach ?>
        </div>
        <p class="analytics__chart-caption">Each bar represents one day. Hover for exact date and visitor count.</p>
      </div>

    </div>

    <!-- Main Content Grid: Activity Table (Left) + Breakdowns (Right) -->
    <div class="analytics__content-grid">

      <!-- Left Column: Recent Activity (Visitor Sessions) -->
      <div class="analytics__panel analytics__activity-panel" style="border-top:3px solid var(--c-ink)">
        <div class="analytics__panel-heading">
          <div>
            <span class="analytics-kicker" style="margin:0 0 .25rem">Live Feed</span>
            <h2>Recent Activity</h2>
            <p class="analytics__panel-sub">Latest visitor sessions recorded on the website. Inquiries display contact names.</p>
          </div>
          <a href="<?= e_attr(url('analytics/visitors')) ?>" class="analytics-btn analytics-btn--ghost" style="padding:.45rem .8rem;font-size:.65rem;flex-shrink:0">
            View All Visitors &rarr;
          </a>
        </div>

        <?php if (empty($recentVisitors)): ?>
          <p class="analytics__empty">No visitor sessions recorded yet.</p>

        <?php else: ?>
          <div class="analytics__table-wrap">
            <table class="analytics__table visitors-table--dashboard">
              <thead>
                <tr>
                  <th title="Best available identifier for this visitor">Visitor ID</th>
                  <th>Name</th>
                  <th title="Latest recorded session for this visitor">Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentVisitors as $v):
                  $ident = visitorIdentifier($v);
                  $name  = trim((string) ($v['inq_name'] ?? ''));
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
                      <span class="analytics__empty-cell">&mdash;</span>
                    <?php endif ?>
                  </td>

                  <!-- Date (Last Visit) -->
                  <td class="visitor-date">
                    <?= formatVisitorDate((string) ($v['last_seen_at'] ?? '')) ?>
                  </td>

                </tr>
                <?php endforeach ?>
              </tbody>
            </table>
          </div>
        <?php endif ?>
      </div>

      <!-- Right Column: Attribution & Event Breakdown -->
      <div class="analytics__breakdowns-col">

        <!-- Traffic Sources -->
        <div class="analytics__panel" style="border-top:3px solid #00b4d8">
          <div class="analytics__panel-heading">
            <div>
              <span class="analytics-kicker" style="margin:0 0 .25rem">Attribution</span>
              <h2>Traffic Sources</h2>
              <p class="analytics__panel-sub">Where visitors are arriving from</p>
            </div>
            <span class="analytics__panel-stat"><?= number_format($totalVisits) ?> total</span>
          </div>

          <?php if (empty($utmSources)): ?>
            <p class="analytics__empty">No traffic source data recorded yet.</p>
          <?php else: ?>
            <div class="analytics__rank-list">
              <?php foreach ($utmSources as $utm):
                $srcTotal = (int) $utm['total'];
                $srcPct   = round(($srcTotal / $utmSum) * 100);
                $srcName  = (string) ($utm['source'] ?? 'Direct Visit');
                $isMeta   = str_contains(strtolower($srcName), 'meta') || str_contains(strtolower($srcName), 'facebook');
              ?>
              <div class="analytics__rank-item">
                <div class="analytics__rank-label">
                  <span class="analytics__source-dot <?= $isMeta ? 'analytics__source-dot--meta' : 'analytics__source-dot--direct' ?>"></span>
                  <span class="analytics__rank-name" title="<?= e_attr($srcName) ?>"><?= e($srcName) ?></span>
                </div>
                <div class="analytics__rank-track">
                  <i style="width:<?= $srcPct ?>%;background:var(--c-signal)"></i>
                </div>
                <div class="analytics__rank-val">
                  <strong><?= number_format($srcTotal) ?></strong>
                  <small><?= $srcPct ?>%</small>
                </div>
              </div>
              <?php endforeach ?>
            </div>
          <?php endif ?>
        </div>

        <!-- Actions by Type -->
        <div class="analytics__panel" style="border-top:3px solid var(--c-tally)">
          <div class="analytics__panel-heading">
            <div>
              <span class="analytics-kicker" style="margin:0 0 .25rem">Interactions</span>
              <h2>Actions by Type</h2>
              <p class="analytics__panel-sub">Breakdown of recorded interactions</p>
            </div>
            <span class="analytics__panel-stat"><?= number_format($totalEvents) ?> total</span>
          </div>

          <?php if (empty($eventsByType)): ?>
            <p class="analytics__empty">No events recorded yet.</p>
          <?php else: ?>
            <div class="analytics__rank-list">
              <?php foreach ($eventsByType as $et):
                $evtTotal = (int) $et['total'];
                $evtPct   = round(($evtTotal / $etSum) * 100);
                $h        = humanizeEventName((string) $et['event_name']);
              ?>
              <div class="analytics__rank-item">
                <div class="analytics__rank-label">
                  <span class="analytics__badge <?= e($h['class']) ?>" title="Raw event: <?= e_attr($et['event_name']) ?>">
                    <?= e($h['title']) ?>
                  </span>
                </div>
                <div class="analytics__rank-track">
                  <i style="width:<?= round(($evtTotal / $etMax) * 100) ?>%"></i>
                </div>
                <div class="analytics__rank-val">
                  <strong><?= number_format($evtTotal) ?></strong>
                  <small><?= $evtPct ?>%</small>
                </div>
              </div>
              <?php endforeach ?>
            </div>
          <?php endif ?>
        </div>

      </div>

    </div>

  </div>
</section>

<?= $this->partial('analytics.partials.events_modal', ['nonce' => $nonce ?? '']) ?>
<?php $this->end() ?>
