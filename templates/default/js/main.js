document.addEventListener('DOMContentLoaded', function() {
    const L = window.lang || {};
    const toggle = document.getElementById('mobileToggle');
    const nav = document.querySelector('.main-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function() {
            nav.classList.toggle('show');
        });
    }

    const userMenu = document.querySelector('.user-menu');
    if (userMenu) {
        let hideTimeout;
        const dropdown = userMenu.querySelector('.user-dropdown');
        function showDropdown() {
            if (hideTimeout) clearTimeout(hideTimeout);
            if (dropdown) dropdown.style.display = 'block';
        }
        function hideDropdown() {
            if (hideTimeout) clearTimeout(hideTimeout);
            hideTimeout = setTimeout(() => {
                if (dropdown) dropdown.style.display = 'none';
            }, 200);
        }
        userMenu.addEventListener('mouseenter', showDropdown);
        userMenu.addEventListener('mouseleave', hideDropdown);
        if (dropdown) {
            dropdown.addEventListener('mouseenter', showDropdown);
            dropdown.addEventListener('mouseleave', hideDropdown);
        }
    }

    function bookshelfCsrf() {
        return (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    }
    function markInShelf(btn, novelId) {
        btn.classList.remove('add-bookshelf');
        btn.classList.add('in-bookshelf');
        btn.dataset.novelId = novelId;
        btn.innerHTML = '<i class="fas fa-check"></i> ' + (L.already_in_bookshelf || 'In bookshelf');
    }

    document.body.addEventListener('click', function(e) {
        const inBtn = e.target.closest('.in-bookshelf');
        if (inBtn) {
            e.preventDefault();
            UI.toast(L.bookshelf_exists || 'Already in your bookshelf', 'info');
            return;
        }
        const addBtn = e.target.closest('.add-bookshelf');
        if (!addBtn) return;
        e.preventDefault();
        const novelId = addBtn.dataset.novelId;
        if (!novelId) return;
        addBtn.disabled = true;
        fetch('/api/bookshelf', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'novel_id=' + encodeURIComponent(novelId) + '&action=add&_csrf=' + encodeURIComponent(bookshelfCsrf())
        })
        .then(res => res.json())
        .then(resp => {
            const data = resp.data || {};
            if (resp.code === 0 && data.action === 'added') {
                UI.toast(L.bookshelf_added || 'Added to bookshelf', 'success');
                markInShelf(addBtn, novelId);
            } else if (resp.code === 0 && data.action === 'exists') {
                UI.toast(L.bookshelf_exists || 'Already in your bookshelf', 'info');
                markInShelf(addBtn, novelId);
            } else {
                UI.alert({message: resp.msg || resp.message || ((L.error_prefix || 'Error ') + resp.code), type: 'error'});
            }
        })
        .catch(() => UI.alert({message: L.request_failed || 'Request failed, please retry later', type: 'error'}))
        .finally(() => { addBtn.disabled = false; });
    });

    document.body.addEventListener('click', function(e) {
        const removeBtn = e.target.closest('.remove-bookshelf');
        if (removeBtn) {
            e.preventDefault();
            UI.confirm({message: L.confirm_remove || 'Remove this item?'}).then(function(ok) {
                if (!ok) return;
                const novelId = removeBtn.dataset.novelId;
                const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                fetch('/api/bookshelf', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'novel_id=' + novelId + '&_csrf=' + encodeURIComponent(csrf)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.code === 0) {
                        const item = removeBtn.closest('.bookshelf-item');
                        if (item) item.remove();
                        if (document.querySelectorAll('.bookshelf-item').length === 0) location.reload();
                    } else {
                        UI.alert({message: data.msg || data.message || ((L.error_prefix || 'Error ') + data.code), type: 'error'});
                    }
                });
            });
        }
    });

    const chapterContent = document.getElementById('chapterContent');
    if (chapterContent) {
        let fontSize = parseInt(localStorage.getItem('readFontSize') || '18');
        let theme = localStorage.getItem('readTheme') || 'light';
        const fontInc = document.getElementById('fontIncrease');
        const fontDec = document.getElementById('fontDecrease');
        const themeToggle = document.getElementById('themeToggle');
        function applyFont() {
            if (chapterContent) chapterContent.style.fontSize = fontSize + 'px';
            localStorage.setItem('readFontSize', fontSize);
        }
        function applyTheme() {
            if (theme === 'dark') {
                document.body.classList.add('dark-read');
                if (themeToggle) themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            } else {
                document.body.classList.remove('dark-read');
                if (themeToggle) themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
            }
            localStorage.setItem('readTheme', theme);
        }
        if (fontInc) fontInc.addEventListener('click', () => { fontSize = Math.min(28, fontSize + 2); applyFont(); });
        if (fontDec) fontDec.addEventListener('click', () => { fontSize = Math.max(12, fontSize - 2); applyFont(); });
        if (themeToggle) themeToggle.addEventListener('click', () => { theme = theme === 'light' ? 'dark' : 'light'; applyTheme(); });
        applyFont();
        applyTheme();
    }

    const rankTabs = document.querySelectorAll('.rank-tab');
    if (rankTabs.length) {
        rankTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const type = this.dataset.type;
                if (type) window.location.href = (window.ENOVEL_URL.rankTpl || '/rank/@@TYPE@@').replace('@@TYPE@@', type);
            });
        });
    }

    const buyBtn = document.getElementById('buyChapterBtn');
    if (buyBtn) {
        buyBtn.addEventListener('click', function() {
            const chapterId = buyBtn.dataset.chapterId;
            const price = buyBtn.dataset.price;
            const autoCheck = document.getElementById('autoBuyCheck');
            const autoBuy = (autoCheck && autoCheck.checked) ? 1 : 0;
            UI.confirm({
                title: L.purchase_chapter || 'Buy this chapter',
                message: (L.purchase_confirm || 'Spend %s to unlock this chapter?').replace('%s', price),
                okText: L.confirm_purchase || 'Confirm purchase'
            }).then(function(ok) {
                if (!ok) return;
                buyBtn.disabled = true;
                fetch('/api/purchase', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'chapter_id=' + encodeURIComponent(chapterId) + '&auto_buy=' + autoBuy + '&_csrf=' + encodeURIComponent(bookshelfCsrf())
                })
                .then(res => res.json())
                .then(resp => {
                    const d = resp.data || {};
                    if (resp.code === 0 && d.ok) {
                        UI.toast(d.msg || L.purchase_success || 'Purchase successful', 'success');
                        setTimeout(function() { location.reload(); }, 800);
                    } else {
                        UI.alert({ title: L.purchase_tip || 'Purchase', message: d.msg || resp.msg || L.purchase_failed || 'Purchase failed', type: 'error' });
                        buyBtn.disabled = false;
                    }
                })
                .catch(function() {
                    UI.alert({ message: L.request_failed || 'Request failed, please retry later', type: 'error' });
                    buyBtn.disabled = false;
                });
            });
        });
    }

    document.body.addEventListener('click', function(e) {
        const sendBtn = e.target.closest('#sendCodeBtn');
        if (!sendBtn) return;
        e.preventDefault();
        const emailInput = document.getElementById('email');
        const captchaInput = document.getElementById('captchaInput');
        const email = emailInput ? emailInput.value : '';
        const captcha = captchaInput ? captchaInput.value : '';
        if (!email) {
            UI.alert({message: L.fill_email_first || 'Please fill in email first', type: 'error'});
            return;
        }
        if (!captcha) {
            UI.alert({message: L.captcha_error || 'Captcha error', type: 'error'});
            return;
        }
        sendBtn.disabled = true;
        const originalText = sendBtn.innerText;
        sendBtn.innerText = L.sending || 'Sending...';
        const url = window.location.pathname.includes('register')
            ? '?send_code=1&email=' + encodeURIComponent(email) + '&captcha=' + encodeURIComponent(captcha)
            : '?step=send_code&email=' + encodeURIComponent(email) + '&captcha=' + encodeURIComponent(captcha);
        fetch(url)
            .then(res => res.json ? res.json() : res.text())
            .then(data => {
                let msg = '';
                if (typeof data === 'object') msg = data.code === 1 ? (L.send_success || 'Success') : (L.send_fail || 'Failed');
                else msg = data;
                UI.alert({message: msg, type: typeof data === 'object' && data.code === 1 ? 'success' : 'error'});
                if (typeof data === 'object' && data.code === 1) {
                    let countdown = 60;
                    const timer = setInterval(() => {
                        countdown--;
                        if (countdown <= 0) {
                            clearInterval(timer);
                            sendBtn.disabled = false;
                            sendBtn.innerText = originalText;
                        } else {
                            sendBtn.innerText = countdown + (L.seconds_retry || 's');
                        }
                    }, 1000);
                } else {
                    sendBtn.disabled = false;
                    sendBtn.innerText = originalText;
                }
                const captchaImg = document.getElementById('captchaImg');
                if (captchaImg) captchaImg.src = '/api/captcha?' + Math.random();
            })
            .catch(err => {
                UI.alert({message: L.network_error || 'Network error', type: 'error'});
                sendBtn.disabled = false;
                sendBtn.innerText = originalText;
            });
    });
});

(function() {
    const searchBtn = document.getElementById('searchBtn');
    const overlay = document.getElementById('searchOverlay');
    const closeBtn = document.getElementById('closeSearchBtn');
    const submitBtn = document.getElementById('submitSearch');
    const keywordInput = document.getElementById('searchKeyword');
    if (!searchBtn || !overlay) return;
    function openSearch() {
        overlay.classList.add('active');
        if (keywordInput) keywordInput.focus();
    }
    function closeSearch() {
        overlay.classList.remove('active');
        if (keywordInput) keywordInput.value = '';
    }
    function doSearch() {
        let keyword = keywordInput ? keywordInput.value.trim() : '';
        const L = window.lang || {};
        if (keyword === '') {
            UI.alert({message: L.search_keyword_required || 'Please enter a keyword', type: 'error'});
            return;
        }
        window.location.href = (window.ENOVEL_URL.search || '/search') + '?q=' + encodeURIComponent(keyword);
    }
    searchBtn.addEventListener('click', openSearch);
    if (closeBtn) closeBtn.addEventListener('click', closeSearch);
    if (submitBtn) submitBtn.addEventListener('click', doSearch);
    if (keywordInput) keywordInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') doSearch();
    });
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeSearch();
    });
})();

(function() {
    document.querySelectorAll('.tag-picker').forEach(function(picker) {
        const max = parseInt(picker.getAttribute('data-max'), 10) || 1;
        const boxes = picker.querySelectorAll('input[type=checkbox]');
        function refresh() {
            const count = picker.querySelectorAll('input[type=checkbox]:checked').length;
            boxes.forEach(function(box) {
                if (!box.checked) box.disabled = count >= max;
            });
        }
        boxes.forEach(function(box) { box.addEventListener('change', refresh); });
        refresh();
    });
})();