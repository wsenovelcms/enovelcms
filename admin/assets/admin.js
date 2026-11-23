
(function () {
  'use strict';

  var layout = document.getElementById('adminLayout');
  var toggle = document.getElementById('sidebarToggle');

  if (layout && toggle) {
    
    if (localStorage.getItem('admin_sidebar_collapsed') === '1') {
      layout.classList.add('sidebar-collapsed');
    }

    
    var narrow = window.matchMedia('(max-width: 900px)');
    var mask = document.createElement('div');
    mask.className = 'sidebar-mask';
    mask.setAttribute('data-sidebar-mask', '');
    layout.appendChild(mask);

    function closeDrawer() { layout.classList.remove('drawer-open'); }

    toggle.addEventListener('click', function () {
      if (narrow.matches) {
        layout.classList.toggle('drawer-open');
        return;
      }
      closeDrawer();
      var collapsed = layout.classList.toggle('sidebar-collapsed');
      localStorage.setItem('admin_sidebar_collapsed', collapsed ? '1' : '0');
    });

    mask.addEventListener('click', closeDrawer);

    layout.addEventListener('click', function (e) {
      if (!narrow.matches || !layout.classList.contains('drawer-open')) return;
      if (e.target.closest('.sidebar .nav-link')) closeDrawer();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeDrawer();
    });

    var onBreakpoint = function () { if (!narrow.matches) closeDrawer(); };
    if (narrow.addEventListener) narrow.addEventListener('change', onBreakpoint);
    else if (narrow.addListener) narrow.addListener(onBreakpoint);
  }

  var userMenu = document.getElementById('userMenu');
  var userTrigger = document.getElementById('userTrigger');

  
  var navGroups = document.querySelectorAll('.sidebar .nav-group');
  if (navGroups.length) {
    var LS_KEY = 'admin_sidebar_open';
    var activeLink = document.querySelector('.sidebar .nav-link.active');
    var activeGroup = activeLink ? activeLink.closest('.nav-group') : null;
    var openGroups = [];
    try { openGroups = JSON.parse(localStorage.getItem(LS_KEY) || '[]'); } catch (e) { openGroups = []; }

    if (activeGroup) {
      var activeIdx = Array.prototype.indexOf.call(navGroups, activeGroup);
      openGroups = activeIdx !== -1 ? [String(activeIdx)] : [];
    }

    navGroups.forEach(function (group, idx) {
      var groupToggle = group.querySelector('.group-toggle');
      if (activeGroup) {
        
        if (group === activeGroup) group.classList.add('open');
        else group.classList.remove('open');
      } else if (openGroups.indexOf(String(idx)) !== -1) {
        group.classList.add('open');
      }
      if (!groupToggle) return;
      groupToggle.addEventListener('click', function () {
        var isOpen = group.classList.contains('open');
        navGroups.forEach(function (g) { g.classList.remove('open'); });
        if (!isOpen) {
          group.classList.add('open');
          openGroups = [String(idx)];
        } else {
          openGroups = [];
        }
        try { localStorage.setItem(LS_KEY, JSON.stringify(openGroups)); } catch (e) {}
      });
    });
  }

  if (userMenu && userTrigger) {
    userTrigger.addEventListener('click', function (e) {
      e.stopPropagation();
      userMenu.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
      if (!userMenu.contains(e.target)) {
        userMenu.classList.remove('open');
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        userMenu.classList.remove('open');
      }
    });
  }

  
  if (layout) {
    var tip = document.createElement('div');
    tip.className = 'sidebar-tip';
    document.body.appendChild(tip);

    var sidebar = document.querySelector('.sidebar');
    if (sidebar) {
      sidebar.addEventListener('mouseover', function (e) {
        var link = e.target.closest('.nav-link');
        if (!link || !layout.classList.contains('sidebar-collapsed')) { return; }
        tip.textContent = link.getAttribute('data-tip') || '';
        tip.classList.add('show');
        var rect = link.getBoundingClientRect();
        tip.style.top = rect.top + rect.height / 2 + 'px';
        tip.style.left = rect.right + 10 + 'px';
      });
      sidebar.addEventListener('mouseout', function (e) {
        if (e.target.closest('.nav-link')) { tip.classList.remove('show'); }
      });
    }
  }

  
  var pwdModal = document.getElementById('pwdModal');
  var openPwd = document.getElementById('openPwdModal');
  if (pwdModal && openPwd) {
    var closeEls = ['pwdModalClose', 'pwdCancel'];
    function openModal(e) {
      if (e) { e.preventDefault(); e.stopPropagation(); }
      userMenu.classList.remove('open');
      pwdModal.classList.add('show');
      var first = pwdModal.querySelector('input[type=password]');
      if (first) first.focus();
    }
    function closeModal() { pwdModal.classList.remove('show'); }
    openPwd.addEventListener('click', openModal);
    closeEls.forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('click', closeModal);
    });
    pwdModal.addEventListener('click', function (e) {
      if (e.target === pwdModal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeModal();
    });
  }

  
  var tabBtns = document.querySelectorAll('.tab-btn[data-tab]');
  if (tabBtns.length) {
    function activateTab(name) {
      tabBtns.forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-tab') === name); });
      document.querySelectorAll('.tab-pane[data-pane]').forEach(function (p) {
        p.classList.toggle('active', p.getAttribute('data-pane') === name);
      });
      try { localStorage.setItem('admin_active_tab', name); } catch (e) {}
    }
    tabBtns.forEach(function (b) {
      b.addEventListener('click', function () { activateTab(b.getAttribute('data-tab')); });
    });
    var urlTab = null;
    try { urlTab = new URLSearchParams(location.search).get('tab'); } catch (e) {}
    var saved = null;
    try { saved = localStorage.getItem('admin_active_tab'); } catch (e) {}
    var defaultBtn = document.querySelector('.tab-btn[data-default]');
    var initialTab = (urlTab && document.querySelector('.tab-pane[data-pane="' + urlTab + '"]')) ? urlTab
        : (saved && document.querySelector('.tab-pane[data-pane="' + saved + '"]')) ? saved
        : defaultBtn ? defaultBtn.getAttribute('data-tab')
        : tabBtns[0].getAttribute('data-tab');
    activateTab(initialTab);
  }

  var tagPickers = document.querySelectorAll('.tag-picker');
  tagPickers.forEach(function (picker) {
    var max = parseInt(picker.getAttribute('data-max'), 10) || 1;
    var boxes = picker.querySelectorAll('input[type=checkbox]');
    function refresh() {
      var count = picker.querySelectorAll('input[type=checkbox]:checked').length;
      boxes.forEach(function (box) {
        if (!box.checked) box.disabled = count >= max;
      });
    }
    boxes.forEach(function (box) { box.addEventListener('change', refresh); });
    refresh();
  });

  var versionBadge = document.getElementById('versionBadge');
  if (versionBadge) {
    fetch(versionBadge.getAttribute('data-check-url'))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || data.code !== 1) return;
        if (data.hasUpdate) {
          versionBadge.classList.add('has-update');
          var badgeIcon = versionBadge.querySelector('i');
          if (badgeIcon) badgeIcon.className = 'fas fa-arrow-up';
        }
        if (data.title) versionBadge.setAttribute('title', data.title);
      })
      .catch(function () {});
  }
})();
