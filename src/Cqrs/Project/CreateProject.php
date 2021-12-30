<?php

declare(strict_types=1);

namespace Xakki\Emailer\Cqrs\Project;

use Xakki\Emailer\Exception;
use Xakki\Emailer\Helper\Tools;
use Xakki\Emailer\Model\Project;
use Xakki\Emailer\Model\Template;
use Xakki\Emailer\Repository;

class CreateProject
{
    protected string $name;
    /** @var array<string,string>  */
    protected array $params;

    /**
     * @param string $name
     * @param array<string,string> $params
     * @throws Exception\Validation
     */
    public function __construct(string $name, array $params)
    {
        static::validateProjectParams($params);
        $this->name = $name;
        $this->params = $params;
    }

    /**
     * @param array<string,string> $params
     * @return void
     * @throws Exception\Validation
     */
    protected static function validateProjectParams(array &$params): void
    {
        if (empty($params[Template::NAME_HOST])) {
            throw new Exception\Validation('Require params NAME_HOST', Exception\Validation::CODE_REQUIRE);
        }
        if (empty($params[Template::NAME_ROUTE])) {
            throw new Exception\Validation('Require params NAME_ROUTE', Exception\Validation::CODE_REQUIRE);
        }
        if (empty($params[Template::NAME_URL_LOGO])) {
            throw new Exception\Validation('Require params NAME_LOGO - its png/jpg/gif file', Exception\Validation::CODE_REQUIRE);
        }

        if (str_contains($params[Template::NAME_URL_LOGO], DIRECTORY_SEPARATOR)) {
            if (!file_exists($params[Template::NAME_URL_LOGO])) {
                throw new Exception\Validation('File project.logo - not exist', Exception\Validation::CODE_REQUIRE);
            } else {
                $params[Template::NAME_URL_LOGO] = Tools::getBase64File($params[Template::NAME_URL_LOGO]);
            }
        }
    }

    public function handler(): Project
    {
        $project = new Project();
        $project->name = $this->name;
        $project->params = json_encode($this->params);
        $project->token = md5(time() . $project->name . rand(0, 1000) . $project->params . rand(0, 1000));
        $project->id = Repository\Project::save($project->getProperties());
        if (!$project->id) {
            throw new Exception\Exception('Can`t create project `' . $this->name . '`');
        }
        return $project;
    }
}
