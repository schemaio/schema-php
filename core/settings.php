<?php
/**
 * Schema PHP Template Framework
 *
 * @version  1.1.6
 * @link     https://schema.io
 * @license  http://opensource.org/licenses/mit MIT
 */

namespace Schema;

class Settings extends Util\ArrayInterface
{
    /**
     * @var array
     */
    protected $records;

    /**
     * @var \Swell\Client
     */
    protected $client;

    /**
     * @return \Schema\Settings
     */
    public function __construct($client)
    {
        $this->records = array();
        $this->client = $client;
        parent::__construct();
    }

 	/**
     * Get a setting record
     *
     * @param  string $key
     * @return mixed
     */
    public function offsetGet($id)
    {
        if (!array_key_exists($id, $this->records)) {
            return $this->get($id);
        }

        return $this->records[$id];
    }

    /**
     * Get a setting record
     *
     * @param  string $key
     * @return mixed
     */
    public function get($id)
    {
        if (array_key_exists($id, $this->records)) {
            return $this->records[$id];
        }
        $this->records[$id] = $this->client->get('/settings/{id}', array('id' => $id));
        if ($this->records[$id] === null) {
            $this->records[$id] = new Util\ArrayInterface;
        }
        return $this->records[$id];
    }
}
