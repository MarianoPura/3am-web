<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no" />
  <meta name="color-scheme" content="light" />
  <meta name="supported-color-schemes" content="light" />
  <title><?= e(sprintf('Quote Inquiry Confirmation — %s', $record['reference'])) ?></title>
  <style type="text/css">
    /* Global Resets */
    body, p, h1, h2, h3, table, td {
      margin: 0;
      padding: 0;
    }
    body {
      background-color: #F0F4F8;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      -webkit-font-smoothing: antialiased;
      -webkit-text-size-adjust: 100%;
      -ms-text-size-adjust: 100%;
      color: #152B45;
      margin: 0 !important;
      padding: 0 !important;
      width: 100% !important;
    }
    table {
      border-collapse: collapse;
      mso-table-lspace: 0pt;
      mso-table-rspace: 0pt;
    }
    img {
      border: 0;
      height: auto;
      line-height: 100%;
      outline: none;
      text-decoration: none;
    }
    /* Mobile Responsive */
    @media only screen and (max-width: 620px) {
      .email-container {
        width: 100% !important;
        max-width: 100% !important;
      }
      .email-content {
        padding: 24px 20px 20px 20px !important;
      }
      .email-header {
        padding: 24px 20px !important;
      }
      .email-footer {
        padding: 24px 20px !important;
      }
      .ref-code {
        font-size: 20px !important;
        letter-spacing: 1px !important;
      }
      .summary-label {
        width: 110px !important;
        font-size: 12px !important;
      }
      .summary-value {
        font-size: 13px !important;
      }
    }
  </style>
</head>
<body style="background-color: #F0F4F8; margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">

  <!-- Preheader text (visible in inbox preview, hidden in email body) -->
  <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all; font-size: 1px; line-height: 1px; color: #F0F4F8;">
    We have received your quote inquiry (Ref: <?= e($record['reference']) ?>). Our production team will review your requirements.
    &#847; &zwnj; &nbsp; &#8199; &shy; &#847; &zwnj; &nbsp; &#8199; &shy; &#847; &zwnj; &nbsp; &#8199; &shy;
  </div>

  <!-- Outer background container table -->
  <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #F0F4F8;">
    <tr>
      <td align="center" style="padding: 32px 12px;">

        <!-- Main Email Container (600px) -->
        <table role="presentation" class="email-container" border="0" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px; width: 100%; background-color: #FFFFFF; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 20px rgba(11, 22, 34, 0.07); border: 1px solid #D7E3EE;">

          <!-- Top Brand Accent Line (Tally Amber) -->
          <tr>
            <td height="4" style="background-color: #FFB300; line-height: 4px; font-size: 4px;">&nbsp;</td>
          </tr>

          <!-- Header (Brand Void Navy) -->
          <tr>
            <td class="email-header" style="background-color: #0B1622; padding: 30px 36px 26px 36px;">
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td>
                    <!-- Brand Lockup -->
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                      <tr>
                        <td valign="middle" style="padding-right: 12px;">
                          <!-- 3AM Triangle Cluster Mark -->
                          <svg width="32" height="32" viewBox="0 0 100 100" style="display: block; vertical-align: middle;">
                            <polygon points="50,12 70,45 30,45" fill="#FFB300" stroke="#FFB300" stroke-width="4" stroke-linejoin="round" />
                            <polygon points="29,54 49,87 9,87" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="4" stroke-linejoin="round" />
                            <polygon points="71,54 91,87 51,87" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="4" stroke-linejoin="round" />
                          </svg>
                        </td>
                        <td valign="middle">
                          <span style="font-family: 'Archivo', 'Arial Black', Arial, sans-serif; font-size: 21px; font-weight: 900; color: #FFFFFF; letter-spacing: 0.5px; line-height: 1;">3AM</span>
                          <span style="font-family: 'JetBrains Mono', 'SF Mono', Consolas, Menlo, monospace; font-size: 12px; font-weight: 700; color: #FFB300; letter-spacing: 2px; text-transform: uppercase; margin-left: 5px; line-height: 1;">DIGITAL MEDIA</span>
                        </td>
                      </tr>
                    </table>
                  </td>
                  <td align="right" valign="middle">
                    <span style="display: inline-block; font-family: 'JetBrains Mono', Consolas, monospace; font-size: 10px; color: #8296AC; letter-spacing: 1.5px; text-transform: uppercase; border: 1px solid rgba(255, 255, 255, 0.12); padding: 4px 8px; border-radius: 3px;">INQUIRY CONFIRMATION</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Main Content Body -->
          <tr>
            <td class="email-content" style="padding: 36px 36px 30px 36px; background-color: #FFFFFF;">

              <!-- Greeting -->
              <h1 style="margin: 0 0 14px 0; font-family: 'Archivo', 'Inter', Arial, sans-serif; font-size: 22px; font-weight: 800; color: #152B45; line-height: 1.3;">
                Hi <?= e(trim((string) $record['name'])) ?>,
              </h1>

              <!-- Message Lead -->
              <p style="margin: 0 0 12px 0; font-family: 'Inter', Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #152B45;">
                Thank you for reaching out to <strong><?= e($siteName) ?></strong> We have received your inquiry and our production team will review your requirements.
              </p>
              <p style="margin: 0 0 24px 0; font-family: 'Inter', Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #4B5F73;">
                We usually get back to you within <strong>one business day</strong>.
              </p>

              <!-- Reference Badge Card -->
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0B1622; border-radius: 6px; border: 1px solid #17273A; margin-bottom: 28px;">
                <tr>
                  <td style="padding: 18px 22px;">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                      <tr>
                        <td align="left" style="font-family: 'JetBrains Mono', 'SF Mono', Consolas, monospace; font-size: 10px; font-weight: 700; color: #8296AC; letter-spacing: 1.5px; text-transform: uppercase;">
                          YOUR REFERENCE
                        </td>
                      </tr>
                      <tr>
                        <td colspan="2" class="ref-code" style="padding-top: 6px; font-family: 'JetBrains Mono', 'SF Mono', Consolas, Monaco, monospace; font-size: 24px; font-weight: 800; color: #FFB300; letter-spacing: 2.5px;">
                          <?= e($record['reference']) ?>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <!-- Summary Header -->
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 14px;">
                <tr>
                  <td style="border-bottom: 2px solid #E5EDF5; padding-bottom: 8px;">
                    <span style="font-family: 'Archivo', 'Inter', Arial, sans-serif; font-size: 13px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; color: #152B45;">
                      Summary of your inquiry
                    </span>
                  </td>
                </tr>
              </table>

              <!-- Summary Data Table -->
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border: 1px solid #D7E3EE; border-radius: 6px; overflow: hidden; margin-bottom: 24px;">
                <?php
                $fields = array_filter([
                    ['label' => 'Service',   'value' => $record['type']],
                    ['label' => 'Full Name', 'value' => $record['name']],
                    ['label' => 'Email',     'value' => $record['email']],
                    ['label' => 'Phone',     'value' => $record['phone']],
                    ['label' => 'Company',   'value' => $record['company']],
                ], static fn ($f): bool => trim((string) $f['value']) !== '');

                $total = count($fields);
                $index = 0;
                foreach ($fields as $field):
                    $index++;
                    $isLast = $index === $total;
                    $isOdd = ($index % 2) === 1;
                    $rowBg = $isOdd ? '#FFFFFF' : '#F8FAFC';
                ?>
                  <tr style="background-color: <?= $rowBg ?>;">
                    <td class="summary-label" width="130" valign="top" style="padding: 12px 16px; font-family: 'JetBrains Mono', Consolas, monospace; font-size: 12px; font-weight: 700; color: #5A6B7F; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: <?= $isLast ? 'none' : '1px solid #E5EDF5' ?>;">
                      <?= e($field['label']) ?>
                    </td>
                    <td class="summary-value" valign="top" style="padding: 12px 16px; font-family: 'Inter', Arial, sans-serif; font-size: 14px; font-weight: <?= $field['label'] === 'Service' ? '700' : '500' ?>; color: #152B45; border-bottom: <?= $isLast ? 'none' : '1px solid #E5EDF5' ?>;">
                      <?= e($field['value']) ?>
                    </td>
                  </tr>
                <?php endforeach ?>
              </table>

              <!-- Event Details (Optional section, shown only if present) -->
              <?php if (trim((string) $record['details']) !== ''): ?>
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 24px;">
                  <tr>
                    <td style="background-color: #F8FAFC; border: 1px solid #D7E3EE; border-left: 4px solid #FFB300; border-radius: 4px; padding: 16px 20px;">
                      <div style="font-family: 'JetBrains Mono', Consolas, monospace; font-size: 11px; font-weight: 700; color: #152B45; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">
                        Event Details:
                      </div>
                      <div style="font-family: 'Inter', Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #2E4763; white-space: pre-wrap;">
                        <?= nl2br(e(trim((string) $record['details']))) ?>
                      </div>
                    </td>
                  </tr>
                </table>
              <?php endif ?>

              <!-- Updates / Next Steps Notice -->
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 12px;">
                <tr>
                  <td style="background-color: #F0F4F8; border: 1px solid #BDCDDC; border-radius: 6px; padding: 14px 18px;">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                      <tr>
                        <td valign="top" style="padding-right: 10px; font-size: 16px; line-height: 1; color: #FFB300;">
                          &#9993;
                        </td>
                        <td style="font-family: 'Inter', Arial, sans-serif; font-size: 13px; line-height: 1.5; color: #152B45;">
                          If you need to update anything or send additional files, simply reply to this email.
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

            </td>
          </tr>

          <!-- Footer (Dark Navy Background) -->
          <tr>
            <td class="email-footer" style="background-color: #0B1622; padding: 28px 36px; border-top: 1px solid rgba(255, 255, 255, 0.08);">
              <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td style="font-family: 'Inter', Arial, sans-serif; font-size: 12px; line-height: 1.6; color: #8296AC;">
                    <div style="font-weight: 700; color: #FFFFFF; font-size: 13px; margin-bottom: 4px;">
                      <?= e($company['legal_name'] ?? $siteName) ?>
                    </div>
                    <?php if (!empty($formattedAddress)): ?>
                      <div style="color: #8296AC; margin-bottom: 8px;">
                        <?= e($formattedAddress) ?>
                      </div>
                    <?php endif ?>
                    <div style="font-family: 'JetBrains Mono', Consolas, monospace; font-size: 10px; color: #5A6B7F; letter-spacing: 0.5px; border-top: 1px solid rgba(255, 255, 255, 0.06); padding-top: 8px; margin-top: 8px;">
                      This is an automated confirmation of your website inquiry.
                    </div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

        </table>
        <!-- End Main Email Container -->

      </td>
    </tr>
  </table>

</body>
</html>
