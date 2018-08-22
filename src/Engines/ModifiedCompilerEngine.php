<?php

namespace Ardentic\Squeezer\Engines;

use Illuminate\View\Engines\CompilerEngine;

class ModifiedCompilerEngine extends CompilerEngine
{
    public function get($path, array $data = [])
    {
      if(array_key_exists('__blockChildData', $data)){
        $compiler = $this->getCompiler();
        $cachePath = str_replace('.blade.php', $data['__blockChildData']['variable'].'.blade.php', $path);
        $compiledPath = $compiler->getCompiledPath($cachePath);
        $lastModified = $compiler->getFiles()->lastModified($path);
        $lastModifiedCompiled = 0;
        if($compiler->getFiles()->exists($compiledPath)){

          $lastModifiedCompiled = $compiler->getFiles()->lastModified($compiledPath);

          if ($lastModified >= $lastModifiedCompiled) {
            if(isset($data['__parentTemplate'])){
              $parentCompiledPath = $this->compiler->getCompiledPath($data['__parentTemplate']);
              $lastModifiedParentCompiled = $compiler->getFiles()->lastModified($parentCompiledPath);


              if($lastModified >= $lastModifiedParentCompiled){

                /*
                 *  Fix Artisan::call('view:clear') when time, this will only be
                 *  used when coding on local machine
                 */
                \Artisan::call('view:clear');
                $this->compiler->compile($data['__parentTemplate'], $data['__parentTemplateData']);
                $compiled = $this->compiler->getCompiledPath($data['__parentTemplate']);
                $results = $this->evaluatePath($compiled, $data);
                array_pop($this->lastCompiled);
              }
              return $this->evaluatePath($compiledPath, $data);

              //Needs to recompile most upper view
              /*
              $templateContent = $compiler->getFiles()->get($path);
              dd($data['__parentTemplate']);
              dd($templateContent);
              $compiledContent = $compiler->compileString($templateContent, $data);
              $compiler->getFiles()->put($compiledPath, $compiledContent);
              return $compiledContent;
              */
            } else {
              $this->compiler->compile($path, $data);
            }
          } else {
            return $this->evaluatePath($compiledPath, $data);
          }
        } else {

          $this->compiler->compile($path, $data);
          $compiled = $this->compiler->getCompiledPath($path);
          $results = $this->evaluatePath($compiled, $data);

          array_pop($this->lastCompiled);
          return $results;
        }
        /*
        if(isset($data['__parentTemplate'])){
          $parentLastModified = $compiler->getFiles()->lastModified($data['__parentTemplate']);
          if($parentLastModified >= $lastModifiedCompiled){
            $this->compiler->compile($data['__parentTemplate'], $data);
          }
        }

        $results = $this->evaluatePath($compiledPath, $data);

        array_pop($this->lastCompiled);

        return $results;
        */
      } else {

        $this->lastCompiled[] = $path;

        // If this given view has expired, which means it has simply been edited since
        // it was last compiled, we will re-compile the views so we can evaluate a
        // fresh copy of the view. We'll pass the compiler the path of the view.
        if ($this->compiler->isExpired($path)) {
            $this->compiler->compile($path, $data);
        }


        $compiled = $this->compiler->getCompiledPath($path);

        // Once we have the path to the compiled file, we will evaluate the paths with
        // typical PHP just like any other templates. We also keep a stack of views
        // which have been rendered for right exception messages to be generated.
        $results = $this->evaluatePath($compiled, $data);

        array_pop($this->lastCompiled);

        return $results;
      }
  }
}
