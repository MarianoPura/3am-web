for (const panel of document.querySelectorAll('[data-rentals-qr]')) {
  const target = panel.querySelector('[data-rentals-qr-image]');
  const button = panel.querySelector('[data-rentals-qr-download]');
  const statusUrl = panel.dataset.qrUrl;
  if (!target || !button || !statusUrl || typeof qrcode !== 'function') continue;
  try {
    const code = qrcode(0, 'M');
    code.addData(statusUrl);
    code.make();
    const svg = code.createSvgTag(5, 4);
    target.innerHTML = svg;
    button.addEventListener('click', () => {
      const blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = '3am-rental-status-qr.svg';
      link.click();
      setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    });
  } catch {
    button.hidden = true;
    target.textContent = 'Use the status link below to check your order.';
  }
}
