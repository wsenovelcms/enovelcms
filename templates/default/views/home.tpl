{include file="header.tpl"}
<div class="container">
    {hook name="home_container_top"}
    <div class="hero-slider">{ad name="home_banner"}</div>

    {hook name="home_banner_after"}

    {if $announcements}
    <section class="home-card announce-card">
        <div class="announce-ticker">
            <i class="fas fa-volume-up announce-tag"></i>
            <div class="announce-viewport">
                <ul class="announce-list" id="announceList">
                    {foreach $announcements as $a}
                    <li>
                        {if $a.link}
                        <a href="{$a.link}" target="_blank">{$a.title}</a>
                        {else}
                        <span class="announce-title">{$a.title}</span>
                        {/if}
                        {if $a.content}<span class="announce-content">{$a.content}</span>{/if}
                        <span class="announce-time">{$a.created_at|date_format:'Y-m-d'}</span>
                    </li>
                    {/foreach}
                </ul>
            </div>
            {hook name="announce_extra"}
        </div>
    </section>
    {/if}

    {hook name="home_announce_after"}

    {if $heroSlider}
    <section class="home-card hero-block">
        <div class="hero-left">
            <a href="{url route="novel" id=$heroSlider.novels[0].id}" class="hero-cover" id="heroMainLink">
                <img id="heroMainImg" src="/{$heroSlider.novels[0].cover|default:'assets/images/default_cover.jpg'}" alt="{$heroSlider.novels[0].title}">
            </a>
            <div class="hero-meta">
                <a href="{url route="novel" id=$heroSlider.novels[0].id}" class="hero-title" id="heroMainTitle">{$heroSlider.novels[0].title}</a>
                <p class="hero-desc" id="heroMainDesc">{$heroSlider.novels[0].description|truncate:80}</p>
            </div>
        </div>
        <div class="hero-rail" id="heroRail">
            {foreach $heroSlider.novels item=n key=i}
            {if $i < 5}
            <a href="javascript:;"
               class="rail-cover{if $i == 0} rail-active{/if}"
               data-hero-idx="{$i}"
               data-hero-url="{url route="novel" id=$n.id}"
               data-hero-img="/{$n.cover|default:'assets/images/default_cover.jpg'}"
               data-hero-title="{$n.title}"
               data-hero-desc="{$n.description|truncate:80}">
                <img src="/{$n.cover|default:'assets/images/default_cover.jpg'}" alt="{$n.title}">
            </a>
            {/if}
            {/foreach}
        </div>
        <div class="hero-center">
            {foreach $heroTable.novels item=n key=i}
            {if $i < 20}
            {if $i == 0 || $i == 5 || $i == 10 || $i == 15}
            <div class="hero-center-head">
                <a href="{url route="novel" id=$n.id}">{$n.title}</a>
            </div>
            {else}
            <div class="hero-center-row">
                <span class="hero-cat">{if $n.category_name}{$n.category_name|truncate:2:''}{else}{t name="novel"}{/if}</span>
                <a class="hero-book" href="{url route="novel" id=$n.id}">{$n.title}</a>
            </div>
            {/if}
            {/if}
            {/foreach}
        </div>
        <div class="hero-rank">
            <div class="hero-rank-head"><span class="hero-rank-title">{t name="rank"}</span><a href="{url route="rank"}" class="hero-rank-more">{t name="view_all"} &gt;&gt;</a></div>
            <ol class="hero-rank-list">
                {foreach $heroRank.novels item=n key=i}
                <li>
                    <span class="rank-badge rank-badge-{$i+1}">{$i+1}</span>
                    {if $i == 0}
                    <a href="{url route="novel" id=$n.id}" class="rank-cover-mini"><img src="/{$n.cover|default:'assets/images/default_cover.jpg'}" alt=""></a>
                    {/if}
                    <a href="{url route="novel" id=$n.id}" class="rank-link">{$n.title}</a>
                </li>
                {/foreach}
            </ol>
        </div>
    </section>
    {/if}

    {foreach $sections as $block}
    {if $block.novels}
    {if $block.name == 'latest_updates'}
    <section class="home-card">
        <div class="section-header">
            <h2><i class="fas fa-clock"></i> {$block.title}</h2>
            <a href="{url route="library" query="order_by=update"}" class="view-all">{t name="view_all"} <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="chapter-update-table">
            <table class="update-table">
                <thead><tr><th>{t name="category_label"}</th><th>{t name="novel"}</th><th>{t name="author_label"}</th><th>{t name="latest_chapter"}</th><th>{t name="last_update"}</th>{hook name="home_recent_head"}</tr></thead>
                <tbody>
                    {foreach $block.novels as $novel}
                    <tr>
                        <td>{$novel.category_name|default:'-'}</td>
                        <td><a href="{url route="novel" id=$novel.id}">{$novel.title}{if $novel.is_vip}<span class="vip-badge">{t name="vip_label"}</span>{/if}</a></td>
                        <td>{$novel.display_name}</td>
                        <td>
                            {if $novel.last_chapter_id}
                            <a href="{url route="read" novel=$novel.id chapter=$novel.last_chapter_id}">{$novel.last_chapter_title}</a>
                            {if $novel.last_chapter_is_vip}<span class="vip-chapter-badge">{t name="vip_label"}</span>{/if}
                            {else}
                            {t name="no_chapter"}
                            {/if}
                        </td>
                        <td>{$novel.last_chapter_time|default:$novel.created_at|date_format:'Y-m-d H:i'}</td>
                        {hook name="home_recent_cell" item=$novel}
                    </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    </section>
    {else}
    <section class="home-card">
        <div class="section-header">
            <h2><i class="fas fa-star"></i> {$block.title}</h2>
            <a href="{url route="library"}" class="view-all">{t name="view_all"} <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="novel-grid">
            {foreach $block.novels as $n}
            <div class="novel-card">
                <a href="{url route="novel" id=$n.id}">
                    <div class="card-cover">
                        <img src="/{$n.cover|default:'assets/images/default_cover.jpg'}" alt="{$n.title}">
                        {if $n.is_vip}<span class="vip-badge">{t name="vip_label"}</span>{/if}
                    </div>
                    <div class="card-info">
                        <h3>{$n.title}{if $n.is_vip}<span class="vip-badge">{t name="vip_label"}</span>{/if}</h3>
                        <p class="author">{$n.display_name}</p>
                        <div class="card-stats"><span><i class="fas fa-eye"></i> {$n.views|number_format}</span></div>
                    </div>
                </a>
                {hook name="home_card_item" item=$n}
            </div>
            {/foreach}
        </div>
    </section>
    {/if}
    {/if}
    {/foreach}

    {ad name="home_sidebar_top"}

    {if $rankBlocks}
    <div class="rank-row">
        {foreach $rankBlocks as $block}
        <div class="rank-col">
            {if $block.novels}
            <div class="rank-box">
                <h3><i class="fas fa-chart-line"></i> {$block.title}</h3>
                <ol class="rank-list">
                    {foreach $block.novels as $idx => $n}
                    {if $idx < 10}
                    <li><span class="rank-num">{$idx+1}</span> <a href="{url route="novel" id=$n.id}">{$n.title}{if $n.is_vip}<span class="vip-badge">{t name="vip_label"}</span>{/if}</a>
                    {if $block.order_by == 'favorites'}<span class="rank-value">{$n.favorites|number_format}</span>
                    {elseif $block.order_by == 'words'}<span class="rank-value">{$n.total_words|number_format} {t name="words_unit"}</span>
                    {else}<span class="rank-value">{$n.views|number_format}</span>{/if}
                    {hook name="home_rank_item" item=$n}
                    </li>
                    {/if}
                    {/foreach}
                </ol>
            </div>
            {else}
            <div class="rank-box empty-placeholder"></div>
            {/if}
        </div>
        {/foreach}
    </div>
    {/if}

    {foreach $randomBlocks as $block}
    {if $block.novels}
    <section class="home-card">
        <div class="section-header">
            <h2><i class="fas fa-shuffle"></i> {$block.title}</h2>
        </div>
        <div class="random-list">
            {foreach $block.novels as $n}
            <a href="{url route="novel" id=$n.id}" class="random-item">
                <span class="random-title">{$n.title}</span>
            </a>
            {hook name="home_random_item" item=$n}
            {/foreach}
        </div>
    </section>
    {/if}
    {/foreach}

    {ad name="home_sidebar_bottom"}
    {hook name="home_container_bottom"}
</div>
{include file="footer.tpl"}
{literal}
<script>
(function(){
  var rail = document.getElementById('heroRail');
  if (rail) {
    var items = rail.querySelectorAll('[data-hero-idx]');
    if (items.length) {
      var mainLink = document.getElementById('heroMainLink');
      var mainImg  = document.getElementById('heroMainImg');
      var mainTitle= document.getElementById('heroMainTitle');
      var mainDesc = document.getElementById('heroMainDesc');
      var cur = 0, timer = null;
      function activate(i){
        cur = i;
        var el = items[i];
        mainLink.href = el.getAttribute('data-hero-url');
        mainImg.src = el.getAttribute('data-hero-img');
        mainImg.alt = el.getAttribute('data-hero-title');
        mainTitle.textContent = el.getAttribute('data-hero-title');
        mainTitle.href = el.getAttribute('data-hero-url');
        mainDesc.textContent = el.getAttribute('data-hero-desc');
        items.forEach(function(it, k){ it.classList.toggle('rail-active', k === i); });
      }
      function next(){ activate((cur + 1) % items.length); }
      function start(){ stop(); timer = setInterval(next, 4000); }
      function stop(){ if (timer) { clearInterval(timer); timer = null; } }
      items.forEach(function(el, i){
        el.addEventListener('mouseenter', function(){ stop(); activate(i); });
        el.addEventListener('click', function(e){ e.preventDefault(); stop(); activate(i); });
      });
      var block = rail.closest('.hero-block');
      if (block) { block.addEventListener('mouseenter', stop); block.addEventListener('mouseleave', start); }
      start();
    }
  }
  var list = document.getElementById('announceList');
  if (list && list.children.length > 1) {
    var first = list.children[0];
    var clone = first.cloneNode(true);
    clone.removeAttribute('id');
    list.appendChild(clone);
    var total = list.children.length;
    var idx = 0;
    setInterval(function(){
      idx++;
      list.style.transform = 'translateY(-' + (idx * 24) + 'px)';
      if (idx >= total - 1) {
        setTimeout(function(){
          list.style.transition = 'none';
          list.style.transform = 'translateY(0)';
          idx = 0;
          setTimeout(function(){ list.style.transition = ''; }, 50);
        }, 600);
      }
    }, 3000);
  }
})();
</script>
{/literal}
