<?php

class HomeController extends Controller
{
    public function index(): void
    {
        if (!defined('ENOVEL_HOME')) define('ENOVEL_HOME', true);

        $service = new NovelService();
        $data = $service->getHomeData();
        View::assign('seo', SeoService::getMeta('home'));

        $this->view('home.tpl', $data);
    }
}
