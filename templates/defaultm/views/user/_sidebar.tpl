<aside class="user-sidebar">
    <div class="user-sidebar-header">
        <div class="user-avatar-img">
            <img src="{$user.avatar|default:'/assets/images/default_avatar.png'}" alt="avatar">
        </div>
        <h3>{$user.username|default:$username}</h3>
        <p>{if ($user.vip_level|default:0) > 0}<span class="vip-tag">VIP{$user.vip_level}</span>{else}{t name="normal_user"}{/if}</p>
    </div>
    <nav class="user-nav">
        <a href="{url route="user"}" class="user-nav-item {if $currentModule == 'user' && ($currentAction == '' || $currentAction == 'index')}active{/if}"><i class="fas fa-tachometer-alt"></i> <span>{t name="user_center"}</span></a>
        <a href="{url route="user" action="profile"}" class="user-nav-item {if $currentModule == 'user' && $currentAction == 'profile'}active{/if}"><i class="fas fa-user-edit"></i> <span>{t name="profile"}</span></a>
        <a href="{url route="user" action="gold"}" class="user-nav-item {if $currentModule == 'user' && $currentAction == 'gold'}active{/if}"><i class="fas fa-coins"></i> <span>{t name="finance_detail"}</span></a>
        <a href="{url route="user" action="vip"}" class="user-nav-item {if $currentModule == 'user' && $currentAction == 'vip'}active{/if}"><i class="fas fa-crown"></i> <span>{t name="vip_center"}</span></a>
        <a href="{url route="user" action="chapters"}" class="user-nav-item {if $currentModule == 'user' && $currentAction == 'chapters'}active{/if}"><i class="fas fa-book-open"></i> <span>{t name="purchased_chapters_title"}</span></a>
        <a href="{url route="user" action="auto"}" class="user-nav-item {if $currentModule == 'user' && $currentAction == 'auto'}active{/if}"><i class="fas fa-bolt"></i> <span>{t name="auto_purchase_title"}</span></a>
        <a href="{url route="user" action="sign"}" class="user-nav-item {if $currentModule == 'user' && $currentAction == 'sign'}active{/if}"><i class="fas fa-calendar-check"></i> <span>{t name="daily_sign"}</span></a>
        <a href="{url route="user" action="bookshelf"}" class="user-nav-item {if $currentModule == 'user' && $currentAction == 'bookshelf'}active{/if}"><i class="fas fa-bookmark"></i> <span>{t name="bookshelf"}</span></a>
        <a href="{url route="user" action="history"}" class="user-nav-item {if $currentModule == 'user' && $currentAction == 'history'}active{/if}"><i class="fas fa-history"></i> <span>{t name="history"}</span></a>
        <a href="{url route="user" action="recharge"}" class="user-nav-item {if $currentModule == 'user' && $currentAction == 'recharge'}active{/if}"><i class="fas fa-plus-circle"></i> <span>{t name="recharge_action"}</span></a>
        {if ($user.is_author|default:0) == 1}
        <a href="{url route="authorcenter" action="works"}" class="user-nav-item {if $currentModule == 'authorcenter' && $currentAction != 'income' && $currentAction != 'withdraw' && $currentAction != 'apply'}active{/if}"><i class="fas fa-pen-fancy"></i> <span>{t name="my_works"}</span></a>
        <a href="{url route="authorcenter" action="income"}" class="user-nav-item {if $currentModule == 'authorcenter' && $currentAction == 'income'}active{/if}"><i class="fas fa-dollar-sign"></i> <span>{t name="income_stats_title"}</span></a>
        <a href="{url route="authorcenter" action="withdraw"}" class="user-nav-item {if $currentModule == 'authorcenter' && $currentAction == 'withdraw'}active{/if}"><i class="fas fa-hand-holding-usd"></i> <span>{t name="withdraw_title"}</span></a>
        {else}
        <a href="{url route="authorcenter" action="apply"}" class="user-nav-item {if $currentModule == 'authorcenter' && $currentAction == 'apply'}active{/if}"><i class="fas fa-user-plus"></i> <span>{t name="apply_author_title"}</span></a>
        {/if}
        <div class="user-nav-divider"></div>
        {hook name="user_sidebar_extra"}
        <form method="post" action="{url route="user" action="logout"}" class="logout-form">
            {csrf_field}
            <button type="submit" class="user-nav-item logout-btn"><i class="fas fa-sign-out-alt"></i> <span>{t name="logout"}</span></button>
        </form>
    </nav>
</aside>
