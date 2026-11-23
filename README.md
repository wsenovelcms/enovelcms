# EnovelCms 小说建站系统

> 轻量、高效、功能完整的原生 PHP 小说建站程序

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange.svg)](https://mysql.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

- 当前版本：**3.0.4**（build 20261122）
- 官方网站：https://www.enovelcms.cn/

## 一、项目介绍

EnovelCms 是一套原生 PHP 小说建站管理系统，基于 **PHP 8.0+ / MySQL 5.7+** 开发，不依赖重型前端框架，服务器占用低。内置前台阅读、多端模板、后台管理、采集规则、会员充值与签到、榜单与自定义区块、多语言、广告管理、插件与主题扩展等能力，适合快速搭建网文阅读站点。

> 本仓库仅包含客户端程序；应用中心（插件/主题市场）为独立站点，不在此仓库内。

## 二、环境要求

| 环境 | 要求 |
| --- | --- |
| PHP | ≥ 8.0（兼容 8.1 / 8.2 / 8.3 / 8.4） |
| 必需扩展 | mbstring、curl；需支持安全随机数与密码哈希 |
| 建议扩展 | zip（主题/插件包解压）、fileinfo |
| 数据库 | MySQL / MariaDB ≥ 5.7 |
| Web 服务器 | Apache / Nginx / IIS |
| 目录权限 | `data/`、`uploads/`、`assets/uploads/` 需可读写 |

## 三、目录结构

```
enovelcms/
├── admin/        # 后台管理页面与资源
├── app/          # 应用核心：controllers / core / models / services
├── assets/       # 前台静态资源（CSS / JS / 字体 / 图片）
├── cli/          # 命令行脚本（定时任务、数据库自检等）
├── config/       # 配置目录（config.sample.php 为示例，安装后生成 config.php）
├── data/         # 运行时数据目录（缓存、日志、章节、封面等）
├── install/      # 安装向导与结构 SQL
├── plugins/      # 已安装插件
├── templates/    # 前台主题模板（PC / 移动端）
├── uploads/      # 用户上传内容
├── vendor/       # 第三方依赖
├── index.php     # 前台入口
└── 404.php
```

## 四、安装步骤

1. 将源码完整上传至网站根目录；
2. 确保 `data/`、`uploads/`、`assets/uploads/` 目录可读写；
3. 浏览器访问 `域名/install/install.php`，按向导完成环境检测与数据库导入；
   - 向导会在 `config/` 目录写入 `config.php`；也可手动复制 `config/config.sample.php` 为 `config/config.php` 后填写数据库信息；
4. 安装完成后建议移除 `install/` 目录以提升安全性；
5. 后台访问地址：`域名/admin/`，使用安装时设置的账号密码登录。

## 五、插件与主题

- 插件、主题以独立分发包形式安装，通过后台对应页面上传；
- 目录 `plugins/`、`templates/` 用于存放已安装内容。

## 六、免责声明

本软件仅提供建站技术能力，不存储、不编辑、不发布任何具体内容。使用者须确保所采集、转载的内容已获得合法授权或已进入公有领域，并自行承担内容合规与运营的全部法律责任。完整条款见包内《使用必读与免责声明.txt》。

## 七、开源协议

本项目采用 [MIT License](LICENSE) 开源；开源协议仅约束代码本身，不免除使用者在内容合规上的义务。
