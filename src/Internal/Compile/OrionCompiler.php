<?php

namespace Orion\Internal\Compile;

use Exception;

/**
 * @internal Esta classe é apenas para uso interno da biblioteca.
 */
final class OrionCompiler
{
    protected $sections = [];
    protected $extends = null;
    protected string $content = '';
    protected string $viewsPath;
    protected string $directivePath;
    protected bool $debug;
    protected array $directives = [];

    public function __construct(bool $debug, string $viewsPath, string $directivePath){

        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $bt[1]['class'] ?? null;

        if ($caller !== 'Orion\Orion' && $caller !== 'Orion\Internal\Engine\OrionEngine') {
            throw new \RuntimeException('OrionCompiler é para uso interno apenas.');
        }

        $this->debug = $debug;
        $this->viewsPath = $viewsPath;
        $this->directivePath = $directivePath;
        $this->loadDirectives();
    }

    protected function log($message)
    {
        if ($this->debug) {
            echo "<pre>[DEBUG] $message</pre>";
        }
    }

    protected function checkExtends(){
        if (preg_match('/@extends\([\'"](.+)[\'"]\)/', $this->content, $matches)) {
            $this->extends = $matches[1];
            $this->content = preg_replace('/@extends\([\'"].+[\'"]\)/', '', $this->content);
        }
    }

    protected function extractSections(){
        $this->sections = [];
        // First, handle inline sections (single line)
        $this->content = preg_replace_callback(
            '/@section\([\'"]([^\'"\)]+)[\'"]\s*,\s*[\'"]([^\'"\)]+)[\'"]\)/', 
            function($matches) {
                $this->sections[$matches[1]] = $matches[2];
                return '';
            }, 
            $this->content
        );

        // Then, handle block sections
        $this->content = preg_replace_callback(
            '/@section\([\'"]([^\'"\)]+)[\'"]\)(.*?)@endsection/s', 
            function($matches) {
                $this->sections[$matches[1]] = trim($matches[2]);
                return '';
            }, 
            $this->content
        );

        // Debug logging
        $this->log("Extracted Sections: " . print_r($this->sections, true));
    }

    protected function injectSectionsIntoLayout($layoutContent){
        return preg_replace_callback('/@yield\([\'"](.+?)[\'"]\)/', function ($matches) {
            $sectionName = $matches[1];
            $this->log("Injecting section: $sectionName");
            return $this->sections[$sectionName] ?? '';
        }, $layoutContent);
    }

    protected function compileStatements($content){

        $paterns = [
            '/@if\s*\((.+)\)/' => '<?php if ($1): ?>',
            '/@elseif\s*\((.+)\)/' => '<?php elseif ($1): ?>',
            '/@else/' => '<?php else: ?>',
            '/@endif/' => '<?php endif; ?>',
            '/@foreach\s*\((.+)\)/' => '<?php foreach ($1): ?>',
            '/@endforeach/' => '<?php endforeach; ?>',
            '/@php/' => '<?php ',
            '/@endphp/' => ' ?>',
            '/@csrf/' => '<?php echo csrfDirective(); ?>',
            '/@for\s*\((.+)\)/' => '<?php for ($1): ?>',
            '/@endfor/' => '<?php endfor; ?>',
            '/@break/' => '<?php break; ?>',
            '/@while\s*\((.+)\)/' => '<?php while ($1): ?>',
            '/@endwhile/' => '<?php endwhile; ?>',
            '/@dowhile/' => '<?php do { ?>',
            '/@enddowhile\s*\((.+)\)/' => '<?php } while ($1); ?>',
        ];

        foreach($paterns as $pattern => $replace) {
            $content = preg_replace($pattern, $replace, $content);
        }

        return $content;
    }

    protected function compileEchos($content) {
        $content = preg_replace('/{{\s*(?!.*!!)(.+?)\s*}}/', '<?php echo escape($1); ?>', $content);
        return $content;
    }

    protected function compileEchosHtml($content) {
        $content = preg_replace('/\{\!\!\s*(.+?)\s*\!\!\}/', '<?php echo $1; ?>', $content);
        return $content;
    }

    protected function loadDirectives() {
        $directiveFile = $this->directivePath . '/directives.php';
        
        if (file_exists($directiveFile)) {
            $this->log("Loading directives from: $directiveFile");
            
            try {
                $directives = require($directiveFile);
                
                if (is_array($directives)) {
                    $this->directives = $directives;
                    $this->log("Loaded " . count($this->directives) . " custom directives");
                } else {
                    $this->log("Directives file did not return an array");
                }
            } catch (Exception $e) {
                $this->log("Error loading directives: " . $e->getMessage());
            }
        } else {
            $this->log("Directives file not found: $directiveFile");
        }
    }

    
    protected function compileCustomDirectives($content) {
        if (empty($this->directives)) {
            return $content;
        }
        
        foreach ($this->directives as $name => $callback) {
            // Pattern for directives with parameters: @directive(param1, param2)
            $patternWithParams = '/@' . $name . '\s*\((.+?)\)/';
            
            $content = preg_replace_callback($patternWithParams, function($matches) use ($callback) {
                $args = $this->parseDirectiveArguments($matches[1]);
                return $callback(...$args);
            }, $content);
            
            // Pattern for directives without parameters: @directive
            $patternWithoutParams = '/@' . $name . '(?!\w|\()/';
            
            $content = preg_replace_callback($patternWithoutParams, function($matches) use ($callback) {
                // Call the callback with no arguments
                return $callback();
            }, $content);
        }
        
        return $content;
    }

    protected function parseDirectiveArguments($argumentsString) {
        // Remove espaços em branco
        $argumentsString = trim($argumentsString);
        
        // Se não tiver argumentos, retorna array vazio
        if (empty($argumentsString)) {
            return [];
        }
        
        // Parâmetros podem ser separados por vírgulas
        $arguments = [];
        $buffer = '';
        $inString = false;
        $stringChar = '';
        $escaped = false;
        $parenthesesLevel = 0;
        
        for ($i = 0; $i < strlen($argumentsString); $i++) {
            $char = $argumentsString[$i];
            
            // Lidando com caracteres escapados
            if ($escaped) {
                $buffer .= $char;
                $escaped = false;
                continue;
            }
            
            // Verifica se é um caractere de escape
            if ($char === '\\') {
                $escaped = true;
                $buffer .= $char;
                continue;
            }
            
            // Lidando com strings
            if ($char === '"' || $char === "'") {
                if (!$inString) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === $stringChar) {
                    $inString = false;
                }
                $buffer .= $char;
                continue;
            }
            
            // Lidando com parênteses
            if ($char === '(') {
                $parenthesesLevel++;
                $buffer .= $char;
                continue;
            }
            
            if ($char === ')') {
                $parenthesesLevel--;
                $buffer .= $char;
                continue;
            }
            
            // Se estamos dentro de uma string ou parênteses, adicione o caractere ao buffer
            if ($inString || $parenthesesLevel > 0) {
                $buffer .= $char;
                continue;
            }
            
            // Se encontrarmos uma vírgula fora de uma string e não estamos dentro de parênteses
            if ($char === ',' && !$inString && $parenthesesLevel === 0) {
                $arguments[] = trim($buffer);
                $buffer = '';
                continue;
            }
            
            // Adiciona o caractere ao buffer
            $buffer .= $char;
        }
        
        // Adiciona o último argumento
        if (!empty($buffer)) {
            $arguments[] = trim($buffer);
        }
        
        return $arguments;
    }

    protected function compileSwitch($content)
    {
        // Padrão para encontrar a estrutura completa do switch
        $switchPattern = '/@switch\s*\((.+?)\)(.*?)@endswitch/s';
        
        return preg_replace_callback($switchPattern, function($matches) {
            $condition = $matches[1];
            $body = $matches[2];
            
            // Processa o primeiro @case especialmente para colocá-lo na mesma linha do switch
            $firstCaseProcessed = false;
            $body = preg_replace_callback('/@case\s*\((.+?)\)/', function($caseMatches) use (&$firstCaseProcessed) {
                if (!$firstCaseProcessed) {
                    $firstCaseProcessed = true;
                    return "case ({$caseMatches[1]}): ?>";
                } else {
                    return "<?php case ({$caseMatches[1]}): ?>";
                }
            }, $body);
            
            // Processa o @default
            $body = preg_replace('/@default/', '<?php default: ?>', $body);
            
            // Processa os @break dentro do switch
            $body = preg_replace('/@break/', '<?php break; ?>', $body);
            
            // Monta a estrutura final do switch com o primeiro case na mesma linha
            return "<?php switch($condition): {$body}<?php endswitch; ?>";
        }, $content);
    }

    protected function processIncludes($content, $data)
    {
        return preg_replace_callback(
            '/@include\([\'"]([^\'"\)]+)[\'"](,\s*(\[.*?\]))?\)/', 
            function($matches) use ($data) {
                $includePath = $matches[1];
                $includeData = $data; // Start with parent data
                
                // Check if additional data was provided
                if (isset($matches[3])) {
                    // Parse the array string safely without eval
                    $additionalData = $this->parseIncludeData($matches[3]);
                    if (is_array($additionalData)) {
                        $includeData = array_merge($data, $additionalData);
                    }
                }
                
                // Render the included file
                return $this->renderInclude($includePath, $includeData);
            }, 
            $content
        );
    }

    protected function parseIncludeData(string $raw): array
    {
        // Remove os colchetes externos para processar o conteúdo
        $raw = trim($raw);
        if (substr($raw, 0, 1) === '[' && substr($raw, -1) === ']') {
            $raw = substr($raw, 1, -1);
        }
        
        $result = [];
        $pairs = [];
        
        // Divide a string em pares chave=>valor
        $state = 'key';
        $buffer = '';
        $quote = null;
        $escape = false;
        $depth = 0;
        $currentKey = null;
        
        for ($i = 0; $i < strlen($raw); $i++) {
            $char = $raw[$i];
            
            // Gerencia escapes
            if ($escape) {
                $buffer .= $char;
                $escape = false;
                continue;
            }
            
            // Verifica caractere de escape
            if ($char === '\\') {
                $escape = true;
                continue;
            }
            
            // Gerencia aspas
            if (($char === "'" || $char === '"') && $depth === 0) {
                if ($quote === null) {
                    $quote = $char;
                    continue;
                } elseif ($quote === $char) {
                    $quote = null;
                    continue;
                }
            }
            
            // Dentro de aspas, adiciona tudo ao buffer
            if ($quote !== null) {
                $buffer .= $char;
                continue;
            }
            
            // Gerencia profundidade de arrays/objetos aninhados
            if ($char === '[' || $char === '{') {
                $depth++;
                $buffer .= $char;
                continue;
            }
            
            if ($char === ']' || $char === '}') {
                $depth--;
                $buffer .= $char;
                continue;
            }
            
            // Dentro de estruturas aninhadas, adiciona tudo ao buffer
            if ($depth > 0) {
                $buffer .= $char;
                continue;
            }
            
            // Processa separadores
            if ($char === '=' && $state === 'key' && substr($raw, $i + 1, 1) === '>') {
                // Encontrou =>
                $state = 'value';
                $currentKey = trim($buffer);
                // Remove aspas do nome da chave se presentes
                if ((substr($currentKey, 0, 1) === "'" && substr($currentKey, -1) === "'") ||
                    (substr($currentKey, 0, 1) === '"' && substr($currentKey, -1) === '"')) {
                    $currentKey = substr($currentKey, 1, -1);
                }
                $buffer = '';
                $i++; // Pula o >
                continue;
            }
            
            if ($char === ',' && $state === 'value') {
                // Terminou um par chave-valor
                $value = trim($buffer);
                // Tenta converter para tipos PHP nativos
                $value = $this->convertStringValue($value);
                $result[$currentKey] = $value;
                
                $buffer = '';
                $state = 'key';
                continue;
            }
            
            // Adiciona o caractere ao buffer
            $buffer .= $char;
        }
        
        // Não esqueça do último par se houver
        if ($state === 'value' && !empty($currentKey)) {
            $value = trim($buffer);
            $value = $this->convertStringValue($value);
            $result[$currentKey] = $value;
        }
        
        return $result;
    }

    protected function convertStringValue($value)
    {
        // Remove aspas se presentes
        if ((substr($value, 0, 1) === "'" && substr($value, -1) === "'") ||
            (substr($value, 0, 1) === '"' && substr($value, -1) === '"')) {
            return substr($value, 1, -1);
        }
        
        // Converte valores booleanos
        if (strtolower($value) === 'true') return true;
        if (strtolower($value) === 'false') return false;
        
        // Converte valor nulo
        if (strtolower($value) === 'null') return null;
        
        // Converte números
        if (is_numeric($value)) {
            // Inteiro ou float?
            if (strpos($value, '.') !== false) {
                return (float)$value;
            }
            return (int)$value;
        }
        
        // Retorna o valor como string se não for convertível
        return $value;
    }

    protected function renderInclude($view, $data = [])
    {
        $file = $this->viewsPath . str_replace('.', '/', $view) . '.orion.php';
        $this->log("Including file: $file");

        if (!file_exists($file)) {
            $this->log("Include file not found: $file");
            throw new Exception("Include file not found: $view");
        }

        // Load the include content
        $includeContent = file_get_contents($file);

        // Process nested includes
        $includeContent = $this->processIncludes($includeContent, $data);

        // Compile statements and echos
        $includeContent = $this->compileStatements($includeContent);
        $includeContent = $this->compileEchos($includeContent);

        // Extract data
        extract($data);

        // Save the compiled content to a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'orion_') . '.php';
        file_put_contents($tempFile, $includeContent);

        // Start output buffering and include the temporary file
        ob_start();
        include $tempFile;
        $output = ob_get_clean();

        // Remove the temporary file
        unlink($tempFile);

        return $output;
    }


    public function compile(string $content, array $data){
        $this->content = $content;
        $this->checkExtends();
        $this->log("Extends: " . ($this->extends ?? 'None'));
        $this->extractSections();
        if ($this->extends) {
            $layoutFile = $this->viewsPath . str_replace('.', '/', $this->extends) . '.orion.php';
            $this->log("Layout file: $layoutFile");

            if (!file_exists($layoutFile)) {
                throw new Exception("Layout {$this->extends} not found.");
            }

            $layoutContent = file_get_contents($layoutFile);
            $this->log("Layout content:\n" . htmlspecialchars($layoutContent));

            // 5. Replace yields with sections
            $finalContent = $this->injectSectionsIntoLayout($layoutContent);
        } else {
            $finalContent = $this->content;
        }
        

        $this->log("Content after layout injection:\n" . htmlspecialchars($finalContent));

        $finalContent = $this->processIncludes($finalContent, $data);
        $this->log("Content after processing includes:\n" . htmlspecialchars($finalContent));

        // Compilação do switch deve acontecer antes da compilação de outros statements
        // para evitar conflitos com o @break
        $finalContent = $this->compileSwitch($finalContent);
        $this->log("Content after compiling switch statements:\n" . htmlspecialchars($finalContent));

        // Compilar diretivas personalizadas antes de outros statements
        $finalContent = $this->compileCustomDirectives($finalContent);
        $this->log("Content after compiling custom directives:\n" . htmlspecialchars($finalContent));

        $finalContent = $this->compileStatements($finalContent);
        $this->log("Content after compiling statements:\n" . htmlspecialchars($finalContent));

        $finalContent = $this->compileEchos($finalContent);
        $this->log("Content after compiling echos:\n" . htmlspecialchars($finalContent));

        $finalContent = $this->compileEchosHtml($finalContent);
        $this->log("Content after compiling echos HTML:\n" . htmlspecialchars($finalContent));

        return $finalContent;
    }
}