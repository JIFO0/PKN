(function () {
  function applyFullWidthLayout() {
    var selectors = [
      '.sc-search-container',
      '.sc-results-container',
      '.sc-list-container',
      '.sc-forum',
      '.sc-statistics-page',
      '.sc-dashboard-page'
    ];

    var pluginRoot = document.querySelector(selectors.join(','));
    if (!pluginRoot) return;

    document.body.classList.add('sc-plugin-page');

    var contentCol = pluginRoot.closest('.col-lg-8, .col-md-8, .content-area, .site-main');
    if (contentCol) {
      contentCol.classList.add('sc-force-fullwidth-column');
      var row = contentCol.parentElement;
      if (row) {
        row.classList.add('sc-plugin-row');
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyFullWidthLayout);
  } else {
    applyFullWidthLayout();
  }
})();
