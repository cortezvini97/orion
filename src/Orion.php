<?php

namespace Orion;

use Orion\Internal\Engine\OrionEngine;

class Orion {
    private OrionEngine $engine;
    private bool $debug = false;
    private bool $deleteFile = false;
    private static $directives = [];
    private $app = null;


    public function __construct($configs = [])
    {
        if(isset($configs["deleteFile"])){
            $this->deleteFile = $configs["deleteFile"];
        }
        $this->engine = new OrionEngine($configs);
    }

    protected function log($message)
    {
        if ($this->debug) {
            echo "<pre>[DEBUG] $message</pre>";
        }
    }

    public function setCustomDirectives(string $file){
        $this->engine->setCustomDirectives($file);
    }

    protected function loadCustoms(){
        $this->engine->loadCustoms();
    }

    public function setApp(){
        $this->app = $this;
    }
    
    public function render(string $view, array $data = []){
        $this->loadCustoms();
        $result = $this->engine->renderView($view, $data);
        extract($data);
        if($this->app != null){
            $app = $this->app;
        }
        //render
        if($this->debug){
            ob_start();
            eval(' ?>' . $result . '<?php ');
            $renderedContent = ob_get_clean();

            $this->log("Final rendered content:\n" . htmlspecialchars($renderedContent));
            
            return $renderedContent;
        }else{
            ob_start();
            $file_dir = $this->engine->genTemplateFile($result);
            include ($file_dir);
            $renderedContent =  ob_get_clean();
            if($this->deleteFile){
                unlink($file_dir);
            }
            return $renderedContent;
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