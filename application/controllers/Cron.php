<?php

use Google\Cloud\Speech\SpeechClient;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Core\ExponentialBackoff;

class Cron extends CI_Controller {
    public function __construct()
    {
        parent::__construct();

        if(!$this->input->is_cli_request()) {
            echo 'Not allowed';
            exit();
        }
    }

    public function index()
    {
        $this->load->model('Video_model', 'videos', TRUE);

        $video = $this->videos->getFirstUnprocessedVideo();
        if (!$video) {
            return;
        }

        try {
            $this->videos->update($video->id, [
                'status' => 'processing',
            ]);

            $videoFile = $this->downloadVideo($video);
            if (!$videoFile) {
                throw new Exception("Can't download video file");
            }

            $flacFile = $this->convertVideoToFlac($videoFile);
            if (!$flacFile) {
                throw new Exception("Can't convert video file to audio file");
            }

            $flacObject = $this->uploadFlacToCloud($flacFile);
            $transcript = $this->getTranscript($flacObject);

            $this->videos->update($video->id, [
                'transcript' => json_encode($transcript),
                'status' => 'done',
            ]);

        } catch (Exception $e) {
            print ($e->getMessage());
            if (file_exists($videoFile)){
                unlink($videoFile);
            }

            $this->videos->update($video->id, [
                'status' => 'failed',
            ]);
        }
    }

    private function downloadVideo($video)
    {
        $videoFile = FCPATH . 'storage/' .  $video->id . '.mp4';
        if (!file_put_contents($videoFile, fopen($video->url, 'r'))) {
            return false;
        }

        return $videoFile;
    }

    private function convertVideoToFlac($videoFile)
    {
        $flacFile = $videoFile . '.flac';

        $ffmpegCommand = "ffmpeg -y -i $videoFile -ac 1 -ar 16000 $flacFile";
        system($ffmpegCommand);

        if (!file_exists($flacFile)) {
            return false;
        }

        return $flacFile;
    }

    private function uploadFlacToCloud($flacFile)
    {
        $storage = new StorageClient();
        $file = fopen($flacFile, 'r');
        $bucket = $storage->bucket('hbm-speech-test');
        $object = $bucket->upload($file, [
            'name' => basename($flacFile),
        ]);

        unlink($flacFile);

        return $object;
    }

    private function getTranscript($flacObject)
    {
        $speech = new SpeechClient([
            'languageCode' => 'en-US',
        ]);

        $options = [
            'encoding' => 'FLAC',
            'sampleRateHertz' => 16000,
            'enableWordTimeOffsets' => true,
        ];

        $operation = $speech->beginRecognizeOperation(
            $flacObject,
            $options
        );

        $backoff = new ExponentialBackoff(100);
        $backoff->execute(function () use ($operation) {
            print('Waiting for operation to complete' . PHP_EOL);
            $operation->reload();
            if (!$operation->isComplete()) {
                throw new Exception('Job has not yet completed', 500);
            }
        });

        if ($operation->isComplete()) {
            $flacObject->delete();

            $results = $operation->results();

            $transcript = [];

            foreach ($results as $result) {
                $alternative = $result->alternatives()[0];
                foreach ($alternative['words'] as $wordInfo) {
                    $transcript[] = $wordInfo['word'];
                    $transcript[] = intval(rtrim($wordInfo['startTime'],'s'));
                }
            }

            return $transcript;
        }

    }
}
