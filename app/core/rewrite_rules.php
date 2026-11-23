<?php

function enovel_rewrite_rules(string $type): string
{
    if ($type === 'nginx') {
        return 'fastcgi_intercept_errors off;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~* ^/(app|vendor|config|data|cli)/ {
    deny all;
}

location ~* ^/uploads/.*\.(?:php|php3|php4|php5|phtml|pl|py|cgi|asp|aspx)$ {
    deny all;
}

location ~* \.(?:css|js|jpe?g|png|gif|webp|svg|ico|woff2?|ttf|eot)$ {
    expires 30d;
    access_log off;
    try_files $uri =404;
}';
    }
    if ($type === 'apache') {
        return '<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    RewriteRule ^(app|config|vendor|data|cli)/(.*)$ - [F,L]
    RewriteRule ^uploads/.*\.(php|php3|php4|php5|phtml|pl|py|cgi|asp|aspx)$ - [F,L,NC]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>';
    }
    return '<configuration>
  <system.webServer>
    <rewrite>
      <rules>
        <rule name="EnovelCms" stopProcessing="true">
          <match url="^(.*)$" />
          <conditions logicalGrouping="MatchAll">
            <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
            <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
          </conditions>
          <action type="Rewrite" url="index.php" />
        </rule>
      </rules>
    </rewrite>
  </system.webServer>
</configuration>';
}
