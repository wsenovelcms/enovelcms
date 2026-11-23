{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-tachometer-alt"></i> {t name="user_center"}</h2>

        <div class="profile-hero">
            <div class="profile-avatar-wrap">
                <img src="{$user.avatar|default:'/assets/images/default_avatar.png'}" alt="avatar" class="profile-avatar-preview">
                <span class="profile-avatar-name">{$user.username}</span>
            </div>
            <div class="profile-hero-info">
                <span class="profile-meta-label">{t name="register_time"}</span>
                <span class="profile-meta-value">{$user.created_at|date_format:'Y-m-d'}</span>
                <span class="profile-meta-label">{t name="vip_status"}</span>
                <span class="profile-meta-value">
                    {if $user.vip_level > 0}<span class="vip-tag">VIP{$user.vip_level}</span>{else}<span class="normal-tag">{t name="normal_user"}</span>{/if}
                    {hook name="user_vip_badge"}
                </span>
                <span class="profile-meta-label">{t name="current_point_label"}</span>
                <span class="profile-meta-value gold-tag"><i class="fas fa-coins"></i> {$user.gold|number_format}</span>
            </div>
        </div>

        <div class="stat-row">
            <div class="stat-card">
                <div class="num"><i class="fas fa-coins"></i>{$user.gold|number_format}</div>
                <div class="label">{t name="current_point_label"}</div>
            </div>
            <div class="stat-card">
                <div class="num"><i class="fas fa-book"></i>{$bookshelf}</div>
                <div class="label">{t name="bookshelf"}</div>
            </div>
            <div class="stat-card">
                <div class="num"><i class="fas fa-clock"></i>{$history}</div>
                <div class="label">{t name="history"}</div>
            </div>
        </div>

        <div class="page-card">
            <h3 class="card-title"><i class="fas fa-bolt"></i> {t name="quick_entries"}</h3>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="{url route="user" action="profile"}" class="btn-outline" style="padding:7px 16px;text-decoration:none;color:#333;border:1px solid #ccc;border-radius:3px;font-size:0.86rem;"><i class="fas fa-user-edit"></i> {t name="profile_link"}</a>
                <a href="{url route="user" action="bookshelf"}" class="btn-outline" style="padding:7px 16px;text-decoration:none;color:#333;border:1px solid #ccc;border-radius:3px;font-size:0.86rem;"><i class="fas fa-bookmark"></i> {t name="my_bookshelf"}</a>
                <a href="{url route="user" action="recharge"}" class="btn-outline" style="padding:7px 16px;text-decoration:none;color:#333;border:1px solid #ccc;border-radius:3px;font-size:0.86rem;"><i class="fas fa-plus-circle"></i> {t name="recharge_action"}</a>
                <a href="{url route="user" action="sign"}" class="btn-outline" style="padding:7px 16px;text-decoration:none;color:#333;border:1px solid #ccc;border-radius:3px;font-size:0.86rem;"><i class="fas fa-calendar-check"></i> {t name="daily_sign"}</a>
                {hook name="user_quick_actions_extra"}
            </div>
        </div>
        {hook name="user_center_after"}
    </div>
</div>
{include file="footer.tpl"}
