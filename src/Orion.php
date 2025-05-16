<?php

namespace Orion;

use Orion\Internal\Engine\OrionEngine;

class Orion {
    private OrionEngine $engine;
    private bool $debug = false;
    private static $directives = [];


    public function __construct($configs = [])
    {
        $this->engine = new OrionEngine($configs);
    }

    protected function log($message)
    {
        if ($this->debug) {
            echo "<pre>[DEBUG] $message</pre>";
        }
    }

    protected function loadCustoms(){
        $this->engine->loadCustoms();
    }
    
    public function render(string $view, array $data = []){
        $this->loadCustoms();
        $result = $this->engine->renderView($view, $data);
        extract($data);
        //render
        if($this->debug){
            ob_start();
            eval(' ?>' . $result . '<?php ');
            $renderedContent = ob_get_clean();

            $this->log("Final rendered content:\n" . htmlspecialchars($renderedContent));

            echo $renderedContent;
        }else{
            ob_start();
            $file_dir = $this->engine->genTemplateFile($result);
            include ($file_dir);
            return ob_get_clean();
        }
    }

    public static function directive($name, \Closure $handler)
    {
        static::$directives[$name] = $handler;
    }

    public static function getDirective($name)
    {
        return static::$directives[$name] ?? null;
    }

    public static function getAllDirectives()
    {
        return static::$directives;
    }

    public static function execute($name, $expression)
    {
        if (!isset(static::$directives[$name])) {
            throw new \Exception("Diretiva '{$name}' não registrada.");
        }

        return call_user_func(static::$directives[$name], $expression);
    }
}