{include file="header.tpl"}
<div class="read-container">
    <div class="read-header">
        <div class="breadcrumb">
            {hook name="read_top_left"}
            <a href="{url route="home"}">{t name="home"}</a> /
            <a href="{url route="novel" id=$novel.id}">{$novel.title}</a> /
            <span>{$chapter.title}</span>
        </div>
        <div class="read-controls">
            {hook name="read_top_right"}
            <button id="fontDecrease" class="ctrl-btn" title="{t name="font_decrease"}"><i class="fas fa-font"></i>-</button>
            <button id="fontIncrease" class="ctrl-btn" title="{t name="font_increase"}"><i class="fas fa-font"></i>+</button>
            <button id="themeToggle" class="ctrl-btn" title="{t name="night_mode"}"><i class="fas fa-moon"></i></button>
        </div>
    </div>
    <article class="chapter-content" id="chapterContent">
        <h1 class="chapter-title">{$chapter.title} {hook name="read_title_extra"}</h1>
        {hook name="read_title_after"}
        <div class="content-body" id="contentBody">
            {ad name="read_top"}
            {hook name="read_content_extra"}
            {if $perm.allowed}
            <div id="fullContent">
                {$content nofilter}
            </div>
            {hook name="read_content_after"}
            {elseif $perm.need_pay}
            {if $perm.preview}
            <div class="content-preview" style="color:#444;margin-bottom:14px;">
                {$content nofilter}
            </div>
            <div style="text-align:center;color:#999;font-size:0.9rem;margin:10px 0 18px;border-top:1px dashed #ccc;padding-top:14px;">
                {t name="vip_preview_divider"}
            </div>
            {/if}
            <div id="purchasePanel" class="purchase-panel">
                <div class="purchase-info">
                    <i class="fas fa-lock" style="font-size:2rem;color:#b8860b;"></i>
                    <h3>{t name="vip_chapter_price_msg"}</h3>
                    <p>{t name="need_pay_msg"}<strong>{$perm.price}</strong>{$point_name}</p>
                    <p>{t name="current_point_label"}{$point_name}：<strong>{$userGold|number_format}</strong></p>
                    {if $purchaseError}
                    <div class="error" id="purchaseError">{$purchaseError}</div>
                    {/if}
                    {if $perm.auto_purchase_failed}
                    <div class="error">{$perm.auto_purchase_failed}{t name="manual_buy_after_recharge"}</div>
                    {/if}
                    <label class="auto-buy-option" for="autoBuyCheck">
                        <input type="checkbox" id="autoBuyCheck" {if $perm.auto_on}checked{/if}>
                        <span>{t name="auto_purchase_label"}</span>
                    </label>
                    <button type="button" id="buyChapterBtn" class="btn-primary" data-chapter-id="{$chapter.id}" data-novel-id="{$novel.id}" data-price="{$perm.price}" style="padding:12px 40px;">
                        <i class="fas fa-shopping-cart"></i> {t name="purchase_full_action"}（{$perm.price} {$point_name}）
                    </button>
                    <div style="margin-top:15px;font-size:0.9rem;">
                        <a href="{url route="user" action="vip"}" target="_blank">{t name="vip_recharge"}</a>
                        | <a href="{url route="user" action="recharge"}" target="_blank">{t name="recharge_action"}{$point_name}</a>
                    </div>
                </div>
            </div>
            {else}
            {if $perm.preview}
            <div class="content-preview" style="color:#444;margin-bottom:14px;">
                {$content nofilter}
            </div>
            <div style="text-align:center;color:#999;font-size:0.9rem;margin:10px 0 18px;border-top:1px dashed #ccc;padding-top:14px;">
                {t name="login_preview_divider"}
            </div>
            {/if}
            <div id="loginPanel" class="login-panel">
                <div class="login-info">
                    <i class="fas fa-user-lock" style="font-size:2.5rem;color:#b8860b;"></i>
                    <h3>
                        {if $chapter.is_vip}
                        {t name="vip_chapter_need_login"}
                        {else}
                        {t name="need_login_to_read"}
                        {/if}
                    </h3>
                    <p>{t name="guest_preview_tip"}</p>
                    <div class="login-actions">
                        <a href="{url route="user" action="login"}" class="btn-primary">
                            <i class="fas fa-sign-in-alt"></i> {t name="login_now"}
                        </a>
                        <a href="{url route="user" action="register"}" class="btn-outline">
                            <i class="fas fa-user-plus"></i> {t name="register_now"}
                        </a>
                    </div>
                </div>
            </div>
            {/if}
            {ad name="read_bottom"}
        </div>
    </article>
    <div class="chapter-nav">
        {hook name="read_nav_left"}
        {if $prev}
        <a href="{url route="read" novel=$novel.id chapter=$prev.id}" class="nav-btn"><i class="fas fa-chevron-left"></i> {t name="previous_chapter"}</a>
        {else}
        <span class="nav-btn disabled"><i class="fas fa-chevron-left"></i> {t name="previous_chapter"}</span>
        {/if}
        <a href="{url route="novel" id=$novel.id}" class="nav-btn"><i class="fas fa-list"></i> {t name="chapter_list"}</a>
        {if $next}
        <a href="{url route="read" novel=$novel.id chapter=$next.id}" class="nav-btn">{t name="next_chapter"} <i class="fas fa-chevron-right"></i></a>
        {else}
        <span class="nav-btn disabled">{t name="next_chapter"} <i class="fas fa-chevron-right"></i></span>
        {/if}
        {hook name="read_nav_right"}
    </div>
    {hook name="read_nav_after"}
    {if $perm.auto_on}
    <div class="auto-float" id="autoFloatBtn" data-novel-id="{$novel.id}" title="{t name="close_auto_purchase_tip"}">
        <i class="fas fa-bolt"></i><span>{t name="auto_purchasing"}</span>
    </div>
    {/if}
</div>
<style>
.preview-content {
    background: #faf7f2;
    padding: 20px;
    border-radius: 6px;
    margin-bottom: 16px;
    border-left: 3px solid #b8860b;
    font-size: 0.95em;
    line-height: 1.8;
    color:#555;
}
.purchase-panel {
    background: #fff;
    border: 1px solid #d4b45a;
    border-radius: 6px;
    padding: 30px 20px;
    text-align: center;
    margin: 16px 0;
}
.purchase-info h3 {
    margin: 10px 0;
    color: #333;
    font-size:18px;
}
.purchase-info p {
    color: #666;
    margin: 6px 0;
}
.purchase-info strong { color:#b8860b; }
.purchase-info .btn-primary {
    background: #333;
    border: none;
    font-weight: 600;
    border-radius:4px;
    display: block;
    width: fit-content;
    margin: 14px auto 0;
}
.purchase-info .btn-primary:hover { background:#b8860b; }
.auto-buy-option {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-top: 14px;
    font-size: 0.95rem;
    color: #333;
    cursor: pointer;
    user-select: none;
}
.auto-buy-option input[type="checkbox"] { width: 16px; height: 16px; accent-color: #b8860b; cursor: pointer; }
.auto-float {
    position: fixed;
    right: 24px;
    bottom: 96px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: #fff;
    border: 1px solid #d4b45a;
    border-radius: 999px;
    color: #b8860b;
    font-size: 0.9rem;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
    z-index: 900;
    user-select: none;
}
.auto-float:hover { background: #b8860b; color: #fff; border-color: #b8860b; }
.auto-float i { font-size: 0.95rem; }
.login-panel {
    background: #fff;
    border: 1px solid #ececec;
    border-radius: 6px;
    padding: 30px 20px;
    text-align: center;
    margin: 16px 0;
}
.login-info h3 {
    margin: 12px 0;
    color: #333;
}
.login-info p {
    color: #888;
    margin: 8px 0 20px;
    font-size: 0.95rem;
}
.login-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}
.login-actions .btn-primary {
    background: #333;
    border: none;
    font-weight: 600;
    padding: 10px 28px;
    border-radius: 4px;
    color: #fff;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.login-actions .btn-primary:hover { background:#b8860b; }
.login-actions .btn-outline {
    padding: 10px 28px;
    border-radius: 4px;
    border: 1px solid #ececec;
    color: #666;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fff;
}
.login-actions .btn-outline:hover {
    border-color: #b8860b;
    color: #b8860b;
}
#fullContent {
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
<script>
(function() {
    const content = document.getElementById('chapterContent');
    let size = parseInt(localStorage.getItem('readFontSize') || '18', 10);
    function applySize() {
        content.style.fontSize = size + 'px';
    }
    applySize();
    const up = document.getElementById('fontIncrease');
    const down = document.getElementById('fontDecrease');
    if (up) up.addEventListener('click', function() {
        if (size < 30) { size++; localStorage.setItem('readFontSize', size); applySize(); }
    });
    if (down) down.addEventListener('click', function() {
        if (size > 12) { size--; localStorage.setItem('readFontSize', size); applySize(); }
    });
    const toggle = document.getElementById('themeToggle');
    if (toggle) {
        if (localStorage.getItem('readTheme') === 'night') document.body.classList.add('night-mode');
        toggle.addEventListener('click', function() {
            document.body.classList.toggle('night-mode');
            localStorage.setItem('readTheme', document.body.classList.contains('night-mode') ? 'night' : 'day');
        });
    }
})();
(function() {
    const floatBtn = document.getElementById('autoFloatBtn');
    if (!floatBtn) return;
    function csrf() { return (document.querySelector('meta[name="csrf-token"]') || {}).content || ''; }
    floatBtn.addEventListener('click', function() {
        const novelId = floatBtn.dataset.novelId;
        UI.confirm({
            title: '{t name="close_auto_purchase"}',
            message: '{t name="confirm_close_auto_purchase"}',
            okText: '{t name="close_word"}'
        }).then(function(ok) {
            if (!ok) return;
            floatBtn.style.pointerEvents = 'none';
            fetch('/api/auto_purchase_toggle', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'novel_id=' + encodeURIComponent(novelId) + '&enabled=0&_csrf=' + encodeURIComponent(csrf())
            })
            .then(r => r.json())
            .then(resp => {
                if (resp.code === 0) {
                    UI.toast('{t name="auto_purchase_closed"}', 'success');
                    floatBtn.style.display = 'none';
                    const chk = document.getElementById('autoBuyCheck');
                    if (chk) chk.checked = false;
                } else {
                    UI.alert({ message: (resp.data && resp.data.msg) || resp.msg || '{t name="operation_failed"}', type: 'error' });
                    floatBtn.style.pointerEvents = '';
                }
            })
            .catch(function() {
                UI.alert({ message: '{t name="network_error_retry"}', type: 'error' });
                floatBtn.style.pointerEvents = '';
            });
        });
    });
})();
</script>
{include file="footer.tpl"}
