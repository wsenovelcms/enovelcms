<?php

class AuthorCenterController extends Controller
{
    private AuthorService $author;

    public function handle(string $action = 'works'): void
    {
        View::assignArray(['currentModule' => 'authorcenter', 'currentAction' => $action]);
        $this->requireLogin();
        $this->author = new AuthorService();

        $allowForNonAuthor = ['apply'];
        $userRow = UserModel::findById($this->request->userId(), 'is_author');
        $isAuthor = (int)($userRow['is_author'] ?? 0) === 1;
        if (!$isAuthor && !in_array($action, $allowForNonAuthor, true)) {
            $this->redirect(url_full('authorcenter', ['action' => 'apply']));
        }

        $method = 'action' . str_replace('_', '', ucwords($action, '_'));
        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            http_response_code(404);
            $this->view('404.tpl', ['message' => Language::get('page_not_found', '页面不存在')]);
        }
    }

    private function novelPermissions(array $novel): array
    {
        $signed = (int)($novel['is_vip'] ?? 0) === 1;
        $finished = (int)($novel['status'] ?? 0) === 1;
        $denyMsg = (string)SettingModel::get(
            'signed_deny_msg',
            Language::get('signed_locked_notice', '该作品已完本签约，相关内容已锁定，如需调整请联系网站管理员')
        );
        if (!$signed || !$finished) {
            return [
                'signed' => $signed, 'finished' => $finished,
                'can_edit_info' => true, 'can_manage_volume' => true,
                'can_manage_chapter' => true, 'can_reorder' => true,
                'can_change_cover' => true, 'deny_msg' => $denyMsg,
            ];
        }
        return [
            'signed' => true, 'finished' => true,
            'can_edit_info' => (int)SettingModel::get('signed_allow_edit_info', 0) === 1,
            'can_manage_volume' => (int)SettingModel::get('signed_allow_manage_volume', 0) === 1,
            'can_manage_chapter' => (int)SettingModel::get('signed_allow_manage_chapter', 0) === 1,

            'can_reorder' => false,
            'can_change_cover' => false,
            'deny_msg' => $denyMsg,
        ];
    }

    private function denyIfLocked(bool $allowed, string $msg, bool $ajax = false): bool
    {
        if ($allowed) return false;
        if ($ajax) {
            $this->json(['ok' => false, 'msg' => $msg]);
            return true;
        }
        $this->view('lock.tpl', ['message' => $msg]);
        return true;
    }

    private function actionApply(): void
    {
        $userId = $this->request->userId();
        if ($this->request->isPost()) {
            if (!Security::verifyCsrf()) {
                $this->redirect(url_full('authorcenter', ['action' => 'apply']));
            }
            if ($err = Security::requireCaptcha()) {
                $_SESSION['applyResult'] = ['ok' => false, 'msg' => $err];
                $this->redirect(url_full('authorcenter', ['action' => 'apply']));
            }
            $_SESSION['applyResult'] = $this->author->apply($userId, $this->request->post);
            $this->redirect(url_full('authorcenter', ['action' => 'apply']));
        }
        $applyResult = $_SESSION['applyResult'] ?? null;
        unset($_SESSION['applyResult']);
        $existing = AuthorApplicationModel::getByUser($userId);

        $status = $existing ? (int)$existing['status'] : -1;

        $showForm = ($status === -1 || $status === 2);
        $this->view('author/apply.tpl', [
            'applyResult'  => $applyResult,
            'existing'     => $existing,
            'status'       => $status,
            'showForm'     => $showForm,
            'rejectReason' => $existing['reject_reason'] ?? '',
        ]);
    }

    private function actionWorks(): void
    {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $novelResult = $_SESSION['novelResult'] ?? null;
        unset($_SESSION['novelResult']);
        $this->view('author/works.tpl', [
            'works' => $this->author->getWorks($this->request->userId()),
            'novelResult' => $novelResult,
        ]);
    }

    private function actionNoveladd(): void
    {
        if ($this->request->isPost()) {
            if (!Security::verifyCsrf()) {
                $this->redirect(url_full('authorcenter', ['action' => 'novel_add']));
            }
            $data = $this->request->post;
            unset($data['_csrf']);
            $tagIds = $this->extractTagIds($data);
            $maxTags = TagModel::getMaxTags();
            if (count($tagIds) > $maxTags) {
                View::assign('novelResult', ['ok' => false, 'msg' => Language::get('author_tag_limit', '每部作品最多选择 %d 个标签', $maxTags)]);
            } else {
                if (!empty($_FILES['cover']['tmp_name'])) {
                    $coverPath = $this->author->saveCover($_FILES['cover']);
                    if ($coverPath) { $data['cover'] = $coverPath; } else { unset($data['cover']); }
                } else {
                    unset($data['cover']);
                }
                $result = $this->author->createNovel($this->request->userId(), $data);
                if ($result['ok']) {
                    $newId = (int)($result['id'] ?? 0);
                    TagModel::syncNovelTags($newId, $tagIds);
                    Hook::doAction('author_novel_published', $newId, $this->request->userId(), $_POST);
                    if (session_status() === PHP_SESSION_NONE) @session_start();
                    $_SESSION['novelResult'] = ['ok' => true, 'msg' => Language::get('msg_novel_publish_success', '作品发布成功')];
                    $this->redirect(url_full('authorcenter', ['action' => 'works']));
                }
                View::assign('novelResult', $result);
            }
        }
        $this->view('author/novel_add.tpl', [
            'categories' => CategoryModel::getCategoryTree(),
            'allTags'    => TagModel::getAllTags(),
            'maxTags'    => TagModel::getMaxTags(),
        ]);
    }

    private function extractTagIds(array &$data): array
    {
        $raw = $data['tag_ids'] ?? [];
        unset($data['tag_ids']);
        if (!is_array($raw)) return [];
        return array_values(array_unique(array_map('intval', $raw)));
    }

    private function actionNoveledit(): void
    {
        $novelId = $this->request->get('id', 0, 'int');
        $userId = $this->request->userId();
        if (!$this->author->checkOwnership($novelId, $userId)) {
            $this->view('404.tpl', ['message' => Language::get('msg_no_permission_novel', '无权操作该作品')]);
            return;
        }
        $novelRow = NovelModel::findById($novelId);
        $perm = $this->novelPermissions($novelRow);
        if ($this->request->isPost()) {
            if (!Security::verifyCsrf()) {
                $this->redirect(url_full('authorcenter', ['action' => 'novel_edit', 'query' => ['id' => $novelId]]));
            }
            if ($this->denyIfLocked($perm['can_edit_info'], $perm['deny_msg'])) return;
            $raw = $this->request->post;
            unset($raw['_csrf']);
            $tagIds = $this->extractTagIds($raw);
            $maxTags = TagModel::getMaxTags();
            if (count($tagIds) > $maxTags) {
                View::assign('novelResult', ['ok' => false, 'msg' => Language::get('author_tag_limit', '每部作品最多选择 %d 个标签', $maxTags)]);
                $this->view('author/novel_edit.tpl', $this->novelEditViewData($novelRow, $perm));
                return;
            }

            $update = [];
            $title = trim((string)($raw['title'] ?? ''));
            if ($title === '') {
                View::assign('novelResult', ['ok' => false, 'msg' => Language::get('title_required', '书名不能为空')]);
                $this->view('author/novel_edit.tpl', $this->novelEditViewData($novelRow, $perm));
                return;
            }
            $update['title'] = excerpt($title, 100, '');
            $update['category_id'] = (int)($raw['category_id'] ?? 0);
            $status = (int)($raw['status'] ?? 0);
            $update['status'] = in_array($status, [0, 1], true) ? $status : 0;
            $update['description'] = excerpt(trim((string)($raw['description'] ?? '')), 2000, '');

            if (!empty($_FILES['cover']['tmp_name']) && $perm['can_change_cover']) {
                $coverPath = $this->author->saveCover($_FILES['cover']);
                if ($coverPath) {

                    $oldCover = str_replace('\\', '/', (string)($raw['old_cover'] ?? ''));
                    if ($oldCover !== '') {
                        $candidate = realpath(ROOT_PATH . ltrim($oldCover, '/'));
                        if ($candidate && is_file($candidate)) {
                            $coverRoots = array_filter(array(
                                realpath(ROOT_PATH . 'uploads/covers'),
                                realpath(ROOT_PATH . 'data/uploads/covers'),
                            ));
                            foreach ($coverRoots as $coverRoot) {
                                if (strpos($candidate, $coverRoot . DIRECTORY_SEPARATOR) === 0) {
                                    @unlink($candidate);
                                    break;
                                }
                            }
                        }
                    }
                    $update['cover'] = $coverPath;
                }
            }
            NovelModel::update($update, ['id' => $novelId]);
            TagModel::syncNovelTags($novelId, $tagIds);
            $this->redirect(url_full('authorcenter', ['action' => 'works']));
        }
        $this->view('author/novel_edit.tpl', $this->novelEditViewData($novelRow, $perm));
    }

    private function novelEditViewData(array $novelRow, array $perm): array
    {
        return [
            'novel'          => $novelRow,
            'perm'           => $perm,
            'categories'     => CategoryModel::getCategoryTree(),
            'allTags'        => TagModel::getAllTags(),
            'selectedTagIds' => TagModel::getTagIdsByNovel((int)$novelRow['id']),
            'selectedTagIdsStr' => implode(',', TagModel::getTagIdsByNovel((int)$novelRow['id'])),
            'maxTags'        => TagModel::getMaxTags(),
        ];
    }

    private function actionNovelsign(): void
    {
        $novelId = $this->request->post('id', 0, 'int') ?: $this->request->get('id', 0, 'int');
        $userId = $this->request->userId();
        $ajax = $this->request->post('ajax', '0', 'str') === '1';
        if (!$this->author->checkOwnership($novelId, $userId)) {
            if ($ajax) $this->json(['ok' => false, 'msg' => Language::get('msg_no_permission_novel', '无权操作该作品')]);
            $this->view('404.tpl', ['message' => Language::get('msg_no_permission_novel', '无权操作该作品')]);
            return;
        }
        if (!$this->request->isPost() || !Security::verifyCsrf()) {
            if ($ajax) $this->json(['ok' => false, 'msg' => Language::get('csrf_failed_refresh', 'CSRF验证失败，请刷新重试')]);
            $this->redirect(url_full('authorcenter', ['action' => 'works']));
        }
        if ($err = Security::requireCaptcha()) {
            if ($ajax) $this->json(['ok' => false, 'msg' => $err]);
            if (session_status() === PHP_SESSION_NONE) @session_start();
            $_SESSION['novelResult'] = ['ok' => false, 'msg' => $err];
            $this->redirect(url_full('authorcenter', ['action' => 'works']));
            return;
        }
        $novel = NovelModel::findById($novelId, 'is_vip,vip_audit_status');
        $msg = '';
        if ($novel && (int)$novel['is_vip'] === 1) {
            $msg = ['ok' => false, 'msg' => Language::get('vip_already_signed', '该作品已是VIP，无需申请签约')];
        } elseif ($novel && (int)$novel['vip_audit_status'] === 2) {
            NovelModel::update(['vip_audit_status' => 0], ['id' => $novelId]);
            $msg = ['ok' => true, 'msg' => Language::get('resign_submitted', '已重新提交签约申请，请等待后台审核')];
        } elseif ($novel && (int)$novel['vip_audit_status'] === -1) {
            NovelModel::update(['vip_audit_status' => 0], ['id' => $novelId]);
            $msg = ['ok' => true, 'msg' => Language::get('sign_applied', '签约申请已提交，请等待后台审核')];
        } else {
            $msg = ['ok' => false, 'msg' => Language::get('status_not_allow_apply', '当前状态不允许申请')];
        }
        if ($ajax) $this->json($msg);
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $_SESSION['novelResult'] = $msg;
        $this->redirect(url_full('authorcenter', ['action' => 'works']));
    }

    private function actionChapterlist(): void
    {
        $novelId = $this->request->get('novel_id', 0, 'int');
        if (!$this->author->checkOwnership($novelId, $this->request->userId())) {
            $this->view('404.tpl', ['message' => Language::get('msg_no_permission_novel', '无权操作该作品')]);
            return;
        }

        $chapterResult = null;
        if (session_status() === PHP_SESSION_NONE) @session_start();
        if (!empty($_SESSION['chapterResult'])) {
            $chapterResult = $_SESSION['chapterResult'];
            unset($_SESSION['chapterResult']);
        }
        $allChapters = ChapterModel::getListByNovel($novelId);
        $perPage = 20;
        $total = count($allChapters);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($pages, $this->request->get('page', 1, 'int')));
        $offset = ($page - 1) * $perPage;
        $chapters = array_slice($allChapters, $offset, $perPage);
        $volumes = VolumeModel::getByNovel($novelId);

        $volumeTotal = [];
        $startedBefore = [];
        foreach ($allChapters as $i => $ac) {
            $avid = (int)($ac['volume_id'] ?? 0);
            $volumeTotal[$avid] = ($volumeTotal[$avid] ?? 0) + 1;
            if ($i < $offset) { $startedBefore[$avid] = true; }
        }

        $volPos = [];
        $volCounter = [];
        foreach ($allChapters as $ac) {
            $avid = (int)($ac['volume_id'] ?? 0);
            $p = $volCounter[$avid] ?? 0;
            $volPos[(int)$ac['id']] = $p;
            $volCounter[$avid] = $p + 1;
        }

        $grouped = [];
        foreach ($volumes as $vol) {
            $grouped[(int)$vol['id']] = ['volume' => $vol, 'chapters' => []];
        }
        foreach ($chapters as $ch) {
            $vid = (int)($ch['volume_id'] ?? 0);
            if (!isset($grouped[$vid])) {
                $grouped[$vid] = ['volume' => ['id' => $vid, 'title' => Language::get('volume_main_text', '正文')], 'chapters' => []];
            }
            $grouped[$vid]['chapters'][] = $ch;
        }
        foreach ($grouped as $vid => $g) {
            if (empty($g['chapters'])) {
                unset($grouped[$vid]);
                continue;
            }

            $grouped[$vid]['show_header'] = !isset($startedBefore[$vid]);
            $grouped[$vid]['total_count'] = $volumeTotal[$vid] ?? count($g['chapters']);
        }

        $listNovel = NovelModel::findById($novelId);
        $this->view('author/chapter_list.tpl', [
            'novel'    => $listNovel,
            'perm'     => $this->novelPermissions($listNovel),
            'chapterResult' => $chapterResult,
            'chapters' => $chapters,
            'chapters_by_volume' => array_values($grouped),
            'all_chapters' => $allChapters,
            'volumes'  => $volumes,
            'vol_pos'  => $volPos,
            'volume_total' => $volumeTotal,
            'page'     => $page,
            'pages'    => $pages,
            'total'    => $total,
            'per_page' => $perPage,
            'offset'   => $offset,
        ]);
    }

    private function actionChapteradd(): void
    {
        $novelId = $this->request->get('novel_id', 0, 'int');
        if (!$this->author->checkOwnership($novelId, $this->request->userId())) {
            $this->view('404.tpl', ['message' => Language::get('msg_no_permission_novel', '无权操作该作品')]);
            return;
        }
        $novel = NovelModel::findById($novelId);
        $addPerm = $this->novelPermissions($novel);
        if ($this->denyIfLocked($addPerm['can_manage_chapter'], $addPerm['deny_msg'])) return;
        if ($this->request->isPost()) {
            if (!Security::verifyCsrf()) {
                $this->redirect(url_full('authorcenter', ['action' => 'chapter_add', 'query' => ['novel_id' => $novelId]]));
            }
            $result = $this->author->createChapter($novelId, $this->request->post);
            View::assign('chapterResult', $result);
            if ($result['ok']) {
                Hook::doAction('author_chapter_published', $novelId, $result['chapter_id'] ?? 0, $_POST);
                $this->redirect(url_full('authorcenter', ['action' => 'chapter_list', 'query' => ['novel_id' => $novelId]]));
            }
        }

        $defaultVip = !empty($novel['last_chapter_is_vip']) && (int)$novel['last_chapter_is_vip'] === 1;

        $this->view('author/chapter_add.tpl', [
            'novel'              => $novel,
            'volumes'            => VolumeModel::getByNovel($novelId),
            'default_vip'        => $defaultVip,
        ]);
    }

    private function actionChapteredit(): void
    {
        $chapterId = $this->request->get('id', 0, 'int');
        $chapter = ChapterModel::findById($chapterId);
        if (!$chapter || !$this->author->checkOwnership((int)$chapter['novel_id'], $this->request->userId())) {
            $this->view('404.tpl', ['message' => Language::get('msg_no_permission_chapter', '无权操作该章节')]);
            return;
        }
        $editPerm = $this->novelPermissions(NovelModel::findById((int)$chapter['novel_id']));
        if ($this->denyIfLocked($editPerm['can_manage_chapter'], $editPerm['deny_msg'])) return;
        if ($this->request->isPost()) {
            if (!Security::verifyCsrf()) {
                $this->redirect(url_full('authorcenter', ['action' => 'chapter_edit', 'query' => ['id' => $chapterId]]));
            }
            $result = $this->author->updateChapter($chapterId, $this->request->post);
            if ($result['ok']) {
                if (session_status() === PHP_SESSION_NONE) @session_start();
                $_SESSION['chapterResult'] = $result;
                $this->redirect(url_full('authorcenter', ['action' => 'chapter_list', 'query' => ['novel_id' => (int)$chapter['novel_id']]]));
            }
            View::assign('chapterResult', $result);
        }
        $this->view('author/chapter_edit.tpl', [
            'chapter' => $chapter,
            'novel'   => NovelModel::findById((int)$chapter['novel_id']),
            'content' => ChapterModel::getRawContent($chapter),
            'volumes' => VolumeModel::getByNovel((int)$chapter['novel_id']),
        ]);
    }

    private function actionChapterdelete(): void
    {
        $chapterId = $this->request->post('chapter_id', 0, 'int');
        $chapter = ChapterModel::findById($chapterId);
        $ajax = $this->request->post('ajax', '0', 'str') === '1';
        if (!$chapter || !$this->author->checkOwnership((int)$chapter['novel_id'], $this->request->userId())) {
            if ($ajax) $this->json(['ok' => false, 'msg' => Language::get('msg_no_permission_chapter', '无权操作该章节')]);
            $this->view('404.tpl', ['message' => Language::get('msg_no_permission_chapter', '无权操作该章节')]);
            return;
        }
        $delPerm = $this->novelPermissions(NovelModel::findById((int)$chapter['novel_id']));
        if ($this->denyIfLocked($delPerm['can_manage_chapter'], $delPerm['deny_msg'], $ajax)) return;
        if (!$this->request->isPost() || !Security::verifyCsrf()) {
            if ($ajax) $this->json(['ok' => false, 'msg' => Language::get('csrf_failed_refresh', 'CSRF验证失败，请刷新重试')]);
            $this->redirect(url_full('authorcenter', ['action' => 'works']));
        }
        if (session_status() === PHP_SESSION_NONE) @session_start();
        if ($err = Security::requireCaptcha()) {
            if ($ajax) $this->json(['ok' => false, 'msg' => $err]);
            $_SESSION['chapterResult'] = ['ok' => false, 'msg' => $err];
            $this->redirect(url_full('authorcenter', ['action' => 'chapter_list', 'query' => ['novel_id' => $chapter['novel_id']]]));
            return;
        }
        ChapterModel::deleteById($chapterId);
        NovelModel::refreshStats((int)$chapter['novel_id']);
        if ($ajax) $this->json(['ok' => true, 'msg' => Language::get('msg_chapter_deleted', '章节已删除')]);
        $_SESSION['chapterResult'] = ['ok' => true, 'msg' => Language::get('msg_chapter_deleted', '章节已删除')];
        $this->redirect(url_full('authorcenter', ['action' => 'chapter_list', 'query' => ['novel_id' => $chapter['novel_id']]]));
    }

    private function actionChaptermove(): void
    {
        $chapterId = $this->request->post('chapter_id', 0, 'int');
        $chapter = ChapterModel::findById($chapterId);
        if (!$chapter || !$this->author->checkOwnership((int)$chapter['novel_id'], $this->request->userId())) {
            $this->view('404.tpl', ['message' => Language::get('msg_no_permission_chapter', '无权操作该章节')]);
            return;
        }
        $movePerm = $this->novelPermissions(NovelModel::findById((int)$chapter['novel_id']));
        if ($this->denyIfLocked($movePerm['can_reorder'], $movePerm['deny_msg'])) return;
        if (!$this->request->isPost() || !Security::verifyCsrf()) {
            $this->redirect(url_full('authorcenter', ['action' => 'chapter_list', 'query' => ['novel_id' => $chapter['novel_id']]]));
        }
        $novelId = (int)$chapter['novel_id'];
        (new ChapterService())->reorderChapter($chapter, [
            'target_id' => $this->request->post('target_id', 0, 'int'),
            'position'  => $this->request->post('position', '', 'str'),
            'direction' => $this->request->post('direction', '', 'str'),
        ]);
        $this->redirect(url_full('authorcenter', ['action' => 'chapter_list', 'query' => ['novel_id' => $novelId]]));
    }

    private function actionVolumelist(): void
    {
        $novelId = $this->request->get('novel_id', 0, 'int');
        if (!$this->author->checkOwnership($novelId, $this->request->userId())) {
            $this->view('404.tpl', ['message' => Language::get('msg_no_permission_novel', '无权操作该作品')]);
            return;
        }
        $allVolumes = VolumeModel::getByNovel($novelId);
        foreach ($allVolumes as &$v) {
            $v['chapter_count'] = VolumeModel::countChapters((int)$v['id']);
        }
        unset($v);
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $volumeResult = $_SESSION['volumeResult'] ?? null;
        unset($_SESSION['volumeResult']);
        $perPage = 20;
        $total = count($allVolumes);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($pages, $this->request->get('page', 1, 'int')));
        $offset = ($page - 1) * $perPage;
        $volumes = array_slice($allVolumes, $offset, $perPage);
        $volListNovel = NovelModel::findById($novelId);
        $this->view('author/volume_list.tpl', [
            'novel'        => $volListNovel,
            'perm'         => $this->novelPermissions($volListNovel),
            'volumes'      => $volumes,
            'all_volumes'  => $allVolumes,
            'volumeResult' => $volumeResult,
            'page'         => $page,
            'pages'        => $pages,
            'total'        => $total,
            'offset'       => $offset,
        ]);
    }

    private function actionVolumeadd(): void
    {
        $novelId = $this->request->get('novel_id', 0, 'int');
        if (!$this->author->checkOwnership($novelId, $this->request->userId())) {
            $this->view('404.tpl', ['message' => Language::get('msg_no_permission_novel', '无权操作该作品')]);
            return;
        }
        $vAddPerm = $this->novelPermissions(NovelModel::findById($novelId));
        if ($this->denyIfLocked($vAddPerm['can_manage_volume'], $vAddPerm['deny_msg'])) return;
        if ($this->request->isPost()) {
            if (!Security::verifyCsrf()) {
                $this->redirect(url_full('authorcenter', ['action' => 'volume_add', 'query' => ['novel_id' => $novelId]]));
            }
            $title = trim($this->request->post('title', '', 'str'));
            if ($title === '') {
                if (session_status() === PHP_SESSION_NONE) @session_start();
                $_SESSION['volumeResult'] = ['ok' => false, 'msg' => Language::get('msg_please_enter_volume_name', '请输入分卷名称')];
                $this->redirect(url_full('authorcenter', ['action' => 'volume_list', 'query' => ['novel_id' => $novelId]]));
            }
            $sort = $this->request->post('sort', 0, 'int');
            if ($sort <= 0) {
                $existing = VolumeModel::all(array('novel_id' => $novelId), 'sort DESC', 1);
                $sort = isset($existing[0]['sort']) ? ((int)$existing[0]['sort'] + 1) : 1;
            }
            VolumeModel::create([
                'novel_id' => $novelId,
                'title'    => $title,
                'sort'     => $sort,
            ]);
            $this->redirect(url_full('authorcenter', ['action' => 'volume_list', 'query' => ['novel_id' => $novelId]]));
        }
        $this->view('author/volume_add.tpl', ['novel' => NovelModel::findById($novelId)]);
    }

    private function actionVolumeedit(): void
    {
        $volumeId = $this->request->get('id', 0, 'int');
        $volume = VolumeModel::findById($volumeId);
        if (!$volume || !$this->author->checkOwnership((int)$volume['novel_id'], $this->request->userId())) {
            $this->view('404.tpl', ['message' => Language::get('msg_no_permission_volume', '无权操作该分卷')]);
            return;
        }
        $vEditPerm = $this->novelPermissions(NovelModel::findById((int)$volume['novel_id']));
        if ($this->denyIfLocked($vEditPerm['can_manage_volume'], $vEditPerm['deny_msg'])) return;
        if ($this->request->isPost()) {
            if (!Security::verifyCsrf()) {
                $this->redirect(url_full('authorcenter', ['action' => 'volume_edit', 'query' => ['id' => $volumeId]]));
            }
            $title = trim($this->request->post('title', '', 'str'));
            if ($title === '') {
                if (session_status() === PHP_SESSION_NONE) @session_start();
                $_SESSION['volumeResult'] = ['ok' => false, 'msg' => Language::get('msg_please_enter_volume_name', '请输入分卷名称')];
                $this->redirect(url_full('authorcenter', ['action' => 'volume_list', 'query' => ['novel_id' => $volume['novel_id']]]));
            }
            VolumeModel::update([
                'title' => $title,
                'sort'  => $this->request->post('sort', 0, 'int'),
            ], ['id' => $volumeId]);
            $this->redirect(url_full('authorcenter', ['action' => 'volume_list', 'query' => ['novel_id' => $volume['novel_id']]]));
        }
        $this->view('author/volume_edit.tpl', ['volume' => $volume]);
    }

    private function actionVolumedelete(): void
    {
        $volumeId = $this->request->post('volume_id', 0, 'int');
        $volume = VolumeModel::findById($volumeId);
        $ajax = $this->request->post('ajax', '0', 'str') === '1';
        if (!$volume || !$this->author->checkOwnership((int)$volume['novel_id'], $this->request->userId())) {
            if ($ajax) $this->json(['ok' => false, 'msg' => Language::get('msg_no_permission_volume', '无权操作该分卷')]);
            $this->view('404.tpl', ['message' => Language::get('msg_no_permission_volume', '无权操作该分卷')]);
            return;
        }
        $delPerm = $this->novelPermissions(NovelModel::findById((int)$volume['novel_id']));
        if ($this->denyIfLocked($delPerm['can_manage_volume'], $delPerm['deny_msg'], $ajax)) return;
        if (!$this->request->isPost() || !Security::verifyCsrf()) {
            if ($ajax) $this->json(['ok' => false, 'msg' => Language::get('csrf_failed_refresh', 'CSRF验证失败，请刷新重试')]);
            $this->redirect(url_full('authorcenter', ['action' => 'volume_list', 'query' => ['novel_id' => $volume['novel_id']]]));
            return;
        }
        if (session_status() === PHP_SESSION_NONE) @session_start();
        if ($err = Security::requireCaptcha()) {
            if ($ajax) $this->json(['ok' => false, 'msg' => $err]);
            $_SESSION['volumeResult'] = ['ok' => false, 'msg' => $err];
            $this->redirect(url_full('authorcenter', ['action' => 'volume_list', 'query' => ['novel_id' => $volume['novel_id']]]));
            return;
        }
        $mode = $this->request->post('mode', 'delete', 'str');
        $targetId = $this->request->post('target_volume_id', 0, 'int');
        $result = (new NovelService())->deleteVolume($volumeId, $mode, $targetId);
        if ($ajax) {
            $this->json($result);
            return;
        }
        $_SESSION['volumeResult'] = $result;
        $this->redirect(url_full('authorcenter', ['action' => 'volume_list', 'query' => ['novel_id' => $volume['novel_id']]]));
    }

    private function actionIncome(): void
    {
        $userId = $this->request->userId();
        $this->view('author/income.tpl', [
            'stats'   => $this->author->getIncomeStats($userId),
            'byNovel' => $this->author->getIncomeByNovel($userId),
            'logs'    => AuthorIncomeModel::getByAuthor($userId)['list'],
        ]);
    }

    private function actionWithdraw(): void
    {
        $userId = $this->request->userId();
        if ($this->request->isPost()) {
            if (!Security::verifyCsrf()) {
                $this->redirect(url_full('authorcenter', ['action' => 'withdraw']));
            }
            if ($err = Security::requireCaptcha()) {
                $_SESSION['withdrawResult'] = ['ok' => false, 'msg' => $err];
                $this->redirect(url_full('authorcenter', ['action' => 'withdraw']));
            }

            if ($this->request->post('save_info', 0, 'int')) {
                $data = $this->request->post;
                unset($data['_csrf']);

                $qrContent = trim($this->request->post('qrcode_content', '', 'text'));
                $qrError = '';
                if ($qrContent !== '') {
                    $qrPath = $this->author->generatePayoutQr($qrContent, $userId);
                    if ($qrPath) {
                        $data['existing_qrcode'] = $qrPath;
                    } else {
                        $qrError = Language::get('qrcode_generate_failed', '收款码生成失败，请上传清晰、完整、无遮挡的收款码截图');
                    }
                }
                if ($qrError !== '') {
                    $_SESSION['withdrawInfoResult'] = ['ok' => false, 'msg' => $qrError];
                } else {
                    $_SESSION['withdrawInfoResult'] = $this->author->saveWithdrawInfo($userId, $data);
                }
            } else {
                $_SESSION['withdrawResult'] = $this->author->applyWithdraw($userId, $this->request->post('amount', 0, 'float'));
            }
            $this->redirect(url_full('authorcenter', ['action' => 'withdraw']));
        }
        $withdrawResult = $_SESSION['withdrawResult'] ?? null;
        unset($_SESSION['withdrawResult']);
        $withdrawInfoResult = $_SESSION['withdrawInfoResult'] ?? null;
        unset($_SESSION['withdrawInfoResult']);
        $this->view('author/withdraw.tpl', [
            'withdrawResult'  => $withdrawResult,
            'withdrawInfoResult' => $withdrawInfoResult,
            'user'     => UserModel::findById($userId),
            'info'     => $this->author->getWithdrawInfo($userId),
            'requests' => WithdrawRequestModel::getByUser($userId),
            'minAmount' => (float)SettingModel::get('vip_min_withdraw_amount', 10),
            'feeRate'   => (float)SettingModel::get('withdraw_fee_rate', 5),
        ]);
    }
}
