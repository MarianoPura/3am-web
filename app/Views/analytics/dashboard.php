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

/**
 * Format a datetime string using "F j, Y" format, e.g. "October 7, 2026 · 3:15 PM"
 */
function formatDashboardDateTime(string $datetime): string {
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
 * Map raw event name and parameters into human-understandable labels & explanations.
 * Tailored for non-developer and non-technical business users.
 */
function humanizeEvent(string $name, array|string $eventData = []): array {
    $data = is_array($eventData) ? $eventData : (json_decode((string) $eventData, true) ?: []);

    $val = function(string $key) use ($data): ?string {
        $v = $data[$key] ?? null;
        return ($v !== null && $v !== '') ? trim((string) $v) : null;
    };

    switch ($name) {
        case 'PageView':
            return [
                'badge'       => 'Page View',
                'badgeClass'  => 'analytics__badge--lead',
                'title'       => 'Visited Landing Page',
                'description' => 'Visitor arrived and loaded the website',
            ];

        case 'ViewContent':
            $content = $val('content_name') ?? 'Services Section';
            return [
                'badge'       => 'View Content',
                'badgeClass'  => 'analytics__badge--lead',
                'title'       => 'Explored Services',
                'description' => 'Viewed ' . $content,
            ];

        case 'Lead':
            $ref = $val('reference');
            return [
                'badge'       => 'Quote Form',
                'badgeClass'  => 'analytics__badge--lead',
                'title'       => 'Quote Request Submitted',
                'description' => $ref ? 'Submitted inquiry #' . $ref : 'Completed and sent the quote request form',
            ];

        case 'Contact':
            $ref = $val('reference');
            return [
                'badge'       => 'Contact Info',
                'badgeClass'  => 'analytics__badge--lead',
                'title'       => 'Contact Details Provided',
                'description' => $ref ? 'Sent contact info for inquiry #' . $ref : 'Provided contact information for callback',
            ];

        case 'form_start':
            return [
                'badge'       => 'Form Start',
                'badgeClass'  => 'analytics__badge--form',
                'title'       => 'Started Filling Form',
                'description' => 'Began typing into the quote request form',
            ];

        case 'form_field_focus':
            $field = $val('field_name');
            $fieldName = match($field) {
                'name'    => 'Name',
                'email'   => 'Email Address',
                'phone'   => 'Phone Number',
                'details' => 'Event Details',
                'service' => 'Service Option',
                default   => $field ? ucfirst($field) : 'form',
            };
            return [
                'badge'       => 'Form Activity',
                'badgeClass'  => 'analytics__badge--form',
                'title'       => 'Active on Form',
                'description' => 'Interacting with ' . $fieldName . ' field',
            ];

        case 'cta_click':
            $btnText = $val('cta_text') ?? $val('text') ?? $val('label') ?? 'Get a Quote';
            return [
                'badge'       => 'Button Click',
                'badgeClass'  => 'analytics__badge--cta',
                'title'       => 'Clicked Action Button',
                'description' => 'Clicked “' . $btnText . '” button',
            ];

        case 'scrolldepth':
            $pct = $val('percent') ?? $val('depth') ?? '50';
            return [
                'badge'       => 'Page Scroll',
                'badgeClass'  => 'analytics__badge--scroll',
                'title'       => 'Scrolled ' . $pct . '% of Page',
                'description' => 'Browsed through ' . $pct . '% of the page content',
            ];

        case 'video_25':
            return [
                'badge'       => 'Video 25%',
                'badgeClass'  => 'analytics__badge--video',
                'title'       => 'Watched Video (25%)',
                'description' => 'Watched initial 25% of the showreel preview',
            ];

        case 'video_50':
            return [
                'badge'       => 'Video 50%',
                'badgeClass'  => 'analytics__badge--video',
                'title'       => 'Watched Video (50%)',
                'description' => 'Watched half (50%) of the showreel preview',
            ];

        case 'video_75':
            return [
                'badge'       => 'Video 75%',
                'badgeClass'  => 'analytics__badge--video',
                'title'       => 'Watched Video (75%)',
                'description' => 'Watched 75% of the showreel preview',
            ];

        case 'Video_Complete':
            return [
                'badge'       => 'Video 100%',
                'badgeClass'  => 'analytics__badge--video',
                'title'       => 'Completed Video',
                'description' => 'Finished watching the full showreel to the end',
            ];

        default:
            $friendly = ucfirst(str_replace(['_', '-'], ' ', $name));
            return [
                'badge'       => $friendly,
                'badgeClass'  => 'analytics__badge--other',
                'title'       => $friendly,
                'description' => 'Tracked user action',
            ];
    }
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
          <div title="<?= e(date('F j, Y', strtotime($day))) ?>: <?= (int)$count ?> actions">
            <span style="height:<?= round(($count / $maxEvt) * 100) ?>%"></span>
            <small><?= e(date('M j', strtotime($day))) ?></small>
          </div>
          <?php endforeach ?>
        </div>
        <p class="analytics__chart-caption">Each bar represents one day. Hover for exact date and count.</p>
      </div>

      <!-- Sessions over 14 days -->
      <div class="analytics__panel">
        <div class="analytics__panel-heading">
          <p class="analytics-kicker" style="margin:0">Visitor Sessions</p>
          <h2>Last 14 Days</h2>
        </div>
        <div class="analytics__chart-bars" role="img" aria-label="Visitor sessions bar chart, last 14 days">
          <?php foreach ($visitsChart as $day => $count): ?>
          <div title="<?= e(date('F j, Y', strtotime($day))) ?>: <?= (int)$count ?> visitors">
            <span style="height:<?= round(($count / $maxVis) * 100) ?>%;background:var(--c-signal)"></span>
            <small><?= e(date('M j', strtotime($day))) ?></small>
          </div>
          <?php endforeach ?>
        </div>
        <p class="analytics__chart-caption">Each bar represents one day. Hover for exact date and count.</p>
      </div>
    </div>

    <!-- Events by type + Traffic sources -->
    <div class="analytics__two" style="margin-top:1rem">
      <!-- Events by type -->
      <div class="analytics__panel">
        <div class="analytics__panel-heading">
          <h2>Actions by Type</h2>
          <p class="analytics__panel-sub">Breakdown of interactions recorded on the website.</p>
        </div>
        <?php if (empty($eventsByType)): ?>
          <p class="analytics__empty">No events recorded yet.</p>
        <?php else: ?>
          <div class="analytics__rank-bars">
            <?php foreach ($eventsByType as $et): ?>
            <?php
              $h = humanizeEvent((string) $et['event_name']);
            ?>
            <div>
              <span class="analytics__badge <?= e($h['badgeClass']) ?>" title="Raw: <?= e_attr($et['event_name']) ?>">
                <?= e($h['title']) ?>
              </span>
              <div><i style="width:<?= round(((int)$et['total'] / $etMax) * 100) ?>%"></i></div>
              <strong><?= number_format((int)$et['total']) ?></strong>
            </div>
            <?php endforeach ?>
          </div>
        <?php endif ?>
      </div>

      <!-- Traffic source breakdown -->
      <div class="analytics__panel">
        <div class="analytics__panel-heading">
          <h2>Traffic Sources</h2>
          <p class="analytics__panel-sub">Where visitors are arriving from.</p>
        </div>
        <?php if (empty($utmSources)): ?>
          <p class="analytics__empty">No traffic source data recorded yet.</p>
        <?php else: ?>
          <div class="analytics__rank-bars">
            <?php foreach ($utmSources as $utm): ?>
            <div>
              <span style="font-size:.84rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                <?= e((string)($utm['source'] ?? '—')) ?>
              </span>
              <div><i style="width:<?= round(((int)$utm['total'] / $utmMax) * 100) ?>%;background:var(--c-signal)"></i></div>
              <strong><?= number_format((int)$utm['total']) ?></strong>
            </div>
            <?php endforeach ?>
          </div>
        <?php endif ?>
      </div>
    </div>

    <!-- Recent activity -->
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
                <th title="Visitor identifier matching the Visitors page">Visitor ID</th>
                <th>Action / Event</th>
                <th>Traffic Source</th>
                <th>Campaign</th>
                <th>Triggered From</th>
                <th>Date &amp; Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentEvents as $ev): ?>
              <?php
                $rawName       = (string)($ev['event_name'] ?? '');
                $rawSource     = (string)($ev['event_source'] ?? 'browser');
                $ident         = visitorIdentifier($ev);
                $h             = humanizeEvent($rawName, $ev['event_data'] ?? []);
                $trafficSource = \App\Controllers\Analytics\AnalyticsController::classifyTrafficSource(
                    $ev['fbc'] ?? null,
                    $ev['utm_source'] ?? null,
                    $ev['referrer'] ?? null
                );
                $campaign = trim((string)($ev['utm_campaign'] ?? ''));
                if (str_contains($campaign, '{{') || $campaign === '') {
                    $campaign = '—';
                }
                $srcLbl = match(strtolower($rawSource)) {
                  'browser' => 'Website',
                  'server'  => 'Server',
                  'pixel'   => 'Meta Pixel',
                  default   => ucfirst($rawSource),
                };
              ?>
              <tr>
                <!-- Visitor identifier cell matching visitors.php -->
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
                  <div class="visitor-id__meta mono">#<?= (int) ($ev['visit_id'] ?? 0) ?></div>
                  <?php if (!empty($ev['inq_name'])): ?>
                    <div class="visitor-name" style="font-size:.78rem;color:var(--c-text-muted);margin-top:2px"><?= e($ev['inq_name']) ?></div>
                  <?php endif ?>
                </td>

                <!-- Friendly Action / Activity description -->
                <td>
                  <div style="display:flex;align-items:center;gap:.45rem;flex-wrap:wrap">
                    <span class="analytics__badge <?= e($h['badgeClass']) ?>" title="Raw: <?= e_attr($rawName) ?>">
                      <?= e($h['badge']) ?>
                    </span>
                    <strong style="font-size:.84rem;color:var(--c-text)"><?= e($h['title']) ?></strong>
                  </div>
                  <p style="margin:.25rem 0 0;font-size:.78rem;color:var(--c-text-muted)"><?= e($h['description']) ?></p>
                </td>

                <!-- Traffic Source -->
                <td>
                  <span style="font-weight:600;font-size:.82rem"><?= e($trafficSource) ?></span>
                </td>

                <!-- Campaign -->
                <td>
                  <?= e($campaign) ?>
                </td>

                <!-- Triggered From -->
                <td><?= e($srcLbl) ?></td>

                <!-- Date & Time formatted as F j, Y · g:i A -->
                <td style="white-space:nowrap;font-size:.82rem">
                  <?= e(formatDashboardDateTime((string)($ev['occurred_at'] ?? '—'))) ?>
                </td>
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
