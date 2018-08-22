<?php

namespace Ardentic\Squeezer\Compilers;

use Illuminate\View\Compilers\BladeCompiler;

class ModifiedBladeCompiler extends BladeCompiler
{
    public $tmpData;

    public function compileStringToFile($content, $path, $data = [])
    {
      if (! is_null($this->cachePath)) {
        $compiledContent = $this->compileString($content, $data);
        $compiledPath = $this->getCompiledPath($path);
        $this->files->put($compiledPath, $compiledContent);
      }
    }

    public function getFiles()
    {
      return $this->files;
    }

    public function compile($path = null, $data = [])
    {
        if ($path) {
            $this->setPath($path);
        }

        if (count($data)) {
          $this->tmpData = $data;
        } else {
          $this->tmpData = [];
        }

        $contents = $this->files->get($this->getPath());
        $contents = $this->compileString($contents);
        if (! is_null($this->cachePath)) {
            $this->files->put($this->getCompiledPath($this->getPath()), $contents);
        }
    }

    public function compileString($value)
    {
        $result = '';

        $this->footer = [];

        // Here we will loop through all of the tokens returned by the Zend lexer and
        // parse each one into the corresponding valid PHP. We will then have this
        // template as the correctly rendered PHP that can be rendered natively.
        foreach (token_get_all($value) as $token) {
            $result .= is_array($token) ? $this->parseToken($token) : $token;
        }

        // If there are any footer lines that need to get added to a template we will
        // add them here at the end of the template. This gets used mainly for the
        // template inheritance via the extends keyword that should be appended.
        if (count($this->footer) > 0) {
            $result = ltrim($result, PHP_EOL)
                    .PHP_EOL.implode(PHP_EOL, array_reverse($this->footer));
        }

        return $result;
    }
}
