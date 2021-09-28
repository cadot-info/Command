<?php

use App\CMService\String_functions;

$SF = new String_functions(); //tools for string
$res = $this->res;
$Entity = $this->Entity;
$entity = strToLower($Entity);
$timestamptable = $this->timestamptable;
$html = $this->twigParser(file_get_contents($this->path . 'type.php'), array('entity' => $entity, 'Entity' => $Entity, 'extends' => $this->extend));
//ALIAS to type
$tab_ALIAS = ['uploadjs' => 'file', 'hidden' => 'hidden', 'radio' => 'radio', 'date' => 'date', 'password' => 'password', 'centimetre' => 'CentiMetre', 'metre' => 'metre', 'prix' => 'money', 'autocomplete' => 'text', 'ckeditor' => 'CKEditor', 'tinymce' => 'Textarea', 'editorjs' => 'hidden',  'texte_propre' => 'text', 'email' => 'email', 'color' => 'color', 'phonefr' => 'tel', 'code_postal' => 'text', 'km' => 'number', 'adeli' => 'number'];
//loop on fields
$twigNew = []; // array for stock parser
$twigNew['form_rows'] = ''; //contains string for replace form_rows
//remove timestamptables
unset($res['updatedAt']);
unset($res['createdAt']);
unset($res['deletedAt']);
$adds = ' $builder'; //contents add of builder
$uses = ''; //for parse uses in php file
$collections = ''; //content collections for use
$biblio_use = []; //content uses
$numUpload = 0; //counter for file name
foreach ($res as $field => $val) {
    $type = 'null'; //stock type in fcuntion of tab_ALIAS
    $Field = ucfirst($field);
    $attrs = []; //array of attributes
    $opts = []; //array of options
    //if it's a relation field
    if ($this->is_relation($val['AUTRE']) !== false) {
        //determination of entity for add in use
        foreach ($val['AUTRE'] as $key => $value) {
            $entityRelation = ($SF->chaine_extract($value, 'targetEntity=', '::class'));
            $choiceentitie = false;
            //for choiceentitie
            if (isset($val['ALIAS']))
                if ($val['ALIAS'] == 'choiceEntitie')
                    $choiceentitie = true;
            if ($choiceentitie == true)
                $collections .= "\nuse App\Form\\$entityRelation" . "Type;\n";
            else {
                $type = "CollectionType::class";
                $opts[] = "'entry_type' => $entityRelation" . "Type::class,'entry_options' => ['label' => false],'allow_add' => true,'by_reference' => false,'allow_delete' => true,'required' => false,";
                $attrs[] = "'class' => 'collection'";
                $collections .= "\nuse App\Form\\$entityRelation" . "Type;\n";
                //} else
                if ($entityRelation) {
                    $collections .= "\nuse App\Entity\\$entityRelation;\n";
                }
            }
        }
    }
    if (isset($val['ALIAS'])) {
        if ($val['ALIAS'] == 'uploadjs') {
            $nUpload = $numUpload == 0 ? '' : \strval($numUpload);
            //create files for upload
            file_put_contents("src/Form/CM/Upload$nUpload" . "Type.php", $this->twigParser(file_get_contents('crudmick/php/upload/UploadType.php'), array('upload' => "upload$nUpload", 'Upload' => "Upload$nUpload")));
            file_put_contents("src/Repository/CM/Upload$nUpload" . "Repository.php", $this->twigParser(file_get_contents('crudmick/php/upload/UploadRepository.php'), array('upload' => "upload$nUpload", 'Upload' => "Upload$nUpload")));
            file_put_contents("src/Entity/CM/Upload$nUpload" . ".php", $this->twigParser(file_get_contents('crudmick/php/upload/Upload.php'), array('upload' => "upload$nUpload", 'Upload' => "Upload$nUpload", 'extends' => $this->extend)));
            $type = "FileType::class";
            $opts[] = "'data_class' => null";
            $attrs[] = "'class' => 'uploadjs'";
            $numUpload += 1;
        }
        if ($val['ALIAS'] == 'tinymce') {
            $attrs[] = "'class' => 'tinymce'";
        }
    }


    if ($field != 'id') {
        //for use by ALIAS
        if (isset($val['ALIAS']))
            if (isset($tab_ALIAS[$val['ALIAS']]))
                if (!in_array(ucfirst($tab_ALIAS[$val['ALIAS']]), $biblio_use)) {
                    $biblio_use[] = ucfirst($tab_ALIAS[$val['ALIAS']]);
                    $type = ucfirst($tab_ALIAS[$val['ALIAS']]) . "Type::class";
                }
        //for use by OPT
        if (isset($val['OPT']))
            if ($values = $this->searchInValue($val['OPT'], 'choices')) {
                if (!in_array('Choice', $biblio_use))
                    $biblio_use[] = 'Choice';
                $type = 'Choice' . "Type::class";
            }

        $adds .= "\n->add('$field',$type";
        //for attributes
        if (isset($val['ATTR'])) {
            foreach ($val['ATTR'] as $key => $value) {
                if (isset(explode('=>', $value)[1]))
                    $attrs[] = "'" . explode('=>', $value)[0] . "'=>" . explode('=>', $value)[1];
            }
        }
        //for options
        if (isset($val['OPT'])) {
            foreach ($val['OPT'] as $key => $value) {
                if (isset(explode('=>', $value)[1])) {
                    $val = explode('=>', $value);
                    //exception for choices
                    if ($val[0] == 'choices') {
                        $resChoices = [];
                        //for only value
                        if (substr_count($value, '=>') == 1) {
                            foreach (explode(',', substr(trim($val[1]), 1, -1)) as $choix) {
                                $resChoices[] = $choix . "=>" . $choix;
                            }
                            $opts[] = "'choices'=>[" . implode(',', $resChoices) . "]";
                        } else //for key and value
                            $opts[] =  str_replace("choices=>", "'choices'=>[", $value) . "]";
                    }
                    //for others option
                    else {
                        $opts[] = "'$val[0]'=>$val[1]";
                    }
                }
            }
        }
        //render
        if (count($attrs) or count($opts)) $adds .= ',[';
        if (count($attrs)) $adds .= "'attr'=>[" . implode(',', $attrs) . "]";
        if (count($attrs) and count($opts)) $adds .= ',';
        if (count($opts)) $adds .=  implode(',', $opts);
        if (count($attrs) or count($opts)) $adds .= ']';
        $adds .= ")";
    }
}
//create uses
if ($collections) $uses .= $collections . "\nuse Symfony\Component\Form\Extension\Core\Type\CollectionType;\n";

//create biblio
foreach ($biblio_use as $biblio) {
    switch ($biblio) {
        case 'CKEditor':
            $uses .= "use FOS\CKEditorBundle\Form\Type\\" . $biblio . "Type;\n";
            break;
        case 'CentiMetre':
        case 'Metre':
            $uses .= "use App\Form\Type\\" . $biblio . "Type;\n";
            break;
        default:
            $uses .= "use Symfony\Component\Form\Extension\Core\Type\\" . $biblio . "Type;\n";
            break;
    }
}

//parse type.php with headers
$html = $this->twigParser($html, ['adds' => $adds, 'uses' => $uses]);
//create file
if ($this->input->getOption('origin'))
    $this->saveFileWithCodes('/app/src/Form/' .  $Entity . 'Type.php', $html);
else
    file_put_contents('/app/crudmick/crud/' . $Entity . 'Type.php', $html);
