<?php

declare(strict_types=1);

namespace Xakki\Emailer\Controller;

/**
 * @method logs()
 */
class Panel extends AbstractController
{
    /**
     * @param string $name
     * @param array<string,mixed> $arguments
     * @return string
     */
    protected function run(string $name, array $arguments): string
    {
        $this->headerSend('Content-Type: text/html; charset=UTF-8');
        return parent::run($name, $arguments);
    }

    protected function actionLogs(): string
    {
        $fp = fopen('/var/log/app.log', 'r');
        $max = 50;
        $xPos = 0;
        while ($max > 0) {
            $max--;
            $line = '';
            while (fseek($fp, $xPos, SEEK_END) !== -1) {
                $xPos--;
                $c = fgetc($fp);
                if ($c === "\r") {
                    continue;
                }
                if ($c === "\n") {
                    break;
                }
                if ($c) {
                    $line = $c . $line;
                }
            }
            if ($line) {
                echo '<pre>';
                print_r(json_decode($line, true));
                echo '</pre><hr/>';
            }
        }
        fclose($fp);
        return '';
//        exit('');
//        return $this->renderView('queueStatus.html', ['queueStatus' => $queue::TITLE_QUEUE_STATUS[$queue->status]]);
    }
}
