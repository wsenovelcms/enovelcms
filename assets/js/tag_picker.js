(function () {
  'use strict';
  var I18N = window.TagPickerL10n || window.lang || window.admLang || {};
  function T(k, fb) { return I18N[k] !== undefined && I18N[k] !== '' && I18N[k] !== k ? I18N[k] : fb; }
  function fmt(tpl) {
    var a = Array.prototype.slice.call(arguments, 1);
    return String(tpl).replace(/%d|%s/g, function () { return a.length ? a.shift() : ''; });
  }
  var PER = 24;
  var CSS = ".ts-hidden{display:none}.ts-trigger{min-height:40px;border:1px solid #d3dae4;background:#fff;border-radius:6px;padding:5px 10px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;cursor:pointer;transition:border-color .15s,box-shadow .15s}.ts-trigger:hover{border-color:#b8860b}.ts-trigger:focus{outline:none;border-color:#b8860b;box-shadow:0 0 0 3px rgba(184,134,11,.12)}.ts-ph{color:#94a3b8;font-size:14px}.ts-chip{display:inline-flex;align-items:center;gap:6px;background:#faf7f2;border:1px solid #e6ddc7;color:#333;border-radius:4px;padding:2px 8px;font-size:13px;line-height:1.4}.ts-chip-x{font-style:normal;font-size:11px;color:#94a3b8;cursor:pointer}.ts-chip-x:hover{color:#dc2626}.ts-mask{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:10000;display:flex;align-items:center;justify-content:center;padding:20px}.ts-modal{background:#fff;border-radius:8px;width:600px;max-width:100%;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 20px 50px rgba(0,0,0,.25);overflow:hidden}.ts-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #eef1f5}.ts-head-title{font-size:15px;font-weight:600;color:#1e293b;display:flex;align-items:center;gap:8px}.ts-head-x{border:none;background:none;font-size:18px;color:#94a3b8;cursor:pointer;line-height:1;padding:4px}.ts-head-x:hover{color:#333}.ts-search{padding:12px 18px;border-bottom:1px solid #f1f4f8}.ts-search input{width:100%;height:38px;border:1px solid #d3dae4;border-radius:6px;padding:0 12px;font-size:14px}.ts-search input:focus{outline:none;border-color:#b8860b;box-shadow:0 0 0 3px rgba(184,134,11,.12)}.ts-warn{display:none;padding:10px 18px 0;color:#dc2626;font-size:13px}.ts-warn.show{display:block}.ts-grid{flex:1;overflow-y:auto;padding:14px 18px;display:grid;grid-template-columns:repeat(auto-fill,minmax(108px,1fr));gap:8px;align-content:start}.ts-opt{appearance:none;border:1px solid #dfe4ec;background:#fff;color:#475569;border-radius:5px;padding:7px 8px;font-size:13px;cursor:pointer;transition:all .12s;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ts-opt:hover{border-color:#b8860b;color:#b8860b}.ts-opt.on{background:#333;border-color:#333;color:#fff;font-weight:600}.ts-empty{grid-column:1/-1;text-align:center;color:#94a3b8;padding:30px 0;font-size:13px}.ts-pager{display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 18px;border-top:1px solid #f1f4f8;flex-wrap:wrap}.ts-pg{min-width:34px;height:32px;padding:0 8px;border:1px solid #d3dae4;background:#fff;border-radius:5px;color:#555;font-size:13px;cursor:pointer}.ts-pg:hover:not(.dis):not(.cur){border-color:#b8860b;color:#b8860b}.ts-pg.cur{background:#333;border-color:#333;color:#fff;font-weight:600}.ts-pg.dis{color:#c2c8d2;background:#f8fafc;cursor:not-allowed}.ts-pg-info{font-size:13px;color:#64748b;padding:0 6px}.ts-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 18px;border-top:1px solid #eef1f5;background:#fafbfc;flex-wrap:wrap}.ts-foot-info{font-size:13px;color:#64748b}.ts-foot-info b{color:#1e293b}.ts-foot-btns{display:flex;gap:8px}.ts-btn{height:36px;padding:0 16px;border-radius:6px;font-size:14px;cursor:pointer;border:1px solid transparent;display:inline-flex;align-items:center;gap:6px}.ts-btn-ghost{background:#fff;border-color:#d3dae4;color:#555}.ts-btn-ghost:hover{border-color:#b8860b;color:#b8860b}.ts-btn-primary{background:#333;color:#fff}.ts-btn-primary:hover{background:#000}.ts-btn-text{background:none;border-color:transparent;color:#64748b}.ts-btn-text:hover{color:#dc2626}@media(max-width:560px){.ts-mask{padding:0}.ts-modal{width:100%;max-height:100vh;height:100vh;border-radius:0}.ts-grid{grid-template-columns:repeat(auto-fill,minmax(92px,1fr))}}";

  function injectCss() {
    if (document.getElementById('ts-style')) return;
    var s = document.createElement('style');
    s.id = 'ts-style';
    s.textContent = CSS;
    document.head.appendChild(s);
  }
  function el(tag, attrs, kids) {
    var n = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) {
      if (k === 'className') n.className = attrs[k];
      else if (k === 'text') n.textContent = attrs[k];
      else if (k === 'html') n.innerHTML = attrs[k];
      else if (k === 'style' && typeof attrs[k] === 'object') Object.assign(n.style, attrs[k]);
      else if (k.indexOf('on') === 0 && typeof attrs[k] === 'function') n.addEventListener(k.slice(2), attrs[k]);
      else if (attrs[k] !== null && attrs[k] !== undefined) n.setAttribute(k, attrs[k]);
    });
    if (kids) (Array.isArray(kids) ? kids : [kids]).forEach(function (c) {
      if (c == null) return;
      n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
    });
    return n;
  }

  function Picker(node) {
    this.node = node;
    this.field = node.getAttribute('data-field') || 'tag_ids';
    this.max = parseInt(node.getAttribute('data-max'), 10) || 0;
    this.tags = [];
    try { this.tags = JSON.parse(node.getAttribute('data-tags') || '[]'); } catch (e) { this.tags = []; }
    this.selected = this.parseIds(node.getAttribute('data-selected'));
    this.temp = this.selected.slice();
    this.page = 1;
    this.keyword = '';
    this.nameMap = {};
    this.tags.forEach(function (t) { this.nameMap[t.id] = t.name; }, this);
    this.render();
  }
  Picker.prototype.parseIds = function (str) {
    var out = [];
    (str || '').split(',').forEach(function (s) {
      s = parseInt(s, 10);
      if (s > 0 && out.indexOf(s) === -1) out.push(s);
    });
    return out;
  };
  Picker.prototype.render = function () {
    var self = this;
    this.node.classList.add('ts');
    this.node.innerHTML = '';
    this.hiddenBox = el('div', { className: 'ts-hidden' });
    this.trigger = el('div', {
      className: 'ts-trigger', tabindex: '0', role: 'button',
      onclick: function () { self.open(); },
      onkeydown: function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); self.open(); } }
    });
    this.node.appendChild(this.trigger);
    this.node.appendChild(this.hiddenBox);
    this.refreshTrigger();
  };
  Picker.prototype.refreshTrigger = function () {
    var self = this;
    this.trigger.innerHTML = '';
    if (!this.selected.length) {
      this.trigger.appendChild(el('span', { className: 'ts-ph', text: this.node.getAttribute('data-placeholder') || T('tp_placeholder', '点击选择标签') }));
    } else {
      this.selected.forEach(function (id) {
        self.trigger.appendChild(el('span', { className: 'ts-chip' }, [
          el('span', { text: self.nameMap[id] || '#' + id }),
          el('i', { className: 'ts-chip-x fas fa-times', title: T('ui_remove', '移除'), onclick: function (e) { e.stopPropagation(); self.removeSelected(id); } })
        ]));
      });
    }
    this.hiddenBox.innerHTML = '';
    this.selected.forEach(function (id) {
      self.hiddenBox.appendChild(el('input', { type: 'hidden', name: self.field + '[]', value: String(id) }));
    });
  };
  Picker.prototype.removeSelected = function (id) {
    var i = this.selected.indexOf(id);
    if (i > -1) { this.selected.splice(i, 1); this.refreshTrigger(); }
  };
  Picker.prototype.filtered = function () {
    var kw = this.keyword.trim().toLowerCase();
    if (!kw) return this.tags;
    return this.tags.filter(function (t) { return String(t.name).toLowerCase().indexOf(kw) > -1; });
  };
  Picker.prototype.open = function () {
    var self = this;
    this.temp = this.selected.slice();
    this.page = 1;
    this.keyword = '';
    this.warnTimer = null;

    this.searchInput = el('input', {
      type: 'text', autocomplete: 'off', placeholder: T('tp_search_ph', '搜索标签名称'),
      oninput: function () { self.keyword = this.value; self.page = 1; self.renderGrid(); }
    });
    this.warn = el('div', { className: 'ts-warn' });
    this.grid = el('div', { className: 'ts-grid' });
    this.pager = el('div', { className: 'ts-pager' });
    this.footInfo = el('div', { className: 'ts-foot-info' });

    function close() {
      if (self.warnTimer) clearTimeout(self.warnTimer);
      if (mask.parentNode) mask.parentNode.removeChild(mask);
      document.removeEventListener('keydown', onKey);
    }
    function onKey(e) { if (e.key === 'Escape') close(); }

    this.mask = el('div', {
      className: 'ts-mask',
      onclick: function (e) { if (e.target === mask) close(); }
    });
    var mask = this.mask;
    var modal = el('div', { className: 'ts-modal' }, [
      el('div', { className: 'ts-head' }, [
        el('span', { className: 'ts-head-title' }, [el('i', { className: 'fas fa-tags', style: { color: '#b8860b' } }), el('span', { text: T('tp_title', '选择标签') })]),
        el('button', { type: 'button', className: 'ts-head-x', 'aria-label': T('ui_close', '关闭'), onclick: close }, [el('span', { text: '×' })])
      ]),
      el('div', { className: 'ts-search' }, [this.searchInput]),
      this.warn,
      this.grid,
      this.pager,
      el('div', { className: 'ts-foot' }, [
        this.footInfo,
        el('div', { className: 'ts-foot-btns' }, [
          el('button', { type: 'button', className: 'ts-btn ts-btn-text', onclick: function () { self.temp = []; self.renderGrid(); } }, [el('span', { text: T('tp_clear', '清空') })]),
          el('button', { type: 'button', className: 'ts-btn ts-btn-ghost', onclick: close }, [el('span', { text: T('ui_cancel', '取消') })]),
          el('button', { type: 'button', className: 'ts-btn ts-btn-primary', onclick: function () { self.selected = self.temp.slice(); self.refreshTrigger(); close(); } }, [el('i', { className: 'fas fa-check' }), el('span', { text: T('ui_ok', '确定') })])
        ])
      ])
    ]);
    mask.appendChild(modal);
    document.body.appendChild(mask);
    document.addEventListener('keydown', onKey);
    this.renderGrid();
    setTimeout(function () { self.searchInput.focus(); }, 30);
  };
  Picker.prototype.flashWarn = function () {
    var self = this;
    this.warn.textContent = fmt(T('tp_limit_fmt', '最多只能选择 %d 个标签'), this.max);
    this.warn.classList.add('show');
    if (this.warnTimer) clearTimeout(this.warnTimer);
    this.warnTimer = setTimeout(function () { self.warn.classList.remove('show'); }, 1800);
  };
  Picker.prototype.toggle = function (id) {
    var i = this.temp.indexOf(id);
    if (i > -1) this.temp.splice(i, 1);
    else {
      if (this.max > 0 && this.temp.length >= this.max) { this.flashWarn(); return; }
      this.temp.push(id);
    }
    this.renderGrid();
  };
  Picker.prototype.renderGrid = function () {
    var self = this;
    var list = this.filtered();
    var pages = Math.max(1, Math.ceil(list.length / PER));
    if (this.page > pages) this.page = pages;
    var start = (this.page - 1) * PER;
    var rows = list.slice(start, start + PER);
    this.grid.innerHTML = '';
    if (!rows.length) {
      this.grid.appendChild(el('div', { className: 'ts-empty', text: T('tp_no_match', '没有匹配的标签') }));
    } else {
      rows.forEach(function (t) {
        var on = self.temp.indexOf(t.id) > -1;
        self.grid.appendChild(el('button', {
          type: 'button', className: 'ts-opt' + (on ? ' on' : ''),
          onclick: function () { self.toggle(t.id); }
        }, [el('span', { text: t.name })]));
      });
    }
    this.renderPager(pages);
    this.footInfo.innerHTML = '';
    this.footInfo.appendChild(el('span', { html: fmt(T('tp_selected_fmt', '已选 <b>%d</b> 个'), this.temp.length) }));
    if (this.max > 0) {
      this.footInfo.appendChild(el('span', { text: '　' + fmt(T('tp_max_fmt', '上限 %d 个'), this.max) }));
    } else {
      this.footInfo.appendChild(el('span', { text: '　' + T('tp_unlimited', '不限数量') }));
    }
  };
  Picker.prototype.renderPager = function (pages) {
    var self = this;
    this.pager.innerHTML = '';
    function pg(label, page, mode) {
      var attrs = { type: 'button', className: 'ts-pg' + (mode ? ' ' + mode : '') };
      if (mode === 'dis') attrs.disabled = '';
      else attrs.onclick = function () { self.page = page; self.renderGrid(); };
      return el('button', attrs, [el('span', { text: label })]);
    }
    this.pager.appendChild(pg(T('tp_prev', '上一页'), this.page - 1, this.page <= 1 ? 'dis' : ''));
    var s = Math.max(1, this.page - 2), e = Math.min(pages, s + 4);
    s = Math.max(1, Math.min(s, e - 4));
    for (var i = s; i <= e; i++) this.pager.appendChild(pg(String(i), i, i === this.page ? 'cur' : ''));
    this.pager.appendChild(pg(T('tp_next', '下一页'), this.page + 1, this.page >= pages ? 'dis' : ''));
    this.pager.appendChild(el('span', { className: 'ts-pg-info', text: this.page + ' / ' + pages }));
  };

  function init() {
    injectCss();
    document.querySelectorAll('.tag-select').forEach(function (n) {
      if (!n.__ts) n.__ts = new Picker(n);
    });
  }
  window.TagPicker = { init: init, refresh: init };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
