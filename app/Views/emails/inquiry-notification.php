<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no" />
  <meta name="color-scheme" content="light" />
  <meta name="supported-color-schemes" content="light" />
  <title><?= e(sprintf('[%s] %s inquiry — %s', $record['reference'], ucfirst((string) $record['form']), $record['name'])) ?></title>
  <style type="text/css">
    body, p, h1, h2, h3, table, td { margin: 0; padding: 0; }
    body {
      background-color: #F0F4F8;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      color: #152B45;
      margin: 0 !important;
      padding: 0 !important;
      width: 100% !important;
    }
    table { border-collapse: collapse; }
    @media only screen and (max-width: 620px) {
      .email-container { width: 100% !important; }
      .email-content, .email-header, .email-footer { padding: 20px !important; }
    }
  </style>
</head>
<body style="background-color: #F0F4F8; margin: 0; padding: 0;">

  <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all; font-size: 1px; color: #F0F4F8;">
    New website lead from <?= e($record['name']) ?> (Ref: <?= e($record['reference']) ?>).
  </div>

  <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #F0F4F8;">
    <tr>
      <td align="center" style="padding: 32px 12px;">

        <table role="presentation" class="email-container" border="0" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px; width: 100%; background-color: #FFFFFF; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(11, 22, 34, 0.07); border: 1px solid #D7E3EE;">

          <!-- Top Brand Accent Line -->
          <tr>
            <td height="4" style="background-color: #FFB300; line-height: 4px; font-size: 4px;">&nbsp;</td>
          </tr>

          <!-- Header -->
          <tr>
            <td class="email-header" style="background-color: #0B1622; padding: 26px 36px;">
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td>
                    <span style="font-family: 'Archivo', Arial, sans-serif; font-size: 20px; font-weight: 900; color: #FFFFFF; letter-spacing: 0.5px;">3AM</span>
                    <span style="font-family: 'JetBrains Mono', Consolas, monospace; font-size: 11px; font-weight: 700; color: #FFB300; letter-spacing: 2px; text-transform: uppercase; margin-left: 5px;">INTERNAL ALERT</span>
                  </td>
                  <td align="right">
                    <span style="display: inline-block; font-family: 'JetBrains Mono', Consolas, monospace; font-size: 10px; color: #FF4438; background-color: rgba(255, 68, 56, 0.15); border: 1px solid rgba(255, 68, 56, 0.3); padding: 4px 8px; border-radius: 3px; font-weight: 700;">&#9679; NEW LEAD</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Content -->
          <tr>
            <td class="email-content" style="padding: 32px 36px 28px 36px;">

              <h1 style="margin: 0 0 16px 0; font-family: 'Archivo', Arial, sans-serif; font-size: 20px; font-weight: 800; color: #152B45;">
                New inquiry received from website
              </h1>

              <!-- Reference Badge -->
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0B1622; border-radius: 6px; padding: 14px 20px; margin-bottom: 24px;">
                <tr>
                  <td>
                    <div style="font-family: 'JetBrains Mono', Consolas, monospace; font-size: 10px; color: #8296AC; text-transform: uppercase; letter-spacing: 1px;">REFERENCE CODE</div>
                    <div style="font-family: 'JetBrains Mono', Consolas, monospace; font-size: 20px; font-weight: 800; color: #FFB300; letter-spacing: 2px; margin-top: 2px;"><?= e($record['reference']) ?></div>
                  </td>
                  <td align="right">
                    <div style="font-family: 'JetBrains Mono', Consolas, monospace; font-size: 10px; color: #8296AC;">RECEIVED</div>
                    <div style="font-family: 'JetBrains Mono', Consolas, monospace; font-size: 12px; color: #FFFFFF; margin-top: 2px;"><?= e((string) $record['received']) ?></div>
                  </td>
                </tr>
              </table>

              <!-- Lead Facts Table -->
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border: 1px solid #D7E3EE; border-radius: 6px; overflow: hidden; margin-bottom: 22px;">
                <?php
                $facts = [
                    'Form'     => ucfirst((string) $record['form']),
                    'Service'  => $record['type'] ?: '(Not specified)',
                    'Client'   => $record['name'],
                    'Email'    => $record['email'],
                    'Phone'    => $record['phone'] ?: '(Not provided)',
                    'Company'  => $record['company'] ?: '(Not provided)',
                ];
                $i = 0;
                foreach ($facts as $label => $val):
                    $i++;
                    $bg = ($i % 2) === 1 ? '#FFFFFF' : '#F8FAFC';
                ?>
                  <tr style="background-color: <?= $bg ?>;">
                    <td width="120" style="padding: 10px 14px; font-family: 'JetBrains Mono', Consolas, monospace; font-size: 11px; font-weight: 700; color: #5A6B7F; text-transform: uppercase; border-bottom: 1px solid #E5EDF5;">
                      <?= e($label) ?>
                    </td>
                    <td style="padding: 10px 14px; font-family: 'Inter', Arial, sans-serif; font-size: 13px; font-weight: <?= in_array($label, ['Client', 'Service'], true) ? '700' : '500' ?>; color: #152B45; border-bottom: 1px solid #E5EDF5;">
                      <?php if ($label === 'Email'): ?>
                        <a href="mailto:<?= e_attr($val) ?>" style="color: #152B45; text-decoration: underline;"><?= e($val) ?></a>
                      <?php elseif ($label === 'Phone' && $val !== '(Not provided)'): ?>
                        <a href="tel:<?= e_attr($val) ?>" style="color: #152B45; text-decoration: underline;"><?= e($val) ?></a>
                      <?php else: ?>
                        <?= e($val) ?>
                      <?php endif ?>
                    </td>
                  </tr>
                <?php endforeach ?>
              </table>

              <!-- Event Details -->
              <?php if (trim((string) $record['details']) !== ''): ?>
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 22px;">
                  <tr>
                    <td style="background-color: #F8FAFC; border: 1px solid #D7E3EE; border-left: 4px solid #FFB300; border-radius: 4px; padding: 14px 18px;">
                      <div style="font-family: 'JetBrains Mono', Consolas, monospace; font-size: 11px; font-weight: 700; color: #152B45; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">
                        Event Details:
                      </div>
                      <div style="font-family: 'Inter', Arial, sans-serif; font-size: 13px; line-height: 1.6; color: #2E4763; white-space: pre-wrap;">
                        <?= nl2br(e(trim((string) $record['details']))) ?>
                      </div>
                    </td>
                  </tr>
                </table>
              <?php endif ?>

              <!-- Campaign Attribution -->
              <?php
              $utms = array_filter([
                  'utm_source'   => $record['utm_source'] ?? $record['attribution']['utm_source'] ?? null,
                  'utm_medium'   => $record['utm_medium'] ?? $record['attribution']['utm_medium'] ?? null,
                  'utm_campaign' => $record['utm_campaign'] ?? $record['attribution']['utm_campaign'] ?? null,
                  'utm_content'  => $record['utm_content'] ?? $record['attribution']['utm_content'] ?? null,
                  'utm_term'     => $record['utm_term'] ?? $record['attribution']['utm_term'] ?? null,
              ]);
              if ($utms !== []):
              ?>
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 20px; background-color: #F0F4F8; border: 1px solid #BDCDDC; border-radius: 6px; padding: 12px 16px;">
                  <tr>
                    <td>
                      <div style="font-family: 'JetBrains Mono', Consolas, monospace; font-size: 10px; font-weight: 700; color: #152B45; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">Campaign Attribution (UTMs):</div>
                      <?php foreach ($utms as $k => $v): ?>
                        <div style="font-family: 'JetBrains Mono', Consolas, monospace; font-size: 11px; color: #4B5F73; line-height: 1.5;">
                          <strong><?= e($k) ?>:</strong> <?= e($v) ?>
                        </div>
                      <?php endforeach ?>
                    </td>
                  </tr>
                </table>
              <?php endif ?>

              <!-- Direct Reply Callout -->
              <p style="font-family: 'Inter', Arial, sans-serif; font-size: 13px; color: #5A6B7F; line-height: 1.5; margin: 0;">
                To reply directly to the client, hit Reply to this email.
              </p>

            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td class="email-footer" style="background-color: #0B1622; padding: 20px 36px; border-top: 1px solid rgba(255, 255, 255, 0.08); font-family: 'JetBrains Mono', Consolas, monospace; font-size: 10px; color: #8296AC;">
              <?= e($siteName) ?> &middot; Automated Internal Notification
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>

</body>
</html>
