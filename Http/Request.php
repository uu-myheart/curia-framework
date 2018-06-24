<?php


namespace Curia\Framework\Http;

use Zend\Diactoros\ServerRequest;
use Tightenco\Collect\Support\Arr;

class Request extends ServerRequest
{
    public function get($key)
    {
        $array = $this->all();
        
        return array_key_exists($key, $array) ? $array[$key] : null;
    }

    public function all()
    {
        return array_merge(
            $this->getQueryParams(),
            $this->getParsedBody(),
            $this->getUploadedFiles()
        );
    }

    public function only($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        $result = array_intersect_key($this->all(), array_flip($keys));

        return $result ?: null;
    }

    public function except($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        $array = $this->all();
        
        Arr::forget($array, $keys);

        return $array;
    }

    public function has($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return Arr::has($this->all(), $keys);
    }
}