<?php

declare(strict_types=1);

namespace Xakki\Emailer\Controller\Api;

class Smtp extends AbstractApi
{
    /**
     * @OA\Get(
     *     path="/smtp/test",
     *     summary="Test",
     *     tags={"Smtp"},
     *     @OA\Parameter( name="x-token", in="header", description="ApiToken", required=true, @OA\Schema( type="string" )),
     *     @OA\Response( response=200, description="OK", @OA\JsonContent(ref="#/components/schemas/Success"))
     * )
     */
    /**
     * @return array<mixed>
     */
    public function actionTest(): array
    {
        return [];
    }
}
