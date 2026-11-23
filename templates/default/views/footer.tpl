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
    {hook name="footer_below"}
</div>
<script src="{$themeUrl}/js/main.js?v={$smarty.now}"></script>
{hook name="body_end"}
{if $analytics_code}{$analytics_code nofilter}{/if}
</body>
</html>
