<?php

namespace Typecho;

class Validate
{
    private array $data;
    private string $key;
    private array $rules = [];
    private bool $break = false;

    public static function minLength(string $str, int $length): bool
    {
        return (Common::strLen($str) >= $length);
    }

    public static function enum(string $str, array $params): bool
    {
        $keys = array_flip($params);
        return isset($keys[$str]);
    }

    public static function maxLength(string $str, int $length): bool
    {
        return (Common::strLen($str) < $length);
    }

    public static function email(string $str): bool
    {
        $email = filter_var($str, FILTER_SANITIZE_EMAIL);
        return !!filter_var($str, FILTER_VALIDATE_EMAIL) && ($email === $str);
    }

    public static function url(string $str): bool
    {
        $url = Common::safeUrl($str);
        return !!filter_var($str, FILTER_VALIDATE_URL) && ($url === $str);
    }

    public static function alpha(string $str): bool
    {
        return ctype_alpha($str);
    }

    public static function alphaNumeric(string $str): bool
    {
        return ctype_alnum($str);
    }

    public static function alphaDash(string $str): bool
    {
        return !!preg_match("/^([_a-z0-9-])+$/i", $str);
    }

    public static function xssCheck(string $str): bool
    {
        $str = Common::cleanHex($str);
        return !preg_match('/(\(|\)|\\\|"|<|>|[\x00-\x08]|[\x0b-\x0c]|[\x0e-\x19]|' . "\r|\n|\t" . ')/', $str);
    }

    public static function isInteger($str): bool
    {
        return filter_var($str, FILTER_VALIDATE_INT) !== false;
    }

    public static function regexp(string $str, string $pattern): bool
    {
        return preg_match($pattern, $str) === 1;
    }

    public function addRule(string $key, $rule, string $message): Validate
    {
        if (func_num_args() <= 3) {
            $this->rules[$key][] = [$rule, $message];
        } else {
            $params = func_get_args();
            $params = array_splice($params, 3);
            $this->rules[$key][] = array_merge([$rule, $message], $params);
        }

        return $this;
    }

    public function setBreak()
    {
        $this->break = true;
    }

    public function run(array $data, ?array $rules = null): array
    {
        $result = [];
        $this->data = $data;
        $rules = empty($rules) ? $this->rules : $rules;

        foreach ($rules as $key => $rule) {
            $this->key = $key;
            $data[$key] = (is_array($data[$key]) ? 0 == count($data[$key])
                : 0 == strlen($data[$key] ?? '')) ? null : $data[$key];

            foreach ($rule as $params) {
                $method = $params[0];

                if ('required' != $method && 'confirm' != $method && 0 == strlen($data[$key] ?? '')) {
                    continue;
                }

                $message = $params[1];
                $params[1] = $data[$key];
                $params = array_slice($params, 1);

                if (!call_user_func_array(is_callable($method) ? $method : [$this, $method], $params)) {
                    $result[$key] = $message;
                    break;
                }
            }

            if ($this->break && $result) {
                break;
            }
        }

        return $result;
    }

    public function confirm(?string $str, string $key): bool
    {
        return !empty($this->data[$key]) ? ($str == $this->data[$key]) : empty($str);
    }

    public function required(): bool
    {
        return array_key_exists($this->key, $this->data) &&
            (is_array($this->data[$this->key]) ? 0 < count($this->data[$this->key])
                : 0 < strlen($this->data[$this->key] ?? ''));
    }
}
