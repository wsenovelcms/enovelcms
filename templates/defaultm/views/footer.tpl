    </main>
    {hook name="footer_above"}
    <footer class="site-footer">
        <div class="container">
            <div class="footer-inner">
                {hook name="footer_inner_before"}
                <p>&copy; {$smarty.now|date_format:'Y'} {$siteName}. All rights reserved.</p>
                <p style="margin-top:6px;font-size:0.85em;opacity:0.8;">
                    Powered By <a href="https://www.enovelcms.cn/" target="_blank" rel="noopener" style="color:#d4d4d4;text-decoration:none;">EnovelCms</a>
                </p>
                {hook name="footer_inner_after"}
            </div>
        </div>
    </footer>
    <nav class="mobile-tabbar" aria-label="{t name="ui_mobile_nav" default="Mobile navigation"}">
        <a href="{url route="home"}" class="tabbar-item"><i class="fas fa-home"></i><span>{t name="home"}</span></a>
        <a href="{url route="library"}" class="tabbar-item"><i class="fas fa-book"></i><span>{t name="library"}</span></a>
        <a href="{url route="rank"}" class="tabbar-item"><i class="fas fa-trophy"></i><span>{t name="rank"}</span></a>
        {if $user_id > 0}
        <a href="{url route="user" action="bookshelf"}" class="tabbar-item"><i class="fas fa-bookmark"></i><span>{t name="bookshelf"}</span></a>
        <a href="{url route="user"}" class="tabbar-item"><i class="fas fa-user"></i><span>{t name="user_center"}</span></a>
        {else}
        <a href="{url route="user" action="login"}" class="tabbar-item"><i class="fas fa-user"></i><span>{t name="login"}</span></a>
        {/if}
    </nav>
    {hook name="footer_below"}
</div>
<script src="{$themeUrl}/js/main.js?v={$smarty.now}"></script>
{hook name="body_end"}
{if $analytics_code}{$analytics_code nofilter}{/if}
</body>
</html>
