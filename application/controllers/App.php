<?php

class App extends CI_Controller {

    public function index()
    {
        $this->load->view('header');
        $this->load->view('index');
        $this->load->view('footer');
    }

    public function view($videoId)
    {
        $this->load->view('header');
        $this->load->view('view', [
            'videoId' => $videoId
        ]);
        $this->load->view('footer');
    }

}
