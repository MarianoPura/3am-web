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
 * @var list<array>   $recentEvents
 */
$this->extend('analytics.layout');
$this->start('title'); echo 'Dashboard'; $this->end();

function analyticsEventBadgeClass(string $name): string {
    if ($name === 'Video_Complete' || str_starts_with($name, 'video')) return 'analytics__badge--video';
    if (str_starts_with($name, 'form')) return 'analytics__badge--form';
    if ($name === 'scrolldepth')        return 'analytics__badge--scroll';
    if ($name === 'cta_click')          return 'analytics__badge--cta';
    if (in_array($name, ['PageView', 'ViewContent', 'Lead', 'Contact', 'Purchase'], true)) return 'analytics__badge--lead';
    return 'analytics__badge--other';
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
$utmMax = max(1, (int) ($utmSources[0]['total']   ?? 1));

$this->start('content');
?>
<section class="analytics-page">
  <div class="analytics-shell">

    <!-- Page heading -->
    <div class="analytics-page__heading">
      <div>
        <p class="analytics-kicker">3AM Digital Media / Website Tracking</p>
        <h1>Analytics Dashboard</h1>
      </div>
      <p>Overview of all tracked visitor actions and website sessions.</p>
    </div>

    <!-- Stat cards -->
    <div class="analytics__metrics">
      <div class="analytics__metric">
        <p>Total Actions Tracked</p>
        <strong><?= number_format($totalEvents) ?></strong>
        <small>All time &mdash; every click, view &amp; interaction</small>
      </div>
      <div class="analytics__metric">
        <p>Total Visitors</p>
        <strong><?= number_format($totalVisits) ?></strong>
        <small>Unique browsing sessions recorded</small>
      </div>
      <div class="analytics__metric analytics__metric--feature">
        <p>Actions Today</p>
        <strong><?= number_format($todayEvents) ?></strong>
        <small>Since midnight &mdash; Manila time</small>
      </div>
      <div class="analytics__metric">
        <p>Visitors Today</p>
        <strong><?= number_format($todayVisits) ?></strong>
        <small>New sessions started today</small>
      </div>
    </div>

    <!-- 14-day charts row -->
    <div class="analytics__two">
      <!-- Events over 14 days -->
      <div class="analytics__panel">
        <div class="analytics__panel-heading">
          <p class="analytics-kicker" style="margin:0">Actions Tracked</p>
          <h2>Last 14 Days</h2>
        </div>
        <div class="analytics__chart-bars" role="img" aria-label="Tracked actions bar chart, last 14 days">
          <?php foreach ($eventsChart as $day => $count): ?>
          <div title="<?= e(date('M j', strtotime($day))) ?>: <?= (int)$count ?> actions">
            <span style="height:<?= round(($count / $maxEvt) * 100) ?>%"></span>
            <small><?= e(date('M j', strtotime($day))) ?></small>
          </div>
          <?php endforeach ?>
        </div>
        <p class="analytics__chart-caption">Each bar represents one day. Hover for exact count.</p>
      </div>

      <!-- Sessions over 14 days -->
      <div class="analytics__panel">
        <div class="analytics__panel-heading">
          <p class="analytics-kicker" style="margin:0">Visitor Sessions</p>
          <h2>Last 14 Days</h2>
        </div>
        <div class="analytics__chart-bars" role="img" aria-label="Visitor sessions bar chart, last 14 days">
          <?php foreach ($visitsChart as $day => $count): ?>
          <div title="<?= e(date('M j', strtotime($day))) ?>: <?= (int)$count ?> visitors">
            <span style="height:<?= round(($count / $maxVis) * 100) ?>%;background:var(--c-signal)"></span>
            <small><?= e(date('M j', strtotime($day))) ?></small>
          </div>
          <?php endforeach ?>
        </div>
        <p class="analytics__chart-caption">Each bar represents one day. Hover for exact count.</p>
      </div>
    </div>

    <!-- Events by type + UTM sources -->
    <div class="analytics__two" style="margin-top:1rem">
      <!-- Events by type -->
      <div class="analytics__panel">
        <div class="analytics__panel-heading">
          <h2>Actions by Type</h2>
        </div>
        <?php if (empty($eventsByType)): ?>
          <p class="analytics__empty">No events recorded yet.</p>
        <?php else: ?>
          <div class="analytics__rank-bars">
            <?php foreach ($eventsByType as $et): ?>
            <div>
              <span class="analytics__badge <?= e(analyticsEventBadgeClass($et['event_name'])) ?>" title="Raw: <?= e_attr($et['event_name']) ?>">
                <?php
                  // Inline label helper (avoids duplicate function if dashboard loaded alone)
                  $evtLbl = match($et['event_name']) {
                    'PageView'         => 'Page View',
                    'ViewContent'      => 'Content Viewed',
                    'Lead'             => 'Lead Captured',
                    'Contact'          => 'Contact Submitted',
                    'Purchase'         => 'Purchase',
                    'form_start'       => 'Form Started',
                    'form_field_focus' => 'Form Field Focused',
                    'scrolldepth'      => 'Scroll Depth',
                    'cta_click'        => 'Button Clicked',
                    'video_25'         => 'Video 25%',
                    'video_50'         => 'Video 50%',
                    'video_75'         => 'Video 75%',
                    'Video_Complete'   => 'Video Complete',
                    default            => ucfirst(str_replace(['_','-'],' ',$et['event_name'])),
                  };
                  echo e($evtLbl);
                ?>
              </span>
              <div><i style="width:<?= round(((int)$et['total'] / $etMax) * 100) ?>%"></i></div>
              <strong><?= number_format((int)$et['total']) ?></strong>
            </div>
            <?php endforeach ?>
          </div>
        <?php endif ?>
      </div>

      <!-- UTM source breakdown -->
      <div class="analytics__panel">
        <div class="analytics__panel-heading">
          <h2>Traffic Sources</h2>
        </div>
        <?php if (empty($utmSources)): ?>
          <p class="analytics__empty">No traffic source data recorded yet.</p>
        <?php else: ?>
          <div class="analytics__rank-bars">
            <?php foreach ($utmSources as $utm): ?>
            <div>
              <span style="font-size:.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e((string)($utm['source'] ?? '—')) ?></span>
              <div><i style="width:<?= round(((int)$utm['total'] / $utmMax) * 100) ?>%;background:var(--c-signal)"></i></div>
              <strong><?= number_format((int)$utm['total']) ?></strong>
            </div>
            <?php endforeach ?>
          </div>
        <?php endif ?>
      </div>
    </div>

    <!-- Recent events -->
    <div class="analytics__panel" style="margin-top:1rem">
      <div class="analytics__panel-heading">
        <div>
          <h2>Recent Activity</h2>
          <p class="analytics__panel-sub">The 10 most recent actions recorded on the website.</p>
        </div>
        <a href="<?= e_attr(url('analytics/events')) ?>">View all events &rarr;</a>
      </div>
      <?php if (empty($recentEvents)): ?>
        <p class="analytics__empty">No events recorded yet.</p>
      <?php else: ?>
        <div class="analytics__table-wrap">
          <table class="analytics__table">
            <thead>
              <tr>
                <th>Action / Event</th>
                <th>Triggered From</th>
                <th>Visitor IP</th>
                <th>Traffic Source</th>
                <th>Campaign</th>
                <th>Date &amp; Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentEvents as $ev): ?>
              <?php
                $rawName   = (string)($ev['event_name'] ?? '');
                $rawSource = (string)($ev['event_source'] ?? 'browser');
                $evtLbl = match($rawName) {
                  'PageView'         => 'Page View',
                  'ViewContent'      => 'Content Viewed',
                  'Lead'             => 'Lead Captured',
                  'Contact'          => 'Contact Submitted',
                  'Purchase'         => 'Purchase',
                  'form_start'       => 'Form Started',
                  'form_field_focus' => 'Form Field Focused',
                  'scrolldepth'      => 'Scroll Depth',
                  'cta_click'        => 'Button Clicked',
                  'video_25'         => 'Video 25%',
                  'video_50'         => 'Video 50%',
                  'video_75'         => 'Video 75%',
                  'Video_Complete'   => 'Video Complete',
                  default            => ucfirst(str_replace(['_','-'],' ',$rawName)),
                };
                $srcLbl = match(strtolower($rawSource)) {
                  'browser' => 'Website',
                  'server'  => 'Server',
                  'pixel'   => 'Meta Pixel',
                  default   => ucfirst($rawSource),
                };
              ?>
              <tr>
                <td>
                  <span class="analytics__badge <?= e(analyticsEventBadgeClass($rawName)) ?>" title="Raw: <?= e_attr($rawName) ?>">
                    <?= e($evtLbl) ?>
                  </span>
                </td>
                <td><?= e($srcLbl) ?></td>
                <td class="mono"><?= e((string)($ev['ip_address'] ?? '—')) ?></td>
                <td><?= e($ev['utm_source'] !== '' && $ev['utm_source'] !== null ? (string)$ev['utm_source'] : '—') ?></td>
                <td><?= e($ev['utm_campaign'] !== '' && $ev['utm_campaign'] !== null ? (string)$ev['utm_campaign'] : '—') ?></td>
                <td class="mono" style="white-space:nowrap"><?= e((string)($ev['occurred_at'] ?? '—')) ?></td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php endif ?>
    </div>

  </div>
</section>
<?php $this->end() ?>
