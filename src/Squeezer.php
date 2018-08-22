<?php

namespace Ardentic\Squeezer;

use Illuminate\Foundation\Application;
use View;

class Squeezer
{

  private $variables;
  private $nestledEmbeds;

  public function __construct()
  {
    $this->variables = [];
    $this->nestledEmbeds = [];
  }

  public static function attach(Application $app)
  {
      $class = $app['squeezer'];
      $method = 'register';
      $view = $app['view'];
      $compiler = $app['view']->getEngineResolver()->resolve('blade')->getCompiler();
      $compiler->extend(function ($value) use ($class, $method, $compiler, $view) {
        return $class->$method($value,  $compiler, $view);
      });
  }

  private function handleBlockInEmbed($html, $templateName, $compiler) {
    $variables = [];
    $bla = preg_match_all('/(?<!\w)(\s*)@block\(([^\)]+)\)(.+?(?=@endblock))@endblock/s', $html, $blockOut);
    foreach ($blockOut[2] as $blockVariableIndex => $blockVariable) {
      $variableName = md5($blockOut[0][$blockVariableIndex]);
      $variables[str_replace("'", "", $blockVariable)] = $blockOut[3][$blockVariableIndex];
    }

    return $variables;
  }

  private function checkNestledEmbeds($html, $break = false)
  {
    $tmp = preg_match_all('/(?<!\w)(\s*)((@embed\(.*?\)))(.*?@embed)\(.*?\)(.*?@endembed)/s', $html, $out);
    if(substr_count($out[0][0], '@embed') > 1){
      $tmp = preg_match_all('/(?<!\w)(\s*)(@embed\(.*?\))/s', $html, $replaceOut);
      $embeds = $replaceOut[2];
      $embedToReplace = $embeds[count($embeds) - 1];

      $firstSplitPosition = strrpos($html, $embedToReplace);
      $subHtml = substr($html, $firstSplitPosition);

      $posToEnd = strrpos($subHtml, '@endembed');
      $subHtml = substr($subHtml, 0, $posToEnd+9);

      $randomVariable = md5($subHtml);
      $this->nestledEmbeds[$randomVariable] = $subHtml;
      $html = str_replace($subHtml, '@nestledEmbed('.$randomVariable.')', $html);
    }
    return $html;
  }

  private function registerSingleEmbed($embed, $compiler){
    $tmp = preg_match_all('/(?<!\w)(\s*)@embed\(([^\)]+)\)(.+?(?=@endembed)@endembed)/s', $embed, $out);
    $templateName = explode("'", $out[2][0])[1];
    $variableName = md5($out[3][0]);
    $variables = $this->handleBlockInEmbed($out[3][0], $templateName, $compiler);

    $viewBlockData = ['variables' => $variables, 'defaultCode' => $out[3][0]];
    \Cache::put($variableName, json_encode($viewBlockData), 5);

    if (isset($out[3][0]) && strlen($out[3][0]) > 0){
      $hasBlockChildren = "[\"__blockVariables\" => \"".$variableName."\", \"hasChild\" => true, \"variable\" => \"".md5($out[3][0])."\"]";
    } else {
      $hasBlockChildren = "[\"__blockVariables\" => \"\", \"hasChild\" => false, \"variable\" => \"]";
    }

    $parentTemplate = $compiler->getPath();
    $variables = $out[2][0];
    if(substr_count($variables, '[') == 0){
      $variables = $variables.' ,[]';
    }

    $replaceString = '<?php echo Squeezer::embedView(' . $variables . ', get_defined_vars()["__data"], '.$hasBlockChildren.', "'.$parentTemplate.'"); ?>';

    return $replaceString;
  }

  private function registerEmbed($html, $compiler){
    $hasEmbeds = true;
    while($hasEmbeds){
      $tmp = preg_match_all('/(?<!\w)(\s*)((@embed\(.*?\){1,}.*?@endembed))/s', $html, $out);
      if(count($out[0]) > 0){
        foreach ($out[0] as $index => $embedBlock) {
          if(substr_count($embedBlock, '@embed') > 1 && substr_count($embedBlock, '@embed') > substr_count($embedBlock, '@endembed')){
            $lastEmbedPos = strrpos($embedBlock,'@embed');
            $nestledEmbed = substr($embedBlock, $lastEmbedPos);
            $replaceHtml = $this->registerSingleEmbed($nestledEmbed, $compiler);
            $html = str_replace($nestledEmbed, $replaceHtml, $html);
          } else if(substr_count($embedBlock, '@embed') == 1 && substr_count($embedBlock, '@endembed') == 1){
            $replaceHtml = $this->registerSingleEmbed($embedBlock, $compiler);
            $html = str_replace($embedBlock, $replaceHtml, $html);
          } else {

          }
        }
      } else {
        $hasEmbeds = false;
      }
    }

    return $html;
  }

  private function registerNestledEmbed($html, $compiler){
    $tmpNestled = preg_match_all('/(?<!\w)(\s*)@nestledEmbed\(([^\)]+)\)/', $html, $outNestled);
    if(count($outNestled) > 0 && count($outNestled[2]) > 0){
      foreach ($outNestled[0] as $key => $value) {
        $html = str_replace($outNestled[0][$key], $this->nestledEmbeds[$outNestled[2][$key]], $html);
        $html = $this->registerEmbed($html, $compiler);
      }
    }
    return $html;
  }

  public function registerBlock($html, $compiler) {
    $out = [];
    $cachePath = '';
    $variables = [];
    $templateVariables = [];

    $tmp = preg_match_all('/(?<!\w)(\s*)@block\(([^\)]+)\)/', $html, $out);
    if(count($out) > 0 && count($out[2]) > 0){
      //dd($compiler->tmpData);
      if(isset($compiler->tmpData) && array_key_exists('__blockChildData',  $compiler->tmpData)){
        $cacheVariablesName = $compiler->tmpData['__blockChildData']['__blockVariables'];
        $templateVariables = json_decode(\Cache::get($cacheVariablesName), true);
        $variables = $templateVariables['variables'];
      } else {
        $variables = [];
      }
      $templateName = $compiler->getPath();
      $templateName = str_replace(base_path().'/resources/views/', '', $templateName);
      $templateName = str_replace('.blade.php', '', $templateName);
      $templateName = str_replace('/', '.', $templateName);

      foreach ($out[2] as $variableIndex => $variableName) {
        $variableName = str_replace("'", "", $variableName);
        $replaceString = '';
        if(isset($variables[$variableName])) {
          $replaceString = $variables[$variableName];
        }

        $replaceString = $this->registerNestledEmbed($replaceString, $compiler);
        $html = str_replace($out[0][$variableIndex], $replaceString, $html);
      }
    }
    if(count($templateVariables) > 0){
      $cacheVariableName = md5($templateVariables['defaultCode']);
      $cachePath = $compiler->getPath();
      $cachePath = str_replace('.blade.php', $cacheVariableName.'.blade.php', $cachePath);
      $compiler->compileStringToFile($html, $cachePath);
    }
    return $html;
  }

  private function registerStyle($html) {
    $out = [];
    $tmp = preg_match_all('/(?<!\w)(\s*)@style\(([^\)]+)/', $html, $out);
    if(count($out) > 0 && count($out[2]) > 0){
      $variable = $out[2][0];

      $html = preg_replace('/(?<!\w)(\s*)@style\(([^\)]+)\)/', '<?php echo Squeezer::compileStyles(' . $variable . ') ?>', $html);
    }
    return $html;
  }

  private function registerSet($html, $compiler){

    $tmp = preg_match_all('/(?<!\w)(\s*)@set\(([^\)]+)\)/', $html, $out);
    if(count($out) > 0 && count($out[2]) > 0){
      foreach ($out[0] as $key => $value) {
        $variableDef = explode(',', $out[2][$key]);
        $variableName = str_replace("'", "", $variableDef[0]);
        $variable = trim($variableDef[1]);
        $html = str_replace($value, '<?php $'.$variableName.' = '.$variable.'; ?>', $html);
      }
    }
    return $html;
  }

  private function registerClass($html) {
    $tmp = preg_match_all('/(?<!\w)(\s*)@class\(([^\)]+)/', $html, $out);
    if(count($out) > 0 && count($out[2]) > 0){
      $variable = $out[2][0];
      $html = preg_replace('/(?<!\w)(\s*)@class\(([^\)]+)\)/', '<?php echo Squeezer::compileClasses('.$variable.') ?>', $html);
    }
    return $html;
  }

  public function register($html, $compiler, $view){
    $html = $this->registerSet($html, $compiler);
    $html = $this->registerEmbed($html, $compiler);
    $html = $this->registerBlock($html, $compiler);
    $html = $this->registerStyle($html, $compiler);
    $html = $this->registerClass($html, $compiler);
    return $html;
  }

  private function fixClassArray($array){
    $cleanArray = [];
    foreach ($array as $key => $value) {
      if((string)intval($key) == $key){
        $cleanArray[$value] = true;
      } else {
        if ($value !== false && $value !== '' && $value !== null){
          if (is_bool($value) && $value){
            $cleanArray[$key] = true;
          } else {
            $cleanArray[$key] = $value;
          }
        }
      }
    }
    return $cleanArray;
  }

  public function compileClasses($array){
    if(is_object($array)) {
      $array = (array)$array;
    }
    $array = $this->fixClassArray(array_unique($array));
    $classes = '';
    foreach ($array as $key => $value) {
      if($value){
        if ($classes == ''){
          $classes = $key;
        } else {
          $classes = $classes . ' ' .$key;
        }
      }
    }
    $classes = 'class="' . $classes .'"';
    return $classes;
  }

  public function compileStyles($array){
    if(is_object($array)) {
      $array = (array)$array;
    }
    $styles = '';
    foreach (array_filter(array_unique($array)) as $key => $value) {
      if ($value !== null && $value !== 'url()'){
        $styles = $styles . $key . ':' . $value . ';';
      }
    }
    $styles = 'style="' . $styles . '"';
    return $styles;
  }

  public function includeView($view, $data) {
    return view($view)->with($data)->render();
  }

  public function writeBlock($string) {
    return $string;
  }

  public function embedView($view, $data, $viewObjectData=[], $blockChild=[], $parentTemplate=''){
    foreach ($viewObjectData as $key => $value) {
      $data[$key] = $value;
    }

    if(isset($blockChild) && isset($blockChild['hasChild'])){
      $data['__blockChildData'] = $blockChild;
    }

    if($parentTemplate !== ''){
      if(!isset($data['__parentTemplate'])){
        $data['__parentTemplate'] = $parentTemplate;
        $data['__parentTemplateData'] = $data;
      }
    }

    $view = view($view)->with($data);
    $compiler = $view->getEngine()->getCompiler();
    return $compiler->compileString($view->render(), $data);
  }
}
