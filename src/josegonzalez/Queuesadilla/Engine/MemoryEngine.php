<?php

namespace josegonzalez\Queuesadilla\Engine;

use DateInterval;
use DateTime;
use josegonzalez\Queuesadilla\Engine\Base;

class MemoryEngine extends Base
{
    protected $baseConfig = [
        'api_version' => 1,  # unsupported
        'delay' => null,
        'database' => 'database_name',  # unsupported
        'expires_in' => null,
        'user' => null,  # unsupported
        'pass' => null,  # unsupported
        'persistent' => true,  # unsupported
        'port' => 0,  # unsupported
        'priority' => 0,  # unsupported
        'protocol' => 'https',  # unsupported
        'queue' => 'default',
        'host' => '127.0.0.1',  # unsupported
        'table' => null,  # unsupported
        'time_to_run' => 60,  # unsupported
        'timeout' => 0,  # unsupported
    ];

    protected $queues = [];

    /**
     * {@inheritDoc}
     */
    public function connect()
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function delete($item)
    {
        if (!is_array($item) || !isset($item['id'])) {
            return false;
        }

        $deleted = false;
        foreach ($this->queues as $name => $queue) {
            foreach ($queue as $i => $queueItem) {
                if ($queueItem['id'] === $item['id']) {
                    unset($this->queues[$name][$i]);
                    $deleted = true;
                    break 2;
                }
            }
        }
        return $deleted;
    }

    /**
     * {@inheritDoc}
     */
    public function pop($options = [])
    {
        $queue = $this->setting($options, 'queue');
        $this->requireQueue($options);

        $itemId = null;
        $item = null;
        while ($item === null) {
            $item = array_shift($this->queues[$queue]);
            if (!$item) {
                return null;
            }

            if ($itemId === $item['id']) {
                array_push($this->queues[$queue], $item);
                return null;
            }

            if ($itemId === null) {
                $itemId = $item['id'];
            }

            if (empty($item['options'])) {
                break;
            }

            $datetime = new DateTime;
            if (!empty($item['options']['delay_until'])) {
                if ($datetime < $item['options']['delay_until']) {
                    $this->queues[$queue][] = $item;
                    $item = null;
                    continue;
                }
            }

            if (!empty($item['options']['expires_at'])) {
                if ($datetime > $item['options']['expires_at']) {
                    $item = null;
                    continue;
                }
            }
        }

        return $item;
    }

    /**
     * {@inheritDoc}
     */
    public function push($class, $vars = [], $options = [])
    {
        if (!is_array($options)) {
            $options = ['queue' => $options];
        }

        $queue = $this->setting($options, 'queue');
        $delay = $this->setting($options, 'delay');
        $expiresIn = $this->setting($options, 'expires_in');
        $this->requireQueue($options);
        $jobId = $this->jobId();

        if ($delay !== null) {
            $datetime = new DateTime;
            $options['delay_until'] = $datetime->add(new DateInterval(sprintf('PT%sS', $delay)));
            unset($options['delay']);
        }

        if ($expiresIn !== null) {
            $datetime = new DateTime;
            $options['expires_at'] = $datetime->add(new DateInterval(sprintf('PT%sS', $expiresIn)));
            unset($options['expires_in']);
        }

        $oldCount = count($this->queues[$queue]);
        $newCount = array_push($this->queues[$queue], [
            'id' => $jobId,
            'class' => $class,
            'vars' => $vars,
            'options' => $options
        ]);
        return $newCount === ($oldCount + 1);
    }

    /**
     * {@inheritDoc}
     */
    public function queues()
    {
        return array_keys($this->queues);
    }

    /**
     * {@inheritDoc}
     */
    public function release($item, $options = [])
    {
        $queue = $this->setting($options, 'queue');
        $this->requireQueue($options);

        return array_push($this->queues[$queue], $item) !== count($this->queues[$queue]);
    }

    protected function requireQueue($options)
    {
        $queue = $this->setting($options, 'queue');
        if (!isset($this->queues[$queue])) {
            $this->queues[$queue] = [];
        }
    }
}
