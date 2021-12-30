<?php

declare(strict_types=1);

namespace Xakki\Emailer\Exception;

class Validations extends Validation
{
    public string $title = 'Validations';

    public const CODE_VALIDATIONS = 1;

    public const CODE_MESSAGES = [
        self::CODE_REQUIRE => 'is require',
    ];

    /** @var array<int,mixed> */
    protected array $data = [];

    /**
     * @param array<int,mixed> $data
     * @throws \Exception
     */
    public function __construct(array $data)
    {
        foreach ($data as &$r) {
            if (!is_string($r[0])) {
                throw new \Exception('Wrong validation data format: first value must be string.');
            }
            if (!isset(self::CODE_MESSAGES[$r[1]])) {
                throw new \Exception('Wrong validation data format: second value must be message code.');
            }
            $r[1] = self::CODE_MESSAGES[$r[1]];
        }
        $this->data = $data;
        parent::__construct($this->title, self::CODE_VALIDATIONS);
    }

    /**
     * @return array<int,mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
