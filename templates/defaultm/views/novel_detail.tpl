{include file="header.tpl"}
{assign var=chCount value=$chapterTotal|default:0}
{foreach $chaptersByVolume as $volId => $group}
{if $group.chapters && !$firstChap}{assign var=firstChap value=$group.chapters[0]}{/if}
{/foreach}
<div class="container novel-detail-page">
    <div class="novel-detail-grid">
        <div class="detail-cover">
            <img src="/{$novel.cover|default:'assets/images/default_cover.jpg'}" alt="{$novel.title}">
        </div>
        <div class="detail-info">
            <h1>
                {$novel.title}
                {if $novel.is_vip}
                    <span class="vip-badge" style="background:#f59e0b;color:#fff;padding:2px 12px;border-radius:20px;font-size:0.8rem;">{t name="vip_work"}</span>
                {/if}
            </h1>
            <div class="meta-row">
                <span>
                    <i class="fas fa-user"></i>
                    {if $novel.user_id > 0}
                    <a href="{url route="author" id=$novel.user_id}" class="author-link">{$novel.display_name}</a>
                    {else}
                    <a href="{url route="search"}" class="author-link">{$novel.display_name}</a>
                    {/if}
                </span>
                <span style="color:#ccc;">|</span>
                <span><i class="fas fa-tag"></i> {$novel.category_name|default:'-'}</span>
                <span style="color:#ccc;">|</span>
                <span><i class="fas fa-{if $novel.status}check-circle{else}sync-alt{/if}"></i>
                    {if $novel.status}{t name="finished"}{else}{t name="ongoing"}{/if}
                </span>
                {if $novel.is_vip}
                <span style="color:#ccc;">|</span>
                <span><i class="fas fa-crown" style="color:#f59e0b;"></i> {t name="vip_label"}</span>
                {/if}
                {hook name="novel_meta_extra"}
            </div>
            <div class="stats-inline">
                <span><i class="fas fa-file-alt"></i> {t name="words"}{t name="label_separator"}<strong>{$novel.total_words|number_format}</strong></span>
                <span style="color:#ccc;">|</span>
                <span><i class="fas fa-eye"></i> {t name="reads_count_label"}{t name="label_separator"}<strong>{$novel.views|number_format}</strong></span>
                <span style="color:#ccc;">|</span>
                <span><i class="fas fa-star"></i> {t name="favorites_count_label"}{t name="label_separator"}<strong><span id="favoritesDisplay">{$novel.favorites|number_format}</span></strong></span>
                <span style="color:#ccc;">|</span>
                <span><i class="fas fa-list"></i> {t name="chapters_count_label"}{t name="label_separator"}<strong>{$chCount}</strong></span>
                {hook name="novel_stats_extra"}
            </div>
            {if $tags}
            <div class="novel-tags-row">
                <span class="novel-tags-label"><i class="fas fa-bookmark"></i> {t name="tags_label_field"}</span>
                <div class="novel-tags-list">
                    {foreach $tags as $tag}
                    <a href="{url route="library" tag_id=$tag.id}" class="novel-tag-link">{$tag.name}</a>
                    {/foreach}
                </div>
            </div>
            {/if}
            {hook name="novel_info_extra"}
        </div>
        <div class="action-buttons">
            {if $firstChap}
            <a href="{url route="read" novel=$novel.id chapter=$firstChap.id}" class="btn-primary btn-block">
                <i class="fas fa-book-open"></i> {t name="start_reading"}
            </a>
            {else}
            <span class="btn-primary btn-block" style="opacity:0.5;cursor:not-allowed;display:flex;align-items:center;justify-content:center;gap:8px;">
                <i class="fas fa-book-open"></i> {t name="no_chapters"}
            </span>
            {/if}
            {if $inBookshelf}
            <button type="button" class="btn-outline btn-block in-bookshelf" data-novel-id="{$novel.id}">
                <i class="fas fa-check"></i> {t name="already_in_bookshelf"}
            </button>
            {else}
            <button type="button" class="btn-outline btn-block add-bookshelf" data-novel-id="{$novel.id}">
                <i class="fas fa-bookmark"></i> {t name="add_to_bookshelf"}
            </button>
            {/if}
            {hook name="novel_actions_extra"}
        </div>
        <div class="description detail-description">
            <h3>{t name="description"}</h3><div class="description-content" style="white-space:pre-line;">{$novel.description|trim}</div>
        </div>
    </div>
    {ad name="novel_detail_top"}
    <div class="chapters-section">
        <div class="section-header">
            <h2><i class="fas fa-list-ul"></i> {'chapters_count'|t|sprintf:$chCount}</h2>
            <div class="section-header-tools">
                {if $chapterTotalPages > 1}
                <div class="chapter-pager">
                    {if $chapterPage > 1}
                    <a class="pager-btn" href="{url route="novel" id=$novel.id page=$chapterPage-1}"><i class="fas fa-chevron-left"></i></a>
                    {/if}
                    {section name=i start=1 loop=$chapterTotalPages+1}
                        {assign var=p value=$smarty.section.i.index}
                        {if $p >= $chapterPage-2 && $p <= $chapterPage+2}
                        <a class="pager-btn{if $p == $chapterPage} current{/if}" href="{url route="novel" id=$novel.id page=$p}">{$p}</a>
                        {/if}
                    {/section}
                    {if $chapterPage < $chapterTotalPages}
                    <a class="pager-btn" href="{url route="novel" id=$novel.id page=$chapterPage+1}"><i class="fas fa-chevron-right"></i></a>
                    {/if}
                    <span class="pager-jump">
                    <span class="pager-jump-label">{t name="jump_to"}</span>
                    <input type="number" id="chapterJumpInput" class="pager-input" min="1" max="{$chapterTotalPages}" value="{$chapterPage}">
                    <button type="button" class="pager-go" id="chapterJumpGo">{t name="jump_go"}</button>
                    <span class="pager-jump-total">/ {$chapterTotalPages}</span>
                    </span>
                </div>
                {/if}
                <div class="chapter-sort">
                    <button class="sort-btn active" data-order="asc">{t name="asc"}</button>
                    <button class="sort-btn" data-order="desc">{t name="desc"}</button>
                </div>
            </div>
        </div>
        <div class="chapters-accordion" id="chaptersAccordion">
            {foreach $chaptersByVolume as $volId => $group}
            <div class="volume-group" data-volume-id="{$volId}">
                <div class="volume-header">
                    <h3>{if $group.volume.title}{$group.volume.title}{elseif $group.volume.name}{$group.volume.name}{else}{t name="volume_main_text"}{/if}</h3>
                </div>
                <div class="volume-chapters">
                    {foreach $group.chapters as $ch}
                    <div class="chapter-item" data-sort="{$ch.sort}" data-id="{$ch.id}">
                        <a href="{url route="read" novel=$novel.id chapter=$ch.id}">
                            <span class="chapter-title-info">{$ch.title}</span>
                            {if $ch.is_vip}
                                <span class="vip-badge chapter-vip-badge" style="background:#f59e0b;color:#fff;padding:0 6px;border-radius:10px;font-size:0.6rem;">{t name="vip_label"}</span>
                            {/if}
                            <span class="word-count">{$ch.word_count|number_format} {t name="words_unit"}</span>
                        </a>
                        {hook name="novel_chapter_item" item=$ch}
                    </div>
                    {/foreach}
                </div>
            </div>
            {/foreach}
        </div>
    </div>
    {hook name="novel_below_chapters"}
    {if $related}
    <section class="related-novels">
        <h3><i class="fas fa-share-alt"></i> {t name="you_may_also_like"}</h3>
        <div class="novel-grid small-grid">
            {foreach $related as $n}
            <div class="novel-card compact">
                <a href="{url route="novel" id=$n.id}">
                    <div class="card-cover">
                        <img src="/{$n.cover|default:'assets/images/default_cover.jpg'}" alt="{$n.title}">
                    </div>
                    <div class="card-info">
                        <h4>{$n.title}</h4>
                    </div>
                </a>
            </div>
            {/foreach}
        </div>
    </section>
    {/if}
    {ad name="novel_detail_bottom"}
</div>
<script>
(function() {
    const accordion = document.getElementById('chaptersAccordion');
    if (!accordion) return;
    const initialHtml = accordion.innerHTML;
    function bindVolumeToggleEvents() {
        document.querySelectorAll('.volume-header').forEach(header => {
            if (header._clickHandler) {
                header.removeEventListener('click', header._clickHandler);
            }
            const handler = function() {
                const group = this.closest('.volume-group');
                const chaptersDiv = group.querySelector('.volume-chapters');
                const icon = this.querySelector('.toggle-icon');
                if (!chaptersDiv) return;
                if (chaptersDiv.style.display === 'none') {
                    chaptersDiv.style.display = 'grid';
                    if (icon) icon.classList.add('open');
                } else {
                    chaptersDiv.style.display = 'none';
                    if (icon) icon.classList.remove('open');
                }
            };
            header._clickHandler = handler;
            header.addEventListener('click', handler);
        });
    }
    function sortGrid(order) {
        if (order === 'asc') {
            accordion.innerHTML = initialHtml;
            bindVolumeToggleEvents();
            return;
        }
        const volumeGroups = Array.from(accordion.querySelectorAll('.volume-group'));
        volumeGroups.forEach(group => {
            const chaptersContainer = group.querySelector('.volume-chapters');
            if (!chaptersContainer) return;
            const items = Array.from(chaptersContainer.querySelectorAll('.chapter-item'));
            items.sort((a, b) => {
                const aS = parseInt(a.dataset.sort, 10) || 0;
                const bS = parseInt(b.dataset.sort, 10) || 0;
                if (bS !== aS) return bS - aS;
                const aId = parseInt(a.dataset.id, 10) || 0;
                const bId = parseInt(b.dataset.id, 10) || 0;
                return bId - aId;
            });
            chaptersContainer.innerHTML = '';
            items.forEach(item => chaptersContainer.appendChild(item));
        });
        const reversedGroups = volumeGroups.slice().reverse();
        accordion.innerHTML = '';
        reversedGroups.forEach(group => accordion.appendChild(group));
        bindVolumeToggleEvents();
    }
    bindVolumeToggleEvents();
    document.querySelectorAll('.chapter-sort .sort-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const order = this.dataset.order;
            document.querySelectorAll('.chapter-sort .sort-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            sortGrid(order);
        });
    });
    const jumpInput = document.getElementById('chapterJumpInput');
    if (jumpInput) {
        const doJump = function() {
            let p = parseInt(jumpInput.value, 10);
            const max = parseInt(jumpInput.max, 10) || 1;
            if (isNaN(p) || p < 1) p = 1;
            if (p > max) p = max;
            const urlFirst = '{url route="novel" id=$novel.id}';
            const urlPageTpl = '{url route="novel" id=$novel.id page="@@PAGE@@"}';
            window.location.href = (p === 1) ? urlFirst : urlPageTpl.replace('@@PAGE@@', p);
        };
        jumpInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doJump();
            }
        });
        const jumpGo = document.getElementById('chapterJumpGo');
        if (jumpGo) {
            jumpGo.addEventListener('click', doJump);
        }
    }
})();
</script>
{include file="footer.tpl"}
