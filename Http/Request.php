<?php


namespace Curia\Framework\Http;

use Zend\Diactoros\ServerRequest;
use Tightenco\Collect\Support\Arr;

class Request extends ServerRequest
{
    /**
     * Get request value from specific key.
     * @param $key
     * @param null $default
     * @return null
     */
    public function get($key, $default = null)
    {
        $array = $this->all();
        
        return array_key_exists($key, $array) ? $array[$key] : $default;
    }

    /**
     * Get all request params.
     * @return array
     */
    public function all()
    {
        return array_merge(
            $this->getQueryParams(),
            $this->getParsedBody(),
            $this->getUploadedFiles()
        );
    }

    /**
     * Get request values from specific keys.
     * @param $keys
     * @return array
     */
    public function only($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * Get request values except specific keys.
     * @param $keys
     * @return array
     */
    public function except($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        $array = $this->all();
        
        Arr::forget($array, $keys);

        return $array;
    }

    /**
     * Check if an item or items exist in an array using "dot" notation.
     * @param $keys
     * @return bool
     */
    public function has($keys)
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return Arr::has($this->all(), $keys);
    }
}