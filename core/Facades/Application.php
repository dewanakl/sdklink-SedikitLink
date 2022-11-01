<?php

namespace Core\Facades;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Aplikasi untuk menampung kumpulan objek yang bisa digunakan kembali serta
 * inject sebuah object kedalam fungsi
 *
 * @class Application
 * @package Core\Facades
 */
class Application
{
    /**
     * Kumpulan objek ada disini gaes
     * 
     * @var array $objectPool
     */
    private $objectPool;

    /**
     * Buat objek application
     *
     * @return void
     */
    function __construct()
    {
        if (is_null($this->objectPool)) {
            $this->objectPool = [];
        }
    }

    /**
     * Inject pada constructor yang akan di buat objek
     *
     * @param string $name
     * @param array $param
     * @return object
     */
    private function getConstructor(string $name, array $param = []): object
    {
        $reflector = new ReflectionClass($name);

        $constructor = $reflector->getConstructor();
        $args = is_null($constructor) ? null : $constructor->getParameters();

        return $reflector->newInstanceArgs($this->getDependencies($args, $param));
    }

    /**
     * Cek apa aja yang dibutuhkan untuk injek objek atau parameter
     *
     * @param ?array $parameters
     * @param array $value
     * @return array
     */
    private function getDependencies(?array $parameters = null, array $value = []): array
    {
        $args = [];
        $paramid = 0;

        if (!$parameters) {
            return $args;
        }

        foreach ($parameters as $parameter) {
            if ($parameter->getType() && !$parameter->getType()->isBuiltin()) {
                $args[] = $this->singleton($parameter->getType()->getName());
            } else {
                $args[] = $value[$paramid] ?? $parameter->getDefaultValue();
                $paramid++;
            }
        }

        return $args;
    }

    /**
     * Bikin objek dari sebuah class lalu menyimpannya
     *
     * @param string $name
     * @param array $param
     * @return object
     */
    public function singleton(string $name, array $param = []): object
    {
        if (empty($this->objectPool[$name])) {
            $this->objectPool[$name] = $this->getConstructor($name, $param);
        }

        return $this->objectPool[$name];
    }

    /**
     * Bikin objek dari sebuah class lalu gantikan dengan yang lama
     *
     * @param string $name
     * @param array $param
     * @return object
     */
    public function make(string $name, array $param = []): object
    {
        $this->objectPool[$name] = $this->getConstructor($name, $param);

        return $this->objectPool[$name];
    }

    /**
     * Inject objek pada suatu fungsi yang akan di eksekusi
     *
     * @param string $name
     * @param string $method
     * @param array $value
     * @return mixed
     * 
     * @throws Exception
     */
    public function invoke(string $name, string $method, array $value = []): mixed
    {
        $name = $this->singleton($name);

        $reflector = new ReflectionClass($name);
        $params = $this->getDependencies($reflector->getMethod($method)->getParameters(), $value);

        try {
            $reflectionMethod = new ReflectionMethod($name, $method);
            return $reflectionMethod->invokeArgs($name, $params);
        } catch (ReflectionException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Hapus dan dapatkan object itu terlebih dahulu
     * 
     * @param string $name
     * @return mixed
     */
    public function clean(string $name): mixed
    {
        $object = $this->objectPool[$name] ?? null;
        unset($this->objectPool[$name]);
        return $object;
    }

    /**
     * Inject objek pada suatu closure fungsi
     *
     * @param Closure $name
     * @return mixed
     * 
     * @throws Exception
     */
    public function resolve(Closure $name): mixed
    {
        try {
            $reflector = new ReflectionFunction($name);
            return $reflector->invokeArgs($this->getDependencies($reflector->getParameters(), array($this)));
        } catch (ReflectionException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Binding interface dengan class object
     *
     * @param string $interface
     * @param Closure|string $class
     * @param array $param
     * @return void
     */
    public function bind(string $interface, Closure|string $class, array $param = []): void
    {
        $object = ($class instanceof Closure) ? $this->resolve($class) : $this->getConstructor($class, $param);

        if (empty($this->objectPool[$interface])) {
            $this->objectPool[$interface] = $object;
        }
    }
}
