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
                'progress' => 10,
            ]);

            $videoFile = $this->downloadVideo($video);
            if (!$videoFile) {
                throw new Exception("Can't download video file");
            }
            $this->videos->update($video->id, [
                'progress' => 20,
            ]);

            $mp4File = $this->convertVideoToMp4($videoFile);
            if (!$mp4File) {
                throw new Exception("Can't convert video file to MP4");
            }
            $this->videos->update($video->id, [
                'progress' => 40,
            ]);

            $flacFile = $this->convertMp4ToFlac($mp4File);
            if (!$flacFile) {
                throw new Exception("Can't convert video file to audio file");
            }
            $this->videos->update($video->id, [
                'progress' => 50,
            ]);

            $flacObject = $this->uploadFlacToCloud($flacFile);
            $this->videos->update($video->id, [
                'progress' => 70,
            ]);

            $transcript = $this->getTranscript($flacObject);

            $this->videos->update($video->id, [
                'transcript' => json_encode($transcript),
                'status' => 'done',
                'progress' => 100,
            ]);

        } catch (Exception $e) {
            $this->videos->update($video->id, [
                'status' => 'failed',
            ]);

            print ($e->getMessage());

            if (isset($videoFile) && file_exists($videoFile)){
                unlink($videoFile);
            }

            if (isset($mp4File) && file_exists($mp4File)){
                unlink($mp4File);
            }

            if (isset($flacFile) && file_exists($flacFile)){
                unlink($flacFile);
            }
        }
    }

    private function downloadVideo($video)
    {
        $videoFile = FCPATH . 'storage/' .  $video->id;
        if (!file_put_contents($videoFile, fopen($video->url, 'r'))) {
            return false;
        }

        return $videoFile;
    }

    private function convertVideoToMp4($videoFile) {
        $mp4File = $videoFile . '.mp4';

        $ffmpegCommand = "ffmpeg -y -i $videoFile $mp4File";
        system($ffmpegCommand);

        if (!file_exists($mp4File)) {
            return false;
        }

        unlink($videoFile);

        return $mp4File;
    }

    private function convertMp4ToFlac($mp4File)
    {
        $flacFile = $mp4File . '.flac';

        $ffmpegCommand = "ffmpeg -y -i $mp4File -ac 1 -ar 16000 $flacFile";
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
                    $transcript[] = [
                        'w' => $wordInfo['word'],
                        't' => intval(rtrim($wordInfo['startTime'],'s')),
                    ];
                }
            }

            return $transcript;
        }

    }
}
