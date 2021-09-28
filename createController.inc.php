<?php
$res = $this->res;
$Entity = $this->Entity;
$entity = strToLower($Entity);
$timestamptable = $this->timestamptable;
$html = $this->twigParser(file_get_contents($this->path . 'controller.php'), array('entity' => $entity, 'Entity' => $Entity, 'extends' => $this->extend));
//lop for autocomplete
$autocompleteRender = '';
foreach ($res as $field => $val) {
    $Field = ucfirst($field);
    if (isset($val['ALIAS'])) {
        if ($val['ALIAS'] == 'autocomplete') {
            $autocompleteRender .= "'autocomplete$Field'=>\$functionEntitie->getAllOfFields('$entity','$field'),";
        }
    }
}
//parse the html with autocomplete
if ($autocompleteRender) {
    /**@var string $html */
    $html = $this->twigParser($html, array('autocompleteRender' => substr($autocompleteRender, 0, -1), 'autocompleteService' => 'use App\CMService\FunctionEntitie'));
    //specific replacement for php for include Service
    $html = str_replace('new(Request $request)', 'new(Request $request,FunctionEntitie $functionEntitie)', $html);
    $html = str_replace('edit(Request $request', 'edit(Request $request,FunctionEntitie $functionEntitie', $html);
} else
    $html = str_replace('¤autocompleteRender¤,', '', $html);
//create file
if ($this->input->getOption('origin')) {
    $this->saveFileWithCodes('/app/src/Controller/' .  $Entity . 'Controller.php', $html);
} else
    file_put_contents('/app/crudmick/crud/' . $Entity . 'Controller.php', $html);
