<?php

namespace Techart;
use Techart\Core;
use Techart\Core\Service;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;

class DB extends Service
{
    protected $capsule;
    protected $connectionsForTables = array();

    public function init()
    {
        $this->capsule = new Capsule();

        $config = Core::config()->loadScope($this->getOption('config', 'db'));

        foreach($config as $key => $value) {

            $key = $key == 'connection'? 'connection.default' : $key;

            if ($m = Core::regexp('{^connection\.(.+)$}', $key)) {
                $name = trim($m[1]);
                $data = $this->parseConnectionConfig($value);
                foreach($data['tables'] as $table) {
                    $this->connectionsForTables[$table] = $name;
                }
                unset($data['tables']);
                $this->capsule->addConnection($data, $name);
            }
        }
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
        //$this->capsule->setEventDispatcher(new Dispatcher(new Container));
    }

    public function parseConnectionConfig($in)
    {
        $driver = 'none';
        $username = 'l';
        $password = 'p';
        $host = 'localhost';
        $database = false;
        $tables = array();

        if ($m = Core::regexp('{^([a-z]+)://(.+)$}', $in)) {
            $driver = $m[1];
            $in = trim($m[2]);
        }

        if ($m = Core::regexp('{(.+)/([a-z0-9_:,]+)$}i', $in)) {
            $in = trim($m[1]);
            $database = trim($m[2]);
            if ($m = Core::regexp('{([a-z0-9_]+):(.+)$}i', $database)) {
                $database = trim($m[1]);
                $tables = explode(',',trim($m[2]));
            }
        }

        if ($m = Core::regexp('{(.+)@(.+)$}i', $in)) {
            $in = trim($m[1]);
            $host = trim($m[2]);
        }

        list($username, $password) = explode(':', $in);

        return array(
            'driver'    => $driver,
            'host'      => $host,
            'database'  => $database,
            'username'  => $username,
            'password'  => $password,
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'tables' => $tables,
        );
    }

    public function connection($name = null)
    {
        return $this->capsule->getConnection($name);
    }

    public function connectionNameFor($table)
    {
        return isset($this->connectionsForTables[$table])? $this->connectionsForTables[$table] : 'default';
    }

    public function connectionFor($table)
    {
        return $this->capsule->getConnection($this->connectionNameFor($table));
    }

    public function schema($connection = null)
    {
        return Capsule::schema($connection);
    }

    public function table($table)
    {
        $connection = $this->connectionNameFor($table);
        return Capsule::table($table, $connection);
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array(array($this->connection(), $name), $arguments);
    }

}
