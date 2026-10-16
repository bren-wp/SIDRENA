/* global window */
(function () {
  'use strict';
  if (!window.wc || !window.wc.blocksCheckout || typeof window.wc.blocksCheckout.registerCheckoutFilters !== 'function') {
    return;
  }
  const escapeHTML = (value) => String(value).replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);
  window.wc.blocksCheckout.registerCheckoutFilters('brendigo-sidrena-cijena', {
    itemName: (defaultValue, extensions, args) => {
      if (!args || !args.cartItem || !['cart', 'summary'].includes(args.context)) {
        return defaultValue;
      }
      const details = (args.cartItem.extensions || {}).brendigo_sidrena || (extensions || {}).brendigo_sidrena || {};
      if (!details.text || typeof details.text !== 'string' || defaultValue.includes('brendigo-sidrena-block-note')) {
        return defaultValue;
      }
      return defaultValue + '<span class="brendigo-sidrena-block-note">' + escapeHTML(details.text) + '</span>';
    }
  });
})();
