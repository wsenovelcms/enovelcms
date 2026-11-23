{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-calendar-check"></i> {t name="sign_today"}</h2>
        {if $signResult}<div data-ui-toast data-type="{if $signResult.ok}success{else}info{/if}" data-msg="{$signResult.msg}" style="display:none"></div>{/if}
        <div class="sign-card">
            <div class="sign-icon"><i class="fas fa-star"></i></div>
            <h3>{t name="daily_sign"}</h3>
            <div class="sign-reward-preview">
                <div class="reward-label">{t name="today_sign_reward_preview"}</div>
                <div class="reward-number">+{$reward.total|number_format}</div>
            </div>
            {if $signed_today}
            <div class="signed-banner">
                <i class="fas fa-check-circle"></i> {t name="already_signed_today"}
                {if $sign_days > 1}
                <span style="opacity:.7;margin-left:8px;">{t name="continuous_days"}{t name="label_separator"}{$sign_days} {t name="days_unit"}</span>
                {/if}
            </div>
            {else}
            <form method="post" action="{url route="user" action="sign"}">
                {csrf_field}
                <button type="submit" class="sign-btn">{t name="sign_now_action"}</button>
            </form>
            {/if}
            <div class="sign-stats-row">
                <div class="sign-stat">
                    <i class="fas fa-coins"></i>
                    <span>{t name="current_point_label"}{t name="label_separator"}</span>
                    <strong>{$user.gold|number_format}</strong>
                </div>
                <div class="sign-stat">
                    <i class="fas fa-fire"></i>
                    <span>{t name="continuous_days"}{t name="label_separator"}</span>
                    <strong>{$sign_days} {t name="days_unit"}</strong>
                </div>
            </div>
        </div>
    </div>
</div>
{include file="footer.tpl"}
