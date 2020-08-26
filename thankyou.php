<?php
(new LeadrockIntegrationDownloader())->checkForInstall();



//-------------------------------------------------------------------------------------------
// Integration settings / Настройки интеграции
//-------------------------------------------------------------------------------------------


if (file_exists(dirname(__FILE__) . '/leadrock-integration')) {
    require_once dirname(__FILE__) . '/leadrock-integration/vendor/autoload.php';
} else {
    require_once 'phar://' . dirname(__FILE__) . '/leadrock-integration.phar/vendor/autoload.php';
}
$integration = new \Leadrock\Layouts\Landing();
include 'confirm.html';
$integration->end();


//-------------------------------------------------------------------------------------------
// Integratino self install & update / Автоустановка и обновление интеграционного пакета
//-------------------------------------------------------------------------------------------


class LeadrockIntegrationDownloader
{
    private $isLogEnabled = false;
    const DOWNLOAD_ATTEMPTS = 5;
    const FILE_HASH_HEADER_NAME = 'File-Hash:';

    public function checkForInstall()
    {
        $this->isLogEnabled = isset($_GET['update_integration_package']);
        if (!file_exists(dirname(__FILE__) . '/leadrock-integration.phar') || isset($_GET['update_integration_package'])) {
            $this->doMagic();
        }
    }

    public function doMagic()
    {
        $this->log('Start downloading file', true);

        if ($this->downloadFile($this->getPackageUrl(), $this->getPackageSavePath())) {
            if ($this->checkIncludePhar()) {
                $this->log('Ready to work. Open your landing now to check the result.');
            } else {
                try {
                    $this->log('Unable to include PHAR package. Trying to unpack it.');
                    $this->unpackPhar();

                    $this->log('Ready to work. Open your landing now to check the result.');
                } catch (Exception $e) {
                    $this->log($this->getErrorText());
                }
            }
        } else {
            $this->log($this->getErrorText());
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        if ($this->isLogEnabled) {
            die();
        }
    }

    private function getErrorText()
    {
        return [
            'Error',
            'Check write access to current directory: script is trying to create file leadrock-integration.phar or directory leadrock-integration.',
            'If there will be no success, try manual download: <a href="' . $this->getPackageUrl() . '">' . $this->getPackageUrl() . '</a>. Place this file to current folder and open your landing page.',
            'Or you can visit source code page and see expanded documentation: <a href="https://bitbucket.org/leadrockmain/leadrock-integration/releases">https://bitbucket.org/leadrockmain/leadrock-integration/releases</a>',
        ];
    }

    private function downloadFile($url, $pathToSave)
    {
        do {
            static $downloadCounter;
            $downloadCounter++;
            $hash = null;
            @unlink($pathToSave);
            if ($stream = fopen($url, 'r')) {
                $headers = $http_response_header;
                if (!is_array($headers)) {
                    continue;
                }
                foreach ($headers as $header) {
                    if ($this->checkIfFileHashHeader($header)) {
                        $hash = trim(substr($header, strlen(self::FILE_HASH_HEADER_NAME)));
                    }
                }
                if (empty($hash)) {
                    fclose($stream);
                    continue;
                }
                @file_put_contents($pathToSave, $stream);
                fclose($stream);
            }
        }
        while (file_exists($pathToSave) && md5_file($pathToSave) !== $hash && $downloadCounter < self::DOWNLOAD_ATTEMPTS);
        $downloadCounter = 0;
        try {
            return filesize($pathToSave) > 0;
        } catch (Exception $exception) {
            $this->log($exception->getMessage());
            return false;
        }
    }

    private function checkIfFileHashHeader($header)
    {
        return preg_match('/' . self::FILE_HASH_HEADER_NAME . '/', $header);
    }

    private function checkIncludePhar()
    {
        @include_once 'phar://' . dirname(__FILE__) . '/leadrock-integration.phar/vendor/autoload.php';
        return class_exists('\Leadrock\Layouts\Landing');
    }

    private function unpackPhar()
    {
        $phar = new Phar($this->getPackageSavePath());
        $phar->extractTo($this->getPackageSavePath(false), null, true);
        unset($phar);
    }

    private function log($texts, $extraSeparator = false)
    {
        if ($this->isLogEnabled) {
            if (!is_array($texts)) {
                $texts = [$texts];
            }
            foreach ($texts as $row) {
                echo $row, "<br>\r\n";
            }
            echo($extraSeparator ? "<br>\r\n" : '');
        }
    }

    private function getPackageSavePath($isPhar = true)
    {
        return dirname(__FILE__) . '/leadrock-integration' . ($isPhar ? '.phar' : '');
    }

    private function getPackageUrl()
    {
        return 'https://leadrock.com/integration/download?url=' . $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
}