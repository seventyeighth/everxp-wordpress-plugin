/**
 * EverXP Embed SDK  v1.3
 * Loaded via async snippet — reads key/base from window globals set by the
 * loader, with data-key / data-base attributes as fallback.
 * API calls use ?key= query param (no Authorization header = no CORS preflight).
 */
(function () {
  'use strict';

  var CACHE_TTL = 5 * 60 * 1000;

  /* ── bootstrap ───────────────────────────────────────────────────────── */
  var scriptTag = document.currentScript || (function () {
    var s = document.getElementsByTagName('script');
    return s[s.length - 1];
  })();
  var API_KEY  = window._everxpKey  || (scriptTag && scriptTag.getAttribute('data-key'));
  var API_BASE = window._everxpBase || (scriptTag && scriptTag.getAttribute('data-base')) || 'https://api.everxp.com';
  if (!API_KEY) { return; }

  /* ── 1. fetch embed deployments (cached 5 min) ───────────────────────── */
  function fetchEmbeds(cb) {
    var k = 'everxp_' + API_KEY.slice(-8);
    try {
      var raw = sessionStorage.getItem(k);
      if (raw) {
        var p = JSON.parse(raw);
        if (p && (Date.now() - p._ts) < CACHE_TTL) { cb(p.d); return; }
      }
    } catch (e) {}

    var xhr = new XMLHttpRequest();
    xhr.open('GET', API_BASE + '/v2/embeds?key=' + encodeURIComponent(API_KEY), true);
    xhr.onload = function () {
      if (xhr.status !== 200) { return; }
      try {
        var embeds = JSON.parse(xhr.responseText).embeds || [];
        try { sessionStorage.setItem(k, JSON.stringify({ _ts: Date.now(), d: embeds })); } catch (e) {}
        cb(embeds);
      } catch (e) {}
    };
    xhr.onerror = function () {};
    xhr.send();
  }

  /* ── 2. fetch a random heading from a bank (never cached) ───────────── */
  function fetchHeading(bankId, cb) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', API_BASE + '/v2/embed_heading?bank=' + bankId + '&key=' + encodeURIComponent(API_KEY), true);
    xhr.onload = function () {
      if (xhr.status !== 200) { cb(null); return; }
      try { cb(JSON.parse(xhr.responseText).heading || null); } catch (e) { cb(null); }
    };
    xhr.onerror = function () { cb(null); };
    xhr.send();
  }

  /* ── 3. resolve final HTML for an embed ─────────────────────────────── */
  function resolveHtml(embed, cb) {
    if (!embed.bank_id) { cb(null); return; }
    fetchHeading(embed.bank_id, function (heading) {
      if (!heading) { cb(null); return; }
      cb('<div class="everxp-heading">' + heading + '</div>');
    });
  }

  /* ── 4. client-side conditions ───────────────────────────────────────── */
  function passes(cond) {
    if (!cond) { return true; }
    var url  = window.location.href;
    var path = window.location.pathname;
    var mob  = /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

    if (cond.url_contains && cond.url_contains.length &&
        !cond.url_contains.some(function (s) { return url.indexOf(s) !== -1; }))     { return false; }
    if (cond.url_not_contains && cond.url_not_contains.length &&
         cond.url_not_contains.some(function (s) { return url.indexOf(s) !== -1; })) { return false; }
    if (cond.url_equals && cond.url_equals.length &&
        !cond.url_equals.some(function (s) { return path === s || url === s; }))      { return false; }
    if (cond.device === 'mobile'  && !mob)  { return false; }
    if (cond.device === 'desktop' &&  mob)  { return false; }
    if (cond.element_selector) {
      try { if (!document.querySelector(cond.element_selector)) { return false; } } catch (e) {}
    }
    return true;
  }

  /* ── 5. DOM helpers ─────────────────────────────────────────────────── */
  function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

  function ensureStyle(id, css) {
    if (!css || !css.trim() || document.getElementById('expcss-' + id)) { return; }
    var s = document.createElement('style');
    s.id = 'expcss-' + id;
    s.textContent = css;
    (document.head || document.documentElement).appendChild(s);
  }

  function runScripts(el) {
    qsa('script', el).forEach(function (o) {
      var s = document.createElement('script');
      for (var i = 0; i < o.attributes.length; i++) { s.setAttribute(o.attributes[i].name, o.attributes[i].value); }
      if (o.text) { s.text = o.text; }
      o.parentNode.replaceChild(s, o);
    });
  }

  function inject(tag, id, css, html, before) {
    if (document.getElementById(id)) { return; }
    ensureStyle(id, css);
    var el = document.createElement(tag);
    el.id = id; el.innerHTML = html;
    if (before) { before.parentNode.insertBefore(el, before); }
    else { document.body.appendChild(el); }
    runScripts(el);
  }

  (function () {
    if (document.getElementById('everxp-base-css')) { return; }
    var s = document.createElement('style');
    s.id = 'everxp-base-css';
    s.textContent = [
      '.everxp-banner-insert { box-sizing:border-box; overflow:hidden; }',
      '.everxp-banner-content img { width:100%; height:auto; display:block; max-width:100%; }',
      '.everxp-banner-content a  { display:block; }',
      '.everxp-heading { width:100%; }'
    ].join('\n');
    (document.head || document.documentElement).appendChild(s);
  })();

  /* ── 6. Woo / Elementor loop grid injection ─────────────────────────── */
  function buildWrap(grid) {
    var list = /^(UL|OL)$/i.test(grid.tagName);
    var el = document.createElement(list ? 'LI' : 'DIV');
    el.className = 'everxp-banner-insert';
    el.style.cssText = 'list-style:none;padding:0;box-sizing:border-box;';
    return el;
  }

  function matchItemLayout(wrap, item) {
    try {
      var cs  = window.getComputedStyle(item);
      var pcs = window.getComputedStyle(item.parentNode);
      var pd  = pcs.display;

      if (pd === 'grid' || pd === 'inline-grid') {
        // CSS Grid — browser auto-places the element in the next free cell.
      } else if (pd === 'flex' || pd === 'inline-flex') {
        wrap.style.flex   = cs.flex;
        wrap.style.width  = cs.width;
        wrap.style.margin = cs.margin;
      } else {
        wrap.style.cssFloat = cs.cssFloat;
        wrap.style.width    = cs.width;
        wrap.style.display  = cs.display;
        wrap.style.margin   = cs.margin;
      }
    } catch (e) {}
  }

  var ITEM_SELS = [
    ':scope > li.wc-block-product',
    ':scope > li.wc-block-grid__product',
    ':scope > li.type-product',
    ':scope > li.product',
    ':scope > li.post-type-product',
    ':scope > .e-loop-item',
    ':scope > div.wd-product',        // WoodMart theme
    ':scope > div.type-product',      // generic div-based grids
    ':scope > div.product-grid-item', // WoodMart / similar
    ':scope > li'
  ];

  function items(grid) {
    var seen = [], out = [];
    ITEM_SELS.forEach(function (sel) {
      try {
        qsa(sel, grid).forEach(function (n) {
          if (seen.indexOf(n) === -1 && !n.classList.contains('everxp-banner-insert')) {
            seen.push(n); out.push(n);
          }
        });
      } catch (e) {}
    });
    out.sort(function (a, b) { return a.compareDocumentPosition(b) & 4 ? -1 : 1; });
    return out;
  }

  function itemsGeneric(grid) {
    return Array.prototype.slice.call(grid.children).filter(function (n) {
      return !n.classList.contains('everxp-banner-insert');
    });
  }

  function fixedPos(count, every) {
    var out = [];
    for (var i = Math.max(1, every | 0); i <= count; i += Math.max(1, every | 0)) { out.push(i); }
    return out;
  }

  function randomPos(count, min, max, perRow) {
    var out = [], cur = 0;
    min = Math.max(1, min | 0); max = Math.max(min, max | 0); perRow = Math.max(1, perRow | 0);
    while (true) {
      cur += Math.max(1, (Math.floor(Math.random() * (max - min + 1)) + min) * perRow);
      if (cur > count) { break; }
      out.push(cur);
    }
    return out;
  }

  function specificPos(str) {
    return (str || '').split(',')
      .map(function (s) { return parseInt(s.trim(), 10); })
      .filter(function (n) { return n > 0; })
      .sort(function (a, b) { return a - b; })
      .filter(function (v, i, a) { return i === 0 || a[i - 1] !== v; });
  }

  function doGrid(grid, embed, html) {
    var l   = embed.loop_settings || {};
    var its = l.grid_selector ? itemsGeneric(grid) : items(grid);
    if (!its.length) { return; }

    var mode = l.mode || 'fixed';
    var pos  = mode === 'specific' ? specificPos(l.positions)
             : mode === 'fixed'    ? fixedPos(its.length, l.every)
             :                       randomPos(its.length, l.min_rows, l.max_rows, l.per_row);
    if (l.max_show > 0) { pos = pos.slice(0, l.max_show); }

    var eid = String(embed.id);
    ensureStyle(embed.id, embed.custom_css);
    pos.forEach(function (p) {
      var item = its[p - 1];
      if (!item) { return; }
      if (item.getAttribute('data-exp-emb') === eid) { return; }
      item.setAttribute('data-exp-emb', eid);
      var wrap = buildWrap(grid);
      wrap.setAttribute('data-embed-id', eid);
      matchItemLayout(wrap, item);
      wrap.innerHTML = '<div class="everxp-banner-row" style="width:100%;"><div class="everxp-banner-content" style="width:100%;max-width:100%;overflow:hidden;">' + html + '</div></div>';
      item.parentNode.insertBefore(wrap, item.nextSibling);
      runScripts(wrap);
    });
  }

  var GRID_SELS = [
    'ul.wc-block-product-template',
    'ul.wc-block-grid__products',
    'div.wc-block-grid__products',
    '.wc-block-product-template',
    'ul.products',
    'div.products',
    'div.wd-products',                // WoodMart theme
    '.wp-block-woocommerce-all-products ul',
    '.wp-block-woocommerce-product-collection ul'
  ];

  function findGrids() {
    var seen = [], out = [];
    GRID_SELS.forEach(function (sel) {
      try {
        qsa(sel).forEach(function (g) {
          if (seen.indexOf(g) === -1) { seen.push(g); out.push(g); }
        });
      } catch (e) {}
    });
    qsa('.e-loop-item').forEach(function (n) {
      var p = n.parentNode;
      if (p && p.nodeType === 1 && seen.indexOf(p) === -1) { seen.push(p); out.push(p); }
    });
    return out.filter(function (g) {
      return !out.some(function (other) { return other !== g && other.contains(g); });
    });
  }

  /* ── 7. placement router ─────────────────────────────────────────────── */
  var CONTENT_SEL = 'main,[role="main"],.main-content,#content,.site-content,article';

  function place(embed, html) {
    if (!html) { return; }
    var p = embed.placement || 'woo_loop';

    switch (p) {
      case 'woo_loop':
        (function () {
          var watched = [];
          var customSel = embed.loop_settings && embed.loop_settings.grid_selector;

          function findTargetGrids() {
            if (customSel) {
              var out = [], seen = [];
              qsa(customSel).forEach(function (g) {
                if (seen.indexOf(g) === -1) { seen.push(g); out.push(g); }
              });
              return out;
            }
            return findGrids();
          }

          function proc(g) {
            if (watched.indexOf(g) !== -1) { return; }
            watched.push(g);
            doGrid(g, embed, html);
            new MutationObserver(function () { doGrid(g, embed, html); })
              .observe(g, { childList: true });
          }

          findTargetGrids().forEach(proc);

          new MutationObserver(function () { findTargetGrids().forEach(proc); })
            .observe(document.body || document.documentElement, { childList: true, subtree: true });
        })();
        break;

      case 'top_bar':
        inject('div', 'exp-top-' + embed.id, embed.custom_css, html, document.body.firstChild);
        break;

      case 'bottom_bar':
        inject('div', 'exp-bot-' + embed.id, embed.custom_css, html, null);
        var el = document.getElementById('exp-bot-' + embed.id);
        if (el) { el.style.cssText = 'position:fixed;bottom:0;left:0;width:100%;z-index:999999;'; }
        break;

      case 'before_content': {
        var t = document.querySelector(CONTENT_SEL);
        if (t) { inject('div', 'exp-bef-' + embed.id, embed.custom_css, html, t); }
        break;
      }
      case 'after_content': {
        var t = document.querySelector(CONTENT_SEL);
        if (t) { inject('div', 'exp-aft-' + embed.id, embed.custom_css, html, t.nextSibling); }
        break;
      }
      case 'selector': {
        var sel = embed.selector || '';
        if (!sel) { break; }
        var eid = 'exp-sel-' + embed.id;
        if (document.getElementById(eid)) { break; }
        var target = document.querySelector(sel);
        if (!target) { break; }
        ensureStyle(embed.id, embed.custom_css);
        var el = document.createElement('div');
        el.id = eid; el.innerHTML = html;
        var before = embed.selector_position === 'before';
        target.parentNode.insertBefore(el, before ? target : target.nextSibling);
        runScripts(el);
        break;
      }
    }
  }

  /* ── 8. analytics (fire-and-forget) ─────────────────────────────────── */
  function track(embedId) {
    try {
      var url = API_BASE + '/logs/track_event'
        + '?key='       + encodeURIComponent(API_KEY)
        + '&eventType=impression'
        + '&embed_id='  + encodeURIComponent(embedId)
        + '&url='       + encodeURIComponent(location.href);
      var xhr = new XMLHttpRequest();
      xhr.open('GET', url, true);
      xhr.send();
    } catch (e) {}
  }

  /* ── 9. main ─────────────────────────────────────────────────────────── */
  function run(embeds) {
    embeds.forEach(function (embed) {
      try {
        if (typeof embed.conditions    === 'string') { try { embed.conditions    = JSON.parse(embed.conditions);    } catch (_) { embed.conditions    = null; } }
        if (typeof embed.loop_settings === 'string') { try { embed.loop_settings = JSON.parse(embed.loop_settings); } catch (_) { embed.loop_settings = {};   } }
        if (!passes(embed.conditions)) { return; }

        resolveHtml(embed, function (html) {
          place(embed, html);
          track(embed.id);
        });
      } catch (e) {}
    });
  }

  fetchEmbeds(function (embeds) {
    if (!embeds || !embeds.length) { return; }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () { run(embeds); });
      window.addEventListener('load', function () { run(embeds); });
    } else if (document.readyState === 'interactive') {
      run(embeds);
      window.addEventListener('load', function () { run(embeds); });
    } else {
      run(embeds);
    }
  });

})();
