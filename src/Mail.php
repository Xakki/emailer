<?php

declare(strict_types=1);

namespace Xakki\Emailer;

use Xakki\Emailer\Exception\Validation;
use Xakki\Emailer\Model\Template;

class Mail implements \JsonSerializable
{
    /** @var array<string,mixed> */
    protected array $data = [];

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    public static function initFromJson(string $json): self
    {
        $json = json_decode($json, true);
        $mail = new self();
        return $mail->setData($json);
    }

    public function setEmail(string $email): self
    {
        $this->data['email'] = $this->validateEmail($email);
        return $this;
    }

    public function setEmailName(string $name): self
    {
        $this->data['emailName'] = $this->validateEmailName($name);
        return $this;
    }

    public function setSubject(string $subject): self
    {
        $subject = preg_replace("/[\<\>\/\\\]+/ui", '', $subject);
        if (mb_strlen($subject) < 10) {
            throw new Validation('Subject: too small. Min 10 chars.', Validation::CODE_WRONG_VALUE);
        }
        if (mb_strlen($subject) > 255) {
            throw new Validation('Subject: too long. Max 255 chars.', Validation::CODE_WRONG_VALUE);
        }
        $this->data['subject'] = $subject;
        return $this;
    }

    /**
     * @param array<string,string> $reply
     * @return $this
     * @throws Validation
     */
    public function setReplyTo(array $reply): self
    {
        $replyTo = [];
        foreach ($reply as $email => $name) {
            // @phpstan-ignore-next-line
            if (!is_string($email) || !is_string($name)) {
                throw new Validation('ReplyTo: allow array with string key and value.', Validation::CODE_WRONG_VALUE);
            }
            $email = $this->validateEmail($email);
            $name = $this->validateEmailName($name);
            $replyTo[$email] = !empty($name) ? $name : $email;
        }
        $this->data['replyTo'] = $replyTo;
        return $this;
    }

    public function setDescr(string $descr): self
    {
        $descr = preg_replace("/[\<\>\/\\\]+/ui", '', $descr);
        if (mb_strlen($descr) > 1024) {
            throw new Validation('Descr: too long. Max 1024 chars.', Validation::CODE_WRONG_VALUE);
        }
        $this->data['descr'] = $descr;
        return $this;
    }

    public function setBody(string $val): self
    {
        $this->data['body'] = $val;
        return $this;
    }

    public function setLocale(string $val): self
    {
        $this->data['locale'] = $val;
        return $this;
    }

    /**
     * @param array<string,mixed> $data
     * @return $this
     * @throws Validation
     */
    public function setData(array $data): static
    {
        if (count($data) > 100) {
            throw new Validation('Data: Too much count', Validation::CODE_WRONG_VALUE);
        }

        foreach ($data as $k => $r) {
            if (!is_string($k)) {
                throw new Validation('Data: allow only `string` key', Validation::CODE_WRONG_VALUE);
            }
            $call = [$this, 'set' . ucfirst($k)];
            if (method_exists($call[0], $call[1])) {
                if (!empty($this->data[$k])) {
                    throw new Validation(sprintf('Data: try replace exist value by `%s`', $k), Validation::CODE_WRONG_VALUE);
                }
                // @phpstan-ignore-next-line
                call_user_func($call, $r);
            } else {
                if (!is_string($r) && !is_int($r)) {
                    throw new Validation(sprintf('Data: allow only `string` and `int` item. Wrong key `%s`', $k), Validation::CODE_WRONG_VALUE);
                }
                $this->data[$k] = $r;
            }
        }
        return $this;
    }

    public function getEmail(): string
    {
        return (string) ($this->data['email'] ?? '');
    }

    public function getEmailName(): string
    {
        return (string) ($this->data['emailName'] ?? '');
    }

    public function getSubject(): string
    {
        return (string) ($this->data['subject'] ?? '');
    }

    /**
     * @return array<string,string>
     */
    public function getReplyTo(): array
    {
        return $this->data['replyTo'] ?? [];
    }

    public function getDescr(): string
    {
        return (string) ($this->data['descr'] ?? '');
    }

    public function getBody(): string
    {
        return (string) ($this->data['body'] ?? '');
    }

    public function getLocale(): string
    {
        return (string) ($this->data['locale'] ?? Template::LOCALE_DEFAULT);
    }

    /**
     * @return array<string,mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param string[] $requiredParams
     * @return void
     * @throws Validation
     */
    public function validate(array $requiredParams): void
    {
        if (empty($this->getEmail())) {
            throw new Validation('Email is required', Validation::CODE_REQUIRE);
        }

        $diff = array_diff($requiredParams, array_keys($this->getData()));
        if ($diff) {
            throw new Validation(sprintf('Data: miss replacers `%s`', implode(', ', $diff)), Validation::CODE_DATA_MISS);
        }
    }

    public function validateEmail(string $email): string
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        if ($email) {
            $email = filter_var(strtolower($email), FILTER_VALIDATE_EMAIL);
        }
        if (!$email) {
            throw new Validation('Invalid Email', Validation::CODE_EMAIL_BAD);
        }
        return $email;
    }

    public function validateEmailName(string $name): string
    {
        $name = preg_replace("/[^\d\w\s\-\_\.]+/ui", '', $name);
        if (mb_strlen($name) > 255) {
            throw new Validation('EmailName too long. Max 255 chars.', Validation::CODE_WRONG_VALUE);
        }
        return $name;
    }
}
