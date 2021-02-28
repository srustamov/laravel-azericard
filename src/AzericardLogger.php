<?php


namespace Srustamov\Azericard;


use Illuminate\Support\Facades\File;
use Monolog\Handler\StreamHandler;

trait AzericardLogger
{

    protected $logPath;

    /**
     * @param string $message
     * @param array $context
     */
    public function writeDebugLog($message = '', $context = []): void
    {
        $logPath = rtrim($this->logPath, DIRECTORY_SEPARATOR);

        File::ensureDirectoryExists($logPath);

        $orderLog = new \Monolog\Logger('Azericard');

        $orderLog->pushHandler(new StreamHandler($this->logPath . "/" . (now()->format('Y-m-d')) . ".log", Logger::DEBUG));

        $orderLog->debug($message, $context);
    }


    /**
     * @param string $path
     * @return Azericard
     */
    public function setLogPath(string $path): self
    {
        if (!File::exists(File::dirname($path))) {
            File::makeDirectory(File::dirname($path));
        }

        $this->logPath = $path;

        return $this;
    }
}
