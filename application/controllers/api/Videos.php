<?php

class Videos extends CI_Controller {
    public function __construct()
    {
        parent::__construct();

        $this->load->model('Video_model', 'videos', TRUE);
    }

    public function _remap($method)
    {
        $requestMethod = $this->input->server('REQUEST_METHOD');

        switch ($method) {
            case 'index':
                if ($requestMethod === 'POST') {
                    return $this->create();
                } else {
                    return $this->index();
                }

            default:
                if ($requestMethod === 'POST') {
                    return $this->update($method);
                } else {
                    return $this->show($method);
                }
        }
    }

    public function index()
    {
        $videos = $this->videos->getAll();
        $this->responseJson($videos);
    }

    public function show($id)
    {
        $video = $this->videos->get($id);
        $this->responseJson($video);
    }

    public function create()
    {
        $videoId = $this->videos->create([
            'url' => trim($_POST['url']),
            'status' => 'waiting',
        ]);
    }

    public function update($id)
    {
        $this->videos->update($id, [
            'transcript' => $_POST['transcript'],
        ]);
    }

    protected function responseJson($data)
    {
        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }
}
