<?php

class HomeController extends Controller
{
    public function index(): void
    {

        if (!defined('ENOVEL_HOME')) define('ENOVEL_HOME', true);

        $service = new NovelService();
        $data = $service->getHomeData();

        $seo = SeoService::getMeta('home');
        View::assign('seo', $seo);

        $this->view('home.tpl', $data);
    }
}
