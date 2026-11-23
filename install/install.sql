-- --------------------------------------------------------
-- EnovelCms 3.0.4 全新安装SQL
-- 字符集：utf8mb4 / 引擎：InnoDB
-- --------------------------------------------------------
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 用户表
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT '/assets/images/default_avatar.png',
  `gold` int(11) DEFAULT '0',
  `vip_level` tinyint(1) DEFAULT '0',
  `vip_expire` datetime DEFAULT NULL,
  `sign_days` int(11) DEFAULT '0',
  `last_sign` date DEFAULT NULL,
  `is_author` tinyint(1) DEFAULT '0',
  `pen_name` varchar(50) DEFAULT NULL,
  `signature` varchar(255) NOT NULL DEFAULT '',
  `phone` varchar(20) NOT NULL DEFAULT '',
  `gender` tinyint(1) NOT NULL DEFAULT '0',
  `real_name` varchar(50) DEFAULT NULL,
  `alipay_account` varchar(100) DEFAULT NULL,
  `wechat_account` varchar(100) DEFAULT NULL,
  `withdraw_qrcode` varchar(255) DEFAULT NULL,
  `total_income` decimal(10,2) DEFAULT '0.00',
  `available_income` decimal(10,2) DEFAULT '0.00',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `username` (`username`), KEY `email` (`email`), KEY `is_author` (`is_author`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 小说表
CREATE TABLE IF NOT EXISTS `novels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `author` varchar(50) DEFAULT NULL,
  `source_id` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT '0',
  `category_id` int(11) DEFAULT NULL,
  `cover` varchar(255) DEFAULT NULL,
  `source_cover` varchar(500) DEFAULT '',
  `description` text,
  `status` tinyint(1) DEFAULT '0',
  `views` int(11) DEFAULT '0',
  `total_words` int(11) DEFAULT '0',
  `favorites` int(11) NOT NULL DEFAULT '0',
  `is_vip` tinyint(1) DEFAULT '0',
  `vip_audit_status` tinyint(1) DEFAULT '-1',
  `last_chapter_id` int(11) DEFAULT NULL,
  `last_chapter_title` varchar(200) DEFAULT NULL,
  `last_chapter_is_vip` tinyint(1) NOT NULL DEFAULT '0',
  `last_chapter_time` datetime DEFAULT NULL,
  `total_chapters` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `category_id` (`category_id`), KEY `user_id` (`user_id`), KEY `is_vip` (`is_vip`), KEY `idx_last_chapter_time` (`last_chapter_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 分类表
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `sort` int(11) DEFAULT '0',
  PRIMARY KEY (`id`), KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 分卷表
CREATE TABLE IF NOT EXISTS `volumes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `novel_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `sort` int(11) DEFAULT '0',
  PRIMARY KEY (`id`), KEY `novel_id` (`novel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 章节表（分表：chapters_0 为第 1 张，写满 100 万条由 ChapterShard::ensureShard() 自动创建下一片）
-- 这里手动创建首表，结构与后续自动扩容分片保持一致
CREATE TABLE IF NOT EXISTS `chapters_0` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `novel_id` int(11) NOT NULL,
  `volume_id` int(11) NOT NULL DEFAULT '0',
  `title` varchar(200) NOT NULL,
  `word_count` int(11) DEFAULT '0',
  `file_path` varchar(255) DEFAULT NULL,
  `source_url` varchar(255) DEFAULT NULL,
  `source_chapter_id` varchar(50) DEFAULT NULL,
  `sort` int(11) DEFAULT '0',
  `is_vip` tinyint(1) DEFAULT '0',
  `price` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `novel_id` (`novel_id`), KEY `volume_id` (`volume_id`), KEY `is_vip` (`is_vip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1;

-- 书架
CREATE TABLE IF NOT EXISTS `bookshelf` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `novel_id` int(11) NOT NULL,
  `last_read_chapter` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `user_novel` (`user_id`,`novel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 阅读历史
CREATE TABLE IF NOT EXISTS `reading_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `novel_id` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL,
  `read_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 订单
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `out_trade_no` varchar(32) NOT NULL,
  `user_id` int(11) NOT NULL,
  `money` decimal(10,2) NOT NULL,
  `gold` int(11) NOT NULL DEFAULT '0',
  `type` varchar(20) DEFAULT 'vip',
  `gateway` varchar(32) NOT NULL DEFAULT 'epay',
  `months` int(11) DEFAULT '1',
  `scene` varchar(20) NOT NULL DEFAULT 'recharge',
  `biz_id` int(11) NOT NULL DEFAULT '0',
  `ext` text,
  `status` tinyint(1) DEFAULT '0',
  `pay_time` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `out_trade_no` (`out_trade_no`), KEY `user_id` (`user_id`), KEY `scene` (`scene`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 系统设置
CREATE TABLE IF NOT EXISTS `settings` (
  `key` varchar(50) NOT NULL,
  `value` text,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 公告表
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(120) NOT NULL DEFAULT '',
  `content` text,
  `link` varchar(255) NOT NULL DEFAULT '',
  `sort` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 语言包元数据（代码 / 自称名称 / 归属范围 / 所属主题或插件 / 包文件相对路径）
CREATE TABLE IF NOT EXISTS `languages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `scope` varchar(16) NOT NULL DEFAULT 'theme',
  `owner` varchar(64) NOT NULL DEFAULT '',
  `file` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scope_owner_code` (`scope`,`owner`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 内置语种元数据种子（后台包 + 默认主题包）；插件语言包随插件安装登记
INSERT IGNORE INTO `languages` (`code`, `name`, `scope`, `owner`, `file`) VALUES
('zh-cn', '简体中文', 'admin', '', 'admin/lang/zh-cn.php'),
('en-us', 'English', 'admin', '', 'admin/lang/en-us.php'),
('ja-jp', '日本語', 'admin', '', 'admin/lang/ja-jp.php'),
('ko-kr', '한국어', 'admin', '', 'admin/lang/ko-kr.php'),
('ru-ru', 'Русский', 'admin', '', 'admin/lang/ru-ru.php'),
('vi-vn', 'Tiếng Việt', 'admin', '', 'admin/lang/vi-vn.php'),
('es-mx', 'Español (México)', 'admin', '', 'admin/lang/es-mx.php'),
('fr-fr', 'Français', 'admin', '', 'admin/lang/fr-fr.php'),
('zh-cn', '简体中文', 'theme', 'default', 'templates/default/lang/zh-cn.php'),
('en-us', 'English', 'theme', 'default', 'templates/default/lang/en-us.php'),
('ja-jp', '日本語', 'theme', 'default', 'templates/default/lang/ja-jp.php'),
('ko-kr', '한국어', 'theme', 'default', 'templates/default/lang/ko-kr.php'),
('ru-ru', 'Русский', 'theme', 'default', 'templates/default/lang/ru-ru.php'),
('vi-vn', 'Tiếng Việt', 'theme', 'default', 'templates/default/lang/vi-vn.php'),
('es-mx', 'Español (México)', 'theme', 'default', 'templates/default/lang/es-mx.php'),
('fr-fr', 'Français', 'theme', 'default', 'templates/default/lang/fr-fr.php');

-- 广告
CREATE TABLE IF NOT EXISTS `ads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `position` varchar(30) DEFAULT 'home',
  `html_code` text,
  `sort` int(11) DEFAULT '0',
  `enabled` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 管理员
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SEO
CREATE TABLE IF NOT EXISTS `seo_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page` varchar(50) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`id`), UNIQUE KEY `page` (`page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DIY区块
CREATE TABLE IF NOT EXISTS `diy_blocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `num` int(11) DEFAULT '10',
  `category_id` varchar(255) NOT NULL DEFAULT '0',
  `tag_id` text,
  `status` varchar(20) DEFAULT 'all',
  `min_words` int(11) DEFAULT '0',
  `max_words` int(11) DEFAULT '0',
  `order_by` varchar(20) DEFAULT 'views',
  `time_range` varchar(30) DEFAULT 'all',
  `cache_time` int(11) DEFAULT '300',
  `custom_ids` text,
  `data` longtext,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 访问统计
CREATE TABLE IF NOT EXISTS `stats_visit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `visit_date` date NOT NULL,
  `spider_count` int(11) DEFAULT '0',
  `real_count` int(11) DEFAULT '0',
  `pv` int(11) DEFAULT '0',
  PRIMARY KEY (`id`), UNIQUE KEY `visit_date` (`visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 金币日志
CREATE TABLE IF NOT EXISTS `gold_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(20) NOT NULL,
  `gold_change` int(11) NOT NULL,
  `gold_after` int(11) NOT NULL,
  `remark` varchar(255) DEFAULT NULL,
  `novel_id` int(11) DEFAULT '0',
  `chapter_id` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `user_id` (`user_id`), KEY `type` (`type`), KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 邮箱验证
CREATE TABLE IF NOT EXISTS `email_verify` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL,
  `expire` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`), KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 作者申请
CREATE TABLE IF NOT EXISTS `author_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `real_name` varchar(50) NOT NULL,
  `pen_name` varchar(50) NOT NULL,
  `id_card` varchar(30) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `qq` varchar(20) DEFAULT NULL,
  `id_card_front` varchar(255) DEFAULT NULL,
  `id_card_back` varchar(255) DEFAULT NULL,
  `introduction` text,
  `status` tinyint(1) DEFAULT '0',
  `reject_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `user_id` (`user_id`), KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 章节购买
CREATE TABLE IF NOT EXISTS `chapter_purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `novel_id` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `user_chapter` (`user_id`,`chapter_id`), KEY `novel_id` (`novel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 章节自动购买偏好（按小说维度，用户可随时开关）
CREATE TABLE IF NOT EXISTS `auto_purchase` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `novel_id` int(11) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_novel` (`user_id`,`novel_id`),
  KEY `novel_id` (`novel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 作者收入
CREATE TABLE IF NOT EXISTS `author_incomes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `novel_id` int(11) DEFAULT '0',
  `chapter_id` int(11) DEFAULT '0',
  `amount` decimal(10,2) NOT NULL,
  `type` varchar(20) DEFAULT 'sale',
  `status` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `user_id` (`user_id`), KEY `novel_id` (`novel_id`), KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 提现
CREATE TABLE IF NOT EXISTS `withdraw_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `fee` decimal(10,2) DEFAULT '0.00',
  `actual_amount` decimal(10,2) NOT NULL,
  `real_name` varchar(50) DEFAULT NULL,
  `account_type` varchar(20) DEFAULT NULL,
  `account` varchar(100) DEFAULT NULL,
  `qrcode` varchar(255) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `user_id` (`user_id`), KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- VIP阅读日志
CREATE TABLE IF NOT EXISTS `vip_reading_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `novel_id` int(11) NOT NULL,
  `chapter_id` int(11) NOT NULL,
  `word_count` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `user_id` (`user_id`), KEY `novel_id` (`novel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 安全防护日志（SecurityGuard 拦截/事件记录）
CREATE TABLE IF NOT EXISTS `security_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) DEFAULT '',
  `ua` varchar(255) DEFAULT '',
  `method` varchar(10) DEFAULT '',
  `url` varchar(500) DEFAULT '',
  `type` varchar(30) DEFAULT '',
  `reason` varchar(255) DEFAULT '',
  `user_id` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `ip` (`ip`), KEY `type` (`type`), KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== 默认数据 ==========
INSERT IGNORE INTO `categories` (`id`, `name`, `sort`) VALUES
(1, '玄幻', 1), (2, '都市', 2), (3, '言情', 3), (4, '历史', 4), (5, '科幻', 5);

INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
('site_name', 'EnovelCms 小说站'),
('site_keywords', '小说,在线阅读,免费小说'),
('site_description', 'EnovelCms 小说站'),
('active_theme', 'default'),
('mobile_theme', 'defaultm'),
('plugins_enabled', '[]'),
('library_per_page', '20'),
('sign_base_gold', '10'),
('vip_price_per_month', '30'),
('vip_gold_per_month', '3000'),
('vip_default_price_per_300', '1'),
('point_name', '金币'),
('recharge_exchange_rate', '10'),
('novel_tag_max', '3');

INSERT IGNORE INTO `seo_settings` (`page`, `title`, `keywords`, `description`) VALUES
('home', '{site_name} - 首页', '{site_keywords}', '{site_description}'),
('library', '{site_name} - 书库', '小说书库', '全部小说列表'),
('rank', '{site_name} - 排行榜', '小说排行榜', '最热小说排行'),
('novel_detail', '{novel_title} - {site_name}', '{novel_keywords}', '{novel_description}'),
('read', '{chapter_title} - {novel_title}', '阅读', '在线阅读'),
('search', '{keyword} - 搜索', '{keyword},小说搜索', '搜索结果');

INSERT IGNORE INTO `diy_blocks` (`name`, `title`, `num`, `category_id`, `status`, `order_by`, `time_range`, `cache_time`) VALUES
('hero_slider', '主推推荐', 5, 0, 'all', 'views', 'all', 300),
('hero_table', '最新入库', 20, 0, 'all', 'new', 'all', 300),
('hero_rank', '章节最多', 15, 0, 'all', 'chapters', 'all', 300),
('featured_novels', '精选小说', 12, 0, 'all', 'favorites', 'all', 300),
('latest_updates', '最新更新', 22, 0, 'all', 'update', 'all', 300),
('rank_views', '点击榜', 15, 0, 'all', 'views', 'all', 300),
('rank_favorites', '收藏榜', 10, 0, 'all', 'favorites', 'all', 300),
('rank_words', '字数榜', 10, 0, 'all', 'words', 'all', 300),
('vip_bestsellers', 'VIP畅销', 12, 0, 'all', 'vip_sales_amount', 'all', 300),
('random_novels', '随机推荐', 30, 0, 'all', 'random', 'all', 300),
('related_novels', '猜你喜欢', 6, 0, 'all', 'random', 'all', 1800);

SET FOREIGN_KEY_CHECKS = 1;


-- ------------------------------------------------------------------
-- 以下表为运行必需，此前安装包遗漏，全新安装后缺失会导致 1146 报错
-- ------------------------------------------------------------------

-- 应用中心绑定与授权（客户端连接中心站点的凭证与授权缓存）
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ac_bind` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `center_user_id` int(11) DEFAULT '0',
  `identity_id` varchar(32) DEFAULT '',
  `username` varchar(50) DEFAULT '',
  `email` varchar(100) DEFAULT '',
  `client_token` varchar(64) DEFAULT '',
  `client_secret` varchar(255) DEFAULT '',
  `site_url` varchar(255) DEFAULT '',
  `site_name` varchar(100) DEFAULT '',
  `cms_version` varchar(20) DEFAULT '',
  `ua` varchar(255) DEFAULT '',
  `last_active_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `client_identity` varchar(32) DEFAULT '',
  `bound` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ac_license` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `license_key` varchar(64) DEFAULT '',
  `center_app_id` int(11) DEFAULT '0',
  `app_key` varchar(40) DEFAULT '',
  `app_type` varchar(10) DEFAULT '',
  `name` varchar(100) DEFAULT '',
  `version` varchar(20) DEFAULT '',
  `expire_at` datetime DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `remote_updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `app_key` (`app_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 插件机制（注册表、插件配置与实体数据、主题注册表）
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `plugin_registry` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plugin_id` varchar(64) NOT NULL DEFAULT '',
  `version` varchar(32) NOT NULL DEFAULT '1.0.0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `installed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `uniq_plugin_id` (`plugin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `plugin_data` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plugin_id` varchar(64) NOT NULL DEFAULT '',
  `entity_type` varchar(64) NOT NULL DEFAULT '',
  `entity_id` int(11) NOT NULL DEFAULT '0',
  `data_key` varchar(128) NOT NULL DEFAULT '',
  `data_value` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pk_lookup` (`plugin_id`,`entity_type`,`entity_id`,`data_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `theme_registry` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `theme_id` varchar(64) NOT NULL DEFAULT '',
  `version` varchar(32) NOT NULL DEFAULT '1.0.0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `installed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `uniq_theme_id` (`theme_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- 随包分发的插件数据表（采集 / 友链 / 万能词替换 / 粉丝等级）
-- ------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `crawl_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_key` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `site_url` varchar(255) NOT NULL DEFAULT '',
  `charset` varchar(20) NOT NULL DEFAULT 'UTF-8',
  `config` longtext NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rule_key` (`rule_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `crawl_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_id` int(11) NOT NULL,
  `status` enum('pending','running','done','failed','stopped') NOT NULL DEFAULT 'pending',
  `options` text,
  `error` text,
  `created_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `total_novels` int(11) NOT NULL DEFAULT '0',
  `processed_novels` int(11) NOT NULL DEFAULT '0',
  `total_chapters` int(11) NOT NULL DEFAULT '0',
  `fetched_chapters` int(11) NOT NULL DEFAULT '0',
  `current_novel` varchar(200) NOT NULL DEFAULT '',
  `stop_flag` tinyint(1) NOT NULL DEFAULT '0',
  `proxy` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `rule_id` (`rule_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `crawl_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `level` enum('info','success','error','warn') NOT NULL DEFAULT 'info',
  `message` text NOT NULL,
  `url` varchar(500) NOT NULL DEFAULT '',
  `http_code` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `crawl_proxies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proxy` varchar(255) NOT NULL,
  `label` varchar(100) NOT NULL DEFAULT '',
  `status` enum('active','dead') NOT NULL DEFAULT 'active',
  `last_error` varchar(255) NOT NULL DEFAULT '',
  `success_count` int(11) NOT NULL DEFAULT '0',
  `fail_count` int(11) NOT NULL DEFAULT '0',
  `last_used` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proxy` (`proxy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `crawl_blacklist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `keyword` varchar(200) NOT NULL,
  `match_type` varchar(20) NOT NULL DEFAULT 'title',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `keyword` (`keyword`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `crawl_filters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pattern` varchar(200) NOT NULL,
  `scope` varchar(30) NOT NULL DEFAULT 'chapter_content',
  `action` varchar(20) NOT NULL DEFAULT 'strip',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `crawl_category_map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rule_id` int(11) NOT NULL DEFAULT '0',
  `source_cat` varchar(100) NOT NULL,
  `local_cat_id` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rule_source` (`rule_id`,`source_cat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `crawl_novel_claims` (
  `rule_id` int(11) NOT NULL,
  `source_novel_id` varchar(100) NOT NULL,
  `task_id` int(11) NOT NULL DEFAULT '0',
  `claimed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`rule_id`,`source_novel_id`),
  KEY `task_id` (`task_id`),
  KEY `claimed_at` (`claimed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `friendlink_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `url` varchar(255) NOT NULL,
  `logo` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(255) NOT NULL DEFAULT '',
  `sort` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(4) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `friendlink_applies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_name` varchar(100) NOT NULL,
  `url` varchar(255) NOT NULL,
  `contact` varchar(60) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `description` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint(4) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wf_map` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `keyword` varchar(100) NOT NULL,
  `replacement` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint(4) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_keyword` (`keyword`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fan_levels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `novel_id` int(10) unsigned NOT NULL DEFAULT '0',
  `level` int(10) unsigned NOT NULL DEFAULT '1',
  `title` varchar(50) NOT NULL,
  `icon` varchar(50) NOT NULL DEFAULT 'fa-star',
  `min_words` int(10) unsigned NOT NULL DEFAULT '0',
  `min_comments` int(10) unsigned NOT NULL DEFAULT '0',
  `min_consume` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_novel_level` (`novel_id`,`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cron_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_key` varchar(64) NOT NULL,
  `source` varchar(16) NOT NULL DEFAULT 'system',
  `plugin_id` varchar(64) NOT NULL DEFAULT '',
  `name` varchar(120) NOT NULL DEFAULT '',
  `description` varchar(255) NOT NULL DEFAULT '',
  `interval` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_run_at` datetime NULL,
  `last_status` varchar(16) NOT NULL DEFAULT '',
  `last_message` varchar(255) NOT NULL DEFAULT '',
  `run_count` int(10) unsigned NOT NULL DEFAULT '0',
  `success_count` int(10) unsigned NOT NULL DEFAULT '0',
  `fail_count` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_task_key` (`task_key`),
  KEY `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `sort` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `novel_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `novel_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_novel_tag` (`novel_id`,`tag_id`),
  KEY `idx_tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `tags` (`name`) VALUES
('玄幻'),('仙侠'),('武侠'),('都市'),('科幻'),('历史'),('悬疑'),('游戏'),('言情'),('二次元'),
('修真'),('系统'),('穿越'),('重生'),('末世'),('无限流'),('种田'),('魔法'),('江湖'),('甜宠'),
('赘婿'),('爽文'),('热血'),('轻松'),('搞笑'),('逆袭'),('打脸'),('虐恋'),('复仇'),('大女主');

