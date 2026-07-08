<?php

class ErrorController extends Controller
{
    public function erro404()
    {
        $this->view('site/404');
    }
}
