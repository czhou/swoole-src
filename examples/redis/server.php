<?php
use Swoole\Redis\Server;

define('DB_FILE', __DIR__.'/db.php');

$server = new Server("127.0.0.1", 9501, SWOOLE_BASE);

if (is_file(DB_FILE))
{
    $server->data = unserialize(file_get_contents(DB_FILE));
}
else
{
    $server->data = array();
}

$server->setHandler('GET', function ($data) use ($server) {
    if (count($data) == 0)
    {
        return $server->format(Server::ERROR, "ERR wrong number of arguments for 'GET' command");
    }
        
    $key = $data[0];
    if (empty($server->data[$key]))
    {
        return $server->format(Server::NIL);
    }
    else
    {
        return $server->format(Server::STRING, $server->data[$key]);
    }
});

$server->setHandler('SET', function ($data) use ($server) {
    if (count($data) < 2)
    {
        return $server->format(Server::ERROR, "ERR wrong number of arguments for 'SET' command");
    }

    $key = $data[0];
    $server->data[$key] = $data[1];
    return $server->format(Server::STATUS, 'OK');
});

$server->setHandler('sAdd', function ($data) use ($server) {

    if (count($data) < 2)
    {
        return $server->format(Server::ERROR, "ERR wrong number of arguments for 'sAdd' command");
    }

    $key = $data[0];
    if (!isset($server->data[$key]))
    {
        $array[$key] = array();
    }

    $count = 0;
    for($i = 1; $i < count($data); $i++)
    {
        $value = $data[$i];
        if (!isset($server->data[$key][$value]))
        {
            $server->data[$key][$value] = 1;
            $count ++;
        }
    }

    return $server->format(Server::INT, $count);
});

$server->setHandler('sMembers', function ($data) use ($server) {
    if (count($data) < 1)
    {
        return $server->format(Server::ERROR, "ERR wrong number of arguments for 'sMembers' command");
    }
    $key = $data[0];
    if (!isset($server->data[$key]))
    {
        return $server->format(Server::NIL);
    }
    return $server->format(Server::SET, array_keys($server->data[$key]));
});

$server->setHandler('hSet', function ($data) use ($server) {

    if (count($data) < 3)
    {
        return $server->format(Server::ERROR, "ERR wrong number of arguments for 'hSet' command");
    }

    $key = $data[0];
    if (!isset($server->data[$key]))
    {
        $array[$key] = array();
    }
    $field = $data[1];
    $value = $data[2];
    $count = !isset($server->data[$key][$field]) ? 1 : 0;
    $server->data[$key][$field] = $value;
    return $server->format(Server::INT, $count);
});

$server->setHandler('hGetAll', function ($data) use ($server) {
    if (count($data) < 1)
    {
        return $server->format(Server::ERROR, "ERR wrong number of arguments for 'hGetAll' command");
    }
    $key = $data[0];
    if (!isset($server->data[$key]))
    {
        return $server->format(Server::NIL);
    }
    return $server->format(Server::MAP, $server->data[$key]);
});

$server->on('WorkerStart', function ($server) {
    $server->tick(10000, function() use ($server) {
        file_put_contents(DB_FILE, serialize($server->data));
    });
});

$server->start();
