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
        <p class="analytics-kicker">3AM Digital Media / Meta Pixel</p>
        <h1>Dashboard</h1>
      </div>
      <p>Overview of all tracked events and visitor sessions.</p>
    </div>

    <!-- Stat cards -->
    <div class="analytics__metrics">
      <div class="analytics__metric">
        <p>Total Events</p>
        <strong><?= number_format($totalEvents) ?></strong>
        <small>All time</small>
      </div>
      <div class="analytics__metric">
        <p>Total Sessions</p>
        <strong><?= number_format($totalVisits) ?></strong>
        <small>Unique visits recorded</small>
      </div>
      <div class="analytics__metric analytics__metric--feature">
        <p>Today's Events</p>
        <strong><?= number_format($todayEvents) ?></strong>
        <small>Since midnight (Manila time)</small>
      </div>
      <div class="analytics__metric">
        <p>Today's Sessions</p>
        <strong><?= number_format($todayVisits) ?></strong>
        <small>New visits today</small>
      </div>
    </div>

    <!-- 14-day charts row -->
    <div class="analytics__two">
      <!-- Events over 14 days -->
      <div class="analytics__panel">
        <div class="analytics__panel-heading">
          <p class="analytics-kicker" style="margin:0">Events</p>
          <h2>Last 14 Days</h2>
        </div>
        <div class="analytics__chart-bars" role="img" aria-label="Events bar chart, last 14 days">
          <?php foreach ($eventsChart as $day => $count): ?>
          <div title="<?= e(date('M j', strtotime($day))) ?>: <?= (int)$count ?> events">
            <span style="height:<?= round(($count / $maxEvt) * 100) ?>%"></span>
            <small><?= e(date('M j', strtotime($day))) ?></small>
          </div>
          <?php endforeach ?>
        </div>
        <p class="analytics__chart-caption">Each bar = one calendar day</p>
      </div>

      <!-- Sessions over 14 days -->
      <div class="analytics__panel">
        <div class="analytics__panel-heading">
          <p class="analytics-kicker" style="margin:0">Sessions</p>
          <h2>Last 14 Days</h2>
        </div>
        <div class="analytics__chart-bars" role="img" aria-label="Sessions bar chart, last 14 days">
          <?php foreach ($visitsChart as $day => $count): ?>
          <div title="<?= e(date('M j', strtotime($day))) ?>: <?= (int)$count ?> sessions">
            <span style="height:<?= round(($count / $maxVis) * 100) ?>%;background:var(--c-signal)"></span>
            <small><?= e(date('M j', strtotime($day))) ?></small>
          </div>
          <?php endforeach ?>
        </div>
        <p class="analytics__chart-caption">Each bar = one calendar day</p>
      </div>
    </div>

    <!-- Events by type + UTM sources -->
    <div class="analytics__two" style="margin-top:1rem">
      <!-- Events by type -->
      <div class="analytics__panel">
        <div class="analytics__panel-heading">
          <h2>Events by Type</h2>
        </div>
        <?php if (empty($eventsByType)): ?>
          <p class="analytics__empty">No events recorded yet.</p>
        <?php else: ?>
          <div class="analytics__rank-bars">
            <?php foreach ($eventsByType as $et): ?>
            <div>
              <span class="analytics__badge <?= e(analyticsEventBadgeClass($et['event_name'])) ?>"><?= e($et['event_name']) ?></span>
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
          <p class="analytics__empty">No UTM data recorded yet.</p>
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
        <h2>Recent Events</h2>
        <a href="<?= e_attr(url('analytics/events')) ?>">View all events →</a>
      </div>
      <?php if (empty($recentEvents)): ?>
        <p class="analytics__empty">No events recorded yet.</p>
      <?php else: ?>
        <div class="analytics__table-wrap">
          <table class="analytics__table">
            <thead>
              <tr>
                <th>Event</th>
                <th>Source</th>
                <th>IP Address</th>
                <th>UTM Source</th>
                <th>Campaign</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentEvents as $ev): ?>
              <tr>
                <td><span class="analytics__badge <?= e(analyticsEventBadgeClass($ev['event_name'])) ?>"><?= e($ev['event_name']) ?></span></td>
                <td class="mono"><?= e((string)($ev['event_source'] ?? 'browser')) ?></td>
                <td class="mono"><?= e((string)($ev['ip_address'] ?? '—')) ?></td>
                <td><?= e((string)($ev['utm_source'] ?? '—')) ?></td>
                <td><?= e((string)($ev['utm_campaign'] ?? '—')) ?></td>
                <td class="mono" style="white-space:nowrap"><?= e(substr((string)($ev['occurred_at'] ?? ''), 0, 16)) ?></td>
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
