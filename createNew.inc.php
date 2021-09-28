<?php
$res = $this->res;
$Entity = $this->Entity;
$entity = strToLower($Entity);
$timestamptable = $this->timestamptable;
$html = $this->twigParser(file_get_contents($this->path . 'new.html.twig'), array('entity' => $entity, 'Entity' => $Entity, 'extends' => $this->extend));

//loop on fields
$twigNew = []; // array for stock parser
$twigNew['form_rows'] = ''; //contains string for replace form_rows
$numEditor = 0; //increment nulber for id of editorjs
foreach ($res as $field => $val) {
    $Field = ucfirst($field);
    //jump timestamptables and id
    if (in_array($field, $timestamptable) === false and $field != 'id') {
        $no_new = false;
        //verify show for new
        if (isset($val['ATTR'])) $no_new = in_array('no_new', $val['ATTR']) ? true : false;
        //if no_new insert in twig, field is rendered
        $twigNew['form_rows'] .= $no_new ? ' {% do form.' . $field . '.setRendered() %}' . "\n" : '{{ form_row(form.' . $field . ') }}' . "\n";
        //All fields except no_new and id
        if ($no_new == false) {
            if (isset($val['ALIAS'])) {
                //ALIAS uploadjs
                // he has ATTR file, text or picture
                if ($val['ALIAS'] == 'uploadjs') {
                    $twigNew['form_rows'] .= '<div class="form-group">';
                    //get the type by ATTR with default text
                    if (isset($val['ATTR'])) {
                        $attr = explode('=>', $val['ATTR'][0]);
                        $type = $attr[0];
                    } else {
                        $type = 'new_text';
                    }
                    //file ATTR type for show
                    switch ($type) {
                        case 'new_picture':
                            $size = explode('x', $attr[1]);
                            $sizef = trim($size[0]) == 'auto' ? 'height=' . $size[1] . 'px' : 'width=' . $size[0] . 'px'; //render the html size
                            $twigNew['form_rows'] .= "\n" . "{%if $entity.$field %}\n<img $sizef src=\"{{voir('$field/~$entity.$field')}}\">\n{% endif %}"; // add html form
                            break;
                        case 'new_icon':
                            $twigNew['form_rows'] .= "\n" . "{%if $entity.$field %}\n<img src=\"{{getico('$field/$entity.$field')}}\"> \n{% endif %}"; // add html form
                            break;
                        case 'new_text':
                        default:
                            $twigNew['form_rows'] .= "\n<label class='exNomfile'>{{ $entity.$field }}</label>";
                            break;
                    }
                    $twigNew['form_rows'] .= "\n</div>\n";
                }
                //for editorjs
                if ($val['ALIAS'] == 'editorjs') {
                    $twigNew['form_rows'] .= "<div class='editorjs' id='editorjs_$numEditor'></div>\n";
                    $numEditor += 1;
                }
                //for autocomplete.js
                if ($val['ALIAS'] == 'autocomplete')
                    $twigNew['form_rows'] .= "<input type='hidden' class='autocomplete' data-id='$entity" . "_" . "$field' value='{{autocomplete$Field}}'>\n";
            }
        }
    }
}
$html = $this->twigParser($html, $twigNew);
if ($this->input->getOption('origin')) {
    @mkdir('/app/templates/' . $entity);
    $this->saveFileWithCodes('/app/templates/' . $entity . '/new.html.twig', $html);
} else {
    @mkdir('/app/crudmick/crud');
    file_put_contents('/app/crudmick/crud/' . $Entity . '_new.html.twig', $html);
}
