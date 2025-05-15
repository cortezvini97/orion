# Orion Template Engine

Orion é uma biblioteca PHP leve e eficiente para renderização de templates e visualizações, projetada para ser flexível e fácil de usar em seus projetos.

![Orion Template Engine](img/orion.svg?text=Orion+Template+Engine)

## Índice

- [Instalação](#instalação)
- [Configuração](#configuração)
- [Uso Básico](#uso-básico)
- [Trabalhando com Templates](#trabalhando-com-templates)
- [Diretivas Personalizadas](#diretivas-personalizadas)
- [Modo Debug](#modo-debug)
- [Exemplos](#exemplos)
- [API de Referência](#api-de-referência)
- [Contribuição](#contribuição)
- [Licença](#licença)

## Instalação

```bash
composer require cortezvini97/orion
```

## Configuração

Para começar a usar o Orion, você precisa criar uma instância da classe principal e configurá-la de acordo com suas necessidades:

```php
use Orion\Orion;

// Configuração básica
$orion = new Orion([
    'template_path' => 'caminho/para/templates',
    'cache_path' => 'caminho/para/cache',
    // outras configurações
]);
```

### Opções de Configuração

| Opção | Tipo | Descrição | Padrão |
|-------|------|-----------|--------|
| template_path | string | Caminho para o diretório de templates | ./templates |
| cache_path | string | Caminho para armazenar arquivos compilados | ./cache |
| cache_enabled | bool | Ativa ou desativa o cache de templates | true |
| file_extension | string | Extensão padrão dos arquivos de template | .orion.php |

## Uso Básico

O Orion permite renderizar templates de forma simples e rápida:

```php
// Renderizar um template com dados
$orion->render('home', [
    'title' => 'Página Inicial',
    'user' => $user,
    'items' => $items
]);
```

Um template básico poderia ser assim:

```php
<!-- home.orion.php -->
<html>
<head>
    <title><?= $title ?></title>
</head>
<body>
    <h1>Bem-vindo, <?= $user->name ?>!</h1>
    
    <ul>
    <?php foreach($items as $item): ?>
        <li><?= $item->name ?></li>
    <?php endforeach; ?>
    </ul>
</body>
</html>
```

## Trabalhando com Templates

O Orion oferece um sistema de templates poderoso e flexível. Você pode incluir outros templates, herdar layouts e muito mais.

### Incluir Templates

```php
@include('components.header')
@include('components.footer', ['year' => 2025])
```

### Herança de Templates

```php
<!-- child.orion.php -->
@extends('layouts.master')

@section('title', 'Página de Exemplo')

@section('content')
    <p>Este é o conteúdo da página</p>
@endsection
```

```php
<!-- layouts/master.orion.php -->
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title')</title>
</head>
<body>
    <header>
        @include('components.navbar')
    </header>
    
    <main>
        @yield('content')
    </main>
    
    <footer>
        @include('components.footer')
    </footer>
</body>
</html>
```

## Diretivas Personalizadas

O Orion permite que você crie suas próprias diretivas para estender a funcionalidade do sistema de templates:

```php
use Orion\Orion;

// Registrar uma diretiva personalizada
Orion::directive('uppercase', function ($expression) {
    return "<?php echo strtoupper($expression); ?>";
});

Orion::directive('datetime', function ($expression) {
    return "<?php echo date('Y-m-d H:i:s', $expression); ?>";
});
```

Uso no template:

```php
<p>@uppercase($name)</p>
<p>Data: @datetime(time())</p>
```

### Diretivas Integradas

O Orion vem com algumas diretivas úteis pré-integradas:

| Diretiva | Descrição | Exemplo |
|----------|-----------|---------|
| @if | Estrutura condicional | @if($condition) ... @endif |
| @foreach | Loop foreach | @foreach($items as $item) ... @endforeach |
| @include | Incluir outro template | @include('path.to.template') |
| @extends | Herdar de um layout | @extends('layouts.master') |
| @section | Definir uma seção | @section('name') ... @endsection |
| @yield | Renderizar uma seção | @yield('name', 'default') |

## Modo Debug

O Orion inclui um modo de depuração que pode ser útil durante o desenvolvimento:

```php
$orion = new Orion([
    'debug' => true
]);
```

No modo de depuração, o Orion:
- Exibe informações detalhadas sobre o processo de renderização
- Mostra erros de compilação e execução de forma mais clara
- Não utiliza cache para os templates
- Registra informações sobre o desempenho

## Exemplos

### Exemplo de Aplicação Completa

```php
<?php
// index.php
require_once 'vendor/autoload.php';

use Orion\Orion;

// Configurar diretivas personalizadas
Orion::directive('markdown', function ($expression) {
    return "<?php echo parseMarkdown($expression); ?>";
});

// Configurar e inicializar o Orion
$orion = new Orion([
    'template_path' => 'resources/views',
    'cache_path' => 'storage/framework/views',
    'debug' => true
]);

// Obter dados para a página
$user = getUserFromDatabase();
$posts = getRecentPosts();

// Renderizar a página
$orion->render('blog.index', [
    'title' => 'Meu Blog',
    'user' => $user,
    'posts' => $posts
]);
```

## API de Referência

### Classe Orion

#### Métodos Públicos

| Método | Parâmetros | Retorno | Descrição |
|--------|------------|---------|-----------|
| __construct | array $configs = [] | void | Inicializa o Orion com as configurações fornecidas |
| render | string $view, array $data = [] | void | Renderiza um template com os dados fornecidos |
| directive | string $name, \Closure $handler | void | Registra uma diretiva personalizada |
| getDirective | string $name | \Closure\|null | Obtém uma diretiva pelo nome |
| getAllDirectives | - | array | Obtém todas as diretivas registradas |
| execute | string $name, string $expression | string | Executa uma diretiva específica |

### Motor de Renderização (OrionEngine)

O Orion utiliza internamente um motor de renderização poderoso que gerencia:

- Compilação de templates
- Transformação de diretivas em código PHP
- Cache de templates compilados
- Resolução de dependências entre templates

## Contribuição

Contribuições são bem-vindas! Sinta-se à vontade para:

1. Reportar bugs
2. Sugerir novas funcionalidades
3. Enviar pull requests

### Diretrizes para Contribuição

- Siga o estilo de código PSR-12
- Adicione testes para novas funcionalidades
- Documente novos recursos
- Mantenha a retrocompatibilidade quando possível

## Licença

Orion Template Engine é software livre, licenciado sob a licença MIT. Veja o arquivo LICENSE para mais informações.

---

Desenvolvido com ❤️ pela equipe Orion