/* Phones: every admin table becomes a list of cards (label : value), so nothing needs sideways swiping. */
(function () {
  'use strict';
  var SEL = 'table.t, table.data', raf = 0;
  function label(table) {
    if (table.dataset.cards === '1') { var rows0 = table.tBodies; if (!rows0.length || table.dataset.n === String(table.rows.length)) return; }
    var heads = [].map.call(table.querySelectorAll('thead tr:last-child th'), function (th) { return th.textContent.replace(/\s+/g, ' ').trim(); });
    if (!heads.length) return;
    [].forEach.call(table.tBodies, function (tb) {
      [].forEach.call(tb.rows, function (tr) {
        var i = 0;
        [].forEach.call(tr.cells, function (td) {
          var h = heads[i] || '';
          if (i > 0 && h && !td.hasAttribute('data-label')) td.setAttribute('data-label', h);
          if (i === 0) td.classList.add('tc-first');
          if (!td.textContent.trim() && !td.querySelector('img,svg,button,a,input,select,.pill,.av')) td.classList.add('tc-empty');
          if (!h || /^(actions?)?$/i.test(h)) td.classList.add('tc-act');
          i += td.colSpan || 1;
        });
      });
    });
    table.classList.add('is-cards'); table.dataset.cards = '1'; table.dataset.n = String(table.rows.length);
  }
  function scan() { raf = 0; [].forEach.call(document.querySelectorAll(SEL), label); }
  function queue() { if (!raf) raf = requestAnimationFrame(scan); }
  new MutationObserver(queue).observe(document.documentElement, { childList: true, subtree: true });
  document.addEventListener('DOMContentLoaded', queue); queue();
})();
