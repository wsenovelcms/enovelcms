<!DOCTYPE html>
<html lang="{$html_lang}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{if $seo.title}{$seo.title}{else}{$siteName}{/if}</title>
    {if $seo.keywords}<meta name="keywords" content="{$seo.keywords}">{/if}
    {if $seo.description}<meta name="description" content="{$seo.description}">{/if}
    <link rel="stylesheet" href="{asset file='fontawesome/css/all.min.css'}">
    <link rel="stylesheet" href="{$themeUrl}/css/style.css?v={$smarty.now}">
    <meta name="csrf-token" content="{$csrf_token}">
    <script>window.ENOVEL_URL = { search: "{url route="search"}", rankTpl: "{url route="rank" type="@@TYPE@@"}", library: "{url route="library"}" };window.lang = {$js_lang nofilter};</script>
    {hook name="head_end"}
</head>
<body>
<div class="app-wrapper">
    {hook name="home_top"}
    <header class="site-header">
        <div class="container">
            <div class="header-inner">
                <div class="logo">
                    <a href="{url route="home"}" aria-label="{$siteName}">
                        {if $siteLogo}
                            <img src="{$siteLogo}" alt="{$siteName}" class="site-logo-img">
                        {else}
                            <img src="/assets/uploads/logo/default_logo.png?v={$smarty.now}" alt="{$siteName}" class="site-logo-img">
                        {/if}
                    </a>
                    {hook name="header_logo_after"}
                </div>
                <nav class="main-nav">
                    <ul>
                        <li><a href="{url route="home"}">{t name="home"}</a></li>
                        <li><a href="{url route="library"}">{t name="library"}</a></li>
                        <li><a href="{url route="rank"}">{t name="rank"}</a></li>
                        <li><a href="{url route="authors"}">{t name="author"}</a></li>
                        {if $user_id > 0}
                        <li><a href="{url route="user" action="bookshelf"}">{t name="bookshelf"}</a></li>
                        <li><a href="{url route="user" action="history"}">{t name="history"}</a></li>
                        <li><a href="{url route="user" action="vip"}">{t name="vip"}</a></li>
                        {/if}
                        {hook name="header_nav_extra"}
                    </ul>
                    {hook name="header_nav_after"}
                </nav>
                <div class="header-actions">
                    <button id="searchBtn" class="search-icon-btn" title="{t name="search"}" aria-label="{t name="search"}">
                        <i class="fas fa-search"></i>
                    </button>
                    {if $user_id > 0}
                        <span class="gold-badge"><i class="fas fa-coins"></i> {$userGold|number_format}</span>
                        <div class="user-menu">
                            <span class="username">{$username} <i class="fas fa-chevron-down"></i></span>
                            <div class="user-dropdown">
                                <a href="{url route="user"}"><i class="fas fa-user"></i> {t name="user_center"}</a>
                                {hook name="user_dropdown_extra"}
                                <form method="post" action="{url route="user" action="logout"}" class="logout-form">
                                    {csrf_field}
                                    <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> {t name="logout"}</button>
                                </form>
                            </div>
                        </div>
                    {else}
                        <a href="{url route="user" action="login"}" class="btn-outline">{t name="login"}</a>
                        <a href="{url route="user" action="register"}" class="btn-primary">{t name="register"}</a>
                    {/if}
                    {if !empty($siteLangs)}
                    <div class="lang-switch">
                        <select class="lang-select" onchange="if(this.value) location.href=this.value" aria-label="{t name="ui_language" default="Language"}">
                            {foreach $siteLangs as $langCode => $langName}
                            <option value="{url route="lang" lang=$langCode}" {if $langCode == $currentLang}selected{/if}>{$langName}</option>
                            {/foreach}
                        </select>
                    </div>
                    {/if}
                    <button class="mobile-toggle" id="mobileToggle" title="{t name="ui_menu" default="Menu"}" aria-label="{t name="ui_menu" default="Menu"}"><i class="fas fa-bars"></i></button>
                </div>
            </div>
        </div>
    </header>
    {hook name="header_below"}
    <div id="searchOverlay" class="search-overlay">
        <div class="search-modal">
            <div class="search-close">
                <button id="closeSearchBtn" aria-label="{t name="ui_close" default="Close"}"><i class="fas fa-times"></i></button>
            </div>
            <h3><i class="fas fa-search"></i> {t name="search"}</h3>
            <div class="search-input-group">
                <input type="text" id="searchKeyword" placeholder="{t name="search_placeholder"}" autocomplete="off">
                <button id="submitSearch">{t name="search"}</button>
            </div>
            <div class="search-tip">
                <i class="fas fa-info-circle"></i> {t name="search_tip"}
            </div>
        </div>
    </div>
    <main class="site-main">
