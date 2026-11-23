<?php

class NotFoundController extends Controller
{
    public function index(): void
    {
        http_response_code(404);
        $this->view('404.tpl', ['message' => Language::get('page_not_found', '页面不存在')]);
    }
}
