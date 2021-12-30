<?php

declare(strict_types=1);

namespace Xakki\Emailer\Controller\Api;

use Xakki\Emailer\Cqrs\Auth\GetAuthToken;

class Panel extends AbstractApi
{
    /**
     * @OA\Schema(
     *     schema="AuthSuccess",
     *     @OA\Property( property="success", type="bool", default=true),
     *     @OA\Property( property="data", type="object", example={
     *        "lifetime": "2022-05-09T02:42:12+03:00",
     *        "xToken": "ef88f02fc1ef792f4f4c2105533bc0a0",
     *        "hasOldAuth": false
     *     }),
     * )
     * @OA\Post(
     *     path="/panel/login",
     *     summary="Panel authorization",
     *     tags={"Admin Panel"},
     *     @OA\RequestBody( description="Post data", required=true, @OA\JsonContent(type="object",
     *         @OA\Property(type="string", property="login", description="Admin login"),
     *         @OA\Property(type="string", property="pass", description="Admin password"),
     *     )),
     *     @OA\Response( response=200, description="OK", @OA\JsonContent(ref="#/components/schemas/AuthSuccess")),
     *     @OA\Response( response=401, description="OK", @OA\JsonContent(example={
     *          "info": "API version: v1","success": false,"data": {},
     *          "message": "Auth failed: Wrong pass or login."})),
     *     @OA\Response( response=450, description="Error", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
     * )
     */
    /**
     * @return array<string,string>
     * @throws \Xakki\Emailer\Exception\AccessFail
     * @throws \Xakki\Emailer\Exception\Exception
     * @throws \Xakki\Emailer\Exception\Validations
     */
    public function actionLogin(): array
    {
        return (new GetAuthToken($this->getPost()))->handler();
    }

    /**
     * @OA\Schema(
     *     schema="HeadSuccess",
     *     @OA\Property( property="success", type="bool", default=true),
     *     @OA\Property( property="data", type="array", @OA\Items(ref="#/components/schemas/HeadData"))
     * )
     * @OA\Schema(
     *     schema="HeadData",
     *     @OA\Property( property="menu", type="array", description="Menu", @OA\Items(type="string")),
     *     @OA\Property( property="user", type="array", description="User info", @OA\Items(ref="#/components/schemas/User"))
     * )
     * @OA\Schema(
     *     schema="User",
     *     @OA\Property( property="name", type="string"),
     *     @OA\Property( property="role", type="string")
     * )
     * @OA\Get(
     *     path="/panel/head",
     *     summary="Panel head: menu, info, etc",
     *     tags={"Admin Panel"},
     *     @OA\Parameter( name="x-token", in="header", description="ApiToken", required=true, @OA\Schema( type="string" )),
     *     @OA\Response( response=200, description="OK", @OA\JsonContent(ref="#/components/schemas/HeadSuccess"))
     * )
     */
    /**
     * @return array<mixed>
     */
    public function actionHead(): array
    {
        return [];
    }

    /**
     * @OA\Get(
     *     path="/panel/dashboard",
     *     summary="Index dashboard page",
     *     tags={"Admin Panel"},
     *     @OA\Parameter( name="x-token", in="header", description="ApiToken", required=true, @OA\Schema( type="string" )),
     *     @OA\Response( response=200, description="OK", @OA\JsonContent(ref="#/components/schemas/Success"))
     * )
     */
    /**
     * @return array<mixed>
     */
    public function actionDashboard(): array
    {
        return [];
    }
}
