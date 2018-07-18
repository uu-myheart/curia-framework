<?php

namespace Curia\Framework;

use SplQueue;
use Exception;

class Baton
{
    /**
     * 容器实例
     */
    protected $container;

    /**
     * 储存中间件队列
     *
     * @var SplQueue
     */
    protected $queue;

    /**
     * 中间件往下执行时候的方法名
     * @var string
     */
    protected $method = 'process';

    /**
     * 穿过中间件的目标
     *
     * @var mixed
     */
    protected $passable;

    /**
     * Baton constructor.
     *
     * @param $container null|any dependency-injection container.
     */
    public function __construct($container = null)
    {
        $this->container = $container;
    }

    /**
     * 指定发送到中间件的目标
     *
     * @param $passable
     * @return $this
     */
    public function send($passable)
    {
        $this->passable = $passable;

        return $this;
    }

    /**
     * 过滤目标的对象，类名或者任意Callable
     *
     * @param iterable $strainers
     * @return $this
     * @throws Exception
     */
    public function through(iterable $strainers)
    {
        $queue = new SplQueue;

        foreach ($strainers as $strainer) {
            if (is_string($strainer)) {
                $strainer = $this->container->get($strainer);
            }

            $queue->enqueue($strainer);
        }

        $this->queue = $queue;

        return $this;
    }

    /**
     * 将目标传入中间件执行
     *
     * @param $request
     * @return mixed
     */
    protected function handle($request)
    {
        $strainer = $this->queue->dequeue();

        if (method_exists($strainer, $this->method)) {
            return $strainer->{$this->method}($request, $this);
        }

        return $strainer($request, $this);
    }

    /**
     * 设置中间件往下执行的方法名
     *
     * @param $method
     * @return $this
     */
    public function via($method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * 执行到中间件最后时的callback
     *
     * @param callable $destination
     * @return mixed
     */
    public function then(Callable $destination)
    {
        $this->queue->enqueue($destination);

        return $this->handle($this->passable);
    }

    /**
     * Baton实例直接当做函数调用
     *
     * @param $request
     * @return mixed
     */
    public function __invoke($request)
    {
        return $this->handle($request);
    }
}
