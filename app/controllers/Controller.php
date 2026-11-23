<?php

abstract class Controller
{
    protected Database $db;
    protected Request $request;

    public function __construct(Database $db, Request $request)
    {
        $this->db = $db;
        $this->request = $request;
        $this->init();
    }

    protected function init(): void
    {

        $activeTheme = View::getTheme();
        $siteLogo = '';
        $themeLogoPath = ROOT_PATH . '/uploads/theme_logos/' . $activeTheme . '.png';
        if (is_file($themeLogoPath)) {
            $siteLogo = BASE_URL . '/uploads/theme_logos/' . $activeTheme . '.png';
        } else {
            $globalLogo = SettingModel::get('site_logo', '');
            if ($globalLogo) $siteLogo = $globalLogo;
        }
        View::assignArray([
            'siteName'    => SettingModel::get('site_name', 'EnovelCms'),
            'siteKeywords' => SettingModel::get('site_keywords', ''),
            'siteDesc'    => SettingModel::get('site_description', ''),
            'siteLogo'    => $siteLogo,
            'themeUrl'    => BASE_URL . '/templates/' . $activeTheme,
            'user_id'     => $this->request->userId(),
            'username'    => $_SESSION['username'] ?? '',
            'user'        => $this->currentUser(),
            'userGold'    => $this->getUserGold(),
            'csrf_token'  => Security::csrfToken(),
            'seo'         => ['title' => SettingModel::get('site_name', 'EnovelCms'), 'keywords' => '', 'description' => ''],
            'siteLangs'   => Language::getAllLanguages(),
            'currentLang' => Language::current(),
            'analytics_code' => (string)SettingModel::get('analytics_code', ''),
        ]);
    }

    private function getUserGold(): int
    {
        $uid = $this->request->userId();
        if ($uid <= 0) return 0;
        $user = UserModel::findById($uid, 'gold');
        return (int)($user['gold'] ?? 0);
    }

    private function currentUser(): ?array
    {
        $uid = $this->request->userId();
        if ($uid <= 0) return null;
        static $cached = false;
        static $row = null;
        if ($cached) return $row;
        $row = UserModel::findById($uid);
        if ($row) unset($row['password']);
        $cached = true;
        return $row;
    }

    protected function assign(string $key, $value): void
    {
        View::assign($key, $value);
    }

    protected function view(string $template, array $data = []): void
    {
        $data = Hook::applyFilters('view_before_render', $data, $template);
        View::render($template, $data);
    }

    protected function json($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function setFlash(array $result): void
    {
        $_SESSION['front_flash'] = $result;
    }

    protected function takeFlash(): ?array
    {
        if (empty($_SESSION['front_flash'])) return null;
        $flash = $_SESSION['front_flash'];
        unset($_SESSION['front_flash']);
        return is_array($flash) ? $flash : null;
    }

    protected function requireLogin(): void
    {
        if (!$this->request->isLoggedIn()) {
            $this->redirect(url_full('user', ['action' => 'login', 'query' => ['redirect' => $this->request->path]]));
        }
    }
}
