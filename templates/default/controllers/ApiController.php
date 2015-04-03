<?php

class ApiController
{
    public $index;
    public $method;
    public $url;
    public $rel_url;

    /**
     * Default API console index
     */
    public function index()
    {
        $options = array();
        $result = null;
        $url = null;
        $timing = null;

        $this->method = isset($this->params['method']) ? $this->params['method'] : null;
        $this->url = isset($this->params['url']) ? $this->params['url'] : null;
        $this->rel_url = isset($this->params['rel_url']) ? $this->params['rel_url'] : null;

        if ($this->url) {
            $start = microtime(true);
            $relative_url = $this->rel_url;
            if ($relative_url && $relative_url[0] !== '/') {
                $relative_url = "/{$relative_url}";
            }
            $url = "{$this->url}{$relative_url}";
            try {
                if ($url === '/:options') {
                    $options = $this->get_options_sorted();
                    $result = $options;
                } else {
                    $result = request($this->method ?: "GET", $url);
                    $result = $result instanceof \Schema\Resource
                        ? $result->dump(true, false) : $result;
                }
            } catch(\Schema\ServerException $e) {
                $result = array('$error' => $e->getMessage());
            }
            $end = microtime(true);
            $timing = ($end - $start);
        }

        if (!$options) {
            $options = $this->get_options_sorted();
        }

        $this->index = array(
            'options' => $options,
            'result' => $result,
            'url' => $url,
            'timing' => $timing
        );
    }

    /**
     * Get sorted API options
     *
     * @return array
     */
    private function get_options_sorted()
    {
        $options = get("/:options");
        $options = $options instanceof \Schema\Resource 
            ? $options->data() : $options;

        uksort($options, function($a, $b) {
            if ($a[0] == ':') {
                if ($b[0] == ':') {
                    return substr($a, 1) > substr($b, 1);
                } else {
                    return 1;
                }
            } else if ($b[0] == ':') {
                return -1;
            } else {
                return $a > $b;
            }
        });

        return $options;
    }
}
