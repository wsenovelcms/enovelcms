{include file="header.tpl"}
<div class="user-center-layout">
    {include file="user/_sidebar.tpl"}
    <div class="user-content">
        <h2><i class="fas fa-crown"></i> {t name="vip_center"}</h2>
        {if $vipResult}<div data-ui-toast data-type="{if $vipResult.ok}success{else}error{/if}" data-msg="{$vipResult.msg}" style="display:none"></div>{/if}

        <div class="vip-banner-card {if $user.vip_level > 0}active{/if}">
            <div class="vip-banner-left">
                <i class="fas fa-gem"></i>
            </div>
            <div class="vip-banner-right">
                <div class="vip-banner-title">{if $user.vip_level > 0}VIP{$user.vip_level} {t name="member_suffix"}{else}{t name="normal_user"}{/if}</div>
                {if $user.vip_level > 0}
                    <div class="vip-banner-sub">{t name="valid_until_label"} {$user.vip_expire|date_format:'Y-m-d'}</div>
                {else}
                    <div class="vip-banner-sub">{t name="vip_banner_sub"}</div>
                {/if}
            </div>
        </div>

        {if $goldPerMonth > 0}
        <div class="page-card">
            <h3 class="card-title"><i class="fas fa-coins"></i> {$point_name}{t name="exchange_vip_suffix"}</h3>
            <div class="vip-rate-bar">
                <span>{t name="current_point_label"}{$point_name}{t name="label_separator"}<strong class="gold-tag">{$user.gold|number_format}</strong></span>
                <span class="vip-rate-sep">|</span>
                <span>{t name="gold_exchange_rate"}{t name="label_separator"}<strong>{$goldPerMonth}</strong> {$point_name} / {t name="months_unit"}</span>
            </div>
            <form method="post" action="{url route="user" action="vip"}" class="vip-form">
                {csrf_field}
                {assign var=g3 value=$goldPerMonth*3}
                {assign var=g6 value=$goldPerMonth*6}
                {assign var=g12 value=$goldPerMonth*12}
                <div class="profile-form-row">
                    <label>{t name="select_months"}</label>
                    <select name="months" class="vip-select">
                        <option value="1">{'months_option_fmt'|t|sprintf:1:$goldPerMonth:$point_name}</option>
                        <option value="3">{'months_option_fmt'|t|sprintf:3:$g3:$point_name}</option>
                        <option value="6">{'months_option_fmt'|t|sprintf:6:$g6:$point_name}</option>
                        <option value="12">{'months_option_fmt'|t|sprintf:12:$g12:$point_name}</option>
                    </select>
                </div>
                <div class="profile-actions">
                    <button type="submit" name="exchange_vip" class="btn-primary"><i class="fas fa-crown"></i> {t name="exchange_now"}</button>
                </div>
            </form>
        </div>
        {/if}
    </div>
</div>
{include file="footer.tpl"}
