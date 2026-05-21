<?php

declare(strict_types=1);

namespace Xakki\Emailer\Tests\Integration;

use Xakki\Emailer\Cqrs;
use Xakki\Emailer\Exception\DataNotFound;
use Xakki\Emailer\Model;
use Xakki\Emailer\Repository;
use Xakki\Emailer\Tests\Support\IntegrationCase;

class RepositoryModelTest extends IntegrationCase
{
    public function testRepositoryCrud(): void
    {
        $id = Repository\Notify::insert(['name' => 'n1', 'project_id' => 1]);
        self::assertSame(1, $id);

        $row = Repository\Notify::findOne(['id' => $id]);
        self::assertSame('n1', $row['name']);
        self::assertSame(1, $row['project_id']);

        self::assertSame($id, Repository\Notify::findId(['name' => 'n1']));
        self::assertSame(0, Repository\Notify::findId(['name' => 'absent']));

        $cnt = Repository\Notify::updateById($id, ['name' => 'n1-upd']);
        self::assertSame(1, $cnt);
        self::assertSame('n1-upd', Repository\Notify::findOne(['id' => $id])['name']);

        Repository\Notify::insert(['name' => 'n2', 'project_id' => 1]);
        $all = iterator_to_array(Repository\Notify::findAll(['project_id' => 1]));
        self::assertCount(2, $all);

        // save() routes to insert (no pk) then update (with pk)
        $newId = Repository\Notify::save(['name' => 'n3', 'project_id' => 2]);
        self::assertGreaterThan(0, $newId);
        Repository\Notify::save(['id' => $newId, 'name' => 'n3-upd', 'project_id' => 2]);
        self::assertSame('n3-upd', Repository\Notify::findOne(['id' => $newId])['name']);

        self::assertSame(1, Repository\Notify::delete(['id' => $id]));
        self::assertSame([], Repository\Notify::findOne(['id' => $id]));
    }

    public function testRepositoryFindOneInClause(): void
    {
        Repository\Notify::insert(['name' => 'a', 'project_id' => 7]);
        Repository\Notify::insert(['name' => 'b', 'project_id' => 7]);
        $row = Repository\Notify::findOne(['project_id' => [7, 8]]);
        self::assertSame(7, $row['project_id']);
    }

    public function testRepositoryIncAndValidateCasts(): void
    {
        $pid = Repository\Project::insert(['name' => 'p', 'token' => 't', 'params' => '{}']);
        $nid = Repository\Notify::insert(['name' => 'nt', 'project_id' => $pid]);
        $wid = Repository\Template::insert(['name' => 'w', 'html' => '', 'type' => 'wrapper', 'project_id' => $pid]);
        $cid = Repository\Template::insert(['name' => 'c', 'html' => '', 'type' => 'content', 'project_id' => $pid]);
        $campId = Repository\Campaign::insert([
            'name' => 'camp', 'notify_id' => $nid, 'tpl_wrapper_id' => $wid,
            'tpl_content_id' => $cid, 'project_id' => $pid,
        ]);

        Repository\Campaign::inc($campId, 'cnt_send', 3);
        $row = Repository\Campaign::findOne(['id' => $campId]);
        // validate() must cast numeric strings from the driver to int.
        self::assertSame(3, $row['cnt_send']);
        self::assertIsInt($row['project_id']);
    }

    public function testModelLifecycle(): void
    {
        $model = new Model\Notify(['name' => 'mdl', 'project_id' => 9]);
        $model->insert();
        self::assertGreaterThan(0, $model->getPk());
        self::assertSame('id', Model\Notify::getPkName());

        $found = Model\Notify::findOneById($model->id);
        self::assertSame('mdl', $found->name);

        $model->name = 'mdl-upd';
        self::assertSame(1, $model->update(['name']));

        $reloaded = Model\Notify::findOne(['id' => $model->id]);
        self::assertSame('mdl-upd', $reloaded->name);

        $reloaded->name = 'stale';
        $reloaded->renew();
        self::assertSame('mdl-upd', $reloaded->name);

        $list = Model\Notify::findAll(['project_id' => 9]);
        self::assertCount(1, $list);
        self::assertInstanceOf(Model\Notify::class, $list[0]);

        $props = $model->getProperties();
        self::assertArrayHasKey('name', $props);
    }

    public function testModelFindOneThrowsWhenMissing(): void
    {
        $this->expectException(DataNotFound::class);
        Model\Notify::findOneById(424242);
    }

    public function testModelFindOrSaveCreatesThenFinds(): void
    {
        $a = Model\Notify::findOrSave(['name' => 'fos', 'project_id' => 3]);
        self::assertGreaterThan(0, $a->id);
        $b = Model\Notify::findOrSave(['name' => 'fos', 'project_id' => 3], ['name' => 'fos']);
        self::assertSame($a->id, $b->id);
    }

    public function testCreateProjectViaCqrsAndRelations(): void
    {
        $project = (new Cqrs\Project\CreateProject('Demo', [
            Model\Template::NAME_HOST => 'demo.test',
            Model\Template::NAME_ROUTE => 'rdr',
            Model\Template::NAME_URL_LOGO => __DIR__ . '/../logo.png',
        ]))->handler();

        self::assertGreaterThan(0, $project->id);
        self::assertNotEmpty($project->token);

        $params = $project->getParams();
        self::assertSame('Demo', $params[Model\Template::NAME_PROJECT]);
        self::assertSame('demo.test', $project->getParam(Model\Template::NAME_HOST));
        self::assertNull($project->getParam('absent.key'));

        $cached = (new Cqrs\Project\GetProject($project->id))->handler();
        self::assertSame($project->id, $cached->id);
        // second call hits the static cache branch
        self::assertSame($cached, (new Cqrs\Project\GetProject($project->id))->handler());

        $notify = $project->createNotify('News');
        self::assertGreaterThan(0, $notify->id);

        $wrapper = $project->createTplWrapper('wrap', '<html>{{content}}</html>');
        $content = $project->createTplContent('cont', 'Hello {{name}}');
        $project->createTplBlock('foot', 'footer');

        $campaign = $project->createCampaign('Subject {{name}}', $wrapper, $content, $notify, []);
        self::assertGreaterThan(0, $campaign->id);

        $sameCampaign = $project->getCampaign($campaign->id);
        self::assertSame($campaign->id, $sameCampaign->id);
        self::assertSame('News', $sameCampaign->getNotify()->name);
        self::assertSame($wrapper->id, $sameCampaign->getTplWrapper()->id);
        self::assertSame($content->id, $sameCampaign->getTplContent()->id);
        self::assertContains('name', $sameCampaign->getRequiredParams());

        $blocks = (new Cqrs\Template\GetTemplateNames($project->id, Model\Template::TYPE_BLOCK))->handler();
        self::assertContains('foot', $blocks);

        $campaign->incCntQueue();
        $campaign->incCntSend();
        $reloaded = Model\Campaign::findOneById($campaign->id);
        self::assertSame(1, $reloaded->cnt_queue);
        self::assertSame(1, $reloaded->cnt_send);

        $cparams = $reloaded->getParams();
        self::assertSame('News', $cparams[Model\Template::NAME_NOTIFY]);
    }

    public function testSubscribeCqrs(): void
    {
        $queue = new Model\Queue(['project_id' => 1, 'notify_id' => 1, 'email_id' => 1]);

        // No subscribe row yet -> active by default.
        self::assertTrue((new Cqrs\Subscribe\IsActiveSubscribe($queue))->handler());

        $off = (new Cqrs\Subscribe\UpdateSubscribe($queue, Model\Subscribe::STATUS_OFF))->handler();
        self::assertSame(Model\Subscribe::STATUS_OFF, $off->status);
        self::assertFalse((new Cqrs\Subscribe\IsActiveSubscribe($queue))->handler());

        $on = (new Cqrs\Subscribe\UpdateSubscribe($queue, Model\Subscribe::STATUS_ON, 1200))->handler();
        self::assertSame(Model\Subscribe::STATUS_ON, $on->status);
        self::assertSame(1200, $on->period);
        self::assertTrue((new Cqrs\Subscribe\IsActiveSubscribe($queue))->handler());
    }

    public function testIsActiveSubscribeRejectsIncompleteQueue(): void
    {
        $this->expectException(\Xakki\Emailer\Exception\Validation::class);
        new Cqrs\Subscribe\IsActiveSubscribe(new Model\Queue(['project_id' => 1]));
    }
}
