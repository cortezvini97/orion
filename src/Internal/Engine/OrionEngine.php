<?php

namespace Orion\Internal\Engine;

use Exception;
use Orion\Internal\Compile\OrionCompiler;

/**
 * @internal Esta classe é apenas para uso interno da biblioteca.
 */
final class OrionEngine
{
    protected $viewsPath = './views/';
    protected $functionsPath = './functions/';
    protected $directivesPath = './directives/';
    protected $compiledPath =  './compiled/';
    protected bool $debug = false;
    protected $custom_directives = [];

    public function __construct($configs = [])
    {
        if(isset($configs["debug"])){
            $this->debug = $configs["debug"];
        }


        if(isset($configs["viewsPath"])){
            $this->viewsPath = $configs["viewsPath"];
        }

        if(isset($configs["functionsPath"])){
            $this->functionsPath = $configs["functionsPath"];
        }

        if(isset($configs["directivesPath"])){
            $this->directivesPath = $configs["directivesPath"];
        }

        if(isset($configs["compiledPath"])){
            $this->compiledPath = $configs["compiledPath"];
        }

        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $bt[1]['class'] ?? null;

        if ($caller !== 'Orion\Orion') {
            throw new \RuntimeException('OrionEngine é para uso interno apenas.');
        }
    }

    protected function log($message)
    {
        if ($this->debug) {
            echo "<pre>[DEBUG] $message</pre>";
        }
    }

    public function loadCustoms()
    {
        require_once __DIR__."/../headers/headers.php";

        foreach (glob($this->functionsPath . '*.php') as $file) {
            require_once $file;
        }
    }

    public function genTemplateFile($content){
        if (!is_dir($this->compiledPath)) {
            mkdir($this->compiledPath, 0755, true);
        }
        $filename = md5($content) . '.php';
        $filePath = $this->compiledPath . $filename;
        if (!file_exists($filePath)) {
            file_put_contents($filePath, $content);
        }

        // Retorna o caminho completo
        return $filePath;
    }


    public function setCustomDirectives(string $file_path){
        
        if (file_exists($file_path)) {
            $this->log("Loading directives from: $file_path");
            
            try {
                $directives = require($file_path);
            } catch (Exception $e) {
                $this->log("Error loading directives: " . $e->getMessage());
            }
        } else {
            $this->log("Directives file not found: $directiveFile");
        }
    }


    public function renderView(string $view, array $data = []){
        $this->log("Starting render for view: $view");
        $file = $this->viewsPath . "/" . str_replace('.', '/', $view) . '.orion.php';

        $this->log("Looking for view file: $file");

        if (!file_exists($file)) {
            throw new Exception("View {$view} not found.");
        }

        $this->log("Loading custom functions and directives");

        //getContent
        // 1. Load page content
        $content = file_get_contents($file);
        $this->log("Original content:\n" . htmlspecialchars($content));
        //Compile
        $compiler = new OrionCompiler($this->debug, $this->viewsPath, $this->directivesPath);
        $result = $compiler->compile($content, $data, $this->custom_directives);
        return $result;
    }
}