{include file="header.tpl"}
<div class="container">
    <div class="filter-section">
        <div class="filter-group">
            <div class="filter-label">{t name="category_label"}</div>
            <div class="filter-options" id="categoryOptions">
                <a href="{url route="library" tag_id=$currentTag status=$filters.status order_by=$filters.order_by vip_filter=$filters.vip_filter}" class="filter-option {if $currentCategory == 0}active{/if}">{t name="all_categories"}</a>
                {foreach $categories as $top}
                <a href="{url route="library" category=$top.id tag_id=$currentTag status=$filters.status order_by=$filters.order_by vip_filter=$filters.vip_filter}" class="filter-option {if $currentCategory == $top.id}active{/if}">{$top.name}</a>
                {/foreach}
            </div>
        </div>
        {foreach $categories as $top}
        {if $top.children}
            {assign var=showSub value=false}
            {if $currentCategory == $top.id}{assign var=showSub value=true}{/if}
            {foreach $top.children as $sub}
                {if $currentCategory == $sub.id}{assign var=showSub value=true}{/if}
            {/foreach}
            {if $showSub}
        <div class="filter-group subcategory-row">
            <div class="filter-label">└ {t name="subcategory"}</div>
            <div class="filter-options">
                <a href="{url route="library" category=$top.id tag_id=$currentTag status=$filters.status order_by=$filters.order_by vip_filter=$filters.vip_filter}" class="filter-option {if $currentCategory == $top.id}active{/if}">{t name="all_label"}</a>
                {foreach $top.children as $sub}
                <a href="{url route="library" category=$sub.id tag_id=$currentTag status=$filters.status order_by=$filters.order_by vip_filter=$filters.vip_filter}" class="filter-option {if $currentCategory == $sub.id}active{/if}">{$sub.name}</a>
                {/foreach}
            </div>
        </div>
            {/if}
        {/if}
        {/foreach}
        {if $allTags}
        {assign var=tagLimit value=8}
        {assign var=tagExpanded value=false}
        {foreach $allTags as $tag}
        {if $tag@index >= $tagLimit && $currentTag == $tag.id}{assign var=tagExpanded value=true}{/if}
        {/foreach}
        <div class="filter-group">
            <div class="filter-label">{t name="tag_filter_label"}</div>
            <div class="filter-options tag-collapse{if $tagExpanded} is-open{/if}" id="tagOptions">
                <a href="{url route="library" category=$currentCategory status=$filters.status order_by=$filters.order_by vip_filter=$filters.vip_filter}" class="filter-option {if $currentTag == 0}active{/if}">{t name="all_tags"}</a>
                {foreach $allTags as $tag}
                <a href="{url route="library" category=$currentCategory tag_id=$tag.id status=$filters.status order_by=$filters.order_by vip_filter=$filters.vip_filter}" class="filter-option {if $currentTag == $tag.id}active{/if}{if $tag@index >= $tagLimit} tag-more{/if}">{$tag.name}</a>
                {/foreach}
                {if count($allTags) > $tagLimit}
                <button type="button" class="filter-toggle"><i class="fas fa-chevron-down"></i><span class="ft-text">{t name="expand_tags"}</span></button>
                {/if}
            </div>
        </div>
        {/if}
        <div class="filter-group">
            <div class="filter-label">{t name="status_label"}</div>
            <div class="filter-options" id="statusOptions">
                {foreach $statusLabels as $sv => $sl}
                <a href="{url route="library" category=$currentCategory tag_id=$currentTag status=$sv order_by=$filters.order_by vip_filter=$filters.vip_filter}" class="filter-option {if $filters.status == $sv}active{/if}">{$sl}</a>
                {/foreach}
            </div>
        </div>
        <div class="filter-group">
            <div class="filter-label">{t name="work_type_label"}</div>
            <div class="filter-options" id="vipOptions">
                {foreach $vipLabels as $vv => $vl}
                <a href="{url route="library" category=$currentCategory tag_id=$currentTag status=$filters.status order_by=$filters.order_by vip_filter=$vv}" class="filter-option {if $filters.vip_filter == $vv}active{/if}">{$vl}</a>
                {/foreach}
            </div>
        </div>
        <div class="filter-group">
            <div class="filter-label">{t name="sort_by"}</div>
            <div class="filter-options" id="sortOptions">
                {foreach $orderLabels as $ov => $ol}
                <a href="{url route="library" category=$currentCategory tag_id=$currentTag status=$filters.status vip_filter=$filters.vip_filter order_by=$ov}" class="filter-option {if $filters.order_by == $ov}active{/if}">{$ol}</a>
                {/foreach}
                {hook name="library_sort_extra"}
            </div>
        </div>
        <div class="filter-group">
            <div class="filter-label">{t name="word_range"}</div>
            <div class="word-range-inputs">
                <input type="number" id="minWords" placeholder="{t name="min_words"}">
                <span>—</span>
                <input type="number" id="maxWords" placeholder="{t name="max_words"}">
                <button type="button" id="applyWordsBtn" class="btn-icon" title="{t name="search"}"><i class="fas fa-check"></i></button>
                <button type="button" id="resetWordsBtn" class="btn-icon" title="{t name="reset_button"}"><i class="fas fa-undo-alt"></i></button>
            </div>
        </div>
        {hook name="library_filter_extra"}
    </div>
    {ad name="library_top"}
    <div class="result-stats">
        <span class="stats-badge">
            <i class="fas fa-book-open"></i>
            {t name="total_reward"} <strong>{$total|number_format}</strong> {t name="novel"}
        </span>
        <div class="view-toggle">
            <button class="view-btn active" data-view="list"><i class="fas fa-list"></i> {t name="list"}</button>
            <button class="view-btn" data-view="grid"><i class="fas fa-th"></i> {t name="grid"}</button>
        </div>
    </div>
    <div class="novel-list-container list-view" id="novelContainer">
        {if $novels}
            {foreach $novels as $n}
            <div class="novel-item">
                <div class="novel-cover">
                    <a href="{url route="novel" id=$n.id}">
                        <img src="/{$n.cover|default:'assets/images/default_cover.jpg'}" alt="{$n.title}">
                    </a>
                    {if $n.is_vip}<span class="vip-badge">VIP</span>{/if}
                </div>
                <div class="novel-info">
                    <h3 class="novel-title">
                        <a href="{url route="novel" id=$n.id}">{$n.title}</a>
                    </h3>
                    <p class="novel-author">{t name="author_label"}{t name="label_separator"}{$n.display_name}</p>
                    <div class="novel-meta">
                        <span><i class="fas fa-eye"></i> {$n.views|number_format}</span>
                        <span><i class="fas fa-file-alt"></i> {$n.total_words|number_format} {t name="words_unit"}</span>
                        <span><i class="fas fa-bookmark"></i> {$n.favorites|number_format} {t name="favorites"}</span>
                        <span class="status-badge {if $n.status == 0}ongoing{else}finished{/if}">
                            {if $n.status == 0}{t name="ongoing"}{else}{t name="finished"}{/if}
                        </span>
                    </div>
                    <p class="novel-desc">{$n.description|truncate:120}</p>
                    <div class="novel-last">
                        {if $n.last_chapter_id}
                        <span>
                            <i class="fas fa-book-open"></i> {t name="latest_chapter"}：
                            <a href="{url route="read" novel=$n.id chapter=$n.last_chapter_id}">{$n.last_chapter_title}</a>
                            {if $n.last_chapter_is_vip}
                                <span class="vip-chapter-badge" style="background:#f59e0b;color:#fff;padding:0 6px;border-radius:10px;font-size:0.6rem;margin-left:4px;">{t name="vip_label"}</span>
                            {/if}
                        </span>
                        {/if}
                        <span><i class="fas fa-calendar-alt"></i> {t name="last_update"}：{$n.last_chapter_time|default:$n.updated_at|date_format:'Y-m-d'}</span>
                    </div>
                    {hook name="library_item" item=$n}
                </div>
            </div>
            {/foreach}
        {else}
            <div class="empty-state"><i class="fas fa-book-open"></i><p>{t name="no_novels"}</p></div>
        {/if}
    </div>
    {if $totalPages > 1}
        {url route="library" category=$currentCategory tag_id=$currentTag status=$filters.status vip_filter=$filters.vip_filter order_by=$filters.order_by page="__PAGE__" assign="pageTpl"}
        {pagination page=$page totalPages=$totalPages urlTpl=$pageTpl|replace:"__PAGE__":"@@PAGE@@"}
    {/if}
</div>
<script>
(function() {
    const box = document.getElementById('tagOptions');
    if (!box) return;
    const btn = box.querySelector('.filter-toggle');
    if (!btn) return;
    const txt = btn.querySelector('.ft-text');
    const L = window.lang || {};
    const expandText = L.expand_tags || '展开';
    const collapseText = L.collapse_tags || '收起';
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const open = box.classList.toggle('is-open');
        if (txt) txt.textContent = open ? collapseText : expandText;
    });
})();
(function() {
    const container = document.getElementById('novelContainer');
    const viewBtns = document.querySelectorAll('.view-btn');
    const savedView = localStorage.getItem('libraryView') || 'list';
    function setView(view) {
        if (view === 'grid') {
            container.classList.add('grid-view');
            container.classList.remove('list-view');
        } else {
            container.classList.add('list-view');
            container.classList.remove('grid-view');
        }
        viewBtns.forEach(btn => {
            if (btn.dataset.view === view) { btn.classList.add('active'); } else { btn.classList.remove('active'); }
        });
        localStorage.setItem('libraryView', view);
    }
    setView(savedView);
    viewBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            setView(this.dataset.view);
        });
    });
})();
(function() {
    const minInput = document.getElementById('minWords');
    const maxInput = document.getElementById('maxWords');
    const applyBtn = document.getElementById('applyWordsBtn');
    const resetWordsBtn = document.getElementById('resetWordsBtn');
    function buildPathWithWords(min, max) {
        const seg = window.location.pathname.replace(/\.html$/, '');
        const pairs = [];
        const cur = seg.split('/').filter(Boolean);
        const seen = {};
        for (let i = 1; i < cur.length; i += 2) {
            if (cur[i] && cur[i+1]) seen[cur[i]] = cur[i+1];
        }
        if (min > 0) seen['min_words'] = min; else delete seen['min_words'];
        if (max > 0) seen['max_words'] = max; else delete seen['max_words'];
        delete seen['page'];
        const libBase = window.ENOVEL_URL.library || '/library';
        const fallbackSeg = (libBase.split('/').filter(Boolean)[0]) || 'library';
        const suffix = libBase.endsWith('.html') ? '.html' : '';
        let path = '/' + (cur[0] || fallbackSeg);
        for (const [k, v] of Object.entries(seen)) path += '/' + k + '/' + encodeURIComponent(v);
        return path + suffix;
    }
    function applyWordRange() {
        let minVal = minInput.value.trim() === '' ? 0 : parseInt(minInput.value, 10);
        let maxVal = maxInput.value.trim() === '' ? 0 : parseInt(maxInput.value, 10);
        if (isNaN(minVal)) minVal = 0;
        if (isNaN(maxVal)) maxVal = 0;
        if (minVal < 0) minVal = 0;
        if (maxVal < 0) maxVal = 0;
        window.location.href = buildPathWithWords(minVal, maxVal);
    }
    function resetWordRange() {
        minInput.value = '';
        maxInput.value = '';
        applyWordRange();
    }
    if (applyBtn) applyBtn.addEventListener('click', applyWordRange);
    if (resetWordsBtn) resetWordsBtn.addEventListener('click', resetWordRange);
    minInput?.addEventListener('keypress', function(e) { if (e.key === 'Enter') applyWordRange(); });
    maxInput?.addEventListener('keypress', function(e) { if (e.key === 'Enter') applyWordRange(); });
})();
</script>
{include file="footer.tpl"}
