<?php

require_once __DIR__.'/../../vendor/autoload.php';

use Xakki\Emailer\ConfigService;
use Xakki\Emailer\Emailer;
use Xakki\Emailer\Exception;
use Xakki\Emailer\Model;
use Xakki\Emailer\Transports;

define('NOTIFY_NEWS', 'Новости');
define('CAMPANY_NEWS', 'Тестирование сервиса');

$projectId = 1;

$config = new ConfigService();
$config->db['host'] = 'emailer-mariadb';
$config->db['pass'] = 'CHENGE_ME';

$logger = new \Xakki\Emailer\test\phpunit\Logger();
$emailer = new Emailer($config, $logger);

$tplDir = __DIR__ . '/../tpl/';
try {
    $emailer->getProject($projectId);
    $campaign = Model\Campaign::findOne(['project_id' => $projectId]);
} catch (exception\DataNotFound $e) {
    $emailer->getDb()->beginTransaction();
    // Add project
    $project = $emailer->createProject('Test project', [
        Model\Template::NAME_HOST => 'localhost',
        Model\Template::NAME_ROUTE => '/my-emailer',
        Model\Template::NAME_TIMEZONE => 'UTC',
        Model\Template::NAME_LANG => 'ru',
        Model\Template::NAME_URL_LOGO => $tplDir . 'img/logo.webp',
        'url_reg' => 'http://localhost',
    ]);

    // Add tpl wrapper
    $tplWrapper = $project->createTplWrapper('Base', file_get_contents($tplDir . 'wrapper1.php'));

    // Add tpl content
    $tplContent = $project->createTplContent('News1', file_get_contents($tplDir . 'content1.php'));

    // Add tpl head
    $tplContent = $project->createTplBlock('head1', file_get_contents($tplDir . 'head1.php'));

    // Add tpl footer
    $tplContent = $project->createTplBlock('footer1', file_get_contents($tplDir . 'footer1.php'));

    $notifyNews = $project->createNotify(NOTIFY_NEWS);

    // Add transport
    $smtp = new Transports\Smtp($emailer);
    // Add notify
    $smtp->fromEmail = 'robot@localhost';
    $smtp->fromName = 'Robot';
    $smtp->dkim = $tplDir . 'dkim.key';
    $transport = $project->createTransport($smtp);

    // Add campaign
    $campaignParams = [
        Model\Template::TYPE_CONTENT => 'Welcome. Some text here!',
    ];
    $campaign = $project->createCampaign(CAMPANY_NEWS, $tplWrapper, $tplContent, $notifyNews, $campaignParams);

    $emailer->getDb()->commit();
}

$mail = $emailer->getNewMail();
$mail->setEmail('test@xakki.ru');
$mail->setEmailName('Test User');
//$mail->setSubject('Test subject');
$mail->setData([
    'HEAD_NAME' => 'Dear Mr. #1 !',
    'project.url' => 'http://localhost',
    'project.name' => 'Localhost test',
    'unsubscribe.url' => 'http://localhost/',
]);
$hash = $emailer
    ->getNewSender($campaign->project_id, $campaign->id)
    ->send($mail);

echo $hash;

